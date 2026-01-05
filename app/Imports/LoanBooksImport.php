<?php

namespace App\Imports;

use App\Models\Client;
use App\Models\Import;
use App\Models\LoanBook;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Events\ImportFailed;

class LoanBooksImport implements ToCollection, WithHeadingRow, WithEvents, WithChunkReading, ShouldQueue
{
    protected Import $import;
    protected array $mapping;
    protected string $importType;
    protected string $exceptionFilePath;

    public function __construct(Import $import, array $mapping = [], string $importType = 'custom')
    {
        $this->import      = $import;
        $this->mapping     = $mapping;
        $this->importType  = $importType;

        $this->exceptionFilePath = storage_path("app/public/failed_imports/loan_books_exception_{$import->id}.csv");

        if (!file_exists(dirname($this->exceptionFilePath))) {
            mkdir(dirname($this->exceptionFilePath), 0755, true);
        }

        if (!file_exists($this->exceptionFilePath)) {
            $file = fopen($this->exceptionFilePath, 'w');
            fputcsv($file, ['Row Data', 'Reason']);
            fclose($file);
        }
    }

    protected function parseDate($value): ?string
    {
        if (!$value) return null;

        // Clean the value first - handle your CSV's " -   " values
        $value = trim(str_replace(['-', ' -   ', "\xC2\xA0", "\xA0"], '', $value));
        
        if (empty($value)) return null;

        $formats = [
            'd/m/Y', 'd/m/Y H:i', 'd/m/Y H:i:s',
            'm/d/Y', 'm/d/Y H:i', 'm/d/Y H:i:s', 
            'Y-m-d', 'Y-m-d H:i:s'
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date !== false) {
                    return $date->format('Y-m-d');
                }
            } catch (\Exception $e) {
                // Continue to next format
            }
        }

        // Try generic parsing as last resort
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            Log::warning("Failed to parse date: {$value}", ['error' => $e->getMessage()]);
            return null;
        }
    }

    protected function cleanNumber($value): float
    {
        if (!$value || trim($value) === '-' || trim($value) === '' || trim($value) === ' -   ') return 0;
        
        // Remove commas, spaces, and special characters
        $cleaned = str_replace([',', ' ', "\xC2\xA0", "\xA0", '"'], '', trim($value));
        return (float) $cleaned;
    }

    public function classifyIFRS9Stage($row)
    {
        $clean = function($value) {
            if (!$value || trim($value) === '-' || trim($value) === '' || trim($value) === ' -   ') {
                return false;
            }
            $value = str_replace(['-', "\xC2\xA0", "\xA0", "\xE2\x80\x8B", "\xE2\x80\x8C", "\t", "\n", "\r", ',', ' ', '"'], '', $value);
            $value = trim(preg_replace('/[\x00-\x1F\x7F\xA0\xAD]/u', '', $value));
            return is_numeric($value) && floatval($value) > 0;
        };

        // Debug: Log what we're getting
        // Log::debug('IFRS9 Classification row data:', [
        //     '1_30_days' => $row['1_30_days'] ?? $row['1-30_days'] ?? null,
        //     '31_90_days' => $row['31_90_days'] ?? $row['31-90_days'] ?? null,
        //     '91_180_days' => $row['91_180_days'] ?? $row['91-180_days'] ?? null,
        //     '181_270_days' => $row['181_270_days'] ?? $row['181-270_days'] ?? null,
        // ]);

        // Check with both underscore and dash versions
        if ($clean($row['271_360_days'] ?? $row['271-360_days'] ?? null)) return '3';
        if ($clean($row['181_270_days'] ?? $row['181-270_days'] ?? null)) return '3';
        if ($clean($row['91_180_days'] ?? $row['91-180_days'] ?? null))  return '2';
        if ($clean($row['31_90_days'] ?? $row['31-90_days'] ?? null))  return '2';
        if ($clean($row['1_30_days'] ?? $row['1-30_days'] ?? null))   return '1';

        return '1';
    }

    protected function normalizeKey($key): string
    {
        // First remove all quotes and trim
        $key = trim($key, " \t\n\r\0\x0B\"'");
        
        // Replace multiple spaces with single space
        $key = preg_replace('/\s+/', ' ', $key);
        
        // Replace special characters and spaces with underscores
        $key = preg_replace('/[^a-zA-Z0-9_]/', '_', $key);
        
        // Remove multiple underscores
        $key = preg_replace('/_+/', '_', $key);
        
        // Convert to lowercase
        $key = strtolower($key);
        
        // Remove trailing underscores
        $key = trim($key, '_');
        
        return $key;
    }

    public function collection(Collection $rows)
    {
        $bulkInsert = [];
        $inserted   = 0;
        $exceptions = 0;

        $reportingPeriod = $this->mapping['reporting_period'] ?? $this->import->settings['reporting_period'] ?? now()->format('Y-m');
        $portfolioId     = $this->mapping['loan_portfolio_id'] ?? $this->import->settings['loan_portfolio_id'] ?? 1;
        
        if (!str_contains($reportingPeriod, '-')) {
            $reportingPeriod = now()->format('Y-m');
        }
        
        [$year, $month]  = explode('-', $reportingPeriod);

        foreach ($rows as $index => $row) {
            try {
                // Normalize keys with the new method
                $normalizedRow = collect($row->toArray())->mapWithKeys(function ($value, $key) {
                    $normalizedKey = $this->normalizeKey($key);
                    return [$normalizedKey => $value];
                })->toArray();

                // Log::info("Processing Loan row {$index}", $normalizedRow);
                // Log::info("Available normalized keys: " . implode(', ', array_keys($normalizedRow)));

                // Determine import type
                $data = [];
                if ($this->importType === 'legacy') {
                    // Legacy logic
                    $data['customer_id']    = $normalizedRow['customer_id'] ?? null;
                    $data['contract_id']    = $normalizedRow['contract_id'] ?? null;
                    $data['name']           = $normalizedRow['name'] ?? $normalizedRow['customer_name'] ?? 'Unknown';
                    $data['value_date']     = $normalizedRow['value_date'] ?? null;
                    $data['maturity_date']  = $normalizedRow['maturity_date'] ?? null;
                    $data['principal']      = $this->cleanNumber($normalizedRow['principal'] ?? 0);
                    $data['tenor']          = $normalizedRow['tenor'] ?? null;
                    $data['interest_rate']  = $this->cleanNumber($normalizedRow['interest_rate'] ?? 0);
                    $data['disbursed']      = $this->cleanNumber($normalizedRow['disbursed'] ?? 0);
                    $data['carrying_amount']= $this->cleanNumber($normalizedRow['carrying_amount'] ?? 0);
                    $data['product_group']  = $normalizedRow['type'] ?? null;
                    $data['industry_code']  = $normalizedRow['industry_code'] ?? null;
                    $data['internal_grade_code']  = $normalizedRow['internal_grade_code'] ?? null;
                } else {
                    // Custom import type with mapping
                   // Log::info("Using custom mapping", ['mapping' => $this->mapping]);

                   $ignoredKeys = [
                            'loan_portfolio_id',
                            'reporting_period',
                            'import_type',
                            'mapping',
                        ];
                    
                    foreach ($this->mapping as $csv => $db) {

                         if (in_array($csv, $ignoredKeys)) {
                                    continue;
                                }

                        if (!$db || $db === '') continue; // Skip empty mappings
                        
                        // Normalize the CSV header key
                        $normalizedCsvKey = $this->normalizeKey($csv);
                        
                        // Log::debug("Looking for key", [
                        //     'original_csv' => $csv,
                        //     'normalized_csv' => $normalizedCsvKey,
                        //     'database_field' => $db,
                        //     'available_keys' => array_keys($normalizedRow),
                        //     'has_key' => isset($normalizedRow[$normalizedCsvKey])
                        // ]);
                        
                        if (isset($normalizedRow[$normalizedCsvKey])) {
                            $data[$db] = $normalizedRow[$normalizedCsvKey];
                            // Log::debug("Mapped successfully", [
                            //     'csv_key' => $csv,
                            //     'normalized_key' => $normalizedCsvKey,
                            //     'db_field' => $db,
                            //     'value' => $normalizedRow[$normalizedCsvKey]
                            // ]);
                        } else {
                            Log::warning("Mapping key not found in row", [
                                'csv_key' => $csv,
                                'normalized_csv_key' => $normalizedCsvKey,
                                'db_field' => $db,
                                'available_keys' => array_keys($normalizedRow)
                            ]);
                        }
                    }
                    
                    // Debug the mapped data
                   // Log::info("Mapped data result", $data);
                    
                    // If mapping is empty or doesn't include essential fields, use direct field mapping as fallback
                    $essentialFields = ['customer_id', 'contract_id'];
                    $hasEssentialFields = count(array_intersect($essentialFields, array_keys($data))) === count($essentialFields);
                    
                    if (!$hasEssentialFields) {
                        //Log::info("Missing essential fields, using fallback mapping");
                        $data['customer_id']    = $normalizedRow['customer_id'] ?? null;
                        $data['contract_id']    = $normalizedRow['contract_id'] ?? null;
                        $data['name']           = $normalizedRow['name'] ?? 'Unknown';
                        $data['value_date']     = $normalizedRow['value_date'] ?? null;
                        $data['maturity_date']  = $normalizedRow['maturity_date'] ?? null;
                        $data['principal']      = $this->cleanNumber($normalizedRow['principal'] ?? 0);
                        $data['tenor']          = $normalizedRow['tenor'] ?? null;
                        $data['interest_rate']  = $this->cleanNumber($normalizedRow['interest_rate'] ?? 0);
                        $data['disbursed']      = $this->cleanNumber($normalizedRow['disbursed'] ?? 0);
                        $data['carrying_amount']= $this->cleanNumber($normalizedRow['carrying_amount'] ?? 0);
                        $data['industry_code']  = $normalizedRow['industry_code'] ?? null;
                        $data['industry_type']  = $normalizedRow['industry_type'] ?? null;
                        $data['internal_grade_code']  = $normalizedRow['internal_grade'] ?? null;
                        $data['product_group']  = $normalizedRow['type'] ?? null;
                    }
             }

                // FIX: Validate customer_id - get it from $data array
                $customerId = $data['customer_id'] ?? null;
                if (empty($customerId)) {
                    throw new \Exception("customer_id missing in data array. Available keys in data array: " . implode(', ', array_keys($data)));
                }

                $clientName = $data['name'] ?? 'Unknown';

                // Create or update client
                // Log::info("Creating/updating client", [
                //     'customer_id' => $customerId,
                //     'customer_name' => $clientName
                // ]);

                $client = Client::updateOrCreate(
                    ['customer_id' => $customerId],
                    ['name' => $clientName]
                );

                // Log::info("Client created/updated", [
                //     'client_id' => $client->id,
                //     'customer_id' => $client->customer_id
                // ]);

                // Parse and validate dates
                $createDate = $this->parseDate($data['value_date'] ?? null);
                $dueDate    = $this->parseDate($data['maturity_date'] ?? null);
                if (!$createDate || !$dueDate) {
                    throw new \Exception("Missing/invalid value_date or maturity_date");
                }

                $createCarbon  = Carbon::createFromFormat('Y-m-d', $createDate);
                $reportingEnd  = Carbon::createFromFormat('Y-m', $reportingPeriod)->endOfMonth();
                $remainingLife = $createCarbon->floatDiffInYears($reportingEnd);

                // Handle principal fallback
                $principal = $this->cleanNumber($data['principal'] ?? 0);
                $carryingAmount = $this->cleanNumber($data['carrying_amount'] ?? 0);
                if ($principal == 0 && $carryingAmount > 0) {
                    $principal = $carryingAmount;
                }

                // Build bulk insert - ensure industry_code is included
                $loanData = [
                    'customer_id'                 => $client->id,
                    'customer_name'               => $data['name'] ?? '',
                    'loan_portfolio_id'           => $portfolioId,
                    'reporting_period'            => $reportingPeriod,
                    'reporting_year'              => $year,
                    'reporting_month'             => $month,
                    'contract_id'                 => $data['contract_id'] ?? null,
                    'industry_code'               => $data['industry_code'] ?? null,
                    'industry_type'               => $data['industry_type'] ?? null,
                    'internal_grade_code'         => $data['internal_grade_code'] ?? null, 
                    'product_group'               => $data['product_group'] ?? null,
                    'create_date'                 => $createDate,
                    'due_date'                    => $dueDate,
                    'tenor'                       => $data['tenor'] ?? null,
                    'interest_rate'               => $this->cleanNumber($data['interest_rate'] ?? 0),
                    'remaining_tenor'             => $remainingLife ?? 0,
                    'principal_balance'           => $principal,
                    'disbursed'                   => $this->cleanNumber($data['disbursed'] ?? 0),
                    'carrying_amount'             => $carryingAmount,
                    'ifrs9stage_pre_qualitative'  => $this->classifyIFRS9Stage($normalizedRow),
                    'ifrs9stage_post_qualitative' => $this->classifyIFRS9Stage($normalizedRow),
                    'created_at'                  => now(),
                    'updated_at'                  => now(),
                ];

                // Log the loan data to verify industry_code
                Log::info("Prepared loan data for insert", [
                    'customer_name' => $data['name'] ?? '',
                    'industry_code' => $loanData['industry_code'],
                    'all_data_keys' => array_keys($data)
                ]);

                $bulkInsert[] = $loanData;

                $inserted++;
                //Log::info("Successfully processed row {$index} for customer: {$clientName}");

            } catch (\Exception $e) {
                //Log::error("Error processing row {$index}: " . $e->getMessage());
                //Log::error("Row data: " . json_encode($row->toArray()));
                $this->appendExceptionRow($row->toArray(), $e->getMessage());
                $exceptions++;
            }
        }

        // Bulk upsert
        if (!empty($bulkInsert)) {
            try {
                // Insert in smaller chunks to avoid memory issues
                $chunks = array_chunk($bulkInsert, 100);
                foreach ($chunks as $chunk) {
                    LoanBook::upsert(
                        $chunk,
                        ['customer_id', 'loan_portfolio_id', 'reporting_period', 'contract_id'],
                        [
                            'principal_balance', 
                            'carrying_amount',
                            'disbursed',
                            'create_date', 
                            'due_date', 
                            'ifrs9stage_pre_qualitative', 
                            'ifrs9stage_post_qualitative', 
                            'updated_at',
                            'industry_code' // Make sure industry_code is included in update
                        ]
                    );
                }
                Log::info("Successfully upserted {$inserted} records");
            } catch (\Exception $e) {
                //Log::error("Bulk insert failed: " . $e->getMessage());
            }
        }

        // Update import stats
        $import = Import::find($this->import->id);
        if ($import) {
            $import->records += $inserted;
            $import->failed_records += $exceptions;
            $import->save();
        }
        
        Log::info("Import completed - Inserted: {$inserted}, Exceptions: {$exceptions}");
    }

    protected function appendExceptionRow(array $row, string $reason)
    {
        $handle = fopen($this->exceptionFilePath, 'a');
        fputcsv($handle, [json_encode($row), $reason]);
        fclose($handle);
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function() {
                $this->import->update(['status' => 'processing']);
                Log::info("Starting import for: " . $this->import->id);
            },
            AfterImport::class => function() {
                $this->import->update(['status' => 'completed', 'completed_at' => now()]);
                Log::info("Completed import for: " . $this->import->id);
            },
            ImportFailed::class => function(ImportFailed $event) {
                $this->import->update(['status' => 'failed', 'completed_at' => now()]);
                Log::error("Import failed: " . $event->getException()->getMessage());
            }
        ];
    }
}