<?php
namespace App\Http\Controllers;

use App\Models\CollateralType;
use App\Models\CollateralRegister;
use App\Models\CollateralAllocation;
use App\Models\LoanBook;
use App\Models\Client;
use App\Imports\CollateralRegisterImport;
use Illuminate\Http\Request;
use App\Models\Import;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;

class CollateralController extends Controller
{
    /* ============================================================
       INERTIA PAGE ROUTES (UI Views)
    ============================================================ */

    /**
     * Render the Collateral Types management page
     */
    public function collateralType()
    {
        return Inertia::render('Collateral/Types', [
            'types' => CollateralType::paginate(10),
        ]);
    }
    

    /**
     * Render the Collateral Register Import page
     */
    public function importView()
    {
        return Inertia::render('Collateral/Import');
    }

    /**
     * Render the Auto Allocation page
     */
    public function allocateView()
        {
             $registerDates = CollateralRegister::select('registration_date')
                ->distinct()
                ->orderBy('registration_date', 'desc')
                ->pluck('registration_date');

            $collateralList = CollateralRegister::all();
            return Inertia::render('Collateral/Components/Allocate',
                [
                'collateralList' => $collateralList,
                 'registerDates' => $registerDates]
                );
        }

    /**
     * Render Collateral Allocations index page
     */
    public function indexAllocations(Request $request)
    {

            $query = CollateralAllocation::query();

            // --- FILTERS ---

            //  Filter by registration date (exact or range)
            if ($request->filled('reporting_period')) {
                $query->whereDate('reporting_period', '>=', $request->reporting_period);
            }

            //  Filter by collateral type
           // if ($request->filled('type_code')) {
            //     $query->where('type_code', '>=',$request->type_code);
            //}

            //  Filter by nominal value / market value / execution value range
            if ($request->filled('customer_id')) {
                $query->where('customer_id', 'like', '%'.$request->customer_id);
            }

            if ($request->filled('customer_name')) {
                $query->where('customer_name', 'like', '%' . $request->customer_name . '%');
            }

            // --- SORT AND PAGINATE ---
        $allocations = $query
                    ->orderByRaw('CAST(contract_id AS UNSIGNED) ASC')
                    ->paginate(10)
                    ->appends($request->all()); 

        return Inertia::render('Collateral/Index', [
                'allocations' => $allocations,
                 'filters' => $request->only([
                    'reporting_period',
                    'type_code',
                    'customer_id',
                    'customer_name',
                ]),
        ]);
    }


    /* ============================================================
       LOGIC / API ENDPOINTS
    ============================================================ */

    public function store(Request $request)
    {
        $request->validate([
            'type_code' => 'required|unique:collateral_types,type_code',
            'type_name' => 'required|string|max:255',
            'standard_haircut' => 'required|numeric|min:0|max:100',
            'realisation_period' => 'required|integer|min:1',
            'description' => 'nullable|string',
        ]);

        CollateralType::create($request->only(['type_code', 'type_name', 'standard_haircut', 'realisation_period','description']));

        return back()->with('success', 'Collateral type added successfully.');
    }

    public function update(Request $request, $id)
    {
        $collateralType = CollateralType::findOrFail($id);

        $request->validate([
            'type_code' => 'required|unique:collateral_types,type_code,' . $collateralType->id,
            'type_name' => 'required|string|max:255',
            'standard_haircut' => 'required|numeric|min:0|max:100',
            'realisation_period' => 'required|integer|min:1',
            'description' => 'nullable|string',
        ]);
        

        $collateralType->update($request->only(['type_code', 'type_name', 'standard_haircut', 'realisation_period','description']));

        return back()->with('success', 'Collateral type updated successfully.');
    }

    public function destroy($id)
    {
        $collateralType = CollateralType::findOrFail($id);
        $collateralType->delete();

        return back()->with('success', 'Collateral type deleted successfully.');
    }

