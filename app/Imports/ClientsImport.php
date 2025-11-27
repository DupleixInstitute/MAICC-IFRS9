<?php

namespace App\Imports;

use App\Models\Client;
use App\Models\Import;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Events\ImportFailed;

class ClientsImport implements ToCollection, WithHeadingRow, WithChunkReading, WithEvents, ShouldQueue
{
    protected int $processed = 0;
    protected int $failed = 0;
    protected array $failedRecords = [];

    protected Import $import;
    protected array $mapping;
    protected string $importType;
    protected string $exceptionFilePath;

    public function __construct(Import $import, array $mapping = [], string $importType = 'custom')
    {
        $this->import = $import;
        $this->mapping = $mapping;
        $this->importType = $importType;

        // Setup exception file
        $this->exceptionFilePath = storage_path("app/public/failed_imports/clients_exception_{$import->id}.csv");

        if (!file_exists(dirname($this->exceptionFilePath))) {
            mkdir(dirname($this->exceptionFilePath), 0755, true);
        }

        if (!file_exists($this->exceptionFilePath)) {
            $handle = fopen($this->exceptionFilePath, 'w');
            fputcsv($handle, ['Row Data', 'Reason']);
            fclose($handle);
        }
    }

    public function collection(Collection $rows)
    {
        // Set file path on import if not set
        if (empty($this->import->failed_file_path)) {
            $this->import->update([
                'failed_file_path' => 'failed_imports/' . basename($this->exceptionFilePath)
            ]);
        }

        foreach ($rows as $index => $row) {
            try {
                Log::info("--- Processing Row {$index} ---");
                Log::info("Raw row data:", $row->toArray());

                $data = [];

                if ($this->importType === 'legacy') {
                    // Legacy import - use direct field mapping
                    $data['customer_id'] = $row['Customer ID'] ?? $row['customer_id'] ?? null;
                    $data['name'] = $row['Name'] ?? $row['name'] ?? null;

                } else {
                    // Custom import - apply mapping
                    foreach ($this->mapping as $csvColumn => $dbColumn) {
                        Log::info("Checking mapping: CSV '{$csvColumn}' => DB '{$dbColumn}'");
                        
                        if (!empty($this->mapping)) {
        // Use mapping
                            foreach ($this->mapping as $csvColumn => $dbColumn) {
                                if (!empty($dbColumn)) {
                                    $value = $row[$csvColumn] ?? null;

                                    if (!is_null($value)) {
                                        $data[$dbColumn] = $value;
                                    }
                                }
                            }
                        } else {
                            // No mapping defined → fallback to row directly
                            $data = $row->toArray();
                        }
                    }
                }

                // Validate that we have at least customer_id
                if (empty($data['customer_id'])) {
                    $availableColumns = implode(', ', array_keys($data));
                    throw new \Exception("customer_id is required but missing. Available columns: {$availableColumns}");
                }

                // Prepare data for updateOrCreate
                $customerId = $data['customer_id'];
                $updateData = $data;
                unset($updateData['customer_id']);

                // Perform the database operation
                Client::updateOrCreate(
                    ['customer_id' => $customerId],
                    $updateData
                );
                
                $this->processed++;

            } catch (\Exception $e) {
                Log::error("Insert failed for row {$index}: " . $e->getMessage());
                $this->failed++;
                $this->logFailedRow($row, $e->getMessage());
            }
        }

        Log::info("Chunk completed - Processed: {$this->processed}, Failed: {$this->failed}");
    }

    protected function logFailedRow($row, string $reason): void
    {
        $rowArray = is_array($row) ? $row : $row->toArray();
        $handle = fopen($this->exceptionFilePath, 'a');
        fputcsv($handle, [json_encode($rowArray), $reason]);
        fclose($handle);

        $this->failedRecords[] = ['row' => $rowArray, 'reason' => $reason];
    }

    public function registerEvents(): array
    {
        return [
            BeforeImport::class => function (BeforeImport $event) {
                // Refresh the import model to ensure we have the latest
                $this->import->refresh();
                $this->import->update([
                    'status' => 'processing',
                    'started_at' => now()
                ]);
                Log::info('Import started for file: ' . $this->import->name);
            },

            AfterImport::class => function (AfterImport $event) {
                Log::info("Final import statistics - Processed: {$this->processed}, Failed: {$this->failed}");

                // Use database transaction to ensure save
                DB::transaction(function () {
                    $import = Import::find($this->import->id);
                    if ($import) {
                        $import->update([
                            'status' => 'completed',
                            'completed_at' => now(),
                            'records' => $this->processed,
                            'failed_records' => $this->failed,
                            'failed_file_path' => 'failed_imports/' . basename($this->exceptionFilePath),
                        ]);
                        Log::info("Import record updated successfully", [
                            'import_id' => $import->id,
                            'records' => $this->processed,
                            'failed_records' => $this->failed
                        ]);
                    }
                });
            },

            ImportFailed::class => function (ImportFailed $event) {
                Log::error('Import failed: ' . $event->getException()->getMessage());
                
                DB::transaction(function () use ($event) {
                    $import = Import::find($this->import->id);
                    if ($import) {
                        $import->update([
                            'status' => 'failed',
                            'completed_at' => now(),
                            'records' => $this->processed,
                            'failed_records' => $this->failed,
                            'failed_file_path' => 'failed_imports/' . basename($this->exceptionFilePath),
                        ]);
                    }
                });
            },
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function model(array $row)
        {
            // Exclude system fields so they don’t get inserted
            unset($row['created_by']);
            unset($row['created_at']);
            unset($row['updated_at']);

            $mappedData = [];

            foreach ($this->mapping as $csvColumn => $dbColumn) {
                if (!empty($dbColumn) && array_key_exists($csvColumn, $row)) {
                    $mappedData[$dbColumn] = $row[$csvColumn];
                }
            }

            return new Client($mappedData);
        }

}