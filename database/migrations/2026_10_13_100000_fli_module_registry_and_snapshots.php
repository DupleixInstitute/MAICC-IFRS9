<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| The forward-looking module (FLI module specification v1, 13 October 2026)
|--------------------------------------------------------------------------
| D-F2  fli_models and fli_model_versions: an approved fit is published as a
|       model version with an effective month; the route reads the version in
|       force at the reporting date, so a month-end applies the model and
|       does not have to re-fit it (D-F1).
| D-F3  ecl_scenario_results: the ECL of each loan under each scenario, so
|       the sensitivity, the impact screen and the booked allowance read the
|       same rows.
| D-F11 fli_run_snapshots: what one month-end ran under, in one row.
| D-F10 published_at on the macro and credit-loss observations: the date the
|       publisher released the figure, which the as-at reader honours.
| D-F9  risk_driver and double_count_review on the overlay register.
| The weighted forecast and the scenario profiles are tied to the governed
| scenario sets (governed_set_id, statistic_code, year_offset).
|
| The approved fits already in the database are published as model versions
| (one per period, effective from that period), so every month that ran
| before this migration keeps the model it ran under.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fli_models')) {
            Schema::create('fli_models', function (Blueprint $t) {
                $t->id();
                $t->string('code', 32)->unique();
                $t->string('name', 128);
                $t->string('target_metric', 8)->default('PD');
                $t->string('segment_basis', 32)->default('book');
                $t->string('description', 500)->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('fli_model_versions')) {
            Schema::create('fli_model_versions', function (Blueprint $t) {
                $t->id();
                $t->foreignId('model_id');
                $t->unsignedInteger('version');
                $t->unsignedBigInteger('fli_fit_id')->nullable();
                $t->string('statistic_code', 32);
                $t->string('proxy_code', 32);
                $t->integer('lag_months')->default(0);
                $t->decimal('slope', 20, 10);
                $t->decimal('intercept', 20, 10);
                $t->decimal('r_squared', 12, 8)->nullable();
                $t->decimal('p_value', 12, 8)->nullable();
                $t->unsignedInteger('n_obs')->nullable();
                $t->string('calibrated_period', 7)->comment('The month whose data the fit was estimated on');
                $t->string('effective_from', 7)->comment('The first month-end the version governs');
                $t->string('status', 12)->default('PUBLISHED')->comment('PUBLISHED, SUPERSEDED or RETIRED');
                $t->unsignedBigInteger('proposed_by')->nullable();
                $t->unsignedBigInteger('published_by')->nullable();
                $t->string('approver_label', 199)->nullable();
                $t->timestamp('published_at')->nullable();
                $t->unsignedBigInteger('retired_by')->nullable();
                $t->timestamp('retired_at')->nullable();
                $t->string('retired_reason', 500)->nullable();
                $t->string('note', 500)->nullable();
                $t->timestamps();
                $t->unique(['model_id', 'version']);
                $t->index(['status', 'effective_from']);
            });
        }
        if (! Schema::hasTable('fli_run_snapshots')) {
            Schema::create('fli_run_snapshots', function (Blueprint $t) {
                $t->id();
                $t->string('reporting_period', 7)->index();
                $t->unsignedBigInteger('model_version_id')->nullable();
                $t->unsignedBigInteger('fit_id')->nullable();
                $t->unsignedBigInteger('set_id')->nullable();
                $t->unsignedInteger('set_version')->nullable();
                $t->string('route', 64)->nullable();
                $t->string('method', 64)->nullable();
                $t->string('weighting', 64)->nullable();
                $t->text('overlay_ids')->nullable();
                $t->string('macro_vintage', 128)->nullable();
                $t->unsignedInteger('loans')->default(0);
                $t->decimal('pre_fli_ecl', 20, 2)->nullable();
                $t->decimal('model_effect', 20, 2)->nullable();
                $t->decimal('overlay_effect', 20, 2)->nullable();
                $t->decimal('booked_ecl', 20, 2)->nullable();
                $t->decimal('weighted_ecl', 20, 2)->nullable();
                $t->decimal('difference', 20, 2)->nullable();
                $t->boolean('reconciles')->default(false);
                $t->longText('per_scenario')->nullable();
                $t->longText('by_stage')->nullable();
                $t->longText('by_portfolio')->nullable();
                $t->longText('inputs')->nullable();
                $t->decimal('prior_booked_ecl', 20, 2)->nullable();
                $t->decimal('movement_pct', 12, 6)->nullable();
                $t->boolean('needs_commentary')->default(false);
                $t->text('commentary')->nullable();
                $t->string('status', 12)->default('RECORDED')->comment('RECORDED, SIGNED_OFF or SUPERSEDED');
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('signed_off_by')->nullable();
                $t->string('approver_label', 199)->nullable();
                $t->timestamp('signed_off_at')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('ecl_scenario_results')) {
            Schema::create('ecl_scenario_results', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('snapshot_id')->index();
                $t->string('reporting_period', 7);
                $t->unsignedBigInteger('loan_book_id');
                $t->string('contract_id', 199)->nullable();
                $t->unsignedBigInteger('loan_portfolio_id')->nullable();
                $t->string('stage', 4)->nullable();
                $t->string('scenario', 64);
                $t->decimal('weight', 7, 3);
                $t->decimal('pd12', 14, 10)->nullable();
                $t->decimal('stage_pd', 14, 10)->nullable();
                $t->decimal('ecl', 20, 2);
                $t->timestamps();
                $t->index(['reporting_period', 'scenario']);
            });
        }
        if (Schema::hasTable('loan_books') && ! Schema::hasColumn('loan_books', 'fli_model_version_id')) {
            Schema::table('loan_books', fn (Blueprint $t) => $t->unsignedBigInteger('fli_model_version_id')->nullable());
        }
        if (Schema::hasTable('macro_statistics_data') && ! Schema::hasColumn('macro_statistics_data', 'published_at')) {
            Schema::table('macro_statistics_data', fn (Blueprint $t) => $t->date('published_at')->nullable()->comment('The date the publisher released the figure (D-F10)'));
        }
        if (Schema::hasTable('macro_credit_loss_data')) {
            Schema::table('macro_credit_loss_data', function (Blueprint $t) {
                if (! Schema::hasColumn('macro_credit_loss_data', 'published_at')) {
                    $t->date('published_at')->nullable()->comment('The date the publisher released the figure');
                }
            });
        }
        if (Schema::hasTable('fli_overlays')) {
            Schema::table('fli_overlays', function (Blueprint $t) {
                if (! Schema::hasColumn('fli_overlays', 'risk_driver')) {
                    $t->string('risk_driver', 64)->nullable()->comment('The driver, sector or event the overlay covers (D-F9)');
                }
                if (! Schema::hasColumn('fli_overlays', 'double_count_review')) {
                    $t->text('double_count_review')->nullable()->comment('Why the model in force does not already carry this risk');
                }
            });
        }
        if (Schema::hasTable('macro_forecast_weighted')) {
            Schema::table('macro_forecast_weighted', function (Blueprint $t) {
                foreach (['governed_set_id' => 'unsignedBigInteger', 'year_offset' => 'integer'] as $c => $type) {
                    if (! Schema::hasColumn('macro_forecast_weighted', $c)) {
                        $t->{$type}($c)->nullable();
                    }
                }
                if (! Schema::hasColumn('macro_forecast_weighted', 'reporting_period')) {
                    $t->string('reporting_period', 7)->nullable();
                }
                if (! Schema::hasColumn('macro_forecast_weighted', 'statistic_code')) {
                    $t->string('statistic_code', 32)->nullable();
                }
                if (! Schema::hasColumn('macro_forecast_weighted', 'scenario_values')) {
                    $t->text('scenario_values')->nullable();
                }
            });
            if (DB::getDriverName() === 'mysql') {
                // the weighted forecast is now per governed set; the legacy keys may be empty
                DB::statement('ALTER TABLE macro_forecast_weighted MODIFY scenario_profile_id BIGINT UNSIGNED NULL, MODIFY macro_statistic_id BIGINT UNSIGNED NULL, MODIFY reporting_period_id BIGINT UNSIGNED NULL');
            }
        }
        if (Schema::hasTable('macro_credit_loss_data') && DB::getDriverName() === 'mysql') {
            // an external series (an industry NPL ratio) belongs to no MAIIC portfolio
            DB::statement('ALTER TABLE macro_credit_loss_data MODIFY portfolio_id BIGINT UNSIGNED NULL');
        }

        $this->publishApprovedFits();
    }

    /** Every approved fit becomes a published version effective from its own period. */
    private function publishApprovedFits(): void
    {
        if (! Schema::hasTable('fli_fits') || ! Schema::hasTable('fli_relationships')) {
            return;
        }
        $modelId = DB::table('fli_models')->where('code', 'PD-FLI-BOOK')->value('id') ?? DB::table('fli_models')->insertGetId([
            'code' => 'PD-FLI-BOOK', 'name' => 'Book-level PD forward-looking model', 'target_metric' => 'PD', 'segment_basis' => 'book',
            'description' => 'One driver against one credit-loss measure, fitted under the guardrail; its version in force moves every loan\'s PD under each scenario.',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $fits = DB::table('fli_fits as f')->join('fli_relationships as r', 'r.id', '=', 'f.fli_relationship_id')
            ->where('f.approval_status', 'APPROVED')->orderBy('f.reporting_period')
            ->get(['f.id', 'f.reporting_period', 'f.slope', 'f.intercept', 'f.r_squared', 'f.p_value', 'f.n_obs', 'f.proposed_by', 'f.approved_by', 'f.approver_label', 'f.approved_at', 'r.statistic_code', 'r.proxy_code', 'r.lag_months']);
        $version = (int) DB::table('fli_model_versions')->where('model_id', $modelId)->max('version');
        foreach ($fits as $f) {
            if (DB::table('fli_model_versions')->where('fli_fit_id', $f->id)->exists()) {
                continue;
            }
            $period = substr((string) $f->reporting_period, 0, 4) . '-' . substr((string) $f->reporting_period, 4, 2);
            DB::table('fli_model_versions')->insert([
                'model_id' => $modelId, 'version' => ++$version, 'fli_fit_id' => $f->id, 'statistic_code' => $f->statistic_code, 'proxy_code' => $f->proxy_code,
                'lag_months' => (int) $f->lag_months, 'slope' => $f->slope, 'intercept' => $f->intercept, 'r_squared' => $f->r_squared, 'p_value' => $f->p_value, 'n_obs' => $f->n_obs,
                'calibrated_period' => $period, 'effective_from' => $period, 'status' => 'PUBLISHED', 'proposed_by' => $f->proposed_by, 'published_by' => $f->approved_by,
                'approver_label' => $f->approver_label, 'published_at' => $f->approved_at ?? now(),
                'note' => 'Published from the fit approved for ' . $period . ' when the model registry was introduced (13 October 2026).',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        // the version each loan ran under
        if (Schema::hasColumn('loan_books', 'fli_model_version_id')) {
            foreach (DB::table('fli_model_versions')->whereNotNull('fli_fit_id')->get(['id', 'fli_fit_id']) as $v) {
                DB::table('loan_books')->where('fli_fit_id', $v->fli_fit_id)->whereNull('fli_model_version_id')->update(['fli_model_version_id' => $v->id]);
            }
        }
    }

    public function down(): void
    {
        foreach (['ecl_scenario_results', 'fli_run_snapshots', 'fli_model_versions', 'fli_models'] as $t) {
            Schema::dropIfExists($t);
        }
        if (Schema::hasColumn('loan_books', 'fli_model_version_id')) {
            Schema::table('loan_books', fn (Blueprint $t) => $t->dropColumn('fli_model_version_id'));
        }
        if (Schema::hasColumn('macro_statistics_data', 'published_at')) {
            Schema::table('macro_statistics_data', fn (Blueprint $t) => $t->dropColumn('published_at'));
        }
        if (Schema::hasColumn('macro_credit_loss_data', 'published_at')) {
            Schema::table('macro_credit_loss_data', fn (Blueprint $t) => $t->dropColumn('published_at'));
        }
        Schema::table('fli_overlays', function (Blueprint $t) {
            foreach (['risk_driver', 'double_count_review'] as $c) {
                if (Schema::hasColumn('fli_overlays', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
        Schema::table('macro_forecast_weighted', function (Blueprint $t) {
            foreach (['governed_set_id', 'year_offset', 'reporting_period', 'statistic_code', 'scenario_values'] as $c) {
                if (Schema::hasColumn('macro_forecast_weighted', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
