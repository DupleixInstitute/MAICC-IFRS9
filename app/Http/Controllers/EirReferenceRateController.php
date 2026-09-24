<?php

namespace App\Http\Controllers;

use App\Models\ReferenceRate;
use App\Services\Eir\ReferenceRateImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * The reference-rate series (the Reserve Bank prime lending rate) as the
 * finance team reads it: every dated rate, the change it brought, and the
 * audit trail of how each delivered row was read. Read-only; loading a new
 * file goes through EIR Data Intake with the reference_rates type.
 */
class EirReferenceRateController extends Controller
{
    public function __construct()
    {
        // Read-only screen: the EIR view permission, as on Coverage and EIR Data.
        $this->middleware(['auth', 'permission:eir.view']);
    }

    public function index(Request $request, ReferenceRateImportService $service)
    {
        $indexes = DB::table('reference_rate_series')->distinct()->orderBy('index_code')->pluck('index_code')->all();

        $index = strtoupper(trim((string) $request->input('index', '')));
        if (! in_array($index, $indexes, true)) {
            $index = $indexes[0] ?? ReferenceRate::DEFAULT_INDEX;
        }

        $rows = ReferenceRate::query()
            ->where('index_code', $index)
            ->orderBy('effective_date')
            ->orderBy('id')
            ->get();

        // Change from the previous row, walked oldest first; the page shows
        // newest first so the rate in force today is at the top.
        $series = [];
        $previous = null;
        $repaired = 0;
        foreach ($rows as $row) {
            $rate = (float) $row->rate;
            $change = $previous === null ? null : round($rate - $previous, 5);
            $isChange = $previous === null || abs($change) >= 0.000005;
            if (str_starts_with(strtoupper((string) $row->interpretation), 'REPAIRED')) {
                $repaired++;
            }
            $series[] = [
                'id' => $row->id,
                'effective_date' => $row->effective_date->toDateString(),
                'rate' => $rate,
                'change' => $change,
                'is_change' => $isChange,
                'source_row' => $row->source_row,
                'as_delivered' => $row->as_delivered,
                'interpretation' => $row->interpretation,
                'loaded_at' => $row->created_at?->toDateTimeString(),
            ];
            $previous = $rate;
        }

        return Inertia::render('Eir/ReferenceRates', [
            'index' => $index,
            'indexes' => $indexes,
            'summary' => $service->summary($index) + [
                'repaired_rows' => $repaired,
                'last_loaded_at' => $rows->max('created_at')?->toDateTimeString(),
            ],
            'series' => array_reverse($series),
            'tolerancePp' => \App\Services\Eir\SpreadDerivationService::TOLERANCE_PP,
        ]);
    }
}
