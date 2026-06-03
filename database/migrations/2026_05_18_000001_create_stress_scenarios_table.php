<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saved stress-testing scenarios (per-stage PD multipliers + LGD add-ons).
 * Laravel port of the FDH `stress_scenarios` table, adapted to this schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stress_scenarios', function (Blueprint $table) {
            $table->id();
            $table->string('scenario_name', 120);
            $table->string('reporting_period', 10);            // YYYY-MM
            $table->unsignedBigInteger('loan_portfolio_id')->nullable(); // null = all portfolios
            $table->text('description')->nullable();
            $table->decimal('s1_pd_mult', 8, 4)->default(1);
            $table->decimal('s2_pd_mult', 8, 4)->default(1);
            $table->decimal('s3_pd_mult', 8, 4)->default(1);
            $table->decimal('s1_lgd_add', 8, 4)->default(0);   // percentage points
            $table->decimal('s2_lgd_add', 8, 4)->default(0);
            $table->decimal('s3_lgd_add', 8, 4)->default(0);
            $table->json('result_snapshot')->nullable();
            $table->string('saved_by', 120)->nullable();
            $table->timestamps();

            $table->index(['reporting_period', 'loan_portfolio_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stress_scenarios');
    }
};
