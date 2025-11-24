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

            $portfolioData = [];

            foreach ($portfolios as $portfolio) {
                $query = CreditLossData::with(['definition', 'creator'])
                    ->where('portfolio_id', $portfolio->id)
                    ->orderBy('period', 'desc')
                    ->orderBy('definition_id');
                    
                if ($period) {
                    [$year, $month] = explode('-', $period);
                    $query->whereYear('period', $year)
                        ->whereMonth('period', $month);
                }


                if ($definitionId) {
                    $query->where('definition_id', $definitionId);
                }

                if ($request->has('portfolio_id')) {
                    $query->where('portfolio_id', $request->input('portfolio_id'));
                }

                $portfolioData[$portfolio->id] = $query->paginate(5, ['*'], "portfolio_{$portfolio->id}_page")
                                                    ->withQueryString();
            }

            return Inertia::render('FLI/CreditLossData/Index', [
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
            'code' => ['required', 'string', 'max:50', 'unique:credit_loss_definitions,code'], // Fixed: added code field
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
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'portfolio_id' => ['required', 'exists:loan_portfolios,id'],
            'period' => ['required', 'date'], // We validate it's a date first
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
        ]);

        $creditLossData->update($validated);

        return redirect()->back()->with('success', 'Credit loss data updated successfully!');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx|max:2048',
            'portfolio_id' => ['required', 'exists:loan_portfolios,id'],
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