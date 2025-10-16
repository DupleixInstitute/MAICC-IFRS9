<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('settings')->insert([
            [
                'name' => 'Company Name',
                'setting_key' => 'company_name',
                'setting_value' => 'Eswatini  Bank Credit Scoring',
                'category' => 'general',
                'type' => 'text',
                'options' => '',
                'class' => '',
                'required' => '1',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => ''
            ],
            [
                'name' => 'Company Address',
                'setting_key' => 'company_address',

                'setting_value' => '',
                'category' => 'general',
                'type' => 'textarea',
                'options' => '',
                'class' => '',
                'required' => '0',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => ''
            ],
            [
                'name' => 'Company Mobile',
                'setting_key' => 'company_mobile',

                'setting_value' => '',
                'category' => 'general',
                'type' => 'text',
                'options' => '',
                'class' => '',
                'required' => '0',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => ''
            ], [
                'name' => 'Company Tel',
                'setting_key' => 'company_tel',

                'setting_value' => '',
                'category' => 'general',
                'type' => 'text',
                'options' => '',
                'class' => '',
                'required' => '0',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => ''
            ],
            [
                'name' => 'Company Website',
                'setting_key' => 'company_website',
                'setting_value' => '',
                'category' => 'general',
                'type' => 'text',
                'options' => '',
                'class' => '',
                'required' => '0',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => ''
            ],
            [
                'name' => 'System Version',
                'setting_key' => 'system_version',
                'setting_value' => '1.0',
                'category' => 'update',
                'type' => 'info',
                'options' => '',
                'class' => '',
                'required' => '1',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => ''
            ],
            [
                'name' => 'Company Email',
                'setting_key' => 'company_email',

                'setting_value' => 'nonreply@company.com',
                'category' => 'general',
                'type' => 'text',
                'options' => '',
                'class' => '',
                'required' => '1',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => ''
            ],
            [
                'name' => 'Company Logo',
                'setting_key' => 'company_logo',

                'setting_value' => '',
                'category' => 'general',
                'type' => 'file',
                'options' => 'jpeg,jpg,bmp,png',
                'class' => '',
                'required' => '0',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => 'nullable|file|mimes:jpeg,jpg,bmp,png'
            ],
            [
                'name' => 'Company Small Logo',
                'setting_key' => 'company_small_logo',

                'setting_value' => '',
                'category' => 'general',
                'type' => 'file',
                'options' => 'jpeg,jpg,bmp,png',
                'class' => '',
                'required' => '0',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => 'nullable|file|mimes:jpeg,jpg,bmp,png'
            ], [
                'name' => 'Company Letterhead',
                'setting_key' => 'company_letterhead',
                'setting_value' => '',
                'category' => 'general',
                'type' => 'file',
                'options' => 'jpeg,jpg,bmp,png',
                'class' => '',
                'required' => '0',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => 'nullable|file|mimes:jpeg,jpg,bmp,png'
            ],
            [
                'name' => 'Site Online',
                'setting_key' => 'site_online',
                'setting_value' => 'yes',
                'category' => 'system',
                'type' => 'select',
                'options' => 'yes,no',
                'class' => '',
                'required' => '1',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => ''
            ],
            [
                'name' => 'Console Last Run',
                'setting_key' => 'console_last_run',
                'setting_value' => '',
                'category' => 'system',
                'type' => 'info',
                'options' => '',
                'class' => '',
                'required' => '1',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => ''
            ],
            [
                'name' => 'Update last checked',
                'setting_key' => 'update_last_checked',

                'setting_value' => '',
                'category' => 'system',
                'type' => 'info',
                'options' => '',
                'class' => '',
                'required' => '1',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => ''
            ],
            [
                'name' => 'Extra Javascript',
                'setting_key' => 'extra_javascript',

                'setting_value' => '',
                'category' => 'system',
                'type' => 'textarea',
                'options' => '',
                'class' => '',
                'required' => '0',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => ''
            ],
            [
                'name' => 'Extra Styles',
                'setting_key' => 'extra_styles',

                'setting_value' => '',
                'category' => 'system',
                'type' => 'textarea',
                'options' => '',
                'class' => '',
                'required' => '0',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => ''
            ],

            [
                'name' => 'Active Theme',
                'setting_key' => 'active_theme',
                'setting_value' => 'AdminLTE',
                'category' => 'system',
                'type' => 'text',
                'options' => '',
                'class' => '',
                'required' => '0',
                'db_columns' => '',
                'displayed' => '0',
                'rules' => ''
            ],
            [
                'name' => 'Timezone',
                'setting_key' => 'timezone',
                'setting_value' => '425',
                'category' => 'system',
                'type' => 'select_db',
                'options' => 'timezones',
                'class' => '',
                'required' => '0',
                'db_columns' => 'id,name',
                'displayed' => '0',
                'rules' => ''
            ],
            [
                'name' => 'Currency',
                'setting_key' => 'currency',
                'setting_value' => '33',
                'category' => 'system',
                'type' => 'select_db',
                'options' => 'currencies',
                'class' => '',
                'required' => '0',
                'db_columns' => 'id,name',
                'displayed' => '0',
                'rules' => ''
            ],
            [
                'name' => 'Purchase Code',
                'setting_key' => 'purchase_code',
                'setting_value' => '',
                'category' => 'system',
                'type' => 'text',
                'options' => '',
                'class' => '',
                'required' => '0',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => ''
            ],
            [
                'name' => 'Purchase Code Type',
                'setting_key' => 'purchase_code_type',
                'setting_value' => '',
                'category' => 'system',
                'type' => 'text',
                'options' => '',
                'class' => '',
                'required' => '0',
                'db_columns' => '',
                'displayed' => '0',
                'rules' => ''
            ],
            [
                'name' => 'Installed IP Address',
                'setting_key' => 'installed_ip_address',
                'setting_value' => '',
                'category' => 'system',
                'type' => 'text',
                'options' => '',
                'class' => '',
                'required' => '0',
                'db_columns' => '',
                'displayed' => '0',
                'rules' => ''
            ],
            [
                'name' => 'License Details',
                'setting_key' => 'license_details',
                'setting_value' => '',
                'category' => 'system',
                'type' => 'text',
                'options' => '',
                'class' => '',
                'required' => '0',
                'db_columns' => '',
                'displayed' => '0',
                'rules' => ''
            ],
            [
                'name' => 'Allow member Self Registration',
                'setting_key' => 'allow_self_registration',
                'setting_value' => 'no',
                'category' => 'system',
                'type' => 'select',
                'options' => 'yes,no',
                'class' => '',
                'required' => '1',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => ''
            ],
            [
                'name' => 'SMS Enabled',
                'setting_key' => 'sms_enabled',
                'setting_value' => 'no',
                'category' => 'sms',
                'type' => 'select',
                'options' => 'yes,no',
                'class' => '',
                'required' => '1',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => ''
            ],
            [
                'name' => 'Active SMS Gateway',
                'setting_key' => 'active_sms_gateway',
                'setting_value' => '',
                'category' => 'sms',
                'type' => 'select_db',
                'options' => 'sms_gateways',
                'class' => 'select2',
                'required' => '0',
                'db_columns' => 'id,name',
                'displayed' => '1',
                'rules' => ''
            ],
            [
                'name' => 'Allow Editing Currency Exchange Rate',
                'setting_key' => 'invoice_edit_exchange_rate',
                'setting_value' => 'no',
                'category' => 'system',
                'type' => 'select',
                'options' => 'yes,no',
                'class' => '',
                'required' => '0',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => ''
            ],
           [
                'name' => 'Mail Driver',
                'setting_key' => 'mail_mailer',
                'setting_value' => 'smtp',
                'category' => 'email',
                'type' => 'text',
                'options' => '',
                'class' => '',
                'required' => '0',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => ''
            ], [
                'name' => 'Mail Host',
                'setting_key' => 'mail_host',
                'setting_value' => 'localhost',
                'category' => 'email',
                'type' => 'text',
                'options' => '',
                'class' => '',
                'required' => '0',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => ''
            ], [
                'name' => 'Mail Port',
                'setting_key' => 'mail_port',
                'setting_value' => '25',
                'category' => 'email',
                'type' => 'text',
                'options' => '',
                'class' => '',
                'required' => '0',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => ''
            ], [
                'name' => 'Mail Username',
                'setting_key' => 'mail_username',
                'setting_value' => '',
                'category' => 'email',
                'type' => 'text',
                'options' => '',
                'class' => '',
                'required' => '0',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => ''
            ], [
                'name' => 'Mail Password',
                'setting_key' => 'mail_password',
                'setting_value' => '',
                'category' => 'email',
                'type' => 'text',
                'options' => '',
                'class' => '',
                'required' => '0',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => ''
            ], [
                'name' => 'Mail Encryption',
                'setting_key' => 'mail_encryption',
                'setting_value' => '',
                'category' => 'email',
                'type' => 'text',
                'options' => '',
                'class' => '',
                'required' => '0',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => ''
            ], [
                'name' => 'Mail From Address',
                'setting_key' => 'mail_from_address',
                'setting_value' => '',
                'category' => 'email',
                'type' => 'text',
                'options' => '',
                'class' => '',
                'required' => '0',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => ''
            ], [
                'name' => 'Mail From Name',
                'setting_key' => 'mail_from_name',
                'setting_value' => '',
                'category' => 'email',
                'type' => 'text',
                'options' => '',
                'class' => '',
                'required' => '0',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => ''
            ],
            [
                'name' => 'System User',
                'setting_key' => 'system_user',
                'setting_value' => '1',
                'category' => 'system',
                'type' => 'text',
                'options' => '',
                'class' => '',
                'required' => '0',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => ''
            ],

            [
                'name' => 'Date Format',
                'setting_key' => 'date_format',
                'setting_value' => 'YYYY-mm-dd',
                'category' => 'system',
                'type' => 'text',
                'options' => '',
                'class' => '',
                'required' => '0',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => ''
            ],
            [
                'name' => 'Records Per Page',
                'setting_key' => 'records_per_page',
                'setting_value' => '20',
                'category' => 'system',
                'type' => 'text',
                'options' => '',
                'class' => '',
                'required' => '0',
                'db_columns' => '',
                'displayed' => '1',
                'rules' => ''
            ],
        ]);
    }
}
