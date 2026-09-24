<?php

namespace App\Services\Eir;

use App\Models\ContractEir;
use App\Support\ReportingPeriod;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * What the core banking system charges a loan in a month (spec v3 section 7.7,
 * decisions D9 and D10).
 *
 * This is not an estimate of E-Banker's behaviour. It is the convention that
 * reproduces E-Banker's own postings to the cent, established by rebuilding
 * MAIIC's monthly loan books against their general ledger:
 *
 *     prior month-end outstanding balance x annual contractual rate
 *         x days in the calendar month / 365
 *
 * Three rules go with it, each proven on MAIIC's own data:
 *
 *  - In the month the money is first paid out, the days run from the
 *    disbursement day INCLUSIVE to the month end. Ebenezer Midian, drawn on
 *    8 August 2025, is charged 24 days of August; Microloan Foundation, drawn
 *    on 18 December 2025, is charged 14 days of December.
 *  - A capitalising moratorium (E-Banker's "Both") compounds monthly: next
 *    month opens on this month's balance plus exactly this month's interest.
 *  - Dividing the annual rate by twelve is wrong. In February it overstates
 *    the charge by between 8 and 9 percent.
 *
 * The day count is the governed setting `day_count`, read for the month being
 * charged, so a change to it never restates a month already reported. Nothing
 * here is a constant: no 365, no division by twelve.
 *
 * Every figure is returned with the basis it was worked out on, and a missing
 * input produces no figure at all, only a named reason. See ContractualInterest.
 */
class ContractualInterestService
{
    /** The governed day-count options, and the denominator each one charges on. */
    private const DENOMINATORS = ['ACT/365' => 365, '30/360' => 360];

    /** A 30/360 month is thirty days whatever the calendar says. */
    private const DAYS_IN_A_360_MONTH = 30;

    /**
     * How far the capitalisation fallback will walk back before giving up.
     * A moratorium longer than this is a data problem, not a calculation.
     */
    private const MAX_CAPITALISED_MONTHS = 120;

    private readonly GovernanceService $governance;

    /** Loan-book rows by contract id, then by YYYY-MM. Loaded once per contract. */
    private array $loanBooks = [];

    /** Contract master rows by contract id; false records "looked and found none". */
    private array $contracts = [];

    public function __construct(?GovernanceService $governance = null)
    {
        $this->governance = $governance ?? app(GovernanceService::class);
    }

    /**
     * Load the loan books and contract masters for a whole batch in two
     * queries, so a portfolio-wide reconciliation does not ask per account.
     *
     * @param  list<string>  $contractIds
     */
    public function prime(array $contractIds): void
    {
        $wanted = array_values(array_unique(array_filter($contractIds, fn ($id) => (string) $id !== '')));
        $missingBooks = array_values(array_diff($wanted, array_keys($this->loanBooks)));
        $missingContracts = array_values(array_diff($wanted, array_keys($this->contracts)));

        if ($missingBooks !== []) {
            foreach ($missingBooks as $id) {
                $this->loanBooks[$id] = [];
            }
            foreach ($this->loanBookQuery($missingBooks)->get() as $row) {
                $this->fileLoanBookRow($row);
            }
        }

        if ($missingContracts !== []) {
            foreach ($missingContracts as $id) {
                $this->contracts[$id] = false;
            }
            foreach (ContractEir::whereIn('contract_id', $missingContracts)->get() as $contract) {
                $this->contracts[$contract->contract_id] = $contract;
            }
        }
    }

    /**
     * The contractual interest charged on one account for one month.
     *
     * @param  string  $period  YYYY-MM, YYYYMM or any date inside the month
     */
    public function forPeriod(string $contractId, string $period): ContractualInterest
    {
        return $this->compute($contractId, $period, []);
    }

    /**
     * The day count in force for a month: the governed setting, never a
     * convention written here.
     */
    public function dayCount(CarbonInterface $asOf): string
    {
        $option = trim($this->governance->get('day_count', $asOf));
        if (! array_key_exists($option, self::DENOMINATORS)) {
            throw new InvalidArgumentException("The day count option '{$option}' is not one the engine can charge on: "
                . implode(' or ', array_keys(self::DENOMINATORS)) . '.');
        }

        return $option;
    }

    /**
     * The customer name the loan book carries for an account, for reports that
     * name the borrower rather than only the account number.
     */
    public function customerName(string $contractId): ?string
    {
        $rows = $this->rowsFor($contractId);
        foreach (array_reverse($rows) as $row) {
            $name = trim((string) ($row->customer_name ?? ''));
            if ($name !== '') {
                return $name;
            }
        }

        return null;
    }

    /**
     * The annual rate the loan book carries for a month, as a decimal. The
     * reconciliation reads it to see whether the ledger charged the loan book's
     * rate or another one on record.
     */
    public function loanBookRate(string $contractId, string $period): ?float
    {
        $month = ReportingPeriod::normalise($period);
        $row = $month === null ? null : $this->rowFor($contractId, $month);

        return $this->asRate($row->interest_rate ?? null);
    }

    /** The loan book's outstanding balance at the end of a month, when it holds one. */
    public function outstandingBalance(string $contractId, string $period): ?float
    {
        $month = ReportingPeriod::normalise($period);

        return $month === null ? null : $this->balanceFromRow($this->rowFor($contractId, $month));
    }

    /**
     * The cumulative amount disbursed by the end of a month, as the loan book
     * reports it. The reconciliation compares two months of this to see
     * whether money was drawn inside a month.
     */
    public function disbursedToDate(string $contractId, string $period): ?float
    {
        $month = ReportingPeriod::normalise($period);
        $row = $month === null ? null : $this->rowFor($contractId, $month);
        if ($row === null || $row->disbursed === null) {
            return null;
        }

        return (float) $row->disbursed;
    }

    /**
     * True when the loan book shows the account live at the end of a month:
     * a row exists and it carries a balance.
     */
    public function isLiveInPeriod(string $contractId, string $period): bool
    {
        return ($this->outstandingBalance($contractId, $period) ?? 0.0) > 0.0;
    }

    /**
     * The first month the account can be charged in, which is the month the
     * money was first paid out.
     */
    public function firstChargeableMonth(string $contractId): ?string
    {
        $contract = $this->contract($contractId);

        return $contract === null ? null : ($this->firstDisbursement($contract)['month'] ?? null);
    }

    /**
     * @param  list<string>  $chain  months already being computed, so the
     *                               capitalisation fallback cannot loop
     */
    private function compute(string $contractId, string $period, array $chain): ContractualInterest
    {
        $month = ReportingPeriod::normalise($period);
        if ($month === null) {
            return ContractualInterest::unavailable($contractId, null, ContractualInterest::PERIOD_NOT_READABLE,
                "'{$period}' cannot be read as a reporting month. Write it as yyyy-mm.");
        }

        $contract = $this->contract($contractId);
        if ($contract === null) {
            return ContractualInterest::unavailable($contractId, $month, ContractualInterest::NO_CONTRACT,
                "There is no contract master row for {$contractId}, so neither its rate nor its disbursement date is known. Load it on EIR Data Intake.");
        }

        $monthEnd = CarbonImmutable::createFromFormat('Y-m-d', $month . '-01')->endOfMonth();
        $dayCount = $this->dayCount($monthEnd);

        $rate = $this->rateForPeriod($contractId, $month, $contract);
        if ($rate === null) {
            return ContractualInterest::unavailable($contractId, $month, ContractualInterest::NO_RATE,
                "No annual interest rate is recorded for {$contractId} in {$month}: the loan book for the month has none and the contract master has none either.");
        }

        $disbursement = $this->firstDisbursement($contract);
        $priorBalance = $this->balanceFromRow($this->rowFor($contractId, $this->previousMonth($month)));

        if ($disbursement['month'] === null && $priorBalance === null) {
            return ContractualInterest::unavailable($contractId, $month, ContractualInterest::NO_FIRST_DISBURSEMENT_DATE,
                "Nothing records when {$contractId} was first paid out, and no loan book gives the balance at the end of "
                . $this->previousMonth($month) . '. One of the two is needed before a month can be charged.');
        }

        if ($disbursement['month'] !== null && $month < $disbursement['month']) {
            return ContractualInterest::unavailable($contractId, $month, ContractualInterest::BEFORE_FIRST_DISBURSEMENT,
                "{$contractId} was first paid out on {$disbursement['date']}, which is after {$month}, so nothing is charged for that month.");
        }

        $firstMonth = $disbursement['month'] !== null && $month === $disbursement['month'];
        $capitalising = $this->capitalisesMonthly($contract, $month, $disbursement);

        if ($firstMonth) {
            $drawn = $this->drawnAmount($contract, $month);
            if ($drawn === null) {
                return ContractualInterest::unavailable($contractId, $month, ContractualInterest::NO_DRAWN_AMOUNT,
                    "{$contractId} was paid out in {$month} but no amount drawn is recorded, so the first part month cannot be charged.");
            }
            $opening = $drawn;
            $openingSource = 'DISBURSEMENT';
        } elseif ($priorBalance !== null) {
            $opening = $priorBalance;
            $openingSource = 'LOAN_BOOK_PRIOR_MONTH';
        } else {
            // No loan book for the month before. On a capitalising moratorium
            // the balance is knowable anyway: it is last month's balance plus
            // last month's interest, which is the compounding E-Banker itself
            // applies. Outside that convention the engine stops.
            $carried = $this->carriedForward($contractId, $month, $contract, $chain, $disbursement);
            if (! $carried instanceof ContractualInterest) {
                return $carried === ContractualInterest::CAPITALISATION_NOT_MONTHLY
                    ? ContractualInterest::unavailable($contractId, $month, ContractualInterest::CAPITALISATION_NOT_MONTHLY,
                        "No loan book gives the balance at the end of " . $this->previousMonth($month) . " for {$contractId}, and the governed moratorium convention is not monthly capitalisation, so it cannot be carried forward either.")
                    : ContractualInterest::unavailable($contractId, $month, ContractualInterest::NO_OPENING_BALANCE,
                        "No loan book gives the balance at the end of " . $this->previousMonth($month) . " for {$contractId}, so there is no balance to charge {$month} on. Load that month's Loan Book Report.");
            }
            $opening = $carried->openingBalance + $carried->interest;
            $openingSource = 'CAPITALISED_MORATORIUM';
        }

        $days = $this->days($dayCount, $monthEnd, $firstMonth ? $disbursement['date'] : null);
        $denominator = self::DENOMINATORS[$dayCount];
        $interest = round($opening * $rate['rate'] * $days['charged'] / $denominator, 2);

        return ContractualInterest::calculated($contractId, $month, $opening, $openingSource,
            $rate['rate'], $rate['source'], $dayCount, $days['charged'], $days['in_period'], $denominator,
            $interest, $firstMonth, $capitalising, $disbursement['date'], $disbursement['source']);
    }

    /**
     * The annual contractual rate in force for one month.
     *
     * The loan book for the month is the first source, because it is struck at
     * the month end and already carries any repricing E-Banker applied; the
     * contract master is the fallback. Floating-rate resets are phase P5: this
     * is the one place the rate is decided, so P5 extends here and nowhere
     * else.
     *
     * @return array{rate:float, source:string}|null
     */
    protected function rateForPeriod(string $contractId, string $month, ContractEir $contract): ?array
    {
        $row = $this->rowFor($contractId, $month);
        $fromBook = $this->asRate($row->interest_rate ?? null);
        if ($fromBook !== null) {
            return ['rate' => $fromBook, 'source' => 'LOAN_BOOK_PERIOD'];
        }

        $fromContract = $this->asRate($contract->contractual_rate);

        return $fromContract === null ? null : ['rate' => $fromContract, 'source' => 'CONTRACT_MASTER'];
    }

    /**
     * Days charged and days in the month, on the governed day count.
     *
     * In the month of the first disbursement the days run from the
     * disbursement day inclusive, which is what reproduces the ledger: 8 to
     * 31 August is 24 days, not 23.
     *
     * @return array{charged:int, in_period:int}
     */
    private function days(string $dayCount, CarbonImmutable $monthEnd, ?string $disbursedOn): array
    {
        $inPeriod = $dayCount === '30/360' ? self::DAYS_IN_A_360_MONTH : $monthEnd->day;
        if ($disbursedOn === null) {
            return ['charged' => $inPeriod, 'in_period' => $inPeriod];
        }

        $day = CarbonImmutable::createFromFormat('Y-m-d', $disbursedOn)->day;
        $day = $dayCount === '30/360' ? min($day, self::DAYS_IN_A_360_MONTH) : $day;

        return ['charged' => max(0, $inPeriod - $day + 1), 'in_period' => $inPeriod];
    }

    /**
     * Last month's figure, for the capitalising-moratorium carry forward.
     *
     * Returns the reason code instead when the carry forward is not open to
     * the engine, so the caller can name it.
     */
    private function carriedForward(
        string $contractId,
        string $month,
        ContractEir $contract,
        array $chain,
        array $disbursement,
    ): ContractualInterest|string {
        $previous = $this->previousMonth($month);
        if (in_array($previous, $chain, true) || count($chain) >= self::MAX_CAPITALISED_MONTHS) {
            return ContractualInterest::NO_OPENING_BALANCE;
        }
        if ($disbursement['month'] === null || $previous < $disbursement['month']) {
            return ContractualInterest::NO_OPENING_BALANCE;
        }
        if (! $this->capitalisesMonthly($contract, $previous, $disbursement)) {
            return $this->moratoriumInForce($contract, $previous, $disbursement)
                ? ContractualInterest::CAPITALISATION_NOT_MONTHLY
                : ContractualInterest::NO_OPENING_BALANCE;
        }

        $earlier = $this->compute($contractId, $previous, array_merge($chain, [$month]));

        return $earlier->available ? $earlier : ContractualInterest::NO_OPENING_BALANCE;
    }

    /**
     * True when this month sits inside a moratorium that defers both interest
     * and capital AND the governed convention compounds it monthly (D10). Both
     * have to hold: the convention is read from the Governance Centre for the
     * month, never assumed.
     */
    private function capitalisesMonthly(ContractEir $contract, string $month, array $disbursement): bool
    {
        if (! $this->moratoriumInForce($contract, $month, $disbursement)) {
            return false;
        }
        $monthEnd = CarbonImmutable::createFromFormat('Y-m-d', $month . '-01')->endOfMonth();

        return str_starts_with(strtolower(trim($this->governance->get('moratorium_capitalisation', $monthEnd))), 'monthly');
    }

    /** True when a "Both" moratorium covers the month, on the contract's own terms. */
    private function moratoriumInForce(ContractEir $contract, string $month, array $disbursement): bool
    {
        if ((string) $contract->moratorium_type !== 'BOTH') {
            return false;
        }
        $months = (int) ($contract->moratorium_months ?? 0);
        if ($months < 1) {
            return false;
        }

        $start = $contract->moratorium_from?->format('Y-m') ?? $disbursement['month'];
        if ($start === null) {
            return false;
        }

        $last = CarbonImmutable::createFromFormat('Y-m-d', $start . '-01')->addMonths($months - 1)->format('Y-m');

        return $month >= $start && $month <= $last;
    }

    /**
     * When the money was first paid out, and which field said so. The interest
     * start date E-Banker holds is preferred over the origination date, and
     * the loan book's own create date is the last resort.
     *
     * @return array{date:?string, month:?string, source:?string}
     */
    private function firstDisbursement(ContractEir $contract): array
    {
        $candidates = [
            'CONTRACT_INTEREST_START' => $contract->interest_start_date?->toDateString(),
            'CONTRACT_ORIGINATION' => $contract->origination_date?->toDateString(),
            'LOAN_BOOK_CREATE_DATE' => $this->earliestCreateDate($contract->contract_id),
        ];

        foreach ($candidates as $source => $date) {
            if ($date !== null && $date !== '') {
                return ['date' => substr($date, 0, 10), 'month' => substr($date, 0, 7), 'source' => $source];
            }
        }

        return ['date' => null, 'month' => null, 'source' => null];
    }

    /** The amount treated as drawn in the month of the first disbursement. */
    private function drawnAmount(ContractEir $contract, string $month): ?float
    {
        $drawn = (float) ($contract->drawn_amount ?? 0);
        if ($drawn > 0) {
            return $drawn;
        }

        $disbursed = $this->disbursedToDate($contract->contract_id, $month);

        return $disbursed !== null && $disbursed > 0 ? $disbursed : null;
    }

    private function earliestCreateDate(string $contractId): ?string
    {
        foreach ($this->rowsFor($contractId) as $row) {
            $date = trim((string) ($row->create_date ?? ''));
            if ($date !== '') {
                return substr($date, 0, 10);
            }
        }

        return null;
    }

    /**
     * The loan book's outstanding balance on a row: the carrying amount, or
     * the principal balance where the carrying amount is blank, which is the
     * same fallback the loan-book importer applies.
     */
    private function balanceFromRow(?object $row): ?float
    {
        if ($row === null) {
            return null;
        }
        $carrying = (float) ($row->carrying_amount ?? 0);
        if ($carrying > 0) {
            return $carrying;
        }
        $principal = (float) ($row->principal_balance ?? 0);

        return $principal > 0 ? $principal : null;
    }

    /**
     * A rate as a decimal. Extracts write 31 for 31 percent and 0.31 for the
     * same rate; anything above 1 is a percentage, the rule the contract
     * master importer already applies.
     */
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

    private function contract(string $contractId): ?ContractEir
    {
        if (! array_key_exists($contractId, $this->contracts)) {
            $this->contracts[$contractId] = ContractEir::where('contract_id', $contractId)->first() ?? false;
        }

        return $this->contracts[$contractId] ?: null;
    }

    /** @return array<string, object> loan-book rows for one contract, oldest month first */
    private function rowsFor(string $contractId): array
    {
        if (! array_key_exists($contractId, $this->loanBooks)) {
            $this->loanBooks[$contractId] = [];
            foreach ($this->loanBookQuery([$contractId])->get() as $row) {
                $this->fileLoanBookRow($row);
            }
        }

        return $this->loanBooks[$contractId];
    }

    private function rowFor(string $contractId, string $month): ?object
    {
        return $this->rowsFor($contractId)[$month] ?? null;
    }

    /**
     * File one loan-book row under its normalised month. The column is a free
     * string, so it is read through ReportingPeriod rather than with a SUBSTR
     * trick, and a month delivered twice keeps the later row.
     */
    private function fileLoanBookRow(object $row): void
    {
        $month = ReportingPeriod::normalise($row->reporting_period ?? null);
        if ($month === null) {
            return;
        }
        $contractId = (string) $row->contract_id;
        $existing = $this->loanBooks[$contractId][$month] ?? null;
        if ($existing === null || (int) $row->id >= (int) $existing->id) {
            $this->loanBooks[$contractId][$month] = $row;
        }
        ksort($this->loanBooks[$contractId]);
    }

    /** @param list<string> $contractIds */
    private function loanBookQuery(array $contractIds)
    {
        return DB::table('loan_books')
            ->select('id', 'contract_id', 'customer_name', 'reporting_period', 'interest_rate',
                'carrying_amount', 'principal_balance', 'disbursed', 'create_date')
            ->whereIn('contract_id', $contractIds)
            ->orderBy('id');
    }

    private function previousMonth(string $month): string
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $month . '-01')->subMonth()->format('Y-m');
    }
}
