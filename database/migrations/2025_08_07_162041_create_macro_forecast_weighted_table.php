<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('macro_forecast_weighted', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scenario_profile_id')->constrained()->onDelete('cascade');
            $table->foreignId('macro_statistic_id')->constrained()->onDelete('cascade');
            $table->foreignId('reporting_period_id')->constrained()->onDelete('cascade');
            $table->date('start_period')->nullable();
            $table->date('end_period')->nullable();
            $table->decimal('weighted_value', 14, 4);
            $table->integer('revision')->default(1);
            $table->boolean('is_current')->default(true);
            $table->decimal('standard_deviation', 14, 4)->nullable();
            $table->decimal('confidence_level', 5, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('macro_forecast_weighted');
    }
};
