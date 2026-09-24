<?php

namespace App\Services\Eir;

use App\Exceptions\GovernanceSettingMissingException;
use App\Models\ContractEir;
use App\Models\EirAmortisation;
use App\Models\GlInterestPosting;
use App\Support\ReportingPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The monthly interest reconciliation: what the ledger posted, what the
 * contract says should have been posted, and a named cause for every
 * difference (spec v3 sections 7.7 and 7.8, decision D20).
 *
 * The expected figure is the contractual interest of section 7.7, worked out
 * by ContractualInterestService on the prior month-end outstanding balance,
 * the rate in force for the month and the actual days in the month. An earlier
 * version of this service assumed the ledger charged the annual rate divided
 * by twelve on the amount originally drawn. That is not what E-Banker does,
 * and in February it is wrong by between 8 and 9 percent, so most of the
 * difference used to land in "unexplained".
 *
 * Every difference outside the governed band now carries one of these causes,
 * and each one was observed in the reconstruction of MAIIC's own books:
 *
 *   NO_POSTING          the loan book shows the account live with a balance
 *                       and the ledger has no interest posting at all
 *   LATE_DISBURSEMENT   the month the money was paid out, so only part of the
 *                       month is charged, and the two sides count it
 *                       differently
 *   CATCH_UP_POSTING    several months posted at once, as Promenade Medical
 *                       Centre was in February 2026
 *   MID_MONTH_TRANCHE   more money was drawn inside the month, so the month-end
 *                       balance cannot reproduce the charge
 *   RATE_MISMATCH       the ledger's posting implies another rate recorded for
 *                       the same account: the core rate against the offer
 *                       letter or the loan book
 *   DATA_GAP            a named input is missing, so there is no expected
 *                       figure to compare
 *   WITHIN_TOLERANCE    the two agree inside the governed band
 *   UNEXPLAINED         the last resort, reported and never absorbed
 *
 * The variance between the engine's EIR interest and the ledger still resolves
 * into terms that sum to it exactly, so the bridge on the screen balances:
 *
 *   base effect            = contractual interest - GL posted   (cause named)
 *   carrying amount effect = the same convention on the engine's amortised
 *                            cost instead of the loan book's balance
 *   rate effect            = accruing at the EIR instead of the contractual
 *                            rate on the same balance: the integral fee uplift
 *                            and any difference between the two conventions
 *   impairment effect      = Stage 3 accruing on the amount net of the loss
 *                            allowance (IFRS 9 5.4.1(b))
 *
 * Anything the four cannot account for is reported as unexplained rather than
 * absorbed. Postings with no calculated counterpart are never folded into the
 * bridge: an absent row is a coverage gap, not a measurement difference.
 *
 * Both the band and the day count are governed settings read for the month
 * being reconciled, so a later change never restates a month already reported.
 */
class EirGlReconciliationService
{
    public const CAUSE_WITHIN_TOLERANCE = 'WITHIN_TOLERANCE';
    public const CAUSE_LATE_DISBURSEMENT = 'LATE_DISBURSEMENT';
    public const CAUSE_CATCH_UP_POSTING = 'CATCH_UP_POSTING';
    public const CAUSE_MID_MONTH_TRANCHE = 'MID_MONTH_TRANCHE';
    public const CAUSE_RATE_MISMATCH = 'RATE_MISMATCH';
    public const CAUSE_NO_POSTING = 'NO_POSTING';
    public const CAUSE_DATA_GAP = 'DATA_GAP';
    public const CAUSE_UNEXPLAINED = 'UNEXPLAINED';

    /** How far back a catch-up posting is allowed to reach. */
    private const CATCH_UP_LOOK_BACK_MONTHS = 12;

    /** Two rates count as the same when they are this close: one basis point. */
    private const RATE_MATCH_TOLERANCE = 0.0001;

    private readonly GovernanceService $governance;

    private readonly ContractualInterestService $contractual;

    /**
     * The band inside which a row is treated as agreeing: a share of the
     * posted amount with an absolute floor so near-zero postings do not
     * register a false variance. It is the governed setting recon_tolerance
     * in force at the period end, never a number written here.
     *
     * @var array{percent:float, floor:float}|null
     */
    private ?array $tolerance = null;

    /** The governed day count, days and denominator for a full month of the period. */
    private array $monthBasis = ['day_count' => null, 'days' => null, 'denominator' => null];

    /** Months that carry a ledger posting, by contract id, for the catch-up test. */
    private array $postedMonths = [];

