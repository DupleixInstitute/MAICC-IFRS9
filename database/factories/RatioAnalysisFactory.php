<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\RatioAnalysis;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class RatioAnalysisFactory extends Factory
{
    protected $model = RatioAnalysis::class;

    public function definition(): array
    {
        return [
            'created_by_id' => $this->faker->randomNumber(),
            'working_capital_total_assets' => $this->faker->word(),
            'retained_earnings_total_assets' => $this->faker->word(),
            'ebit_total_assets' => $this->faker->word(),
            'equity_total_liabilities' => $this->faker->word(),
            'sales_total_assets' => $this->faker->word(),
            'bankruptcy_prediction_ratios' => $this->faker->word(),
            'z_score' => $this->faker->word(),
            'z_score_comment' => $this->faker->word(),
            'z_second_score' => $this->faker->word(),
            'z_second_score_comment' => $this->faker->word(),
            'z_score_thresholds_healthy' => $this->faker->word(),
            'z_second_score_thresholds_bankrupt' => $this->faker->word(),
            'current_ratio' => $this->faker->word(),
            'current_ratio_comment' => $this->faker->word(),
            'quick_ratio' => $this->faker->word(),
            'quick_ratio_comment' => $this->faker->word(),
            'debtor_days' => $this->faker->word(),
            'debtor_days_comment' => $this->faker->word(),
            'creditor_days' => $this->faker->word(),
            'creditor_days_comment' => $this->faker->word(),
            'working_capital' => $this->faker->word(),
            'working_capital_comment' => $this->faker->word(),
            'turnover_growth' => $this->faker->word(),
            'turnover_growth_comment' => $this->faker->word(),
            'gross_profit' => $this->faker->word(),
            'gross_profit_comment' => $this->faker->word(),
            'operating_profit_margin' => $this->faker->word(),
            'operating_profit_margin_comment' => $this->faker->word(),
            'net_profit_margin' => $this->faker->word(),
            'net_profit_margin_comment' => $this->faker->word(),
            'return_on_equity' => $this->faker->word(),
            'return_on_equity_comment' => $this->faker->word(),
            'return_on_assets' => $this->faker->word(),
            'return_on_assets_comment' => $this->faker->word(),
            'return_on_investment' => $this->faker->word(),
            'return_on_investment_comment' => $this->faker->word(),
            'gearing_ratio' => $this->faker->word(),
            'gearing_ratio_comment' => $this->faker->word(),
            'long_term_debt' => $this->faker->word(),
            'long_term_debt_comment' => $this->faker->word(),
            'tangible_net_worth' => $this->faker->word(),
            'tangible_net_worth_comment' => $this->faker->word(),
            'total_assets' => $this->faker->word(),
            'total_assets_comment' => $this->faker->word(),
            'solvency' => $this->faker->word(),
            'solvency_comment' => $this->faker->word(),
            'interest_cover' => $this->faker->word(),
            'interest_cover_comment' => $this->faker->word(),
            'gross_interest_debts' => $this->faker->word(),
            'gross_interest_debts_comment' => $this->faker->word(),
            'total_assets_turnover' => $this->faker->word(),
            'total_assets_turnover_comment' => $this->faker->word(),
            'fixed_assets_turn_over' => $this->faker->word(),
            'fixed_assets_turn_over_comment' => $this->faker->word(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'client_id' => Client::factory(),
        ];
    }
}
