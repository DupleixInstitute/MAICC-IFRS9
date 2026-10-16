<?php

namespace App\Services\Pd;

use App\Services\Eir\GovernanceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each loan's starting risk (PD at Origination Specification v1).
 *
 * capture() writes, for every loan of a month that has a PD and no record
 * yet, one record that is never changed afterwards (decision 1), labelled by
 * how good a starting reading it is. The first capture on a database works
 * through every month already held, oldest first, so every loan's record is
 * its earliest PD. A loan with no PD in any month is marked UNAVAILABLE; that
 * mark alone is replaced if a PD comes later.
 *
 * shadow() is the PD-change test of setting 9, run in shadow (decision 5):
 * it reports which Stage 1 loans would move to Stage 2 under the governed
 * thresholds (decision 4), comparing the 12-month PD after the forward-looking
 * adjustment (decision 2) with the starting reading, and changes nothing.
 */
class OriginationPdService
{
    public const AT_ORIGINATION = 'AT_ORIGINATION';

    public const AFTER_ORIGINATION = 'AFTER_ORIGINATION';

    public const BEFORE_HISTORY = 'BEFORE_HISTORY';

    public const UNAVAILABLE = 'UNAVAILABLE';

    public const LABELS = [
        self::AT_ORIGINATION => 'At origination',
        self::AFTER_ORIGINATION => 'Shortly after origination',
        self::BEFORE_HISTORY => 'Before the PD history',
        self::UNAVAILABLE => 'Unavailable',
    ];

    public const RATIO_SETTING = 'sicr_pd_ratio_threshold';

    public const POINTS_SETTING = 'sicr_pd_points_threshold';

    public static function available(): bool
    {
        return Schema::hasTable('loan_origination_pds') && Schema::hasTable('loan_books');
    }

    /** The first month any loan has a PD: where the PD history begins (read from the data, never a fixed date). */
    public function historyStart(): ?string
    {
        return DB::table('loan_books')->where(fn ($q) => $q->whereNotNull('pd_post_fli')->orWhereNotNull('pd_prefli'))->min('reporting_period');
    }

    /**
     * Record the starting reading of every loan of $period that has a PD and no record yet.
     *
     * @return array{captured:int, by_basis:array<string,int>, unavailable:int, backfilled:bool}
     */
    public function capture(string $period, ?int $runId = null): array
    {
        if (! self::available()) {
            return ['captured' => 0, 'by_basis' => [], 'unavailable' => 0, 'backfilled' => false];
        }
        // the first capture on a database fills every month already held, oldest first, so a record is the earliest PD
        if (DB::table('loan_origination_pds')->whereNot('basis', self::UNAVAILABLE)->doesntExist() && $this->monthsBefore($period) !== []) {
            $r = $this->backfill($period);

            return $r + ['backfilled' => true];
        }

        return $this->captureMonth($period, $runId) + ['unavailable' => $this->markUnavailable(), 'backfilled' => false];
    }

    /** @return array{captured:int, by_basis:array<string,int>, unavailable:int} every month up to $through (all, if null), oldest first */
    public function backfill(?string $through = null): array
    {
        $captured = 0;
        $by = [];
        $months = DB::table('loan_books')->select('reporting_period')->distinct()->orderBy('reporting_period')->pluck('reporting_period')
            ->filter(fn ($m) => $through === null || $m <= $through);
        foreach ($months as $m) {
            $r = $this->captureMonth((string) $m, null);
            $captured += $r['captured'];
            foreach ($r['by_basis'] as $b => $n) {
                $by[$b] = ($by[$b] ?? 0) + $n;
            }
        }

        return ['captured' => $captured, 'by_basis' => $by, 'unavailable' => $this->markUnavailable()];
    }

    /** @return list<string> */
    private function monthsBefore(string $period): array
    {
        return DB::table('loan_books')->where('reporting_period', '<', $period)->select('reporting_period')->distinct()->pluck('reporting_period')->all();
    }

