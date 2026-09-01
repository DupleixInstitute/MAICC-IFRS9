<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Older test/production databases already contain reviewed and locked
        // EIRs. Preserve that historical conclusion; do not make a locked
        // original EIR unusable merely because governance columns arrived later.
        DB::table('contract_eir')->where('schedule_approval_status', 'NOT_GENERATED')
            ->whereNotNull('locked_at')->whereExists(function ($q) {
                $q->selectRaw('1')->from('contract_cashflow_schedule as s')
                    ->whereColumn('s.contract_id', 'contract_eir.contract_id')->where('s.schedule_version', 1);
            })->update([
                'schedule_approval_status' => 'APPROVED',
                'schedule_review_notes' => 'Legacy locked EIR backfilled during schedule-governance migration.',
                'schedule_approved_at' => DB::raw('locked_at'),
                'schedule_approved_by' => DB::raw('locked_by'),
            ]);

        DB::table('contract_eir')->where('schedule_approval_status', 'NOT_GENERATED')
            ->whereNull('locked_at')->whereExists(function ($q) {
                $q->selectRaw('1')->from('contract_cashflow_schedule as s')
                    ->whereColumn('s.contract_id', 'contract_eir.contract_id')->where('s.schedule_version', 1);
            })->update(['schedule_approval_status' => 'DRAFT']);

        Schema::create('ecl_projection_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('run_id')->unique();
            $table->string('reporting_period', 7)->index();
            $table->string('scope_type', 20)->default('PORTFOLIO');
            $table->string('scope_value')->nullable();
            $table->string('methodology_version', 30)->default('TIME_PHASED_V1');
            $table->string('status', 20)->default('PROCESSING');
            $table->unsignedInteger('contracts_processed')->default(0);
            $table->unsignedInteger('contracts_unresolved')->default(0);
            $table->decimal('undiscounted_ecl', 20, 2)->default(0);
            $table->decimal('discounted_ecl', 20, 2)->default(0);
            $table->json('input_snapshot')->nullable();
            $table->json('exceptions')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ecl_scenario_assumptions', function (Blueprint $table) {
            $table->id();
            $table->string('scenario_code', 30);
            $table->string('name');
            $table->decimal('weight', 10, 8);
            $table->decimal('pd_multiplier', 10, 6)->default(1);
            $table->decimal('lgd_multiplier', 10, 6)->default(1);
            $table->decimal('ead_multiplier', 10, 6)->default(1);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('status', 20)->default('APPROVED');
            $table->text('rationale')->nullable();
            $table->timestamps();
            $table->unique(['scenario_code', 'effective_from'], 'uq_ecl_scenario_effective');
        });

        Schema::create('ecl_pd_term_structures', function (Blueprint $table) {
            $table->id();
            $table->string('contract_id')->index();
            $table->string('reporting_period', 7);
            $table->string('scenario_code', 30);
            $table->unsignedSmallInteger('period_index');
            $table->date('projection_date');
            $table->decimal('conditional_pd', 14, 10);
            $table->decimal('survival_open', 14, 10);
            $table->decimal('marginal_pd', 14, 10);
            $table->decimal('cumulative_pd', 14, 10);
            $table->string('source', 40)->default('SCALAR_PD_FLAT_HAZARD');
            $table->timestamps();
            $table->unique(['contract_id','reporting_period','scenario_code','period_index'], 'uq_pd_term_contract_scenario_period');
        });

        Schema::create('ecl_cashflow_projections', function (Blueprint $table) {
            $table->id();
            $table->uuid('run_id')->index();
            $table->string('contract_id')->index();
            $table->string('reporting_period', 7);
            $table->unsignedTinyInteger('ifrs9_stage');
            $table->string('scenario_code', 30);
            $table->decimal('scenario_weight', 10, 8);
            $table->unsignedSmallInteger('period_index');
            $table->date('projection_date');
            $table->decimal('opening_ead', 20, 2);
            $table->decimal('scheduled_principal', 20, 2)->default(0);
            $table->decimal('closing_ead', 20, 2);
            $table->decimal('conditional_pd', 14, 10);
            $table->decimal('survival_open', 14, 10);
            $table->decimal('marginal_pd', 14, 10);
            $table->decimal('cumulative_pd', 14, 10);
            $table->decimal('lgd', 14, 10);
            $table->decimal('undiscounted_shortfall', 20, 2);
            $table->decimal('discount_rate', 14, 10);
            $table->decimal('discount_exponent', 14, 10);
            $table->decimal('discount_factor', 14, 10);
            $table->decimal('discounted_shortfall', 20, 2);
            $table->decimal('weighted_discounted_shortfall', 20, 2);
            $table->string('rate_source', 40);
            $table->string('pd_source', 40);
            $table->string('lgd_source', 40);
            $table->timestamps();
            $table->unique(['run_id','contract_id','scenario_code','period_index'], 'uq_ecl_projection_run_contract_scenario_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecl_cashflow_projections');
        Schema::dropIfExists('ecl_pd_term_structures');
        Schema::dropIfExists('ecl_scenario_assumptions');
        Schema::dropIfExists('ecl_projection_runs');
    }
};
