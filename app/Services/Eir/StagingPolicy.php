<?php

namespace App\Services\Eir;

use Carbon\CarbonInterface;

/**
 * The staging rules in force at a month-end (MAIIC Staging Module
 * Specification v1, section 3).
 *
 * Every rule is a governed setting. The preset in force decides which ones
 * apply: under "Single-ladder staging" or "Directive-aligned staging" the
 * preset's own values govern every rule, so adopting a policy is one governed
 * change; under "Custom" each individual setting governs. Single-ladder
 * staging (MAIIC's staging policy as at 31 December 2025, the ECL workbooks
 * of 14 December 2025) is the seeded default. Directive-aligned staging is
 * defined but never in force until MAIIC proposes it and a second person
 * approves it; nothing switches it on by date and the bootstrap never
 * approves it.
 *
 * No rule has a default in code (decision D21): a setting missing at the
 * month-end stops staging with a named error (GovernanceService::get).
 */
final class StagingPolicy
{
    public const PRESET = 'staging_preset';
    public const PRESET_SINGLE = 'Single-ladder staging';
    public const PRESET_DIRECTIVE = 'Directive-aligned staging';
    public const PRESET_CUSTOM = 'Custom';

    public const PROFILE_SINGLE = 'One threshold for every facility (181 days)';
    public const PROFILE_BY_TERM = 'By facility term (short-term 91, medium and long-term 181)';
    public const PROFILE_IFRS9 = 'IFRS 9 presumption for every facility (91 days)';

    public const STAGE2_31 = '31 days';
    public const STAGE2_91 = '91 days, with documented rebuttal evidence';

    public const MF_SAME = 'Same threshold as every facility';
    public const MF_BY_TENOR = 'By tenor';
    public const MF_SHORT = 'Always short-term (91 days)';

    public const BASIS_OLDEST = 'Oldest overdue instalment (the directive)';
    public const BASIS_BUCKET = 'Bucket ageing of the overdue amounts';

    public const MATURITY_CORE = 'Core-system ageing only';
    public const MATURITY_COUNT = 'Count from maturity when longer';

    public const SCHEDULE_AS_GIVEN = 'Core-system report as given';
    public const SCHEDULE_RECOUNT = 'Recount against the schedule in force and the ledger';

    public const APPLIED = 'Applied';
    public const NOT_APPLIED = 'Not applied';
    public const PD_DOUBLED = 'PD at least doubled since origination';

    public const CURE_MONTHS = 'Months below the threshold';
    public const CURE_CLEARED = 'All arrears cleared';
    public const CURE_BOTH = 'Arrears cleared, then the months';

    /** The staging_thresholds class each Stage 3 profile reads. */
    public const CLASS_SINGLE = 'SINGLE_LADDER';
    public const CLASS_IFRS9 = 'IFRS9_PRESUMPTION';
    public const CLASS_MEGA = 'MEGA_FARM';
    public const CLASS_DEFAULT = 'DEFAULT';

    /** The comment every 31 December 2025 option carries in the Governance Centre. */
    public const POLICY_2025 = 'MAIIC\'s staging policy as at 31 December 2025 (ECL workbooks of 14 December 2025).';

    /** The individual settings, in the order of the specification's section 3, with the rule each sets. */
    public const SETTINGS = [
        'staging_threshold_profile' => 'threshold_profile',
        'stage2_threshold_medium_long' => 'stage2_medium_long',
        'staging_mega_farm_term' => 'mega_farm_term',
        'dpd_basis' => 'dpd_basis',
        'dpd_after_maturity' => 'after_maturity',
        'dpd_schedule_correction' => 'schedule_correction',
        'stage3_missed_instalments' => 'missed_instalments',
        'sicr_qualitative_register' => 'qualitative_register',
        'sicr_pd_change' => 'pd_change',
        'stage_cure_months' => 'cure_months',
        'stage_cure_test' => 'cure_test',
    ];

