<?php

namespace App\Services\Eir;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Builds the contractual repayment schedule from the terms E-Banker holds
 * (spec v3 sections 7.1 and 7.6, phase P4). It is the engine's written record
 * of what the borrower promised to pay and on which date, and it is the input
 * the date-sensitive EIR solver discounts.
 *
 * Only the shapes the source system can actually express are built, and
 * anything outside them is refused with a named reason rather than bent into
 * the nearest shape the code already had:
 *
 *  - Moratorium. E-Banker's Moratorium Type has exactly two options (LOS
 *    p.76, decision D4): "Principle Only", where the borrower pays interest
 *    but no principal and the balance does not grow, and "Both (Interest +
 *    Principle)", where nothing is paid, the interest is added to the balance
 *    each month (decision D10) and the instalment is then sized on the
 *    enlarged balance. No third shape exists in 33 months of loan books, so
 *    none is built. A moratorium with no type stated is refused: the two
 *    shapes give different cash flows and the engine does not guess.
 *    The moratorium runs from the first disbursement date (E-Banker p.31),
 *    which is `moratorium_from`, not from approval. `grace_period_months` is
 *    E-Banker's separate grace and is carried through untouched: it is never
 *    folded into the moratorium.
 *
 *  - Instalment shape. EMI Calc Type E (manual p.30) is the level instalment
 *    and covers the whole current book: MAIIC confirmed on 10 September 2026
 *    that every facility is EMI today (fact F7). Type P is equal principal,
 *    where the principal share is fixed and the interest share falls. Type F
 *    is refused until MAIiC supplies an example of the pattern it produces.
 *
 *  - Sanction against balance. Loan Interest Cal. Base On (N S B, p.30)
 *    decides whether interest is charged on the full approved amount or only
 *    on what has been drawn, and Installment Based On (p.22) decides the same
 *    for the instalment. On Lake Malawi Aquaculture (approved MWK
 *    1,055,473,655, drawn MWK 297,161,905 at 34.75 percent) the two bases
 *    differ by about MWK 22 million a month, so the flag is not cosmetic.
 *    Where a facility is partly drawn and neither the contract nor its scheme
 *    states a basis, the schedule is refused.
 *
 *  - Dates, not ordinal periods. Every row carries its due date and the days
 *    in its period. A period that is a whole payment period is charged at the
 *    nominal rate over the number of payments a year, which is the basis the
 *    offer-letter instalments reproduce; a period that is not (a short or long
 *    first period, or the gap a moratorium leaves) is charged on its actual
 *    days under the day count in force. The day count is read from the
 *    Governance Centre key `day_count` (ACT/365 by default, decision D9),
 *    never from a number written here.
 */
class ScheduleGeneratorService
{
    /** Monthly, every two months, quarterly, semi-annual or annual. */
    private const ALLOWED_FREQUENCIES = [1, 2, 4, 6, 12];

    /** The borrower pays interest but no principal; the balance does not grow. */
    public const MORATORIUM_PRINCIPAL_ONLY = 'PRINCIPAL_ONLY';

    /** Nothing is paid; the interest is added to the balance each month. */
    public const MORATORIUM_BOTH = 'BOTH';

    /** EMI Calc Type E: one level instalment for the whole term. */
    public const EMI_LEVEL_INSTALMENT = 'E';

    /** EMI Calc Type P: a fixed principal share and a falling interest share. */
    public const EMI_EQUAL_PRINCIPAL = 'P';

    /** EMI Calc Type F: flexible. Not built; no example exists. */
    public const EMI_FLEXIBLE = 'F';

    /** Loan Interest Cal. Base On = S: interest on the approved amount. */
    public const INTEREST_ON_SANCTION = 'S';

    /** Loan Interest Cal. Base On = B: interest on the outstanding balance. */
    public const INTEREST_ON_BALANCE = 'B';

    /** Installment Based On the approved (sanctioned) amount. */
    public const INSTALMENT_ON_SANCTION = 'SANCTION';

    /** Installment Based On the amount disbursed. */
    public const INSTALMENT_ON_DISBURSEMENT = 'DISBURSEMENT';

