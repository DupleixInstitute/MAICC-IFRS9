<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The Malawi macro series MAIIC's forward-looking model reads, with the code
 * that identifies each at its source (spec v4 section 13.4). Idempotent on
 * statistic_code. A code the World Bank later retires is a change here,
 * not in code.
 */
class MacroSeriesSeeder extends Seeder
{
    /** @return list<array{code:string,name:string,unit:string,frequency:string,source:string,codes:array,why:string}> */
    public static function series(): array
    {
        $wb = fn ($i) => ['world_bank' => $i];

        return [
            ['code' => 'GDP_GROWTH', 'name' => 'Real GDP growth', 'unit' => 'annual %', 'frequency' => 'yearly', 'source' => 'World Bank; IMF WEO for the forecast years', 'codes' => $wb('NY.GDP.MKTP.KD.ZG') + ['imf_weo' => 'NGDP_RPCH'], 'why' => 'The headline driver of default rates'],
            ['code' => 'CPI', 'name' => 'Inflation, consumer prices', 'unit' => 'annual %', 'frequency' => 'yearly', 'source' => 'World Bank; IMF WEO for the forecast years', 'codes' => $wb('FP.CPI.TOTL.ZG') + ['imf_weo' => 'PCPIPCH'], 'why' => 'Real income of borrowers; the policy-rate path'],
            ['code' => 'MWK_USD', 'name' => 'Official exchange rate, MWK per USD', 'unit' => 'MWK per USD, period average', 'frequency' => 'yearly', 'source' => 'World Bank', 'codes' => $wb('PA.NUS.FCRF'), 'why' => 'Import-dependent borrowers; the industrial book'],
            ['code' => 'LENDING_RATE', 'name' => 'Lending interest rate', 'unit' => '%', 'frequency' => 'yearly', 'source' => 'World Bank', 'codes' => $wb('FR.INR.LEND'), 'why' => 'Debt service burden; cross-check to the PLR series'],
            ['code' => 'REAL_RATE', 'name' => 'Real interest rate', 'unit' => '%', 'frequency' => 'yearly', 'source' => 'World Bank', 'codes' => $wb('FR.INR.RINR'), 'why' => 'Affordability after inflation'],
            ['code' => 'PRIVATE_CREDIT', 'name' => 'Domestic credit to private sector', 'unit' => '% of GDP', 'frequency' => 'yearly', 'source' => 'World Bank', 'codes' => $wb('FS.AST.PRVT.GD.ZS'), 'why' => 'Credit conditions'],
            ['code' => 'AGRI_GROWTH', 'name' => 'Agriculture, forestry and fishing value added growth', 'unit' => 'annual %', 'frequency' => 'yearly', 'source' => 'World Bank', 'codes' => $wb('NV.AGR.TOTL.KD.ZG'), 'why' => 'MAIIC\'s book is agricultural and agro-industrial; the harvest is its own driver'],
            ['code' => 'BROAD_MONEY', 'name' => 'Broad money growth', 'unit' => 'annual %', 'frequency' => 'yearly', 'source' => 'World Bank', 'codes' => $wb('FM.LBL.BMNY.ZG'), 'why' => 'Liquidity conditions'],
            ['code' => 'RESERVES', 'name' => 'Total reserves in months of imports', 'unit' => 'months', 'frequency' => 'yearly', 'source' => 'World Bank', 'codes' => $wb('FI.RES.TOTL.MO'), 'why' => 'Exchange-rate and import-cover stress'],
            ['code' => 'DEBT_GDP', 'name' => 'Central government debt', 'unit' => '% of GDP', 'frequency' => 'yearly', 'source' => 'World Bank; IMF WEO for the forecast years', 'codes' => $wb('GC.DOD.TOTL.GD.ZS') + ['imf_weo' => 'GGXWDG_NGDP'], 'why' => 'Sovereign stress and crowding out'],
            ['code' => 'CURRENT_ACCOUNT', 'name' => 'Current account balance', 'unit' => '% of GDP', 'frequency' => 'yearly', 'source' => 'World Bank; IMF WEO for the forecast years', 'codes' => $wb('BN.CAB.XOKA.GD.ZS') + ['imf_weo' => 'BCA_NGDPD'], 'why' => 'External balance'],
            ['code' => 'POLICY_RATE', 'name' => 'RBM policy rate', 'unit' => '%', 'frequency' => 'monthly', 'source' => 'Reserve Bank of Malawi, by file', 'codes' => ['rbm' => 'policy_rate'], 'why' => 'The rate the PLR follows'],
            ['code' => 'PLR', 'name' => 'Prime lending rate', 'unit' => '%', 'frequency' => 'monthly', 'source' => 'The landing zone, P2_06 (E-Banker PLR master)', 'codes' => ['landing_zone' => 'P2_06'], 'why' => 'The floating-rate reference of the EIR engine, so the ECL and EIR modules read one series'],
        ];
    }

    public function run(): void
    {
        foreach (self::series() as $s) {
            DB::table('macro_statistics')->updateOrInsert(['statistic_code' => $s['code']], [
                'statistic_name' => $s['name'], 'statistic_description' => $s['why'], 'unit' => $s['unit'], 'frequency' => $s['frequency'],
                'data_source' => $s['source'], 'external_codes' => json_encode($s['codes']), 'country' => 'MWI', 'is_active' => 1,
                'website_link' => isset($s['codes']['world_bank']) ? 'https://data.worldbank.org/indicator/' . $s['codes']['world_bank'] . '?locations=MW' : null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->command?->info('Macro series: ' . count(self::series()) . ' seeded with their source codes.');
    }
}
