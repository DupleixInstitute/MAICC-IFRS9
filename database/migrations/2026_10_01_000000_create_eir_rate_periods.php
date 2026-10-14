<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase P5, floating-rate resets (spec v3 sections 6.3 and 7.3, decision D8).
 *
 * A loan that follows the Reserve Bank prime rate has one EIR per stretch of
 * time between rate changes, not one for its life (IFRS 9 B5.4.5). contract_eir
 * keeps the origination record and is never overwritten; each rate period is a
 * row here: the prime rate, the locked spread and the contract rate in force.
 *
 * Rate periods and schedule versions are not the same thing: a reset opens a
 * rate period and a schedule version, but arrears (P6) open a schedule
 * version and keep the EIR. So the period has its own table, and each
 * schedule row says what kind of version it belongs to.
 *
 * The EIR a floating loan accrues at is decision D38 (spec v4, 13 October
 * 2026): the monthly re-estimation at the rate E-Banker charged, recorded on
 * each roll-forward row (eir_amortisation.eir_annual) and read by the ECL as
 * the current EIR. The EIR columns of a rate period are not a second source of
 * it: no engine solves or reads them (reconciliation of 14 October 2026).
 *
 * rate_reset_events is shared with the maker-checker register of migration
 * 2026_10_14_230000. This migration owns the pipeline's columns, `status`
 * (DETECTED, SCHEDULED, SOLVED, BLOCKED) among them; the register's own state
 * is `classification_status`. Every column is added only where it is missing,
 * so the two migrations run in either order. On a database where the
 * register's first version ran (its `status` held DETECTED, PROPOSED or
 * APPROVED) that column is renamed classification_status before the
 * pipeline's `status` is added.
 *
 * Units, as elsewhere in the engine: reference_rate and spread_over_prime in
 * percentage points as the rate series and the loan books state them (21.20,
 * 8.00); contractual_rate and the EIR columns as fractions, as on contract_eir
 * (0.2920).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('eir_rate_periods')) {
            Schema::create('eir_rate_periods', function (Blueprint $table) {
                $table->id();
                $table->string('contract_id')->index();
                $table->string('sub_account_no', 20)->nullable();
                $table->unsignedSmallInteger('period_no')->comment('1 at origination, then one per reset');
                $table->date('effective_from');
                $table->date('effective_to')->nullable()->comment('Null for the period in force');
                $table->string('index_code', 20)->default('PLR');
                $table->decimal('reference_rate', 8, 5)->nullable()->comment('Prime rate in force, percentage points');
                $table->decimal('spread_over_prime', 8, 5)->nullable()->comment('Locked spread, percentage points');
                $table->string('spread_basis', 30)->nullable()->comment('Governance margin_basis the spread was taken on');
                $table->decimal('contractual_rate', 10, 7)->nullable()->comment('Reference + spread, as a fraction');
                $table->decimal('opening_amortised_cost', 20, 2)->nullable();
                $table->decimal('eir_period', 12, 10)->nullable()->comment('Not an EIR source: the EIR in force is D38 (eir_amortisation.eir_annual)');
                $table->decimal('eir_nominal_annual', 12, 10)->nullable();
                $table->decimal('eir_effective_annual', 12, 10)->nullable();
                $table->decimal('eir_resolved_effective_annual', 12, 10)->nullable()->comment('Re-solve on the new flows, the test oracle');
                $table->boolean('eir_disagreement_flag')->default(false);
                $table->string('solver_method', 30)->nullable();
                $table->unsignedSmallInteger('solver_iterations')->nullable();
                $table->decimal('solver_residual', 20, 10)->nullable();
                $table->json('input_snapshot')->nullable();
                $table->foreignId('rate_reset_event_id')->nullable()->constrained('rate_reset_events')->nullOnDelete();
                $table->unsignedSmallInteger('schedule_version')->nullable()->comment('The schedule version built for this period');
                $table->string('status', 20)->default('PENDING_EIR')->comment('PENDING_EIR | SOLVED | BLOCKED | LOCKED');
                $table->text('note')->nullable();
                $table->timestamp('locked_at')->nullable();
                $table->foreignId('locked_by')->nullable()->constrained('users');
                $table->timestamps();

                $table->unique(['contract_id', 'effective_from'], 'uq_rate_period_contract_from');
            });
        }

        // The register's first version (2026_10_14_230000 as committed in 6b57002) named its
        // maker-checker state `status`: it is the register's classification_status.
        if (Schema::hasColumn('rate_reset_events', 'detection_key') && Schema::hasColumn('rate_reset_events', 'status')
            && ! Schema::hasColumn('rate_reset_events', 'classification_status')) {
            Schema::table('rate_reset_events', function (Blueprint $table) {
                $table->renameColumn('status', 'classification_status');
            });
        }

        $columns = [
            'plr_effective_date' => fn (Blueprint $t) => $t->date('plr_effective_date')->nullable()
                ->comment('The Reserve Bank date; reset_date is when the engine applies it (plr_mid_period)'),
            'index_code' => fn (Blueprint $t) => $t->string('index_code', 20)->default('PLR'),
            'spread_over_prime' => fn (Blueprint $t) => $t->decimal('spread_over_prime', 8, 5)->nullable(),
            'old_contractual_rate' => fn (Blueprint $t) => $t->decimal('old_contractual_rate', 10, 7)->nullable(),
            'new_contractual_rate' => fn (Blueprint $t) => $t->decimal('new_contractual_rate', 10, 7)->nullable(),
            'status' => fn (Blueprint $t) => $t->string('status', 20)->default('DETECTED')
                ->comment('The reset pipeline: DETECTED | SCHEDULED | SOLVED | BLOCKED'),
            'note' => fn (Blueprint $t) => $t->text('note')->nullable(),
        ];
        foreach ($columns as $name => $add) {
            if (! Schema::hasColumn('rate_reset_events', $name)) {
                Schema::table('rate_reset_events', fn (Blueprint $t) => $add($t));
            }
        }

        // One reset of each kind per loan and date. Where the register's event_type is already
        // there (it ran first) the rule covers it: a prime-rate reset and a spread change on the
        // same date are two events (spec v4 P5); otherwise 2026_10_14_230000 widens it.
        $indexes = array_keys(Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes('rate_reset_events'));
        if (! in_array('uq_reset_contract_date_type', $indexes, true) && ! in_array('uq_reset_contract_date', $indexes, true)) {
            $withType = Schema::hasColumn('rate_reset_events', 'event_type');
            Schema::table('rate_reset_events', function (Blueprint $table) use ($withType) {
                $withType
                    ? $table->unique(['contract_id', 'reset_date', 'event_type'], 'uq_reset_contract_date_type')
                    : $table->unique(['contract_id', 'reset_date'], 'uq_reset_contract_date');
            });
        }

        // A reset is detected before its schedule exists; the version is
        // written when the reset schedule is built (step 3).
        Schema::table('rate_reset_events', function (Blueprint $table) {
            $table->unsignedSmallInteger('new_schedule_version')->nullable()->change();
        });

        if (! Schema::hasColumn('contract_cashflow_schedule', 'schedule_type')) {
            Schema::table('contract_cashflow_schedule', function (Blueprint $table) {
                $table->string('schedule_type', 20)->default('ORIGINAL')->after('schedule_version')
                    ->comment('ORIGINAL | RESET | ARREARS | RESTRUCTURE');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('contract_cashflow_schedule', 'schedule_type')) {
            Schema::table('contract_cashflow_schedule', fn (Blueprint $t) => $t->dropColumn('schedule_type'));
        }
        $indexes = array_keys(Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes('rate_reset_events'));
        foreach (['uq_reset_contract_date', 'uq_reset_contract_date_type'] as $index) {
            if (in_array($index, $indexes, true)) {
                Schema::table('rate_reset_events', fn (Blueprint $t) => $t->dropUnique($index));
            }
        }
        foreach (['plr_effective_date', 'index_code', 'spread_over_prime', 'old_contractual_rate', 'new_contractual_rate', 'status', 'note'] as $column) {
            if (Schema::hasColumn('rate_reset_events', $column)) {
                Schema::table('rate_reset_events', fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
        Schema::dropIfExists('eir_rate_periods');
    }
};