    /** The day counts the Governance Centre offers, plus ACT/360 for completeness. */
    private const DAY_COUNTS = ['ACT/365', 'ACT/360', '30/360'];

    public function __construct(private readonly ?GovernanceService $governance = null)
    {
    }

    /**
     * @param array $terms {
     *   principal: float              amount drawn (> 0)
     *   approved_amount: float        sanctioned amount; defaults to the drawn amount
     *   annual_rate: float            nominal annual contractual rate as a decimal (0.3475)
     *   payments_per_year: int        1 | 2 | 4 | 6 | 12
     *   n_payments: int               instalments after the moratorium
     *   start_date: string|Carbon      first disbursement / origination date
     *   interest_start_date: ?date    when interest starts, if not the start date
     *   first_due_date: ?date         first instalment date as E-Banker holds it
     *   moratorium_months: int        moratorium length in months (default 0)
     *   moratorium_type: ?string      PRINCIPAL_ONLY | BOTH, or E-Banker's own words
     *   moratorium_from: ?date        first disbursement date the moratorium runs from
     *   grace_period_months: int      E-Banker's separate grace; recorded, never applied here
     *   emi_calc_type: ?string        E | P | F
     *   type_of_repayment: ?string    EMI | equal principal, cross-checked against emi_calc_type
     *   interest_calc_base: ?string   N | S | B
     *   installment_based_on: ?string sanction | disbursement
     *   day_count: ?string            overrides the Governance Centre value (tests)
     *   as_of: ?date                  the date the conventions are read as at
     *   scheme: ?array                the scheme's settings, used where the contract states none
     * }
     * @return array {
     *   instalment: float                  level instalment (E) or the fixed principal share (P)
     *   instalment_shape: string           LEVEL_INSTALMENT | EQUAL_PRINCIPAL
     *   emi_calc_type: string,
     *   interest_basis: string             S | B
     *   instalment_basis: string           SANCTION | DISBURSEMENT
     *   basis_sources: array               where each shape came from: CONTRACT, SCHEME or a named assumption
     *   moratorium_type: ?string,
     *   moratorium_months: int,
     *   moratorium_from: ?string,
     *   moratorium_end: ?string,
     *   grace_period_months: int,
     *   day_count: string,
     *   capitalised_principal: float       balance the instalments amortise
     *   capitalised_interest: float        interest added to the balance during a Both moratorium
     *   moratorium_rows: list<array>,
     *   rows: list<array{due_date: string, principal_due: float, interest_due: float, days: int, opening_balance: float, closing_balance: float, phase: string}>,
     *   totals: array{principal: float, interest: float}
     * }
     */
    public function generate(array $terms): array
    {
        $principal = (float) ($terms['principal'] ?? 0);
        $annualRate = (float) ($terms['annual_rate'] ?? 0);
        $paymentsPerYear = (int) ($terms['payments_per_year'] ?? 12);
        $nPayments = (int) ($terms['n_payments'] ?? 0);
        $moratoriumMonths = (int) ($terms['moratorium_months'] ?? 0);
        $graceMonths = (int) ($terms['grace_period_months'] ?? 0);
        $scheme = is_array($terms['scheme'] ?? null) ? $terms['scheme'] : [];

        if ($principal <= 0) {
            throw new InvalidArgumentException('principal must be positive');
        }
        if ($annualRate < 0) {
            throw new InvalidArgumentException('annual_rate cannot be negative');
        }
        if (! in_array($paymentsPerYear, self::ALLOWED_FREQUENCIES, true)) {
            throw new InvalidArgumentException('payments_per_year must be one of ' . implode(',', self::ALLOWED_FREQUENCIES));
        }
        if ($nPayments < 1) {
            throw new InvalidArgumentException('n_payments must be at least 1');
        }
        if ($moratoriumMonths < 0) {
            throw new InvalidArgumentException('moratorium_months cannot be negative');
        }

        $startDate = $this->date($terms['start_date'] ?? 'today');
        $asOf = isset($terms['as_of']) && $terms['as_of'] ? $this->date($terms['as_of']) : $startDate;
        $dayCount = $this->dayCount($terms, $asOf);

        $sanctioned = (float) ($terms['approved_amount'] ?? 0);
        if ($sanctioned <= 0) {
            $sanctioned = $principal;
        }
        // The two bases only differ where the facility is partly drawn, so
        // that is the only case in which a missing flag stops the schedule.
        $partlyDrawn = round($sanctioned - $principal, 2) > 0.0;

        [$emiCalcType, $emiSource] = $this->emiCalcType($terms, $scheme);
        [$interestBasis, $interestSource] = $this->interestBasis($terms, $scheme, $partlyDrawn);
        [$instalmentBasis, $instalmentSource] = $this->instalmentBasis($terms, $scheme, $partlyDrawn);
        [$moratoriumType, $moratoriumSource] = $moratoriumMonths > 0
            ? $this->moratoriumType($terms, $scheme, $moratoriumMonths)
            : [null, 'NOT_APPLICABLE'];

        $interestBaseFor = fn (float $balance): float => $interestBasis === self::INTEREST_ON_SANCTION ? $sanctioned : $balance;

        // ---- the moratorium, measured from the first disbursement ----------
        $moratoriumFrom = isset($terms['moratorium_from']) && $terms['moratorium_from']
            ? $this->date($terms['moratorium_from'])
            : $startDate;
        $moratoriumEnd = null;
        $moratoriumRows = [];
        $servicedRows = [];
        $capitalisedInterest = 0.0;
        $balance = round($principal, 2);

        if ($moratoriumMonths > 0) {
            $cursor = $moratoriumFrom;
            for ($m = 1; $m <= $moratoriumMonths; $m++) {
                // E-Banker posts interest monthly whatever the instalment
                // frequency, so the moratorium is walked month by month.
                $next = $moratoriumFrom->addMonthsNoOverflow($m);
                $days = $cursor->diffInDays($next);
                $interest = round($interestBaseFor($balance) * $annualRate * $this->yearFraction($cursor, $next, $dayCount), 2);

                $capitalised = 0.0;
                $paid = 0.0;
                if ($moratoriumType === self::MORATORIUM_BOTH) {
                    // Decision D10: the balance rises each month by exactly
                    // the interest posted, so the posted figure compounds.
                    $capitalised = $interest;
                    $balance = round($balance + $interest, 2);
                    $capitalisedInterest = round($capitalisedInterest + $interest, 2);
                } else {
                    $paid = $interest;
                    $servicedRows[] = [
                        'due_date' => $next->toDateString(),
                        'days' => $days,
                        'opening_balance' => $balance,
                        'principal_due' => 0.0,
                        'interest_due' => $interest,
                        'closing_balance' => $balance,
                        'phase' => 'MORATORIUM_INTEREST',
                    ];
                }

                $moratoriumRows[] = [
                    'month' => $m,
                    'due_date' => $next->toDateString(),
                    'days' => $days,
                    'opening_balance' => $moratoriumRows === [] ? round($principal, 2) : $moratoriumRows[count($moratoriumRows) - 1]['closing_balance'],
                    'interest' => $interest,
                    'interest_paid' => $paid,
                    'interest_capitalised' => $capitalised,
                    'closing_balance' => $balance,
                ];
                $cursor = $next;
            }
            $moratoriumEnd = $cursor;
        }

        $capitalisedPrincipal = round($balance, 2);

        // ---- the instalments ----------------------------------------------
        // An instalment sized on the sanctioned amount retires the sanctioned
        // amount: that is what "Installment Based On = sanction" means in
        // E-Banker, and a facility that is never drawn in full is rescheduled
        // at its last drawdown rather than left with a short final payment.
        $amortisingOpening = round(
            ($instalmentBasis === self::INSTALMENT_ON_SANCTION ? $sanctioned : $principal) + $capitalisedInterest,
            2
        );
        $balance = $amortisingOpening;

        $accrualStart = $moratoriumEnd
            ?? (isset($terms['interest_start_date']) && $terms['interest_start_date']
                ? $this->date($terms['interest_start_date'])
                : $startDate);

        $intervalMonths = intdiv(12, $paymentsPerYear);
        $firstDue = isset($terms['first_due_date']) && $terms['first_due_date']
            ? $this->date($terms['first_due_date'])
            : $accrualStart->addMonthsNoOverflow($intervalMonths);
        if ($firstDue->lte($accrualStart)) {
            throw new InvalidArgumentException(sprintf(
                'The first instalment falls on %s, which is not after %s, the date interest starts running. A schedule cannot be generated from those dates.',
                $firstDue->toDateString(), $accrualStart->toDateString()
            ));
        }

        $periodRate = $annualRate / $paymentsPerYear;
        $instalment = $emiCalcType === self::EMI_EQUAL_PRINCIPAL
            ? round($amortisingOpening / $nPayments, 2)
            : $this->levelInstalment($amortisingOpening, $periodRate, $nPayments);

        $rows = $servicedRows;
        $previous = $accrualStart;
        for ($t = 1; $t <= $nPayments; $t++) {
            $due = $firstDue->addMonthsNoOverflow(($t - 1) * $intervalMonths);
            $days = $previous->diffInDays($due);
            // A whole payment period is charged at the nominal rate over the
            // payments a year, the basis the offer letters reproduce; anything
            // else is charged on its actual days under the day count in force.
            $wholePeriod = $previous->addMonthsNoOverflow($intervalMonths)->isSameDay($due);
            $fraction = $wholePeriod ? 1 / $paymentsPerYear : $this->yearFraction($previous, $due, $dayCount);

            $opening = $balance;
            $interest = round($interestBaseFor($balance) * $annualRate * $fraction, 2);

            if ($emiCalcType === self::EMI_EQUAL_PRINCIPAL) {
                $principalDue = $instalment;
            } else {
                $principalDue = round($instalment - $interest, 2);
                if ($principalDue < 0) {
                    throw new InvalidArgumentException(sprintf(
                        'Interest of %s is charged on the approved amount of %s while the instalment of %s is sized on the amount drawn of %s, so no principal would ever be repaid. Confirm Loan Interest Cal. Base On and Installment Based On for this facility with MAIIC.',
                        number_format($interest, 2), number_format($sanctioned, 2),
                        number_format($instalment, 2), number_format($principal, 2)
                    ));
                }
            }

            if ($t === $nPayments) {
                // Close out rounding drift: the final instalment retires the
                // exact remaining balance.
                $principalDue = round($balance, 2);
            }

            $balance = round($balance - $principalDue, 2);

            $rows[] = [
                'due_date' => $due->toDateString(),
                'days' => $days,
                'opening_balance' => $opening,
                'principal_due' => $principalDue,
                'interest_due' => $interest,
                'closing_balance' => $balance,
                'phase' => $wholePeriod ? 'INSTALMENT' : 'INSTALMENT_IRREGULAR_PERIOD',
            ];
            $previous = $due;
        }

        return [
            'instalment' => round($instalment, 2),
            'instalment_shape' => $emiCalcType === self::EMI_EQUAL_PRINCIPAL ? 'EQUAL_PRINCIPAL' : 'LEVEL_INSTALMENT',
            'emi_calc_type' => $emiCalcType,
            'interest_basis' => $interestBasis,
            'instalment_basis' => $instalmentBasis,
            'basis_sources' => [
                'emi_calc_type' => $emiSource,
                'interest_basis' => $interestSource,
                'instalment_basis' => $instalmentSource,
                'moratorium_type' => $moratoriumSource,
            ],
            'moratorium_type' => $moratoriumType,
            'moratorium_months' => $moratoriumMonths,
            'moratorium_from' => $moratoriumMonths > 0 ? $moratoriumFrom->toDateString() : null,
            'moratorium_end' => $moratoriumEnd?->toDateString(),
            'grace_period_months' => $graceMonths,
            'day_count' => $dayCount,
            'approved_amount' => round($sanctioned, 2),
            'drawn_amount' => round($principal, 2),
            'capitalised_principal' => $capitalisedPrincipal,
            'capitalised_interest' => $capitalisedInterest,
            'amortising_opening_balance' => $amortisingOpening,
            'moratorium_rows' => $moratoriumRows,
            'rows' => $rows,
            'totals' => [
                'principal' => round(array_sum(array_column($rows, 'principal_due')), 2),
                'interest' => round(array_sum(array_column($rows, 'interest_due')), 2),
            ],
        ];
    }

