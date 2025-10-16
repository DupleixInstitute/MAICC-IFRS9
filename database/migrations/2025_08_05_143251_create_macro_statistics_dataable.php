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
         Schema::create('macro_statistics_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('macro_stat_definition_id')->constrained('macro_statistics')->onDelete('cascade');
            $table->foreignId('scenario_profile_id')->constrained('scenario_profiles')->nullable();
            $table->foreignId('scenario_id')->constrained('scenarios')->nullable();
            $table->string('period',7);
            $table->decimal('value', 15, 4);
            $table->boolean('is_forecast')->default(false);
            $table->boolean('is_fli')->default(false);
            $table->decimal('actual_value', 14, 4)->nullable();
            $table->decimal('confidence_interval_lower', 14, 4)->nullable();
            $table->decimal('confidence_interval_upper', 14, 4)->nullable();
            $table->text('key_assumptions')->nullable();
            $table->text('sensitivity_analysis')->nullable();
            $table->string('source')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['macro_stat_definition_id', 'period', 'scenario_id'], 'macro_value_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('macro_statistics_data');
    }
};
