<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Ensures the Malawian Kwacha exists and is the organisation's reporting
 * currency. The dashboard (and anything else using the shared `currency`
 * prop) reads the currency from settings - previously "MWK" was hardcoded
 * in the UI while the setting pointed at USD and no MWK row existed.
 *
 * Idempotent:  php artisan db:seed --class=MaiicCurrencySeeder
 */
class MaiicCurrencySeeder extends Seeder
{
    public function run(): void
    {
        $mwk = Currency::firstOrCreate(
            ['code' => 'MWK'],
            [
                'name' => 'Malawian Kwacha',
                'symbol' => 'MK',
                'decimals' => 2,
                'xrate' => 1,
                'international_code' => 'MWK',
                'active' => 1,
                'is_default' => 0,
            ]
        );

        Setting::updateOrCreate(
            ['setting_key' => 'currency'],
            ['setting_value' => (string) $mwk->id]
        );

        $this->command?->info("MWK currency ensured (id {$mwk->id}) and set as the organisation currency.");
    }
}