    /**
     * The generated schedule as the date-sensitive solver wants it: one dated
     * receipt per row, so an irregular first period is discounted by its
     * actual days instead of being treated as a whole period.
     *
     * @param  array  $result  the return of generate()
     * @return list<array{due_date:string, amount:float}>
     */
    public function cashFlowsForSolver(array $result): array
    {
        $flows = [];
        foreach ($result['rows'] as $row) {
            $flows[] = [
                'due_date' => $row['due_date'],
                'amount' => round((float) $row['principal_due'] + (float) $row['interest_due'], 2),
            ];
        }

        return $flows;
    }

    /**
     * Level annuity instalment: P.r / (1 - (1+r)^-n); straight-line when
     * the rate is zero.
     */
    private function levelInstalment(float $principal, float $periodRate, int $nPayments): float
    {
        if ($periodRate == 0.0) {
            return $principal / $nPayments;
        }

        return $principal * $periodRate / (1 - pow(1 + $periodRate, -$nPayments));
    }

    /**
     * The moratorium shape, from the contract's own Moratorium Type or, where
     * the contract row carries none, the scheme's default. E-Banker's words
     * are accepted as written ("Principle Only", "Both (Interest + Principle)")
     * and so are the engine's derived values.
     *
     * @return array{0:string, 1:string} the shape and where it came from
     */
    private function moratoriumType(array $terms, array $scheme, int $months): array
    {
        foreach ([['CONTRACT', $terms['moratorium_type'] ?? null], ['SCHEME', $scheme['default_moratorium_type'] ?? null]] as [$source, $stated]) {
            $shape = $this->readMoratoriumType($stated);
            if ($shape !== null) {
                return [$shape, $source];
            }
            if (trim((string) $stated) !== '') {
                throw new InvalidArgumentException(sprintf(
                    'MORATORIUM_TYPE_NOT_RECOGNISED: the moratorium type is given as "%s", which is neither of the two options E-Banker offers ("Principle Only" and "Both (Interest + Principle)"). No schedule is generated: a third shape is not built.',
                    trim((string) $stated)
                ));
            }
        }

        throw new InvalidArgumentException(sprintf(
            'MORATORIUM_TYPE_MISSING: a moratorium of %d month(s) is stated but no moratorium type is, on the contract or on its scheme. "Principle Only" (interest paid, balance unchanged) and "Both (Interest + Principle)" (nothing paid, interest added to the balance) give different cash flows, so the schedule is not generated and the shape is not guessed.',
            $months
        ));
    }