    public function __construct(?GovernanceService $governance = null, ?ContractualInterestService $contractual = null)
    {
        $this->governance = $governance ?? app(GovernanceService::class);
        $this->contractual = $contractual ?? new ContractualInterestService($this->governance);
    }

    /** @return list<string> Periods that have GL postings, newest first. */
    public function availablePeriods(): array
    {
        return GlInterestPosting::query()
            ->selectRaw('period_year, period_month')->distinct()
            ->orderByDesc('period_year')->orderByDesc('period_month')->get()
            ->map(fn ($row) => $this->periodKey((int) $row->period_year, (int) $row->period_month))
            ->all();
    }

    /**
     * @return array{period:?string,rows:list<array>,bridge:array,summary:array}
     */
    public function forPeriod(?string $period = null, ?string $portfolio = null): array
    {
        $period ??= $this->availablePeriods()[0] ?? null;
        if ($period === null) {
            return ['period' => null, 'rows' => [], 'bridge' => $this->emptyBridge(), 'summary' => $this->emptySummary()];
        }

        [$year, $month] = array_map('intval', explode('-', $period));
        $this->openPeriod($period);

        $postings = GlInterestPosting::query()->where('period_year', $year)->where('period_month', $month)
            ->orderBy('contract_id')->get();

        // Accounts the loan book shows live in the month with nothing posted
        // against them are part of the reconciliation, not outside it: a live
        // loan with no interest in the ledger is the finding.
        $postedIds = $postings->pluck('contract_id')->map(fn ($id) => (string) $id)->unique()->all();
        $liveIds = $this->liveContractIds($period);
        $contractIds = array_values(array_unique(array_merge($postedIds, $liveIds)));

        $contracts = ContractEir::whereIn('contract_id', $contractIds)->get()->keyBy('contract_id');
        $accruals = EirAmortisation::whereIn('contract_id', $contractIds)
            ->where('reporting_period', $period)->get()->keyBy('contract_id');
        $this->contractual->prime($contractIds);
        $this->loadPostedMonths($contractIds);

        $rows = [];
        foreach ($postings as $posting) {
            $contractId = (string) $posting->contract_id;
            $contract = $contracts->get($contractId);
            if (! $this->inPortfolio($contract, $portfolio)) {
                continue;
            }
            $rows[] = $this->row($contractId, $posting, $contract, $accruals->get($contractId), $period);
        }

        foreach (array_diff($liveIds, $postedIds) as $contractId) {
            $contract = $contracts->get($contractId);
            if ($contract === null || ! $this->inPortfolio($contract, $portfolio)) {
                continue;
            }
            $rows[] = $this->row($contractId, null, $contract, $accruals->get($contractId), $period);
        }

        return ['period' => $period, 'rows' => $rows, 'bridge' => $this->bridge($rows), 'summary' => $this->summary($rows)];
    }

    /**
     * The same rows for a set of postings that may span periods, keyed by
     * posting id. The GL postings list uses this so there is one
     * reconciliation in the system rather than a second one in SQL.
     *
     * @param  iterable<GlInterestPosting>  $postings
     * @return array<int, array>
     */
    public function forPostings(iterable $postings): array
    {
        $byPeriod = [];
        foreach ($postings as $posting) {
            $byPeriod[$this->periodKey((int) $posting->period_year, (int) $posting->period_month)][] = $posting;
        }
        if ($byPeriod === []) {
            return [];
        }

        $contractIds = [];
        foreach ($byPeriod as $group) {
            foreach ($group as $posting) {
                $contractIds[] = (string) $posting->contract_id;
            }
        }
        $contractIds = array_values(array_unique($contractIds));
        $contracts = ContractEir::whereIn('contract_id', $contractIds)->get()->keyBy('contract_id');
        $this->contractual->prime($contractIds);
        $this->loadPostedMonths($contractIds);

        $rows = [];
        foreach ($byPeriod as $period => $group) {
            try {
                $this->openPeriod($period);
            } catch (GovernanceSettingMissingException $e) {
                // A list that spans periods must not fall over because one old
                // month has no approved convention. Those rows say so instead
                // of being judged against a band written here.
                foreach ($group as $posting) {
                    $rows[(int) $posting->id] = $this->ungovernedRow($posting, $period, $e->getMessage());
                }
                continue;
            }
            $accruals = EirAmortisation::whereIn('contract_id', $contractIds)
                ->where('reporting_period', $period)->get()->keyBy('contract_id');
            foreach ($group as $posting) {
                $contractId = (string) $posting->contract_id;
                $rows[(int) $posting->id] = $this->row($contractId, $posting, $contracts->get($contractId),
                    $accruals->get($contractId), $period);
            }
        }

        return $rows;
    }

