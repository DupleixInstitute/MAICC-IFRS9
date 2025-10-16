<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CommunicationCampaignBusinessRulesTableSeederTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        DB::table('communication_campaign_business_rules')->insert([
            [
                'name' => 'Active Clients',
                'is_trigger' => 0,
                'description' => 'All clients with the status ‘Active’',
                'active' => 1,
            ],
            [
                'name' => 'Inactive Clients',
                'is_trigger' => 0,
                'description' => 'All clients with the status ‘In-Active’',
                'active' => 1,
            ],
            [
                'name' => 'Clients with courses',
                'is_trigger' => 0,
                'description' => 'All clients with  courses',
                'active' => 1,

            ],
            [
                'name' => 'Active Users',
                'is_trigger' => 0,
                'description' => 'All active Users',
                'active' => 1,

            ],
            [
                'name' => 'Single Client',
                'is_trigger' => 0,
                'description' => 'This sends a message to selected Client',
                'active' => 1,
            ],
            [
                'name' => 'Single User',
                'is_trigger' => 0,
                'description' => 'This sends a message to selected User',
                'active' => 1,
            ],
        ]);
    }
}
