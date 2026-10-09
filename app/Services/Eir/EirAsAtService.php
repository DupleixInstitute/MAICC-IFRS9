<?php

namespace App\Services\Eir;

use App\Services\Ebanker\LandingZoneReader;
use App\Services\Ebanker\LoanBookBuildService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The EIR computation for any loan as at any date the user names (spec v4
 * section 6.11), and the same for the whole book.
 *
 * A month-end reads the roll-forward row the revenue run locked for that
 * month. A date inside a month starts from the last closing amortised cost
 * the engine holds, rolls any whole months between at the monthly EIR less
 * the cash the ledger shows received in each, and accrues the part month
 * the way section 3 says E-Banker does: simple interest at the contractual
 * rate on the prior month-end balance for the actual days over 365 (system
 * audit of 9 October 2026, finding M13: the part month had compounded the
 * EIR instead). For a take-on loan with no roll-forward yet, the opening is
 * the take-on basis of section 6.9 read from contract_takeon: the
 * recomputed amortised cost at 31 July 2024, or the take-on balance. The
 * gross carrying amount at the date is the ledger's running balance. The
 * contractual interest is what the ledger posted (types 303 and 120). A
 * date in a locked period reproduces the locked figures, because the
 * roll-forward rows are what was locked; a date after the last loaded
 * posting is refused with that date named, never estimated. Every figure is
 * returned with the inputs that produced it.
 */
class EirAsAtService
{
    public const TAKEON_DATE = '2024-07-31';

    public function __construct(private LandingZoneReader $zone)
    {
    }

