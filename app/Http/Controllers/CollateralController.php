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
use Carbon\Carbon;


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

            // Filter by reporting period (exact month)
            if ($request->filled('reporting_period')) {
                $period = date('Y-m-d', strtotime($request->reporting_period . '-01'));
                $query->where('reporting_period', $period);
            }

            // Filter by collateral type
            if ($request->filled('type_code')) {
                $query->whereHas('collateralRegister', function ($q) use ($request) {
                    $q->where('collateral_type', $request->type_code);
                });
            }
            // Filter by customer ID
            if ($request->filled('customer_id')) {
                $query->where('customer_id', 'like', '%' . $request->customer_id . '%');
            }

            // Filter by customer name
            if ($request->filled('customer_name')) {
                $query->where('customer_name', 'like', '%' . $request->customer_name . '%');
            }

            // --- CLONE QUERY FOR SUMMARY METRICS BEFORE PAGINATION ---
            $summaryQuery = clone $query;

            // --- SORT AND PAGINATE ---
            $allocations = $query
                ->orderByRaw('CAST(reporting_period AS DATE) DESC')
                ->paginate(10)
                ->appends($request->all()); 

            // --- SUMMARY METRICS USING FRESH QUERY ---
            $summary = [
                'total_allocations' => $summaryQuery->count(),
                'total_exposure' => $summaryQuery->sum('total_customer_exposure'),
                'total_discounted' => $summaryQuery->sum('discounted_collateral'),
                'average_coverage' => $summaryQuery->avg('coverage_ratio')
            ];

            // --- DISTINCT TYPES for filter dropdown ---
            $types = CollateralType::select('type_code')->distinct()->pluck('type_code');

            return Inertia::render('Collateral/Index', [
                'allocations' => $allocations,
                'summary' => $summary,
                'types' => $types,
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

                $from = Carbon::createFromFormat('Y-m', $request->registration_date_from)
                    ->startOfMonth()
                    ->toDateString();

                $to = Carbon::createFromFormat('Y-m', $request->registration_date_to)
                    ->endOfMonth()
                    ->toDateString();

                $query->whereBetween('period', [$from, $to]);

            } elseif ($request->filled('registration_date_from')) {

                $from = Carbon::createFromFormat('Y-m', $request->registration_date_from)
                    ->startOfMonth()
                    ->toDateString();

                $to = Carbon::createFromFormat('Y-m', $request->registration_date_from)
                    ->endOfMonth()
                    ->toDateString();

                $query->whereBetween('period', [$from, $to]);
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
                ->orderBy('period', 'desc')
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
                'period' => ['required', 'date_format:Y-m'],
                'mapping' => ['nullable', 'array'],
                'import_type' => ['nullable', 'string', 'in:custom,legacy'],
            ]);

            try {
                $import = Import::create([
                    'name' => $request->file('file')->getClientOriginalName(),
                    'status' => 'pending',
                    'settings' => [
                        'period' => $request->input('period'),
                        'mapping' => $request->input('mapping', []),
                        'import_type' => $request->input('import_type', 'custom'),
                    ]
                ]);

                $mapping = $request->input('mapping', []);
                $importType = $request->input('import_type', 'custom');

                // Add period to mapping for the import class
                $mapping['period'] = $request->input('period');

                Excel::import(new CollateralRegisterImport($import, $mapping, $importType), $request->file('file'));

                return redirect()
                    ->route('collateral.register.index')
                    ->with('success', 'Collateral register import has been queued for processing.');
            } catch (\Throwable $e) {
                return back()->with('error', 'Import failed: ' . $e->getMessage());
            }
        }

        /**
         * Auto allocate collateral to loans based on allocation_basis
         */
        public function allocateAutomatically(Request $request)
        {
            ini_set('max_execution_time', 300);

            $request->validate([
                'allocation_basis' => 'required|string|in:proportional,equal,descending,ascending',
                'reporting_year' => 'required|integer|min:2000|max:' . now()->year,
                'reporting_month' => 'required|integer|min:1|max:12',
                'period' => 'nullable|date',
            ]);

            
            $reportingYear = $request->input('reporting_year');
            $reportingMonth = $request->input('reporting_month');
            $reportingPeriod = date('Y-m-d', strtotime("{$reportingYear}-{$reportingMonth}-01"));
            $period = $request->input('period') ?: $reportingPeriod;

            // Fetch clients who have at least one loan and one collateral
          // Step 1: try via Client model (if it exists)
            $customers = Client::whereHas('loanBooks', function ($q) use ($reportingYear, $reportingMonth) {
                $q->where('carrying_amount', '>', 0)
                ->where('reporting_year', $reportingYear)
                ->where('reporting_month', $reportingMonth);
            })->whereHas('collateralRegisters', function ($q) use ($period) {
                if ($period) {
                    $q->whereDate('period', $period);
                }
            })->get();

            // Step 2: fallback directly from loan_books
            if ($customers->isEmpty()) {
                $customers = LoanBook::where('carrying_amount', '>', 0)
                    ->where('reporting_year', $reportingYear)
                    ->where('reporting_month', $reportingMonth)
                    ->select('customer_id')
                    ->distinct()
                    ->get();

                if ($customers->isEmpty()) {
                    return back()->with('error', 'No clients with loans and collaterals found.');
                }

                // Mark fallback mode
                $fallback = true;
            } else {
                $fallback = false;
            }

            // // Log debug info instead of dd() so execution continues
            // Log::debug('allocateAutomatically - customers fetched', [
            //     'Total clients' => $customers->count(),
            //     'First client loans' => optional($customers->first())->loanBooks->pluck('carrying_amount', 'contract_id'),
            //     'First client collaterals' => optional($customers->first())->collateralRegisters->pluck('execution_value', 'collateral_type'),
            // ]);

            foreach ($customers as $customer) {

               if ($fallback) {
                    // fetch loans directly from loan_books
                    $loans = LoanBook::where('customer_id', $customer->customer_id)
                        ->where('carrying_amount', '>', 0)
                        ->where('reporting_year', $reportingYear)
                        ->where('reporting_month', $reportingMonth)
                        ->get();

                    // fetch collaterals directly
                    $collaterals = CollateralRegister::where('customer_id', $customer->customer_id)
                        ->when($period, fn($q) => $q->whereDate('period', $period))
                        ->get();
                } else {
                    // existing Client-based relationships
                    $loans = $customer->loanBooks()
                        ->where('carrying_amount', '>', 0)
                        ->where('reporting_year', $reportingYear)
                        ->where('reporting_month', $reportingMonth)
                        ->get();

                    $collaterals = $customer->collateralRegisters()
                        ->when($period, fn($q) => $q->whereDate('period', $period))
                        ->get();
                }

                if ($loans->isEmpty() || $collaterals->isEmpty()) continue;


                // Determine average haircut from collateral types
                $haircuts = [];
                foreach ($collaterals as $collateral) {
                    $collateralType = CollateralType::where('type_code', $collateral->collateral_type)->first();
                    if ($collateralType && $collateralType->standard_haircut !== null) {
                        $haircuts[] = $collateralType->standard_haircut;
                    }
                }
                $haircut = count($haircuts) > 0 ? array_sum($haircuts) / count($haircuts) : 0;
                $haircutFactor = $haircut;

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
                    
                    $allocatedGrossValue = $share;
                    $allocatedDiscountedValue = $discounted;
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
                            'reporting_period' => $reportingPeriod,
                            'customer_name' => $customer->name,
                            'total_customer_exposure' => $loan->carrying_amount,
                            'allocated_collateral' => $share,
                            'allocation_percentage' => min(round(($share / $totalCollateral) * 100, 2), 100),
                            'discounted_collateral' => $discounted,
                            'coverage_ratio' => max(0, min($coverage / 100, 1)),
                            'allocation_basis' => strtoupper($request->allocation_basis),
                            'realisation_months' => $realisationMonths,
                            'allocation_notes' => 'Auto-allocated using customer_id & customer_name',
                        ]
                    );

                    // Update customer LGD based on coverage (per contract_id)
                    DB::statement("
                        UPDATE loan_books
                        SET 
                            customer_lgd = GREATEST(1 - (? / 100), 0),
                            allocated_gross_value = ?,
                            allocated_discounted_value = ?
                        WHERE contract_id = ?
                    ", [
                        $coverage,
                        $allocatedGrossValue,
                        $allocatedDiscountedValue,
                        $loan->contract_id,
                    ]);

                    $available -= $share;
                    $remainingLoans--;
                }

            }

            return redirect()->route('collateral.allocations.index')->with('success', 'Collateral auto-allocated per customer successfully.');
        }

        public function updateBook(Request $request)
            {
                $request->validate([
                    'contract_id' => 'required|string|exists:loan_books,contract_id',
                ]);

                $loanBook = LoanBook::where('contract_id', $request->input('contract_id'))->first();

                if (!$loanBook) {
                    return back()->with('error', 'Loan book entry not found for the given contract ID.');
                }

                // Recalculate customer LGD based on existing collateral allocations
                $totalDiscountedCollateral = CollateralAllocation::where('contract_id', $loanBook->contract_id)
                    ->sum('discounted_collateral');

                $coverage = $loanBook->carrying_amount > 0 
                            ? min(($totalDiscountedCollateral / $loanBook->carrying_amount) * 100, 100) 
                            : 0;

                $customerLgd = GREATEST(1 - ($coverage / 100), 0);

                $loanBook->customer_lgd = $customerLgd;
                $loanBook->save();

                return back()->with('success', 'Loan book entry updated successfully.');
            }
            
            
        public function downloadAllocationReport(Request $request) {
            // Validate input
            $request->validate([
                'reporting_year' => 'required|integer|min:2000|max:' . now()->year,
                'reporting_month' => 'required|integer|min:1|max:12',
                'customer_id' => 'nullable|string',
            ]);

            $reportingYear = $request->query('reporting_year');
            $reportingMonth = $request->query('reporting_month');
            $customerId = $request->query('customer_id');

            $fileName = "collateral_allocation_report_{$reportingYear}_{$reportingMonth}.csv";

            $headers = [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"$fileName\"",
            ];

            $callback = function () use ($reportingYear, $reportingMonth, $customerId) {
                $file = fopen('php://output', 'w');

                fputcsv($file, [
                    'Customer ID',
                    'Customer Name',
                    'Contract ID',
                    'Total Customer Exposure',
                    'Allocated Collateral',
                    'Allocation Percentage',
                    'Discounted Collateral',
                    'Coverage Ratio',
                    'Allocation Basis',
                    'Allocation Notes',
                ]);

                $query = CollateralAllocation::where('reporting_year', $reportingYear)
                    ->where('reporting_month', $reportingMonth);

                if ($customerId) {
                    $query->where('customer_id', $customerId);
                }

                $allocations = $query->get();

                foreach ($allocations as $allocation) {
                    fputcsv($file, [
                        $allocation->customer_id,
                        $allocation->customer_name,
                        $allocation->contract_id,
                        $allocation->total_customer_exposure,
                        $allocation->allocated_collateral,
                        $allocation->allocation_percentage,
                        $allocation->discounted_collateral,
                        $allocation->coverage_ratio,
                        $allocation->allocation_basis,
                        $allocation->allocation_notes,
                    ]);
                }

                fclose($file);
            };

            return response()->streamDownload($callback, $fileName, $headers);
        }


    }
