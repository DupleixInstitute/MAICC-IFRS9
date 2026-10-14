<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The collateral types MAIIC's register uses (14 October 2026).
 *
 * The collateral register import refuses any type code that is not on
 * Collateral Types, and the sample file takes its type from the first code
 * held, so on a database with no types the sample's Check file failed ("no
 * values are set up yet"). No seeder shipped the types: the list lived only in
 * the databases where someone had typed it in. These are the nineteen codes
 * and names of the app's demonstration database (maiic_ifrs9_demo, the type
 * names of MAIIC's loan origination system, spelt as MAIIC holds them, so the
 * codes of a collateral file match).
 *
 * Only the code and the name are seeded. The value recognised after the
 * haircut and the months to realise an item are MAIIC's collateral policy,
 * not a technical default: each type is seeded with neither (0, which the
 * Collateral Types tab shows as "Not set" and the allocation reads as no
 * value recognised), so no pledged item covers a loan until a person sets
 * the type's values. A code already held is left exactly as it is.
 *
 *   php artisan db:seed --class=CollateralTypesSeeder
 */
class CollateralTypesSeeder extends Seeder
{
    /** @var array<string,string> code => name */
    public const TYPES = [
        '101' => 'Bank Guarantee',
        '102' => 'Bill of Sale New Vehical',
        '103' => 'Bill of sale over motor vehicles',
        '104' => 'Bill of sale over motor vehicles and Others',
        '105' => 'Cash Cover and Registration of financing statement',
        '106' => 'Cash Cover and Shared Interest Gurantee',
        '107' => 'Cash Cover, Shared Interest Gurantee and Registration of financing statement',
        '108' => 'Cash in Acc withing Bank',
        '109' => 'Charge over landed property',
        '110' => 'Charge over landed property & Financing Statement',
        '111' => 'Charge over landed property and bill of sale over motor vehicle',
        '112' => 'Charge over landed property and shared interest',
        '113' => 'Contract proceeds from MICF',
        '114' => 'Corporate Gurantee',
        '115' => 'Debenture over Equipment',
        '116' => 'Financing Statement',
        '117' => 'Financing Statement & charge over Equipment',
        '118' => 'Government Guarantee',
        '119' => 'Guarantee',
    ];

    public function run(): void
    {
        if (! Schema::hasTable('collateral_types')) {
            return;
        }
        $added = 0;
        foreach (self::TYPES as $code => $name) {
            if (DB::table('collateral_types')->where('type_code', (string) $code)->exists()) {
                continue;
            }
            DB::table('collateral_types')->insert([
                'type_code' => (string) $code, 'type_name' => $name, 'description' => null,
                // MAIIC's policy sets these on Collateral Types; until then no value is recognised
                'realisation_period' => 0, 'standard_haircut' => 0, 'liquidity_factor' => 0, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $added++;
        }
        $this->command?->info("Collateral types: {$added} added, " . (count(self::TYPES) - $added) . ' already held; the value recognised and the realisation months are set by MAIIC on Collateral Types.');
    }
}