    /**
     * A posting in a month no convention has been approved for. There is no
     * expected figure and no band, so the row carries the reason and nothing
     * is inferred.
     */
    private function ungovernedRow(GlInterestPosting $posting, string $period, string $message): array
    {
        return [
            'contract_id' => (string) $posting->contract_id,
            'customer_name' => null,
            'reporting_period' => $period,
            'portfolio' => null,
            'gl_account_code' => $posting->gl_account_code,
            'gl_posted' => round((float) $posting->interest_income_posted, 2),
            'has_posting' => true,
            'drawn_amount' => null, 'contractual_rate' => null, 'eir_effective_annual' => null,
            'expected_interest' => null, 'expected_opening_balance' => null, 'expected_opening_source' => null,
            'expected_rate' => null, 'expected_rate_source' => null, 'expected_days' => null,
            'expected_days_in_period' => null, 'day_count' => null, 'first_disbursement_month' => false,
            'capitalising_moratorium_month' => false, 'expected_difference' => null,
            'cause' => self::CAUSE_DATA_GAP, 'cause_detail' => $message,
            'status' => 'NOT_GOVERNED',
            'eir_accrued' => null, 'opening_gross' => null, 'closing_gross' => null, 'interest_basis' => null,
            'variance' => null, 'variance_percent' => null, 'gl_implied_base' => null,
            'base_effect' => null, 'carrying_amount_effect' => null, 'rate_effect' => null,
            'impairment_effect' => null, 'unexplained' => null,
        ];
    }

    /**
     * Totals across every period that has postings, for the EIR Data summary.
     *
     * Read in two queries and matched in PHP, because the reporting period on
     * eir_amortisation is text while the posting carries a year and a month,
     * and a join on a string built in SQL only works on one database driver.
     * A period with no approved tolerance is counted as ungoverned rather than
     * being judged against a band written here.
     *
     * @return array<string, float|int>
     */
    public function overallSummary(): array
    {
        $postings = DB::table('gl_interest_postings')
            ->select('contract_id', 'period_year', 'period_month', 'interest_income_posted')->get();
        $accruals = DB::table('eir_amortisation')
            ->select('contract_id', 'reporting_period', 'interest_accrued')->get();

        $accrued = [];
        foreach ($accruals as $row) {
            $month = ReportingPeriod::normalise($row->reporting_period);
            if ($month !== null) {
                $accrued[$row->contract_id . '|' . $month] = (float) $row->interest_accrued;
            }
        }

        $bands = [];
        $totals = ['posting_rows' => 0, 'calculated_rows' => 0, 'missing_rows' => 0, 'within_tolerance' => 0,
            'variance_rows' => 0, 'ungoverned_rows' => 0, 'gl_total' => 0.0, 'matched_gl_total' => 0.0,
            'missing_gl_total' => 0.0, 'eir_total' => 0.0, 'net_variance' => 0.0];

        foreach ($postings as $posting) {
            $period = $this->periodKey((int) $posting->period_year, (int) $posting->period_month);
            $posted = (float) $posting->interest_income_posted;
            $totals['posting_rows']++;
            $totals['gl_total'] += $posted;

            $key = $posting->contract_id . '|' . $period;
            if (! array_key_exists($key, $accrued)) {
                $totals['missing_rows']++;
                $totals['missing_gl_total'] += $posted;
                continue;
            }

            $interest = $accrued[$key];
            $totals['calculated_rows']++;
            $totals['matched_gl_total'] += $posted;
            $totals['eir_total'] += $interest;
            $totals['net_variance'] += $interest - $posted;

            if (! array_key_exists($period, $bands)) {
                try {
                    $bands[$period] = $this->governance->reconciliationTolerance($this->periodEnd($period));
                } catch (GovernanceSettingMissingException) {
                    $bands[$period] = null;
                }
            }
            $band = $bands[$period];
            if ($band === null) {
                $totals['ungoverned_rows']++;
            } elseif (abs($interest - $posted) <= max($band['floor'], abs($posted) * $band['percent'] / 100)) {
                $totals['within_tolerance']++;
            } else {
                $totals['variance_rows']++;
            }
        }

        foreach (['gl_total', 'matched_gl_total', 'missing_gl_total', 'eir_total', 'net_variance'] as $key) {
            $totals[$key] = round($totals[$key], 2);
        }
        $latest = $this->availablePeriods()[0] ?? null;
        $totals['tolerance_percent'] = $this->latestTolerancePercent($latest, $bands);

        return $totals;
    }

