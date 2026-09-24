<?php

namespace App\Http\Controllers;

use App\Services\Eir\DisbursementService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Drawdowns and undrawn commitments as the finance team reads them (spec v3
 * section 9.1): per facility the approved amount, every drawdown with its date
 * and reference, the total drawn and the undrawn commitment, and a total row.
 *
 * Read-only. Loading a new file goes through EIR Data Intake with the
 * disbursements type, or the eir:import-disbursements command.
 */
class EirDrawdownController extends Controller
{
    public function __construct()
    {
        // Read-only screen: the EIR view permission, as on Coverage and EIR Data.
        $this->middleware(['auth', 'permission:eir.view']);
    }

    public function index(Request $request, DisbursementService $service)
    {
        $asOf = $this->asOf($request->input('as_of'));
        $search = trim((string) $request->input('search', ''));

        $facilities = $service->facilities($asOf, $search === '' ? null : $search);

        // Largest undrawn commitment first: that is the figure the screen is
        // for, and a facility with none needs no attention.
        usort($facilities, function ($a, $b) {
            $left = $a['undrawn'] ?? -1;
            $right = $b['undrawn'] ?? -1;

            return $right <=> $left ?: strcmp($a['contract_id'], $b['contract_id']);
        });

        return Inertia::render('Eir/Drawdowns', [
            'asOf' => $asOf->toDateString(),
            'search' => $search,
            'facilities' => $facilities,
            'totals' => $service->totals($facilities),
            'sourceLabels' => [
                DisbursementService::SOURCE_DRAWDOWNS => 'Drawdown rows loaded',
                DisbursementService::SOURCE_LOAN_BOOK => 'Loan book, disbursed column',
                DisbursementService::SOURCE_CONTRACT_MASTER => 'Contract master',
                DisbursementService::SOURCE_NONE => 'Not known',
            ],
        ]);
    }

    /** Any date the operator asks for, today when they ask for none or for nonsense. */
    private function asOf($value): CarbonImmutable
    {
        $text = trim((string) $value);
        if ($text === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) !== 1) {
            return CarbonImmutable::today();
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $text)->startOfDay();
        } catch (\Throwable) {
            return CarbonImmutable::today();
        }
    }
}
