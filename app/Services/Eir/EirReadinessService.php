<?php

namespace App\Services\Eir;

use App\Models\ContractEir;
use Illuminate\Support\Facades\DB;

class EirReadinessService
{
    /** @return array{contract_id:string,status:string,ready:bool,issues:list<array{code:string,message:string}>,metrics:array} */
    public function assess(string $contractId): array
    {
        $issues = [];
        $contract = ContractEir::where('contract_id', $contractId)->first();
        if (! $contract) return $this->blocked($contractId, [['code' => 'CONTRACT_PROFILE_MISSING', 'message' => 'EIR contract profile is missing.']], []);
        if (! $contract->isInEirScope()) $issues[] = ['code' => 'EQUITY_EXCLUDED', 'message' => 'Equity instruments are outside the EIR engine.'];
        if ($contract->locked_at) $issues[] = ['code' => 'EIR_LOCKED', 'message' => 'The original EIR is already calculated and locked.'];
        if (! $contract->origination_date) $issues[] = ['code' => 'ORIGINATION_DATE_MISSING', 'message' => 'Origination/value date is missing.'];
        $takeon = $contract->schedule_source === \App\Services\Ebanker\TakeonLandingService::ROUTE;
        $takeonRow = $takeon ? DB::table('contract_takeon')->where('contract_id', $contractId)->first() : null;
        // a take-on loan is solved on the workbook's balance chain, so a loan E-Banker now shows closed still has its EIR
        if (! $takeon && (float) $contract->drawn_amount <= 0) $issues[] = ['code' => 'DRAWN_AMOUNT_MISSING', 'message' => 'Drawn/disbursed amount must be greater than zero.'];
        if (! in_array((int) $contract->payments_per_year, [1, 2, 4, 6, 12], true)) $issues[] = ['code' => 'FREQUENCY_INVALID', 'message' => 'Payment frequency must be annual, semi-annual, quarterly, bi-monthly or monthly.'];
        // payments_per_year defaults to monthly, so a facility whose source
        // never stated a frequency is indistinguishable from a genuinely
        // monthly one by value alone. A quarterly facility solved monthly
        // returns a plausible rate that is not the contract's.
        if ($contract->frequency_source !== 'STATED') $issues[] = ['code' => 'FREQUENCY_ASSUMED', 'message' => 'Payment frequency was assumed, not stated by a source. Confirm it against the contract master or offer letter before solving.'];
        if ($contract->schedule_approval_status !== 'APPROVED') $issues[] = ['code' => 'SCHEDULE_NOT_APPROVED', 'message' => 'The original schedule is a draft and must be reviewed and approved before EIR calculation.'];

        $schedule = DB::table('contract_cashflow_schedule')->where('contract_id', $contractId)->where('schedule_version', 1)->orderBy('due_date')->get();
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
        if (! $takeon && $contract->origination_date && $schedule->contains(fn ($r) => $r->due_date <= $contract->origination_date->toDateString())) $issues[] = ['code' => 'SCHEDULE_DATE_INVALID', 'message' => 'Every original contractual cash flow must fall after the origination date.'];
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
        $feeQuery = DB::table('contract_fees')->where('contract_id', $contractId);
        if (DB::getSchemaBuilder()->hasColumn('contract_fees', 'superseded_by_source')) {
            $feeQuery->whereNull('superseded_by_source');
        }
        $fees = $feeQuery->get();
        $unresolved = $fees->whereNotIn('classification_status', ['REVIEWED', 'REJECTED']);
        if ($unresolved->isNotEmpty()) $issues[] = ['code' => 'FEE_CLASSIFICATION_PENDING', 'message' => $unresolved->count() . ' fee/cost line(s) are not independently reviewed.'];
        $reviewedIntegral = $fees->where('classification_status', 'REVIEWED')->where('integral', 1);
        if ($reviewedIntegral->contains(fn ($f) => ! in_array($f->cashflow_direction, ['RECEIVED', 'PAID'], true))) $issues[] = ['code' => 'FEE_DIRECTION_MISSING', 'message' => 'A reviewed integral fee/cost has no cash-flow direction.'];

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

    private function blocked(string $id, array $issues, array $metrics): array
    {
        return ['contract_id' => $id, 'status' => 'BLOCKED', 'ready' => false, 'issues' => $issues, 'metrics' => $metrics];
    }
}
