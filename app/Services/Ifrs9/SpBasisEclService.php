<?php

namespace App\Services\Ifrs9;

use App\Models\InternalGradeProfile;
use App\Services\AuditLoggerService;
use App\Services\Eir\GovernanceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * The S&P basis: an alternative ECL on the method of MAIIC's December 2025 ECL model, for comparison
 * only (spec v4 section 17, 15 October 2026). It never changes a booked figure: it reads the month's
 * loan book and the active internal grading profile and writes nothing but sp_basis_runs and
 * sp_basis_results.
 *
 * The model's method (MAIIC & FInES ECL Model - 14th December 2025_Final.xlsx, ECLComp; results review
 * of 15 October 2026, Appendix F), with each choice a governed setting:
 *  - stage: the arrears bracket of the days past due, 0-30 Stage 1, 31-180 Stage 2, over 180 Stage 3
 *    (sp_basis_staging; or the system's own stage);
 *  - grade: from the same bracket, 0-30 grade 3 (A), 31-90 grade 6 (B), 91-180 grade 8 (CCC), over 180
 *    grade 10 (default) (sp_basis_grades), read from the active profile with its S&P cumulative rates;
 *  - PD: Stage 1 the grade's one-year cumulative rate, Stage 2 its cumulative rate to maturity (whole
 *    years, at least one, at most the profile's longest tenor), Stage 3 100 percent; times one plus the
 *    forward-looking factor (sp_basis_macro_factor), capped at 100 percent;
 *  - EAD and LGD: the system's own for the loan (the model's collateral LGD needs a security register
 *    the system does not hold for most loans);
 *  - the model's typed recovery (sp_basis_recovery): the present value of 46.98 percent of EAD received
 *    360 days out at the loan's rate, deducted from EAD before PD and LGD, the result floored at nil;
 *  - ECL = PD x LGD x (EAD - recovery), undiscounted, as the model.
 * The loans are those the system measures in the main ECL (a loan with an LGD and an EAD in the month);
 * the Mega Farm programme, measured by its own module and by a separate model at MAIIC, is outside it.
 */
class SpBasisEclService
{
    public const STAGING_MODEL = 'The December 2025 model: the arrears bracket (0 to 30 days Stage 1, 31 to 180 Stage 2, over 180 Stage 3)';
    public const STAGING_SYSTEM = 'The system\'s own stage';
    public const GRADES_MODEL = 'The December 2025 model: 0 to 30 days grade 3, 31 to 90 grade 6, 91 to 180 grade 8, over 180 grade 10';
    public const MACRO_TBILL = 'One plus the forecast T-bill rate, 15.75 percent (the December 2025 model)';
    public const MACRO_NPL = 'One plus the NPL change, 27.2 percent (the row the model heads as the PD adjustment)';
    public const MACRO_NONE = 'None: the S&P rate as it stands';
    public const RECOVERY_MODEL = '46.98 percent of EAD received 360 days out, discounted at the loan rate (the December 2025 model)';
    public const RECOVERY_NONE = 'None';
    public const FEED_OFF = 'No: internal grades are a comparison only (the S&P basis); the engine keeps its measured PD';
    public const FEED_ON = 'Yes: Update loan book may write an active profile\'s PDs to the loan book';

    /** Whether an internal grading profile may write its PDs to the loan book (internal_grade_pd_feed); off when not set. */
    public function feedOn(): bool
    {
        try {
            return $this->governance->get('internal_grade_pd_feed') === self::FEED_ON;
        } catch (Throwable) {
            return false;
        }
    }

    public const SEED_REASON = 'Technical default reproducing the December 2025 model, for the S&P basis comparison only; it changes no booked figure (spec v4 section 17, 15 October 2026)';

    /** The model's bracket-to-grade map: the upper bound of days past due and the grade code. */
    public const BRACKET_GRADES = [[30, '3'], [90, '6'], [180, '8'], [PHP_INT_MAX, '10']];

    public function __construct(private readonly GovernanceService $governance)
    {
    }