    /** @return array{captured:int, by_basis:array<string,int>} */
    private function captureMonth(string $period, ?int $runId): array
    {
        $start = $this->historyStart();
        $recorded = DB::table('loan_origination_pds')->whereNot('basis', self::UNAVAILABLE)->pluck('contract_id')->flip()->all();
        $origination = Schema::hasTable('contract_eir') ? DB::table('contract_eir')->pluck('origination_date', 'contract_id')->all() : [];
        $cols = array_values(array_filter(['contract_id', 'pd_post_fli', 'pd_prefli', 'lifetime_pd', 'ifrs9_stage', 'pd_applied_segment_key', 'pd_segment_key', 'loan_portfolio_id', 'pd_segment_run_id', 'run_record_id'],
            fn ($c) => Schema::hasColumn('loan_books', $c)));
        $rows = DB::table('loan_books')->where('reporting_period', $period)->where(fn ($q) => $q->whereNotNull('pd_post_fli')->orWhereNotNull('pd_prefli'))->orderBy('contract_id')->get($cols);
        $by = [];
        $n = 0;
        foreach ($rows as $r) {
            $contract = (string) $r->contract_id;
            if (isset($recorded[$contract])) {
                continue; // written once, never changed (decision 1)
            }
            $orig = $origination[$contract] ?? null;
            $origMonth = $orig ? substr((string) $orig, 0, 7) : null;
            $basis = match (true) {
                $origMonth === $period => self::AT_ORIGINATION,
                $origMonth !== null && $start !== null && $origMonth >= $start => self::AFTER_ORIGINATION,
                default => self::BEFORE_HISTORY,
            };
            $after = $origMonth !== null && $origMonth <= $period
                ? (int) CarbonImmutable::parse($origMonth . '-01')->diffInMonths(CarbonImmutable::parse($period . '-01')) : null;
            $record = [
                'origination_date' => $orig ? substr((string) $orig, 0, 10) : null, 'reporting_period' => $period, 'months_after_origination' => $after, 'basis' => $basis,
                'pd_12m_post_fli' => $r->pd_post_fli ?? null, 'pd_12m_pre_fli' => $r->pd_prefli ?? null, 'pd_lifetime' => $r->lifetime_pd ?? null,
                'stage' => isset($r->ifrs9_stage) ? (int) $r->ifrs9_stage : null, 'segment_key' => $r->pd_applied_segment_key ?? $r->pd_segment_key ?? null,
                'loan_portfolio_id' => $r->loan_portfolio_id ?? null, 'pd_segment_run_id' => $r->pd_segment_run_id ?? null,
                'run_record_id' => $runId ?? ($r->run_record_id ?? null), 'captured_at' => now(), 'updated_at' => now(),
            ];
            // an UNAVAILABLE mark is the only record a real reading replaces
            $updated = DB::table('loan_origination_pds')->where('contract_id', $contract)->where('basis', self::UNAVAILABLE)->update($record);
            if ($updated === 0) {
                DB::table('loan_origination_pds')->insertOrIgnore($record + ['contract_id' => $contract, 'created_at' => now()]);
            }
            $recorded[$contract] = true;
            $by[$basis] = ($by[$basis] ?? 0) + 1;
            $n++;
        }

        return ['captured' => $n, 'by_basis' => $by];
    }

    /** A loan in the loan book with no PD in any month: marked UNAVAILABLE, so it is never silently missing. */
    private function markUnavailable(): int
    {
        $withPd = DB::table('loan_books')->where(fn ($q) => $q->whereNotNull('pd_post_fli')->orWhereNotNull('pd_prefli'))->select('contract_id')->distinct();
        $missing = DB::table('loan_books')->select('contract_id')->distinct()
            ->whereNotIn('contract_id', $withPd)
            ->whereNotIn('contract_id', DB::table('loan_origination_pds')->select('contract_id'))->pluck('contract_id');
        $origination = Schema::hasTable('contract_eir') ? DB::table('contract_eir')->whereIn('contract_id', $missing)->pluck('origination_date', 'contract_id')->all() : [];
        foreach ($missing->chunk(500) as $chunk) {
            DB::table('loan_origination_pds')->insertOrIgnore($chunk->map(fn ($c) => ['contract_id' => (string) $c, 'basis' => self::UNAVAILABLE,
                'origination_date' => isset($origination[$c]) ? substr((string) $origination[$c], 0, 10) : null, 'captured_at' => now(), 'created_at' => now(), 'updated_at' => now()])->values()->all());
        }

        return $missing->count();
    }

