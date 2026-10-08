<?php

namespace App\Services\Eir;

use App\Models\ContractEir;
use App\Models\EirAmortisation;
use App\Models\EirAmortisationHistory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/** Builds one monthly amortised-cost roll-forward from the locked original EIR. */
class EirRevenueService
{
    private readonly GovernanceService $governance;

    public function __construct(?GovernanceService $governance = null)
    {
        $this->governance = $governance ?? app(GovernanceService::class);
    }

    /**
     * Delivered transaction types that represent cash collected from the
     * customer. Amounts are read from `total_amount` rather than summed from
     * the components: the two agree on all but the adjustment rows, which
     * carry a total with every component left at zero.
     */
    private const COLLECTION_TYPES = ['Principal+Interest', 'Fee']; // 'Interest' removed: an interest charge is not cash collected (spec 7.4, audit M15)

    /** Derecognition without cash: a write-off (against the allowance) or a settlement in kind. Reduces the gross; never cash received (audit H1). */
    private const DERECOGNITION_TYPES = ['Write-off', 'Settlement in kind'];

    /** Cash advanced to the customer — the opposite direction, never a receipt. */
    private const ADVANCE_TYPES = ['Disbursement'];

    /**
     * @param bool $recalculate Supersede an existing row instead of leaving it.
     * @return array{contract_id:string,reporting_period:string,status:string,interest_accrued?:float,closing_gross?:float,cash_source?:string,unclassified_cash?:float,superseded?:int,error?:string}
     */
    public function run(string $contractId, string $period, bool $recalculate = false, ?int $userId = null, ?string $reason = null): array
    {
        $period = $this->normalisePeriod($period);
        try {
            \App\Support\ReportingPeriodLock::assertOpen($period, 'the EIR roll-forward');
        } catch (\App\Support\LockedPeriodException $e) {
            return ['contract_id' => $contractId, 'reporting_period' => $period, 'status' => 'BLOCKED', 'error' => $e->getMessage()];
        }
        if ($recalculate && trim((string) $reason) === '') {
            return ['contract_id' => $contractId, 'reporting_period' => $period, 'status' => 'BLOCKED',
                'error' => 'A recalculation must state a reason.'];
        }

        try {
            return DB::transaction(function () use ($contractId, $period, $recalculate, $userId, $reason) {
                $contract = ContractEir::where('contract_id', $contractId)->lockForUpdate()->firstOrFail();
                if ($contract->locked_at === null || $contract->eir_effective_annual === null) {
                    throw new RuntimeException('The original EIR must be approved and locked before revenue can be run.');
                }

                $superseded = 0;
                $existing = EirAmortisation::where('contract_id', $contractId)->where('reporting_period', $period)->first();
                if ($existing && ! $recalculate) {
                    return $this->result($existing, 'UNCHANGED');
                }
                if ($existing) {
                    $superseded = $this->supersede($contractId, $period, $userId, (string) $reason);
                }

                $previous = EirAmortisation::where('contract_id', $contractId)
                    ->where('reporting_period', '<', $period)->orderByDesc('reporting_period')->first();
                $opening = $previous ? (float) $previous->closing_gross : $this->initialOpening($contract, $period);
                $loan = $this->loanSnapshot($contractId, $period);
                if (! $loan) throw new RuntimeException("No loan-book snapshot exists for {$period}.");

                $stage = $this->stage($loan);
                if (! in_array($stage, [1, 2, 3], true)) throw new RuntimeException("The IFRS 9 stage for {$period} is missing or invalid.");
                $allowance = $this->openingAllowance($contractId, $period, $loan);
                $monthlyRate = pow(1 + (float) $contract->eir_effective_annual, 1 / 12) - 1;
                // Stage 3 accrues on the basis governed for this period
                // (stage3_interest_basis, IFRS 9 5.4.1(b) by default); a
                // performing loan always accrues on gross.
                $basis = $stage === 3 ? $this->governance->stage3InterestBasis($this->periodEnd($period)) : 'GROSS';
                $netOpening = max(0.0, $opening - $allowance);
                $interest = $monthlyRate * ($basis === 'NET' ? $netOpening : $opening);
                $unwind = $basis === 'NET' ? $monthlyRate * min($allowance, $opening) : 0.0;
                $cash = $this->cashReceived($contractId, $period);
                // a write-off or a settlement in kind leaves the gross without being cash
                $closing = max(0.0, $opening + $interest + $unwind - $cash['amount'] - $cash['derecognised']);

                $row = EirAmortisation::create([
                    'contract_id' => $contractId,
                    'reporting_period' => $period,
                    'opening_gross' => round($opening, 2),
                    'interest_accrued' => round($interest, 2),
                    'interest_basis' => $basis,
                    'unwind_amount' => round($unwind, 2),
                    'cash_received' => round($cash['amount'], 2),
                    'cash_source' => $cash['source'],
                    'modification_gain_loss' => round(-$cash['derecognised'], 2), // derecognition without cash, disclosed on the row
                    'closing_gross' => round($closing, 2),
                    'ecl_allowance' => round($allowance, 2),
                ]);

                return $this->result($row, $superseded > 0 ? 'RECALCULATED' : 'CREATED', $cash['unclassified'], $superseded);
            });
        } catch (\Throwable $e) {
            return ['contract_id' => $contractId, 'reporting_period' => $period, 'status' => 'BLOCKED', 'error' => $e->getMessage()];
        }
    }

