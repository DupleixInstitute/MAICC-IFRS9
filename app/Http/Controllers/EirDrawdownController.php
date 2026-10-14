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

        // the fee capitalised into each drawdown (type-301 fee debits on the
        // loan account the same day, spec 7.8.4), included in its amount
        $ids = collect($facilities)->flatMap(fn ($f) => array_column($f['tranches'], 'id'))->all();
        $capitalised = $ids !== [] && \Illuminate\Support\Facades\Schema::hasColumn('contract_disbursements', 'capitalised_fees')
            ? \Illuminate\Support\Facades\DB::table('contract_disbursements')->whereIn('id', $ids)->pluck('capitalised_fees', 'id')->all() : [];
        foreach ($facilities as &$f) {
            foreach ($f['tranches'] as &$t) {
                $t['capitalised_fees'] = isset($capitalised[$t['id']]) ? (float) $capitalised[$t['id']] : null;
            }
            unset($t);
            $f['capitalised_fees'] = array_sum(array_map(fn ($t) => (float) ($t['capitalised_fees'] ?? 0), $f['tranches']));
        }
        unset($f);

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
