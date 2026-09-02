<?php

namespace App\Services\Eir;

use Illuminate\Support\Facades\DB;

/**
 * Portfolio-wide EIR coverage and the blockers standing behind the gap.
 *
 * `EirReadinessService` answers "can this one contract be solved". This
 * answers "how much of the book can be solved, and what is stopping the
 * rest" — which is a different question with a different audience. A blocker
 * affecting 3,000 dormant facilities and one affecting 20 facilities carrying
 * half the book's exposure look identical in a contract count, so every
 * blocker here is weighted by carrying amount as well as counted. That
 * ordering is what turns a data request into a prioritised one.
 *
 * The checks mirror `EirReadinessService` issue code for issue code, but are
 * driven from four bulk queries rather than three queries per contract:
 * assessing a 3,600-contract book one row at a time is roughly eleven
 * thousand round trips. `EirCoverageServiceTest` asserts the two agree
 * contract by contract, so the duplication cannot drift silently.
 *
 * One deliberate difference: `EirReadinessService` reports `EIR_LOCKED` as an
 * issue, because a locked contract cannot be re-solved. For coverage a locked
 * contract is the goal state, not a problem, so lock is read as an outcome
 * here rather than a blocker.
 */
class EirCoverageService
{
    /** Outcome per contract, in reporting order. */
    public const STATE_LOCKED = 'LOCKED';
    public const STATE_CALCULATED = 'CALCULATED';
    public const STATE_READY = 'READY';
    public const STATE_BLOCKED = 'BLOCKED';
    public const STATE_OUT_OF_SCOPE = 'OUT_OF_SCOPE';

    /** Human wording for each blocker, kept in step with EirReadinessService. */
    public const ISSUE_LABELS = [
        'CONTRACT_PROFILE_MISSING' => 'No EIR contract profile exists',
        'ORIGINATION_DATE_MISSING' => 'Origination/value date is missing',
        'DRAWN_AMOUNT_MISSING' => 'Drawn amount is missing or zero',
        'FREQUENCY_INVALID' => 'Payment frequency is not a supported value',
        'FREQUENCY_ASSUMED' => 'Payment frequency was assumed, not stated by a source',
        'SCHEDULE_NOT_APPROVED' => 'Original schedule is a draft awaiting review and approval',
        'ORIGINAL_SCHEDULE_MISSING' => 'Original contractual schedule (version 1) is missing',
        'SCHEDULE_INVALID' => 'Schedule has a missing date or negative cash flow',
        'SCHEDULE_DATE_INVALID' => 'A scheduled cash flow falls on or before origination',
        'SCHEDULE_DATE_DUPLICATE' => 'Schedule contains duplicate due dates',
        'PRINCIPAL_NOT_RECONCILED' => 'Scheduled principal does not reconcile to drawn within 1%',
        'FEE_CLASSIFICATION_PENDING' => 'Fee/cost lines are not independently reviewed',
        'FEE_DIRECTION_MISSING' => 'A reviewed integral fee has no cash-flow direction',
        'INITIAL_NET_INVALID' => 'Fee-adjusted initial net investment is not positive',
    ];

    /**
     * @return array{period:?string,summary:array,states:array,issues:list<array>,portfolios:list<array>,contracts:list<array>}
     */
    public function profile(?string $period = null, ?string $portfolio = null): array
    {
        $period ??= $this->latestPeriod();
        $exposure = $this->exposureByContract($period);
        $contracts = DB::table('contract_eir')->get()->keyBy('contract_id');

        // The book is whatever the tape carries, plus any contract that has an
        // EIR profile but has fallen off the tape — those must stay visible
        // rather than disappear from their own coverage report.
        $population = array_values(array_unique(array_merge(
            array_keys($exposure), $contracts->keys()->all()
        )));

        $schedules = $this->scheduleAggregates();
        $fees = $this->feeAggregates();

        $rows = [];
        foreach ($population as $contractId) {
            $contract = $contracts->get($contractId);
            if ($portfolio !== null && $portfolio !== '' && (string) ($contract->portfolio ?? '') !== $portfolio) {
                continue;
            }
            $rows[] = $this->assess(
                (string) $contractId, $contract,
                $schedules[$contractId] ?? null, $fees[$contractId] ?? null,
                (float) ($exposure[$contractId] ?? 0.0), isset($exposure[$contractId])
            );
        }

        return [
            'period' => $period,
            'summary' => $this->summary($rows),
            'states' => $this->byState($rows),
            'issues' => $this->byIssue($rows),
            'portfolios' => $this->byPortfolio($rows),
            'contracts' => $rows,
        ];
    }