    /**
     * The band to show beside the totals: the one governing the latest period
     * with postings, or the one in force today. A setup with no approved band
     * at all shows none rather than a number written here.
     *
     * @param  array<string, array{percent:float, floor:float}|null>  $bands
     */
    private function latestTolerancePercent(?string $latest, array $bands): ?float
    {
        if ($latest !== null && ($bands[$latest] ?? null) !== null) {
            return $bands[$latest]['percent'];
        }

        try {
            return $this->tolerancePercent();
        } catch (GovernanceSettingMissingException) {
            return null;
        }
    }

    /** Read the governed conventions for one period, once, before its rows are built. */
    private function openPeriod(string $period): void
    {
        $periodEnd = $this->periodEnd($period);
        // The band and the day count that governed this period: a later change
        // to either never restates a month already reconciled under the old one.
        $this->tolerance = $this->governance->reconciliationTolerance($periodEnd);
        $dayCount = $this->contractual->dayCount($periodEnd);
        $this->monthBasis = [
            'day_count' => $dayCount,
            'days' => $dayCount === '30/360' ? 30 : $periodEnd->day,
            'denominator' => $dayCount === '30/360' ? 360 : 365,
        ];
    }

    private function inPortfolio(?ContractEir $contract, ?string $portfolio): bool
    {
        return $portfolio === null || $portfolio === '' || (string) ($contract->portfolio ?? '') === $portfolio;
    }

    /**
     * Accounts the loan book shows with a balance at the end of the month. The
     * period column is a free string, so the shapes it holds are matched
     * directly and then confirmed in PHP.
     *
     * @return list<string>
     */
    private function liveContractIds(string $period): array
    {
        $rows = DB::table('loan_books')
            ->select('contract_id', 'reporting_period', 'carrying_amount', 'principal_balance')
            ->where(function ($q) use ($period) {
                $q->where('reporting_period', 'like', $period . '%')
                    ->orWhere('reporting_period', str_replace('-', '', $period));
            })
            ->get();

        $ids = [];
        foreach ($rows as $row) {
            $live = (float) ($row->carrying_amount ?? 0) > 0 || (float) ($row->principal_balance ?? 0) > 0;
            if ($live && ReportingPeriod::same($row->reporting_period, $period)) {
                $ids[(string) $row->contract_id] = true;
            }
        }

        return array_keys($ids);
    }

    /** @param list<string> $contractIds */
    private function loadPostedMonths(array $contractIds): void
    {
        $rows = DB::table('gl_interest_postings')
            ->select('contract_id', 'period_year', 'period_month', 'interest_income_posted')
            ->whereIn('contract_id', $contractIds)->get();

        $this->postedMonths = [];
        foreach ($rows as $row) {
            $month = $this->periodKey((int) $row->period_year, (int) $row->period_month);
            $contractId = (string) $row->contract_id;
            $this->postedMonths[$contractId][$month] = ($this->postedMonths[$contractId][$month] ?? 0.0)
                + (float) $row->interest_income_posted;
        }
    }