    /** The two governed thresholds in force at the month-end (decision 4): the ratio, and the points as a fraction. */
    public function thresholds(string $period): array
    {
        $gov = app(GovernanceService::class);
        $end = CarbonImmutable::parse($period . '-01')->endOfMonth();

        return ['ratio' => (float) $gov->get(self::RATIO_SETTING, $end), 'points' => (float) $gov->get(self::POINTS_SETTING, $end) / 100,
            'ratio_label' => $gov->get(self::RATIO_SETTING, $end), 'points_label' => $gov->get(self::POINTS_SETTING, $end)];
    }

    /**
     * The PD-change test in shadow (decision 5): every Stage 1 loan of $period with a usable starting reading,
     * its starting and current 12-month PD after the forward-looking adjustment, the ratio and the increase,
     * and whether it would move to Stage 2; the loans without a usable reading counted by reason. Writes nothing.
     *
     * @return array{period:string, thresholds:array, rows:list<array>, would_move:int, tested:int, excluded:array<string,int>}
     */
    public function shadow(string $period): array
    {
        $t = $this->thresholds($period);
        $records = DB::table('loan_origination_pds')->get()->keyBy('contract_id');
        $rows = [];
        $excluded = [];
        $skip = function (string $why) use (&$excluded) {
            $excluded[$why] = ($excluded[$why] ?? 0) + 1;
        };
        foreach (DB::table('loan_books')->where('reporting_period', $period)->where('ifrs9_stage', 1)->orderBy('contract_id')
            ->get(['contract_id', 'customer_name', 'pd_post_fli', 'pd_prefli', 'carrying_amount']) as $l) {
            $ref = $records->get((string) $l->contract_id);
            if ($ref === null || $ref->basis === self::UNAVAILABLE) {
                $skip('no starting reading');
                continue;
            }
            if ((int) $ref->stage !== 1) {
                $skip('starting reading taken outside Stage 1 (decision 3)');
                continue;
            }
            // like with like: after the forward-looking adjustment where both readings have it, else before it on both
            [$from, $now, $basis] = $ref->pd_12m_post_fli !== null && $l->pd_post_fli !== null
                ? [(float) $ref->pd_12m_post_fli, (float) $l->pd_post_fli, 'after the forward-looking adjustment']
                : [(float) $ref->pd_12m_pre_fli, (float) $l->pd_prefli, 'before the forward-looking adjustment'];
            if ($from <= 0 || ($ref->pd_12m_post_fli === null && $ref->pd_12m_pre_fli === null) || ($l->pd_post_fli === null && $l->pd_prefli === null)) {
                $skip('no comparable PD');
                continue;
            }
            $ratio = $now / $from;
            $points = $now - $from;
            $move = $ratio >= $t['ratio'] && $points >= $t['points'];
            $rows[] = ['contract_id' => (string) $l->contract_id, 'customer' => $l->customer_name, 'carrying_amount' => (float) $l->carrying_amount,
                'starting_month' => $ref->reporting_period, 'starting_basis' => self::LABELS[$ref->basis], 'pd_start' => $from, 'pd_now' => $now,
                'ratio' => round($ratio, 4), 'points' => round($points * 100, 2), 'compared' => $basis, 'would_move' => $move];
        }
        usort($rows, fn ($a, $b) => [$b['would_move'], $b['ratio']] <=> [$a['would_move'], $a['ratio']]);

        return ['period' => $period, 'thresholds' => $t, 'rows' => $rows, 'would_move' => count(array_filter($rows, fn ($r) => $r['would_move'])),
            'tested' => count($rows), 'excluded' => $excluded];
    }
}
