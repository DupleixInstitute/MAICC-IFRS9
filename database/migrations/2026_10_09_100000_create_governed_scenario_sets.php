<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| The scenario set as a governed object (spec v4 section 15.4)
|--------------------------------------------------------------------------
| One set per reporting period with versions; three or more scenarios under
| it with weights that sum to 100; every scenario but the base expressed as
| shocks on the base path, by series and year offset, so a downside is an
| auditable transformation of the base. The back-test and the sensitivity
| are stored with the set. MAIIC's older scenario_sets / scenario_probabilities
| stay for the screens that read them.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('governed_scenario_sets', function (Blueprint $t) {
            $t->id();
            $t->string('reporting_period', 7)->index();
            $t->string('name');
            $t->unsignedInteger('version')->default(1);
            $t->string('status', 12)->default('DRAFT')->comment('DRAFT | PROPOSED | APPROVED | LOCKED | SUPERSEDED');
            $t->text('narrative')->nullable();
            $t->string('source_vintage')->nullable()->comment('e.g. IMF WEO April 2026; World Bank 2025 actuals; RBM MPC March 2026');
            $t->foreignId('proposed_by')->nullable()->constrained('users');
            $t->timestamp('proposed_at')->nullable();
            $t->foreignId('approved_by')->nullable()->constrained('users');
            $t->string('approver_label')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('locked_at')->nullable();
            $t->unsignedBigInteger('supersedes_id')->nullable();
            $t->string('version_reason')->nullable();
            $t->json('validation')->nullable();
            $t->json('backtest')->nullable();
            $t->json('sensitivity')->nullable();
            $t->timestamps();
            $t->unique(['reporting_period', 'version']);
        });
        Schema::create('governed_scenarios', function (Blueprint $t) {
            $t->id();
            $t->foreignId('set_id')->constrained('governed_scenario_sets')->cascadeOnDelete();
            $t->string('name', 40);
            $t->decimal('weight', 6, 2)->comment('percent; the set sums to 100');
            $t->boolean('is_base')->default(false);
            $t->text('narrative')->nullable();
            $t->string('anchored_to')->nullable()->comment('The year Malawi lived through that the scenario is calibrated to');
            $t->text('calibration_note')->nullable();
            $t->decimal('pd_multiplier', 8, 4)->nullable()->comment('The scenario\'s effect on the pre-FLI PD until a fit per scenario exists');
            $t->unsignedTinyInteger('order_position')->default(0);
            $t->timestamps();
        });
        Schema::create('scenario_shocks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('scenario_id')->constrained('governed_scenarios')->cascadeOnDelete();
            $t->string('statistic_code', 32);
            $t->tinyInteger('year_offset')->default(0)->comment('0 = the first forecast year');
            $t->string('kind', 12)->comment('pct | abs | replace | mult');
            $t->decimal('value', 18, 6);
            $t->string('note')->nullable();
            $t->timestamps();
            $t->unique(['scenario_id', 'statistic_code', 'year_offset']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scenario_shocks');
        Schema::dropIfExists('governed_scenarios');
        Schema::dropIfExists('governed_scenario_sets');
    }
};