    /** Mirrors EirReadinessService::assess, minus the lock check. */
    private function assess(string $contractId, ?object $contract, ?object $schedule, ?object $fee, float $exposure, bool $onTape): array
    {
        $issues = [];

        if (! $contract) {
            return $this->row($contractId, null, self::STATE_BLOCKED, ['CONTRACT_PROFILE_MISSING'], $exposure, $onTape);
        }

        if (($contract->instrument_type ?? null) === 'EQUITY_EXCLUDED') {
            return $this->row($contractId, $contract, self::STATE_OUT_OF_SCOPE, [], $exposure, $onTape);
        }

        if (! $contract->origination_date) $issues[] = 'ORIGINATION_DATE_MISSING';
        if ((float) $contract->drawn_amount <= 0) $issues[] = 'DRAWN_AMOUNT_MISSING';
        if (! in_array((int) $contract->payments_per_year, [1, 2, 4, 6, 12], true)) $issues[] = 'FREQUENCY_INVALID';
        if (($contract->frequency_source ?? null) !== 'STATED') $issues[] = 'FREQUENCY_ASSUMED';
        if (($contract->schedule_approval_status ?? null) !== 'APPROVED') $issues[] = 'SCHEDULE_NOT_APPROVED';

        $rowCount = (int) ($schedule->row_count ?? 0);
        if ($rowCount === 0) {
            $issues[] = 'ORIGINAL_SCHEDULE_MISSING';
        } else {
            if ((int) ($schedule->invalid_rows ?? 0) > 0) $issues[] = 'SCHEDULE_INVALID';
            if ($contract->origination_date && ($schedule->earliest_due ?? null) !== null
                && $schedule->earliest_due <= substr((string) $contract->origination_date, 0, 10)) {
                $issues[] = 'SCHEDULE_DATE_INVALID';
            }
            if ((int) ($schedule->distinct_dates ?? 0) < $rowCount) $issues[] = 'SCHEDULE_DATE_DUPLICATE';

            $drawn = (float) $contract->drawn_amount;
            $principal = (float) ($schedule->principal_due ?? 0);
            if ($drawn > 0 && abs($principal - $drawn) > max(1.0, $drawn * 0.01)) $issues[] = 'PRINCIPAL_NOT_RECONCILED';
        }

        if ((int) ($fee->unresolved ?? 0) > 0) $issues[] = 'FEE_CLASSIFICATION_PENDING';
        if ((int) ($fee->integral_without_direction ?? 0) > 0) $issues[] = 'FEE_DIRECTION_MISSING';

        $initialNet = (float) $contract->drawn_amount - (float) ($fee->integral_received ?? 0) + (float) ($fee->integral_paid ?? 0);
        if ((float) $contract->drawn_amount > 0 && $initialNet <= 0) $issues[] = 'INITIAL_NET_INVALID';

        // Lock and calculation are outcomes, not blockers: a locked contract
        // is the goal state even though readiness refuses to re-solve it.
        if ($contract->locked_at !== null) return $this->row($contractId, $contract, self::STATE_LOCKED, [], $exposure, $onTape);
        if ($issues !== []) return $this->row($contractId, $contract, self::STATE_BLOCKED, $issues, $exposure, $onTape);
        if (($contract->calculation_status ?? null) === 'CALCULATED') {
            return $this->row($contractId, $contract, self::STATE_CALCULATED, [], $exposure, $onTape);
        }

        return $this->row($contractId, $contract, self::STATE_READY, [], $exposure, $onTape);
    }

    private function row(string $contractId, ?object $contract, string $state, array $issues, float $exposure, bool $onTape): array
    {
        return [
            'contract_id' => $contractId,
            'portfolio' => $contract->portfolio ?? null,
            'product_type' => $contract->product_type ?? null,
            'state' => $state,
            'issues' => $issues,
            'exposure' => round($exposure, 2),
            'on_tape' => $onTape,
            'drawn_amount' => $contract ? (float) $contract->drawn_amount : null,
        ];
    }

    private function summary(array $rows): array
    {
        $inScope = array_filter($rows, fn ($r) => $r['state'] !== self::STATE_OUT_OF_SCOPE);
        $covered = array_filter($inScope, fn ($r) => $r['state'] === self::STATE_LOCKED);
        $exposureIn = array_sum(array_column($inScope, 'exposure'));
        $exposureCovered = array_sum(array_column($covered, 'exposure'));

        return [
            'contracts' => count($rows),
            'in_scope' => count($inScope),
            'covered' => count($covered),
            'coverage_percent' => count($inScope) > 0 ? round(count($covered) / count($inScope) * 100, 2) : 0.0,
            'exposure_in_scope' => round($exposureIn, 2),
            'exposure_covered' => round($exposureCovered, 2),
            // The number that decides whether the gap is material, as opposed
            // to merely large: a long tail of dormant facilities can dominate
            // the contract count and carry almost none of the book.
            'exposure_coverage_percent' => $exposureIn > 0 ? round($exposureCovered / $exposureIn * 100, 2) : 0.0,
            'off_tape' => count(array_filter($rows, fn ($r) => ! $r['on_tape'])),
        ];
    }

    private function byState(array $rows): array
    {
        $states = [];
        foreach ([self::STATE_LOCKED, self::STATE_CALCULATED, self::STATE_READY, self::STATE_BLOCKED, self::STATE_OUT_OF_SCOPE] as $state) {
            $matching = array_filter($rows, fn ($r) => $r['state'] === $state);
            $states[$state] = [
                'contracts' => count($matching),
                'exposure' => round((float) array_sum(array_column($matching, 'exposure')), 2),
            ];
        }

        return $states;
    }

