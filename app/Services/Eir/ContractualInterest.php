<?php

namespace App\Services\Eir;

/**
 * One month's contractual interest for one loan, with the whole basis it was
 * worked out on (spec v3 section 7.7).
 *
 * The figure on its own is not reviewable. A reader checking the
 * reconciliation has to see which balance was charged, which rate, how many
 * days, where each of those came from, and whether the month was a special
 * one, so the object carries all of it beside the amount.
 *
 * When an input is missing there is no amount at all. The object is then
 * marked unavailable and carries a named reason and a sentence saying what is
 * missing: the engine never fills a gap with an assumption.
 */
final class ContractualInterest
{
    /** No loan-book row or contract row gives the balance the month opened on. */
    public const NO_OPENING_BALANCE = 'NO_OPENING_BALANCE';

    /** Neither the loan book for the month nor the contract master holds a rate. */
    public const NO_RATE = 'NO_RATE';

    /** Nothing says when the money was first paid out, so a part month cannot be measured. */
    public const NO_FIRST_DISBURSEMENT_DATE = 'NO_FIRST_DISBURSEMENT_DATE';

    /** The month asked for is earlier than the month the loan was paid out in. */
    public const BEFORE_FIRST_DISBURSEMENT = 'BEFORE_FIRST_DISBURSEMENT';

    /** The month of the first disbursement, but no amount is recorded as drawn. */
    public const NO_DRAWN_AMOUNT = 'NO_DRAWN_AMOUNT';

    /** There is no contract master row for the account. */
    public const NO_CONTRACT = 'NO_CONTRACT';

    /** The reporting period could not be read as a month. */
    public const PERIOD_NOT_READABLE = 'PERIOD_NOT_READABLE';

    /**
     * The prior month-end balance is missing and the governed moratorium
     * convention is not monthly capitalisation, so it cannot be carried
     * forward either.
     */
    public const CAPITALISATION_NOT_MONTHLY = 'CAPITALISATION_NOT_MONTHLY';

    private function __construct(
        public readonly string $contractId,
        public readonly ?string $period,
        public readonly bool $available,
        public readonly ?string $reason,
        public readonly ?string $message,
        public readonly ?float $openingBalance,
        public readonly ?string $openingBalanceSource,
        public readonly ?float $rate,
        public readonly ?string $rateSource,
        public readonly ?string $dayCount,
        public readonly ?int $daysCharged,
        public readonly ?int $daysInPeriod,
        public readonly ?int $denominator,
        public readonly ?float $interest,
        public readonly bool $firstDisbursementMonth,
        public readonly bool $capitalisingMoratoriumMonth,
        public readonly ?string $firstDisbursementDate,
        public readonly ?string $firstDisbursementDateSource,
    ) {
    }

    public static function calculated(
        string $contractId,
        string $period,
        float $openingBalance,
        string $openingBalanceSource,
        float $rate,
        string $rateSource,
        string $dayCount,
        int $daysCharged,
        int $daysInPeriod,
        int $denominator,
        float $interest,
        bool $firstDisbursementMonth,
        bool $capitalisingMoratoriumMonth,
        ?string $firstDisbursementDate,
        ?string $firstDisbursementDateSource,
    ): self {
        return new self($contractId, $period, true, null, null, $openingBalance, $openingBalanceSource,
            $rate, $rateSource, $dayCount, $daysCharged, $daysInPeriod, $denominator, $interest,
            $firstDisbursementMonth, $capitalisingMoratoriumMonth, $firstDisbursementDate, $firstDisbursementDateSource);
    }

    /** No figure, a named reason and a sentence a reader can act on. */
    public static function unavailable(string $contractId, ?string $period, string $reason, string $message): self
    {
        return new self($contractId, $period, false, $reason, $message, null, null, null, null,
            null, null, null, null, null, false, false, null, null);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'contract_id' => $this->contractId,
            'reporting_period' => $this->period,
            'available' => $this->available,
            'reason' => $this->reason,
            'message' => $this->message,
            'opening_balance' => $this->openingBalance === null ? null : round($this->openingBalance, 2),
            'opening_balance_source' => $this->openingBalanceSource,
            'rate' => $this->rate,
            'rate_source' => $this->rateSource,
            'day_count' => $this->dayCount,
            'days_charged' => $this->daysCharged,
            'days_in_period' => $this->daysInPeriod,
            'denominator' => $this->denominator,
            'interest' => $this->interest,
            'first_disbursement_month' => $this->firstDisbursementMonth,
            'capitalising_moratorium_month' => $this->capitalisingMoratoriumMonth,
            'first_disbursement_date' => $this->firstDisbursementDate,
            'first_disbursement_date_source' => $this->firstDisbursementDateSource,
        ];
    }
}
