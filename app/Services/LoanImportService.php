<?php

namespace App\Services;

use App\Jobs\ProcessLoanImportJob;
use App\Models\Import;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class LoanImportService
{
    public function importGroupFile(UploadedFile $file, string $reportingPeriod, int $portfolioId, Import $import = null)
    {
        Log::info("Dispatching loan import job for period {$reportingPeriod}");
        
        // Store file temporarily and pass path instead of content
        $originalName = $file->getClientOriginalName();
        $tempPath = $file->storeAs('temp-imports', 'import_' . time() . '_' . $originalName);
        
        Log::info("File stored at path: '{$tempPath}'");
        
        // Dispatch the job with file path and import record
        ProcessLoanImportJob::dispatch($tempPath, $originalName, $reportingPeriod, $portfolioId, $import);
        
        return [
            'total_rows' => 0, // Will be calculated during job processing
            'imported' => 0,    // Will be calculated during job processing
            'queued' => true,      // Indicate that job was queued
            'file_name' => $originalName // Return file name for reference
        ];
    }
}