<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\CreditLossDefinition; // Make sure to import your model
use Illuminate\Support\Facades\DB;

class CreditLossDefinitionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Option 1: Using Eloquent Model (Recommended)
        $definitions = [
            [
                'code' => 'ECL',
                'name' => 'Expected Credit Loss',
                'description' => 'Total expected credit loss amount calculated under IFRS 9',
            ],
            [
                'code' => 'PD',
                'name' => 'Probability of Default',
                'description' => 'Probability that a borrower will default on their obligations (0-1 scale)',
            ],
            [
                'code' => 'LGD',
                'name' => 'Loss Given Default',
                'description' => 'Percentage of exposure lost when a default occurs (0-1 scale)',
            ],
            [
                'code' => 'EAD',
                'name' => 'Exposure at Default',
                'description' => 'Total exposure amount at the time of default',
            ],
            [
                'code' => 'NPL',
                'name' => 'Non-Performing Loans',
                'description' => 'Total value of non-performing loans',
            ],
            [
                'code' => 'STAGE',
                'name' => 'IFRS 9 Stage',
                'description' => 'Credit stage classification under IFRS 9 (1, 2, or 3)',
            ],
        ];

        foreach ($definitions as $definition) {
            CreditLossDefinition::firstOrCreate(
                ['code' => $definition['code']], // Check if exists by code
                $definition // Create with these values if doesn't exist
            );
        }

        // Option 2: Using DB Facade (Alternative method)
        /*
        DB::table('macro_credit_loss_definitions')->insert([
            [
                'code' => 'ECL',
                'name' => 'Expected Credit Loss',
                'description' => 'Total expected credit loss amount',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'PD',
                'name' => 'Probability of Default',
                'description' => 'Probability of default (0-1)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Add more definitions...
        ]);
        */

        // Output success message
        $this->command->info('Credit loss definitions seeded successfully!');
        $this->command->info('Total definitions: ' . count($definitions));
    }
}