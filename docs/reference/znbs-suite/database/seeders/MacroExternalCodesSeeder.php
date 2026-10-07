<?php

namespace Database\Seeders;

use App\Models\MacroVariable;
use Illuminate\Database\Seeder;

/**
 * Maps the system's macro variables to their World Bank Open Data indicator
 * codes (Zambia, ISO3 ZMB) so the World Bank importer can pull real historical
 * series automatically. Only variables with a genuine WB country series are
 * mapped; bank-specific / supervisory series (BOZ_MPR, NPL_RATIO, LCR, NSFR,
 * INDUSTRY_CAR, ...) have no WB equivalent and stay locally sourced.
 *
 * Run by the bootstrap before the World Bank import step.
 */
class MacroExternalCodesSeeder extends Seeder
{
    /** variable code => World Bank indicator code */
    private const WORLD_BANK = [
        'GDP_GROWTH'     => 'NY.GDP.MKTP.KD.ZG',  // Real GDP growth (annual %)
        'CPI'            => 'FP.CPI.TOTL.ZG',      // Inflation, consumer prices (annual %)
        'ZMW_USD'        => 'PA.NUS.FCRF',         // Official exchange rate (LCU per USD)
        'UNEMPLOYMENT'   => 'SL.UEM.TOTL.ZS',      // Unemployment, total (% of labour force)
        'GNI_PER_CAPITA' => 'NY.GNP.PCAP.CD',      // GNI per capita, Atlas method (current USD)
        'FX_RESERVES'    => 'FI.RES.TOTL.MO',      // Total reserves (months of imports)
        'DEBT_GDP'       => 'GC.DOD.TOTL.GD.ZS',   // Central govt debt, total (% of GDP)
        'FISCAL_DEFICIT' => 'GC.NLD.TOTL.GD.ZS',   // Net lending / borrowing (% of GDP)
        'CREDIT_GROWTH'  => 'FS.AST.PRVT.GD.ZS',   // Domestic credit to private sector (% of GDP)
    ];

    public function run(): void
    {
        foreach (self::WORLD_BANK as $code => $wbCode) {
            $variable = MacroVariable::where('code', $code)->first();
            if (! $variable) {
                continue;
            }
            $external = is_array($variable->external_codes) ? $variable->external_codes : [];
            $external['world_bank'] = $wbCode;
            $variable->update([
                'external_codes' => $external,
                'source' => $variable->source ?: 'World Bank Open Data',
            ]);
        }
    }
}
