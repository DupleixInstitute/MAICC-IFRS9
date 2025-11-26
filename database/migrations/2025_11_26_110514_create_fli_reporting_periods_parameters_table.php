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
        Schema::create('fli_reporting_periods_parameters', function (Blueprint $table) {
            $table->id();
            $table->date('reporting_period');
            $table->foreignId('scenario_set_id')->constrained('scenario_sets')->onDelete('cascade');
            $table->integer('number_of_forecasting_periods');
            $table->integer('forecasting_period_length_months');
            $table->string('economic_data_statistic', 50);
            $table->string('pd_proxy_statistic', 50);
            $table->date('base_forecast_period');
            $table->decimal('base_macro_data_value', 15, 6);
            $table->decimal('base_pd_proxy_value', 15, 6);
            $table->decimal('regression_slope', 15, 6);
            $table->decimal('regression_intercept', 15, 6);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index('reporting_period');
            $table->index('scenario_set_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fli_reporting_periods_parameters');
    }
};
