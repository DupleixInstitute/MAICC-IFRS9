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
        Schema::create('macro_credit_loss_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained('loan_portfolios')->onDelete('cascade');
            $table->string('period', 7); // Y-m format
            $table->decimal('ecl_value', 15, 2)->nullable();
            $table->decimal('npl_value', 15, 2)->nullable();
            $table->decimal('pd_value', 8, 6)->nullable()->comment('Probability of Default');
            $table->decimal('lgd_value', 8, 6)->nullable()->comment('Loss Given Default');
            $table->decimal('ead_value', 15, 2)->nullable()->comment('Exposure at Default');
            $table->string('stage', 50)->nullable();
            $table->string('credit_rating', 10)->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('source')->default('CSV Import');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Unique constraint to prevent duplicates
            $table->unique(['portfolio_id', 'period']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('macro_credit_loss_data');
    }
};
