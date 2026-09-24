<?php

namespace Database\Seeders;

use App\Models\GovernanceSetting;
use App\Services\Eir\GovernanceService;
use Illuminate\Database\Seeder;

/**
 * Idempotent: writes Dupleix's recommended default for each of the twelve
 * governance settings (spec v3 section 8) as an APPROVED row effective
 * 1 January 2025, the first month of the loan books held. A key that already
 * has any row is left alone so an approved MAIIC change is never overwritten.
 *
 *   php artisan db:seed --class=GovernanceSettingsSeeder
 */
class GovernanceSettingsSeeder extends Seeder
{
    public const EFFECTIVE_FROM = '2025-01-01';
    public const REASON = 'Dupleix recommended default (spec v3 section 8)';

    public function run(): void
    {
        $seeded = 0;
        foreach (GovernanceService::catalogue() as $key => $definition) {
            if (GovernanceSetting::where('key', $key)->exists()) {
                continue;
            }

            GovernanceSetting::create([
                'key' => $key,
                'value' => $definition['default'],
                'options' => $definition['options'],
                'label' => $definition['label'],
                'description' => $definition['description'],
                'effective_from' => self::EFFECTIVE_FROM,
                'set_by' => null,
                'approved_by' => null,
                'approved_at' => now(),
                'reason' => self::REASON,
                'status' => GovernanceSetting::STATUS_APPROVED,
            ]);
            $seeded++;
        }

        $this->command?->info("Governance settings: {$seeded} default(s) seeded, " . (count(GovernanceService::catalogue()) - $seeded) . ' already present.');
    }
}