    /** The settings in force, read as numbers; refused (fail closed) when one is not set. */
    public function settings(): array
    {
        $get = function (string $key) {
            try {
                $v = $this->governance->get($key);
            } catch (Throwable $e) {
                $v = null;
            }
            if ($v === null || $v === '') {
                throw new RuntimeException("The governed setting {$key} has no value in force: set it in the Governance Centre.");
            }

            return (string) $v;
        };
        $staging = $get('sp_basis_staging');
        $grades = $get('sp_basis_grades');
        $macro = $get('sp_basis_macro_factor');
        $recovery = $get('sp_basis_recovery');

        return [
            'staging' => $staging, 'grades' => $grades, 'macro' => $macro, 'recovery' => $recovery,
            'model_staging' => $staging === self::STAGING_MODEL,
            'macro_factor' => $macro === self::MACRO_NONE ? 0.0 : self::percentIn($macro),
            'recovery_share' => $recovery === self::RECOVERY_NONE ? 0.0 : self::percentIn($recovery),
        ];
    }

    /** The first percentage written in a setting's text, as a fraction. */
    public static function percentIn(string $text): float
    {
        if (preg_match('/(\d+(?:\.\d+)?)\s*percent/', $text, $m) !== 1) {
            throw new RuntimeException("No percentage in the setting \"{$text}\".");
        }

        return (float) $m[1] / 100;
    }

    public static function gradeFor(int $dpd): string
    {
        foreach (self::BRACKET_GRADES as [$upTo, $grade]) {
            if ($dpd <= $upTo) {
                return $grade;
            }
        }

        return '10';
    }

    public static function modelStage(int $dpd): int
    {
        return $dpd <= 30 ? 1 : ($dpd <= 180 ? 2 : 3);
    }

