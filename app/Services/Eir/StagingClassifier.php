<?php

namespace App\Services\Eir;

use App\Models\StagingThreshold;
use Throwable;

/**
 * DPD staging waterfall as configuration (docs/EIR_Build.md §4 Phase 2).
 *
 * The tape carries arrears in buckets (1–30, 31–90, 91–180, 181–270,
 * 271–360 days); the loan's DPD is taken as the lower bound of the highest
 * non-empty bucket, then compared to the governing staging_thresholds row.
 *
 * The seeded DEFAULT rule (stage 2 at 31 DPD, stage 3 at 181 DPD)
 * reproduces the previously hardcoded ladder exactly, so behaviour does
 * not change until a differently-configured rule — e.g. the RBM-based
 * 90-day backstop for long-tenor facilities (2025 review finding 1) —
 * is signed off and activated. When no configuration exists at all
 * (fresh install, mid-migration), the same hardcoded ladder applies.
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

    /** The pre-config ladder: stage 2 from 31 DPD, stage 3 from 181 DPD. */
    private const FALLBACK_STAGE2_DPD = 31;
    private const FALLBACK_STAGE3_DPD = 181;

    private ?StagingThreshold $cached = null;
    private bool $resolved = false;

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
        // One lookup per import run; the rule set does not change mid-file.
        if (! $this->resolved) {
            try {
                $this->cached = StagingThreshold::forFacility($facilityClass, $tenorMonths);
            } catch (Throwable) {
                // Table absent (fresh install, tests without migrations):
                // fall through to the hardcoded ladder.
                $this->cached = null;
            }
            $this->resolved = true;
        }

        return $this->cached
            ? [(int) $this->cached->stage2_dpd, (int) $this->cached->stage3_dpd]
            : [self::FALLBACK_STAGE2_DPD, self::FALLBACK_STAGE3_DPD];
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
