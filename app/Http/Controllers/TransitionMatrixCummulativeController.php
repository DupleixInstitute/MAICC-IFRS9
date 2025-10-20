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
use Exception;
use App\Models\ReportingPeriods;

class TransitionMatrixCummulativeController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = TransitionMatrixCummulative::with(['transitionProfile', 'portfolio'])
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
                    AND ifrs9stage_pre_qualitative = ?
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

    public function destroy($matrix){
        $cummulative = TransitionMatrixCummulative::findOrFail($matrix);

        if(!$cummulative){
            return back()->with('error','Cummulative Matrix not found');
        }
        $cummulative->delete();
        return back()->with('success','Cummulative Matrix deleted successfully');
    }
}
