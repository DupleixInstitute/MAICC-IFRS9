<?php

namespace App\Imports;

use App\Models\CollateralRegister;
use App\Models\Import;
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

class CollateralRegisterImport implements ToCollection, WithHeadingRow, WithEvents, WithChunkReading, ShouldQueue
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

        $this->exceptionFilePath = storage_path("app/public/failed_imports/collateral_register_exception_{$import->id}.csv");

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
            //Log::warning("Failed to parse date: {$value}", ['error' => $e->getMessage()]);
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

        $periodInput = $this->mapping['period'] ?? $this->import->settings['period'] ?? null;
        if ($periodInput) {
            $periodCarbon = Carbon::createFromFormat('Y-m', $periodInput)->startOfMonth();
        } else {
            $periodCarbon = now()->startOfMonth();
        }

        $period = $periodCarbon->format('Y-m-d');


        foreach ($rows as $index => $row) {
            try {
                // Normalize keys with new method
                $normalizedRow = collect($row->toArray())->mapWithKeys(function ($value, $key) {
                    $normalizedKey = $this->normalizeKey($key);
                    return [$normalizedKey => $value];
                })->toArray();

                // Determine import type
                $data = [];
                if ($this->importType === 'legacy') {
                    // Legacy logic
                    $data['customer_id']       = $normalizedRow['customer_id'] ?? null;
                    $data['customer_name']     = $normalizedRow['customer_name'] ?? $normalizedRow['name'] ?? 'Unknown';
                    $data['collateral_type']   = $normalizedRow['collateral_type'] ?? null;
                    $data['property_use']      = $normalizedRow['property_use'] ?? null;
                    $data['description']       = $normalizedRow['description'] ?? null;
                    $data['location']          = $normalizedRow['location'] ?? null;
                    $data['period']            = $periodCarbon->format('Y-m-d');
                    $data['registration_date'] = $this->parseDate($normalizedRow['registration_date'] ?? null) ?? $periodCarbon->format('Y-m-d');
                    $data['expiry_date']       = $this->parseDate($normalizedRow['expiry_date'] ?? null);
                    $data['valuation_date']    = $this->parseDate($normalizedRow['valuation_date'] ?? null);
                    $data['nominal_value']     = $this->cleanNumber($normalizedRow['nominal_value'] ?? 0);
                    $data['market_value']      = $this->cleanNumber($normalizedRow['market_value'] ?? 0);
                    $data['execution_value']   = $this->cleanNumber($normalizedRow['execution_value'] ?? 0);
                    $data['status']            = strtoupper(trim($normalizedRow['status'] ?? '')) ?: 'ACTIVE';
                } else {
                    // Custom import type with mapping
                    $ignoredKeys = [
                        'registration_date',
                        'import_type',
                        'mapping',
                    ];
                    
                    foreach ($this->mapping as $csv => $db) {
                        if (in_array($csv, $ignoredKeys)) {
                            continue;
                        }

                        if (!$db || $db === '') continue; // Skip empty mappings
                        
                        // Normalize CSV header key
                        $normalizedCsvKey = $this->normalizeKey($csv);
                        
                        if (isset($normalizedRow[$normalizedCsvKey])) {
                            $data[$db] = $normalizedRow[$normalizedCsvKey];
                        } else {
                            // Log::warning("Mapping key not found in row", [
                            //     'csv_key' => $csv,
                            //     'normalized_csv_key' => $normalizedCsvKey,
                            //     'db_field' => $db,
                            //     'available_keys' => array_keys($normalizedRow)
                            // ]);
                        }
                    }
                    
                    // If mapping is empty or doesn't include essential fields, use direct field mapping as fallback
                    $essentialFields = ['customer_id', 'collateral_type', 'period'];
                    $hasEssentialFields = count(array_intersect($essentialFields, array_keys($data))) === count($essentialFields);
                    
                    if (!$hasEssentialFields) {
                        $data['customer_id']       = $normalizedRow['customer_id'] ?? null;
                        $data['customer_name']     = $normalizedRow['customer_name'] ?? $normalizedRow['name'] ?? 'Unknown';
                        $data['collateral_type']   = $normalizedRow['collateral_type'] ?? null;
                        $data['property_use']      = $normalizedRow['property_use'] ?? null;
                        $data['description']       = $normalizedRow['description'] ?? null;
                        $data['location']          = $normalizedRow['location'] ?? null;
                        $data['period']            = $periodCarbon->format('Y-m-d');
                        $data['registration_date'] = $this->parseDate($normalizedRow['registration_date'] ?? null) ?? $periodCarbon->format('Y-m-d');
                        $data['expiry_date']       = $this->parseDate($normalizedRow['expiry_date'] ?? null);
                        $data['valuation_date']    = $this->parseDate($normalizedRow['valuation_date'] ?? null);
                        $data['nominal_value']     = $this->cleanNumber($normalizedRow['nominal_value'] ?? 0);
                        $data['market_value']      = $this->cleanNumber($normalizedRow['market_value'] ?? 0);
                        $data['execution_value']   = $this->cleanNumber($normalizedRow['execution_value'] ?? 0);
                        $data['status']            = strtoupper(trim($normalizedRow['status'] ?? '')) ?: 'ACTIVE';
                    }
                }

                // Validate essential fields
                $customerId = $data['customer_id'] ?? null;
                $collateralType = $data['collateral_type'] ?? null;
                
                if (empty($customerId) || empty($collateralType)) {
                    throw new \Exception("Missing essential fields: customer_id and/or collateral_type");
                }

                // Set default registration date if not provided
                if (empty($data['registration_date'])) {
                    $data['registration_date'] = $periodCarbon->format('Y-m-d');
                }

                // Set default status if not provided
                if (empty($data['status'])) {
                    $data['status'] = 'ACTIVE';
                }

                // Clean numeric fields
                $numericFields = ['nominal_value', 'market_value', 'execution_value'];
                foreach ($numericFields as $field) {
                    if (isset($data[$field])) {
                        $data[$field] = $this->cleanNumber($data[$field]);
                    }
                }

                // Parse date fields
                $dateFields = ['registration_date', 'expiry_date', 'valuation_date'];
                foreach ($dateFields as $field) {
                    if (isset($data[$field]) && $data[$field]) {
                        $data[$field] = $this->parseDate($data[$field]);
                    }
                }

                // Add timestamps
                $data['created_at'] = now();
                $data['updated_at'] = now();

                // Create a composite unique key using multiple fields
                $uniqueKey = [
                    'customer_id' => $data['customer_id'],
                    'collateral_type' => $data['collateral_type'],
                    'period' => $period,
                    'registration_date' => $data['registration_date'] ?? $period,
                ];
                
                // Add the unique key fields to the data for upsert
                $data = array_merge($data, $uniqueKey);

                $bulkInsert[] = $data;
                $inserted++;

            } catch (\Exception $e) {
                Log::error("Error processing collateral row {$index}: " . $e->getMessage());
                $this->appendExceptionRow($row->toArray(), $e->getMessage());
                $exceptions++;
            }
        }

        // Bulk upsert - manually check for duplicates first
        if (!empty($bulkInsert)) {
            try {
                // Insert in smaller chunks to avoid memory issues
                $chunks = array_chunk($bulkInsert, 100);
                foreach ($chunks as $chunk) {
                    // Check for existing records with our unique key combination
                    $existingRecords = [];
                    foreach ($chunk as $record) {
                        $key = $record['customer_id'] . '|' . 
                               $record['collateral_type'] . '|' . 
                               $record['period'] . '|' . 
                               $record['registration_date'];
                        
                        $existing = CollateralRegister::where('customer_id', $record['customer_id'])
                            ->where('collateral_type', $record['collateral_type'])
                            ->where('period', $record['period'])
                            ->where('registration_date', $record['registration_date'])
                            ->first();
                        
                        if ($existing) {
                            $existingRecords[$key] = $existing->id;
                        }
                    }
                    
                    // Separate into updates and inserts
                    $updateData = [];
                    $insertData = [];
                    
                    foreach ($chunk as $record) {
                        $key = $record['customer_id'] . '|' . 
                               $record['collateral_type'] . '|' . 
                               $record['period'] . '|' . 
                               $record['registration_date'];
                        
                        if (isset($existingRecords[$key])) {
                            // Update existing record
                            $updateData[] = [
                                'id' => $existingRecords[$key],
                                'customer_name' => $record['customer_name'] ?? null,
                                'property_use' => $record['property_use'] ?? null,
                                'description' => $record['description'] ?? null,
                                'location' => $record['location'] ?? null,
                                'expiry_date' => $record['expiry_date'] ?? null,
                                'valuation_date' => $record['valuation_date'] ?? null,
                                'nominal_value' => $record['nominal_value'] ?? 0,
                                'market_value' => $record['market_value'] ?? 0,
                                'execution_value' => $record['execution_value'] ?? 0,
                                'status' => $record['status'] ?? 'ACTIVE',
                                'updated_at' => now(),
                            ];
                        } else {
                            // Insert new record
                            unset($record['created_at']); // Let Laravel handle this
                            $insertData[] = $record;
                        }
                    }
                    
                    // Perform updates
                    if (!empty($updateData)) {
                        foreach ($updateData as $update) {
                            $id = $update['id'];
                            unset($update['id']);
                            CollateralRegister::where('id', $id)->update($update);
                        }
                    }
                    
                    // Perform inserts
                    if (!empty($insertData)) {
                        CollateralRegister::insert($insertData);
                    }
                }
                Log::info("Successfully processed {$inserted} collateral records");
            } catch (\Exception $e) {
                Log::error("Bulk collateral insert failed: " . $e->getMessage());
            }
        }

        // Update import stats
        $import = Import::find($this->import->id);
        if ($import) {
            $import->records += $inserted;
            $import->failed_records += $exceptions;
            $import->save();
        }
        
        Log::info("Collateral import completed - Inserted: {$inserted}, Exceptions: {$exceptions}");
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
                Log::info("Starting collateral import for: " . $this->import->id);
            },
            AfterImport::class => function() {
                $this->import->update(['status' => 'completed', 'completed_at' => now()]);
                Log::info("Completed collateral import for: " . $this->import->id);
            },
            ImportFailed::class => function(ImportFailed $event) {
                $this->import->update(['status' => 'failed', 'completed_at' => now()]);
                Log::error("Collateral import failed: " . $event->getException()->getMessage());
            }
        ];
    }
}