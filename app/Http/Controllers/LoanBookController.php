<?php

namespace App\Http\Controllers;

use App\Imports\LoanBooksImport;
use App\Services\LoanImportService;
use App\Services\FieldMappingService;
use App\Models\Import;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use League\Csv\Reader;
use App\Models\LoanBook;
use App\Models\StageingRule;
use Illuminate\Http\Request;
use App\Models\LoanPortfolio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use App\Events\ImportProgress;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;


class LoanBookController extends Controller

{

    protected $fieldMappingService;
    
    public function __construct(FieldMappingService $fieldMappingService)
        {
            $this->fieldMappingService = $fieldMappingService;
        }

    public function index(Request $request)
    {
        $query = LoanBook::query()
            ->with('client')
            ->with('portfolio')
            ->orderBy('reporting_period', 'asc');
            // dd($query);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('contract_id', 'like', "%{$search}%")
                  ->orWhere('customer_id', 'like', "%{$search}%")
                  ->orWhereHas('client', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Apply year/month filters independently
        if ($request->filled('year')) {
            $query->where('reporting_year', (int) $request->input('year'));
        }

        if ($request->filled('month')) {
            $query->where('reporting_month', (int) $request->input('month'));
        }


        if ($request->filled('stage')) {
            $query->where('ifrs9stage_post_qualitative', $request->input('stage'));
        }

        if ($request->filled('portfolio')) {
            $query->where('loan_portfolio_id', $request->input('portfolio'));
        }

        $loanBooks = $query->paginate(10)->withQueryString();

        return Inertia::render('LoanBooks/Index', [
            'loanBooks' => $loanBooks,
            'filters' => $request->only(['search', 'year', 'month', 'stage', 'portfolio']),
            'portfolios' => LoanPortfolio::all(),
            'summary'   => $this->summary($request),

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
            'file' => ['required', 'file', 'mimes:txt,csv'],
            'loan_portfolio_id' => ['required'],
            'import_type' => ['required', 'in:legacy,custom'],
            'mapping' => ['nullable', 'array'], // Only used for custom import
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
                        'ifrs9_stage_prequalitative' => $this->calculateIfrs9Stage($record, $reportingPeriod),
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
       $query = LoanBook::query();

            // APPLY SAME FILTERS AS INDEX
            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function($q) use ($search) {
                    $q->where('contract_id', 'like', "%{$search}%")
                      ->orWhere('customer_id', 'like', "%{$search}%")
                      ->orWhereHas('client', function($q) use ($search) {
                          $q->where('name', 'like', "%{$search}%");
                      });
                });
            }

            if ($request->filled('year')) {
                $query->where('reporting_year', (int) $request->year);
            }

            if ($request->filled('month')) {
                $query->where('reporting_month', (int) $request->month);
            }

            if ($request->filled('stage')) {
                $query->where('ifrs9stage_post_qualitative', $request->input('stage'));
            }

            if ($request->filled('portfolio')) {
                $query->where('loan_portfolio_id', $request->input('portfolio'));
            }

            $result = $query->selectRaw('
                COUNT(*) as total_accounts,
                SUM(carrying_amount) as total_principal,
                SUM(ecl_value) as total_ecl,
                SUM(CASE WHEN ifrs9stage_post_qualitative = 1 THEN carrying_amount ELSE 0 END) as stage_1_exposure,
                SUM(CASE WHEN ifrs9stage_post_qualitative = 2 THEN carrying_amount ELSE 0 END) as stage_2_exposure,
                SUM(CASE WHEN ifrs9stage_post_qualitative = 3 THEN carrying_amount ELSE 0 END) as stage_3_exposure,
                COUNT(CASE WHEN ifrs9stage_post_qualitative = 1 THEN 1 END) as stage_1_count,
                COUNT(CASE WHEN ifrs9stage_post_qualitative = 2 THEN 1 END) as stage_2_count,
                COUNT(CASE WHEN ifrs9stage_post_qualitative = 3 THEN 1 END) as stage_3_count
                 ')->first();

            // Transform field names to match Vue expectations
            return [
                'total_loans' => $result->total_accounts,
                'total_balance' => $result->total_principal,
                'total_provision' => $result->total_ecl,
                'overdue_loans' => $result->overdue_loans,
                'stage_1_exposure' => $result->stage_1_exposure,
                'stage_2_exposure' => $result->stage_2_exposure,
                'stage_3_exposure' => $result->stage_3_exposure,
                'stage_1_count' => $result->stage_1_count,
                'stage_2_count' => $result->stage_2_count,
                'stage_3_count' => $result->stage_3_count,
            ];
            }

    public static function calculateIfrs9Stage($record, $reportingPeriod)
    {
        try {
            // Load thresholds
            $rule = StageingRule::first();
            $stage1 = $rule?->stage_1_threshold ?? 30;
            $stage3 = $rule?->stage_3_threshold ?? 90;

            // Prefer Days Past Due if available; fallback to days since create_date
            if (isset($record['overdue_days']) && is_numeric($record['overdue_days'])) {
                $dpd = (int)$record['overdue_days'];
            } else {
                if (empty($record['create_date'])) {
                    throw new \Exception("Missing create_date");
                }
                $createDateFromFile = $record['create_date'] instanceof Carbon
                    ? $record['create_date']
                    : Carbon::parse($record['create_date']);
                $reportingPeriodDate = Carbon::createFromFormat('Ym', $reportingPeriod)->endOfMonth();
                $dpd = $createDateFromFile->diffInDays($reportingPeriodDate);
            }

            if ($dpd <= $stage1) {
                return '1';
            } elseif ($dpd < $stage3) {
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
        $tableName = (new LoanBook())->getTable(); // loan_book table

        // Get all columns with details from the clients table
        $fields = $this->fieldMappingService->getTableColumns($tableName, true);

        return Inertia::render('LoanBooks/Import', [
            'portfolios' => LoanPortfolio::all(),
            'availableFields'   => array_keys($fields),
            'fieldDescriptions' => $fields,
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:txt,csv'],
            'loan_portfolio_id' => ['required'],
            'import_type' => ['required', 'in:legacy,custom'],
            'mapping' => ['nullable', 'array'],
        ]);

        $import = Import::create([
            'name' => $request->file('file')->getClientOriginalName(),
            'status' => 'pending',
        ]);

        Excel::import(
            new LoanBooksImport(
                $import,
                array_merge(
                    $request->only(['loan_portfolio_id','reporting_period','reporting_year','reporting_month']),
                    [
                        'import_type' => $request->import_type,
                        'mapping' => $request->input('mapping', []),
                    ]
                )
            ),
            $request->file('file')
        );

        return redirect()->route('loan_applications.loan-book')
                        ->with('success', 'Import started! You will be notified once it completes.');
    }

  public function importGroup(Request $request, LoanImportService $importService)
    {

        ini_set('max_execution_time', 300);

        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
            'reporting_period' => 'required',
            'loan_portfolio_id' => 'required',
        ]);

        try {
            // Create Import record first
            $import = Import::create([
                'name' => $request->file('file')->getClientOriginalName(),
                'status' => 'pending',
            ]);

            // Validate e-banker format before processing
            $file = $request->file('file');
            // $validationResult = $this->validateEbankerFormat($file);
            
            // if (!$validationResult['valid']) {
            //     $errorMessage = "E-Banker format validation failed: " . $validationResult['error'] . 
            //                    ". Please download the E-Banker template and ensure your file follows the correct format.";
                
            //     return back()->with('error', $errorMessage)
            //                 ->with('show_template_hint', true);
            // }

            // Pass the UploadedFile object directly to service
            $results = $importService->importGroupFile(
                $request->file('file'), 
                $request->reporting_period, 
                $request->loan_portfolio_id,
                $import // Pass the import record to service
            );

            return redirect()->route('loan_applications.loan-book')->with('success', "Import job queued successfully. File: {$results['file_name']}");
            
        } catch (\Exception $e) {
            $errorMessage = "Failed to import E-Banker file: " . $e->getMessage() . 
                          ". Please ensure your file matches the E-Banker format template.";
            
            return back()->with('error', $errorMessage)
                        ->with('show_template_hint', true);
        }
    }

    // private function validateEbankerFormat($file)
    // {
    //     try {
    //         $csv = \League\Csv\Reader::createFromPath($file->getPathname(), 'r');
    //         $csv->setHeaderOffset(0);
            
    //         $headers = $csv->getHeader();
    //         $records = iterator_to_array($csv->getRecords());
            
    //         // Check if file is empty
    //         if (empty($records)) {
    //             return ['valid' => false, 'error' => 'File is empty or contains no data rows'];
    //         }
            
    //         // Check for e-banker specific patterns
    //         $firstRow = $records[0];
    //         $firstCell = array_values($firstRow)[0] ?? '';
            
    //         // E-banker files typically start with serial numbers (1, 2, 3, etc.)
    //         if (!is_numeric($firstCell)) {
    //             // Look for context rows like "Loan Type :"
    //             if (stripos($firstCell, 'Loan Type') === false) {
    //                 return ['valid' => false, 'error' => 'File does not appear to be in E-Banker format. Expected serial numbers or Loan Type context'];
    //             }
    //         }
            
    //         // Validate minimum column count (e-banker format should have at least 20 columns)
    //         $columnCount = count($headers);
    //         if ($columnCount < 15) {
    //             return ['valid' => false, 'error' => "Insufficient columns. Expected at least 15 columns, found {$columnCount}"];
    //         }
            
    //         // Check for key e-banker columns (flexible check)
    //         $keyColumns = ['contract', 'customer', 'date', 'balance'];
    //         $foundKeyColumns = 0;
            
    //         foreach ($headers as $header) {
    //             foreach ($keyColumns as $keyCol) {
    //                 if (stripos($header, $keyCol) !== false) {
    //                     $foundKeyColumns++;
    //                     break;
    //                 }
    //             }
    //         }
            
    //         if ($foundKeyColumns < 2) {
    //             return ['valid' => false, 'error' => 'Missing key E-Banker columns. Please use the E-Banker template'];
    //         }
            
    //         return ['valid' => true, 'error' => null];
            
    //     } catch (\Exception $e) {
    //         return ['valid' => false, 'error' => 'Failed to read file: ' . $e->getMessage()];
    //     }
    // }

    public function downloadEbanker(Request $request)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="ebanker_loan_book_template.csv"'
        ];
        
        // E-Banker format columns based on the actual file structure
        $columns = [
            'Sr.',
            'Customer National A/C',
            'Name',
            'Value',
            'Maturity',
            'Tenure',
            'Moratoriu',
            'Interest',
            'Approved',
            'Disbursed',
            'Not Yet',
            'Principal',
            'Collateral',
            'Charge',
            'Interest',
            'Repaymer Carrying',
            'Principal',
            'Interest',
            'Total',
            'Arrears',
            '1 - 30 Days',
            '31 - 90 Days',
            '91 - 180 Days',
            '181 - 270 Days'
        ];

        $callback = function () use ($columns) {
            $file = fopen('php://output', 'w');
            
            // Add header row
            fputcsv($file, $columns);
            
            // Add context row for Loan Type
            fputcsv($file, ['Loan Type : 1050101-MAJIC Agricultural Loans']);
            
            // Add sample data rows matching the actual format
            $sampleData = [
                [1, 'CUST001', 'MUSECO', '500000.00', '31/12/2025', '60', '0', '15.00', '500000.00', '475000.00', '25000.00', '450000.00', 'PRIMARY', '50000.00', '67500.00', '567500.00', '450000.00', '67500.00', '517500.00', '0.00', '0.00', '0.00', '0.00', '0.00'],
                [2, 'CUST002', 'LENZIEMIL', '750000.00', '30/06/2026', '72', '0', '12.50', '750000.00', '712500.00', '37500.00', '675000.00', 'PRIMARY', '75000.00', '84375.00', '834375.00', '675000.00', '84375.00', '759375.00', '0.00', '0.00', '0.00', '0.00', '0.00'],
                [3, 'CUST003', 'Mach MILK', '1200000.00', '31/12/2027', '84', '6', '18.00', '1200000.00', '1140000.00', '60000.00', '1080000.00', 'SECONDARY', '120000.00', '194400.00', '1412400.00', '1080000.00', '194400.00', '1274400.00', '15000.00', '15000.00', '0.00', '0.00', '0.00'],
            ];
            
            foreach ($sampleData as $row) {
                fputcsv($file, $row);
            }
            
            // Add another context row for different loan type
            fputcsv($file, ['Loan Type : 1050102-MAJIC Industrial Loans']);
            
            // Add sample data for second loan type
            $industrialData = [
                [4, 'CUST004', 'Sunrise Engineering', '2500000.00', '30/09/2028', '96', '0', '17.00', '2500000.00', '2375000.00', '125000.00', '2250000.00', 'COMMERCIAL', '250000.00', '382500.00', '2882750.00', '2250000.00', '382500.00', '2632500.00', '0.00', '0.00', '0.00', '0.00', '0.00'],
                [5, 'CUST005', 'Riverside Transport', '1800000.00', '31/03/2029', '108', '3', '14.50', '1800000.00', '1710000.00', '90000.00', '1620000.00', 'PRIMARY', '180000.00', '234900.00', '2034900.00', '1620000.00', '234900.00', '1854900.00', '45000.00', '45000.00', '0.00', '0.00', '0.00'],
            ];
            
            foreach ($industrialData as $row) {
                fputcsv($file, $row);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