    private function row(string $contractId, ?GlInterestPosting $posting, ?ContractEir $contract, ?EirAmortisation $accrual, string $period): array
    {
        $posted = $posting === null ? 0.0 : (float) $posting->interest_income_posted;
        $expected = $this->contractual->forPeriod($contractId, $period);
        $cause = $this->cause($contractId, $period, $posting !== null, $posted, $expected, $contract);

        $rateUsed = $expected->available ? $expected->rate : $this->asRate($contract?->contractual_rate);
        $daysUsed = $expected->available ? $expected->daysCharged : $this->monthBasis['days'];
        $denominator = $expected->available ? $expected->denominator : $this->monthBasis['denominator'];
        $factor = $rateUsed === null || $daysUsed === null || $denominator === null
            ? null
            : $rateUsed * $daysUsed / $denominator;

        $row = [
            'contract_id' => $contractId,
            'customer_name' => $this->contractual->customerName($contractId),
            'reporting_period' => $period,
            'portfolio' => $contract->portfolio ?? null,
            'gl_account_code' => $posting->gl_account_code ?? null,
            'gl_posted' => round($posted, 2),
            'has_posting' => $posting !== null,
            'drawn_amount' => $contract ? (float) $contract->drawn_amount : null,
            'contractual_rate' => $contract?->contractual_rate !== null ? (float) $contract->contractual_rate : null,
            'eir_effective_annual' => $contract?->eir_effective_annual !== null ? (float) $contract->eir_effective_annual : null,
            // The expected figure of section 7.7, with the basis it stands on.
            'expected_interest' => $expected->interest,
            'expected_opening_balance' => $expected->openingBalance === null ? null : round($expected->openingBalance, 2),
            'expected_opening_source' => $expected->openingBalanceSource,
            'expected_rate' => $expected->rate,
            'expected_rate_source' => $expected->rateSource,
            'expected_days' => $expected->daysCharged,
            'expected_days_in_period' => $expected->daysInPeriod,
            'day_count' => $expected->dayCount ?? $this->monthBasis['day_count'],
            'first_disbursement_month' => $expected->firstDisbursementMonth,
            'capitalising_moratorium_month' => $expected->capitalisingMoratoriumMonth,
            'expected_difference' => $expected->interest === null ? null : round($expected->interest - $posted, 2),
            'cause' => $cause['cause'],
            'cause_detail' => $cause['detail'],
        ];

        if (! $accrual) {
            // No calculated counterpart: a coverage gap, deliberately left out
            // of the bridge so it cannot read as a measurement difference.
            return $row + [
                'status' => $contract === null ? 'NO_CONTRACT' : 'NOT_CALCULATED',
                'eir_accrued' => null, 'opening_gross' => null, 'closing_gross' => null, 'interest_basis' => null,
                'variance' => null, 'variance_percent' => null,
                'gl_implied_base' => $factor === null || $factor <= 0.0 ? null : round($posted / $factor, 2),
                'base_effect' => null, 'carrying_amount_effect' => null, 'rate_effect' => null,
                'impairment_effect' => null, 'unexplained' => null,
            ];
        }

        $accrued = (float) $accrual->interest_accrued;
        $opening = (float) $accrual->opening_gross;
        $variance = $accrued - $posted;

        // The same convention as the expected figure, on the balance the engine
        // amortises rather than the balance the loan book reports.
        $contractualOnEngine = $factor === null ? null : $opening * $factor;
        $baseEffect = $expected->interest === null ? null : $expected->interest - $posted;
        $carryingEffect = $contractualOnEngine === null || $expected->interest === null
            ? null : $contractualOnEngine - $expected->interest;

        $effectiveMonthly = $contract && $contract->eir_effective_annual !== null
            ? pow(1 + (float) $contract->eir_effective_annual, 1 / 12) - 1 : null;
        $rateEffect = $impairmentEffect = null;
        if ($effectiveMonthly !== null && $contractualOnEngine !== null) {
            // The yield uplift from fees integral to the EIR, plus any
            // difference between accruing at the EIR monthly and charging the
            // contractual rate on the governed day count.
            $rateEffect = $effectiveMonthly * $opening - $contractualOnEngine;
            // Stage 3 accrues on the amortised cost net of the loss allowance,
            // so the shortfall against a gross accrual is a real effect and not
            // a residual. It is zero on every GROSS row.
            $impairmentEffect = $accrued - $effectiveMonthly * $opening;
        }
        $unexplained = $variance - ($baseEffect ?? 0.0) - ($carryingEffect ?? 0.0)
            - ($rateEffect ?? 0.0) - ($impairmentEffect ?? 0.0);

        return $row + [
            'status' => $this->withinTolerance($variance, $posted) ? 'WITHIN_TOLERANCE' : 'VARIANCE',
            'eir_accrued' => round($accrued, 2),
            'opening_gross' => round($opening, 2),
            'closing_gross' => round((float) $accrual->closing_gross, 2),
            'interest_basis' => $accrual->interest_basis,
            'variance' => round($variance, 2),
            'variance_percent' => $posted != 0.0 ? round($variance / abs($posted) * 100, 2) : null,
            // The balance the ledger's own posting implies on the governed
            // convention. Whether a ledger amortises is itself worth seeing.
            'gl_implied_base' => $factor === null || $factor <= 0.0 ? null : round($posted / $factor, 2),
            'base_effect' => $baseEffect === null ? null : round($baseEffect, 2),
            'carrying_amount_effect' => $carryingEffect === null ? null : round($carryingEffect, 2),
            'rate_effect' => $rateEffect === null ? null : round($rateEffect, 2),
            'impairment_effect' => $impairmentEffect === null ? null : round($impairmentEffect, 2),
            'unexplained' => round($unexplained, 2),
        ];
    }

