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
        Schema::create('ratio_analysis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('working_capital_total_assets')->nullable();
            $table->string('retained_earnings_total_assets')->nullable();
            $table->string('ebit_total_assets')->nullable();
            $table->string('equity_total_liabilities')->nullable();
            $table->string('sales_total_assets')->nullable();
            $table->text('bankruptcy_prediction_ratios')->nullable();
            $table->string('z_score')->nullable();
            $table->string('z_score_comment')->nullable();
            $table->string('z_second_score')->nullable();
            $table->string('z_second_score_comment')->nullable();
            $table->string('z_score_thresholds_healthy')->nullable();
            $table->string('z_second_score_thresholds_bankrupt')->nullable();
            $table->string('current_ratio')->nullable();
            $table->text('current_ratio_comment')->nullable();
            $table->string('quick_ratio')->nullable();
            $table->text('quick_ratio_comment')->nullable();
            $table->string('debtor_days')->nullable();
            $table->text('debtor_days_comment')->nullable();
            $table->string('creditor_days')->nullable();
            $table->text('creditor_days_comment')->nullable();
            $table->string('working_capital')->nullable();
            $table->text('working_capital_comment')->nullable();
            $table->string('turnover_growth')->nullable();
            $table->text('turnover_growth_comment')->nullable();
            $table->string('gross_profit')->nullable();
            $table->text('gross_profit_comment')->nullable();
            $table->string('operating_profit_margin')->nullable();
            $table->text('operating_profit_margin_comment')->nullable();
            $table->string('net_profit_margin')->nullable();
            $table->text('net_profit_margin_comment')->nullable();
            $table->string('return_on_equity')->nullable();
            $table->text('return_on_equity_comment')->nullable();
            $table->string('return_on_assets')->nullable();
            $table->text('return_on_assets_comment')->nullable();
            $table->string('return_on_investment')->nullable();
            $table->text('return_on_investment_comment')->nullable();
            $table->string('gearing_ratio')->nullable();
            $table->text('gearing_ratio_comment')->nullable();
            $table->string('long_term_debt')->nullable();
            $table->text('long_term_debt_comment')->nullable();
            $table->string('tangible_net_worth')->nullable();
            $table->text('tangible_net_worth_comment')->nullable();
            $table->string('total_assets')->nullable();
            $table->text('total_assets_comment')->nullable();
            $table->string('solvency')->nullable();
            $table->text('solvency_comment')->nullable();
            $table->string('interest_cover')->nullable();
            $table->text('interest_cover_comment')->nullable();
            $table->string('gross_interest_debts')->nullable();
            $table->text('gross_interest_debts_comment')->nullable();
            $table->string('total_assets_turnover')->nullable();
            $table->text('total_assets_turnover_comment')->nullable();
            $table->string('fixed_assets_turn_over')->nullable();
            $table->text('fixed_assets_turn_over_comment')->nullable();


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ratio_analysis');
    }
};
