<?php

namespace App\Http\Controllers;

use App\Models\CreditLossData;
use App\Models\CreditLossDefinition;
use App\Models\LoanPortfolio;
use App\Models\Import;
use App\Imports\CreditLossDataImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CreditLossDataController extends Controller
{
   public function index(Request $request)
        {
            $totalRecords = CreditLossData::count();
            $period = $request->input('period');
            $definitionId = $request->input('definition_id');

            // Get all portfolios for filters and grouping
            $portfolios = LoanPortfolio::orderBy('name')->get();

            $definitions = CreditLossDefinition::all();
            $uniquePeriods = CreditLossData::distinct()
                ->pluck('period')
                ->sort()
                ->reverse()
                ->values();

            // one table of every entered row (below); kept for the screens that still read it
            $portfolioData = [];

            // The measures the model is calibrated on: those derived from MAIIC's own staged
            // loan books (the bridge refreshes them with the correlation sweep), and the
            // external series entered here, which the bridge adds as EXT_ measures.
            $derivedCodes = ['NPL_RATIO', 'STAGE3_SHARE', 'DEFAULT_RATE_12M'];
            $derived = DB::table('credit_loss_series')->whereIn('proxy_code', $derivedCodes)->orderByDesc('observation_period')->get(['proxy_code', 'observation_period', 'value'])
                ->groupBy('observation_period')->map(fn ($g, $p) => ['period' => substr($p, 0, 4) . '-' . substr($p, 4, 2)] + $g->pluck('value', 'proxy_code')->map(fn ($v) => (float) $v)->all())->values();
            $external = DB::table('credit_loss_series')->where('proxy_code', 'like', 'EXT\_%')
                ->selectRaw('proxy_code, max(proxy_name) name, count(*) n, min(observation_period) first, max(observation_period) last, max(known_from) known')->groupBy('proxy_code')->get();
            $rows = CreditLossData::with(['definition', 'creator', 'portfolio'])
                ->when($period, function ($q) use ($period) { [$y, $m] = explode('-', $period); $q->whereYear('period', $y)->whereMonth('period', $m); })
                ->when($definitionId, fn ($q) => $q->where('definition_id', $definitionId))
                ->when($request->filled('portfolio_id'), fn ($q) => $request->input('portfolio_id') === 'external' ? $q->whereNull('portfolio_id') : $q->where('portfolio_id', $request->input('portfolio_id')))
                ->orderByDesc('period')->orderBy('definition_id')->paginate(15)->withQueryString();

            return Inertia::render('FLI/CreditLossData/Index', [
                'rows' => $rows,
                'derived' => $derived,
                'externalSeries' => $external,
                'lastSweep' => DB::table('audit_logs')->where('action', 'FLI Step Launched')->where('new_values', 'like', '%fli:correlate%')->max('created_at'),
                'canGovern' => (bool) $request->user()?->can('eir.govern'),
                'totalRecords' => $totalRecords,
                'portfolios' => $portfolios,
                'portfolioData' => $portfolioData,
                'definitions' => $definitions,
                'uniquePeriods' => $uniquePeriods,
                'filters' => $request->only(['period', 'definition_id', 'portfolio_id']),
            ]);
        }

        public function period(Request $request, $period)
            {
                // Validate period format
                if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
                    abort(404, 'Invalid period format');
                }

                $creditLossData = CreditLossData::with(['portfolio', 'definition', 'creator'])
                    ->where('period', $period)
                    ->orderBy('portfolio_id')
                    ->orderBy('definition_id')
                    ->get();

                // Get all unique periods for navigation
                $allPeriods = CreditLossData::select('period')
                    ->distinct()
                    ->orderBy('period', 'desc')
                    ->pluck('period')
                    ->toArray();

                $definitions = CreditLossDefinition::all()->keyBy('id');

                return Inertia::render('FLI/CreditLossData/PeriodView', [
                    'period' => $period,
                    'creditLossData' => $creditLossData,
                    'definitions' => $definitions,
                    'portfolios' => LoanPortfolio::all(),
                    'allPeriods' => $allPeriods,
                ]);
            }

    public function createDefinition()
    {
        return Inertia::render('FLI/CreditLossData/CreateDefinition');
    }

    public function storeDefinition(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:macro_credit_loss_definitions,code'], // Fixed: added code field
            'name' => ['required', 'string', 'max:100'], // Fixed: max 100 to match migration
            'description' => ['nullable', 'string'],
        ]);

        CreditLossDefinition::create($validated);

        return redirect()->route('credit-loss-data.index')
                         ->with('success', 'Credit loss definition created successfully!');
    }

    public function create()
    {
        return Inertia::render('FLI/CreditLossData/Create', [
            'portfolios' => LoanPortfolio::all(),
            'definitions' => CreditLossDefinition::all(),
        ]);
    }

    public function importView()
    {
        return Inertia::render('FLI/CreditLossData/Import', [
            'portfolios' => LoanPortfolio::all(),
            'definitions' => CreditLossDefinition::all(),
            // The column names the importer recognises per definition, so the
            // screen can say which of the file's columns will be read.
            'aliases' => ImportSampleController::creditLossAliases(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            // empty = an external or industry series (FLI module D-F7), not one MAIIC portfolio
            'portfolio_id' => ['nullable', 'exists:loan_portfolios,id'],
            'period' => ['required', 'date'], // We validate it's a date first
            'published_at' => ['nullable', 'date'],
            'definition_id' => ['required', 'exists:macro_credit_loss_definitions,id'],
            'value' => ['nullable', 'numeric'],
            'source' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);


        $carbonDate = Carbon::parse($validated['period']);
        $validated['period'] = $carbonDate->startOfMonth()->toDateString(); 

        $validated['created_by'] = auth()->id();

        CreditLossData::updateOrCreate(
            [
                'portfolio_id' => $validated['portfolio_id'],
                'definition_id' => $validated['definition_id'],
                'period' => $validated['period'], 
            ],
            $validated
        );
    
        return redirect()->route('credit-loss-data.index')
                        ->with('success', 'Credit loss data added successfully!');
    }
    public function edit(CreditLossData $creditLossData)
        {
            $portfolios = LoanPortfolio::all();
            $definitions = CreditLossDefinition::all();

            return Inertia::render('FLI/CreditLossData/Create', [
                'portfolios' => $portfolios,
                'definitions' => $definitions,
                'editData' => $creditLossData,
            ]);
        }


    public function update(Request $request, CreditLossData $creditLossData)
    {
        $validated = $request->validate([
            'value' => ['nullable', 'numeric'],
            'source' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'published_at' => ['nullable', 'date'],
        ]);

        $creditLossData->update($validated);

        return redirect()->back()->with('success', 'Credit loss data updated successfully!');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx|max:2048',
            'portfolio_id' => ['nullable', 'exists:loan_portfolios,id'],
        ]);

        try {
            // Create an import record for tracking
            $import = Import::create([
                'name' => $request->file('file')->getClientOriginalName(),
                'status' => 'pending',
            ]);

            $userId = auth()->id();

            // Import the data
            Excel::import(
                new CreditLossDataImport($import, $request->portfolio_id,  $userId),
                $request->file('file')
            );

            return redirect()
                ->route('credit-loss-data.index')
                ->with('success', 'Credit Loss Data imported successfully.');
        } catch (\Throwable $e) {
            if (isset($import)) {
                $import->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                ]);
            }

            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }
    }

    public function destroy(CreditLossData $creditLossData)
    {
        $creditLossData->delete();

        return redirect()->back()->with('success', 'Credit loss data deleted.');
    }

    public function getDefinitions()
    {
        return response()->json(CreditLossDefinition::all());
    }
    
}