    /**
     * Name the cause of the difference between what the ledger posted and what
     * the contract says. The specific causes are tested before the general
     * ones, and UNEXPLAINED is only reached when none of them holds.
     *
     * @return array{cause:string, detail:string}
     */
    private function cause(string $contractId, string $period, bool $hasPosting, float $posted, ContractualInterest $expected, ?ContractEir $contract): array
    {
        if (! $hasPosting) {
            $balance = $this->contractual->outstandingBalance($contractId, $period);
            $detail = 'The ledger has no interest posting for this month, and the loan book shows the account live'
                . ($balance === null ? '' : ' with a balance of ' . $this->money($balance))
                . '.' . ($expected->interest === null ? '' : ' The contract says ' . $this->money($expected->interest)
                    . ' should have been charged.');

            return ['cause' => self::CAUSE_NO_POSTING, 'detail' => $detail];
        }

        if (! $expected->available) {
            return ['cause' => self::CAUSE_DATA_GAP, 'detail' => $expected->reason . ': ' . $expected->message];
        }

        $difference = $expected->interest - $posted;
        if ($this->withinTolerance($difference, $posted)) {
            return ['cause' => self::CAUSE_WITHIN_TOLERANCE,
                'detail' => 'The posting and the contractual interest agree inside the governed band.'];
        }

        $impliedDays = $expected->openingBalance > 0.0 && $expected->rate > 0.0
            ? $posted * $expected->denominator / ($expected->openingBalance * $expected->rate) : null;

        if ($expected->firstDisbursementMonth) {
            return ['cause' => self::CAUSE_LATE_DISBURSEMENT,
                'detail' => 'The money was paid out on ' . $expected->firstDisbursementDate . ', so '
                    . $expected->daysCharged . ' of the month\'s ' . $expected->daysInPeriod . ' days are charged'
                    . ($impliedDays === null ? '' : '; the posting works out at ' . number_format($impliedDays, 1) . ' days')
                    . '.'];
        }

        $catchUp = $this->catchUp($contractId, $period, $posted, $expected);
        if ($catchUp !== null) {
            return ['cause' => self::CAUSE_CATCH_UP_POSTING, 'detail' => $catchUp];
        }

        $tranche = $this->trancheInMonth($contractId, $period);
        if ($tranche !== null) {
            return ['cause' => self::CAUSE_MID_MONTH_TRANCHE,
                'detail' => $this->money($tranche) . ' more was drawn during the month, so the month-end balance is not'
                    . ' the balance the whole month was charged on. The per-drawdown dates are needed to charge it day by day.'];
        }

        $rate = $this->rateMismatch($contractId, $period, $expected, $contract, $posted);
        if ($rate !== null) {
            return ['cause' => self::CAUSE_RATE_MISMATCH, 'detail' => $rate];
        }

        $implied = $expected->openingBalance > 0.0 && $expected->daysCharged > 0
            ? $posted * $expected->denominator / ($expected->openingBalance * $expected->daysCharged) : null;

        return ['cause' => self::CAUSE_UNEXPLAINED,
            'detail' => 'The posting is ' . $this->money(-$difference) . ' away from the contractual interest'
                . ($implied === null ? '' : ', which works out at ' . $this->percent($implied) . ' a year on the same balance and days')
                . ', and no rate recorded for this account matches it.'];
    }

    /**
     * Several months posted at once: the months immediately before this one
     * have nothing in the ledger, and their contractual interest plus this
     * month's adds up to what was posted.
     */
    private function catchUp(string $contractId, string $period, float $posted, ContractualInterest $expected): ?string
    {
        $firstMonth = $this->contractual->firstChargeableMonth($contractId);
        $running = $expected->interest;
        $months = [];
        $month = $period;

        for ($i = 0; $i < self::CATCH_UP_LOOK_BACK_MONTHS; $i++) {
            $month = CarbonImmutable::createFromFormat('Y-m-d', $month . '-01')->subMonth()->format('Y-m');
            if ($firstMonth !== null && $month < $firstMonth) {
                break;
            }
            if (isset($this->postedMonths[$contractId][$month])) {
                break;
            }
            $earlier = $this->contractual->forPeriod($contractId, $month);
            if (! $earlier->available) {
                break;
            }
            $running += $earlier->interest;
            $months[] = $month;
            if ($this->withinTolerance($running - $posted, $posted)) {
                return 'The ledger posted nothing for ' . implode(', ', array_reverse($months))
                    . '. The contractual interest for ' . (count($months) === 1 ? 'that month' : 'those months')
                    . ' and this one comes to ' . $this->money($running) . ', which is what this posting carries.';
            }
        }

        return null;
    }

