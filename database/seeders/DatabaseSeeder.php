<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        ini_set('memory_limit', '-1');
        $this->call([
            CountriesTableSeeder::class,
            PermissionsTableSeeder::class,
            RolesTableSeeder::class,
            TicketsPermissionsSeeder::class,
            MaiicAdminPermissionsSeeder::class,
            LegacyPermissionsCleanupSeeder::class,
            UsersTableSeeder::class,
            CurrenciesTableSeeder::class,
            TimezonesTableSeeder::class,
            SettingsTableSeeder::class,
            BranchesTableSeeder::class,
            SmsGatewaysTableSeeder::class,
            CommunicationCampaignBusinessRulesTableSeederTableSeeder::class,
            LegalTypesTableSeeder::class,
            IndustryTypesTableSeeder::class,
            IndustryTypeSeeder::class,
            ChartOfAccountsTableSeeder::class,
            ScoringAttributesTableSeeder::class,
            CreditLossDefinitionSeeder::class,
            ScenarioSetSeeder::class,
        ]);
    }
}
