<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SmsGatewaysTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('sms_gateways')->insert([
            [
                'name' => 'Bluedot',
                'system_name' => 'bluedot',
                'is_system' => 1,
                'http_api' => 1,
            ],
            [
                'name' => 'Twilio',
                'system_name' => 'twilio',
                'is_system' => 1,
                'http_api' => 0,
            ],
            [
                'name' => 'Route Mobile',
                'system_name' => 'routemobile',
                'is_system' => 1,
                'http_api' => 0,
            ],

        ]);
    }
}
