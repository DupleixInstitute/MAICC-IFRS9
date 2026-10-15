<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The S&P basis (spec v4 section 17, 15 October 2026): an alternative ECL on the method of MAIIC's
 * December 2025 model, the S&P grade from the arrears bracket and the S&P cumulative default rate.
 * It is a comparison only: these two tables are its only output, and nothing reads them back into
 * loan_books, the booked allowance or any report figure.
 *
 * sp_basis_runs     one run per month and per time it was run, with the settings and the profile used
 * sp_basis_results  one row per loan of the run: the S&P basis beside the system's own figures
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sp_basis_runs')) {
            Schema::create('sp_basis_runs', function (Blueprint $t) {
                $t->id();
                $t->string('reporting_period', 7)->index();
                $t->unsignedBigInteger('profile_id');
                $t->string('profile_name');
                $t->json('settings');
                $t->unsignedInteger('loans')->default(0);
                $t->decimal('ead', 22, 2)->default(0);
                $t->decimal('ecl', 22, 2)->default(0);
                $t->decimal('system_ecl', 22, 2)->default(0);
                $t->json('by_stage')->nullable();
                $t->json('notes')->nullable();
                $t->unsignedBigInteger('run_by')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('sp_basis_results')) {
            Schema::create('sp_basis_results', function (Blueprint $t) {
                $t->id();
                $t->foreignId('run_id')->constrained('sp_basis_runs')->cascadeOnDelete();
                $t->string('contract_id', 40)->index();
                $t->string('customer_name')->nullable();
                $t->integer('days_past_due')->nullable();
                $t->unsignedTinyInteger('stage');
                $t->string('grade_code', 20);
                $t->string('sp_rating', 40)->nullable();
                $t->unsignedTinyInteger('horizon_years');
                $t->decimal('sp_pd', 12, 8);
                $t->decimal('macro_factor', 10, 6);
                $t->decimal('pd', 12, 8);
                $t->decimal('lgd', 12, 8);
                $t->decimal('ead', 20, 2);
                $t->decimal('recovery_pv', 20, 2)->default(0);
                $t->decimal('ecl', 20, 2);
                $t->unsignedTinyInteger('system_stage')->nullable();
                $t->decimal('system_pd', 12, 8)->nullable();
                $t->decimal('system_ecl', 20, 2)->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_basis_results');
        Schema::dropIfExists('sp_basis_runs');
    }
};
