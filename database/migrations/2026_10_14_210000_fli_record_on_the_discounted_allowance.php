<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| The month record on the discounted allowance (FLI specification D-F3, D-F11;
| spec v4 6.10.1 step 7; UI end-to-end audit of 14 October 2026).
|
| loan_books.ecl_by_scenario       each loan's time-phased shortfall per
|                                  scenario, undiscounted and discounted, with
|                                  the weight the engine used, so the record
|                                  weights the same figures the allowance was
|                                  built from (TimePhasedEclService).
| ecl_scenario_results.ecl_discounted   the discounted ECL of the loan under
|                                  the scenario, beside the engine measure.
| fli_run_snapshots                the discounted allowance and how it
|                                  reconciles to the engine measure (the
|                                  projection effect and the discounting
|                                  effect), the loans it could not discount and
|                                  the Mega Farm programme left out (D30).
*/
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loan_books') && ! Schema::hasColumn('loan_books', 'ecl_by_scenario')) {
            Schema::table('loan_books', function (Blueprint $t) {
                $t->text('ecl_by_scenario')->nullable();
            });
        }
        if (Schema::hasTable('ecl_scenario_results') && ! Schema::hasColumn('ecl_scenario_results', 'ecl_discounted')) {
            Schema::table('ecl_scenario_results', function (Blueprint $t) {
                $t->decimal('ecl_discounted', 20, 2)->nullable();
            });
        }
        if (Schema::hasTable('fli_run_snapshots') && ! Schema::hasColumn('fli_run_snapshots', 'booked_ecl_discounted')) {
            Schema::table('fli_run_snapshots', function (Blueprint $t) {
                $t->unsignedInteger('programme_loans')->default(0);
                $t->decimal('programme_ecl', 20, 2)->nullable();
                $t->unsignedInteger('discounted_loans')->default(0);
                $t->unsignedInteger('discount_unresolved_loans')->default(0);
                $t->decimal('unresolved_ecl', 20, 2)->nullable()->comment('engine ECL of the loans that could not be discounted');
                $t->decimal('projection_effect', 20, 2)->nullable()->comment('time-phased undiscounted less the engine ECL, discounted loans');
                $t->decimal('discounting_effect', 20, 2)->nullable()->comment('time-phased discounted less undiscounted');
                $t->decimal('booked_ecl_discounted', 20, 2)->nullable();
                $t->decimal('weighted_ecl_discounted', 20, 2)->nullable();
                $t->decimal('discounted_difference', 20, 2)->nullable();
                $t->boolean('discounted_reconciles')->nullable();
                $t->longText('discount_detail')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fli_run_snapshots', 'booked_ecl_discounted')) {
            Schema::table('fli_run_snapshots', function (Blueprint $t) {
                $t->dropColumn(['programme_loans', 'programme_ecl', 'discounted_loans', 'discount_unresolved_loans', 'unresolved_ecl', 'projection_effect',
                    'discounting_effect', 'booked_ecl_discounted', 'weighted_ecl_discounted', 'discounted_difference', 'discounted_reconciles', 'discount_detail']);
            });
        }
        if (Schema::hasColumn('ecl_scenario_results', 'ecl_discounted')) {
            Schema::table('ecl_scenario_results', fn (Blueprint $t) => $t->dropColumn('ecl_discounted'));
        }
        if (Schema::hasColumn('loan_books', 'ecl_by_scenario')) {
            Schema::table('loan_books', fn (Blueprint $t) => $t->dropColumn('ecl_by_scenario'));
        }
    }
};