    public function contract(string $contractId, string $date): array
    {
        $asAt = $this->date($date);
        $c = DB::table('contract_eir')->where('contract_id', ltrim($contractId, '0'))->first();
        if ($c === null) {
            throw new RuntimeException("No contract {$contractId} in the EIR register.");
        }
        $account = $this->account($c->contract_id);
        $period = $asAt->format('Y-m');
        $monthEnd = $asAt->endOfMonth()->toDateString();
        $isMonthEnd = $asAt->toDateString() === $monthEnd;
        $priorEnd = $asAt->startOfMonth()->subDay();
        $lock = DB::table('reporting_period_locks')->where('reporting_period', $period)->first();

        $rows = DB::table('eir_amortisation')->where('contract_id', $c->contract_id)->orderBy('reporting_period')->get();
        $thisMonth = $rows->firstWhere('reporting_period', $period);
        $prior = $rows->where('reporting_period', '<=', $priorEnd->format('Y-m'))->last();
        $eir = $c->eir_effective_annual !== null ? (float) $c->eir_effective_annual : null;

        $ledger = $this->postings($account, $asAt->toDateString());
        $gross = round(-array_sum(array_map(fn ($p) => $p['amount'], $ledger)), 2);
        $isInterest = fn (array $p) => in_array($p['type'], LoanBookBuildService::TYPE_INTEREST, true);
        $cashBetween = function (string $after, string $to) use ($ledger, $isInterest): float {
            $cash = 0.0;
            foreach ($ledger as $p) {
                if ($p['date'] > $after && $p['date'] <= $to && $p['amount'] > 0 && ! $isInterest($p)) {
                    $cash += $p['amount'];
                }
            }

            return $cash;
        };
        $cashInMonth = $cashBetween($priorEnd->toDateString(), $asAt->toDateString());
        $postedInMonth = 0.0;
        foreach ($ledger as $p) {
            if ($p['date'] > $priorEnd->toDateString() && $isInterest($p)) {
                $postedInMonth -= $p['amount'];
            }
        }

        // the opening the computation starts from: the last roll-forward row before the
        // month, or, for a take-on loan the revenue run has not reached, the take-on
        // basis of 6.9 (finding M13: the basis was not read)
        $takeon = $this->takeon($account, $c->contract_id);
        $opening = null; $openingPeriod = null; $openingBasis = null;
        if ($prior !== null) {
            $opening = (float) $prior->closing_gross;
            $openingPeriod = $prior->reporting_period;
            $openingBasis = 'roll-forward row for ' . $prior->reporting_period;
        } elseif ($takeon !== null && $asAt->toDateString() >= self::TAKEON_DATE) {
            [$opening, $openingBasis] = $this->takeonOpening($takeon);
            $openingPeriod = substr(self::TAKEON_DATE, 0, 7);
        }

        // the amortised cost and the EIR interest at the date
        $rolled = [];
        $rate = $c->contractual_rate !== null ? (float) $c->contractual_rate : null;
        $rate = $rate !== null && $rate > 1 ? $rate / 100 : $rate;   // the register holds a fraction; a percent is normalised
        if ($isMonthEnd && $thisMonth !== null) {
            $amortised = (float) $thisMonth->closing_gross;
            $interestPeriod = (float) $thisMonth->interest_accrued;
            $basis = 'locked roll-forward row for ' . $period;
        } elseif ($opening !== null && $eir !== null) {
            // whole months between the opening and the prior month-end, at the monthly EIR less the cash received in each
            $monthly = (1 + $eir) ** (1 / 12) - 1;
            $balance = $opening;
            $cursor = CarbonImmutable::parse($openingPeriod . '-01')->endOfMonth();
            while ($cursor->toDateString() < $priorEnd->toDateString()) {
                $next = $cursor->addDay()->endOfMonth();
                $interest = round($balance * $monthly, 2);
                $cash = $cashBetween($cursor->toDateString(), $next->toDateString());
                $balance = round($balance + $interest - $cash, 2);
                $rolled[] = ['period' => $next->format('Y-m'), 'opening' => round($balance - $interest + $cash, 2), 'interest' => $interest, 'cash' => $cash, 'closing' => $balance, 'basis' => 'rolled at the monthly EIR; no revenue run for this month'];
                $cursor = $next;
            }
            if ($isMonthEnd) {
                // a month-end the revenue run has not reached: a whole month at the monthly EIR
                $interestPeriod = round($balance * $monthly, 2);
                $basis = sprintf('prior month-end closing %s for a whole month at the monthly EIR %s (no roll-forward row yet), less cash received %s', number_format($balance, 2), number_format($monthly * 100, 4) . '%', number_format($cashInMonth, 2));
            } else {
                // the part month: section 3, simple interest at the contractual rate on the prior month-end balance, actual days over 365
                $days = $priorEnd->diffInDays($asAt);
                if ($rate !== null) {
                    $interestPeriod = round($balance * $rate * $days / 365, 2);
                    $basis = sprintf('prior month-end closing %s at the contractual rate %s simple on %d/365 days (section 3), less cash received %s', number_format($balance, 2), number_format($rate * 100, 4) . '%', $days, number_format($cashInMonth, 2));
                } else {
                    $interestPeriod = round($balance * ((1 + $eir) ** ($days / 365) - 1), 2);
                    $basis = sprintf('prior month-end closing %s on %d/365 days at the EIR daily equivalent, since the contract carries no contractual rate, less cash received %s', number_format($balance, 2), $days, number_format($cashInMonth, 2));
                }
            }
            $amortised = round($balance + $interestPeriod - $cashInMonth, 2);
            if ($rolled !== []) {
                $basis .= sprintf('; %d month(s) rolled from the %s', count($rolled), $openingBasis);
            } elseif ($prior === null) {
                $basis .= '; opening from the ' . $openingBasis;
            }
        } else {
            $amortised = null;
            $interestPeriod = null;
            $basis = $eir === null ? 'no locked EIR' : 'no roll-forward before this date and no take-on basis: the revenue run has not reached it';
        }

        // year to date, the rolled months counted with the roll-forward rows
        $year = $asAt->format('Y');
        $ytdEir = (float) $rows->filter(fn ($r) => str_starts_with($r->reporting_period, $year) && $r->reporting_period < $period)->sum('interest_accrued')
            + array_sum(array_map(fn ($r) => str_starts_with($r['period'], $year) ? $r['interest'] : 0.0, $rolled)) + ($interestPeriod ?? 0.0);
        $ytdPosted = 0.0;
        foreach ($ledger as $p) {
            if (str_starts_with($p['date'], $year) && $isInterest($p)) {
                $ytdPosted -= $p['amount'];
            }
        }
        // cumulative since the first roll-forward month (or the take-on, where that is the opening)
        $first = $rows->first()?->reporting_period ?? ($prior === null && $opening !== null ? CarbonImmutable::parse($openingPeriod . '-01')->addMonth()->format('Y-m') : null);
        $cumEir = (float) $rows->filter(fn ($r) => $r->reporting_period < $period)->sum('interest_accrued') + array_sum(array_column($rolled, 'interest')) + ($interestPeriod ?? 0.0);
        $cumPosted = 0.0;
        foreach ($ledger as $p) {
            if ($first !== null && substr($p['date'], 0, 7) >= $first && $isInterest($p)) {
                $cumPosted -= $p['amount'];
            }
        }

        // remaining expected cash flows: the schedule version in force on the date
        $version = DB::table('contract_cashflow_schedule')->where('contract_id', $c->contract_id)->where('effective_from', '<=', $asAt->toDateString())->max('schedule_version')
            ?? DB::table('contract_cashflow_schedule')->where('contract_id', $c->contract_id)->min('schedule_version');
        $remaining = DB::table('contract_cashflow_schedule')->where('contract_id', $c->contract_id)->where('schedule_version', $version)->where('due_date', '>', $asAt->toDateString())
            ->orderBy('due_date')->get(['due_date', 'principal_due', 'interest_due', 'fee_due'])->map(fn ($r) => ['due_date' => $r->due_date, 'principal' => (float) $r->principal_due, 'interest' => (float) $r->interest_due, 'fee' => (float) $r->fee_due])->all();

        $modifications = DB::table('rate_reset_events')->where('contract_id', $c->contract_id)->where('reset_date', '<=', $asAt->toDateString())->orderBy('reset_date')
            ->get(['reset_date', 'old_reference_rate', 'new_reference_rate', 'new_schedule_version'])->map(fn ($r) => (array) $r)->all();
        $history = DB::getSchemaBuilder()->hasTable('eir_calculation_history')
            ? DB::table('eir_calculation_history')->where('contract_id', $c->contract_id)->orderBy('id')->get(['eir_effective_annual', 'created_at'])->map(fn ($r) => (array) $r)->all() : [];

        return [
            'contract_id' => $c->contract_id, 'account' => $account, 'customer_name' => $c->customer_name, 'product_type' => $c->product_type, 'gl_account_code' => $c->gl_account_code,
            'as_at' => $asAt->toDateString(), 'period' => $period, 'is_month_end' => $isMonthEnd,
            'locked_period' => $lock !== null, 'locked_at' => $lock?->locked_at, 'locked_reason' => $lock?->reason,
            'eir' => ['effective_annual' => $eir, 'nominal_annual' => $c->eir_nominal_annual !== null ? (float) $c->eir_nominal_annual : null, 'solved_at' => $c->calculated_at, 'locked_at' => $c->locked_at,
                'status' => $c->calculation_status, 'contractual_rate' => $c->contractual_rate !== null ? (float) $c->contractual_rate : null, 'rate_type' => $c->rate_type, 'schedule_version' => $version],
            'amortised_cost' => $amortised, 'amortised_cost_basis' => $basis,
            'opening' => ['amount' => $opening, 'period' => $openingPeriod, 'basis' => $openingBasis],
            'takeon' => $takeon === null ? null : ['basis' => $takeon->basis, 'takeon_balance' => $takeon->takeon_balance !== null ? (float) $takeon->takeon_balance : null,
                'recomputed_amortised_cost' => $takeon->recomputed_amortised_cost !== null ? (float) $takeon->recomputed_amortised_cost : null, 'recomputed_eir' => $takeon->recomputed_eir !== null ? (float) $takeon->recomputed_eir : null,
                'origination_date' => $takeon->origination_date, 'setting_value' => $takeon->setting_value],
            'gross_carrying_amount' => $gross, 'gross_basis' => 'ledger running balance of ' . count($ledger) . ' postings on or before the date',
            'interest' => ['eir_period_to_date' => $interestPeriod, 'contractual_period_to_date' => round($postedInMonth, 2),
                'eir_year_to_date' => round($ytdEir, 2), 'contractual_year_to_date' => round($ytdPosted, 2), 'difference_year_to_date' => round($ytdEir - $ytdPosted, 2),
                'eir_cumulative' => round($cumEir, 2), 'contractual_cumulative' => round($cumPosted, 2), 'difference_cumulative' => round($cumEir - $cumPosted, 2), 'cumulative_from' => $first],
            'roll_forward' => array_merge(
                $rows->map(fn ($r) => ['period' => $r->reporting_period, 'opening' => (float) $r->opening_gross, 'interest' => (float) $r->interest_accrued, 'cash' => (float) $r->cash_received, 'closing' => (float) $r->closing_gross, 'basis' => $r->interest_basis])->values()->all(),
                $rolled,
            ),
            'remaining_cash_flows' => $remaining, 'modifications' => $modifications, 'eir_history' => $history,
            'inputs' => ['ledger_last_date' => $this->zone->lastLedgerDate(), 'postings_read' => count($ledger), 'roll_forward_rows' => $rows->count(), 'months_rolled' => count($rolled),
                'governance_snapshot' => $lock?->settings_snapshot !== null ? 'locked snapshot' : 'values in force on the date'],
        ];
    }