    /** E-Banker's own words, or the engine's derived value, or null. */
    private function readMoratoriumType($stated): ?string
    {
        $text = trim(preg_replace('/\s+/', ' ', strtoupper(preg_replace('/[^A-Za-z]+/', ' ', (string) $stated))));
        if ($text === '') {
            return null;
        }
        if (str_starts_with($text, 'BOTH')) {
            return self::MORATORIUM_BOTH;
        }
        if ((str_starts_with($text, 'PRINCIPLE') || str_starts_with($text, 'PRINCIPAL')) && str_contains($text, 'ONLY')) {
            return self::MORATORIUM_PRINCIPAL_ONLY;
        }

        return null;
    }

    /**
     * EMI Calc Type E (level instalment) or P (equal principal). F is refused.
     * Where neither the contract nor its scheme states a type, E is used and
     * said so in basis_sources: MAIIC confirmed on 10 September 2026 that
     * every facility on the book is EMI today (fact F7). Type of Repayment,
     * where it is supplied, must agree with the code.
     *
     * @return array{0:string, 1:string}
     */
    private function emiCalcType(array $terms, array $scheme): array
    {
        $fromRepaymentType = $this->readRepaymentType($terms['type_of_repayment'] ?? null);

        foreach ([['CONTRACT', $terms['emi_calc_type'] ?? null], ['SCHEME', $scheme['emi_calc_type'] ?? null]] as [$source, $stated]) {
            $code = strtoupper(trim((string) $stated));
            if ($code === '') {
                continue;
            }
            if ($code === self::EMI_FLEXIBLE) {
                throw new InvalidArgumentException(
                    'EMI_CALC_TYPE_F_NOT_BUILT: EMI Calc Type F (flexible) is not built. No MAIIC facility uses it and no example of the instalment pattern it produces exists, so the schedule is not generated. Send one flexible schedule as issued and it can be added.'
                );
            }
            if (! in_array($code, [self::EMI_LEVEL_INSTALMENT, self::EMI_EQUAL_PRINCIPAL], true)) {
                throw new InvalidArgumentException(sprintf(
                    'EMI_CALC_TYPE_NOT_RECOGNISED: the EMI calculation type is given as "%s". E-Banker offers E (level instalment), P (equal principal) and F (flexible, not built).',
                    trim((string) $stated)
                ));
            }
            if ($fromRepaymentType !== null && $fromRepaymentType !== $code) {
                throw new InvalidArgumentException(sprintf(
                    'REPAYMENT_SHAPE_DISAGREES: EMI Calc Type says %s but Type of Repayment says "%s", which is %s. The two must agree before a schedule is generated.',
                    $code, trim((string) $terms['type_of_repayment']), $fromRepaymentType
                ));
            }

            return [$code, $source];
        }

        if ($fromRepaymentType !== null) {
            return [$fromRepaymentType, 'TYPE_OF_REPAYMENT'];
        }

        return [self::EMI_LEVEL_INSTALMENT, 'ASSUMED_LEVEL_INSTALMENT_FACT_F7'];
    }

