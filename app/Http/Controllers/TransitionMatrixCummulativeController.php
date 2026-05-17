<?php
namespace App\Http\Controllers;

use App\Models\LoanPortfolio;
use App\Models\TransitionMatrix;
use App\Models\TransitionMatrixCummulative;
use App\Models\TransitionMatrixCummulativeData;
use App\Models\TransitionProfileDefinition;
use App\Services\TransitionMatrixCummulativeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Exception;
use App\Models\ReportingPeriods;
use Carbon\Carbon;
use ZipArchive;

class TransitionMatrixCummulativeController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $status = $request->input('status');

        $query = TransitionMatrixCummulative::with(['transitionProfile', 'portfolio'])
            ->when($search, function ($query) use ($search) {
                $query->whereHas('transitionProfile', function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%');
                })
                ->orWhere('status', 'like', '%' . $search . '%');
            })
            ->when($startDate, fn ($q) => $q->where('start_period', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('end_period', '<=', $endDate))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('start_period', 'desc')
            ->orderBy('end_period', 'desc');

        $cumMatrix = $query->paginate(10);

        return Inertia::render('TransitionMatrix/Cummulative', [
            'cumMatrix' => $cumMatrix,
            'filters' => [
                'search' => $search,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'status' => $status,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('TransitionMatrix/CummulativeCreate', [
            'portfolios' => LoanPortfolio::select('id', 'name')->get(),
            'profiles' => TransitionProfileDefinition::select('id', 'profile_code', 'short_name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'start_period' => 'required|date',
            'end_period' => 'required|date',
            'portfolio_group' => 'required|exists:loan_portfolios,id',
            'transition_profile_id' => 'required|exists:transition_profile_definitions,id',
        ]);

        try {
            TransitionMatrixCummulativeService::createCumulativeRecord(
                $data['start_period'],
                $data['end_period'],
                $data['portfolio_group'],
                $data['transition_profile_id']
            );

            return redirect()->back()->with('success', 'Cumulative record created successfully!');
        } catch (Exception $e) {
            Log::error('Failed to create cumulative record: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create cumulative record. Please check logs.');
        }
    }

        public function updateLoanBook(Request $request, TransitionMatrixCummulative $matrix)
        {
            ini_set('max_execution_time', 300);

            $validated = $request->validate([
                'reporting_period' => 'required|date',
            ]);

            DB::beginTransaction();

            try {

                $pds = TransitionMatrixCummulativeData::where('cummulative_id', $matrix->id)
                    ->where('end_stage', 3)
                    ->whereNotNull('transition_probability_cummulated')
                    ->get()
                    ->keyBy('start_stage');

                if ($pds->isEmpty()) {
                    throw new \Exception("No PD data with end stage 3 found for this transition matrix");
                }

                $period = substr($validated['reporting_period'], 0, 7);

                $totalUpdated = 0;

           foreach ([1, 2, 3] as $stage) {
                if (!isset($pds[$stage]) && $stage !== 3) {
                    continue;
                }

                // Determine PD decimal
                if ($stage === 3) {
                    $pdDecimal = 1.0; // 100% as decimal
                } else {
                    $pdDecimal = $pds[$stage]->transition_probability_cummulated / 100;
                }

                $affected = DB::update("
                    UPDATE loan_books
                    SET pd_value = ?
                    WHERE reporting_period = ?
                    AND calculated_ifrs9_stage = ?
                ", [
                    $pdDecimal,
                    $period,
                    $stage,
                ]);

                $totalUpdated += $affected;
                    }

                DB::commit();

                $periodParts = explode('-', $validated['reporting_period']);
                $year = $periodParts[0] . '-01-01';
                $month = $periodParts[0] . '-' . $periodParts[1] . '-01';

                ReportingPeriods::updateOrCreate(
        ['period' => substr($validated['reporting_period'], 0, 7)],
            [
                        'reporting_year' => $year,
                        'reporting_month' => $month,
                        'pd_id' => $matrix->id,
                        'pd_calculation_source' => $matrix->calculation_source,
                    ]
                );

                // Log::channel('loan_updates')->info('Loan Book Updated with raw SQL', [
                //     'matrix_id' => $matrix->id,
                //     'reporting_period' => $validated['reporting_period'],
                //     'updated_loans' => $totalUpdated,
                //     'user_id' => auth()->id(),
                // ]);

                return back()->with([
                    'success' => 'Loan book PD updated successfully ',
                    'updated_count' => $totalUpdated,
                ]);

            } catch (\Exception $e) {
                DB::rollBack();

                // Log::channel('loan_updates')->error('Loan Book Update Failed', [
                //     'error' => $e->getMessage(),
                //     'matrix_id' => $matrix->id,
                //     'reporting_period' => $validated['reporting_period'] ?? null,
                // ]);

                return back()->withErrors(['error' => 'Update failed: ' . $e->getMessage()]);
            }
        }



        public function getData($matrix)
            {
                $cumulative = TransitionMatrixCummulative::with('data')->findOrFail($matrix);

                $groupedMatrix = [];
                $startTotals = [];
                $endStageTotals = [];
                $pdPercentages = [];
                $grandTotal = 0;

                foreach ($cumulative->data as $data) {
                    $start = $data->start_stage;
                    $end = $data->end_stage;
                    $amount = $data->transition_balance_cummulated;

                    $groupedMatrix[$start][$end] = [
                        'transition_balance_cummulated' => $amount,
                    ];

                    $startTotals[$start] = ($startTotals[$start] ?? 0) + $amount;
                    $endStageTotals[$end] = ($endStageTotals[$end] ?? 0) + $amount;

                    $grandTotal += $amount;
                }

                foreach ($cumulative->data as $data) {
                    $start = $data->start_stage;
                    $pdPercentages[$start] = $data->transition_probability_cummulated ?? 0;
                }

                $startStages = \App\Models\TransitionProfileOption::where('profile_id', $cumulative->transition_profile_id)
                    ->where('is_start_or_end', 'Start')
                    ->orderBy('ordering_index')
                    ->get();

                $endStages = \App\Models\TransitionProfileOption::where('profile_id', $cumulative->transition_profile_id)
                    ->where('is_start_or_end', 'End')
                    ->orderBy('ordering_index')
                    ->get();

                return response()->json([
                    'matrix' => $groupedMatrix,
                    'startStages' => $startStages,
                    'endStages' => $endStages,
                    'startTotals' => $startTotals,
                    'pdPercentages' => $pdPercentages,
                    'endStageTotals' => $endStageTotals,
                    'grandTotal' => $grandTotal,
                ]);
            }

    public function updateData(Request $request, TransitionMatrixCummulative $matrix)
    {
        $data = $request->input('matrix', []);
        $total_balance  = 0;

        foreach ($data as $item) {
            TransitionMatrixCummulativeData::updateOrCreate(
                [
                    'cummulative_id' => $matrix->id,
                    'start_stage' => $item['start_stage'],
                    'end_stage' => $item['end_stage'],
                ],
                [
                    'transition_balance_cummulated' => $item['transition_balance_cummulated'],
                ]
            );
            $total_balance = $total_balance + $item['transition_balance_cummulated'];
        }

        $matrix->update([
            'transition_balance_cummulated' => $total_balance,
        ]);

        return response()->json(['message' => 'Matrix updated successfully.']);
    }
    public function rerun(TransitionMatrixCummulative $matrix)
    {
        try {
            // Optionally: Delete old data before rerun
            $matrix->data()->delete();

            TransitionMatrixCummulativeService::createCumulativeRecord(
                $matrix->start_period,
                $matrix->end_period,
                $matrix->portfolio_group,
                $matrix->transition_profile_id
            );

            return response()->json(['message' => 'Re-run completed successfully.']);
        } catch (Exception $e) {
            return response()->json(['message' => 'Error during re-run: ' . $e->getMessage()], 500);
        }
    }

    public function keyLock(Request $request, $matrix)
    {
        $request->validate([
            'status' => 'nullable|in:draft,closed',
        ]);

        $matrix = TransitionMatrixCummulative::findOrFail($matrix);

        $existing = TransitionMatrixCummulative::where('id', '!=', $matrix->id)
            ->where('start_period', $matrix->start_period)
            ->where('end_period', $matrix->end_period)
            ->where('status', 'closed')
            ->exists();

        if ($existing) {
            return back()->with('error', 'A closed record already exists for the same reporting period.');
        }

        if (
            $matrix->status == 'closed'
            && !auth()->user()?->hasRole('admin')
        ) {
            return back()->with('error', 'Only an Administrator can unlock a closed record.');
        }

        $matrix->status = $matrix->status === 'closed' ? 'draft' : 'closed';
        $matrix->save();

        return back()->with('success', 'Record ' . ($matrix->status === 'closed' ? 'locked' : 'unlocked') . '.');
    }

    // public function showList($matrix){

    // }

    public function destroy($matrix)
    {
        // Debug logging
        Log::info('Destroy method called for matrix ID: ' . $matrix);
        Log::info('Request method: ' . request()->method());
        Log::info('Request URL: ' . request()->fullUrl());

        DB::beginTransaction();

        try {
            $cummulative = TransitionMatrixCummulative::findOrFail($matrix);
            Log::info('Found cumulative matrix: ' . $cummulative->id);

            if(!$cummulative){
                Log::error('Cumulative Matrix not found');
                return back()->with('error','Cummulative Matrix not found');
            }

            // Delete related cumulative matrix data
            $deletedData = TransitionMatrixCummulativeData::where('cummulative_id', $cummulative->id)->delete();
            Log::info('Deleted ' . $deletedData . ' related data records');

            // Delete the cumulative matrix itself (hard delete to avoid unique constraint conflicts)
            $cummulative->forceDelete();
            Log::info('Force deleted matrix ID: ' . $matrix);

            DB::commit();

            return redirect()->route('transition-matrix-cummulative.index')
                ->with('success', 'Cummulative Matrix and all related data deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete failed: ' . $e->getMessage());

            return back()->withErrors(['error' => 'Failed to delete cumulative matrix: ' . $e->getMessage()]);
        }
    }

    public function downloadReportByPeriod(Request $request)
    {
        $request->validate([
            'start_period' => 'required|date_format:Y-m',
            'end_period' => 'required|date_format:Y-m',
            'format' => 'in:csv,excel',
            'export_type' => 'in:summary,matrix',
            'include_headers' => 'boolean',
            'compress_file' => 'boolean'
        ]);

        $start = Carbon::createFromFormat('Y-m', $request->start_period)->startOfMonth();
        $end = Carbon::createFromFormat('Y-m', $request->end_period)->endOfMonth();

        $format = $request->get('format', 'csv');
        $exportType = $request->get('export_type', 'summary');
        $includeHeaders = $request->get('include_headers', true);
        $compressFile = $request->get('compress_file', true);

        if ($exportType === 'matrix') {
            return $this->exportMatrixFormat($request, $start, $end, $format, $includeHeaders, $compressFile);
        } else {
            return $this->exportSummaryFormat($request, $start, $end, $format, $includeHeaders, $compressFile);
        }
    }

    private function exportSummaryFormat($request, $start, $end, $format, $includeHeaders, $compressFile)
    {
        $matrices = TransitionMatrixCummulative::select([
                'id',
                'transition_profile_id',
                'pd_calculation_id',
                'start_period',
                'end_period',
                'records_counted',
                'periods_count',
                'transition_balance_total',
                'run_no',
                'status',
                'last_reporting_period',
                'calculation_source',
            ])
            ->with(['portfolio:id,name', 'transitionProfile:id,name'])
            ->where('start_period', '<=', $end)
            ->where('end_period', '>=', $start)
            ->where('status', 'closed')
            ->orderBy('start_period')
            ->get();

        if ($matrices->isEmpty()) {
            return back()->with('error', 'No locked periods found for the selected date range');
        }

        // CSV Header
        $csvHeader = [
            'Matrix ID',
            'Profile',
            'Portfolio Group',
            'Start Period',
            'End Period',
            'Records Counted',
            'Reporting Periods',
            'Transition Balance',
            'Calculation Runs',
            'Status',
            'Last Reporting Period',
            'Calculation Source',
        ];

        $csvRows = [];

        // Add report range as first row
        $csvRows[] = ["Closed Transition Matrix Cumulative Report: From {$request->start_period} To {$request->end_period}"];
        $csvRows[] = []; // empty row for spacing

        // Add column headers if requested
        if ($includeHeaders) {
            $csvRows[] = $csvHeader;
        }

        // Add matrix data
        foreach ($matrices as $matrix) {
            $csvRows[] = [
                $matrix->id,
                $matrix->transitionProfile ? $matrix->transitionProfile->name : 'N/A',
                $matrix->portfolio ? $matrix->portfolio->name : 'N/A',
                $matrix->start_period,
                $matrix->end_period,
                $matrix->records_counted,
                $matrix->periods_count,
                $matrix->transition_balance_total,
                $matrix->run_no,
                $matrix->status === 'closed' ? 'Closed' : 'Draft',
                $matrix->last_reporting_period,
                ucfirst($matrix->calculation_source),
            ];
        }

        // Convert array of rows to CSV text
        $csvContent = '';
        foreach ($csvRows as $row) {
            // Escape any commas in data
            $escapedRow = array_map(function($field) {
                $field = str_replace('"', '""', $field); // escape quotes
                return "\"{$field}\"";
            }, $row);
            $csvContent .= implode(',', $escapedRow) . "\n";
        }

        $filename = "Transition_Matrix_Cumulative_Report_{$request->start_period}_to_{$request->end_period}";
        $csvPath = storage_path("app/{$filename}.csv");
        file_put_contents($csvPath, $csvContent);

        Log::info("Cumulative CSV file created: {$csvPath}, size: " . filesize($csvPath) . " bytes");

        if ($compressFile) {
            $zipPath = storage_path("app/{$filename}.zip");
            $zip = new ZipArchive();
            $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip->addFile($csvPath, "{$filename}.csv");
            $zip->close();

            Log::info("Cumulative ZIP file created: {$zipPath}, size: " . filesize($zipPath) . " bytes");
            return response()->download($zipPath, "{$filename}.zip", [
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment; filename="' . $filename . '.zip"'
            ])->deleteFileAfterSend(true);
        } else {
            return response()->download($csvPath, "{$filename}.csv", [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '.csv"'
            ])->deleteFileAfterSend(true);
        }
    }

    private function exportMatrixFormat($request, $start, $end, $format, $includeHeaders, $compressFile)
    {
        // Get matrices for the period
        $matrices = TransitionMatrixCummulative::with(['portfolio', 'transitionProfile'])
            ->where('start_period', '<=', $end)
            ->where('end_period', '>=', $start)
            ->where('status', 'closed')
            ->orderBy('start_period')
            ->get();

        if ($matrices->isEmpty()) {
            return back()->with('error', 'No locked periods found for the selected date range');
        }

        Log::info("Found " . $matrices->count() . " cumulative matrices for export");

        $csvRows = [];

        foreach ($matrices as $matrix) {
            // Get matrix data for this specific matrix
            $matrixData = TransitionMatrixCummulativeData::where('cummulative_id', $matrix->id)->get();

            // Matrix Header
            $csvRows[] = ["Closed Transition Matrix Cumulative Report"];
            $csvRows[] = ["Profile: " . ($matrix->transitionProfile ? $matrix->transitionProfile->name : 'N/A')];
            $csvRows[] = ["Period: " . $matrix->start_period . " to " . $matrix->end_period];
            $csvRows[] = ["Portfolio Group: " . ($matrix->portfolio ? $matrix->portfolio->name : 'N/A')];
            $csvRows[] = ["Matrix ID: " . $matrix->id];
            $csvRows[] = []; // empty row

            // State Matrix Header
            $csvRows[] = ["STATE MATRIX (Balances):"];
            $csvRows[] = ["", "Stage 1", "Stage 2", "Stage 3", "Write-off"];
            $csvRows[] = ["Stage 1", "", "", "", ""];
            $csvRows[] = ["Stage 2", "", "", "", ""];
            $csvRows[] = ["Stage 3", "", "", "", ""];
            $csvRows[] = ["Write-off", "", "", "", ""];

            // Build transition matrix with balances
            $states = ['1' => 'Stage 1', '2' => 'Stage 2', '3' => 'Stage 3', 'Paid' => 'Write-off'];
            $transitionMatrix = [];

            // Initialize matrix with balances
            foreach ($states as $stateKey => $stateName) {
                $transitionMatrix[$stateKey] = [];
                foreach ($states as $toStateKey => $toStateName) {
                    $transitionMatrix[$stateKey][$toStateKey] = 0;
                }
            }

            // Fill matrix from entries with balances
            foreach ($matrixData as $entry) {
                $fromState = $entry->start_stage;
                $toState = $entry->end_stage;

                if (isset($transitionMatrix[$fromState][$toState])) {
                    // Use transition_balance_cummulated as the balance
                    $balance = $entry->transition_balance_cummulated ?? 0;
                    $transitionMatrix[$fromState][$toState] += $balance;
                }
            }

            // Add matrix rows with balances
            $rowIndex = 0;
            foreach ($states as $stateKey => $stateName) {
                $row = [$stateName];
                foreach ($states as $toStateKey => $toStateName) {
                    $balance = $transitionMatrix[$stateKey][$toStateKey] ?? 0;
                    $row[] = number_format($balance, 2);
                }
                // Replace the placeholder row with actual data
                $csvRows[11 + $rowIndex] = $row;
                $rowIndex++;
            }

            $csvRows[] = []; // empty row

            // Balances Header
            $csvRows[] = ["BALANCES:"];
            $csvRows[] = ["Portfolio Group", "Start Stage", "End Stage", "Start Balance", "Transition Balance"];
            $csvRows[] = ["-----------------------------------------------------------------------------------------------"];

            // Add balance data
            $stateMapping = ['1' => 'Stage 1', '2' => 'Stage 2', '3' => 'Stage 3', 'Paid' => 'Write-off'];
            foreach ($matrixData as $entry) {
                $startBalance = $entry->start_total_cummulated ?? 0;
                $transitionBalance = $entry->transition_balance_cummulated ?? 0;
                $csvRows[] = [
                    $matrix->portfolio?->name ?? 'N/A',
                    $stateMapping[$entry->start_stage] ?? $entry->start_stage,
                    $stateMapping[$entry->end_stage] ?? $entry->end_stage,
                    number_format($startBalance, 2),
                    number_format($transitionBalance, 2)
                ];
            }

            $csvRows[] = []; // empty row between matrices
        }

        // Convert to CSV content
        $csvContent = '';
        foreach ($csvRows as $row) {
            $escapedRow = array_map(function($field) {
                $field = str_replace('"', '""', $field);
                return "\"{$field}\"";
            }, $row);
            $csvContent .= implode(',', $escapedRow) . "\n";
        }

        $filename = "Transition_Matrix_Cumulative_Report_{$request->start_period}_to_{$request->end_period}";
        $csvPath = storage_path("app/{$filename}.csv");
        file_put_contents($csvPath, $csvContent);

        Log::info("Cumulative Matrix CSV file created: {$csvPath}, size: " . filesize($csvPath) . " bytes");

        if ($compressFile) {
            $zipPath = storage_path("app/{$filename}.zip");
            $zip = new ZipArchive();
            $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip->addFile($csvPath, "{$filename}.csv");
            $zip->close();

            Log::info("Cumulative Matrix ZIP file created: {$zipPath}, size: " . filesize($zipPath) . " bytes");
            return response()->download($zipPath, "{$filename}.zip", [
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment; filename="' . $filename . '.zip"'
            ])->deleteFileAfterSend(true);
        } else {
            return response()->download($csvPath, "{$filename}.csv", [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '.csv"'
            ])->deleteFileAfterSend(true);
        }
    }

          public function attachFile(TransitionMatrixCummulative $matrix, Request $request)
        {
            // Log::info('Attach file request started', [
            //     'lgd_id' => $lgdC->id,
            //     'has_file' => $request->hasFile('file'),
            // ]);

            $request->validate([
                'file' => 'required|file|max:51200|mimes:pdf,doc,docx,xls,xlsx,jpg,png',
            ]);

            if ($matrix->status === 'closed') {
                //Log::warning('Attempt to attach file to closed record', ['matrix_id' => $matrix->id]);
                return back()->withErrors(['file' => 'Cannot attach file to a closed record.']);
            }

            // Delete old documents
            $deleted = $matrix->supportingDocuments()->delete();
            //Log::info('Old supporting documents deleted', ['count' => $deleted]);

            try {
                $document = \App\Helpers\DocumentHelper::upload(
                    $request->file('file'),
                    $matrix,
                    'pd_support'
                );

                // Log::info('File uploaded successfully', [
                //     'document_id' => $document->id,
                //     'path' => $document->path,
                // ]);

                return back()->with('success', 'File attached successfully.');

            } catch (\Throwable $e) {

                // Log::error('File upload failed', [
                //     'error' => $e->getMessage(),
                //     'lgd_id' => $lgdC->id,
                // ]);

                return back()->withErrors(['file' => 'Upload failed. Check logs.']);
            }
        }


   public function downloadFile($id)
    {
        $matrix = TransitionMatrixCummulative::findOrFail($id);

        $document = $matrix->supportingDocuments()
            ->latest()
            ->first();

        if (!$document || !Storage::disk($document->disk)->exists($document->path)) {
            return back()->with('error', 'File not found.');
        }

        return Storage::disk($document->disk)->download(
            $document->path,
            $document->original_name
        );
    }
}