    /** The value of every individual setting under each named preset. */
    public const PRESETS = [
        self::PRESET_SINGLE => [
            'staging_threshold_profile' => self::PROFILE_SINGLE,
            'stage2_threshold_medium_long' => self::STAGE2_31,
            'staging_mega_farm_term' => self::MF_SAME,
            'dpd_basis' => self::BASIS_BUCKET,
            'dpd_after_maturity' => self::MATURITY_CORE,
            'dpd_schedule_correction' => self::SCHEDULE_AS_GIVEN,
            'stage3_missed_instalments' => self::NOT_APPLIED,
            'sicr_qualitative_register' => self::NOT_APPLIED,
            'sicr_pd_change' => self::NOT_APPLIED,
            'stage_cure_months' => '0 months (no probation)',
            'stage_cure_test' => self::CURE_MONTHS,
        ],
        self::PRESET_DIRECTIVE => [
            'staging_threshold_profile' => self::PROFILE_BY_TERM,
            'stage2_threshold_medium_long' => self::STAGE2_31,
            'staging_mega_farm_term' => self::MF_BY_TENOR,
            'dpd_basis' => self::BASIS_OLDEST,
            'dpd_after_maturity' => self::MATURITY_COUNT,
            'dpd_schedule_correction' => self::SCHEDULE_RECOUNT,
            'stage3_missed_instalments' => '4 consecutive',
            'sicr_qualitative_register' => self::APPLIED,
            'sicr_pd_change' => self::NOT_APPLIED,
            'stage_cure_months' => '3 months',
            'stage_cure_test' => self::CURE_BOTH,
        ],
    ];

    /**
     * @param array<string,string> $values the value of each individual setting, keyed by setting
     * @param array<string,string> $source where each value came from: the preset, or the setting itself
     */
    private function __construct(public readonly string $preset, public readonly array $values, public readonly array $source)
    {
    }

    /** The rules in force at a date: the preset's values, or under Custom the individual settings. */
    public static function at(GovernanceService $governance, CarbonInterface $asOf): self
    {
        $preset = $governance->get(self::PRESET, $asOf);
        $values = []; $source = [];
        foreach (array_keys(self::SETTINGS) as $key) {
            if (isset(self::PRESETS[$preset][$key])) {
                $values[$key] = self::PRESETS[$preset][$key];
                $source[$key] = 'preset: ' . $preset;
            } else {
                $values[$key] = $governance->get($key, $asOf);
                $source[$key] = 'setting';
            }
        }

        return new self($preset, $values, $source);
    }

    public function value(string $key): string
    {
        return $this->values[$key];
    }

    /**
     * The staging_thresholds class a facility is staged on, and whether a row
     * of exactly that class is required (true) or the DEFAULT rows by tenor
     * may serve (false).
     *
     * @return array{0:string,1:bool}
     */
    public function thresholdClass(bool $megaFarm): array
    {
        $profile = $this->values['staging_threshold_profile'];
        if ($megaFarm && $this->values['staging_mega_farm_term'] === self::MF_SHORT) {
            return [self::CLASS_MEGA, true];
        }

        return match ($profile) {
            self::PROFILE_SINGLE => [self::CLASS_SINGLE, true],
            self::PROFILE_IFRS9 => [self::CLASS_IFRS9, true],
            // by facility term: the DEFAULT rows by tenor, for a Mega Farm loan as for any facility
            default => [self::CLASS_DEFAULT, false],
        };
    }

    /** @return list<string> the staging_thresholds classes the policy reads (the IFRS 9 note lists only these) */
    public function thresholdClasses(): array
    {
        $classes = [$this->thresholdClass(false)[0]];
        if ($this->values['staging_mega_farm_term'] === self::MF_SHORT) {
            $classes[] = self::CLASS_MEGA;
        }

        return $classes;
    }

    /** The Stage 2 threshold after the medium- and long-term setting: 91 days where it is chosen and the facility is over 12 months. */
    public function stage2(int $rowStage2, int $tenorMonths, int $stage3): int
    {
        if ($this->values['stage2_threshold_medium_long'] === self::STAGE2_91 && $tenorMonths > 12) {
            return min(max($rowStage2, 91), $stage3);
        }

        return $rowStage2;
    }

    public function bucketBasis(): bool
    {
        return $this->values['dpd_basis'] === self::BASIS_BUCKET;
    }

    /** Consecutive missed instalments that lift a loan to Stage 3; 0 when the trigger is not applied. */
    public function missedInstalments(): int
    {
        return (int) (preg_match('/^(\d+)/', $this->values['stage3_missed_instalments'], $m) ? $m[1] : 0);
    }

    public function qualitativeApplied(): bool
    {
        return $this->values['sicr_qualitative_register'] === self::APPLIED;
    }

    public function cureMonths(): int
    {
        return (int) (preg_match('/^(\d+)/', $this->values['stage_cure_months'], $m) ? $m[1] : 0);
    }

    public function cureTest(): string
    {
        return $this->values['stage_cure_test'];
    }

    /** What the audit log records of the policy a month was staged under. */
    public function toArray(): array
    {
        return ['preset' => $this->preset, 'rules' => $this->values, 'source' => $this->source];
    }
}
