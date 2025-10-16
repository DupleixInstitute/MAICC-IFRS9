<?php

namespace Database\Factories;

use App\Models\BalanceSheet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class BalanceSheetFactory extends Factory
{
    protected $model = BalanceSheet::class;

    public function definition(): array
    {
        return [
            'client_id' => $this->faker->randomNumber(),
            'created_by_id' => $this->faker->randomNumber(),
            'year' => $this->faker->word(),
            'as_at_date' => Carbon::now(),
            'total_assets' => $this->faker->randomFloat(),
            'total_equity' => $this->faker->randomFloat(),
            'total_liabilities' => $this->faker->randomFloat(),
            'total_working_capital' => $this->faker->randomFloat(),
            'total_equity_liabilities' => $this->faker->randomFloat(),
            'description' => $this->faker->text(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
