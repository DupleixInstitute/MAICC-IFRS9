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
        // Credit Loss Definitions Table
        Schema::create('macro_credit_loss_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique(); // e.g. 'ECL', 'PD', 'LGD', etc.
            $table->string('name', 100); // descriptive name
            $table->text('description')->nullable();
            $table->timestamps();
        });

        //  Credit Loss Data Table
        Schema::create('macro_credit_loss_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained('loan_portfolios')->onDelete('cascade');
            $table->foreignId('definition_id')->constrained('macro_credit_loss_definitions')->onDelete('cascade');
            $table->date('period')->nullable(); // Y-m format
            $table->decimal('value', 65, 2)->nullable()->comment('Credit loss metric value');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('source')->default('CSV Import');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->unique(['portfolio_id', 'period', 'definition_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('macro_credit_loss_data');
        Schema::dropIfExists('macro_credit_loss_definitions');
    }
};