    /** Blockers ranked by exposure — the order a data request should follow. */
    private function byIssue(array $rows): array
    {
        $totalExposure = array_sum(array_column(
            array_filter($rows, fn ($r) => $r['state'] !== self::STATE_OUT_OF_SCOPE), 'exposure'
        ));

        $issues = [];
        foreach ($rows as $row) {
            foreach ($row['issues'] as $code) {
                $issues[$code] ??= ['code' => $code, 'label' => self::ISSUE_LABELS[$code] ?? $code, 'contracts' => 0, 'exposure' => 0.0, 'sole_blocker' => 0];
                $issues[$code]['contracts']++;
                $issues[$code]['exposure'] += $row['exposure'];
                // Contracts this blocker alone is holding: clearing it makes
                // them solvable, which is not true of the others.
                if (count($row['issues']) === 1) $issues[$code]['sole_blocker']++;
            }
        }

        $issues = array_values(array_map(function ($issue) use ($totalExposure) {
            $issue['exposure'] = round($issue['exposure'], 2);
            $issue['exposure_percent'] = $totalExposure > 0 ? round($issue['exposure'] / $totalExposure * 100, 2) : 0.0;

            return $issue;
        }, $issues));

        usort($issues, fn ($a, $b) => $b['exposure'] <=> $a['exposure'] ?: $b['contracts'] <=> $a['contracts']);

        return $issues;
    }

    private function byPortfolio(array $rows): array
    {
        $portfolios = [];
        foreach ($rows as $row) {
            $key = $row['portfolio'] ?? 'Unassigned';
            $portfolios[$key] ??= ['portfolio' => $key, 'contracts' => 0, 'covered' => 0, 'exposure' => 0.0, 'exposure_covered' => 0.0];
            $portfolios[$key]['contracts']++;
            $portfolios[$key]['exposure'] += $row['exposure'];
            if ($row['state'] === self::STATE_LOCKED) {
                $portfolios[$key]['covered']++;
                $portfolios[$key]['exposure_covered'] += $row['exposure'];
            }
        }

        $portfolios = array_values(array_map(function ($p) {
            $p['exposure'] = round($p['exposure'], 2);
            $p['exposure_covered'] = round($p['exposure_covered'], 2);
            $p['coverage_percent'] = $p['contracts'] > 0 ? round($p['covered'] / $p['contracts'] * 100, 2) : 0.0;

            return $p;
        }, $portfolios));

        usort($portfolios, fn ($a, $b) => $b['exposure'] <=> $a['exposure']);

        return $portfolios;
    }

    private function scheduleAggregates(): array
    {
        return DB::table('contract_cashflow_schedule')->where('schedule_version', 1)
            ->selectRaw('contract_id, COUNT(*) as row_count, COUNT(DISTINCT due_date) as distinct_dates')
            ->selectRaw('MIN(due_date) as earliest_due, SUM(principal_due) as principal_due')
            ->selectRaw('SUM(CASE WHEN due_date IS NULL OR (principal_due + interest_due + fee_due) < 0 THEN 1 ELSE 0 END) as invalid_rows')
            ->groupBy('contract_id')->get()->keyBy('contract_id')->all();
    }

    private function feeAggregates(): array
    {
        return DB::table('contract_fees')
            ->selectRaw("contract_id")
            ->selectRaw("SUM(CASE WHEN classification_status NOT IN ('REVIEWED','REJECTED') THEN 1 ELSE 0 END) as unresolved")
            ->selectRaw("SUM(CASE WHEN classification_status = 'REVIEWED' AND integral = 1 AND (cashflow_direction IS NULL OR cashflow_direction NOT IN ('RECEIVED','PAID')) THEN 1 ELSE 0 END) as integral_without_direction")
            ->selectRaw("SUM(CASE WHEN classification_status = 'REVIEWED' AND integral = 1 AND cashflow_direction = 'RECEIVED' THEN amount ELSE 0 END) as integral_received")
            ->selectRaw("SUM(CASE WHEN classification_status = 'REVIEWED' AND integral = 1 AND cashflow_direction = 'PAID' THEN amount ELSE 0 END) as integral_paid")
            ->groupBy('contract_id')->get()->keyBy('contract_id')->all();
    }

    /** @return array<string,float> carrying amount by contract for the period */
    private function exposureByContract(?string $period): array
    {
        if ($period === null) return [];

        return DB::table('loan_books')
            ->whereRaw("SUBSTR(reporting_period, 1, 7) = ?", [$period])
            ->selectRaw('contract_id, SUM(COALESCE(carrying_amount, 0)) as exposure')
            ->groupBy('contract_id')->pluck('exposure', 'contract_id')
            ->map(fn ($v) => (float) $v)->all();
    }

    public function availablePeriods(): array
    {
        return DB::table('loan_books')->selectRaw('DISTINCT SUBSTR(reporting_period, 1, 7) as period')
            ->orderByDesc('period')->pluck('period')->filter()->values()->all();
    }

    private function latestPeriod(): ?string
    {
        return $this->availablePeriods()[0] ?? null;
    }
}
