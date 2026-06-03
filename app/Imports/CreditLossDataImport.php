<?php

namespace App\Imports;

use App\Models\CreditLossData;
use App\Models\CreditLossDefinition;
use App\Models\Import;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Str;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Events\ImportFailed;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CreditLossDataImport implements ToCollection, WithHeadingRow, ShouldQueue, WithChunkReading, WithEvents
{
    protected $portfolioId;
    protected $userId;
    protected $import;
    protected $exceptionFilePath;
    protected $data = [];

    // Store definition mappings
    protected $definitionMappings = [];
    public function __construct($import, $portfolioId, $userId)
    {
        $this->portfolioId = $portfolioId;
        $this->data = $import->data ?? [];
        $this->userId = $userId;
        $this->import = $import;

        // Pre-load definition mappings
        $this->loadDefinitionMappings();

        $this->exceptionFilePath = storage_path("app/public/failed_imports/credit_loss_data_exception.csv");
        if (!file_exists(dirname($this->exceptionFilePath))) {
            mkdir(dirname($this->exceptionFilePath), 0755, true);
        }
        if (!file_exists($this->exceptionFilePath)) {
            $handle = fopen($this->exceptionFilePath, 'w');
            fputcsv($handle, [
                'metric_code', 'period','value', 'source', 'notes'
            ]);
            fclose($handle);
        }
    }

     protected function parseDate($value): ?string
    {
        $formats = [
            'd/m/Y H:i',
            'd/m/Y H:i:s',
            'd/m/Y',
            'm/d/Y H:i:s',
            'm/d/Y H:i',
            'm/d/Y',
            'Y-m-d H:i:s',
            'Y-m-d',
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, trim($value));
                if ($date !== false) {
                    return $date->format('Y-m-d');
                }
            } catch (\Exception $e) {
                continue;
            }
        }

            // Fallback to general parse
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null; 
        }
    }


    protected function loadDefinitionMappings()
    {
        $definitions = CreditLossDefinition::all();
        
        foreach ($definitions as $definition) {
            $this->definitionMappings[$definition->code] = [
                'id' => $definition->id,
                'aliases' => $this->getAliasesForDefinition($definition->code)
            ];
        }
    }

    protected function getAliasesForDefinition($code)
    {
        $aliases = [
            'ECL' => ['ecl_value', 'ecl', 'expected credit loss', 'ecl value', 'ecl_amount'],
            'PD' => ['pd_value', 'pd', 'probability of default', 'pd value', 'probability_of_default'],
            'LGD' => ['lgd_value', 'lgd', 'loss given default', 'lgd value', 'loss_given_default'],
            'EAD' => ['ead_value', 'ead', 'exposure at default', 'ead value', 'exposure_at_default'],
            'NPL' => ['npl_value', 'npl', 'non-performing loans', 'npl value', 'non_performing_loans'],
            'STAGE' => ['stage', 'credit stage', 'stage classification', 'ifrs9 stage', 'ifrs_stage'],
            'CREDIT_RATING' => ['credit_rating', 'rating', 'credit rating', 'credit score', 'internal_rating'],
        ];

        return $aliases[$code] ?? [strtolower($code)];
    }

  public function collection(Collection $rows)
        {
            if ($rows->isEmpty()) return;

            $importData = [];
            $insertedInChunk = 0;
            $exceptionsInChunk = 0;
            $sourceRowsProcessedInChunk = 0;

            foreach ($rows as $row) {
                try {
                    $rawRow = $row->toArray();

                    // Normalize keys
                    $normalizedRow = [];
                    foreach ($rawRow as $key => $value) {
                        $normalizedRow[strtolower(trim($key))] = trim($value);
                    }

                    // Get period for this row
                    $csvPeriod = $normalizedRow['period'] ?? null;

                    if (!$csvPeriod) {
                        $exceptionsInChunk++;
                        $this->appendExceptionRow([
                            'metric_code' => 'PERIOD_MISSING',
                            'value' => 'N/A',
                            'source' => $normalizedRow['source'] ?? 'CSV Import',
                            'notes' => 'Period is missing in CSV row'
                        ]);
                        continue; // Skip row without period
                    }

                    $parsedPeriod = $this->parseDate($csvPeriod);
                        if (!$parsedPeriod) {
                            $exceptionsInChunk++;
                            $this->appendExceptionRow([
                                'metric_code' => 'PERIOD_INVALID',
                                'value' => 'N/A',
                                'source' => $normalizedRow['source'] ?? 'CSV Import',
                                'notes' => 'Invalid period format: ' . $csvPeriod
                            ]);
                            continue; // Skip row with invalid period
                        }

                    $metricDataFoundInRow = false;

                    // Your existing definitionMappings loop
                    foreach ($this->definitionMappings as $code => $definition) {
                        $value = null;

                        foreach ($definition['aliases'] as $alias) {
                            $alias = strtolower($alias);
                            if (array_key_exists($alias, $normalizedRow) && $normalizedRow[$alias] !== '') {
                                $value = $normalizedRow[$alias];
                                break;
                            }
                        }

                        if ($value === null) continue;

                        $metricDataFoundInRow = true;

                        $validationRules = $this->getValidationRules($code);
                        $validator = Validator::make([
                            'value' => $value,
                            'period' => $parsedPeriod,
                        ], [
                            'value' => $validationRules,
                            'period' => ['required', 'date'],
                        ]);

                        if ($validator->fails()) {
                            $exceptionsInChunk++;
                            $this->appendExceptionRow([
                                'period' => $parsedPeriod,
                                'metric_code' => $code,
                                'value' => $value,
                                'source' => $normalizedRow['source'] ?? 'CSV Import',
                                'notes' => 'Validation failed: ' . $validator->errors()->first('value')
                            ]);
                            continue;
                        }

                        $importData[] = [
                            'portfolio_id' => $this->portfolioId,
                            'definition_id' => $definition['id'],
                            'period' => $parsedPeriod,
                            'value' => $value,
                            'source' => $normalizedRow['source'] ?? 'CSV Import',
                            'notes' => $normalizedRow['notes'] ?? null,
                            'created_by' => $this->userId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];

                        $insertedInChunk++;
                    }

                    if ($metricDataFoundInRow) {
                        $sourceRowsProcessedInChunk++;
                    }

                } catch (\Throwable $e) {
                    $exceptionsInChunk++;
                    \Log::error('CreditLossData import error: ' . $e->getMessage());
                    $this->appendExceptionRow([
                        'metric_code' => 'GENERAL_ERROR',
                        'value' => 'N/A',
                        'source' => $normalizedRow['source'] ?? 'CSV Import',
                        'notes' => 'Row processing failed: ' . $e->getMessage()
                    ]);
                }
            }

            // Batch insert/update
            if (!empty($importData)) {
                foreach (array_chunk($importData, 500) as $batch) {
                    foreach ($batch as $record) {
                        CreditLossData::updateOrInsert(
                            [
                                'portfolio_id' => $record['portfolio_id'],
                                'definition_id' => $record['definition_id'],
                                'period' => $record['period'],
                            ],
                            $record
                        );
                    }
                }
            }

            // Update import summary
            $import = Import::find($this->import->id);
            $import->records = $insertedInChunk;
            $import->rows_processed = $sourceRowsProcessedInChunk;
            $import->failed_records = $exceptionsInChunk;
            $import->save();
        }

        protected function getValidationRules($code)
        {
            $rules = [
                'ECL' => ['nullable', 'numeric'],
                'NPL' => ['nullable', 'numeric'],
                'PD' => ['nullable', 'numeric', 'between:0,1'],
                'LGD' => ['nullable', 'numeric', 'between:0,1'],
                'EAD' => ['nullable', 'numeric'],
                'STAGE' => ['nullable', 'numeric', 'in:1,2,3'],
                'CREDIT_RATING' => ['nullable', 'string'],
            ];

            return $rules[$code] ?? ['nullable'];
        }

    protected function appendExceptionRow(array $row)
    {
        $handle = fopen($this->exceptionFilePath, 'a');
        fputcsv($handle, $row);
        fclose($handle);
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (BeforeImport $event) {
                $this->import->update([
                    'status' => 'processing',
                ]);
            },
            AfterImport::class => function (AfterImport $event) {
                $this->import->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            },
            ImportFailed::class => function (ImportFailed $event) {
                Log::error('Import failed: ' . $event->getException()->getMessage());
                $this->import->update([
                    'status' => 'failed',
                    'completed_at' => now(),
                ]);
            },
        ];
    }

    function normalizePeriod($period)
    {
        if (!$period) {
            return null;
        }

        $period = trim($period);

        // Convert slashes to hyphens
        $period = str_replace('/', '-', $period);

        // Try standard YYYY-MM format first
        try {
            return Carbon::parse($period . '-01')->startOfMonth();
        } catch (\Exception $e) {}

        // Try MM-YYYY format (e.g., 01-2023)
        if (preg_match('/^(\d{2})-(\d{4})$/', $period, $m)) {
            return Carbon::createFromDate($m[2], $m[1], 1)->startOfMonth();
        }

        // Try month name formats (Jan-2023, January 2023, 2023 Jan, etc.)
        try {
            return Carbon::parse($period)->startOfMonth();
        } catch (\Exception $e) {}

        // If nothing matches, return null
        return null;
    }
}