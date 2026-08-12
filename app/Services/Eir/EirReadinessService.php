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
        if ((float) $contract->drawn_amount <= 0) $issues[] = ['code' => 'DRAWN_AMOUNT_MISSING', 'message' => 'Drawn/disbursed amount must be greater than zero.'];
        if (! in_array((int) $contract->payments_per_year, [1, 2, 4, 6, 12], true)) $issues[] = ['code' => 'FREQUENCY_INVALID', 'message' => 'Payment frequency must be annual, semi-annual, quarterly, bi-monthly or monthly.'];
        // payments_per_year defaults to monthly, so a facility whose source
        // never stated a frequency is indistinguishable from a genuinely
        // monthly one by value alone. A quarterly facility solved monthly
        // returns a plausible rate that is not the contract's.
        if ($contract->frequency_source !== 'STATED') $issues[] = ['code' => 'FREQUENCY_ASSUMED', 'message' => 'Payment frequency was assumed, not stated by a source. Confirm it against the contract master or offer letter before solving.'];

        $schedule = DB::table('contract_cashflow_schedule')->where('contract_id', $contractId)->where('schedule_version', 1)->orderBy('due_date')->get();
        if ($schedule->isEmpty()) $issues[] = ['code' => 'ORIGINAL_SCHEDULE_MISSING', 'message' => 'Original contractual schedule (version 1) is missing.'];
        if ($schedule->contains(fn ($r) => ! $r->due_date || ((float) $r->principal_due + (float) $r->interest_due + (float) $r->fee_due) < 0)) $issues[] = ['code' => 'SCHEDULE_INVALID', 'message' => 'Schedule contains a missing date or negative total cash flow.'];
        if ($contract->origination_date && $schedule->contains(fn ($r) => $r->due_date <= $contract->origination_date->toDateString())) $issues[] = ['code' => 'SCHEDULE_DATE_INVALID', 'message' => 'Every original contractual cash flow must fall after the origination date.'];
        if ($schedule->pluck('due_date')->duplicates()->isNotEmpty()) $issues[] = ['code' => 'SCHEDULE_DATE_DUPLICATE', 'message' => 'Original contractual schedule contains duplicate due dates.'];

        $principalDue = (float) $schedule->sum('principal_due');
        $drawn = (float) $contract->drawn_amount;
        if ($drawn > 0 && $schedule->isNotEmpty() && abs($principalDue - $drawn) > max(1.0, $drawn * 0.01)) $issues[] = ['code' => 'PRINCIPAL_NOT_RECONCILED', 'message' => 'Scheduled principal does not reconcile to the drawn amount within 1%.'];

        $fees = DB::table('contract_fees')->where('contract_id', $contractId)->get();
        $unresolved = $fees->whereNotIn('classification_status', ['REVIEWED', 'REJECTED']);
        if ($unresolved->isNotEmpty()) $issues[] = ['code' => 'FEE_CLASSIFICATION_PENDING', 'message' => $unresolved->count() . ' fee/cost line(s) are not independently reviewed.'];
        $reviewedIntegral = $fees->where('classification_status', 'REVIEWED')->where('integral', 1);
        if ($reviewedIntegral->contains(fn ($f) => ! in_array($f->cashflow_direction, ['RECEIVED', 'PAID'], true))) $issues[] = ['code' => 'FEE_DIRECTION_MISSING', 'message' => 'A reviewed integral fee/cost has no cash-flow direction.'];

        $received = (float) $reviewedIntegral->where('cashflow_direction', 'RECEIVED')->sum('amount');
        $paid = (float) $reviewedIntegral->where('cashflow_direction', 'PAID')->sum('amount');
        $initialNet = $drawn - $received + $paid;
        if ($drawn > 0 && $initialNet <= 0) $issues[] = ['code' => 'INITIAL_NET_INVALID', 'message' => 'Fee-adjusted initial net investment is not positive.'];

        $metrics = ['drawn_amount' => $drawn, 'scheduled_principal' => $principalDue, 'integral_fees_received' => $received, 'integral_costs_paid' => $paid, 'initial_net_investment' => $initialNet, 'schedule_rows' => $schedule->count(), 'unresolved_fee_lines' => $unresolved->count()];
        return $issues === [] ? ['contract_id' => $contractId, 'status' => 'READY', 'ready' => true, 'issues' => [], 'metrics' => $metrics] : $this->blocked($contractId, $issues, $metrics);
    }

    private function blocked(string $id, array $issues, array $metrics): array
    {
        return ['contract_id' => $id, 'status' => 'BLOCKED', 'ready' => false, 'issues' => $issues, 'metrics' => $metrics];
    }
}
