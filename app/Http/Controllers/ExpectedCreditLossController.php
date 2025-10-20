<?php

namespace App\Http\Controllers;
use App\Models\LoanBook;
use App\Models\LoanPortfolio;
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
            $query = LoanBook::query()
                ->with('client')
                ->orderBy('reporting_period', 'desc');

            
            $query->whereIn('reporting_period', function($subQuery) {
                $subQuery->select('period')
                    ->from('reporting_periods')
                    ->where('ecl_calculated', true);
            });


            // Apply filters
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

            if ($request->filled('year') && $request->filled('month')) {
                $period = $request->input('year') . '-' . str_pad($request->input('month'), 2, '0', STR_PAD_LEFT);
                $query->where('reporting_period', $period);
            }

            if ($request->filled('stage')) {
                $query->where('ifrs9stage_pre_qualitative', $request->string('stage'));
                $query->where('ifrs9stage_pre_qualitative');
            }

            $loanBooks = $query->paginate(10)->withQueryString();

            return Inertia::render('ExpectedCreditLoss/Index', [
                'loanBooks' => $loanBooks,
                'filters' => $request->only(['search', 'year', 'month', 'overdue']),
                'portfolios' => LoanPortfolio::all(),
            ]);
        }

        public function create()
            {
                return Inertia::render('ExpectedCreditLoss/Create', [
                    'portfolios' => LoanPortfolio::select('id', 'name')->get(),
                ]);
            }


  public function calculateECL(Request $request)
        {
            ini_set('max_execution_time', 300);
            $startTime = microtime(true);

            $validated = $request->validate([
                'portfolios' => 'required|exists:loan_portfolios,id',
                'reporting_period' => 'required|date',
            ]);

            $portfolioId = $validated['portfolios'];
            $periodDate = Carbon::parse($validated['reporting_period']);
            $year = $periodDate->format('Y') . '-01-01';
            $month = $periodDate->format('Y-m') . '-01';
            $period = $periodDate->format('Y-m');

            // Step 1: Update ECLs
            DB::statement("
                UPDATE loan_books
                SET ecl_value = IFNULL(pd_value, 0) * IFNULL(lgd_value, 0) * IFNULL(principal_balance, 0)
                WHERE loan_portfolio_id = ? AND reporting_period = ?
            ", [$portfolioId, $period]);

            // Step 2: Calculate grouped values by IFRS9 stage
            $grouped = DB::table('loan_books')
                ->selectRaw('
                    ifrs9stage_pre_qualitative,
                    SUM(principal_balance) as total_ead,
                    SUM(ecl_value) as total_ecl,
                    AVG(pd_value) as avg_pd,
                    AVG(lgd_value) as avg_lgd,
                    COUNT(*) as total_loans
                ')
                ->where('loan_portfolio_id', $portfolioId)
                ->where('reporting_period', $period)
                ->groupBy('ifrs9stage_pre_qualitative')
                ->get();

            // Step 3: Store each group into ExpectedCreditLoss
            foreach ($grouped as $row) {
                ExpectedCreditLoss::updateOrCreate(
                    [
                        'reporting_period' => $period,
                        'ifrs9_stage' => $row->ifrs9stage_pre_qualitative
                    ],
                    [
                        'total_ead' => $row->total_ead,
                        'total_ecl' => $row->total_ecl,
                        'lgd_value_used' => $row->avg_lgd,
                        'pd_value_used' => $row->avg_pd,
                        'total_loans' => $row->total_loans,
                        'last_reporting_period' => $period,
                    ]
                );
            }

            // Step 4: Mark reporting period as calculated
            $endTime = microtime(true);
            $timeTaken = round(($endTime - $startTime) / 60, 2);

            ReportingPeriods::updateOrCreate(
                ['period' => $validated['reporting_period']],
                [
                    'reporting_year' => $year,
                    'reporting_month' => $month,
                    'reporting_period' => $period,
                    'ecl_calculated' => true,
                    'ecl_calculation_time' => $timeTaken,
                ]
            );

            return back()->with([
                'success' => "ECL calculation complete in {$timeTaken}s for {$period}. Data saved by stage!"
            ]);
        }

        public function exportECL(Request $request)
        {
            $exportable = [
                'contract_id',
                'principal_balance',
                'pd_value',
                'lgd_value',
                'ecl_value',
                'ifrs9stage_pre_qualitative',
                'reporting_period',
                'external_identity_id',
                'create_date',
                'due_date',
                'contract_status',
                'overdue_days'
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
                        SUM(principal_balance) as total_ead,
                        AVG(pd_value) as avg_pd,
                        AVG(lgd_value) as avg_lgd,
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
