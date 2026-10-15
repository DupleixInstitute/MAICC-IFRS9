<?php

namespace Database\Seeders;

use App\Models\GovernanceSetting;
use App\Services\Eir\GovernanceService;
use App\Support\MakerChecker;
use Illuminate\Database\Seeder;

/**
 * Idempotent: writes Dupleix's recommended default for every governance
 * setting in the catalogue (the twelve conventions of spec v3 section 8 and
 * the open choices of section 4) as an APPROVED row effective from inception.
 * The schedule generator and the EIR resolve a setting as at the contract's
 * origination date, and MAIIC's earliest contract originated in March 2020
 * (63 of the 144 in the master predate 2024), so a default dated from the
 * first loan-book month (1 January 2025, as it was until the system audit of
 * 9 October 2026 found 16 contracts refused for "no approved value in force")
 * left every older contract with no day count, no rate basis and no fee
 * treatment. A key that already has any row is left alone so an approved
 * MAIIC change is never overwritten; re-running the seeder on an installed
 * system adds only the keys that are new. Defaults already seeded at
 * 2025-01-01 are backdated by the migration of 9 October 2026.
 *
 *   php artisan db:seed --class=GovernanceSettingsSeeder
 */
class GovernanceSettingsSeeder extends Seeder
{
    public const EFFECTIVE_FROM = '1900-01-01';
    public const UAT_REASON = 'Seeded for UAT on a UAT server (APP_ENV=uat): administrators may approve their own proposals, each recorded on the self-approvals report; not a MAIIC approval (Staging Module Specification v1, section 8.9)';
    public const REASON = 'Dupleix recommended default (spec v3 section 8)';

    /**
     * The staging rules an existing database ran before the Staging Module
     * (Staging Module Specification v1, section 3; review of 15 October 2026):
     * thresholds by facility term with Mega Farm always short-term, the
     * directive's count from the oldest overdue instalment with the count from
     * maturity and the schedule recount, the qualitative register applied, and a
     * cure by months below the threshold. The missed-instalment trigger and the
     * cure months keep the values the database already holds.
     */
    public const STAGING_BEFORE_THE_MODULE = [
        \App\Services\Eir\StagingPolicy::PRESET => \App\Services\Eir\StagingPolicy::PRESET_CUSTOM,
        'staging_threshold_profile' => \App\Services\Eir\StagingPolicy::PROFILE_BY_TERM,
        'stage2_threshold_medium_long' => \App\Services\Eir\StagingPolicy::STAGE2_31,
        'staging_mega_farm_term' => \App\Services\Eir\StagingPolicy::MF_SHORT,
        'dpd_after_maturity' => \App\Services\Eir\StagingPolicy::MATURITY_COUNT,
        'dpd_schedule_correction' => \App\Services\Eir\StagingPolicy::SCHEDULE_RECOUNT,
        'sicr_qualitative_register' => \App\Services\Eir\StagingPolicy::APPLIED,
        'sicr_pd_change' => \App\Services\Eir\StagingPolicy::NOT_APPLIED,
        'stage_cure_test' => \App\Services\Eir\StagingPolicy::CURE_MONTHS,
    ];

    public const EXISTING_REASON = 'Seeded on a database in use before the Staging Module (15 October 2026), at the staging rules it already ran (Custom), so nothing it stages changes and no locked month reads a rule it did not run on. Not a MAIIC decision: MAIIC adopts single-ladder or directive-aligned staging by a proposal, its supporting document and a second person\'s approval (Staging Module Specification v1, section 3.2).';

    public function run(): void
    {
        $seeded = 0;
        // a database that staged before the Staging Module holds these settings already: it keeps its staging
        // (a fresh install starts with single-ladder staging, the 31 December 2025 policy)
        $existing = GovernanceSetting::where('key', 'stage3_missed_instalments')->exists();
        foreach (GovernanceService::catalogue() as $key => $definition) {
            if (GovernanceSetting::where('key', $key)->exists()) {
                continue;
            }
            $kept = $existing && array_key_exists($key, self::STAGING_BEFORE_THE_MODULE);

            // UAT mode (Staging Module Specification v1, section 8.9, item 3): a fresh install on a UAT
            // server (APP_ENV=uat) starts with the override allowed, labelled as seeded for UAT
            $uat = $key === MakerChecker::SETTING && app()->environment('uat');
            GovernanceSetting::create([
                'key' => $key,
                'value' => $uat ? MakerChecker::ALLOWED : ($kept ? self::STAGING_BEFORE_THE_MODULE[$key] : $definition['default']),
                'options' => $definition['options'],
                'label' => $definition['label'],
                'description' => $definition['description'],
                'effective_from' => self::EFFECTIVE_FROM,
                'set_by' => null,
                'approved_by' => null,
                'approved_at' => now(),
                // a technical default names itself as one, never as a MAIIC approval (fli_without_model, 14 October 2026)
                'reason' => $uat ? self::UAT_REASON : ($kept ? self::EXISTING_REASON : ($definition['seed_reason'] ?? self::REASON)),
                'status' => GovernanceSetting::STATUS_APPROVED,
            ]);
            $seeded++;
        }

        $this->command?->info("Governance settings: {$seeded} default(s) seeded, " . (count(GovernanceService::catalogue()) - $seeded) . ' already present.');
    }
}
