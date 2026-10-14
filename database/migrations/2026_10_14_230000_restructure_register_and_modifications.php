<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The restructure register, modification accounting and the rate-reset intake
 * (specification v4 sections 3.9, P5, P6 and P7; built 14 October 2026).
 *
 *  - restructure_events: the CANDIDATE restructures the engine finds in
 *    E-Banker's reschedule history (RS_02a to RS_02f) and in the rate changes
 *    of fixed-rate loans. A candidate is never a confirmed event (review item
 *    R10): one person proposes its classification with a reason, a different
 *    person approves. Nothing here is confirmed by the build or the bootstrap.
 *  - contract_modifications: one row per confirmed event that changes the
 *    accounting (IFRS 9 5.4.3 modification, derecognition and a new version,
 *    or the B5.4.6 catch-up), with the figures the revenue run computed.
 *  - rate_reset_events (shared with the reset pipeline of 2026_10_01_000000,
 *    which owns its `status`): the register's columns, so each move of a
 *    floating loan's rate carries its loan-book evidence and passes
 *    maker-checker in `classification_status`. Each column is added only where
 *    it is missing, so this and 2026_10_01_000000 run in either order.
 *  - eir_amortisation and its history: the adjustment a confirmed event made
 *    to the gross carrying amount in its month, and the schedule version.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('restructure_events')) {
            Schema::create('restructure_events', function (Blueprint $t) {
                $t->id();
                $t->string('detection_key', 120)->unique()->comment('Source and evidence, so a rebuild never duplicates a candidate');
                $t->string('contract_id', 50)->index();
                $t->string('account', 30)->nullable();
                $t->string('customer_name')->nullable();
                $t->date('event_date')->nullable()->comment('Null when the evidence carries no date: the candidate fails closed');
                $t->string('source', 30)->comment('CHART_REPLACED, CHART_COPY, MODIFICATION_LOG, RATE_CHANGE');
                $t->string('source_reference', 500)->nullable();
                $t->string('suggested_class', 30)->comment('The engine\'s suggestion only: CORRECTION, CONTRACTUAL_RESET, MODIFICATION, REVISED_ESTIMATE, UNCERTAIN');
                $t->string('suggestion_reason', 1000)->nullable();
                $t->date('old_maturity')->nullable();
                $t->date('new_maturity')->nullable();
                $t->unsignedSmallInteger('old_instalments')->nullable()->comment('Instalments due after the event, old chart');
                $t->unsignedSmallInteger('new_instalments')->nullable();
                $t->decimal('old_instalment', 20, 2)->nullable();
                $t->decimal('new_instalment', 20, 2)->nullable();
                $t->decimal('old_balance', 20, 2)->nullable()->comment('The old chart\'s expected balance at the event');
                $t->decimal('new_balance', 20, 2)->nullable()->comment('The balance the new chart amortises from');
                $t->decimal('capitalised_amount', 20, 2)->nullable()->comment('New balance less old expected balance less money advanced in between');
                $t->decimal('advanced_in_window', 20, 2)->nullable();
                $t->decimal('old_rate', 10, 5)->nullable();
                $t->decimal('new_rate', 10, 5)->nullable();
                $t->json('old_terms')->nullable();
                $t->json('new_terms')->nullable();
                $t->json('new_flows')->nullable()->comment('The instalments of the new chart due after the event: the modified cash flows');
                $t->string('ebanker_entered_by', 60)->nullable();
                $t->string('ebanker_passed_by', 60)->nullable();
                $t->string('ebanker_flag', 10)->nullable()->comment('MODIFICATION_LOG accepted/rejected flag, or the chart rows\' verify flag');
                $t->string('incomplete_reason', 500)->nullable()->comment('Why the candidate cannot be confirmed as a change in the accounting: no date, no old terms, no new schedule');
                $t->string('status', 12)->default('CANDIDATE')->comment('CANDIDATE, PROPOSED, CONFIRMED');
                $t->string('classification', 30)->nullable()->comment('MODIFICATION, REVISED_ESTIMATE, CONTRACTUAL_RESET, CORRECTION, NOT_A_CHANGE');
                $t->string('reason', 1000)->nullable();
                $t->json('qualitative_factors')->nullable()->comment('IFRS 9 3.2.3 by analogy to B3.3.6: change of borrower, of currency, a feature that fails SPPI, a change of instrument');
                $t->string('board_approval_ref', 200)->nullable()->comment('RBM DFI directive 2018, para 16(1)');
                $t->date('board_approval_date')->nullable();
                $t->decimal('fair_value', 20, 2)->nullable()->comment('Fair value of the new asset if the test derecognises (IFRS 9 5.1.1)');
                $t->string('fair_value_basis', 500)->nullable();
                $t->decimal('modification_fees', 20, 2)->nullable()->comment('Fees received (+) or costs incurred (-) on the modification (IFRS 9 5.4.3)');
                $t->unsignedBigInteger('proposed_by')->nullable();
                $t->timestamp('proposed_at')->nullable();
                $t->unsignedBigInteger('approved_by')->nullable();
                $t->string('approver_label')->nullable();
                $t->timestamp('approved_at')->nullable();
                $t->unsignedBigInteger('closed_by')->nullable();
                $t->timestamp('closed_at')->nullable();
                $t->string('close_reason', 500)->nullable()->comment('The last rejection, withdrawal or reversal, with its reason');
                $t->timestamps();
                $t->index(['status', 'suggested_class']);
            });
        }

        if (! Schema::hasTable('contract_modifications')) {
            Schema::create('contract_modifications', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('restructure_event_id')->index();
                $t->string('contract_id', 50)->index();
                $t->unsignedSmallInteger('sub_account_no')->nullable();
                $t->date('modification_date');
                $t->string('reporting_period', 7)->comment('The month whose closing carries the adjustment');
                $t->string('classification', 30)->comment('MODIFICATION or REVISED_ESTIMATE / CONTRACTUAL_RESET of a fixed-rate loan (B5.4.6)');
                $t->unsignedSmallInteger('old_schedule_version');
                $t->unsignedSmallInteger('new_schedule_version');
                $t->decimal('carrying_amount_before', 20, 2)->nullable();
                $t->decimal('pv_new_flows_at_original_eir', 20, 2)->nullable();
                $t->decimal('eir_used', 12, 8)->nullable()->comment('The EIR the new flows were discounted at: the original EIR, as revised under B5.4.5 for a floating loan');
                $t->decimal('change_pct', 12, 6)->nullable();
                $t->decimal('threshold_pct', 8, 4)->nullable()->comment('modification_threshold in force (O16)');
                $t->json('qualitative_factors')->nullable();
                $t->string('treatment', 20)->nullable()->comment('MODIFICATION, DERECOGNITION, CATCH_UP');
                $t->decimal('gain_loss', 20, 2)->nullable()->comment('Modification or derecognition gain (+) or loss (-), or the B5.4.6 catch-up, to profit or loss');
                $t->decimal('carrying_amount_after', 20, 2)->nullable();
                $t->decimal('modification_fees', 20, 2)->nullable();
                $t->decimal('revised_eir', 12, 8)->nullable()->comment('After fees on a modification, or the new EIR of version N+1 on derecognition');
                $t->decimal('fair_value', 20, 2)->nullable();
                $t->boolean('poci')->default(false);
                $t->boolean('lifetime_ecl_before')->nullable()->comment('Stage 2 or 3 when modified (IFRS 7 35J)');
                $t->decimal('rate_before', 10, 5)->nullable();
                $t->decimal('rate_after', 10, 5)->nullable();
                $t->string('status', 12)->default('PENDING')->comment('PENDING until the revenue run of its month, then APPLIED or BLOCKED; REVERSED');
                $t->string('blocked_reason', 1000)->nullable();
                $t->timestamp('applied_at')->nullable();
                $t->unsignedBigInteger('approved_by')->nullable();
                $t->timestamp('approved_at')->nullable();
                $t->timestamps();
                $t->unique(['contract_id', 'new_schedule_version'], 'uq_contract_modifications_version');
            });
        }

        if (Schema::hasTable('rate_reset_events')) {
            // The first version of this migration named the maker-checker state `status`; the
            // reset pipeline of 2026_10_01_000000 owns `status` (DETECTED, SCHEDULED, SOLVED,
            // BLOCKED). On a database where that first version ran, the column is renamed by
            // 2026_10_01_000000 (it runs after it there); here only while the pipeline's columns
            // are absent, so the pipeline's `status` is never taken for the register's.
            if (Schema::hasColumn('rate_reset_events', 'detection_key') && Schema::hasColumn('rate_reset_events', 'status')
                && ! Schema::hasColumn('rate_reset_events', 'classification_status') && ! Schema::hasColumn('rate_reset_events', 'plr_effective_date')) {
                Schema::table('rate_reset_events', fn (Blueprint $t) => $t->renameColumn('status', 'classification_status'));
            }
            $columns = [
                'detection_key' => fn (Blueprint $t) => $t->string('detection_key', 120)->nullable()->unique(),
                'account' => fn (Blueprint $t) => $t->string('account', 30)->nullable(),
                'reporting_period' => fn (Blueprint $t) => $t->string('reporting_period', 7)->nullable()->comment('The month the move is charged in: E-Banker charges the whole month at the new rate (section 3.1)'),
                'event_type' => fn (Blueprint $t) => $t->string('event_type', 20)->nullable()->comment('PLR_RESET or SPREAD_CHANGE: two events when both move on one date (spec v4 P5)'),
                'old_rate' => fn (Blueprint $t) => $t->decimal('old_rate', 10, 5)->nullable()->comment('Rate charged (loan book), percent'),
                'new_rate' => fn (Blueprint $t) => $t->decimal('new_rate', 10, 5)->nullable(),
                'old_spread' => fn (Blueprint $t) => $t->decimal('old_spread', 10, 5)->nullable()->comment('Rate charged less PLR, percentage points'),
                'new_spread' => fn (Blueprint $t) => $t->decimal('new_spread', 10, 5)->nullable(),
                'evidence' => fn (Blueprint $t) => $t->string('evidence', 500)->nullable(),
                'suggested_classification' => fn (Blueprint $t) => $t->string('suggested_classification', 20)->nullable()->comment('IN_CONTRACT or NEGOTIATED (O17)'),
                'classification' => fn (Blueprint $t) => $t->string('classification', 20)->nullable(),
                'classification_status' => fn (Blueprint $t) => $t->string('classification_status', 12)->default('DETECTED')->comment('Maker-checker: DETECTED, PROPOSED, APPROVED'),
                'reason' => fn (Blueprint $t) => $t->string('reason', 1000)->nullable(),
                'proposed_by' => fn (Blueprint $t) => $t->unsignedBigInteger('proposed_by')->nullable(),
                'proposed_at' => fn (Blueprint $t) => $t->timestamp('proposed_at')->nullable(),
                'approved_by' => fn (Blueprint $t) => $t->unsignedBigInteger('approved_by')->nullable(),
                'approver_label' => fn (Blueprint $t) => $t->string('approver_label')->nullable(),
                'approved_at' => fn (Blueprint $t) => $t->timestamp('approved_at')->nullable(),
                'close_reason' => fn (Blueprint $t) => $t->string('close_reason', 500)->nullable(),
                'restructure_event_id' => fn (Blueprint $t) => $t->unsignedBigInteger('restructure_event_id')->nullable(),
            ];
            foreach ($columns as $name => $add) {
                if (! Schema::hasColumn('rate_reset_events', $name)) {
                    Schema::table('rate_reset_events', fn (Blueprint $t) => $add($t));
                }
            }
            // the pipeline's one-reset-per-date rule widens to one event of each kind per date
            $indexes = array_keys(Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes('rate_reset_events'));
            if (in_array('uq_reset_contract_date', $indexes, true)) {
                Schema::table('rate_reset_events', fn (Blueprint $t) => $t->dropUnique('uq_reset_contract_date'));
            }
            if (! in_array('uq_reset_contract_date_type', $indexes, true) && Schema::hasColumn('rate_reset_events', 'plr_effective_date')) {
                Schema::table('rate_reset_events', fn (Blueprint $t) => $t->unique(['contract_id', 'reset_date', 'event_type'], 'uq_reset_contract_date_type'));
            }
        }

        foreach (['eir_amortisation', 'eir_amortisation_history'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'modification_adjustment')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->decimal('modification_adjustment', 20, 2)->default(0)->comment('A confirmed event\'s change to the gross carrying amount in the month (5.4.3, B5.4.6, or derecognition and recognition of version N+1)');
                    $t->string('adjustment_kind', 20)->nullable();
                    $t->unsignedSmallInteger('schedule_version')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['eir_amortisation', 'eir_amortisation_history'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'modification_adjustment')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn(['modification_adjustment', 'adjustment_kind', 'schedule_version']);
                });
            }
        }
        if (Schema::hasTable('rate_reset_events') && Schema::hasColumn('rate_reset_events', 'detection_key')) {
            $indexes = array_keys(Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes('rate_reset_events'));
            if (in_array('uq_reset_contract_date_type', $indexes, true)) {
                Schema::table('rate_reset_events', fn (Blueprint $t) => $t->dropUnique('uq_reset_contract_date_type'));
            }
            Schema::table('rate_reset_events', fn (Blueprint $t) => $t->dropUnique(['detection_key']));
            foreach (['detection_key', 'account', 'reporting_period', 'event_type', 'old_rate', 'new_rate', 'old_spread', 'new_spread', 'evidence',
                'suggested_classification', 'classification', 'classification_status', 'reason', 'proposed_by', 'proposed_at', 'approved_by', 'approver_label', 'approved_at',
                'close_reason', 'restructure_event_id'] as $column) {
                if (Schema::hasColumn('rate_reset_events', $column)) {
                    Schema::table('rate_reset_events', fn (Blueprint $t) => $t->dropColumn($column));
                }
            }
            // the pipeline's own rule again, while its columns are there
            if (Schema::hasColumn('rate_reset_events', 'plr_effective_date')) {
                Schema::table('rate_reset_events', fn (Blueprint $t) => $t->unique(['contract_id', 'reset_date'], 'uq_reset_contract_date'));
            }
        }
        Schema::dropIfExists('contract_modifications');
        Schema::dropIfExists('restructure_events');
    }
};
