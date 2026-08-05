<?php

namespace App\Jobs;

use App\Models\Import;
use App\Services\AuditLoggerService;
use App\Services\Eir\ExtractBImportService;
use App\Services\Eir\FeeImportService;
use App\Services\Eir\ScheduleImportService;
use App\Services\Imports\MappedFileReader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Queue-backed EIR intake using the same tracked lifecycle as other imports. */
class ProcessEirImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(
        private readonly int $importId,
        private readonly string $storedPath,
        private readonly string $originalName,
        private readonly string $importType,
        private readonly array $mapping,
        private readonly array $transforms,
    ) {}

    public function handle(MappedFileReader $reader, ScheduleImportService $schedules, FeeImportService $fees, ExtractBImportService $extractB): void
    {
        $import = Import::findOrFail($this->importId);
        $import->update(['status' => 'processing', 'started_at' => now()]);
        $exceptionPath = "failed_imports/eir_exception_{$import->id}.csv";

        try {
            $read = $reader->read(Storage::path($this->storedPath), $this->importType, $this->mapping, $this->transforms);
            $result = match ($this->importType) {
                'schedule' => $schedules->import($read['rows']),
                'fees' => $fees->import($read['rows']),
                'extract_b' => $extractB->import($read['rows']),
            };
            $failures = $this->failureRows($result);
            if ($failures !== []) $this->writeExceptionFile($exceptionPath, $failures);

            $import->update([
                'status' => 'completed',
                'records' => $this->successfulRecords($result),
                'rows_processed' => $read['report']['total_rows'] ?? 0,
                'failed_records' => count($failures),
                'failed_file_path' => $failures === [] ? null : $exceptionPath,
                'completed_at' => now(),
            ]);
            AuditLoggerService::log(action: 'EIR Intake Import', entityType: 'Import', entityId: $import->id,
                data: ['meta' => ['import_type' => $this->importType, 'file' => $this->originalName, 'result' => $result]]);
        } catch (Throwable $e) {
            $this->writeExceptionFile($exceptionPath, [['scope' => 'file', 'status' => 'failed', 'reason' => $e->getMessage()]]);
            $import->update(['status' => 'failed', 'failed_records' => 1, 'failed_file_path' => $exceptionPath, 'completed_at' => now()]);
            Log::error('EIR import failed', ['import_id' => $import->id, 'error' => $e->getMessage()]);
            throw $e;
        } finally {
            Storage::delete($this->storedPath);
        }
    }

    public function failed(Throwable $exception): void
    {
        Import::whereKey($this->importId)->whereNot('status', 'failed')->update(['status' => 'failed', 'completed_at' => now()]);
    }

    private function successfulRecords(array $result): int
    {
        if ($this->importType !== 'extract_b') return (int) ($result['loaded_rows'] ?? 0);
        return (int) ($result['loaded_rows'] ?? 0) + (int) ($result['actual_rows_loaded'] ?? 0)
            + (int) ($result['fee_result']['loaded_rows'] ?? 0);
    }

    private function failureRows(array $result): array
    {
        $rows = [];
        foreach (['held', 'skipped'] as $status) {
            foreach (($result[$status] ?? []) as $scope => $reason) $rows[] = compact('scope', 'status', 'reason');
        }
        return $rows;
    }

    private function writeExceptionFile(string $path, array $rows): void
    {
        Storage::disk('public')->makeDirectory('failed_imports');
        $handle = fopen(Storage::disk('public')->path($path), 'w');
        fputcsv($handle, ['Contract / Scope', 'Status', 'Reason']);
        foreach ($rows as $row) fputcsv($handle, [$row['scope'], $row['status'], $row['reason']]);
        fclose($handle);
    }
}
