<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ScenarioSetSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Get or create "Inhouse View" scenario set
        $scenarioSet = DB::table('scenario_sets')->where('name', 'Inhouse View')->first();
        
        if ($scenarioSet) {
            $scenarioSetId = $scenarioSet->id;
            // Update existing
            DB::table('scenario_sets')->where('id', $scenarioSetId)->update([
                'description' => 'Default economic scenario set for MAICC - Malawi',
                'is_active' => true,
                'updated_at' => Carbon::now(),
            ]);
            
            // Delete old probabilities
            DB::table('scenario_probabilities')->where('scenario_set_id', $scenarioSetId)->delete();
            
            $this->command->info('🔄 Updating existing "Inhouse View" scenario set...');
        } else {
            // Create new
            $scenarioSetId = DB::table('scenario_sets')->insertGetId([
                'name' => 'Inhouse View',
                'description' => 'Default economic scenario set for MAICC - Malawi',
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
            
            $this->command->info('✨ Creating new "Inhouse View" scenario set...');
        }

        // Create scenario probabilities
        DB::table('scenario_probabilities')->insert([
            [
                'scenario_set_id' => $scenarioSetId,
                'scenario_name' => 'Base Case',
                'probability' => 40.00,
                'order_position' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'scenario_set_id' => $scenarioSetId,
                'scenario_name' => 'Upside',
                'probability' => 25.00,
                'order_position' => 2,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'scenario_set_id' => $scenarioSetId,
                'scenario_name' => 'Downside 1',
                'probability' => 20.00,
                'order_position' => 3,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'scenario_set_id' => $scenarioSetId,
                'scenario_name' => 'Downside 2',
                'probability' => 15.00,
                'order_position' => 4,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ]);

        $this->command->info('✅ Scenario Set "Inhouse View" seeded successfully!');
        $this->command->info('   - Base Case: 40%');
        $this->command->info('   - Upside: 25%');
        $this->command->info('   - Downside 1: 20%');
        $this->command->info('   - Downside 2: 15%');
        $this->command->info('   Total: 100%');
    }
}