    /** EMI or equal principal from the Type of Repayment words. */
    private function readRepaymentType($stated): ?string
    {
        $text = strtoupper(trim(preg_replace('/\s+/', ' ', (string) $stated)));
        if ($text === '') {
            return null;
        }
        if (str_contains($text, 'EMI') || str_contains($text, 'LEVEL') || str_contains($text, 'EQUATED')) {
            return self::EMI_LEVEL_INSTALMENT;
        }
        if (str_contains($text, 'EQUAL PRINCIPAL') || str_contains($text, 'STRAIGHT')) {
            return self::EMI_EQUAL_PRINCIPAL;
        }

        throw new InvalidArgumentException(sprintf(
            'TYPE_OF_REPAYMENT_NOT_RECOGNISED: the type of repayment is given as "%s". The engine builds EMI (level instalment) and equal principal; a bullet or any other shape is not built.',
            trim((string) $stated)
        ));
    }

    /**
     * Loan Interest Cal. Base On: S charges interest on the approved amount,
     * B on the outstanding balance. N states no basis, so it is treated the
     * same as a blank and the scheme is asked next. A facility drawn in full
     * needs no decision, because the two bases are the same amount.
     *
     * @return array{0:string, 1:string}
     */
    private function interestBasis(array $terms, array $scheme, bool $partlyDrawn): array
    {
        foreach ([['CONTRACT', $terms['interest_calc_base'] ?? null], ['SCHEME', $scheme['interest_calc_base'] ?? null]] as [$source, $stated]) {
            $code = strtoupper(trim((string) $stated));
            if ($code === '' || $code === 'N') {
                continue;
            }
            if (in_array($code, ['S', 'SANCTION', 'SANCTIONED'], true)) {
                return [self::INTEREST_ON_SANCTION, $source];
            }
            if (in_array($code, ['B', 'BALANCE', 'OUTSTANDING'], true)) {
                return [self::INTEREST_ON_BALANCE, $source];
            }
            throw new InvalidArgumentException(sprintf(
                'INTEREST_BASIS_NOT_RECOGNISED: Loan Interest Cal. Base On is given as "%s". E-Banker offers N (none stated), S (the approved amount) and B (the outstanding balance).',
                trim((string) $stated)
            ));
        }

        if ($partlyDrawn) {
            throw new InvalidArgumentException(
                'INTEREST_BASIS_MISSING: the facility is drawn in part, and neither the contract nor its scheme says whether interest is charged on the approved amount or on the balance (Loan Interest Cal. Base On). On one MAIIC facility the two bases differ by about MWK 22 million a month, so the schedule is not generated and the basis is not assumed.'
            );
        }

        // Drawn in full: the approved amount and the balance start equal, so
        // the flag changes nothing and no decision has to be invented.
        return [self::INTEREST_ON_BALANCE, 'DRAWN_IN_FULL_BASES_AGREE'];
    }