    private function initialOpening(ContractEir $contract, string $period): float
    {
        $snapshot = $contract->input_snapshot ?? [];
        $opening = (float) ($snapshot['initial_net_investment'] ?? $contract->opening_amortised_cost ?? 0);
        if ($opening <= 0) throw new RuntimeException('The locked EIR snapshot has no positive initial net investment.');

        $origination = $snapshot['metadata']['origination_date'] ?? $contract->origination_date?->toDateString();
        $flows = $snapshot['cash_flows'] ?? [];
        $asOf = CarbonImmutable::createFromFormat('Y-m-d', $period . '-01')->endOfMonth();
        if (! $origination || $asOf->lessThan(CarbonImmutable::parse($origination)->endOfMonth()) || $flows === []) return $opening;

        $pv = 0.0;
        foreach ($flows as $flow) {
            if (empty($flow['due_date']) || ! isset($flow['amount'])) continue;
            $due = CarbonImmutable::parse($flow['due_date']);
            if ($due->lessThanOrEqualTo($asOf)) continue;
            $years = $this->yearFraction($asOf, $due, (string) ($contract->source_day_count_basis ?? 'ACT/365'));
            $pv += (float) $flow['amount'] / pow(1 + (float) $contract->eir_effective_annual, $years);
        }
        if ($pv <= 0) throw new RuntimeException("No remaining contractual cash flows exist after {$period}.");
        return $pv;
    }

    private function yearFraction(CarbonImmutable $from, CarbonImmutable $to, string $basis): float
    {
        $basis = strtoupper(str_replace([' ', '_'], ['', '/'], $basis));
        if (in_array($basis, ['30/360', '30E/360'], true)) {
            $days = (($to->year - $from->year) * 360) + (($to->month - $from->month) * 30)
                + (min(30, $to->day) - min(30, $from->day));
            return max(0, $days / 360);
        }
        return max(0, $from->diffInDays($to) / 365);
    }

    /**
     * The loss allowance the net basis nets off (IFRS 9 5.4.1(b)): the
     * allowance in the books at the start of the month, which is the ECL the
     * engine wrote on the prior month's loan-book row. The current month's
     * ECL stands in when the prior month has none; the legacy
     * expected_loss_provision column, which only a legacy importer fills, is
     * the last resort. System audit of 9 October 2026, finding C6: the engine
     * read only that column, nothing in the governed chain wrote it, and
     * Stage 3 interest accrued on gross while the setting said net.
     */
    private function openingAllowance(string $contractId, string $period, object $loan): float
    {
        $prior = CarbonImmutable::createFromFormat('Y-m-d', $period . '-01')->subMonth()->format('Y-m');
        $previous = $this->loanSnapshot($contractId, $prior);
        foreach ([$previous->ecl_value ?? null, $loan->ecl_value ?? null, $loan->expected_loss_provision ?? null] as $candidate) {
            if ($candidate !== null) {
                return max(0.0, (float) $candidate);
            }
        }

        return 0.0;
    }

    private function loanSnapshot(string $contractId, string $period): ?object
    {
        return DB::table('loan_books')->where('contract_id', $contractId)
            ->whereRaw("REPLACE(SUBSTR(reporting_period, 1, 7), '-', '') = ?", [str_replace('-', '', $period)])
            ->orderByDesc('id')->first();
    }

    private function stage(object $loan): int
    {
        foreach (['calculated_ifrs9_stage', 'ifrs9stage_post_qualitative', 'ifrs9_stage'] as $field) {
            if (isset($loan->{$field}) && is_numeric($loan->{$field})) return (int) $loan->{$field};
        }
        return 0;
    }

    /**
     * Cash collected in the period, with its provenance.
     *
     * Delivered actuals are authoritative wherever the feed covers the period:
     * inside that window an absent row means the customer paid nothing, which
     * the contractual schedule cannot distinguish from a month it never
     * reached. Outside the window the schedule is the only evidence there is,
     * and the row is labelled DERIVED so a reader can tell a promise from a
     * receipt. A disbursement is cash advanced, not collected, and is never
     * netted off. Any other delivered type is excluded and reported rather
     * than absorbed, because its direction and component split are unknown.
     *
     * @return array{amount:float,source:string,unclassified:float}
     */
    private function cashReceived(string $contractId, string $period): array
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d', $period . '-01')->startOfMonth();
        $end = $start->endOfMonth();

