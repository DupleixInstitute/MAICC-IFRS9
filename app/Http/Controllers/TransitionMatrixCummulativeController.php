<?php
namespace App\Http\Controllers;

use App\Models\LoanPortfolio;
use App\Models\TransitionMatrix;
use App\Models\TransitionMatrixCummulative;
use App\Models\TransitionMatrixCummulativeData;
use App\Models\TransitionProfileDefinition;
use App\Services\TransitionMatrixCummulativeService;
use App\Models\ReportingPeriods;
use App\Models\SupportingDocument;
use App\Helpers\DocumentHelper;
use Illuminate\Http\Request;
use App\Models\IndustryType;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Console\Input\Input;
use Illuminate\Support\Facades\Storage;
use Exception;




class TransitionMatrixCummulativeController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = TransitionMatrixCummulative::with(['transitionProfile', 'portfolio','sector'])
            ->when($search, function ($query) use ($search) {
                $query->whereHas('transitionProfile', function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%');
                })
                ->orWhere('status', 'like', '%' . $search . '%');
            })
            ->when($startDate, fn ($q) => $q->where('start_period', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('end_period', '<=', $endDate));

        $cumMatrix = $query->paginate(10);

        return Inertia::render('TransitionMatrix/Cummulative', [
            'cumMatrix' => $cumMatrix,
            'filters' => [
                'search' => $search,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('TransitionMatrix/CummulativeCreate', [
            'portfolios' => LoanPortfolio::select('id', 'name')->get(),
            'sectors' => IndustryType::select('id', 'code','name')->get(),
            'profiles' => TransitionProfileDefinition::select('id', 'profile_code', 'short_name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'start_period' => 'required|date',
            'end_period' => 'required|date',
            'pd_calculation_level' => 'required|in:portfolio,sector',
            'pd_calculation_id'   => 'required_if:pd_calculation_level,portfolio|nullable|integer',
            'pd_calculation_code' => 'required_if:pd_calculation_level,sector|nullable|string',
            'transition_profile_id' => 'required|exists:transition_profile_definitions,id',
        ]);

        try {
            TransitionMatrixCummulativeService::createCumulativeRecord(
                $data['start_period'],
                $data['end_period'],
                $data['pd_calculation_level'],
                $data['pd_calculation_id'] ?? null,
                $data['pd_calculation_code'] ?? null,
                $data['transition_profile_id']
            );

            return redirect()->back()->with('success', 'Cumulative record created successfully!');
        } catch (Exception $e) {
            Log::error('Failed to create cumulative record: ' . $e->getMessage());
            return redirect('transition-matrix-cummulative.index')->with('error', 'Failed to create cumulative record. Please check logs.');
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

                    $scope = $matrix->pd_calculation_level; // 'portfolio' or 'sector'

                    if (!in_array($scope, ['portfolio', 'sector'])) {
                        throw new \Exception("Invalid PD calculation level.");
                    }

                    $pds = TransitionMatrixCummulativeData::where('cummulative_id', $matrix->id)
                        ->where('end_stage', 3)
                        ->whereNotNull('transition_probability_cummulated')
                        ->get()
                        ->keyBy('start_stage');

                    if ($pds->isEmpty()) {
                        throw new \Exception("No PD data with end stage 3 found for this transition matrix");
                    }

                    $periodKey = substr($validated['reporting_period'], 0, 7);

                    $totalUpdated = 0;

                    foreach ([1, 2, 3] as $stage) {

                        if (!isset($pds[$stage]) && $stage !== 3) {
                            continue;
                        }

                        if ($stage === 3) {
                            $pdDecimal = 1.0;
                        } else {
                            $pdDecimal = $pds[$stage]->transition_probability_cummulated / 100;
                        }

                        $scopeSql = "";
                        $scopeBindings = [];

                        if ($scope === 'portfolio') {
                            $scopeSql = " AND portfolio_group = ?";
                            $scopeBindings[] = $matrix->pd_calculation_id;
                        }

                        if ($scope === 'sector') {
                            $scopeSql = " AND industry_code = ?";
                            $scopeBindings[] = $matrix->pd_calculation_code;
                        }

                        $affected = DB::update("
                            UPDATE loan_books
                            SET pd_prefli = ?
                            WHERE LEFT(reporting_period, 7) = ?
                            AND ifrs9stage_pre_qualitative = ?
                            $scopeSql
                        ", array_merge([
                            $pdDecimal,
                            $periodKey,
                            $stage
                        ], $scopeBindings));

                        $totalUpdated += $affected;

                        DB::statement("
                            UPDATE loan_books
                            SET lifetime_pd = 1 - POWER((1 - ?), remaining_tenor)
                            WHERE LEFT(reporting_period, 7) = ?
                            AND ifrs9stage_pre_qualitative = ?
                            AND remaining_tenor IS NOT NULL
                            $scopeSql
                        ", array_merge([
                            $pdDecimal,
                            $periodKey,
                            $stage
                        ], $scopeBindings));
                    }

                    DB::commit();

                    $periodParts = explode('-', $validated['reporting_period']);
                    $year  = (int)$periodParts[0];
                    $month = (int)$periodParts[1];
                    $period = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01';

                    ReportingPeriods::updateOrCreate(
                        ['period' => $period],
                        [
                            'reporting_year' => $year,
                            'reporting_month' => $month,
                            'pd_id' => $matrix->id,
                            'pd_calculation_source' => $matrix->calculation_source,
                        ]
                    );

                    return back()->with([
                        'success' => 'Loan book PD updated successfully',
                        'updated_count' => $totalUpdated,
                    ]);

                } catch (\Exception $e) {

                    DB::rollBack();

                    return back()->withErrors([
                        'error' => 'Update failed: ' . $e->getMessage()
                    ]);
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
    public function rerun(TransitionMatrixCummulative $matrix)
    {
        try {
            // Optionally: Delete old data before rerun
            $matrix->data()->delete();

            TransitionMatrixCummulativeService::createCumulativeRecord(
                $matrix->start_period,
                $matrix->end_period,
                $matrix->pd_calculation_level,
                $matrix->pd_calculation_id,
                $matrix->pd_calculation_code,
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
                    ->where('pd_calculation_level', $matrix->pd_calculation_level)
                    ->where('pd_calculation_id', $matrix->pd_calculation_id)
                    ->where('pd_calculation_code', $matrix->pd_calculation_code)
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

          public function attachFile(TransitionMatrixCummulative $matrix, Request $request)
        {
            // Log::info('Attach file request started', [
            //     'lgd_id' => $lgdC->id,
            //     'has_file' => $request->hasFile('file'),
            // ]);

            $request->validate([
                'file' => 'required|file|max:51200|mimes:pdf,doc,docx,xls,xlsx,jpg,png',
            ]);

            if ($matrix->is_active_or_closed === 'closed') {
                //Log::warning('Attempt to attach file to closed LGD', ['lgd_id' => $lgdC->id]);
                return back()->withErrors(['file' => 'Cannot attach file to a closed LGD record.']);
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

        $document = $lgd->supportingDocuments()
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

    


    public function destroy($matrix){
        $cummulative = TransitionMatrixCummulative::findOrFail($matrix);

        if(!$cummulative){
            return back()->with('error','Cummulative Matrix not found');
        }
        $cummulative->delete();
        return back()->with('success','Cummulative Matrix deleted successfully');
    }
}