    /**
     * Installment Based On: the sanctioned amount or the amount disbursed
     * (E-Banker p.22). Same rule as the interest basis: a facility drawn in
     * full needs no decision, a partly drawn one with no flag is refused.
     *
     * @return array{0:string, 1:string}
     */
    private function instalmentBasis(array $terms, array $scheme, bool $partlyDrawn): array
    {
        foreach ([['CONTRACT', $terms['installment_based_on'] ?? null], ['SCHEME', $scheme['installment_based_on'] ?? null]] as [$source, $stated]) {
            $text = strtoupper(trim((string) $stated));
            if ($text === '' || $text === 'N') {
                continue;
            }
            if (str_contains($text, 'SANCTION') || $text === 'S') {
                return [self::INSTALMENT_ON_SANCTION, $source];
            }
            if (str_contains($text, 'DISBURSE') || str_contains($text, 'DRAWN') || str_contains($text, 'BALANCE') || $text === 'D' || $text === 'B') {
                return [self::INSTALMENT_ON_DISBURSEMENT, $source];
            }
            throw new InvalidArgumentException(sprintf(
                'INSTALMENT_BASIS_NOT_RECOGNISED: Installment Based On is given as "%s". E-Banker sizes the instalment either on the sanctioned amount or on the amount disbursed.',
                trim((string) $stated)
            ));
        }

        if ($partlyDrawn) {
            throw new InvalidArgumentException(
                'INSTALMENT_BASIS_MISSING: the facility is drawn in part, and neither the contract nor its scheme says whether the instalment is sized on the sanctioned amount or on the amount disbursed (Installment Based On). The two give different instalments, so the schedule is not generated and the basis is not assumed.'
            );
        }

        return [self::INSTALMENT_ON_DISBURSEMENT, 'DRAWN_IN_FULL_BASES_AGREE'];
    }

