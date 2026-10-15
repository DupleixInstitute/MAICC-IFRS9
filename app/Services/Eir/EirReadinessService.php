<?php

namespace App\Services\Eir;

use App\Models\ContractEir;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EirReadinessService
{
    /** @return array{contract_id:string,status:string,ready:bool,issues:list<array{code:string,message:string}>,metrics:array} */
    public function assess(string $contractId): array
    {
        $contract = ContractEir::where('contract_id', $contractId)->first();
        if (! $contract) return $this->blocked($contractId, [['code' => 'CONTRACT_PROFILE_MISSING', 'message' => 'EIR contract profile is missing.']], []);

        return $this->assessContract($contract);
    }

    /**
     * The checks on one contract. The version 1 schedule, the fee lines and the
     * take-on row are read here unless the caller has read them already:
     * EirCoverageService reads them for the whole book in three queries and
     * passes each contract's share, so the two run the same checks and cannot
     * drift (documentation audit, 14 October 2026).
     *
     * @param  \Illuminate\Support\Collection|null  $schedule  the contract's version 1 rows, ordered by due date
     * @param  \Illuminate\Support\Collection|null  $fees  the contract's fee lines not superseded by a higher source
     * @param  object|false|null  $takeonRow  the contract_takeon row; false when the caller looked and there is none
     */
    public function assessContract(ContractEir $contract, ?Collection $schedule = null, ?Collection $fees = null, object|false|null $takeonRow = null): array
    {
        $issues = [];
        $contractId = (string) $contract->contract_id;
        if (! $contract->isInEirScope()) $issues[] = ['code' => 'EQUITY_EXCLUDED', 'message' => 'Equity instruments are outside the EIR engine.'];
        if ($contract->locked_at) $issues[] = ['code' => 'EIR_LOCKED', 'message' => 'The original EIR is already calculated and locked.'];
        if (! $contract->origination_date) $issues[] = ['code' => 'ORIGINATION_DATE_MISSING', 'message' => 'Origination/value date is missing.'];
        $takeon = $contract->schedule_source === \App\Services\Ebanker\TakeonLandingService::ROUTE;
        if ($takeonRow === null) {
            $takeonRow = $takeon ? DB::table('contract_takeon')->where('contract_id', $contractId)->first() : null;
        }
        $takeonRow = $takeonRow ?: null;
        // a take-on loan is solved on the workbook's balance chain, so a loan E-Banker now shows closed still has its EIR
        if (! $takeon && (float) $contract->drawn_amount <= 0) $issues[] = ['code' => 'DRAWN_AMOUNT_MISSING', 'message' => 'Drawn/disbursed amount must be greater than zero.'];
        if (! in_array((int) $contract->payments_per_year, [1, 2, 4, 6, 12], true)) $issues[] = ['code' => 'FREQUENCY_INVALID', 'message' => 'Payment frequency must be annual, semi-annual, quarterly, bi-monthly or monthly.'];
        // payments_per_year defaults to monthly, so a facility whose source
        // never stated a frequency is indistinguishable from a genuinely
        // monthly one by value alone. A quarterly facility solved monthly
        // returns a plausible rate that is not the contract's.
        if ($contract->frequency_source !== 'STATED') $issues[] = ['code' => 'FREQUENCY_ASSUMED', 'message' => 'Payment frequency was assumed, not stated by a source. Confirm it against the contract master or offer letter before solving.'];
        if ($contract->schedule_approval_status !== 'APPROVED') $issues[] = ['code' => 'SCHEDULE_NOT_APPROVED', 'message' => 'The original schedule is a draft and must be reviewed and approved before EIR calculation.'];

        $schedule ??= DB::table('contract_cashflow_schedule')->where('contract_id', $contractId)->where('schedule_version', 1)->orderBy('due_date')->get();
        if ($schedule->isEmpty()) $issues[] = ['code' => 'ORIGINAL_SCHEDULE_MISSING', 'message' => 'Original contractual schedule (version 1) is missing.'];
        // A take-on loan's schedule is the take-on workbook's, checked by the
        // take-on build as a whole (spec 6.9; results review E2): its dates run
        // from the workbook's own start and its principal is the balance chain,
        // not the drawn amount in E-Banker. The build's check stands in for the
        // date and principal checks below, and a solve that failed it is named.
        if ($takeon) {
            $row = $takeonRow;
            if (! $row || $row->basis !== 'RECOMPUTED') {
                $issues[] = ['code' => 'TAKEON_NOT_RECOMPUTED', 'message' => 'The take-on loan is on its take-on balance, not recomputed from origination (' . ($row ? implode('; ', json_decode((string) $row->flags, true) ?? []) : 'no take-on row') . ').'];
            } elseif (($row->eir_check ?? null) !== 'PASS') {
                $issues[] = ['code' => 'TAKEON_SOLVE_REFUSED', 'message' => 'The take-on EIR is refused: ' . ($row->eir_check_reason ?: 'the take-on solve has not been checked; build the take-on population again')];
            }
        }
        if (! $takeon && $schedule->contains(fn ($r) => ! $r->due_date || ((float) $r->principal_due + (float) $r->interest_due + (float) $r->fee_due) < 0)) $issues[] = ['code' => 'SCHEDULE_INVALID', 'message' => 'Schedule contains a missing date or negative total cash flow.'];
        // The solve discounts from the first money advanced (DisbursementService::solvePlan,
        // spec v4 E1), so the dates are checked against that date, not only the signing date:
        // the gate passed contracts the solver then refused (104430000068, UAT 14 October 2026).
        $plan = ! $takeon && $contract->origination_date ? $this->solvePlan($contract) : null;
        if (! $takeon && $contract->origination_date && $schedule->contains(fn ($r) => $r->due_date <= $contract->origination_date->toDateString())) {
            $issues[] = ['code' => 'SCHEDULE_DATE_INVALID', 'message' => 'Every original contractual cash flow must fall after the origination date.'];
        } elseif ($plan !== null && $plan['solve_from'] !== $contract->origination_date->toDateString() && ($early = $schedule->filter(fn ($r) => (string) $r->due_date <= $plan['solve_from']))->isNotEmpty()) {
            $issues[] = ['code' => 'SCHEDULE_BEFORE_FIRST_DRAWDOWN', 'message' => $early->count() . ' instalment(s) of the version 1 schedule fall due on or before ' . $plan['solve_from']
                . ', the date the tranche register shows the first money advanced, so the EIR cannot be solved from that date. Generate the schedule again from the tranche register and have it reviewed.'];
        }
        if ($plan !== null && ($mismatch = $this->trancheMismatch($contract, $plan)) !== null) {
            $issues[] = ['code' => 'SCHEDULE_NOT_ON_TRANCHES', 'message' => $mismatch];
        }
        if ($schedule->pluck('due_date')->duplicates()->isNotEmpty()) $issues[] = ['code' => 'SCHEDULE_DATE_DUPLICATE', 'message' => 'Original contractual schedule contains duplicate due dates.'];

        $principalDue = (float) $schedule->sum('principal_due');
        $drawn = (float) $contract->drawn_amount;
        // A generated schedule retires the balance the generator recorded when
        // it built the draft: the amount drawn, the approved amount where the
        // instalment is sized on the sanction, plus any interest capitalised
        // during a moratorium of type Both. Reading that figure replaces the
        // old assumption that every moratorium capitalises at the annual rate
        // over twelve, which was true of only one of the two shapes E-Banker
        // offers (spec v3 section 7.1). Imported original schedules remain
        // controlled directly to the drawdown.
        $expectedPrincipal = $drawn;
        $recorded = $contract->schedule_amortising_balance ?? null;
        if ($contract->schedule_source === 'GENERATED' && $recorded !== null && (float) $recorded > 0) {
            $expectedPrincipal = round((float) $recorded, 2);
        }
        if (! $takeon && $expectedPrincipal > 0 && $schedule->isNotEmpty() && abs($principalDue - $expectedPrincipal) > max(1.0, $expectedPrincipal * 0.01)) $issues[] = ['code' => 'PRINCIPAL_NOT_RECONCILED', 'message' => 'Scheduled principal does not reconcile to the expected contractual balance within 1%. Where the schedule was generated before the moratorium shapes were built, generate it again so the balance it amortises is recorded.'];

        // a line superseded by a higher source (fee_source_precedence, spec 7.8.2) neither blocks nor enters the EIR
        $fees ??= self::feeQuery()->where('contract_id', $contractId)->get();
        $unresolved = $fees->whereNotIn('classification_status', ['REVIEWED', 'REJECTED']);
        if ($unresolved->isNotEmpty()) $issues[] = ['code' => 'FEE_CLASSIFICATION_PENDING', 'message' => $unresolved->count() . ' fee/cost line(s) are not independently reviewed.'];
        // a line of a lower fee source the source of record has no event for (R03): it waits for a person's decision
        $overlap = $fees->filter(fn ($f) => isset($f->source_overlap) && $f->source_overlap !== null);
        if ($overlap->isNotEmpty()) $issues[] = ['code' => 'FEE_SOURCE_OVERLAP', 'message' => $overlap->count() . ' fee line(s) of a lower source match no fee of the source of record: on Fee Classification, decide for each whether it is a fee of its own or the same fee.'];
        // a held GL fee posting that names this loan as its likely owner: the loan's fees are not known to be complete
        // until a person ties it or says it is not this loan's (15 October 2026); nothing is allocated by suggestion
        $held = HeldFeeEvidence::forContract($contractId);
        if ($held !== []) $issues[] = ['code' => 'FEE_HELD_FOR_LOAN', 'message' => count($held) . ' GL fee posting(s) held without a loan name this loan as the likely owner: ' . HeldFeeEvidence::describe($held) . '. On Fee Classification, tie each to its loan or record that it is not this loan\'s fee.'];
        $reviewedIntegral = $fees->where('classification_status', 'REVIEWED')->where('integral', 1);
        if ($reviewedIntegral->contains(fn ($f) => ! in_array($f->cashflow_direction, ['RECEIVED', 'PAID'], true))) $issues[] = ['code' => 'FEE_DIRECTION_MISSING', 'message' => 'A reviewed integral fee/cost has no cash-flow direction. Return it to classification on Fee Classification, give it its direction and have it reviewed again.'];

        $received = (float) $reviewedIntegral->where('cashflow_direction', 'RECEIVED')->sum('amount');
        $paid = (float) $reviewedIntegral->where('cashflow_direction', 'PAID')->sum('amount');
        if ($takeon && $takeonRow) {
            // the take-on loan's money out is the opening of the workbook's chain
            $drawn = (float) (json_decode((string) $takeonRow->recompute_detail, true)['chain']['opening'] ?? 0);
        }
        $initialNet = $drawn - $received + $paid;
        if ($drawn > 0 && $initialNet <= 0) $issues[] = ['code' => 'INITIAL_NET_INVALID', 'message' => 'Fee-adjusted initial net investment is not positive.'];

        $metrics = ['drawn_amount' => $drawn, 'expected_contractual_principal' => $expectedPrincipal, 'scheduled_principal' => $principalDue, 'integral_fees_received' => $received, 'integral_costs_paid' => $paid, 'initial_net_investment' => $initialNet, 'schedule_rows' => $schedule->count(), 'unresolved_fee_lines' => $unresolved->count()];
        return $issues === [] ? ['contract_id' => $contractId, 'status' => 'READY', 'ready' => true, 'issues' => [], 'metrics' => $metrics] : $this->blocked($contractId, $issues, $metrics);
    }

    /**
     * Why a generated version 1 schedule does not follow the drawdowns the EIR
     * is solved on, or null when it does (spec v4 E1). The solve takes the money
     * advanced from the tranche register; a schedule generated before the
     * register was read (its basis records no drawdown profile) or with none
     * given treats the facility as drawn in full at signing, so its interest
     * runs on money not yet lent and the two would be mixed in one EIR. Such a
     * schedule, even approved, is generated again and reviewed again while the
     * EIR is not locked (ScheduleWorkflowService::generate).
     *
     * @param  array{drawn:?float, solve_from:string, later:list<array>, register:bool}|null  $plan
     */
    public function trancheMismatch(ContractEir $contract, ?array $plan = null): ?string
    {
        // generate() records the basis of every generated schedule (schedule_basis_sources); a
        // store without that column (an isolated test schema) holds no basis to judge
        if ($contract->schedule_source !== 'GENERATED' || ! $contract->origination_date || $contract->locked_at
            || ! array_key_exists('schedule_basis_sources', $contract->getAttributes())) {
            return null;
        }
        $plan ??= $this->solvePlan($contract);
        if ($plan === null || ! $plan['register']) {
            return null;
        }
        $origination = $contract->origination_date->toDateString();
        if ($plan['solve_from'] === $origination && $plan['later'] === []) {
            return null; // everything drawn by origination: nothing to follow
        }
        $basis = (string) $contract->schedule_basis_sources;
        if (str_contains($basis, 'drawdown_profile=TRANCHES_AS_DRAWN') || str_contains($basis, 'drawdown_profile=DRAWN_BY_THE_START')) {
            return null;
        }
        $tranches = app(DisbursementService::class)->tranchesFor((string) $contract->contract_id);
        $sum = round((float) array_sum(array_column($tranches, 'amount')), 2);
        if (count(array_unique(array_column($tranches, 'disbursement_date'))) === 1 && abs($sum - round((float) $contract->drawn_amount, 2)) > 0.01) {
            // the generator will not follow a single drawdown whose amount the register and the master tell differently
            return 'The tranche register shows one drawdown of ' . number_format($sum, 2) . " on {$plan['solve_from']}, and the contract master a drawn amount of "
                . number_format((float) $contract->drawn_amount, 2) . ': the schedule and the EIR would rest on different amounts. Correct the register or the contract master, then generate the schedule again.';
        }
        $first = $plan['solve_from'] !== $origination ? "the first money advanced on {$plan['solve_from']}" : "money advanced at origination";
        $more = count($plan['later']) > 0 ? ' and ' . count($plan['later']) . ' later drawdown(s)' : '';

        return "The version 1 schedule was generated without the tranche register: it treats the facility as drawn in full on {$origination}, while the register shows {$first}{$more}. "
            . 'Generate the schedule again (it then follows each drawdown) and have a second person approve it before the EIR is calculated.';
    }

    /** DisbursementService::solvePlan for the contract, or null when no tranche register is held. */
    private function solvePlan(ContractEir $contract): ?array
    {
        if (! DB::getSchemaBuilder()->hasTable('contract_disbursements')) {
            return null;
        }

        return app(DisbursementService::class)->solvePlan((string) $contract->contract_id, $contract->origination_date);
    }

    /** The fee lines that count: a line superseded by a higher source (fee_source_precedence, spec 7.8.2) neither blocks nor enters the EIR. */
    public static function feeQuery(): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('contract_fees');
        if (DB::getSchemaBuilder()->hasColumn('contract_fees', 'superseded_by_source')) {
            $query->whereNull('superseded_by_source');
        }

        return $query;
    }

    private function blocked(string $id, array $issues, array $metrics): array
    {
        return ['contract_id' => $id, 'status' => 'BLOCKED', 'ready' => false, 'issues' => $issues, 'metrics' => $metrics];
    }
}
