<?php

namespace App\Services\Eir;

use App\Models\StagingThreshold;
use RuntimeException;

/**
 * DPD staging waterfall as configuration (docs/EIR_Build.md §4 Phase 2).
 *
 * The tape carries arrears in buckets (1–30, 31–90, 91–180, 181–270,
 * 271–360 days); the loan's DPD is taken as the lower bound of the highest
 * non-empty bucket, then compared to the governing staging_thresholds row.
 *
 * The rule is the approved staging_thresholds row in force at the end of
 * the reporting period the import is for, not at today's date, so a re-run
 * of a past month keeps the rules that governed it (D21; system audit of
 * 9 October 2026, H11). With no rule in force the import stops and names
 * what is missing: until 14 October 2026 it fell back to a ladder written
 * in code (stage 2 at 31 days, stage 3 at 181).
 */
class StagingClassifier
{
    /** Bucket lower bounds, highest first; keys as they appear post-normalisation. */
    private const BUCKETS = [
        271 => ['271_360_days', '271-360_days'],
        181 => ['181_270_days', '181-270_days'],
        91  => ['91_180_days', '91-180_days'],
        31  => ['31_90_days', '31-90_days'],
        1   => ['1_30_days', '1-30_days'],
    ];

    /** @var array<string, array{0:int,1:int}> the rule per class and tenor, read once per import */
    private array $cached = [];

    /** @param string $asOf the reporting period end the rows are staged at (Y-m-d) */
    public function __construct(private readonly string $asOf)
    {
    }

    /**
     * @param array   $row          normalised import row (arrears buckets)
     * @param ?string $facilityClass staging_thresholds class; null = DEFAULT
     * @param int     $tenorMonths  loan tenor, for class-specific rules
     */
    public function classify(array $row, ?string $facilityClass = null, int $tenorMonths = 0): string
    {
        $dpd = $this->daysPastDue($row);
        if ($dpd === 0) {
            return '1';
        }

        [$stage2, $stage3] = $this->thresholds($facilityClass, $tenorMonths);

        if ($dpd >= $stage3) {
            return '3';
        }
        if ($dpd >= $stage2) {
            return '2';
        }

        return '1';
    }

    /** Lower bound of the highest non-empty arrears bucket; 0 = current. */
    public function daysPastDue(array $row): int
    {
        foreach (self::BUCKETS as $lowerBound => $keys) {
            foreach ($keys as $key) {
                if ($this->hasPositiveValue($row[$key] ?? null)) {
                    return $lowerBound;
                }
            }
        }

        return 0;
    }

    /** @return array{0:int,1:int} [stage2_dpd, stage3_dpd] */
    private function thresholds(?string $facilityClass, int $tenorMonths): array
    {
        $key = ($facilityClass ?? 'DEFAULT') . '|' . $tenorMonths;
        if (! isset($this->cached[$key])) {
            $rule = StagingThreshold::forFacility($facilityClass, $tenorMonths, $this->asOf);
            if ($rule === null) {
                throw new \App\Exceptions\StagingRuleMissingException('No approved staging threshold is in force for facility class ' . ($facilityClass ?? 'DEFAULT')
                    . " (tenor {$tenorMonths} months) at {$this->asOf}. Approve one on Settings, Staging basis; the import does not assume 31 and 181 days.");
            }
            $this->cached[$key] = [(int) $rule->stage2_dpd, (int) $rule->stage3_dpd];
        }

        return $this->cached[$key];
    }

    /**
     * Same value-cleaning the import has always applied: NBSP/zero-width
     * garbage, "-" placeholders, thousands separators.
     */
    private function hasPositiveValue($value): bool
    {
        if ($value === null || $value === false) {
            return false;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value > 0;
        }

        $value = trim((string) $value);
        if ($value === '' || $value === '-' || $value === ' -   ') {
            return false;
        }

        $value = str_replace(["\xC2\xA0", "\xA0", "\xE2\x80\x8B", "\xE2\x80\x8C", "\t", "\n", "\r", ',', ' ', '"'], '', $value);
        $value = trim(preg_replace('/[\x00-\x1F\x7F\xA0\xAD]/u', '', $value));

        return is_numeric($value) && (float) $value > 0;
    }
}
