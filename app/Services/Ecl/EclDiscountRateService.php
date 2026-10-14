<?php

namespace App\Services\Ecl;

use App\Models\ContractEir;
use App\Services\Eir\EirRevenueService;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the rate at which expected credit losses are discounted.
 *
 * IFRS 9 §5.5.17(b) requires ECL to be discounted at the effective interest
 * rate determined at initial recognition, not at a funding or risk-free rate.
 * A facility yielding 30% and one yielding 10% do not carry the same present
 * value of a loss two years out, and using one rate for both moves the
 * allowance in a direction nobody can explain.
 *
 * Every resolved rate carries the basis it came from, following the same
 * convention as `rate_source`, `schedule_source` and `cash_source`: a mixed
 * run has to be explainable line by line, and an approximation has to be
 * visible as one.
 *
 * There is deliberately no default rate. The previous behaviour fell back to
 * a hardcoded 10% whenever a rate could not be found, which silently produced
 * an allowance for a facility whose discounting basis was unknown. A payment
 * whose rate cannot be resolved is now returned as unresolved and excluded by
 * the caller, named.
 *
 * A floating-rate facility is discounted at its current EIR (IFRS 9 B5.5.44),
 * the EIR in force for the period as the revenue engine re-estimates it from
 * the rate charged (decision D38, EirRevenueService::currentFloatingEir), the
 * same rate TimePhasedEclService discounts the allowance at. Where it cannot
 * be determined the payment is unresolved with the reason; the original EIR
 * is never used in its place (it was, labelled EIR_ORIGINAL_FLOATING_PROXY,
 * until 14 October 2026).
 */
class EclDiscountRateService
{
    /** Requested bases, matching the `discount_rate_source` column. */
    public const SOURCE_EIR = 'eir';
    public const SOURCE_LOAN_BOOK = 'loan_book';
    public const SOURCE_MANUAL = 'manual';

    /** The basis actually applied to a given payment. */
    public const APPLIED_EIR_ORIGINAL = 'EIR_ORIGINAL';
    public const APPLIED_EIR_CURRENT_FLOATING = 'EIR_CURRENT_FLOATING';
    public const APPLIED_LOAN_BOOK = 'LOAN_BOOK_CONTRACTUAL';
    public const APPLIED_MANUAL = 'MANUAL';

    /**
     * @param list<array{contract_id:string,period:string}> $pairs
     * @return array{rates:array<string,array{rate:float,applied_source:string}>,unresolved:array<string,string>}
     *         Both maps are keyed by "{contract_id}|{period}".
     */
    public function resolve(array $pairs, string $requestedSource, ?float $manualRate = null): array
    {
        $normalised = [];
        foreach ($pairs as $pair) {
            $contractId = (string) $pair['contract_id'];
            $period = $this->periodKey((string) ($pair['period'] ?? ''));
            $normalised[$contractId . '|' . $period] = ['contract_id' => $contractId, 'period' => $period];
        }

        return match ($requestedSource) {
            self::SOURCE_MANUAL => $this->fromManual($normalised, $manualRate),
            self::SOURCE_EIR => $this->fromLockedEir($normalised),
            self::SOURCE_LOAN_BOOK => $this->fromLoanBook($normalised),
            default => $this->allUnresolved($normalised, "Unknown discount rate source '{$requestedSource}'."),
        };
    }

    private function fromManual(array $pairs, ?float $manualRate): array
    {
        if ($manualRate === null || $manualRate <= 0) {
            return $this->allUnresolved($pairs, 'A manual discount rate was requested but none was supplied.');
        }

        $rates = [];
        foreach ($pairs as $key => $pair) {
            $rates[$key] = ['rate' => $manualRate, 'applied_source' => self::APPLIED_MANUAL];
        }

        return ['rates' => $rates, 'unresolved' => []];
    }

