<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class IndustryTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $now = Carbon::now();

        DB::table('industry_types')->insert([
            [
                'code' => '1',
                'name' => 'Agriculture, forestry and fishing',
                'description' => 'Industries related to farming, forestry, and fishing',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => '2',
                'name' => 'Mining',
                'description' => 'Extraction of minerals and natural resources',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => '3',
                'name' => 'Manufacturing',
                'description' => 'Production of goods in factories and workshops',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => '4',
                'name' => 'Electricity, gas, steam and air conditioning supply, Water collection, treatment and supply',
                'description' => 'Utilities and essential services',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => '5',
                'name' => 'Construction and Engineering',
                'description' => 'Building and infrastructure development',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => '6',
                'name' => 'Wholesale and Retail, Accommodation and Food Services',
                'description' => 'Trade, hospitality, and food services',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => '7',
                'name' => 'Transport, and Storage, Information and Communication',
                'description' => 'Logistics, ICT, and communication services',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => '9',
                'name' => 'Financial and Insurance Activities, Real Estate Activities, Professional, Scientific and Technical Activities',
                'description' => 'Finance, insurance, real estate, and professional services',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'code' => '10',
                'name' => 'Community, Social and Personal Services',
                'description' => 'Community support, social services, and personal care',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}