    /**
     * Run the S&P basis for a month and keep it (a later run of the same month is kept beside it; the
     * screen shows the latest).
     *
     * @return array{run_id:int, loans:int, ecl:float, system_ecl:float, by_stage:array, notes:list<string>}
     */
    public function run(string $period, ?int $userId = null): array
    {
        if (preg_match('/^\d{4}-\d{2}$/', $period) !== 1) {
            throw new RuntimeException("A month is written YYYY-MM; \"{$period}\" is not one.");
        }
        $s = $this->settings();
        $profile = InternalGradeProfile::where('is_active', true)->first();
        if ($profile === null) {
            throw new RuntimeException('No internal grading profile is active: activate the S&P profile on Internal Grades first.');
        }
        $curves = [];
        foreach ($profile->mappings()->with('tenorPds')->get() as $m) {
            $curves[(string) $m->grade_code] = ['rating' => $m->sp_rating, 'pds' => $m->tenorPds->pluck('pd_probability', 'tenor_years')->map(fn ($v) => (float) $v)->all()];
        }
        $missing = array_diff(array_unique(array_column(self::BRACKET_GRADES, 1)), array_keys($curves));
        if ($missing !== []) {
            throw new RuntimeException("The active profile \"{$profile->name}\" has no grade " . implode(', ', $missing) . ', which the bracket map needs.');
        }
        $maxTenor = max(1, (int) $profile->max_tenor_years);
        $end = CarbonImmutable::createFromFormat('Y-m', $period)->endOfMonth()->startOfDay();

        // the Mega Farm programme's loans, by the programme module's own definition, are outside the comparison
        $megaFarm = app(\App\Services\MegaFarm\MegaFarmEclService::class)->loans($period)->pluck('contract_id')->map(fn ($v) => (string) $v)->all();
        $loans = DB::table('loan_books')->where('reporting_period', $period)->whereNotNull('lgd_value')->where('ead', '>', 0)
            ->when($megaFarm !== [], fn ($q) => $q->whereNotIn('contract_id', $megaFarm))
            ->get(['contract_id', 'customer_name', 'overdue_days', 'ifrs9stage_post_qualitative', 'interest_rate', 'lgd_value', 'ead', 'pd_post_fli', 'ecl_value', 'due_date']);
        if ($loans->isEmpty()) {
            throw new RuntimeException("No loan in {$period} has an LGD and an EAD: run the month's ECL first.");
        }
        $notes = ['The Mega Farm programme is outside the S&P basis: it is measured by its own module (and at MAIIC by a separate model).'];
        $noMaturity = 0;

        $rows = [];
        foreach ($loans as $l) {
            $dpd = max(0, (int) $l->overdue_days);
            $grade = self::gradeFor($dpd);
            $stage = $s['model_staging'] ? self::modelStage($dpd) : max(1, min(3, (int) $l->ifrs9stage_post_qualitative));
            $horizon = 1;
            if ($stage === 2) {
                if ($l->due_date) {
                    $months = $end->diffInMonths(CarbonImmutable::parse($l->due_date), false);
                    $horizon = max(1, min($maxTenor, (int) ceil(max(0, $months) / 12)));
                } else {
                    $noMaturity++;
                }
            }
            $spPd = $stage === 3 ? 1.0 : (float) ($curves[$grade]['pds'][$horizon] ?? $curves[$grade]['pds'][max(array_keys($curves[$grade]['pds']))]);
            $pd = $stage === 3 ? 1.0 : min(1.0, $spPd * (1 + $s['macro_factor']));
            $ead = (float) $l->ead;
            $rate = (float) $l->interest_rate / 100;
            $recovery = $s['recovery_share'] > 0 ? $s['recovery_share'] * $ead / pow(1 + $rate, 360 / 365) : 0.0;
            $lgd = (float) $l->lgd_value;
            $ecl = round($pd * $lgd * max(0.0, $ead - $recovery), 2);
            $rows[] = [
                'contract_id' => (string) $l->contract_id, 'customer_name' => $l->customer_name, 'days_past_due' => $dpd, 'stage' => $stage,
                'grade_code' => $grade, 'sp_rating' => $curves[$grade]['rating'], 'horizon_years' => $stage === 3 ? 0 : $horizon,
                'sp_pd' => round($spPd, 8), 'macro_factor' => $stage === 3 ? 0 : $s['macro_factor'], 'pd' => round($pd, 8), 'lgd' => round($lgd, 8),
                'ead' => round($ead, 2), 'recovery_pv' => round($recovery, 2), 'ecl' => $ecl,
                'system_stage' => $l->ifrs9stage_post_qualitative !== null ? (int) $l->ifrs9stage_post_qualitative : null,
                'system_pd' => $l->pd_post_fli !== null ? round((float) $l->pd_post_fli, 8) : null,
                'system_ecl' => $l->ecl_value !== null ? round((float) $l->ecl_value, 2) : null,
            ];
        }
        if ($noMaturity > 0) {
            $notes[] = "{$noMaturity} Stage 2 loan(s) have no maturity date: their PD is taken at one year.";
        }

        $byStage = [];
        foreach ([1, 2, 3] as $st) {
            $in = array_filter($rows, fn ($r) => $r['stage'] === $st);
            $byStage[$st] = ['loans' => count($in), 'ead' => round(array_sum(array_column($in, 'ead')), 2), 'ecl' => round(array_sum(array_column($in, 'ecl')), 2)];
        }
        $systemByStage = [];
        foreach ([1, 2, 3] as $st) {
            $in = array_filter($rows, fn ($r) => $r['system_stage'] === $st);
            $systemByStage[$st] = ['loans' => count($in), 'ead' => round(array_sum(array_column($in, 'ead')), 2), 'ecl' => round(array_sum(array_map(fn ($r) => (float) $r['system_ecl'], $in)), 2)];
        }

        $runId = DB::transaction(function () use ($period, $profile, $s, $rows, $byStage, $systemByStage, $notes, $userId) {
            $now = now();
            $id = DB::table('sp_basis_runs')->insertGetId([
                'reporting_period' => $period, 'profile_id' => $profile->id, 'profile_name' => $profile->name,
                'settings' => json_encode(array_intersect_key($s, array_flip(['staging', 'grades', 'macro', 'recovery']))),
                'loans' => count($rows), 'ead' => round(array_sum(array_column($rows, 'ead')), 2), 'ecl' => round(array_sum(array_column($rows, 'ecl')), 2),
                'system_ecl' => round(array_sum(array_map(fn ($r) => (float) $r['system_ecl'], $rows)), 2),
                'by_stage' => json_encode(['sp_basis' => $byStage, 'system' => $systemByStage]), 'notes' => json_encode($notes),
                'run_by' => $userId, 'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('sp_basis_results')->insert(array_map(fn ($r) => $r + ['run_id' => $id, 'created_at' => $now, 'updated_at' => $now], $chunk));
            }

            return $id;
        });
        AuditLoggerService::log('S&P Basis Run', 'sp_basis_runs', $runId, ['new_values' => ['period' => $period, 'loans' => count($rows), 'ecl' => round(array_sum(array_column($rows, 'ecl')), 2)], 'meta' => ['user' => $userId]]);

        return ['run_id' => $runId, 'loans' => count($rows), 'ecl' => round(array_sum(array_column($rows, 'ecl')), 2),
            'system_ecl' => round(array_sum(array_map(fn ($r) => (float) $r['system_ecl'], $rows)), 2), 'by_stage' => $byStage, 'notes' => $notes];
    }
}