    /** More money drawn inside the month, read off the loan book's cumulative disbursed column. */
    private function trancheInMonth(string $contractId, string $period): ?float
    {
        $previous = CarbonImmutable::createFromFormat('Y-m-d', $period . '-01')->subMonth()->format('Y-m');
        $now = $this->contractual->disbursedToDate($contractId, $period);
        $before = $this->contractual->disbursedToDate($contractId, $previous);
        if ($now === null || $before === null) {
            return null;
        }
        $drawn = $now - $before;

        return $drawn > 0.005 ? $drawn : null;
    }

    /**
     * The ledger's posting implies one of the other rates recorded for the same
     * account. That is the proven cause: the core system charging a rate the
     * offer letter or the loan book does not carry. An implied rate that
     * matches nothing on record is not called a rate mismatch.
     */
    private function rateMismatch(string $contractId, string $period, ContractualInterest $expected, ?ContractEir $contract, float $posted): ?string
    {
        if ($expected->openingBalance <= 0.0 || $expected->daysCharged <= 0) {
            return null;
        }
        $implied = $posted * $expected->denominator / ($expected->openingBalance * $expected->daysCharged);

        $candidates = [];
        if ($expected->rateSource !== 'LOAN_BOOK_PERIOD') {
            $fromBook = $this->contractual->loanBookRate($contractId, $period);
            if ($fromBook !== null) {
                $candidates['the loan book for the month'] = $fromBook;
            }
        }
        if ($expected->rateSource !== 'CONTRACT_MASTER') {
            $fromMaster = $this->asRate($contract?->contractual_rate);
            if ($fromMaster !== null) {
                $candidates['the contract master'] = $fromMaster;
            }
        }
        $reference = $this->asRate($contract?->reference_rate_at_origination);
        if ($reference !== null) {
            $candidates['the reference rate in the offer letter'] = $reference;
            $spread = $contract?->spread_over_prime ?? $contract?->markup;
            if ($spread !== null && (float) $spread > 0) {
                $candidates['the offer letter rate plus the spread over prime'] = $reference + $this->asRate($spread);
            }
        }

        foreach ($candidates as $label => $rate) {
            if (abs($implied - $rate) <= self::RATE_MATCH_TOLERANCE) {
                return 'The posting works out at ' . $this->percent($implied) . ' a year on the same balance and days,'
                    . ' which is the rate ' . $label . ' carries. The engine charged ' . $this->percent($expected->rate)
                    . ' from ' . $this->rateSourceLabel($expected->rateSource) . '.';
            }
        }

        return null;
    }

    private function rateSourceLabel(?string $source): string
    {
        return match ($source) {
            'LOAN_BOOK_PERIOD' => 'the loan book for the month',
            'CONTRACT_MASTER' => 'the contract master',
            default => 'the source on record',
        };
    }

    private function asRate($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $rate = (float) $value;
        if ($rate <= 0) {
            return null;
        }

        return $rate > 1 ? $rate / 100 : $rate;
    }

    private function money(float $value): string
    {
        return 'MWK ' . number_format($value, 2);
    }

    private function percent(float $rate): string
    {
        return number_format($rate * 100, 2) . ' percent';
    }

    private function withinTolerance(float $variance, float $posted): bool
    {
        $band = $this->tolerance ?? $this->governance->reconciliationTolerance();

        return abs($variance) <= max($band['floor'], abs($posted) * $band['percent'] / 100);
    }

    /** The governed share of the posted amount, for the screen's tolerance note. */
    private function tolerancePercent(): float
    {
        return ($this->tolerance ?? $this->governance->reconciliationTolerance())['percent'];
    }

