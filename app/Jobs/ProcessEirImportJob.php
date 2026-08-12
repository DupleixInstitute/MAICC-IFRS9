<?php

namespace App\Jobs;

use App\Models\Import;
use App\Services\AuditLoggerService;
use App\Services\Eir\ContractMasterImportService;
use App\Services\Eir\ContractTransactionImportService;
use App\Services\Eir\FeeImportService;
use App\Services\Eir\GlInterestImportService;
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

    public function handle(
        MappedFileReader $reader,
        ScheduleImportService $schedules,
        FeeImportService $fees,
        ContractTransactionImportService $transactions,
        ContractMasterImportService $master,
        GlInterestImportService $glInterest,
    ): void {
        $import = Import::findOrFail($this->importId);
        $import->update(['status' => 'processing', 'started_at' => now()]);
        $exceptionPath = "failed_imports/eir_exception_{$import->id}.csv";

        try {
            $read = $reader->read(Storage::path($this->storedPath), $this->importType, $this->mapping, $this->transforms);
            $result = match ($this->importType) {
                'contract_master' => $master->import($read['rows']),
                'schedule' => $schedules->import($read['rows']),
                'fees' => $fees->import($read['rows']),
                'contract_transactions' => $transactions->import($read['rows']),
                'gl_interest' => $glInterest->import($read['rows']),
            };
            $exceptions = $this->failureRows($result);
            if ($exceptions !== []) $this->writeExceptionFile($exceptionPath, $exceptions);

            // Only rows that did not load count as failures in the history.
            // Notices about rows that did load belong in the file, not in a
            // number the operator reads as "this import went wrong".
            $failed = count(array_filter($exceptions, fn ($row) => in_array($row['status'], ['held', 'skipped'], true)));

            $import->update([
                'status' => 'completed',
                'records' => $this->successfulRecords($result),
                'rows_processed' => $read['report']['total_rows'] ?? 0,
                'failed_records' => $failed,
                'failed_file_path' => $exceptions === [] ? null : $exceptionPath,
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
        return match ($this->importType) {
            // Extract B fans out to three destinations; counting only the
            // schedule rows would under-report the import in the history.
            'contract_transactions' => (int) ($result['loaded_rows'] ?? 0)
                + (int) ($result['actual_rows_loaded'] ?? 0)
                + (int) ($result['fee_result']['loaded_rows'] ?? 0),
            'contract_master' => (int) ($result['loaded_rows'] ?? 0)
                + (int) ($result['fee_result']['loaded_rows'] ?? 0),
            'gl_interest' => (int) ($result['loaded_rows'] ?? 0) + (int) ($result['restated_rows'] ?? 0),
            default => (int) ($result['loaded_rows'] ?? 0),
        };
    }

    /**
     * Everything a reviewer must look at goes to the downloadable exception
     * file — including rows that loaded. A contract created without a
     * repayment frequency, or a GL figure restated against a period that may
     * already have been reconciled, is not a failure but it is not silent
     * either.
     */
    private function failureRows(array $result): array
    {
        $rows = [];
        foreach (['held', 'skipped', 'incomplete', 'restatements'] as $status) {
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
