<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('macro_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('macro_variable_id')
                  ->constrained('macro_variables')
                  ->cascadeOnDelete();
            $table->date('period_date');              // First day of the period (2024-01-01 = Q1 2024)
            $table->string('period_label', 20);       // e.g. Q1 2024, 2023, Jan 2024
            $table->string('period_type', 20)->default('quarterly'); // monthly, quarterly, annual
            $table->decimal('value', 18, 6);          // Actual observed value
            $table->decimal('value_stressed_mild', 18, 6)->nullable();   // Mild scenario override
            $table->decimal('value_stressed_severe', 18, 6)->nullable(); // Severe scenario override
            $table->string('source', 120)->nullable();  // Where this data point came from
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // A variable can only have one observation per period
            $table->unique(['macro_variable_id', 'period_date', 'period_type'], 'macro_obs_unique');
            $table->index(['macro_variable_id', 'period_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('macro_observations');
    }
};
