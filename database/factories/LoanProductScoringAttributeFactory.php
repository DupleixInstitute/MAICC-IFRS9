<?php

namespace Database\Factories;

use App\Models\LoanProductScoringAttribute;
use App\Models\ScoringAttribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class LoanProductScoringAttributeFactory extends Factory
{
    protected $model = LoanProductScoringAttribute::class;

    public function definition(): array
    {
        return [
            'created_by_id' => $this->faker->randomNumber(),
            'loan_product_id' => $this->faker->randomNumber(),
            'scoring_attribute_group_id' => $this->faker->randomNumber(),
            'order_position' => $this->faker->randomNumber(),
            'weight' => $this->faker->randomFloat(),
            'effective_weight' => $this->faker->randomFloat(),
            'score' => $this->faker->randomFloat(),
            'weighted_score' => $this->faker->randomFloat(),
            'min_score' => $this->faker->randomFloat(),
            'max_score' => $this->faker->randomFloat(),
            'name' => $this->faker->name(),
            'reject_value' => $this->faker->word(),
            'accept_value' => $this->faker->words(),
            'description' => $this->faker->text(),
            'active' => $this->faker->randomNumber(),
            'is_group' => $this->faker->randomNumber(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
            'accept_condition' => $this->faker->word(),
            'option_type' => $this->faker->word(),
            'median_value' => $this->faker->word(),
            'data' => $this->faker->word(),
            'is_ratio' => $this->faker->randomNumber(),
            'is_industry_analysis' => $this->faker->randomNumber(),
            'is_shareholder_analysis' => $this->faker->randomNumber(),
            'is_management_analysis' => $this->faker->randomNumber(),
            'system_name' => $this->faker->name(),

            'scoring_attribute_id' => ScoringAttribute::factory(),
        ];
    }
}