    /** The take-on row for a contract, by account or by contract id; null for a loan E-Banker originated. */
    private function takeon(string $account, string $contractId): ?object
    {
        return DB::table('contract_takeon')->where('account', $account)->orWhere('contract_id', $contractId)->first();
    }

    /**
     * The opening amortised cost a take-on loan starts from, under the basis
     * it was built on (6.9): the recomputed amortised cost at 31 July 2024
     * where the build solved it, the take-on balance otherwise. The take-on
     * balance is E-Banker's carrying amount after the three opening legs.
     *
     * @return array{0:?float,1:string}
     */
    private function takeonOpening(object $t): array
    {
        if ($t->basis === 'RECOMPUTED' && $t->recomputed_amortised_cost !== null) {
            return [(float) $t->recomputed_amortised_cost, 'take-on basis RECOMPUTED: amortised cost ' . number_format((float) $t->recomputed_amortised_cost, 2) . ' rolled from origination to 31 Jul 2024 at the EIR solved on the workbook schedule'];
        }
        $balance = $t->takeon_balance !== null ? (float) $t->takeon_balance : round((float) $t->takeon_posting + (float) $t->takeon_opening_interest - (float) ($t->takeon_opening_recovery ?? 0), 2);

        return [$balance, 'take-on basis ' . $t->basis . ': E-Banker take-on balance ' . number_format($balance, 2) . ' at 31 Jul 2024 (principal plus opening interest less opening recovery)'];
    }

