<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| PD and LGD by segment: the lineage of every segment run
|--------------------------------------------------------------------------
| The book is split into its real programmes (MAIIC Industrial, MAIIC
| Agricultural, MAIIC Term, FInES Industrial, FInES Agricultural, ...) and
| the PD is measured per segment on the governed basis
| (pd_segmentation_basis: portfolio, pooled book, sector, or portfolio and
| sector). A segment below the governed minimum either stops the run or
| takes its parent's PD under the governed thin-segment rule; either way
| the outcome is written here, cell by cell, so an auditor can read which
| PD every loan carries, where it came from and why.
|
|   segment_parameter_runs     one row per PD or LGD segment run of a period
|   segment_parameter_results  one row per segment and stage (PD) or per
|                              segment (LGD): the cohort, the defaults, the
|                              observed rate, the rate applied and its source
|   loan_books                 the segment a loan belongs to and the segment
|                              whose PD and LGD it was given
|   expected_credit_loss       the segment run and the segments behind a row
*/
return new class extends Migration
{
    public function up(): void
    {
        $this->upTablesOnly();

        Schema::table('loan_books', function (Blueprint $t) {
            if (! Schema::hasColumn('loan_books', 'pd_segment_key')) {
                $t->string('pd_segment_key', 80)->nullable()->comment('The PD segment the loan belongs to on the governed basis');
                $t->string('pd_applied_segment_key', 80)->nullable()->comment('The segment whose PD it carries (itself, or its parent)');
                $t->string('pd_segment_run_id', 36)->nullable();
                $t->unsignedBigInteger('pd_matrix_id')->nullable()->comment('transition_matrices.id behind the PD');
                $t->string('lgd_applied_segment_key', 80)->nullable();
                $t->unsignedBigInteger('lgd_source_id')->nullable()->comment('loss_given_default.id behind the LGD');
            }
        });

        Schema::table('expected_credit_loss', function (Blueprint $t) {
            if (! Schema::hasColumn('expected_credit_loss', 'pd_segment_run_id')) {
                $t->string('pd_segment_run_id', 36)->nullable()->comment('The PD segment run of the loans behind the row; null when they carry none or more than one');
                $t->text('pd_segment_keys')->nullable()->comment('The PD segments of the loans behind the row');
            }
        });
    }

    /** The two lineage tables; the tests' private schemas build them from here. */
    public function upTablesOnly(): void
    {
        if (! Schema::hasTable('segment_parameter_runs')) {
            Schema::create('segment_parameter_runs', function (Blueprint $t) {
                $t->id();
                $t->string('run_id', 36)->unique();
                $t->string('measure', 3)->comment('PD or LGD');
                $t->string('reporting_period', 7);
                $t->string('basis', 40)->comment('portfolio, book, sector or portfolio_sector');
                $t->string('window_start', 7)->nullable();
                $t->string('window_end', 7)->nullable();
                $t->unsignedSmallInteger('window_months')->nullable();
                $t->string('minimum_rule', 120)->nullable()->comment('The governed minimum in force, as read');
                $t->string('thin_rule', 120)->nullable()->comment('The governed thin-segment rule in force, as read');
                $t->string('unverified_codes', 120)->nullable();
                $t->string('status', 12)->comment('applied or failed');
                $t->text('failure_reason')->nullable();
                $t->unsignedInteger('loans_updated')->default(0);
                $t->unsignedBigInteger('user_id')->nullable();
                $t->timestamps();
                $t->index(['reporting_period', 'measure'], 'sp_runs_period_measure_idx');
            });
        }

        if (! Schema::hasTable('segment_parameter_results')) {
            Schema::create('segment_parameter_results', function (Blueprint $t) {
                $t->id();
                $t->string('run_id', 36)->index();
                $t->string('measure', 3);
                $t->string('reporting_period', 7);
                $t->string('segment_key', 80);
                $t->string('segment_label', 200)->nullable();
                $t->string('parent_key', 80)->nullable();
                $t->unsignedBigInteger('portfolio_id')->nullable();
                $t->string('sector_code', 40)->nullable();
                $t->unsignedTinyInteger('stage')->nullable()->comment('PD: the start stage; LGD: null');
                $t->unsignedInteger('cohort_loans')->default(0);
                $t->decimal('cohort_balance', 20, 2)->default(0);
                $t->unsignedInteger('default_loans')->default(0)->comment('PD: loans in Stage 3 at the window end, or written off');
                $t->decimal('default_balance', 20, 2)->default(0);
                $t->decimal('observed_rate', 16, 8)->nullable()->comment('PD: the balance-weighted rate to Stage 3; LGD: the cohort LGD');
                $t->decimal('annual_rate', 16, 8)->nullable()->comment('PD: annualised when the window is short');
                $t->string('status', 12)->comment('own, parent, failed, not_needed');
                $t->string('applied_from_key', 80)->nullable();
                $t->decimal('applied_rate', 16, 8)->nullable();
                $t->unsignedInteger('period_loans')->default(0)->comment('Loans of the period in this segment (and stage) that take the rate');
                $t->text('reason')->nullable();
                $t->unsignedBigInteger('matrix_id')->nullable()->comment('transition_matrices.id (PD) or loss_given_default.id (LGD)');
                $t->timestamps();
                $t->index(['reporting_period', 'measure', 'segment_key'], 'spr_period_measure_segment_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::table('expected_credit_loss', function (Blueprint $t) {
            $t->dropColumn(['pd_segment_run_id', 'pd_segment_keys']);
        });
        Schema::table('loan_books', function (Blueprint $t) {
            $t->dropColumn(['pd_segment_key', 'pd_applied_segment_key', 'pd_segment_run_id', 'pd_matrix_id', 'lgd_applied_segment_key', 'lgd_source_id']);
        });
        Schema::dropIfExists('segment_parameter_results');
        Schema::dropIfExists('segment_parameter_runs');
    }
};
