<?php

namespace App\Http\Controllers;
use App\Models\LoanBook;
use App\Models\LoanPortfolio;
use App\Models\IndustryType;
use App\Models\ReportingPeriods;
use App\Models\ExpectedCreditLoss;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ExpectedCreditLossController extends Controller
{
   public function index(Request $request)
        {
            /*
            |--------------------------------------------------------------------------
            | GET THE LATEST CALCULATED REPORTING PERIOD
            |--------------------------------------------------------------------------
            */
            $latestPeriod = ReportingPeriods::where('ecl_calculated', 1)
                ->orderByDesc('period')
                ->value(DB::raw("DATE_FORMAT(period, '%Y-%m')"));

            /*
            |--------------------------------------------------------------------------
            | BASE LOAN BOOK QUERY (LATEST ECL ONLY)
            |--------------------------------------------------------------------------
            */
            $query = LoanBook::query()
                ->with('client')
                ->where('reporting_period', $latestPeriod)
                ->orderBy('contract_id', 'asc');

            /*
            |--------------------------------------------------------------------------
            | YEAR FILTER
            |--------------------------------------------------------------------------
            */
            if ($request->filled('year')) {
                $query->whereYear('reporting_period', $request->input('year'));
            }

            /*
            |--------------------------------------------------------------------------
            | MONTH FILTER
            |--------------------------------------------------------------------------
            */
            if ($request->filled('month')) {
                $query->whereMonth('reporting_period', $request->input('month'));
            }

            /*
            |--------------------------------------------------------------------------
            | PORTFOLIO FILTER
            |--------------------------------------------------------------------------
            */
            if ($request->filled('portfolio')) {
                $query->where('loan_portfolio_id', $request->input('portfolio'));
            }

            /*
            |--------------------------------------------------------------------------
            | SEARCH FILTER
            |--------------------------------------------------------------------------
            */
            if ($request->filled('search')) {
                $search = $request->input('search');

                $query->where(function($q) use ($search) {
                    $q->where('contract_id', 'like', "%{$search}%")
                    ->orWhere('external_identity_id', 'like', "%{$search}%")
                    ->orWhereHas('client', function($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
                });
            }

            /*
            |--------------------------------------------------------------------------
            | IFRS9 STAGE FILTER
            |--------------------------------------------------------------------------
            */
            if ($request->filled('stage')) {
                $query->where('ifrs9stage_pre_qualitative', $request->stage);
            }

            $loanBooks = $query->paginate(10)->withQueryString();

            return Inertia::render('ExpectedCreditLoss/Index', [
                'loanBooks'   => $loanBooks,
                'latestPeriod'=> $latestPeriod,
                'summary'     => $this->summary($request),
                'filters'     => $request->only([
                    'search',
                    'year',
                    'month',
                    'stage',
                    'portfolio'
                ]),
                'portfolios'  => LoanPortfolio::all(),
                'sectors'     => IndustryType::all(),
            ]);
        }

        public function summary(Request $request)
        {
            // Get the latest and previous ECL calculation periods
            $latestPeriod = ExpectedCreditLoss::select('reporting_period')
                ->orderBy('reporting_period', 'desc')
                ->value('reporting_period');
            
            $previousPeriod = ExpectedCreditLoss::select('reporting_period')
                ->where('reporting_period', '!=', $latestPeriod)
                ->orderBy('reporting_period', 'desc')
                ->value('reporting_period');

            // Get current loan book data (latest ECL calculation)
            $query = LoanBook::query()
                ->with('client')
                ->where('reporting_period', function($q) {
                    $q->selectRaw('MAX(period)')
                      ->from('reporting_periods')
                      ->where('ecl_calculated', 1);
                });

            // Apply same filters as index
            if ($request->filled('year')) {
                $query->whereYear('reporting_period', $request->input('year'));
            }

            if ($request->filled('month')) {
                $query->whereMonth('reporting_period', $request->input('month'));
            }

            if ($request->filled('portfolio')) {
                $query->where('loan_portfolio_id', $request->input('portfolio'));
            }

            if ($request->filled('stage')) {
                $query->where('ifrs9stage_pre_qualitative', $request->input('stage'));
            }

            // Get current summary data
            $currentExposure = $query->sum('carrying_amount');
            $currentLoss = $query->sum('ecl_value');
            $currentLoans = $query->count();
            
            // Get previous ECL calculation data for comparison
            $previousData = null;
            if ($previousPeriod) {
                $previousData = ExpectedCreditLoss::where('reporting_period', $previousPeriod)
                    ->selectRaw('SUM(total_ead) as total_exposure, SUM(total_ecl) as total_loss, SUM(total_loans) as total_loans')
                    ->first();
            }
            
            // Calculate differences
            $exposureChange = $previousData ? $currentExposure - $previousData->total_exposure : 0;
            $lossChange = $previousData ? $currentLoss - $previousData->total_loss : 0;
            $exposureChangePercent = $previousData && $previousData->total_exposure > 0 ? (($exposureChange / $previousData->total_exposure) * 100) : 0;
            $lossChangePercent = $previousData && $previousData->total_loss > 0 ? (($lossChange / $previousData->total_loss) * 100) : 0;

            // Get summary data
            $summary = [
                'total_calculations' => ExpectedCreditLoss::count(),
                'current_period' => $latestPeriod,
                'previous_period' => $previousPeriod,
                'total_exposure' => $currentExposure,
                'total_loss' => $currentLoss,
                'total_loans' => $currentLoans,
                'previous_exposure' => $previousData ? $previousData->total_exposure : 0,
                'previous_loss' => $previousData ? $previousData->total_loss : 0,
                'exposure_change' => $exposureChange,
                'loss_change' => $lossChange,
                'exposure_change_percent' => $exposureChangePercent,
                'loss_change_percent' => $lossChangePercent,
                'periods' => ExpectedCreditLoss::select('reporting_period')
                    ->distinct()
                    ->orderBy('reporting_period', 'desc')
                    ->pluck('reporting_period')
                    ->take(5),
                'stage_counts' => [
                    'stage_1' => $query->clone()->where('ifrs9stage_pre_qualitative', 1)->count(),
                    'stage_2' => $query->clone()->where('ifrs9stage_pre_qualitative', 2)->count(),
                    'stage_3' => $query->clone()->where('ifrs9stage_pre_qualitative', 3)->count(),
                ]
            ];

            return $summary;
        }

        public function create()
            {
                return Inertia::render('ExpectedCreditLoss/Create', [
                    'portfolios' => LoanPortfolio::select('id', 'name')->get(),
                    'sectors' => IndustryType::select('code', 'name')->get(),
                ]);
            }

         public function calculateECL(Request $request)
            {
                ini_set('max_execution_time', 300);
                $startTime = microtime(true);

                $validated = $request->validate([
                    'ecl_calculation_level' => 'required|in:portfolio,sector',
                    'ecl_calculation_id'    => 'nullable|required_if:ecl_calculation_level,portfolio|exists:loan_portfolios,id',
                    'ecl_calculation_code' => 'nullable|required_if:ecl_calculation_level,sector|exists:industry_types,code',
                    'reporting_period'     => 'required|date',
                    'pd_type'              => 'required|in:pd_prefli,pd_post_fli',
                    'lgd_type'             => 'required|in:customer_lgd,collection_lgd,both',
                ]);

                $level       = $validated['ecl_calculation_level'];
                $portfolioId = $validated['ecl_calculation_id'] ?? null;
                $sectorCode  = $validated['ecl_calculation_code'] ?? null;

                $periodDate = Carbon::parse($validated['reporting_period']);
                $period     = $periodDate->format('Y-m');

                /*
                |--------------------------------------------------------------------------
                | PD & LGD EXPRESSIONS
                |--------------------------------------------------------------------------
                */
                $pdExpr = $validated['pd_type'] === 'pd_prefli'
                    ? 'COALESCE(pd_prefli, pd_post_fli)'
                    : 'pd_post_fli';

                $lgdExpr = $validated['lgd_type'] === 'both'
                    ? '(IFNULL(customer_lgd,0) * IFNULL(collection_lgd,0))'
                    : ($validated['lgd_type'] === 'customer_lgd' ? 'customer_lgd' : 'collection_lgd');

                /*
                |--------------------------------------------------------------------------
                | BASE FILTER
                |--------------------------------------------------------------------------
                */
                $baseWhere = "reporting_period = ?";
                $bindings  = [$period];

                if ($level === 'portfolio') {
                    $baseWhere .= " AND loan_portfolio_id = ?";
                    $bindings[] = $portfolioId;
                }

                if ($level === 'sector') {
                    $baseWhere .= " AND industry_code = ?";
                    $bindings[] = $sectorCode;
                }

                /*
                |--------------------------------------------------------------------------
                | STEP 1: UPDATE LOAN BOOK
                |--------------------------------------------------------------------------
                */
                DB::statement("
                    UPDATE loan_books
                    SET 
                        pd_post_fli = IFNULL($pdExpr, 0),
                        lgd_value   = IFNULL($lgdExpr, 0),
                        ecl_value   = IFNULL($pdExpr, 0) * IFNULL($lgdExpr, 0) * IFNULL(carrying_amount, 0)
                    WHERE $baseWhere
                ", $bindings);

                /*
                |--------------------------------------------------------------------------
                | STEP 2: GROUP BY IFRS9 STAGE 
                |--------------------------------------------------------------------------
                */
                $grouped = DB::table('loan_books')
                    ->selectRaw("
                        ifrs9stage_pre_qualitative,
                        SUM(COALESCE(carrying_amount, 0) + COALESCE(commitments, 0) * COALESCE(facility_utilisation_rate, 1)) AS total_ead,
                        SUM(ecl_value) AS total_ecl,
                        AVG($pdExpr) AS avg_pd,
                        AVG($lgdExpr) AS avg_lgd,
                        COUNT(*) AS total_loans
                    ")
                    ->whereRaw($baseWhere, $bindings)
                    ->groupBy('ifrs9stage_pre_qualitative')
                    ->get();

                /*
                |--------------------------------------------------------------------------
                | STEP 3: SAVE INTO EXPECTED CREDIT LOSS
                |--------------------------------------------------------------------------
                */
                foreach ($grouped as $row) {
                    ExpectedCreditLoss::updateOrCreate(
                        [
                            'reporting_period'        => $period,
                            'ifrs9_stage'             => $row->ifrs9stage_pre_qualitative,
                            'ecl_calculation_level'  => $level,
                            'ecl_calculation_id'     => $portfolioId, 
                            'ecl_calculation_code'   => $sectorCode,
                        ],
                        [
                            'total_ead'              => $row->total_ead,
                            'total_ecl'              => $row->total_ecl,
                            'lgd_value_used'         => $row->avg_lgd,
                            'pd_value_used'          => $row->avg_pd,
                            'total_loans'            => $row->total_loans,
                            'last_reporting_period' => $period,
                        ]
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | STEP 4: MARK PERIOD AS CALCULATED
                |--------------------------------------------------------------------------
                */
                $endTime   = microtime(true);
                $timeTaken = round(($endTime - $startTime) / 60, 2);

                $periodFull = $periodDate->format('Y-m-01');

                ReportingPeriods::updateOrCreate(
                    ['period' => $periodFull],
                    [   
                        'reporting_year'         => (int)$periodDate->format('Y'),
                        'reporting_month'        => (int)$periodDate->format('m'),
                        'reporting_period'      => $periodFull,
                        'ecl_calculated'        => true,
                        'ecl_calculation_time'  => $timeTaken,
                        'ecl_calculation_level' => $level,
                        'ecl_calculation_id'    => $portfolioId,
                        'ecl_calculation_code'  => $sectorCode,
                    ]
                );

                return redirect()
                    ->route('expected-credit-loss.index')
                    ->with('success', "ECL calculated at {$level} level in {$timeTaken} minutes for {$period}.");
            }


    public function exportECL(Request $request)
        {
            $exportable = [
                'contract_id',
                'carrying_amount',
                'pd_value',
                'lgd_value',
                'ecl_value',
                'ifrs9stage_pre_qualitative',
                'ifrs9stage_post_qualitative',
                'reporting_period',
                'customer_name',
                'create_date',
                'due_date',
                'remaining_tenor',
                'industry_code',
                'collateral_id',
                'allocated_gross_value',
                'allocated_discounted_value',
                'commitments',
                'collection_lgd',
                'customer_lgd',
                'pd_prefli',
                'pd_post_fli',
                'fli_adj',

            ];

            // Validate request
            $validated = $request->validate([
                'portfolios' => 'required|exists:loan_portfolios,id',
                'reporting_period' => 'required|date',
                'mode' => 'required|in:summary,totalLoanBook',
                'columns' => 'nullable|array',
                'columns.*' => 'string|in:' . implode(',', $exportable),
            ]);

            $portfolioId = $validated['portfolios'];
            $period = Carbon::parse($validated['reporting_period'])->format('Y-m');
            $mode = $validated['mode'];
            $columns = $validated['columns'] ?? [];

            $filename = 'ecl_report_' . $period . '_' . $mode . '.csv';
            $filePath = storage_path('app/' . $filename);
            $handle = fopen($filePath, 'w+');

            if ($mode === 'summary') {
                $data = DB::table('loan_books')
                    ->selectRaw('
                        ifrs9stage_pre_qualitative as stage,
                        SUM(carrying_amount) as total_ead,
                        AVG(pd_value_used) as avg_pd,
                        AVG(lgd_value_used) as avg_lgd,
                        SUM(ecl_value) as total_ecl
                    ')
                    ->where('loan_portfolio_id', $portfolioId)
                    ->where('reporting_period', $period)
                    ->groupBy('ifrs9stage_pre_qualitative')
                    ->get();

                fputcsv($handle, ['Stage', 'Total EAD', 'PD', 'LGD', 'Total ECL']);

                $totalEAD = 0;
                $totalPD = 0;
                $totalLGD = 0;
                $totalECL = 0;
                $count = 0;

                foreach ($data as $row) {
                    fputcsv($handle, [
                        $row->stage,
                        $row->total_ead,
                        $row->avg_pd,
                        $row->avg_lgd,
                        $row->total_ecl,
                    ]);

                    $totalEAD += $row->total_ead;
                    $totalPD += $row->avg_pd;
                    $totalLGD += $row->avg_lgd;
                    $totalECL += $row->total_ecl;
                    $count++;
                }

                if ($count > 0) {
                    fputcsv($handle, [
                        'Total',
                        $totalEAD,
                        $totalPD / $count,
                        $totalLGD / $count,
                        $totalECL,
                    ]);
                }
            } elseif ($mode === 'totalLoanBook') {
                $selected = !empty($columns) ? $columns : $exportable;

                $data = DB::table('loan_books')
                    ->select($selected)
                    ->where('loan_portfolio_id', $portfolioId)
                    ->where('reporting_period', $period)
                    ->get();

                if ($data->isEmpty()) {
                    fputcsv($handle, ['No records found for the given criteria.']);
                } else {
                    fputcsv($handle, $selected); // headers

                    foreach ($data as $row) {
                        $rowData = [];
                        foreach ($selected as $col) {
                            $rowData[] = $row->$col;
                        }
                        fputcsv($handle, $rowData);
                    }
                }
            }

            fclose($handle);
            return response()->download($filePath)->deleteFileAfterSend(true);
        }


}
