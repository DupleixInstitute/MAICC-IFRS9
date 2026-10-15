<?php

namespace App\Services\Eir;

use App\Models\ContractEir;
use App\Services\MegaFarm\MegaFarmEclService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Portfolio-wide EIR coverage and the blockers standing behind the gap.
 *
 * `EirReadinessService` answers "can this one contract be solved". This
 * answers "how much of the book can be solved, and what is stopping the
 * rest" - which is a different question with a different audience. A blocker
 * affecting 3,000 dormant facilities and one affecting 20 facilities carrying
 * half the book's exposure look identical in a contract count, so every
 * blocker here is weighted by carrying amount as well as counted. That
 * ordering is what turns a data request into a prioritised one.
 *
 * The checks are not duplicated here: each contract with a profile is put
 * through `EirReadinessService::assessContract`, the gate the solver itself
 * applies, with its version 1 schedule, fee lines and take-on row read for
 * the whole book in one query each and handed over. The two therefore apply
 * the same rules, the take-on checks (TAKEON_*) and the tranche checks
 * (SCHEDULE_BEFORE_FIRST_DRAWDOWN, SCHEDULE_NOT_ON_TRANCHES) and the
 * principal compared with the balance the schedule amortises included.
 * `EirCoverageServiceTest` asserts they agree contract by contract and that
 * every code the gate can raise has a label here (documentation audit of
 * 14 October 2026: the earlier copy of the rules had drifted).
 *
 * Two readings differ by design. `EIR_LOCKED` is an issue to the gate, because
 * a locked contract cannot be re-solved; to coverage a locked contract is the
 * goal state, so lock is read as an outcome rather than a blocker.
 * `EQUITY_EXCLUDED` is read as out of scope, with the Mega Farm programme
 * (D30), not as a blocker: no data fix brings either into the EIR.
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
        'FEE_HELD_FOR_LOAN' => 'A held GL fee posting names the loan as its likely owner',
        'FEE_DIRECTION_MISSING' => 'A reviewed integral fee has no cash-flow direction',
        'INITIAL_NET_INVALID' => 'Fee-adjusted initial net investment is not positive',
        'TAKEON_NOT_RECOMPUTED' => 'Take-on loan is on its take-on balance, not recomputed from origination',
        'TAKEON_SOLVE_REFUSED' => 'Take-on EIR solve refused by the take-on build check',
        'SCHEDULE_BEFORE_FIRST_DRAWDOWN' => 'Schedule has instalments due on or before the first money advanced',
        'SCHEDULE_NOT_ON_TRANCHES' => 'Generated schedule does not follow the tranche register',
    ];

    /** Codes the readiness gate raises that coverage reads as an outcome or a scope, never as a blocker. */
    public const NOT_BLOCKERS = ['EIR_LOCKED', 'EQUITY_EXCLUDED'];

    public function __construct(private ?EirReadinessService $readiness = null)
    {
        $this->readiness ??= new EirReadinessService();
    }

    /**
     * Why an out-of-scope contract is out of scope. These are not blockers:
     * no data fix brings them into the EIR, so they are counted apart and
     * left out of the in-scope population.
     */
    public const SCOPE_REASONS = [
        'MEGA_FARM' => "Mega Farm programme, out of the EIR's scope (D30)",
        'EQUITY_EXCLUDED' => 'Equity instrument, outside the EIR engine',
    ];

    /**
     * @return array{period:?string,summary:array,states:array,issues:list<array>,out_of_scope:list<array>,portfolios:list<array>,contracts:list<array>}
     */
    public function profile(?string $period = null, ?string $portfolio = null): array
    {
        $period ??= $this->latestPeriod();
        $exposure = $this->exposureByContract($period);
        $contracts = ContractEir::query()->get()->keyBy('contract_id');

        // The book is whatever the tape carries, plus any contract that has an
        // EIR profile but has fallen off the tape — those must stay visible
        // rather than disappear from their own coverage report.
        $population = array_values(array_unique(array_merge(
            array_keys($exposure), $contracts->keys()->all()
        )));

        $schedules = $this->schedules();
        $fees = $this->fees();
        $takeon = $this->takeonRows();
        $megaFarm = $this->megaFarmContracts($period);

        $rows = [];
        foreach ($population as $contractId) {
            $contract = $contracts->get($contractId);
            if ($portfolio !== null && $portfolio !== '' && (string) ($contract->portfolio ?? '') !== $portfolio) {
                continue;
            }
            $onTape = isset($exposure[$contractId]);
            // The Mega Farm programme is Government money MAIIC runs on its
            // behalf: out of the EIR engine and provided in the ECL module
            // (spec v4 section 16, D30). Its loans carry no EIR profile by
            // design, so they are out of scope, not "profile missing".
            if (isset($megaFarm[$contractId])) {
                $rows[] = $this->row((string) $contractId, $contract, self::STATE_OUT_OF_SCOPE, [], (float) ($exposure[$contractId] ?? 0.0), $onTape, 'MEGA_FARM');
                continue;
            }
            $rows[] = $this->assess(
                (string) $contractId, $contract,
                $schedules->get($contractId, collect()), $fees->get($contractId, collect()), $takeon->get($contractId) ?? false,
                (float) ($exposure[$contractId] ?? 0.0), $onTape
            );
        }

        return [
            'period' => $period,
            'summary' => $this->summary($rows),
            'states' => $this->byState($rows),
            'issues' => $this->byIssue($rows),
            'out_of_scope' => $this->byScopeReason($rows),
            'portfolios' => $this->byPortfolio($rows),
            'contracts' => $rows,
        ];
    }

    /** The readiness gate's own checks, read as coverage: lock is the goal state, equity out of scope. */
    private function assess(string $contractId, ?ContractEir $contract, Collection $schedule, Collection $fees, object|false $takeonRow, float $exposure, bool $onTape): array
    {
        if (! $contract) {
            return $this->row($contractId, null, self::STATE_BLOCKED, ['CONTRACT_PROFILE_MISSING'], $exposure, $onTape);
        }
        if (! $contract->isInEirScope()) {
            return $this->row($contractId, $contract, self::STATE_OUT_OF_SCOPE, [], $exposure, $onTape, 'EQUITY_EXCLUDED');
        }

        $issues = collect($this->readiness->assessContract($contract, $schedule, $fees, $takeonRow)['issues'])
            ->pluck('code')->reject(fn ($code) => in_array($code, self::NOT_BLOCKERS, true))->values()->all();

        // Lock and calculation are outcomes, not blockers: a locked contract
        // is the goal state even though readiness refuses to re-solve it.
        if ($contract->locked_at !== null) return $this->row($contractId, $contract, self::STATE_LOCKED, [], $exposure, $onTape);
        if ($issues !== []) return $this->row($contractId, $contract, self::STATE_BLOCKED, $issues, $exposure, $onTape);
        if (($contract->calculation_status ?? null) === 'CALCULATED') {
            return $this->row($contractId, $contract, self::STATE_CALCULATED, [], $exposure, $onTape);
        }

        return $this->row($contractId, $contract, self::STATE_READY, [], $exposure, $onTape);
    }

    private function row(string $contractId, ?object $contract, string $state, array $issues, float $exposure, bool $onTape, ?string $scopeReason = null): array
    {
        return [
            'contract_id' => $contractId,
            'portfolio' => $contract->portfolio ?? null,
            'product_type' => $contract->product_type ?? null,
            'state' => $state,
            'issues' => $issues,
            'scope_reason' => $scopeReason,
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
            'out_of_scope' => count($rows) - count($inScope),
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

    /** Out-of-scope contracts by reason: shown apart from the blockers, never among them. */
    private function byScopeReason(array $rows): array
    {
        $reasons = [];
        foreach ($rows as $row) {
            if ($row['state'] !== self::STATE_OUT_OF_SCOPE) {
                continue;
            }
            $code = (string) $row['scope_reason'];
            $reasons[$code] ??= ['code' => $code, 'label' => self::SCOPE_REASONS[$code] ?? $code, 'contracts' => 0, 'exposure' => 0.0];
            $reasons[$code]['contracts']++;
            $reasons[$code]['exposure'] += $row['exposure'];
        }

        $reasons = array_values(array_map(function ($r) {
            $r['exposure'] = round($r['exposure'], 2);

            return $r;
        }, $reasons));
        usort($reasons, fn ($a, $b) => $b['exposure'] <=> $a['exposure'] ?: $b['contracts'] <=> $a['contracts']);

        return $reasons;
    }

    /** Coverage of the in-scope book: an out-of-scope contract would read as uncovered. */
    private function byPortfolio(array $rows): array
    {
        $portfolios = [];
        foreach ($rows as $row) {
            if ($row['state'] === self::STATE_OUT_OF_SCOPE) {
                continue;
            }
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

    /** Every version 1 schedule, by contract, in due-date order: the rows the gate reads one contract at a time. */
    private function schedules(): Collection
    {
        return DB::table('contract_cashflow_schedule')->where('schedule_version', 1)
            ->orderBy('contract_id')->orderBy('due_date')->get()->groupBy('contract_id');
    }

    /** Every fee line that counts (not superseded by a higher source), by contract. */
    private function fees(): Collection
    {
        return EirReadinessService::feeQuery()->get()->groupBy('contract_id');
    }

    /** The take-on rows, by contract; none where the take-on tables are not installed. */
    private function takeonRows(): Collection
    {
        if (! Schema::hasTable('contract_takeon')) {
            return collect();
        }

        return DB::table('contract_takeon')->get()->keyBy('contract_id');
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

    /**
     * The period's Mega Farm programme loans, identified as the Mega Farm
     * module identifies them (MegaFarmEclService::loans: a programme GL
     * 1050301-1050307 or a Mega Farm product group), on the same period match
     * as the exposure above. A tape without those columns has no way to tell
     * a programme loan, so none is assumed.
     *
     * @return array<string,true>
     */
    private function megaFarmContracts(?string $period): array
    {
        if ($period === null || ! Schema::hasColumn('loan_books', 'product_code') || ! Schema::hasColumn('loan_books', 'product_group')) {
            return [];
        }

        return app(MegaFarmEclService::class)->loans(null)
            ->whereRaw('SUBSTR(reporting_period, 1, 7) = ?', [$period])
            ->distinct()->pluck('contract_id')
            ->mapWithKeys(fn ($id) => [(string) $id => true])->all();
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