    /** The walk from what the ledger posted to what the engine calculated. */
    private function bridge(array $rows): array
    {
        $matched = array_filter($rows, fn ($r) => $r['eir_accrued'] !== null);

        $glTotal = array_sum(array_column($rows, 'gl_posted'));
        $glMatched = array_sum(array_column($matched, 'gl_posted'));

        return [
            'gl_total' => round($glTotal, 2),
            'gl_without_counterpart' => round($glTotal - $glMatched, 2),
            'gl_matched' => round($glMatched, 2),
            'expected_total' => round((float) array_sum(array_column($matched, 'expected_interest')), 2),
            'base_effect' => round((float) array_sum(array_column($matched, 'base_effect')), 2),
            'carrying_amount_effect' => round((float) array_sum(array_column($matched, 'carrying_amount_effect')), 2),
            'rate_effect' => round((float) array_sum(array_column($matched, 'rate_effect')), 2),
            'impairment_effect' => round((float) array_sum(array_column($matched, 'impairment_effect')), 2),
            'unexplained' => round((float) array_sum(array_column($matched, 'unexplained')), 2),
            'eir_total' => round((float) array_sum(array_column($matched, 'eir_accrued')), 2),
            'net_variance' => round((float) array_sum(array_column($matched, 'variance')), 2),
        ];
    }

    private function summary(array $rows): array
    {
        $statuses = array_count_values(array_column($rows, 'status'));
        $causes = array_count_values(array_column($rows, 'cause'));
        $postingRows = count(array_filter($rows, fn ($r) => $r['has_posting']));
        $band = $this->tolerance ?? $this->governance->reconciliationTolerance();
        // The contractual reconciliation covers every row, including the ones
        // the revenue run has not reached: it needs the loan book and the
        // contract, not the amortised-cost roll-forward. A row with a data gap
        // contributes nothing to the expected total, which is why the count of
        // those rows is reported beside it.
        $posted = (float) array_sum(array_column($rows, 'gl_posted'));
        $expected = (float) array_sum(array_column($rows, 'expected_interest'));

        return [
            'rows' => count($rows),
            'posted_total' => round($posted, 2),
            'expected_total' => round($expected, 2),
            'expected_difference_total' => round($expected - $posted, 2),
            'posting_rows' => $postingRows,
            'no_posting_rows' => count($rows) - $postingRows,
            'within_tolerance' => $statuses['WITHIN_TOLERANCE'] ?? 0,
            'variance_rows' => $statuses['VARIANCE'] ?? 0,
            'not_calculated' => ($statuses['NOT_CALCULATED'] ?? 0) + ($statuses['NO_CONTRACT'] ?? 0),
            'tolerance_percent' => $band['percent'],
            'tolerance_floor' => $band['floor'],
            'day_count' => $this->monthBasis['day_count'],
            'causes' => $causes,
            'expected_agrees' => $causes[self::CAUSE_WITHIN_TOLERANCE] ?? 0,
            'expected_explained' => count($rows) - ($causes[self::CAUSE_WITHIN_TOLERANCE] ?? 0)
                - ($causes[self::CAUSE_UNEXPLAINED] ?? 0),
            'expected_unexplained' => $causes[self::CAUSE_UNEXPLAINED] ?? 0,
        ];
    }

    private function emptyBridge(): array
    {
        return ['gl_total' => 0.0, 'gl_without_counterpart' => 0.0, 'gl_matched' => 0.0, 'expected_total' => 0.0,
            'base_effect' => 0.0, 'carrying_amount_effect' => 0.0, 'rate_effect' => 0.0, 'impairment_effect' => 0.0,
            'unexplained' => 0.0, 'eir_total' => 0.0, 'net_variance' => 0.0];
    }

    private function emptySummary(): array
    {
        // Nothing to reconcile yet. The screen still has to load, so a band
        // that has not been approved is reported as absent rather than raised.
        try {
            $band = $this->governance->reconciliationTolerance();
        } catch (GovernanceSettingMissingException) {
            $band = ['percent' => null, 'floor' => null];
        }

        return ['rows' => 0, 'posted_total' => 0.0, 'expected_total' => 0.0, 'expected_difference_total' => 0.0,
            'posting_rows' => 0, 'no_posting_rows' => 0, 'within_tolerance' => 0, 'variance_rows' => 0,
            'not_calculated' => 0, 'tolerance_percent' => $band['percent'], 'tolerance_floor' => $band['floor'],
            'day_count' => null, 'causes' => [], 'expected_agrees' => 0, 'expected_explained' => 0,
            'expected_unexplained' => 0];
    }

    private function periodEnd(string $period): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $period . '-01')->endOfMonth();
    }

    private function periodKey(int $year, int $month): string
    {
        return sprintf('%04d-%02d', $year, $month);
    }
}