    /** The whole book at a date: EIR interest, contractual interest and the difference for the year to the date, by product, by GL and in total. */
    public function book(string $date): array
    {
        $asAt = $this->date($date);
        $year = $asAt->format('Y');
        $period = $asAt->format('Y-m');
        $contracts = DB::table('contract_eir')->whereNotNull('locked_at')->get(['contract_id', 'product_type', 'gl_account_code', 'customer_name']);
        $lines = [];
        foreach ($contracts as $c) {
            $v = $this->contract($c->contract_id, $asAt->toDateString());
            $lines[] = ['contract_id' => $c->contract_id, 'customer_name' => $c->customer_name, 'product' => $c->product_type ?? '-', 'gl' => $c->gl_account_code ?? '-',
                'eir_ytd' => $v['interest']['eir_year_to_date'], 'contractual_ytd' => $v['interest']['contractual_year_to_date'], 'difference' => $v['interest']['difference_year_to_date'], 'amortised_cost' => $v['amortised_cost'], 'gross' => $v['gross_carrying_amount']];
        }
        $group = function (string $key) use ($lines) {
            $out = [];
            foreach ($lines as $l) {
                $k = $l[$key];
                $out[$k] ??= ['key' => $k, 'contracts' => 0, 'eir_ytd' => 0.0, 'contractual_ytd' => 0.0, 'difference' => 0.0, 'amortised_cost' => 0.0, 'gross' => 0.0];
                $out[$k]['contracts']++;
                foreach (['eir_ytd', 'contractual_ytd', 'difference', 'amortised_cost', 'gross'] as $f) {
                    $out[$k][$f] = round($out[$k][$f] + (float) $l[$f], 2);
                }
            }
            ksort($out);

            return array_values($out);
        };
        $total = ['contracts' => count($lines)];
        foreach (['eir_ytd', 'contractual_ytd', 'difference', 'amortised_cost', 'gross'] as $f) {
            $total[$f] = round(array_sum(array_column($lines, $f)), 2);
        }

        return ['as_at' => $asAt->toDateString(), 'year' => $year, 'period' => $period, 'by_product' => $group('product'), 'by_gl' => $group('gl'), 'total' => $total, 'contracts' => $lines,
            'note' => 'Contracts with a locked EIR only; the revenue shift for the year to the date is the difference column (section 6.11).'];
    }

    private function date(string $date): CarbonImmutable
    {
        try {
            $d = CarbonImmutable::createFromFormat('!Y-m-d', $date);
        } catch (\Throwable) {
            $d = false;
        }
        if ($d === false || $d->format('Y-m-d') !== $date) {
            throw new RuntimeException("Give the date as YYYY-MM-DD, got '{$date}'.");
        }
        $last = $this->zone->lastLedgerDate();
        if ($last !== null && $date > $last) {
            throw new RuntimeException("The last loaded posting is dated {$last}; a view as at {$date} is refused, never estimated.");
        }

        return $d;
    }

    /** The E-Banker account number for a contract id (ids are stored without leading zeros). */
    private function account(string $contractId): string
    {
        $acc = DB::table('loan_books')->where('contract_id', $contractId)->whereNotNull('external_identity_id')->where('external_identity_id', '!=', 'TBA')->value('external_identity_id');

        return $acc ?? str_pad($contractId, 15, '0', STR_PAD_LEFT);
    }

    /** The ledger read once per date, since the book view asks for every contract. */
    private array $ledgerMemo = [];

    /** @return list<array{date:string,type:string,amount:float}> */
    private function postings(string $account, string $toDate): array
    {
        $this->ledgerMemo[$toDate] ??= $this->zone->ledgerByAccount($toDate);
        $out = [];
        foreach ($this->ledgerMemo[$toDate][$account] ?? [] as $p) {
            $out[] = ['date' => $p['row_date'], 'type' => (string) ($p['payload']['TRANTYPE'] ?? ''), 'amount' => (float) str_replace(',', '', (string) ($p['payload']['TRANSAMT'] ?? 0))];
        }

        return $out;
    }
}