        // The ledger covers a month when the feed's postings run past its
        // start and the contract existed by its end; a covered month with no
        // receipt is zero cash, not the schedule. System audit of 9 October
        // 2026, finding C5: the window was the contract's own first and last
        // posting, so every month after a borrower's last receipt fell back to
        // the schedule and the roll-forward retired a loan nobody was paying.
        $feedLast = (string) (DB::table('eir_actual_transactions')->max('transaction_date') ?? '');
        $window = DB::table('eir_actual_transactions')->where('contract_id', $contractId)
            ->selectRaw('MIN(transaction_date) as first_txn')->first();
        $contractStart = $window && $window->first_txn ? substr((string) $window->first_txn, 0, 10) : null;
        $covered = $feedLast !== '' && $contractStart !== null
            && $start->toDateString() <= substr((string) $feedLast, 0, 10)
            && $end->toDateString() >= $contractStart;

        if (! $covered) {
            return ['amount' => $this->scheduledCash($contractId, $start, $end), 'source' => 'DERIVED', 'unclassified' => 0.0, 'derecognised' => 0.0];
        }

        $collected = 0.0;
        $unclassified = 0.0;
        $derecognised = 0.0;
        $rows = DB::table('eir_actual_transactions')->where('contract_id', $contractId)
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('transaction_type, SUM(total_amount) as amount')->groupBy('transaction_type')->get();

        foreach ($rows as $row) {
            if (in_array($row->transaction_type, self::COLLECTION_TYPES, true)) {
                $collected += (float) $row->amount;
            } elseif (in_array($row->transaction_type, self::DERECOGNITION_TYPES, true)) {
                $derecognised += (float) $row->amount;
            } elseif (! in_array($row->transaction_type, self::ADVANCE_TYPES, true)) {
                $unclassified += (float) $row->amount;
            }
        }

        return ['amount' => $collected, 'source' => 'IMPORTED', 'unclassified' => $unclassified, 'derecognised' => $derecognised];
    }

    /** The contractual promise for the period — used only where no actuals cover it. */
    private function scheduledCash(string $contractId, CarbonImmutable $start, CarbonImmutable $end): float
    {
        return (float) DB::table('contract_cashflow_schedule')->where('contract_id', $contractId)
            ->where('schedule_version', 1)
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('COALESCE(SUM(principal_due + interest_due + fee_due), 0) as due')->value('due');
    }

    private function periodEnd(string $period): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $period . '-01')->endOfMonth();
    }

    private function normalisePeriod(string $period): string
    {
        if (preg_match('/^(\d{4})-?(0[1-9]|1[0-2])(?:-\d{2})?$/', trim($period), $m) !== 1) {
            throw new InvalidArgumentException('Reporting period must be YYYY-MM, YYYYMM or a date beginning YYYY-MM.');
        }
        return $m[1] . '-' . $m[2];
    }

    /**
     * Archive this period and every later one for the contract, then remove
     * them. Each opening balance is the prior period's closing, so a restated
     * period invalidates the whole chain behind it; leaving the later rows in
     * place would silently keep figures that no longer follow from anything.
     * They are regenerated by running those periods again, in order.
     */
    private function supersede(string $contractId, string $fromPeriod, ?int $userId, string $reason): int
    {
        $rows = EirAmortisation::where('contract_id', $contractId)
            ->where('reporting_period', '>=', $fromPeriod)->orderBy('reporting_period')->get();

        foreach ($rows as $row) {
            EirAmortisationHistory::create([
                'contract_id' => $row->contract_id,
                'reporting_period' => $row->reporting_period,
                'opening_gross' => $row->opening_gross,
                'interest_accrued' => $row->interest_accrued,
                'interest_basis' => $row->interest_basis,
                'unwind_amount' => $row->unwind_amount,
                'cash_received' => $row->cash_received,
                'cash_source' => $row->cash_source,
                'modification_gain_loss' => $row->modification_gain_loss,
                'closing_gross' => $row->closing_gross,
                'ecl_allowance' => $row->ecl_allowance,
                'originally_created_at' => $row->created_at,
                'superseded_at' => now(),
                'superseded_by' => $userId,
                'supersession_reason' => mb_substr($reason, 0, 500),
            ]);
        }

        EirAmortisation::where('contract_id', $contractId)->where('reporting_period', '>=', $fromPeriod)->delete();

        return $rows->count();
    }

    private function result(EirAmortisation $row, string $status, float $unclassified = 0.0, int $superseded = 0): array
    {
        return ['contract_id' => $row->contract_id, 'reporting_period' => $row->reporting_period, 'status' => $status,
            'interest_accrued' => (float) $row->interest_accrued, 'closing_gross' => (float) $row->closing_gross,
            'cash_source' => (string) $row->cash_source, 'unclassified_cash' => round($unclassified, 2),
            'superseded' => $superseded];
    }
}