    /**
     * The locked original EIR of a fixed-rate facility; the current EIR of a floating-rate
     * facility for the period (B5.5.44), from the month's charged rate on the loan book.
     */
    private function fromLockedEir(array $pairs): array
    {
        $contractIds = array_values(array_unique(array_column($pairs, 'contract_id')));
        $contracts = ContractEir::whereIn('contract_id', $contractIds)
            ->whereNotNull('locked_at')->whereNotNull('eir_effective_annual')
            ->get()->keyBy('contract_id');
        $floating = $contracts->filter(fn ($c) => strtoupper((string) $c->rate_type) === 'FLOATING');
        $charged = [];
        if ($floating->isNotEmpty()) {
            foreach (DB::table('loan_books')->whereIn('contract_id', $floating->keys()->all())->whereNotNull('interest_rate')
                ->orderBy('id')->get(['contract_id', 'reporting_period', 'interest_rate']) as $row) {
                $charged[$row->contract_id . '|' . $this->periodKey((string) $row->reporting_period)] = $row->interest_rate;
            }
        }
        $revenue = $floating->isNotEmpty() ? app(EirRevenueService::class) : null;

        $rates = [];
        $unresolved = [];
        foreach ($pairs as $key => $pair) {
            $contract = $contracts->get($pair['contract_id']);
            if (! $contract) {
                $unresolved[$key] = 'No approved and locked original EIR exists for this contract.';
                continue;
            }

            $rate = (float) $contract->eir_effective_annual;
            if ($rate <= -1.0) {
                $unresolved[$key] = 'The locked EIR is not a usable discount rate.';
                continue;
            }

            if ($floating->has($pair['contract_id'])) {
                try {
                    $current = $revenue->currentFloatingEir($contract, (object) ['interest_rate' => $charged[$key] ?? null], $pair['period']);
                } catch (\InvalidArgumentException $e) {
                    $unresolved[$key] = 'Floating-rate loan: ' . $e->getMessage();
                    continue;
                }
                if ($current === null) {
                    $unresolved[$key] = 'Floating-rate loan: its current EIR for ' . $pair['period'] . ' cannot be determined (no loan-book rate charged for the month, or no rate the locked schedule was built at), and the original EIR is not used in its place (IFRS 9 B5.5.44).';
                    continue;
                }
                if ($current <= 0) {
                    $unresolved[$key] = 'The current EIR of the floating-rate loan is not positive (' . $current . ').';
                    continue;
                }
                $rates[$key] = ['rate' => $current, 'applied_source' => self::APPLIED_EIR_CURRENT_FLOATING];
                continue;
            }

            $rates[$key] = ['rate' => $rate, 'applied_source' => self::APPLIED_EIR_ORIGINAL];
        }

        return ['rates' => $rates, 'unresolved' => $unresolved];
    }

    /**
     * The contractual rate off the monthly tape. Stored as a percentage
     * (34.19 means 34.19%), unlike every other rate in the system, so it is
     * converted here: discounting at 3,419% would reduce a payment one year
     * out to under 3% of its value.
     */
    private function fromLoanBook(array $pairs): array
    {
        $contractIds = array_values(array_unique(array_column($pairs, 'contract_id')));
        $rows = DB::table('loan_books')->whereIn('contract_id', $contractIds)
            ->whereNotNull('interest_rate')
            ->get(['contract_id', 'reporting_period', 'interest_rate']);

        $lookup = [];
        foreach ($rows as $row) {
            $lookup[$row->contract_id . '|' . $this->periodKey((string) $row->reporting_period)] = (float) $row->interest_rate;
        }

        $rates = [];
        $unresolved = [];
        foreach ($pairs as $key => $pair) {
            $percent = $lookup[$key] ?? null;
            if ($percent === null) {
                $unresolved[$key] = 'No loan-book rate exists for this contract in this period.';
                continue;
            }
            if ($percent <= 0) {
                $unresolved[$key] = 'The loan-book contractual rate is zero or negative.';
                continue;
            }

            $rates[$key] = ['rate' => $percent / 100, 'applied_source' => self::APPLIED_LOAN_BOOK];
        }

        return ['rates' => $rates, 'unresolved' => $unresolved];
    }

    private function allUnresolved(array $pairs, string $reason): array
    {
        return ['rates' => [], 'unresolved' => array_map(fn () => $reason, $pairs)];
    }

    public function periodKey(string $period): string
    {
        return preg_match('/^(\d{4})-(\d{2})/', trim($period), $m) === 1 ? $m[1] . '-' . $m[2] : trim($period);
    }
}
