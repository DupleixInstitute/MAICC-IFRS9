<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Seeder;

/**
 * The collateral types MAIIC's register uses, with MAIIC's own policy values where its December 2025
 * ECL model states them (14 October 2026).
 *
 * The collateral register import refuses any type code that is not on Collateral Types, so a database
 * with no types could not load a register. The 28 codes and names are those of MAIIC's loan origination
 * system as held in the demonstration database (maiic_ifrs9_demo), spelt as MAIIC holds them, so the
 * codes of a collateral file match.
 *
 * Values: MAIIC's ECL model of 14 December 2025 (MAIIC & FInES ECL Model - 14th December 2025_Final.xlsx,
 * sheet ASS4-Security, "Security realisation periods and haircut assumptions", each row marked "Per
 * Collateral Policy") gives the realisation period in months and the share of the value kept after the
 * haircut (the model computes "Security after haircut = Security x Haircut", so 0.70 keeps 70 percent) for
 * ten types. Those ten carry the model's figures here, with that source in the description. The model's
 * "Debenture over machinery" is this system's 115 "Debenture over Equipment" (8 months, 0.50), as MAIIC's
 * own list already holds it.
 *
 * The other eighteen types (the combinations such as "Charge over landed property and bill of sale over
 * motor vehicle", the guarantees the model does not price, unsecured and "yet to be obtained") are not in
 * the model's table. They are seeded with neither value (0, shown as "Not set", which the allocation reads
 * as no value recognised): a value for them is MAIIC's collateral policy to set, not something to derive.
 * A code already held is left exactly as it is.
 *
 *   php artisan db:seed --class=CollateralTypesSeeder
 */
class CollateralTypesSeeder extends Seeder
{
    public const SOURCE = "MAIIC ECL model 14 Dec 2025, ASS4-Security (Per Collateral Policy)";

    /** @var array<string, array{0:string, 1:int, 2:float}> code => [name, realisation months, value kept] (0, 0 = not set) */
    public const TYPES = [
        '101' => ['Bank Guarantee', 4, 1.00],
        '102' => ['Bill of Sale New Vehical', 5, 0.50],
        '103' => ['Bill of sale over motor vehicles', 8, 0.50],
        '104' => ['Bill of sale over motor vehicles and Others', 0, 0],
        '105' => ['Cash Cover and Registration of financing statement', 0, 0],
        '106' => ['Cash Cover and Shared Interest Gurantee', 0, 0],
        '107' => ['Cash Cover, Shared Interest Gurantee and Registration of financing statement', 0, 0],
        '108' => ['Cash in Acc withing Bank', 1, 0.90],
        '109' => ['Charge over landed property', 18, 0.70],
        '110' => ['Charge over landed property & Financing Statement', 0, 0],
        '111' => ['Charge over landed property and bill of sale over motor vehicle', 0, 0],
        '112' => ['Charge over landed property and shared interest', 0, 0],
        '113' => ['Contract proceeds from MICF', 0, 0],
        '114' => ['Corporate Gurantee', 0, 0],
        '115' => ['Debenture over Equipment', 8, 0.50],
        '116' => ['Financing Statement', 0, 0],
        '117' => ['Financing Statement & charge over Equipment', 0, 0],
        '118' => ['Government Guarantee', 4, 1.00],
        '119' => ['Guarantee', 4, 0.75],
        '120' => ['Gurantee and Registration of financing statement over machinery', 0, 0],
        '121' => ['Legal Chrg over Res. Property', 5, 0.70],
        '122' => ['Lien over performing loan book', 0, 0],
        '123' => ['Personal Guarantee', 0, 0],
        '124' => ['Shared Interest Gurantee & Cash Cover', 0, 0],
        '125' => ['Shared Interest Gurantee & Equipment', 0, 0],
        '126' => ['Surety Chrg over comm. Preoperty', 8, 0.70],
        '127' => ['Unsecured', 0, 0],
        '128' => ['Yet to be obtained', 0, 0],
    ];

    public function run(): void
    {
        if (! Schema::hasTable('collateral_types')) {
            return;
        }
        $added = 0;
        $priced = 0;
        foreach (self::TYPES as $code => [$name, $months, $kept]) {
            if (DB::table('collateral_types')->where('type_code', (string) $code)->exists()) {
                continue;
            }
            $fromModel = $kept > 0;
            DB::table('collateral_types')->insert([
                'type_code' => (string) $code, 'type_name' => $name,
                'description' => $fromModel ? self::SOURCE : 'Not in the ECL model\'s policy table: set per MAIIC\'s collateral policy',
                'realisation_period' => $months, 'standard_haircut' => $kept, 'liquidity_factor' => 0, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $added++;
            $priced += $fromModel ? 1 : 0;
        }
        $this->command?->info("Collateral types: {$added} added ({$priced} with the ECL model's policy values), "
            . (count(self::TYPES) - $added) . ' already held; the rest are set by MAIIC on Collateral Types.');
    }
}
