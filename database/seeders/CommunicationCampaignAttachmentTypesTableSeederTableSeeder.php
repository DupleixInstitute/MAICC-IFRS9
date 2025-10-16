<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CommunicationCampaignAttachmentTypesTableSeederTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        DB::table('communication_campaign_attachment_types')->insert([
            [
                'name' => 'Loan Statement',
                'active' => 1,
            ],
        ]);
    }
}
