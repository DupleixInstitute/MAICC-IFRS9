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
        Schema::create('fli_adj', function (Blueprint $table) {
            $table->id();
            $table->date('reporting_period');
            $table->foreignId('scenario_set_id')->constrained('scenario_sets')->onDelete('cascade');
            $table->date('forecast_period');
            $table->integer('forecast_window_in_months');
            $table->decimal('weighted_macro_data_value', 15, 6);
            $table->decimal('predicted_value', 15, 6);
            $table->decimal('fli_adj', 10, 6);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index('reporting_period');
            $table->index('scenario_set_id');
            $table->index('forecast_window_in_months');
            $table->unique(['reporting_period', 'scenario_set_id', 'forecast_window_in_months'], 'unique_forecast');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fli_adj');
    }
};
