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

    /** Confirmed restructures (5.4.3, 3.2.3, B5.4.6), applied to the closing of their month (spec P6, P7). */
    private readonly ModificationAccountingService $modifications;

    public function __construct(?GovernanceService $governance = null)
    {
        $this->governance = $governance ?? app(GovernanceService::class);
        $this->modifications = new ModificationAccountingService($this->governance);
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
                // A loan migrated into E-Banker at the take-on of 31 July 2024 has no E-Banker history
                // before it: the ledger holds only the three opening legs, dated back to the loan or to
                // the take-on (the drawn principal, the interest charged to date and the repayments to
                // date), which are balances, not cash of July 2024. Its roll-forward starts in August
                // 2024 from the balance at the take-on, with the arrears at take-on of decision D39
                // (spec 6.9; results review of 13 October 2026, E2).
                if ($period <= substr(\App\Services\Ebanker\TakeonLandingService::TAKEON_DATE, 0, 7)
                    && DB::getSchemaBuilder()->hasTable('contract_takeon') && DB::table('contract_takeon')->where('contract_id', $contractId)->exists()) {
                    throw new RuntimeException('Migrated at the take-on of 31 July 2024: the roll-forward starts in August 2024 from the balance at the take-on; the months before are the take-on build\'s (spec 6.9).');
                }

                $superseded = 0;
                $existing = EirAmortisation::where('contract_id', $contractId)->where('reporting_period', $period)->first();
                if ($existing && ! $recalculate) {
                    return $this->result($existing, 'UNCHANGED');
                }
                $this->modifications->assertBookedBefore($contractId, $period);
                if ($existing) {
                    $superseded = $this->supersede($contractId, $period, $userId, (string) $reason);
                }

                $previous = EirAmortisation::where('contract_id', $contractId)
                    ->where('reporting_period', '<', $period)->orderByDesc('reporting_period')->first();
                // The first month opens on the schedule's present value or on the ledger's drawn balance;
                // every later month carries that basis forward with the closing balance.
                $arrears = 0.0;
                if ($previous) {
                    $opening = (float) $previous->closing_gross;
                    $openingBasis = $previous->opening_basis ?: 'SCHEDULE';
                } else {
                    [$opening, $openingBasis, $arrears] = $this->initialOpening($contract, $period);
                }
                $loan = $this->loanSnapshot($contractId, $period);
                if (! $loan) throw new RuntimeException("No loan-book snapshot exists for {$period}.");

                $stage = $this->stage($loan);
                if (! in_array($stage, [1, 2, 3], true)) throw new RuntimeException("The IFRS 9 stage for {$period} is missing or invalid.");
                $allowance = $this->openingAllowance($contractId, $period, $loan);
                $eirAnnual = $this->eirInForce($contract, $loan, $period);
                $monthlyRate = pow(1 + $eirAnnual, 1 / 12) - 1;
                // Stage 3 accrues on the basis governed for this period
                // (stage3_interest_basis, IFRS 9 5.4.1(b) by default); a
                // performing loan always accrues on gross.
                $basis = $stage === 3 ? $this->governance->stage3InterestBasis($this->periodEnd($period)) : 'GROSS';
                $netOpening = max(0.0, $opening - $allowance);
                $interest = $monthlyRate * ($basis === 'NET' ? $netOpening : $opening);
                $unwind = $basis === 'NET' ? $monthlyRate * min($allowance, $opening) : 0.0;
                $cash = $this->cashReceived($contractId, $period);
                // A tranche drawn in the month raises the gross, and earns interest from the day it is
                // drawn, but only on a ledger basis: a schedule's present value already counts every
                // tranche, so adding the advance there would count it twice.
                $advances = 0.0;
                if ($openingBasis === 'LEDGER') {
                    // the advance raises the net carrying amount as much as the gross, so it accrues on either basis;
                    // it is net of the reviewed integral fees of the month, which the EIR was solved net of (spec 7.8.4)
                    $fees = $cash['advances'] === [] ? 0.0 : $this->feesReceivedIn($contractId, CarbonImmutable::createFromFormat('Y-m-d', $period . '-01'));
                    foreach ($cash['advances'] as $adv) {
                        $net = max(0.0, $adv['amount'] - $fees);
                        $fees = max(0.0, $fees - $adv['amount']);
                        $advances += $net;
                        $interest += $monthlyRate * $net * $adv['share_of_month'];
                    }
                }
                // a write-off or a settlement in kind leaves the gross without being cash
                $closing = max(0.0, $opening + $advances + $interest + $unwind - $cash['amount'] - $cash['derecognised']);
                // a confirmed restructure of the month moves the gross carrying amount at its close: the modified
                // flows at the EIR the month accrued at (5.4.3, B5.4.6), or derecognition and version N+1 (3.2.3)
                $mod = $this->modifications->apply($contract, $period, $closing, $eirAnnual, $stage, (string) ($contract->source_day_count_basis ?? 'ACT/365'));
                if ($mod !== null) {
                    $closing = max(0.0, $mod['closing']);
                }

                $row = EirAmortisation::create([
                    'contract_id' => $contractId,
                    'reporting_period' => $period,
                    'opening_gross' => round($opening, 2),
                    'advances' => round($advances, 2),
                    'opening_basis' => $openingBasis,
                    'eir_annual' => round($eirAnnual, 8),
                    'opening_arrears' => round($arrears, 2),
                    'interest_accrued' => round($interest, 2),
                    'interest_basis' => $basis,
                    'unwind_amount' => round($unwind, 2),
                    'cash_received' => round($cash['amount'], 2),
                    'cash_source' => $cash['source'],
                    'modification_gain_loss' => round(-$cash['derecognised'], 2), // derecognition without cash, disclosed on the row
                    'closing_gross' => round($closing, 2),
                    'ecl_allowance' => round($allowance, 2),
                ] + ($mod === null ? [] : ['modification_adjustment' => $mod['adjustment'], 'adjustment_kind' => $mod['kind'], 'schedule_version' => $mod['version']]));

                return $this->result($row, $superseded > 0 ? 'RECALCULATED' : 'CREATED', $cash['unclassified'], $superseded);
            });
        } catch (\Throwable $e) {
            if ($e instanceof ModificationBlockedException) {
                ModificationAccountingService::recordBlocked($e); // after the rollback, so the register shows why
            }
            return ['contract_id' => $contractId, 'reporting_period' => $period, 'status' => 'BLOCKED', 'error' => $e->getMessage()];
        }
    }

    /**
     * The EIR a month accrues at (decision D38, IFRS 9 B5.4.5). A fixed-rate loan
     * keeps its locked rate. A floating-rate loan's EIR moves with the rate E-Banker
     * charged in the month (the loan book's rate, section 3.2 of the specification),
     * keeping the margin the fees add: the nominal EIR is the locked nominal EIR plus
     * the change from the contractual rate the locked schedule was built on to the
     * month's rate. That is also how a schedule generated at a later rate than the
     * loan was lent at is put right: Chipala Investment was lent at 30.0 percent and
     * its schedule built at 26.1.
     */
    private function eirInForce(ContractEir $contract, object $loan, ?string $period = null): float
    {
        $locked = (float) $contract->eir_effective_annual;
        $built = $this->scheduleRate($contract);
        // after a confirmed restructure: the EIR of version N+1 (derecognition) or the revised EIR after
        // modification fees, at the rate it was set at; a modification kept inside the original EIR moves
        // the rate the re-estimation starts from by its own change in the contractual rate (spec P7)
        if ($period !== null && ($basis = $this->modifications->basisBefore((string) $contract->contract_id, $period)) !== null) {
            $locked = $basis['eir'] ?? $locked;
            $built = ($basis['built'] ?? $built) + $basis['shift'];
        }
        $charged = isset($loan->interest_rate) && is_numeric($loan->interest_rate) ? (float) $loan->interest_rate / 100 : null;
        if (strtoupper((string) $contract->rate_type) !== 'FLOATING' || $built <= 0 || $charged === null || $charged <= 0) {
            return $locked;
        }
        $nominal = 12 * (pow(1 + $locked, 1 / 12) - 1) + ($charged - $built);

        return pow(1 + max(0.0, $nominal) / 12, 12) - 1;
    }

    /**
     * The current EIR of a floating-rate loan for a month (decision D38, IFRS 9 B5.4.5), for
     * the ECL's discount rate (B5.5.44 asks for the current EIR of a floating-rate asset).
     * The rate the roll-forward accrued at for the month (eir_annual) where it ran; otherwise
     * the same re-estimation from the month's loan-book rate. Null where the loan is not
     * floating, or the month's charged rate or the rate the schedule was built at is unknown:
     * never the locked rate in its place.
     */
    public function currentFloatingEir(ContractEir $contract, object $loan, string $period): ?float
    {
        if (strtoupper((string) $contract->rate_type) !== 'FLOATING') {
            return null;
        }
        $period = $this->normalisePeriod($period);
        // without the month's charged rate the roll-forward itself fell back to the locked rate: not a current EIR
        $charged = isset($loan->interest_rate) && is_numeric($loan->interest_rate) && (float) $loan->interest_rate > 0;
        if (! $charged || $contract->eir_effective_annual === null || $this->scheduleRate($contract) <= 0) {
            return null;
        }
        $accrued = EirAmortisation::where('contract_id', $contract->contract_id)->where('reporting_period', $period)->value('eir_annual');
        if ($accrued !== null && (float) $accrued > 0) {
            return (float) $accrued;
        }

        return $this->eirInForce($contract, $loan, $period);
    }

    /**
     * The nominal rate the locked schedule was built at. A generated schedule
     * is built at the contract master's rate; a take-on loan's schedule is the
     * workbook's, which carries the rates MAIIC charged before the migration,
     * so its rate is the one its own cash flows solve to with no fee, recorded
     * on the locked snapshot (results review of 13 October 2026, E2).
     */
    private function scheduleRate(ContractEir $contract): float
    {
        $recorded = $contract->input_snapshot['metadata']['schedule_rate'] ?? null;

        return is_numeric($recorded) && (float) $recorded > 0 ? (float) $recorded : (float) ($contract->contractual_rate ?? 0);
    }

    /**
     * The reviewed integral fees received in a month, which the cash advanced
     * that month is net of: a facility on the ledger basis opens at what the
     * ledger drew, which carries a fee capitalised into the loan, while its EIR
     * was solved on the cash paid out (the amount drawn less the fee). Only a
     * line a person has reviewed counts, and only from the source of record.
     */
    private function feesReceivedIn(string $contractId, CarbonImmutable $start): float
    {
        if (! DB::getSchemaBuilder()->hasTable('contract_fees')) {
            return 0.0;
        }
        $q = DB::table('contract_fees')->where('contract_id', $contractId)->where('classification_status', 'REVIEWED')->where('integral', true)
            ->where('cashflow_direction', 'RECEIVED')->whereBetween('transaction_date', [$start->toDateString(), $start->endOfMonth()->toDateString()]);
        if (DB::getSchemaBuilder()->hasColumn('contract_fees', 'superseded_by_source')) {
            $q->whereNull('superseded_by_source');
        }

        return (float) $q->sum('amount');
    }

    /** @return array{0: float, 1: string, 2: float} the opening, its basis (SCHEDULE or LEDGER) and the arrears in it */
    private function initialOpening(ContractEir $contract, string $period): array
    {
        $snapshot = $contract->input_snapshot ?? [];
        $opening = (float) ($snapshot['initial_net_investment'] ?? $contract->opening_amortised_cost ?? 0);
        if ($opening <= 0) throw new RuntimeException('The locked EIR snapshot has no positive initial net investment.');

        $origination = $snapshot['metadata']['origination_date'] ?? $contract->origination_date?->toDateString();
        $flows = $snapshot['cash_flows'] ?? [];
        // The opening of a month is the amortised cost at the end of the month before: the present
        // value at that date of the flows still to come. (Taking it at the end of the month itself,
        // as before 13 October 2026, counted that month's interest twice: once in the present value
        // and again in the month's accrual.)
        $start = CarbonImmutable::createFromFormat('Y-m-d', $period . '-01');
        $asOf = $start->subDay();
        if ($origination && CarbonImmutable::parse($origination)->greaterThan($asOf)) {
            // Lent within the month: nothing was owed at its start. Where the ledger shows the
            // advances, the month opens at nil and each one is added from the day it was drawn;
            // without them the initial net investment stands in, as before.
            // A facility signed in the month but first drawn later (Mphunzitsi SACCO: signed 15
            // July 2025, first tranche in August) is in the same position: nothing is owed yet,
            // and opening it at the initial net investment on the schedule basis counted money
            // not yet lent and never added the tranches (results review, 13 October 2026).
            $drawn = DB::table('eir_actual_transactions')->where('contract_id', $contract->contract_id)->whereIn('transaction_type', self::ADVANCE_TYPES)
                ->where('transaction_date', '>=', $start->toDateString())->exists();

            return $drawn ? [0.0, 'LEDGER', 0.0] : [$opening, 'SCHEDULE', 0.0];
        }
        if (! $origination || $flows === []) return [$opening, 'SCHEDULE', 0.0];

        $pv = $this->presentValue($contract, $flows, $asOf, (float) $contract->eir_effective_annual);
        if ($pv <= 0) throw new RuntimeException("No remaining contractual cash flows exist after {$period}.");
        // the balance the schedule expects to be owed: the same flows at the rate they were built at
        $contractual = $this->scheduleRate($contract);
        $scheduled = $contractual > 0 ? $this->presentValue($contract, $flows, $asOf, pow(1 + $contractual / 12, 12) - 1) : $pv;

        return $this->prepaidOpening($contract, $period, $pv, $scheduled);
    }

    /** The present value at $asOf of the flows due after it, at an effective annual rate. */
    private function presentValue(ContractEir $contract, array $flows, CarbonImmutable $asOf, float $annual): float
    {
        $pv = 0.0;
        foreach ($flows as $flow) {
            if (empty($flow['due_date']) || ! isset($flow['amount'])) continue;
            $due = CarbonImmutable::parse($flow['due_date']);
            if ($due->lessThanOrEqualTo($asOf)) continue;
            $years = $this->yearFraction($asOf, $due, (string) ($contract->source_day_count_basis ?? 'ACT/365'));
            $pv += (float) $flow['amount'] / pow(1 + $annual, $years);
        }

        return $pv;
    }

    /**
     * The first month's opening for a loan the borrower has prepaid (system
     * audit of 13 October 2026). The opening is the present value of the
     * remaining scheduled cash flows at the original EIR, which assumes every
     * instalment is still to come. When the borrower has repaid ahead of the
     * schedule, the schedule overstates what is owed: Barnet Agriculture
     * Resource Centre repaid MWK 55.6 million in November 2025 and the ledger
     * shows 0.4 million left, while the schedule's present value at December
     * was 38.8 million and the roll-forward accrued EIR interest on it. When
     * the ledger balance at the month before is less than half that present
     * value, the opening is the ledger balance (never below nil), and the
     * substitution is written to the audit log with both figures.
     */
    /**
     * Arrears at the opening (decision D39, specification section 6.9). The present
     * value assumes every instalment up to the month before was paid as scheduled.
     * Where the ledger owes more than the balance the schedule expects at that date
     * (by more than one percent), the difference is booked as arrears and added to the
     * opening: overdue amounts are owed now, so they are not discounted. The addition
     * is written to the audit log with the three figures.
     */
    private function prepaidOpening(ContractEir $contract, string $period, float $pv, float $scheduled): array
    {
        $priorEnd = CarbonImmutable::createFromFormat('Y-m-d', $period . '-01')->subDay()->toDateString();
        $account = DB::table('loan_books')->where('contract_id', $contract->contract_id)->whereNotNull('external_identity_id')
            ->where('external_identity_id', '!=', 'TBA')->value('external_identity_id') ?? str_pad((string) $contract->contract_id, 15, '0', STR_PAD_LEFT);
        try {
            $ledger = app(\App\Services\Ebanker\LandingZoneReader::class)->ledgerByAccount($priorEnd, $account)[$account] ?? [];
        } catch (\Throwable) {
            return [$pv, 'SCHEDULE', 0.0];
        }
        if ($ledger === []) {
            return [$pv, 'SCHEDULE', 0.0];
        }
        $gross = -array_sum(array_map(fn ($p) => (float) str_replace(',', '', (string) ($p['payload']['TRANSAMT'] ?? 0)), $ledger));
        // A facility still drawing down: the schedule assumes every tranche was advanced at the
        // start, so its present value counts money not yet lent. The opening is the drawn balance
        // and each later tranche is added in the month it is drawn.
        $laterAdvance = DB::table('eir_actual_transactions')->where('contract_id', $contract->contract_id)->whereIn('transaction_type', self::ADVANCE_TYPES)
            ->where('transaction_date', '>', $priorEnd)->exists();
        if (! $laterAdvance && $gross >= 0.5 * $pv) {
            $arrears = $gross - $scheduled;
            if ($arrears <= 0.01 * $scheduled) {
                return [$pv, 'SCHEDULE', 0.0];
            }
            $arrears = round($arrears, 2);
            \App\Services\AuditLoggerService::log('EIR Opening Arrears', 'eir_amortisation', null, ['reporting_period' => $period,
                'new_values' => ['contract_id' => $contract->contract_id, 'schedule_present_value' => round($pv, 2), 'scheduled_balance' => round($scheduled, 2),
                    'ledger_balance' => round($gross, 2), 'arrears' => $arrears, 'opening' => round($pv + $arrears, 2),
                    'reason' => 'the ledger at ' . $priorEnd . ' owes more than the schedule expects: the difference is booked as arrears (specification section 6.9)']]);

            return [$pv + $arrears, 'SCHEDULE', $arrears];
        }
        $opening = max(0.0, round($gross, 2));
        \App\Services\AuditLoggerService::log('EIR Opening From Ledger', 'eir_amortisation', null, ['reporting_period' => $period,
            'new_values' => ['contract_id' => $contract->contract_id, 'schedule_present_value' => round($pv, 2), 'ledger_balance' => round($gross, 2), 'opening' => $opening,
                'reason' => $laterAdvance
                    ? 'still drawing down: the opening is the drawn balance at ' . $priorEnd . ' and each later tranche is added in the month it is drawn'
                    : 'prepaid ahead of the schedule: the ledger balance at ' . $priorEnd . ' is below half the present value of the remaining schedule']]);

        return [$opening, 'LEDGER', 0.0];
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
            return ['amount' => $this->scheduledCash($contractId, $start, $end), 'source' => 'DERIVED', 'unclassified' => 0.0, 'derecognised' => 0.0, 'advances' => []];
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

        // each advance with the share of the month it is outstanding for (drawn on the 1st: all of it)
        $days = $start->daysInMonth;
        $advances = DB::table('eir_actual_transactions')->where('contract_id', $contractId)->whereIn('transaction_type', self::ADVANCE_TYPES)
            ->whereBetween('transaction_date', [$start->toDateString(), $end->toDateString()])->get(['transaction_date', 'total_amount'])
            ->map(fn ($a) => ['amount' => (float) $a->total_amount,
                'share_of_month' => ($days - (int) CarbonImmutable::parse($a->transaction_date)->day + 1) / $days])->all();

        return ['amount' => $collected, 'source' => 'IMPORTED', 'unclassified' => $unclassified, 'derecognised' => $derecognised, 'advances' => $advances];
    }

    /** The contractual promise for the period — used only where no actuals cover it. */
    private function scheduledCash(string $contractId, CarbonImmutable $start, CarbonImmutable $end): float
    {
        return (float) DB::table('contract_cashflow_schedule')->where('contract_id', $contractId)
            ->where('schedule_version', ModificationAccountingService::versionInForce($contractId, $start->toDateString()))
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
                'advances' => $row->advances,
                'opening_basis' => $row->opening_basis,
                'eir_annual' => $row->eir_annual,
                'opening_arrears' => $row->opening_arrears,
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
            ] + (isset($row->modification_adjustment) ? ['modification_adjustment' => $row->modification_adjustment, 'adjustment_kind' => $row->adjustment_kind, 'schedule_version' => $row->schedule_version] : []));
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
