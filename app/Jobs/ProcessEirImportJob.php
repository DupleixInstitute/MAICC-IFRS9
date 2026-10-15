<?php

namespace App\Jobs;

use App\Models\Import;
use App\Services\AuditLoggerService;
use App\Services\Eir\ContractMasterImportService;
use App\Services\Eir\ContractTransactionImportService;
use App\Services\Eir\DisbursementImportService;
use App\Services\Eir\FeeImportService;
use App\Services\Eir\FeeGlReconciliationService;
use App\Services\Eir\FeeSourcePrecedence;
use App\Services\Eir\FeeWorkbookTieOut;
use App\Services\Ebanker\GlFeeBuildService;
use App\Services\Eir\GlInterestImportService;
use App\Services\Eir\ReferenceRateImportService;
use App\Services\Eir\ScheduleImportService;
use App\Services\Imports\MappedFileReader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
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
        // Who pressed Import. A queue worker has no authenticated user, so the
        // id travels with the job for the rows that record created_by.
        private readonly ?int $userId = null,
    ) {}

    public function handle(
        MappedFileReader $reader,
        ScheduleImportService $schedules,
        FeeImportService $fees,
        ContractTransactionImportService $transactions,
        ContractMasterImportService $master,
        GlInterestImportService $glInterest,
        ReferenceRateImportService $referenceRates,
        DisbursementImportService $disbursements,
        FeeGlReconciliationService $feeGl,
        FeeWorkbookTieOut $tieOut,
        GlFeeBuildService $glFees,
        FeeSourcePrecedence $precedence,
    ): void {
        $import = Import::findOrFail($this->importId);
        $import->update(['status' => 'processing', 'started_at' => now()]);
        $exceptionPath = "failed_imports/eir_exception_{$import->id}.csv";

        try {
            $transforms = $this->transforms;
            if ($this->importType === 'reference_rates') {
                // Decision D19: the effective date reaches the service exactly
                // as the file wrote it. A declared date format would re-read a
                // dd/mm cell instead of refusing it, so the transform the
                // screen suggested is dropped here rather than trusted.
                unset($transforms['effective_date']);
            }
            if ($this->importType === 'disbursements') {
                // Same rule for the drawdown date: it decides which month a
                // tranche falls in, so it is refused rather than re-read.
                unset($transforms['disbursement_date']);
            }
            $read = $reader->read(Storage::path($this->storedPath), $this->importType, $this->mapping, $transforms);
            if ($this->importType === 'fees') {
                // Finance's fee workbook is proved against the GL fee postings first; a file that fails loads nothing
                $tie = $tieOut->check($read['rows']);
                if ($tie['status'] !== 'PASS') {
                    $this->refuseFees($import, $exceptionPath, $tie);

                    return;
                }
            }
            $result = match ($this->importType) {
                'contract_master' => $master->import($read['rows']),
                'schedule' => $schedules->import($read['rows']),
                'fees' => $this->loadFees($read['rows'], $tie, $fees, $glFees, $precedence),
                'contract_transactions' => $transactions->import($read['rows']),
                'gl_interest' => $glInterest->import($read['rows']),
                'reference_rates' => $referenceRates->import($read['rows'], importId: $import->id, userId: $this->userId ?? auth()->id()),
                'disbursements' => $disbursements->import($read['rows'], $import->id, $this->userId ?? auth()->id()),
            };
            if ($this->importType === 'fees') {
                // every fee held for the years the file covers, against 4871 and 4873 (spec v4 section 7.4); reports, never blocks
                $result['gl_reconciliation'] = $feeGl->report($result['years'] ?? []);
            }
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
    /**
     * Load the proved fee lines and tie each held GL posting a line accounts for
     * to that line's loan (GlFeeBuildService::assignHeld, audited), all or nothing,
     * then apply fee_source_precedence once more. What a person should still look
     * at (a match on amount and date only, a difference within the band, a posting
     * the system tied to another loan) goes to the exception file as notes.
     */
    private function loadFees(array $rows, array $tie, FeeImportService $fees, GlFeeBuildService $glFees, FeeSourcePrecedence $precedence): array
    {
        $user = (int) ($this->userId ?? auth()->id() ?? 0);

        return DB::transaction(function () use ($rows, $tie, $fees, $glFees, $precedence, $user) {
            $result = $fees->import($rows, null, $user ?: null, $this->originalName);
            $tied = 0;
            foreach ($tie['ties'] as $t) {
                $glFees->assignHeld((int) $t['held_id'], (string) $t['contract_id'], $user,
                    "Finance fee workbook {$this->originalName}, row {$t['row']}: the line accounts for this GL posting ({$t['posting']}, amount, GL and date)");
                $tied++;
            }
            if ($tie['coverage'] !== []) {
                // the months the file accounts for: Finance's lines are the source of record only for those (per-period precedence)
                $precedence->recordCoverage(FeeSourcePrecedence::TIER_FINANCE, min(array_column($tie['coverage'], 0)), max(array_column($tie['coverage'], 1)),
                    "Finance fee workbook {$this->originalName}", $user);
            }
            $result['precedence'] = array_diff_key($precedence->apply($user), ['exceptions' => 1]);
            $notes = [];
            foreach ($tie['lines'] as $l) {
                $how = implode('; ', $l['how']);
                if (str_contains($how, 'no name') || abs((float) ($l['difference'] ?? 0)) >= 0.01) {
                    $notes[] = "Row {$l['row']}, loan {$l['contract_id']}, GL {$l['gl']}: {$how}" . (abs((float) ($l['difference'] ?? 0)) >= 0.01 ? ' (difference ' . number_format((float) $l['difference'], 2) . ', within the band)' : '');
                }
            }
            foreach ($tie['conflicts'] as $c) {
                $notes[] = "Row {$c['row']}, loan {$c['contract_id']}: {$c['note']}";
            }
            $result['notes'] = $notes;
            $result['tie_out'] = ['status' => $tie['status'], 'lines' => count($tie['lines']), 'held_postings_tied' => $tied, 'conflicts' => count($tie['conflicts']), 'coverage' => $tie['coverage']];

            return $result;
        });
    }

    /** A fee file that does not tie to the GL: nothing loads; every reason is in the exception file. */
    private function refuseFees(Import $import, string $path, array $tie): void
    {
        $rows = array_map(fn ($r) => ['scope' => $r['row'] === null ? 'file' : "row {$r['row']}" . ($r['contract_id'] ? " / {$r['contract_id']}" : ''),
            'status' => 'refused', 'reason' => $r['reason']], $tie['refusals']);
        foreach ($tie['conflicts'] as $c) {
            $rows[] = ['scope' => "row {$c['row']} / {$c['contract_id']}", 'status' => 'notes', 'reason' => $c['note']];
        }
        $this->writeExceptionFile($path, $rows);
        $import->update(['status' => 'failed', 'records' => 0, 'failed_records' => count($tie['refusals']), 'failed_file_path' => $path, 'completed_at' => now()]);
        AuditLoggerService::log(action: 'EIR Fee Workbook Refused', entityType: 'Import', entityId: $import->id,
            data: ['meta' => ['file' => $this->originalName, 'refusals' => count($tie['refusals']), 'first' => array_slice($tie['refusals'], 0, 5)]]);
    }

    private function failureRows(array $result): array
    {
        $rows = [];
        // notes may be keyed by contract (Extract A) or be a plain list (the
        // reference-rate series, where a note is about a row, not a loan).
        foreach (['held', 'skipped', 'incomplete', 'restatements', 'notes'] as $status) {
            foreach (($result[$status] ?? []) as $scope => $reason) {
                $rows[] = ['scope' => is_int($scope) ? 'file' : $scope, 'status' => $status, 'reason' => $reason];
            }
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