    public function downloadSample()
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="loan_book_sample.csv"',
        ];
        $columns = [
                        'customer_id',
                        'customer_name',
                        'collateral_type',
                        'property_use',
                        'description',
                        'registration_date',
                        'expiry_date',
                        'valuation_date',
                        'nominal_value',
                        'execution_value'
                            ];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            fclose($file);
        };

       return response()->streamDownload($callback, 'collateral_registry_sample.csv', $headers);
    }

    
        /**
         * Collateral View Register 
         */

      public function viewRegister(Request $request)
{
    $query = CollateralRegister::query();

    // --- STRICT FILTERS ---

    // Registration date (exact or range)
    if ($request->filled('registration_date_from') && $request->filled('registration_date_to')) {
        $query->whereBetween('registration_date', [
            $request->registration_date_from,
            $request->registration_date_to,
        ]);
    } elseif ($request->filled('registration_date_from')) {
        $query->whereDate('registration_date', $request->registration_date_from);
    }

    // Collateral type (exact match)
    if ($request->filled('type_code')) {
        $query->whereHas('type', function ($q) use ($request) {
            $q->where('type_code', $request->type_code);
        });
    }

    // Customer ID (exact match)
    if ($request->filled('customer_id')) {
        $query->where('customer_id', 'like', '%'.$request->customer_id);
    }

    // Customer name (exact or partial, depending on what you prefer)
    if ($request->filled('customer_name')) {
        // Strict match
        //$query->where('customer_name', '=', $request->customer_name);

        // OR, for partial (case-insensitive)
        $query->where('customer_name', 'like', '%' . $request->customer_name . '%');
    }

    // --- STRICT ORDERING AND LIMIT ---
    $collateralRegisters = $query
        ->orderBy('registration_date', 'desc')
        ->paginate(10)
        ->appends($request->all());

    return Inertia::render('Collateral/Register', [
        'collateralRegisters' => $collateralRegisters,
        'filters' => $request->only([
            'registration_date_from',
            'registration_date_to',
            'collateral_type',
            'customer_id',
            'customer_name',
        ]),
    ]);
}


        /**
         * Import Collateral Register from uploaded file
         */

        public function importCollateralRegister(Request $request)
        {
            $request->validate([
                'file' => ['required', 'file', 'mimes:txt,csv'],
                'registration_date' => ['required', 'date_format:Y-m'],
            ]);

            try {
                $import = Import::create([
                    'name' => $request->file('file')->getClientOriginalName(),
                    'status' => 'pending',
                ]);

                $data = [
                    'source' => 'collateral',
                    'uploaded_by' => auth()->id(),
                    'registration_date' => $request->input('registration_date'), 
                ];

                Excel::import(new CollateralRegisterImport($import, $data), $request->file('file'));

                return redirect()
                    ->route('collateral.allocations.index')
                    ->with('success', 'Collateral register imported successfully.');
            } catch (\Throwable $e) {
                return back()->with('error', 'Import failed: ' . $e->getMessage());
            }
        }

        /**
         * Auto allocate collateral to loans based on allocation_basis
         */
        public function allocateAutomatically(Request $request)
        {
            $request->validate([
                'allocation_basis' => 'required|string|in:proportional,equal,descending,ascending',
                'reporting_year' => 'required|integer|min:2000|max:' . now()->year,
                'reporting_month' => 'required|integer|min:1|max:12',
                'registration_date' => 'nullable|date',
            ]);

            
            $reportingYear = $request->input('reporting_year');
            $reportingMonth = $request->input('reporting_month');

            // Fetch clients who have at least one loan and one collateral
            $customers = Client::whereHas('loanBooks', fn($q) => $q->where('carrying_amount', '>', 0))
                ->whereHas('collateralRegisters')
                ->get();

            // Debug: check if customers are fetched
            if ($customers->isEmpty()) {
                return back()->with('error', 'No clients with loans and collaterals found.');
            }

            // // Log debug info instead of dd() so execution continues
            // Log::debug('allocateAutomatically - customers fetched', [
            //     'Total clients' => $customers->count(),
            //     'First client loans' => optional($customers->first())->loanBooks->pluck('carrying_amount', 'contract_id'),
            //     'First client collaterals' => optional($customers->first())->collateralRegisters->pluck('execution_value', 'collateral_type'),
            // ]);

            foreach ($customers as $customer) {

                $loans = $customer->loanBooks()
                                    ->where('carrying_amount', '>', 0)
                                    ->where('reporting_year', $reportingYear)
                                    ->where('reporting_month', $reportingMonth)
                                    ->get();

                 $collaterals = $customer->collateralRegisters()
                                        ->whereDate('registration_date', $request->input('registration_date'))
                                        ->get();


                if ($loans->isEmpty() || $collaterals->isEmpty()) {
                    continue;
                }

                // Determine average haircut from collateral types
                $haircuts = [];
                foreach ($collaterals as $collateral) {
                    $collateralType = CollateralType::where('type_code', $collateral->collateral_type)->first();
                    if ($collateralType && $collateralType->standard_haircut !== null) {
                        $haircuts[] = $collateralType->standard_haircut;
                    }
                }
                $haircut = count($haircuts) > 0 ? array_sum($haircuts) / count($haircuts) : 20;
                $haircutFactor = $haircut;
                //$haircutFactor = 1 - ($haircut / 100);

                $totalCollateral = $collaterals->sum('execution_value');
                $totalExposure = $loans->sum('carrying_amount');

                if ($totalCollateral <= 0 || $totalExposure <= 0) {
                    continue;
                }

                // Sort loans if ascending or descending
                $loans = match ($request->allocation_basis) {
                    'ascending' => $loans->sortBy('carrying_amount'),
                    'descending' => $loans->sortByDesc('carrying_amount'),
                    default => $loans,
                };

                $available = $totalCollateral;
                $remainingLoans = $loans->count();

              foreach ($loans as $loan) {
                    if ($available <= 0) break;

                    $share = match ($request->allocation_basis) {
                        'equal' => $available / max(1, $remainingLoans),
                        default => min(($loan->carrying_amount / $totalExposure) * $totalCollateral, $available),
                    };

                    $share = $share / 100;

                    $discountedValueBeforeTime = $share * $haircutFactor; 

                    $collateralType = CollateralType::where('type_code', optional($collaterals->first())->collateral_type)->first();
                    $interestRate = $loan->interest_rate / 100; 
                    $realisationMonths = $collateralType->realisation_period ?? 1;
                    $realisationYears = $realisationMonths / 12;

                    $discounted = $discountedValueBeforeTime / pow(1 + $interestRate, $realisationYears);
                    $discounted = round($discounted, 2);

                    $coverage = $loan->carrying_amount > 0 
                                ? min(($discounted / $loan->carrying_amount) * 100, 100) 
                                : 0;

                   // Log::info('Discounted before saving', ['discounted' => $discounted]);

                    CollateralAllocation::updateOrCreate(
                        [
                            'reporting_year' => $reportingYear,
                            'reporting_month' => $reportingMonth,
                            'customer_id' => $customer->customer_id,
                            'collateral_register_id' => optional($collaterals->first())->id,
                            'contract_id' => $loan->contract_id,
                        ],
                        [
                            'customer_name' => $customer->name,
                            'total_customer_exposure' => $loan->carrying_amount,
                            'allocated_collateral' => $share,
                            'allocation_percentage' => min(round(($share / $totalCollateral) * 100, 2), 100),
                            'discounted_collateral' => $discounted,
                            'coverage_ratio' => max(0, min($coverage / 100, 1)),
                            'allocation_basis' => strtoupper($request->allocation_basis),
                            'allocation_notes' => 'Auto-allocated using customer_id & customer_name',
                        ]
                    );

                    // Update customer LGD based on coverage (per contract_id)
                    DB::statement("
                        UPDATE loan_books
                        SET customer_lgd = GREATEST(1 - ( ? / 100 ), 0)
                        WHERE contract_id = ?
                    ", [
                        $coverage,
                        $loan->contract_id,
                    ]);

                    $available -= $share;
                    $remainingLoans--;
                }

            }

            return redirect()->route('collateral.allocations.index')->with('success', 'Collateral auto-allocated per customer successfully.');
        }

    }
