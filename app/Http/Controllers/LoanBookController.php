<?php

namespace App\Http\Controllers;

use App\Imports\LoanBooksImport;
use App\Services\LoanImportService;
use App\Services\FieldMappingService;
use App\Models\Import;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use App\Support\Imports\ImportSpecValidator;
use App\Support\Imports\ImportTemplateBuilder;
use Inertia\Inertia;
use Inertia\Response;
use League\Csv\Reader;
use App\Models\LoanBook;
use Illuminate\Http\Request;
use App\Models\LoanPortfolio;
use App\Services\Reports\EclBuildUpService;
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
            ->with('contractEir:id,contract_id,eir_effective_annual,eir_nominal_annual,rate_type,rate_source,calculation_status,locked_at')
            ->orderBy('reporting_period', 'desc')
            ->orderBy('contract_id');
        $this->applyFilters($query, $request);

        $loanBooks = $query->paginate(15)->withQueryString();

        // Each row carries its IFRS 9 lineage (PD before and after FLI, the
        // horizon PD, LGD, EAD build-up, ECL and the check against the saved
        // figure), its locked EIR and the fit and scenario set it ran under.
        $buildUp = app(EclBuildUpService::class);
        $rows = collect($loanBooks->items());
        $fits = $buildUp->fits($rows->pluck('fli_fit_id')->filter()->unique()->values()->all());
        $sets = $buildUp->sets($rows->pluck('fli_set_id')->filter()->unique()->values()->all());
        $loanBooks->setCollection($loanBooks->getCollection()->map(function ($loan) use ($fits, $sets) {
            $loan->setAttribute('lineage', EclBuildUpService::lineage((object) $loan->getAttributes()));
            $loan->setAttribute('eir', $this->eirOf($loan->contractEir));
            $loan->setAttribute('sector', EclBuildUpService::sectorLabel($loan->industry_type));
            $loan->setAttribute('fli_by_scenario_list', collect(json_decode((string) $loan->fli_by_scenario, true) ?: [])
                ->map(fn ($v, $k) => ['scenario' => $k, 'weight' => (float) ($v['weight'] ?? 0), 'pd' => isset($v['pd']) ? (float) $v['pd'] : null])->values()->all());
            $loan->setAttribute('fit', $loan->fli_fit_id ? ($fits[(int) $loan->fli_fit_id] ?? ['id' => (int) $loan->fli_fit_id]) : null);
            $loan->setAttribute('scenario_set', $loan->fli_set_id ? ($sets[(int) $loan->fli_set_id] ?? ['id' => (int) $loan->fli_set_id]) : null);
            $loan->makeHidden(['build_basis', 'fli_by_scenario', 'contractEir']);

            return $loan;
        }));

        $period = $this->periodOf($request);

        return Inertia::render('LoanBooks/Index', [
            'loanBooks' => $loanBooks,
            'filters' => $request->only(['search', 'year', 'month', 'stage', 'portfolio', 'product_group', 'sector']),
            'portfolios' => LoanPortfolio::all(),
            'summary'   => $this->summary($request),
            // the month-ends that hold loans, newest first, for the period picker
            'periods'   => LoanBook::query()->select('reporting_period')->distinct()->orderByDesc('reporting_period')->pluck('reporting_period'),
            // the product groups and sectors present in the month shown
            'productGroups' => $period ? LoanBook::where('reporting_period', $period)->whereNotNull('product_group')->distinct()->orderBy('product_group')->pluck('product_group') : [],
            'sectors' => $period ? LoanBook::where('reporting_period', $period)->whereNotNull('industry_type')->distinct()->orderBy('industry_type')->pluck('industry_type')
                ->map(fn ($t) => ['value' => $t, 'label' => EclBuildUpService::sectorLabel($t)])->values() : [],
        ]);
    }

    /** The month shown: the year and month asked for, else the latest loan book. */
    private function periodOf(Request $request): ?string
    {
        if ($request->filled('year') && $request->filled('month')) {
            return sprintf('%04d-%02d', (int) $request->input('year'), (int) $request->input('month'));
        }

        return LoanBook::max('reporting_period');
    }

    /** The table's filters, shared by the list and its summary so the strip matches the rows. */
    private function applyFilters($query, Request $request): void
    {
        // Default to the latest reporting period so the book opens on the
        // current (calculated) figures instead of page 1 of the oldest
        // import. Picking a year/month overrides this.
        if (! $request->filled('year') && ! $request->filled('month')) {
            $latestPeriod = LoanBook::max('reporting_period');
            if ($latestPeriod) {
                $query->where('reporting_period', $latestPeriod);
            }
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('contract_id', 'like', "%{$search}%")
                  ->orWhere('customer_id', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhereHas('client', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }
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
        if ($request->filled('product_group')) {
            $query->where('product_group', $request->input('product_group'));
        }
        if ($request->filled('sector')) {
            $query->where('industry_type', $request->input('sector'));
        }
    }

    /**
     * The EIR to show for a contract: the locked original rate only. An EIR
     * that is not locked (pending, calculated but not approved, in error) is
     * shown by its status, never as a rate.
     */
    private function eirOf($eir): array
    {
        if (! $eir) {
            return ['locked' => false, 'rate' => null, 'status' => 'No EIR record'];
        }
        $locked = $eir->locked_at !== null && $eir->eir_effective_annual !== null;
        $status = ucfirst(strtolower(str_replace('_', ' ', (string) ($eir->calculation_status ?: 'PENDING'))));

        return ['locked' => $locked, 'rate' => $locked ? (float) $eir->eir_effective_annual : null, 'status' => $locked ? 'Locked' : $status,
            'rate_type' => $eir->rate_type, 'locked_at' => $locked ? (string) $eir->locked_at : null];
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

    /** The standard layout's sample file, from the import spec registry (key loan-book). */
    public function downloadSample()
    {
        return app(ImportTemplateController::class)->template('loan-book', app(ImportTemplateBuilder::class));
    }

    public function summary(Request $request)
    {
        $query = LoanBook::query();
        $this->applyFilters($query, $request);
        $ead = EclBuildUpService::eadSql();

        $result = $query->selectRaw("
            COUNT(*) as total_accounts,
            SUM(carrying_amount) as total_carrying,
            SUM(COALESCE(commitments,0)) as total_undrawn,
            SUM({$ead}) as total_ead,
            SUM(ecl_value) as total_ecl,
            SUM(CASE WHEN ifrs9stage_post_qualitative = 3 THEN {$ead} ELSE 0 END) as stage_3_exposure,
            COUNT(CASE WHEN ifrs9stage_post_qualitative = 1 THEN 1 END) as stage_1_count,
            COUNT(CASE WHEN ifrs9stage_post_qualitative = 2 THEN 1 END) as stage_2_count,
            COUNT(CASE WHEN ifrs9stage_post_qualitative = 3 THEN 1 END) as stage_3_count
        ")->first();

        // EAD is the exposure the ECL engine measured: the carrying amount
        // plus the undrawn commitment times the utilisation rate. Coverage is
        // ECL over that EAD, as on the dashboard.
        return [
            'total_loans' => (int) $result->total_accounts,
            'total_carrying' => (float) $result->total_carrying,
            'total_undrawn' => (float) $result->total_undrawn,
            'total_ead' => (float) $result->total_ead,
            'total_provision' => (float) $result->total_ecl,
            'ecl_coverage' => $result->total_ead > 0 ? ($result->total_ecl / $result->total_ead) * 100 : 0,
            'stage_3_exposure' => (float) $result->stage_3_exposure,
            'stage_1_count' => (int) $result->stage_1_count,
            'stage_2_count' => (int) $result->stage_2_count,
            'stage_3_count' => (int) $result->stage_3_count,
        ];
    }

    public function createImport(): Response
    {
        $tableName = (new LoanBook())->getTable(); // loan_book table

        // Get all columns with details from the clients table
        $fields = $this->fieldMappingService->getTableColumns($tableName, true);

        return Inertia::render('LoanBooks/Import', [
            'portfolios' => LoanPortfolio::all(),
            'recentImports' => Import::where('kind', 'loan_book')->orderByDesc('id')->limit(15)->get(['id', 'name', 'status', 'records', 'failed_records', 'failed_file_path', 'created_at']),
            'importCount' => Import::count(),
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

        // the file against the column spec before anything is written (key loan-book); a mapped
        // file is checked through its mapping, under the names the importer reads
        $file = $request->file('file');
        $check = (new ImportSpecValidator())->checkFile('loan-book', $file->getRealPath(), $file->getClientOriginalName(),
            $request->import_type === 'custom' ? ['mapping' => (array) $request->input('mapping', []), 'field_aliases' => ImportTemplateController::fieldAliases('loan-book')] : []);
        if (! $check['ok']) {
            return ImportSpecValidator::refuse($check);
        }

        $import = Import::create([
            'kind' => 'loan_book',
            'name' => $request->file('file')->getClientOriginalName(),
            'status' => 'pending',
        ]);

        Excel::import(
            // the column mapping the user chose is read flat (CSV header => field), beside
            // the portfolio and the period; the import type selects the standard or mapped reader
            new LoanBooksImport(
                $import,
                array_merge(
                    array_filter((array) $request->input('mapping', []), fn ($v) => is_string($v) && $v !== ''),
                    $request->only(['loan_portfolio_id', 'reporting_period', 'reporting_year', 'reporting_month'])
                ),
                (string) $request->import_type
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

        // the E-Banker report against its layout before anything is written (key loan-book-ebanker)
        $check = (new ImportSpecValidator())->checkFile('loan-book-ebanker', $request->file('file')->getRealPath(), $request->file('file')->getClientOriginalName());
        if (! $check['ok']) {
            return ImportSpecValidator::refuse($check);
        }

        try {
            // Create Import record first
            $import = Import::create([
                'kind' => 'loan_book',
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

    /** The E-Banker loan book report layout, from the import spec registry (key loan-book-ebanker). */
    public function downloadEbanker(Request $request)
    {
        return app(ImportTemplateController::class)->template('loan-book-ebanker', app(ImportTemplateBuilder::class));
    }
}
