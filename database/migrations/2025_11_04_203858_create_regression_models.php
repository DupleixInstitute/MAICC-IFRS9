<?php
// database/migrations/2024_01_01_create_regression_models.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regression_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->foreignId('portfolio_id')->constrained('loan_portfolios');
            $table->foreignId('dep_var_id')->constrained('macro_credit_loss_definitions');
            $table->json('indep_vars');
            $table->json('coeffs'); 
            $table->decimal('r_squared', 8, 3);
            $table->decimal('adj_r_squared', 8, 3); 
            $table->json('stats'); 
            $table->date('train_start'); 
            $table->date('train_end'); 
            $table->integer('train_periods'); 
            $table->boolean('is_active')->default(false);
            $table->boolean('is_approved')->default(false);
            $table->text('validation_notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('regression_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('model_id')->constrained('regression_models');
            $table->foreignId('scenario_id')->constrained('scenarios')->nullable();
            $table->date('period')->nullable();
            $table->decimal('pred_value', 65, 2);
            $table->decimal('ci_lower', 65, 2)->nullable(); 
            $table->decimal('ci_upper', 65, 2)->nullable(); 
            $table->decimal('actual_value', 65, 2)->nullable();
            $table->decimal('error', 65, 2)->nullable(); 
            $table->boolean('is_actual')->default(false);
            $table->timestamps();

            // Very short unique constraint name
            $table->unique(['model_id', 'scenario_id', 'period'], 'pred_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regression_predictions');
        Schema::dropIfExists('regression_models');
    }
};