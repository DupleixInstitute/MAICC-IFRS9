<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('industry_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('current_ratio')->nullable();
            $table->string('quick_ratio')->nullable();
            $table->string('debtor_days')->nullable();
            $table->string('creditor_days')->nullable();
            $table->string('working_capital')->nullable();
            $table->string('turnover_growth')->nullable();
            $table->string('gross_profit')->nullable();
            $table->string('operating_profit_margin')->nullable();
            $table->string('net_profit_margin')->nullable();
            $table->string('return_on_equity')->nullable();
            $table->string('return_on_assets')->nullable();
            $table->string('return_on_investment')->nullable();
            $table->string('gearing_ratio')->nullable();
            $table->string('long_term_debt')->nullable();
            $table->string('tangible_net_worth')->nullable();
            $table->string('total_assets')->nullable();
            $table->string('solvency')->nullable();
            $table->string('interest_cover')->nullable();
            $table->string('gross_interest_debts')->nullable();
            $table->string('total_assets_turnover')->nullable();
            $table->string('fixed_assets_turn_over')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('industry_types');
    }
};
