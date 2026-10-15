<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The staging comparison run (Staging Module Specification v1, section 4):
 * what a month's stages and ECL would be under another set of staging rules,
 * and why. A run stages every loan of the month under the rules compared into
 * these tables only; it never writes to the loan book, the ECL, the revenue or
 * a report, and it is allowed in a locked month.
 *
 *   staging_comparison_runs  one per run: the month, the policy booked and the
 *                            policy compared (preset and every rule), who ran
 *                            it, and the totals (stage movements, ECL effect
 *                            by reason, Stage 3 interest effect)
 *   staging_comparison_rows  one per loan: both stages, both day counts, the
 *                            settings that cause a difference, the carrying
 *                            amount and EAD, the booked ECL and the ECL at the
 *                            compared stage, and the RBM class for reference
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('staging_comparison_runs')) {
            Schema::create('staging_comparison_runs', function (Blueprint $t) {
                $t->id();
                $t->string('reporting_period', 7)->index();
                $t->string('booked_preset', 60);
                $t->string('compared_preset', 60);
                $t->json('booked_rules');
                $t->json('compared_rules');
                $t->unsignedBigInteger('run_by')->nullable();
                $t->string('note', 500)->nullable();
                $t->unsignedInteger('loans')->default(0);
                $t->unsignedInteger('loans_moved')->default(0);
                $t->decimal('booked_ecl', 20, 2)->default(0);
                $t->decimal('compared_ecl', 20, 2)->default(0);
                $t->json('totals')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('staging_comparison_rows')) {
            Schema::create('staging_comparison_rows', function (Blueprint $t) {
                $t->id();
                $t->foreignId('run_id')->index();
                $t->string('contract_id', 60);
                $t->string('customer_name')->nullable();
                $t->string('product_group')->nullable();
                $t->integer('tenor')->default(0);
                $t->unsignedTinyInteger('booked_stage');
                $t->unsignedTinyInteger('compared_stage');
                $t->integer('booked_dpd')->default(0);
                $t->integer('compared_dpd')->default(0);
                $t->string('reason', 255)->nullable();
                $t->decimal('carrying_amount', 20, 2)->default(0);
                $t->decimal('ead', 20, 2)->default(0);
                $t->decimal('booked_ecl', 20, 2)->default(0);
                $t->decimal('compared_ecl', 20, 2)->nullable();
                $t->decimal('ecl_effect', 20, 2)->nullable();
                $t->decimal('stage3_interest_effect', 20, 2)->default(0);
                $t->string('rbm_class', 30)->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staging_comparison_rows');
        Schema::dropIfExists('staging_comparison_runs');
    }
};
