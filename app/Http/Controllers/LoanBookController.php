<?php

namespace App\Http\Controllers;

use App\Imports\LoanBooksImport;
use App\Models\Import;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use League\Csv\Reader;
use App\Models\LoanBook;
use Illuminate\Http\Request;
use App\Models\LoanPortfolio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use App\Events\ImportProgress;

class LoanBookController extends Controller
{
    public function index(Request $request)
    {
        $query = LoanBook::query()
            ->with('client')
            ->orderBy('reporting_period', 'desc');
            // dd($query);

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
            $period = $request->input('year') . str_pad($request->input('month'), 2, '0', STR_PAD_LEFT);
            $query->where('reporting_period', $period);
        }

        if ($request->filled('overdue')) {
            $query->where('overdue_days', $request->boolean('overdue') ? '>' : '=', 0);
        }

        $loanBooks = $query->paginate(10)->withQueryString();

        return Inertia::render('LoanBooks/Index', [
            'loanBooks' => $loanBooks,
            'filters' => $request->only(['search', 'year', 'month', 'overdue']),
            'portfolios' => LoanPortfolio::all(),

        ]);
    }

    /*public function import(Request $request)
    {
       Log::debug($request->portfolio);
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:csv,txt|max:10240',
            'reporting_period' => 'required|date_format:Y-m',
            'portfolio' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        try {
            $file = $request->file('file');
            $data = array_map(function($line) {
                // Remove BOM and any other invisible characters
                $line = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $line);
                return str_getcsv(trim($line));
            }, file($file->getRealPath()));

            // Validate empty file
            if (count($data) <= 1) {
                return response()->json(['error' => 'The CSV file is empty or contains only headers'], 422);
            }

            // Get reporting period components
            try {
                $reportingDate = Carbon::createFromFormat('Y-m', $request->reporting_period);
                $reportingYear = $reportingDate->year;
                $reportingMonth = $reportingDate->month;
                $reportingPeriod = $reportingYear . str_pad($reportingMonth, 2, '0', STR_PAD_LEFT);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Invalid reporting period format. Please use YYYY-MM format.'], 422);
            }

            // Validate headers (case-insensitive)
            $headers = array_map('strtolower', array_map('trim', $data[0]));
            $requiredHeaders = ['loan_id', 'customer_id', 'issue_date', 'due_date', 'principal_balance', 'overdue_days'];
            $missingHeaders = array_diff($requiredHeaders, $headers);

            if (!empty($missingHeaders)) {
                return response()->json([
                    'error' => 'Missing required headers: ' . implode(', ', $missingHeaders)
                ], 422);
            }

            // Map headers to their positions
            $headerMap = array_flip($headers);

            // Process rows
            DB::beginTransaction();

            try {
                foreach (array_slice($data, 1) as $index => $row) {
                    $rowNumber = $index + 2; // Adding 2 to account for 1-based indexing and header row

                    // Validate row length
                    if (count($row) !== count($headers)) {
                        throw new \Exception("Row {$rowNumber} has " . count($row) . " columns but should have " . count($headers) . " columns");
                    }

                    // Clean and validate data
                    $row = array_map('trim', $row);

                    // Validate required fields are not empty
                    foreach ($requiredHeaders as $header) {
                        if (empty($row[$headerMap[$header]])) {
                            throw new \Exception("Row {$rowNumber}: {$header} cannot be empty");
                        }
                    }

                    // Validate dates
                    try {
                        $issueDate = Carbon::createFromFormat('d/m/Y', $row[$headerMap['issue_date']]);
                        $dueDate = Carbon::createFromFormat('d/m/Y', $row[$headerMap['due_date']]);
                    } catch (\Exception $e) {
                        throw new \Exception("Row {$rowNumber}: Invalid date format. Dates should be in dd/mm/yyyy format");
                    }

                    // Validate date logic
                    if ($issueDate->gt($dueDate)) {
                        throw new \Exception("Row {$rowNumber}: Issue date cannot be after due date");
                    }

                    // Validate numeric values
                    $principalBalance = str_replace(['E', ','], '', $row[$headerMap['principal_balance']]);
                    if (!is_numeric($principalBalance) || $principalBalance <= 0) {
                        throw new \Exception("Row {$rowNumber}: Invalid principal balance. Must be a positive number");
                    }

                    $overdueDays = $row[$headerMap['overdue_days']];
                    if (!is_numeric($overdueDays) || $overdueDays < 0) {
                        throw new \Exception("Row {$rowNumber}: Invalid overdue days. Must be a non-negative number");
                    }

                    LoanBook::updateOrCreate(
                        [
                            'contract_id' => $row[$headerMap['loan_id']],
                            'reporting_period' => $reportingPeriod,
                        ],
                        [
                            'external_identity_id' => $row[$headerMap['customer_id']],
                            'create_date' => $issueDate,
                            'due_date' => $dueDate,
                            'principal_balance' => $principalBalance,
                            'overdue_days' => $overdueDays,
                            'portfolio_type' => $request->portfolio,
                            'reporting_year' => $reportingYear,
                            'reporting_month' => $reportingMonth,
                        ]
                    );
                }

                DB::commit();
                return response()->json(['message' => 'File imported successfully'], 200);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }*/

    public function downloadSample()
    {
        $headers = ['Content-Type' => 'text/csv'];
        $columns = ['contract_id', 'external_identity_id', 'reporting_year', 'reporting_month', 'due_date', 'principal_balance'];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);
            fclose($file);
        };

        return response()->stream($callback, 200, $headers)->header('Content-Disposition', 'attachment; filename="loan_book_sample.csv"');
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'], // 10MB max
            'reporting_period' => ['required', 'date_format:Y-m'],
            'portfolio' => ['required', 'string', 'in:retail,corporate,sme'],
        ]);

        try {
            DB::beginTransaction();

            $file = $request->file('file');
            $csv = Reader::createFromPath($file->getPathname(), 'r');
            $csv->setHeaderOffset(0);

            // Validate CSV structure
            $headers = $csv->getHeader();
            $requiredHeaders = [
                'contract_id',
                'external_identity_id',
                'create_date',
                'due_date',
                'principal_balance',
                'overdue_days'
            ];

            $missingHeaders = array_diff($requiredHeaders, $headers);
            if (!empty($missingHeaders)) {
                throw new \Exception('Missing required columns: ' . implode(', ', $missingHeaders));
            }

            $records = $csv->getRecords();
            $period = Carbon::createFromFormat('Y-m', $request->reporting_period);
            $reportingPeriod = $period->format('Ym');

            // Check if records already exist for this period and portfolio
            $existingCount = LoanBook::where('reporting_period', $reportingPeriod)
                                   ->where('portfolio_group', $request->portfolio)
                                   ->count();
            if ($existingCount > 0) {
                throw new \Exception("Records already exist for {$period->format('F Y')} in {$request->portfolio} portfolio. Please delete existing records first.");
            }

            $batchSize = 1000;
            $batch = [];
            $processed = 0;
            $totalRecords = iterator_count($csv->getRecords());
            $errors = [];
            $rowNum = 1;

            foreach ($records as $record) {
                $rowNum++;
                try {
                    // Validate required fields
                    foreach ($requiredHeaders as $field) {
                        if (empty($record[$field])) {
                            throw new \Exception("Missing value for {$field}");
                        }
                    }

                    // Validate dates
                    $createDate = Carbon::parse($record['create_date']);
                    $dueDate = Carbon::parse($record['due_date']);

                    if ($createDate > $dueDate) {
                        throw new \Exception('Create date cannot be after due date');
                    }

                    // Validate numeric values
                    if (!is_numeric($record['principal_balance']) || $record['principal_balance'] < 0) {
                        throw new \Exception('Invalid principal balance');
                    }

                    if (!is_numeric($record['overdue_days']) || $record['overdue_days'] < 0) {
                        throw new \Exception('Invalid overdue days');
                    }

                    // Calculate expected loss provision based on overdue days
                    $expectedLossProvision = $this->calculateExpectedLossProvision(
                        $record['overdue_days'],
                        $record['principal_balance']
                    );

                    $batch[] = [
                        'contract_id' => $record['contract_id'],
                        'external_identity_id' => $record['external_identity_id'],
                        'portfolio_group' => $request->portfolio,
                        'reporting_year' => $period->year,
                        'reporting_month' => $period->month,
                        'reporting_period' => $reportingPeriod,
                        'create_date' => $createDate,
                        'due_date' => $dueDate,
                        'principal_balance' => $record['principal_balance'],
                        'overdue_days' => $record['overdue_days'],
                        'expected_loss_provision' => $expectedLossProvision,
                        'overdue_status' => $this->calculateOverdueStatus($record['overdue_days']),
                        'is_month_end' => true,
                        'ifrs9_stage' => $this->calculateIfrs9Stage($record, $reportingPeriod),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $processed++;

                    if (count($batch) >= $batchSize) {
                        LoanBook::insert($batch);
                        $batch = [];

                        // Calculate and broadcast progress
                        $progress = round(($processed / $totalRecords) * 100);
                        broadcast(new ImportProgress($progress))->toOthers();
                    }
                } catch (\Exception $e) {
                    $errors[] = "Row {$rowNum}: {$e->getMessage()}";
                }
            }

            // Insert remaining records
            if (!empty($batch)) {
                LoanBook::insert($batch);
            }

            // If there were any errors, rollback and report them
            if (!empty($errors)) {
                DB::rollBack();
                return response()->json([
                    'error' => 'Import failed with the following errors:',
                    'import_errors' => $errors
                ], 422);
            }

            DB::commit();

            return response()->json([
                'message' => "Successfully imported {$processed} loan records for {$period->format('F Y')} ({$request->portfolio} portfolio).",
                'processed' => $processed,
                'total' => $totalRecords
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to import loan book: ' . $e->getMessage()
            ], 500);
        }
    }

    public function summary(Request $request)
    {
        $year = $request->input('year', date('Y'));
        $month = $request->input('month', date('m'));
        $period = $year . str_pad($month, 2, '0', STR_PAD_LEFT);

        $summary = LoanBook::where('reporting_period', $period)
            ->select([
                DB::raw('COUNT(*) as total_loans'),
                DB::raw('SUM(principal_balance) as total_balance'),
                DB::raw('COUNT(CASE WHEN overdue_days > 0 THEN 1 END) as overdue_loans'),
                DB::raw('SUM(expected_loss_provision) as total_provision')
            ])
            ->first();

        return response()->json($summary);
    }

    public static function calculateIfrs9Stage($record, $reportingPeriod)
    {
        try {
            if (empty($record['create_date'])) {
                throw new \Exception("Missing create_date");
            }

            // Check if create_date is already a Carbon instance
            $createDateFromFile = $record['create_date'] instanceof Carbon
                ? $record['create_date']
                : Carbon::parse($record['create_date']);

            $reportingPeriodDate = Carbon::createFromFormat('Ym', $reportingPeriod)->endOfMonth();

            $days = $createDateFromFile->diffInDays($reportingPeriodDate);

            if ($days <= 30) {
                return '1';
            } elseif ($days <= 90) {
                return '2';
            } else {
                return '3';
            }
        } catch (\Exception $e) {
            Log::error("IFRS9 Stage calculation failed: " . $e->getMessage());
            return null; 
        }
    }


    // private function calculateOverdueStatus($days)
    // {
    //     if ($days === 0) return 'Current';
    //     if ($days <= 30) return 'Watch';
    //     if ($days <= 90) return 'Substandard';
    //     if ($days <= 180) return 'Doubtful';
    //     return 'Loss';
    // }

    private function calculateExpectedLossProvision($overdueDays, $principalBalance)
    {
        $provisionRate = match(true) {
            $overdueDays === 0 => 0.01, // 1% for current loans
            $overdueDays <= 30 => 0.05, // 5% for watch loans
            $overdueDays <= 90 => 0.25, // 25% for substandard loans
            $overdueDays <= 180 => 0.50, // 50% for doubtful loans
            default => 1.00, // 100% for loss loans
        };

        return $principalBalance * $provisionRate;
    }
    public function createImport(): Response
    {

        return Inertia::render('LoanBooks/Import', [
            'portfolios' => LoanPortfolio::all(),
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:txt,csv'],
            'loan_portfolio_id' => ['required'],
        ]);

        $import = Import::create([
            'name' => $request->file('file')->getClientOriginalName(),
            'status' => 'pending',
        ]);
        Excel::import(new LoanBooksImport($import, $request->only([
            'loan_portfolio_id',
            'reporting_period',
            'reporting_year',
            'reporting_month',
        ])), $request->file('file'));

        return redirect()->route('loan_applications.loan-book')->with('success', 'Import started! You will be notified once it completes.');

    }
}
