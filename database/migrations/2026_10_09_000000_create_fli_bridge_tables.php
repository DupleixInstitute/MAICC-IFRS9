<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| The forward-looking engines' tables (spec v4 sections 14.3 to 14.6)
|--------------------------------------------------------------------------
| The suite's engines (guardrail, structural-events register, series
| profiler, correlation finder, regression engine) are ported as they are
| and read these tables by name. FliBridgeService fills the three input
| tables from MAIIC's own: macro_series from macro_statistics_data,
| credit_loss_series from the staged loan books, governed_parameters from
| the Governance Centre. The others are the engines' own outputs. The DDL
| is the suite's, column for column, so the engines need no change.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('macro_series', function (Blueprint $t) {
            $t->id(); $t->string('statistic_code', 32); $t->string('statistic_name', 128)->nullable(); $t->char('observation_period', 6); $t->decimal('value', 18, 6);
            $t->string('source', 64)->nullable(); $t->tinyInteger('lag_months')->default(0); $t->enum('value_type', ['actual', 'estimate', 'forecast'])->default('actual');
            $t->string('vintage', 32)->nullable(); $t->dateTime('imported_at')->nullable(); $t->timestamps(); $t->unique(['statistic_code', 'observation_period'], 'uq_macro');
        });
        Schema::create('credit_loss_series', function (Blueprint $t) {
            $t->id(); $t->string('proxy_code', 32); $t->string('proxy_name', 128)->nullable(); $t->char('observation_period', 6); $t->decimal('value', 18, 6); $t->timestamps();
            $t->unique(['proxy_code', 'observation_period'], 'uq_clp');
        });
        Schema::create('governed_parameters', function (Blueprint $t) {
            $t->id(); $t->string('param_key', 96); $t->string('param_value', 191); $t->char('effective_from', 6); $t->char('effective_to', 6)->nullable();
            $t->unsignedBigInteger('changed_by')->nullable(); $t->dateTime('changed_at')->useCurrent(); $t->string('note', 255)->nullable();
            $t->enum('status', ['draft', 'submitted', 'approved', 'retired'])->default('approved'); $t->string('scope_key', 64)->nullable();
            $t->unsignedBigInteger('made_by')->nullable(); $t->unsignedBigInteger('approved_by')->nullable(); $t->dateTime('approved_at')->nullable();
            $t->unique(['param_key', 'effective_from'], 'uq_param');
        });
        Schema::create('regression_definitions', function (Blueprint $t) {
            $t->id(); $t->string('statistic_code', 32); $t->string('proxy_code', 32); $t->enum('expected_sign', ['positive', 'negative']); $t->decimal('r2_cutoff_pct', 6, 3);
            $t->string('comment', 255)->nullable(); $t->timestamps(); $t->unique(['statistic_code', 'proxy_code'], 'uq_regdef');
        });
        Schema::create('structural_events', function (Blueprint $t) {
            $t->id(); $t->string('code', 40)->unique(); $t->string('name', 160); $t->string('event_type', 40); $t->char('event_date', 6)->nullable(); $t->char('end_date', 6)->nullable();
            $t->string('country', 3)->default('MWI'); $t->json('affected_variables')->nullable(); $t->string('direction', 24)->nullable(); $t->string('detection', 16)->default('manual');
            $t->string('status', 16)->default('approved'); $t->string('note', 255)->nullable(); $t->timestamps();
        });
        Schema::create('fli_suggestions', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('run_id')->nullable(); $t->string('statistic_code', 32)->nullable(); $t->string('proxy_code', 32)->nullable(); $t->tinyInteger('lag_months')->nullable();
            $t->decimal('score', 10, 6)->nullable(); $t->decimal('r_squared', 12, 8)->nullable(); $t->boolean('sign_ok')->nullable(); $t->string('verdict', 24)->nullable();
            $t->string('reason', 255)->nullable(); $t->json('diagnostics')->nullable(); $t->dateTime('created_at')->nullable();
        });
        Schema::create('analysis_runs', function (Blueprint $t) {
            $t->id(); $t->enum('run_type', ['correlation', 'regression', 'transition_matrix', 'pd_derivation', 'lgd_derivation', 'sicr', 'cure', 'writeoff', 'stress', 'ecl']);
            $t->char('reporting_period', 6); $t->char('inputs_hash', 64)->nullable(); $t->string('status', 24)->nullable(); $t->unsignedBigInteger('superseded_by')->nullable();
            $t->unsignedBigInteger('run_by')->nullable(); $t->dateTime('run_at')->useCurrent();
        });
        Schema::create('fli_series_distributions', function (Blueprint $t) {
            $t->id(); $t->string('code', 32); $t->string('kind', 16); $t->integer('n_obs')->default(0); $t->char('min_period', 6)->nullable(); $t->char('max_period', 6)->nullable();
            $t->decimal('mean', 20, 6)->nullable(); $t->decimal('stddev', 20, 6)->nullable(); $t->decimal('skewness', 14, 6)->nullable(); $t->decimal('excess_kurtosis', 14, 6)->nullable();
            $t->string('normality_verdict', 24)->nullable(); $t->string('df_verdict', 24)->nullable(); $t->decimal('df_tau', 12, 4)->nullable(); $t->decimal('df_crit_5pct', 12, 4)->nullable();
            $t->string('recommended_method', 32)->nullable(); $t->string('distribution_label', 96)->nullable(); $t->dateTime('computed_at')->nullable(); $t->unique(['code', 'kind'], 'uq_series_dist');
        });
        Schema::create('fli_relationships', function (Blueprint $t) {
            $t->id(); $t->string('statistic_code', 32); $t->string('proxy_code', 32); $t->unsignedBigInteger('business_unit_id')->nullable(); $t->enum('expected_sign', ['positive', 'negative'])->nullable();
            $t->string('sign_status', 12)->default('provisional'); $t->string('sign_source', 24)->nullable(); $t->string('sign_note', 255)->nullable(); $t->unsignedBigInteger('sign_approved_by')->nullable(); $t->dateTime('sign_approved_at')->nullable();
            $t->decimal('r2_cutoff', 6, 4); $t->enum('method', ['pearson', 'spearman', 'robust'])->default('pearson'); $t->enum('mode', ['single', 'multivariate'])->default('single');
            $t->tinyInteger('lag_months')->default(0); $t->boolean('is_active')->default(true); $t->timestamps(); $t->unique(['statistic_code', 'proxy_code', 'business_unit_id', 'lag_months'], 'uq_rel');
        });
        Schema::create('fli_fits', function (Blueprint $t) {
            $t->id(); $t->foreignId('fli_relationship_id')->constrained('fli_relationships'); $t->char('reporting_period', 6); $t->decimal('slope', 18, 8)->nullable(); $t->decimal('intercept', 18, 8)->nullable();
            $t->decimal('correlation_r', 12, 8)->nullable(); $t->decimal('r_squared', 12, 8)->nullable(); $t->decimal('p_value', 12, 8)->nullable(); $t->integer('n_obs')->nullable(); $t->decimal('n_years', 6, 2)->nullable();
            $t->boolean('sign_ok')->nullable(); $t->enum('verdict', ['applied', 'declined']); $t->string('declined_reason', 128)->nullable(); $t->char('inputs_hash', 64)->nullable();
            $t->unsignedBigInteger('supporting_document_id')->nullable(); $t->dateTime('computed_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['fli_fits', 'fli_relationships', 'fli_series_distributions', 'analysis_runs', 'fli_suggestions', 'structural_events', 'regression_definitions', 'governed_parameters', 'credit_loss_series', 'macro_series'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
