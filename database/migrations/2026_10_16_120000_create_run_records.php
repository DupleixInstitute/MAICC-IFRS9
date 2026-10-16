<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The record of what each month ran on (Staging Module Specification v1,
 * section 8.8, items 1 to 3; acceptance 16 and 17).
 *
 *   run_records          one row per run of a month: the month-end job, a
 *                        bootstrap, or an engine run by hand on a screen
 *                        (SCREEN, never the run of record). Who, when, the
 *                        code version, the steps the engines took, and at the
 *                        end a frozen snapshot of every input with its SHA-256
 *                        fingerprint and the month's headline results
 *   run_record_id        on every result table: the run that last wrote the
 *                        row, for the trace from a loan's figure to its run
 *   reporting_period_locks.run_record_id
 *                        the run whose figures the locked month reports
 */
return new class extends Migration
{
    /** The result tables that carry the run number. */
    public const RESULT_TABLES = ['loan_books', 'expected_credit_loss', 'segment_parameter_runs', 'segment_parameter_results', 'loss_given_default',
        'megafarm_ecl_runs', 'eir_amortisation', 'fli_run_snapshots', 'ecl_projection_runs', 'ecl_scenario_results'];

    public function up(): void
    {
        if (! Schema::hasTable('run_records')) {
            Schema::create('run_records', function (Blueprint $t) {
                $t->id();
                $t->string('reporting_period', 7)->index();
                $t->string('kind', 20);                 // MONTH_END, BOOTSTRAP or SCREEN
                $t->string('status', 20)->index();      // RUNNING, COMPLETED, FAILED, SUPERSEDED
                $t->string('label', 255)->nullable();
                $t->unsignedBigInteger('started_by')->nullable();
                $t->timestamp('started_at')->nullable();
                $t->timestamp('finished_at')->nullable();
                $t->string('code_version', 64)->nullable();
                $t->json('steps')->nullable();
                $t->longText('snapshot')->nullable();
                $t->char('fingerprint', 64)->nullable();
                $t->json('results')->nullable();
                $t->text('error')->nullable();
                $t->unsignedBigInteger('superseded_by_run')->nullable();
                $t->timestamp('of_record_at')->nullable();
                $t->unsignedBigInteger('of_record_by')->nullable();
                $t->timestamp('of_record_until')->nullable();
                $t->timestamps();
            });
        }
        foreach (self::RESULT_TABLES as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'run_record_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->unsignedBigInteger('run_record_id')->nullable()->index());
            }
        }
        if (Schema::hasTable('reporting_period_locks') && ! Schema::hasColumn('reporting_period_locks', 'run_record_id')) {
            Schema::table('reporting_period_locks', fn (Blueprint $t) => $t->unsignedBigInteger('run_record_id')->nullable());
        }
    }

    public function down(): void
    {
        foreach (array_merge(self::RESULT_TABLES, ['reporting_period_locks']) as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'run_record_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('run_record_id'));
            }
        }
        Schema::dropIfExists('run_records');
    }
};
