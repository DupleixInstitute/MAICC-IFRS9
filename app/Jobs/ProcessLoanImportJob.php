<?php

namespace App\Jobs;

use App\Models\Import;
use App\Models\LoanBook;
use App\Models\LoanPortfolio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessLoanImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filePath;
    protected $fileName;
    protected $reportingPeriod;
    protected $portfolioId;
    protected $totalRows;
    protected $importedCount;
    protected $loanContext;
    protected $portfolio;
    protected $import; // Add import record

    public function __construct(string $filePath, string $fileName, string $reportingPeriod, int $portfolioId, Import $import = null)
    {
        $this->filePath = $filePath;
        $this->fileName = $fileName;
        $this->reportingPeriod = $reportingPeriod;
        $this->portfolioId = $portfolioId;
        $this->import = $import; // Store import record
        $this->totalRows = 0;
        $this->importedCount = 0;
        $this->loanContext = null;
    }

    public function handle()
    {
        // Disable debug bar to save memory
        if (class_exists(\Barryvdh\Debugbar\Facades\Debugbar::class)) {
            \Barryvdh\Debugbar\Facades\Debugbar::disable();
        }

        try {
            Log::info("Job handle method called");
            
            // Update import status to processing
            if ($this->import) {
                Log::info("Updating import status to processing for import ID: " . $this->import->id);
                $this->import->update(['status' => 'processing', 'started_at' => now()]);
            } else {
                Log::warning("Import record is null in handle method");
            }

            $this->portfolio = LoanPortfolio::find($this->portfolioId);
            
            // Handle both Y-m and Ym formats
            if (strpos($this->reportingPeriod, '-') !== false) {
                $reportingDate = Carbon::createFromFormat('Y-m', $this->reportingPeriod);
            } else {
                $reportingDate = Carbon::createFromFormat('Ym', $this->reportingPeriod);
            }
            
            Log::info("Starting queued import for period {$this->reportingPeriod} from file: {$this->fileName}");

            // Process the CSV content from memory
            $this->processCsvContent();

            Log::info("Queued import finished. Imported: {$this->importedCount} rows.");

            // Update import record with final statistics
            if ($this->import) {
                Log::info("Updating import status to completed for import ID: " . $this->import->id);
                $this->import->update([
                    'status' => 'completed',
                    'records' => $this->importedCount,
                    'failed_records' => $this->totalRows - $this->importedCount,
                    'completed_at' => now()
                ]);
            } else {
                Log::error("Cannot update import record - it is null");
            }
        } catch (\Exception $e) {
            Log::error("Exception in handle method: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            
            // Update import status to failed
            if ($this->import) {
                $this->import->update([
                    'status' => 'failed',
                    'completed_at' => now()
                ]);
            }
            
            throw $e; // Re-throw to mark job as failed
        }
    }

    private function processCsvContent()
    {
        // Debug the file path
        Log::info("Processing file with path: '{$this->filePath}'");
        
        if (empty($this->filePath)) {
            Log::error("File path is empty");
            return;
        }
        
        // Get full file path from storage
        $fullPath = storage_path('app/' . $this->filePath);
        Log::info("Full file path: '{$fullPath}'");
        
        if (!file_exists($fullPath)) {
            Log::error("Import file not found: {$fullPath}");
            return;
        }

        $file = fopen($fullPath, 'r');
        if (!$file) {
            Log::error("Could not open file: {$fullPath}");
            return;
        }

        $isHeader = true;
        $batchSize = 500; // Process in smaller batches
        $batch = [];
        
        while (($row = fgetcsv($file)) !== false) {
            if ($isHeader) {
                $isHeader = false;
                continue;
            }

            $this->totalRows++;
            
            // Process row and add to batch
            $data = $this->processRow($row, $this->reportingPeriod);
            if ($data) {
                $batch[] = $data;
                
                // Insert batch when it reaches the batch size
                if (count($batch) >= $batchSize) {
                    $this->insertBatch($batch);
                    $batch = []; // Clear batch
                    $this->importedCount += $batchSize;
                    
                    // Free memory
                    gc_collect_cycles();
                }
            }
        }

        // Insert remaining records
        if (!empty($batch)) {
            $this->insertBatch($batch);
            $this->importedCount += count($batch);
        }

        fclose($file);
        
        // Clean up temporary file
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    private function processRow($row, $periodString)
    {
        // Skip empty rows
        if (empty(array_filter($row))) {
           // Log::debug("Skipping empty row");
            return null;
        }

        // Debug: Log the first few cells to understand the format
        $firstCell = trim($row[0] ?? '');
        $secondCell = trim($row[1] ?? '');
       // Log::info("Processing row with first cell: '{$firstCell}', second cell: '{$secondCell}'");
       // Log::info("Full row data: " . json_encode(array_slice($row, 0, 5)));

        // Check if this is a context row (Loan Type) - check all cells for "Loan Type"
        $isContextRow = false;
        foreach ($row as $cell) {
            if (stripos(trim($cell), 'Loan Type') !== false) {
                $isContextRow = true;
                //Log::info("Found context row: " . trim($cell));
                $this->updateContext(trim($cell));
                return null;
            }
        }

        // Check if this is a header row - look for header indicators
        $headerIndicators = ['Sr.', 'No.', 'Customer', 'ID', 'National', 'A/C', 'Number'];
        $isHeaderRow = false;
        foreach ($row as $cell) {
            foreach ($headerIndicators as $indicator) {
                if (stripos(trim($cell), $indicator) !== false) {
                    $isHeaderRow = true;
                   // Log::info("Found header row with indicator: {$indicator}");
                    return null;
                }
            }
        }

        // Check if this is a data row - look for serial number in first or second cell
        $serialCell = $firstCell;
        if (empty($firstCell) && is_numeric($secondCell)) {
            $serialCell = $secondCell;
            //Log::info("Using serial number from second cell: {$serialCell}");
        }
        
        if (!is_numeric($serialCell)) {
            //Log::info("Skipping non-data row, neither first nor second cell is numeric: '{$firstCell}' / '{$secondCell}'");
            return null;
        }

        //Log::info("Processing data row with serial: {$serialCell}");

        try {
            $data = $this->mapRowToData($row, $periodString);
            return $data; // Return data instead of inserting directly
        } catch (\Exception $e) {
           // Log::error("Failed to process row {$this->totalRows}: " . $e->getMessage());
            return null;
        }
    }

    private function mapRowToData($row, $periodString)
    {
       // Log::info("mapRowToData called with row count: " . count($row));
        
        // Ensure we have enough columns
        if (count($row) < 7) {
           // Log::warning("Skipping row - insufficient columns: " . count($row));
            return null;
        }

        $firstCell = trim($row[0] ?? '');
        $reportingDate = null;
        if (strpos($periodString, '-') !== false) {
            $reportingDate = Carbon::createFromFormat('Y-m', $periodString);
        } else {
            $reportingDate = Carbon::createFromFormat('Ym', $periodString);
        }
        
        // Check format based on first cell
        if (empty($firstCell)) {
            // Format 1: First cell is empty - use shifted column mapping
            return $this->mapRowToDataFormat1($row, $periodString, $reportingDate);
        } else {
            // Format 2: First cell has data - use standard column mapping
            return $this->mapRowToDataFormat2($row, $periodString, $reportingDate);
        }
    }

    private function mapRowToDataFormat1($row, $periodString, $reportingDate)
    {
       // Log::info("Using Format 1 - First cell is empty");
        
        // Format 1: [empty, serial, customer_id, national_id, contract_id, account, name, ...]
        $contractId = trim($row[4] ?? '');
        
        if (!$contractId) {
            //Log::warning("Skipping row - missing contract ID in format 1");
            return null;
        }

        // Get account number and convert from scientific notation if needed
        //$accountNumber = $this->convertScientificToInteger($contractId);
        
        $data = [
            'contract_id'      => trim($row[4] ?? ''),
            'customer_id'      => trim($row[2] ?? ''),
            'customer_name'    => trim($row[5] ?? ''),
            'product_group'    => $this->loanContext['group'] ?? 'Unknown',
            'product_code'     => $this->loanContext['code'] ?? '0',
            'loan_portfolio_id'=> $this->portfolio->id,
            'reporting_year'   => $reportingDate->year,
            'reporting_month'  => $reportingDate->month,
            'reporting_period' => $periodString,
            'create_date'      => $this->parseDate($row[6] ?? ''),
            'due_date'         => $this->parseDate($row[7] ?? ''),
            'interest_rate'    => trim($row[10] ?? ''),
            'remaining_tenor'  => $this->calculateRemainingTenor($row[8] ?? '', $periodString),
            'principal_balance' => $this->cleanNumeric($row[14] ?? 0),
            'carrying_amount'   => $this->cleanNumeric($row[20] ?? 0),
            'arrears_1_to_30'   => $this->cleanNumeric($row[25] ?? 0),
            'arrears_30_to_90'   => $this->cleanNumeric($row[26] ?? 0),
            'arrears_91_to_180'   => $this->cleanNumeric($row[27] ?? 0),
            'arrears_180_to_270'   => $this->cleanNumeric($row[29] ?? 0),
            'ifrs9stage_pre_qualitative'  => $this->classifyIFRS9StageF1($row),
            'ifrs9stage_post_qualitative' => $this->classifyIFRS9StageF1($row),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        
        // Log::info("Format 1 mapped data: " . json_encode([
        //     'contract_id' => $data['contract_id'],
        //     'customer_id' => $data['customer_id'],
        //     'customer_name' => $data['customer_name']
        // ]));
        
        return $data;
    }

    private function mapRowToDataFormat2($row, $periodString, $reportingDate)
    {
       // Log::info("Using Format 2 - First cell has data");
        
        // Format 2: [serial, customer_id, national_id, contract_id, account, name, ...]
        $contractId = trim($row[3] ?? '');
        
        if (!$contractId) {
            //Log::warning("Skipping row - missing contract ID in format 2");
            return null;
        }

        // Get account number and convert from scientific notation if needed
       // $accountNumber = $this->convertScientificToInteger($contractId);
        
        $data = [
            'contract_id'      => $this->convertScientificToInteger($contractId),
            'customer_id'      => trim($row[1] ?? ''),
            'customer_name'    => trim($row[4] ?? ''),
            'product_group'    => $this->loanContext['group'] ?? 'Unknown',
            'product_code'     => $this->loanContext['code'] ?? '0',
            'loan_portfolio_id'=> $this->portfolio->id,
            'reporting_year'   => $reportingDate->year,
            'reporting_month'  => $reportingDate->month,
            'reporting_period' => $periodString,
            'create_date'      => $this->parseDate($row[5] ?? ''),
            'due_date'         => $this->parseDate($row[6] ?? ''),
            'interest_rate'    => trim($row[9] ?? ''),
            'remaining_tenor'  => $this->calculateRemainingTenor($row[6] ?? '', $periodString),
            'principal_balance' => $this->cleanNumeric($row[13] ?? 0),
            'carrying_amount'   => $this->cleanNumeric($row[19] ?? 0),
            'arrears_1_to_30'   => $this->cleanNumeric($row[24] ?? 0),
            'arrears_30_to_90'   => $this->cleanNumeric($row[25] ?? 0),
            'arrears_91_to_180'   => $this->cleanNumeric($row[26] ?? 0),
            'arrears_180_to_270'   => $this->cleanNumeric($row[28] ?? 0),
            'ifrs9stage_pre_qualitative'  => $this->classifyIFRS9StageF2($row),
            'ifrs9stage_post_qualitative' => $this->classifyIFRS9StageF2($row),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        
        // Log::info("Format 2 mapped data: " . json_encode([
        //     'contract_id' => $data['contract_id'],
        //     'customer_id' => $data['customer_id'],
        //     'customer_name' => $data['customer_name']
        // ]));
        
        return $data;
    }

    private function insertBatch(array $batch)
    {
        if (empty($batch)) {
            return;
        }

        try {
            DB::table('loan_books')->upsert(
                $batch,
                ['contract_id', 'reporting_period'],
                [
                    'customer_id', 'customer_name', 'product_group', 'product_code',
                    'loan_portfolio_id', 'reporting_year', 'reporting_month',
                    'create_date', 'due_date', 'interest_rate', 'remaining_tenor',
                    'principal_balance', 'disbursed', 'carrying_amount',
                    'arrears_1_to_30', 'arrears_30_to_90', 'arrears_91_to_180', 'arrears_180_to_270',
                    'ifrs9stage_pre_qualitative', 'ifrs9stage_post_qualitative',
                    'updated_at'
                ]
            );
        } catch (\Exception $e) {
            Log::error("Batch insert failed: " . $e->getMessage());
            // Fallback to individual inserts
            foreach ($batch as $data) {
                $this->insertLoanData($data);
            }
        }
    }

    private function insertLoanData($data)
    {
        DB::table('loan_books')->updateOrInsert(
            ['contract_id' => $data['contract_id'], 'reporting_period' => $data['reporting_period']],
            $data
        );
    }

    private function updateContext($headerString)
    {
        $patterns = [
            '/Loan Type\s*:\s*([^\-\s]+)\s*-\s*(.*)/',
            '/Loan Type\s*:\s*(.+)/',
            '/Loan\s*Type\s*:\s*(.+)/',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $headerString, $matches)) {
                if (count($matches) >= 3) {
                    $this->loanContext = [
                        'code' => trim($matches[1]),
                        'group' => trim($matches[2]),
                    ];
                } else {
                    $this->loanContext = [
                        'code' => 'UNKNOWN',
                        'group' => trim($matches[1]),
                    ];
                }
                //Log::debug("Context Switched: " . $this->loanContext['group']);
                return;
            }
        }
        
       // Log::warning("Failed to parse context from: {$headerString}");
    }

    private function parseDate($value)
    {
        if (!$value || trim($value) === '' || trim($value) === '-') return null;
        try {
            return Carbon::createFromFormat('d/m/Y', trim($value))->format('Y-m-d');
        } catch (\Exception $e) {
            try {
                return Carbon::parse(trim($value))->format('Y-m-d');
            } catch (\Exception $e2) {
                return null;
            }
        }
    }

    private function cleanNumeric($value)
    {
        $val = (string)$value;
        $clean = str_replace([',', ' ', "\xA0"], '', $val);
        $clean = preg_replace('/[^0-9.-]/', '', $clean);
        return is_numeric($clean) ? (float)$clean : 0.00;
    }

        
    private function convertScientificToInteger($value)
    {
        if (empty($value) || trim($value) === '') {
            return '';
        }
        
        $value = trim($value);
        
        // 1. Handle Scientific Notation (e.g., 1.23E+10)
        if (stripos($value, 'E') !== false || stripos($value, 'e') !== false) {
            $numericValue = (float)$value;
            $value = number_format($numericValue, 0, '', '');
        }
        
        // 2. Trim leading zeros
        // This will remove any number of leading zeros (e.0. "000123" becomes "123")
        $value = ltrim($value, '0');

        // 3. Fallback: if the value was "000", ltrim makes it empty. 
        // If you want to keep a single "0", use this:
        return $value === '' ? '0' : $value;
    }

    private function classifyIFRS9StageF1($row)
    {
        $col_181_270 = $this->cleanNumeric($row[29] ?? 0);
        $col_91_180  = $this->cleanNumeric($row[27] ?? 0); 
        $col_31_90   = $this->cleanNumeric($row[26] ?? 0); 
        $col_1_30    = $this->cleanNumeric($row[25] ?? 0); 

        if ($col_181_270 > 0) {
            return 3;
        }
        if ($col_91_180 > 0 || $col_31_90 > 0) {
            return 2;
        }
        if ($col_1_30 > 0) {
            return 1;
        }
        return 1;
    }

    private function classifyIFRS9StageF2($row)
    {
        $col_181_270 = $this->cleanNumeric($row[28] ?? 0); // AC (Oldest)
        $col_91_180  = $this->cleanNumeric($row[26] ?? 0); // AA
        $col_31_90   = $this->cleanNumeric($row[25] ?? 0); // Z
        $col_1_30    = $this->cleanNumeric($row[24] ?? 0); // Y

        // 1. Check Oldest Bucket First (Stage 3)
        if ($col_181_270 > 0) {
            return 3;
        }

        // 2. Check Middle Buckets (Stage 2)
        // Both 91-180 and 31-90 are Stage 2 per your requirement
        if ($col_91_180 > 0 || $col_31_90 > 0) {
            return 2;
        }

        // 3. Check Youngest Bucket (Stage 1)
        if ($col_1_30 > 0) {
            return 1;
        }

        // 4. Default to Stage 1 if all are zero or empty
        return 1;
    }

    private function calculateRemainingTenor($dueDateStr, $reportingPeriod)
    {
        $dueDate = $this->parseDate($dueDateStr);
        
        if (!$dueDate) {
            return 0;
        }
        
        try {
            $reportingYear = (int)substr($reportingPeriod, 0, 4);
            $reportingMonth = (int)substr($reportingPeriod, 5, 2);
            $reportingDate = Carbon::create($reportingYear, $reportingMonth, 1)->endOfMonth();
            $maturityDate = Carbon::parse($dueDate);
        
            
            if ($maturityDate <= $reportingDate) {
               // Log::info("Loan is matured, returning 0");
                return 0;
            }
            
            $years = $maturityDate->year - $reportingDate->year;
            $months = $maturityDate->month - $reportingDate->month;
            
            if ($months < 0) {
                $years--;
                $months += 12;
            }
            
            $decimalYears = $years + ($months / 12);
            $remainingTenor = round($decimalYears, 2);
            
            // Only apply max(0, ...) if actually negative
            return $remainingTenor < 0 ? 0 : $remainingTenor;
            
        } catch (\Exception $e) {
            //Log::error("Error calculating remaining tenor: " . $e->getMessage());
            return 0;
        }
    }

    public function failed(\Throwable $exception)
    {
        //Log::error("Loan import job failed: " . $exception->getMessage());
        
        // Update import status to failed
        if ($this->import) {
            $this->import->update([
                'status' => 'failed',
                'completed_at' => now()
            ]);
        }
        
        // Clean up temporary file on failure
        $fullPath = storage_path('app/' . $this->filePath);
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }
}