    /**
     * The day count in force, from the Governance Centre key `day_count`
     * (decision D9, ACT/365 by default). A caller may state it instead, which
     * is how the pure unit tests run without a database; nothing here falls
     * back to a day count written in code.
     */
    private function dayCount(array $terms, CarbonImmutable $asOf): string
    {
        $stated = strtoupper(trim((string) ($terms['day_count'] ?? '')));

        if ($stated === '') {
            if ($this->governance === null) {
                throw new InvalidArgumentException(
                    'DAY_COUNT_UNAVAILABLE: the day count convention could not be read. Resolve this service through the container so it can read the Governance Centre setting "Day count", or state day_count in the terms.'
                );
            }
            $stated = strtoupper(trim($this->governance->get('day_count', $asOf)));
        }

        if (! in_array($stated, self::DAY_COUNTS, true)) {
            throw new InvalidArgumentException(sprintf(
                'DAY_COUNT_NOT_RECOGNISED: the day count "%s" is not one the engine applies. The Governance Centre offers %s.',
                $stated, implode(' and ', ['ACT/365', '30/360'])
            ));
        }

        return $stated;
    }

    /** The share of a year between two dates under the day count in force. */
    private function yearFraction(CarbonImmutable $from, CarbonImmutable $to, string $dayCount): float
    {
        if ($dayCount === '30/360') {
            $fromDay = min(30, $from->day);
            $toDay = min(30, $to->day);

            return ((($to->year - $from->year) * 360) + (($to->month - $from->month) * 30) + ($toDay - $fromDay)) / 360;
        }

        return $from->diffInDays($to) / ($dayCount === 'ACT/360' ? 360 : 365);
    }

    private function date($value): CarbonImmutable
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::parse($value->toDateString())->startOfDay();
        }

        return CarbonImmutable::parse((string) $value)->startOfDay();
    }
}
