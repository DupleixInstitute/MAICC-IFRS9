<?php

namespace App\Http\Controllers;

use App\Models\LoanProduct;
use App\Models\TransitionMatrix;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use App\Models\TransitionProfileDefinition;
use App\Models\LoanPortfolio;
use App\Models\LoanBook;
use App\Models\ReportingPeriods;
use Illuminate\Support\Facades\DB;
use App\Models\TransitionMatrixData;
use App\Models\TransitionProfileOption;
use App\Services\TransitionMatrixService;
use Illuminate\Support\Facades\Log;

class TransitionMatrixController extends Controller
{
    protected $portfolioGroups = [
        'Business Loans',
        'Personal Loans',
        'Mortgage Loans',
        'Agricultural Loans',
        'SME Loans',
        'Corporate Loans'
    ];

    protected $states = [
        'Stage 1' => 'Performing',
        'Stage 2' => 'Under Performing',
        'Stage 3' => 'Non-Performing',
        'Write-off' => 'Written Off'
    ];

    public function index(Request $request)
    {
        $search = $request->input('search');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $query = TransitionMatrix::with('transitionProfile', 'portfolio')
            ->when($search, function($query) use ($search) {
                $query->whereHas('transitionProfile', function($q) use ($search) {
                    $q->where('name', 'like', '%'.$search.'%');
                })
                ->orWhere('status', 'like', '%'.$search.'%')
                ->orWhere('description', 'like', '%'.$search.'%');
            })
            ->when($startDate, function($query) use ($startDate) {
                $query->where('start_reporting_period', '>=', $startDate);
            })
            ->when($endDate, function($query) use ($endDate) {
                $query->where('end_reporting_period', '<=', $endDate);
            });

        return Inertia::render('TransitionMatrix/Index', [
            'matrices' => $query->latest()->paginate(10),
            'filters' => $request->only(['search', 'start_date', 'end_date'])
        ]);
    }

    public function create()
    {
        return Inertia::render('TransitionMatrix/Create', [
            'profiles' => TransitionProfileDefinition::select('id', 'profile_code', 'short_name')->get(),
            'portfolios' => LoanPortfolio::select('id', 'name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        set_time_limit(300); // Increase max execution time for long calculations

        $validated = $request->validate([
            'transition_profile_id' => 'required|exists:transition_profile_definitions,id',
            'portfolio_group_id' => 'required|exists:loan_portfolios,id',
            'start_reporting_period' => 'required',
            'end_reporting_period' => 'required|after:start_reporting_period',
            'calculation_source' => 'required|in:manual,system',
        ]);

        // Parse year and month
        $startPeriodParts = explode('-', $request->start_reporting_period);
        $endPeriodParts = explode('-', $request->end_reporting_period);

        // if ($request->hasFile('external_file_path')) {
        //     $validated['external_file_path'] = $request->file('external_file_path')
        //         ->store('transition-matrices', 'public');
        // }

        //$validated['status'] = 'draft';

        $start_year = (int) $startPeriodParts[0];
        $start_month = (int) $startPeriodParts[1];
        $end_year = (int) $endPeriodParts[0];
        $end_month = (int) $endPeriodParts[1];

        $yearDiff = $end_year - $start_year;
        $monthDiff = $end_month - $start_month;

        if ($monthDiff < 0) {
            $yearDiff--;
            $monthDiff += 12;
        }

        $transition_years = round($yearDiff + ($monthDiff / 12), 0);

        $transitionMatrix = TransitionMatrix::create([
            'transition_profile_id'      => $request->transition_profile_id,
            'start_reporting_period'     => $request->start_reporting_period,
            'end_reporting_period'       => $request->end_reporting_period,
            'pd_start_stage_total_type'  => $request->pd_start_stage_total_type,
            'portfolio_group_id'         => $request->portfolio_group_id,
            'calculation_source'         => $request->calculation_source,
            'start_year'                 => $startPeriodParts[0],
            'start_month'                => $startPeriodParts[1],
            'end_year'                   => $endPeriodParts[0],
            'end_month'                  => $endPeriodParts[1],
            'transition_years'           => $transition_years ?? 0,
            'run_no'                     => 1,
            'records_count_updated'      => 0,
            'records_count_transitioned' => 0,
            'reporting_periods_count'    => 0,
            'updated_balance'            => 0.00,
            'transition_balance'         => 0.00,
            'last_calculation_date'      => now(),
            'portfolio_count'            => 0,
            'book_updated_at'            => now(),
            'take_on_flag'               => $request->take_on_flag ?? 0,
            'comments'                   => $request->comments ?? '',
            'user_name'                  => auth()->user()->name ?? 'system',
        ]);

        // DB::beginTransaction();

        // try {
        //     // Create TransitionMatrix record

        //     // Fetch transition profile definition
        //     $profileDefinition = TransitionProfileDefinition::findOrFail($request->transition_profile_id);

        //     // Fetch start and end stages
        //     $startStages = TransitionProfileOption::where('profile_id', $request->transition_profile_id)
        //         ->where('is_start_or_end', 'Start')
        //         ->orderBy('ordering_index')
        //         ->get();

        //     $endStages = TransitionProfileOption::where('profile_id', $request->transition_profile_id)
        //         ->where('is_start_or_end', 'End')
        //         ->orderBy('ordering_index')
        //         ->get();

        //     // Initialize matrices
        //     $matrix = [];
        //     $start_total = [];
        //     $end_total = [];
        //     $start_count = [];
        //     $matrix_count = [];
        //     $is_default = [];

        //     foreach ($endStages as $endStage) {
        //         $is_default[$endStage->category_name] = $endStage->default_flag;
        //     }

        //     // Fetch start and end tables and columns
        //     $start_table = $profileDefinition->start_table;
        //     $end_table = $profileDefinition->end_table;
        //     $start_grading_col = $profileDefinition->start_grading_col;
        //     $end_grading_col = $profileDefinition->end_grading_col;
        //     $start_client_id_col = $profileDefinition->start_client_id_col;
        //     $end_client_id_col = $profileDefinition->end_client_id_col;
        //     $aggregation_criteria = $profileDefinition->aggregation_criteria;

        //     // Define balance column (adjust as needed)
        //     $balance_column = 'principal_balance'; // Replace with actual balance column

        //     // Build the matrix SQL query
        //     $matrix_sql = "
        //         SELECT 
        //             start_tbl.{$start_client_id_col} AS client_id,
        //             start_tbl.{$balance_column} AS start_bal,
        //             start_tbl.{$start_grading_col} AS start_grade,
        //             COALESCE(end_tbl.{$end_grading_col}, 'Paid') AS end_grade 
        //         FROM {$start_table} AS start_tbl 
        //         LEFT JOIN {$end_table} AS end_tbl 
        //             ON start_tbl.{$start_client_id_col} = end_tbl.{$end_client_id_col} 
        //             AND end_tbl.reporting_period = ?
        //         WHERE start_tbl.reporting_period = ?
        //     ";

        //     $matrix_rows = DB::select($matrix_sql, [$request->end_reporting_period, $request->start_reporting_period]);

        //     foreach ($matrix_rows as $row) {
        //         $start_grade = $row->start_grade;
        //         $end_grade = $row->end_grade;
        //         $bal = (float) $row->start_bal;

        //         // Accumulate start totals
        //         $start_total[$start_grade] = ($start_total[$start_grade] ?? 0) + $bal;
        //         $start_count[$start_grade] = ($start_count[$start_grade] ?? 0) + 1;

        //         // Accumulate end totals
        //         $end_total[$end_grade] = ($end_total[$end_grade] ?? 0) + $bal;

        //         // Sum the transitions
        //         $matrix[$start_grade][$end_grade] = ($matrix[$start_grade][$end_grade] ?? 0) + $bal;
        //         $matrix_count[$start_grade][$end_grade] = ($matrix_count[$start_grade][$end_grade] ?? 0) + 1;
        //     }

        //     // Prepare data for insertion
        //     $dataToInsert = [];
        //     $records_count_transitioned = 0;
        //     $transition_balance = 0.00;

        //     foreach ($startStages as $startStage) {
        //         $start_grade = $startStage->category_name;
        //         $start_total_balance_month = $start_total[$start_grade] ?? 0.00001; // Avoid division by zero

        //         foreach ($endStages as $endStage) {
        //             $end_grade = $endStage->category_name;
        //             $transition_balance_month = $matrix[$start_grade][$end_grade] ?? 0;
        //             $default_flag = $is_default[$end_grade] ?? 0;

        //             $stage_transition = $start_grade . 'to' . $end_grade;

        //             $dataToInsert[] = [
        //                 'calculation_header_id'       => $transitionMatrix->id,
        //                 'portfolio_group'             => $request->portfolio_group_id,
        //                 'is_payments_included'        => 1, // Set as needed
        //                 'start_period'                => $request->start_reporting_period,
        //                 'start_year'                  => $start_year,
        //                 'start_month'                 => $start_month,
        //                 'start_stage'                 => $start_grade,
        //                 'end_period'                  => $request->end_reporting_period,
        //                 'end_year'                    => $end_year,
        //                 'end_month'                   => $end_month,
        //                 'end_stage'                   => $end_grade,
        //                 'stage_transition'            => $stage_transition,
        //                 'transition_years'            => $transition_years,
        //                 'transition_balance_month'    => $transition_balance_month,
        //                 'start_total_balance_month'   => $start_total_balance_month,
        //                 'default_flag'                => $default_flag,
        //                 'created_at'                  => now(),
        //                 'updated_at'                  => now(),
        //             ];

        //             $records_count_transitioned += $matrix_count[$start_grade][$end_grade] ?? 0;
        //             $transition_balance += $transition_balance_month;
        //         }
        //     }

        //     // Insert data into transition_matrices_data
        //     TransitionMatrixData::insert($dataToInsert);

        //     // Update TransitionMatrix record
        //     $transitionMatrix->update([
        //         'records_count_transitioned' => $records_count_transitioned,
        //         'transition_balance'         => $transition_balance,
        //     ]);

        //     DB::commit();

        //     return Inertia::location(route('transition-matrices.index'));
        // } catch (\Exception $e) {
        //     DB::rollBack();
        //     // Handle exception as needed
        //     return back()->withErrors(['error' => $e->getMessage()]);
        // }

        try {
            TransitionMatrixService::processTransitionMatrixData($transitionMatrix);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        // $matrix = TransitionMatrix::create($validated);
        // $matrix->load('transitionProfile');

        //return Inertia::location(route('transition-matrices.show', $matrix->id));
        return Inertia::location(route('transition-matrices.index'));
        //return Inertia::render('TransitionProfiles/Index', ['success' => 'Profile updated successfully.']);
    }

    // public function store(Request $request)
    // {
    //     $validated = $request->validate([
    //         'transition_profile_id' => 'required|exists:loan_products,id',
    //         'start_reporting_period' => 'required|date',
    //         'end_reporting_period' => 'required|date|after:start_reporting_period',
    //         'description' => 'nullable|string',
    //         'external_file_path' => 'nullable|file'
    //     ]);
    //     // ... (rest of the old commented-out code removed to avoid syntax errors)
    // }

    // public function store(Request $request)
    // {
    //     $validated = $request->validate([
    //         'transition_profile_id' => 'required|exists:loan_products,id',
    //         'start_reporting_period' => 'required|date',
    //         'end_reporting_period' => 'required|date|after:start_reporting_period',
    //         'description' => 'nullable|string',
    //         'external_file_path' => 'nullable|file'
    //     ]);

    //     if ($request->hasFile('external_file_path')) {
    //         $validated['external_file_path'] = $request->file('external_file_path')
    //             ->store('transition-matrices', 'public');
    //     }
    //     if ($request->hasFile('external_file_path')) {
    //         $validated['external_file_path'] = $request->file('external_file_path')
    //             ->store('transition-matrices', 'public');
    //     }

    //     $validated['status'] = 'draft'; // Set default status
    //     $validated['status'] = 'draft'; // Set default status

    //     $matrix = TransitionMatrix::create($validated);
    //     $matrix->load('transitionProfile');
    //     $matrix = TransitionMatrix::create($validated);
    //     $matrix->load('transitionProfile');

    //     return Inertia::location(route('transition-matrices.show', $matrix->id));
    // }
    //     return Inertia::location(route('transition-matrices.show', $matrix->id));
    // }

    public function show(TransitionMatrix $matrix)
    {
        // Load the profile (which is actually a loan product) with its scoring attributes
        $loanProduct = $matrix->transitionProfile()->with('scoringAttributes')->first();
        
        // Get states from loan product's configured category names
        $states = $loanProduct->scoringAttributes
            ->map(function ($attribute) {
                return [
                    'id' => $attribute->id,
                    // 'name' => $attribute->weight, // Using weight as category name
                    'name' => $attribute->name
                ];
            })
            ->unique('name')
            ->values()
            ->all();
            // dd($states);

        // Get all portfolio groups from ScoringAttributeGroup
        $portfolioGroups = \App\Models\ScoringAttributeGroup::select('id', 'name', 'system_name', 'name', 'description')
            ->orderBy('name')
            ->get();

        return Inertia::render('TransitionMatrix/Show', [
            'matrix' => $matrix->load('transitionProfile', 'entries'),
            'portfolioGroups' => $portfolioGroups,
            'states' => $states
        ]);
    }

    public function download(TransitionMatrix $matrix)
    {
        if (!$matrix->external_file_path || !Storage::disk('public')->exists($matrix->external_file_path)) {
            abort(404, 'File not found');
        }

        return Storage::disk('public')->download($matrix->external_file_path);
    }

    public function entries(TransitionMatrix $matrix)
    {
        return response()->json([
            'entries' => $matrix->entries()->ordered()->get(),
            'portfolioGroups' => $this->portfolioGroups,
            'states' => $this->states
        ]);
    }

    public function updateEntries(Request $request, TransitionMatrix $matrix)
    {
        $validated = $request->validate([
            'entries' => 'required|array',
            'entries.*.portfolio_group' => 'required|string|in:' . implode(',', $this->portfolioGroups),
            'entries.*.start_state' => 'required|string|in:' . implode(',', array_keys($this->states)),
            'entries.*.end_state' => 'required|string|in:' . implode(',', array_keys($this->states)),
            'entries.*.start_balance' => 'required|numeric|min:0',
            'entries.*.start_count' => 'required|integer|min:0',
            'entries.*.end_balance' => 'required|numeric|min:0',
            'entries.*.end_count' => 'required|integer|min:0'
        ]);

        // Get entries array from validated data
        $entries = $validated['entries'];

        // Process each entry and calculate probabilities
        $processedEntries = array_map(function ($entry) use ($matrix) {
            $entry['is_default'] = in_array($entry['end_state'], ['Stage 3', 'Write-off']);
            
            // Calculate probability based on aggregation criteria
            if ($matrix->transitionProfile->aggregation_criteria === 'balance') {
                $entry['transitional_probability'] = $entry['start_balance'] > 0 
                    ? ($entry['end_balance'] / $entry['start_balance']) * 100 
                    : 0;
            } else {
                $entry['transitional_probability'] = $entry['start_count'] > 0 
                    ? ($entry['end_count'] / $entry['start_count']) * 100 
                    : 0;
            }

            return $entry;
        }, $entries);

        // Delete existing entries and create new ones
        $matrix->entries()->delete();
        $matrix->entries()->createMany($processedEntries);

        return back()->with('success', 'Matrix entries updated successfully');
    }

    // public function updateLoanBook(Request $request, TransitionMatrix $matrix)
    // {
    //     $validated = $request->validate([
    //         'reporting_period' => 'required|date'
    //     ]);

    //     // TODO: Implement loan book update logic

    //     return back()->with('success', 'Loan book updated successfully');
    // }

        public function updateLoanBook(Request $request, TransitionMatrix $matrix)
        {
            ini_set('max_execution_time', 300);

            $validated = $request->validate([
                'reporting_period' => 'required|date', 
            ]);

            DB::beginTransaction();

            try {

                $pds = TransitionMatrixData::where('calculation_header_id', $matrix->id)
                    ->where('end_stage', 3)
                    ->whereNotNull('transition_probability_month')
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
                    $pdDecimal = $pds[$stage]->transition_probability_month / 100;
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


    public function view(TransitionMatrix $matrix)
    {
        return Inertia::render('TransitionMatrices/View', [
            'matrix' => $matrix->load('portfolio'),
        ]);
    }

    public function edit(TransitionMatrix $matrix)
    {
        return Inertia::render('TransitionMatrices/Edit', [
            'matrix' => $matrix->load('portfolio'),
        ]);
    }

    // public function getData(TransitionMatrix $matrix)
    // {
    //     $matrixData = TransitionMatrixData::where('calculation_header_id', $matrix->calculation_header_id)
    //         ->get()
    //         ->groupBy('start_stage')
    //         ->map(fn($g) => $g->keyBy('end_stage'));

    //     $startStages = TransitionProfileOption::where('profile_id', $matrix->transition_profile_id)
    //         ->where('is_start_or_end', 'Start')
    //         ->orderBy('ordering_index')->get();

    //     $endStages = TransitionProfileOption::where('profile_id', $matrix->transition_profile_id)
    //         ->where('is_start_or_end', 'End')
    //         ->orderBy('ordering_index')->get();

    //     return response()->json([
    //         'matrix' => $matrixData,
    //         'startStages' => $startStages,
    //         'endStages' => $endStages,
    //     ]);
    // }
    public function getData(TransitionMatrix $matrix)
    {
        $rawData = TransitionMatrixData::where('calculation_header_id', $matrix->id)
            ->get();

        $groupedMatrix = [];
        $startTotals = [];
        $defaultSums = [];
        $pdPercentages = [];
        $endStageTotals = [];
        $grandTotal = 0;

        foreach ($rawData as $data) {
            $start = $data->start_stage;
            $end = $data->end_stage;
            $amount = $data->transition_balance_month;

            $groupedMatrix[$start][$end] = [
                'transition_balance_month' => $amount,
                'default_flag' => $data->default_flag,
            ];

            $startTotals[$start] = ($startTotals[$start] ?? 0) + $amount;

            $endStageTotals[$end] = ($endStageTotals[$end] ?? 0) + $amount;

            if ($data->default_flag) {
                $defaultSums[$start] = ($defaultSums[$start] ?? 0) + $data->transition_balance_month;
            }
            $grandTotal += $amount;
        }

        foreach ($startTotals as $start => $total) {
            $default = $defaultSums[$start] ?? 0;
            $pdPercentages[$start] = $total == 0 ? 0 : round(($default / $total) * 100, 2);
        }

        foreach ($rawData as $data) {
            $start = $data->start_stage;
            $prob = $pdPercentages[$start] ?? 0;

            $data->transition_probability_month = $prob;
            $data->save();
        }

        $startStages = TransitionProfileOption::where('profile_id', $matrix->transition_profile_id)
            ->where('is_start_or_end', 'Start')
            ->orderBy('ordering_index')->get();

        $endStages = TransitionProfileOption::where('profile_id', $matrix->transition_profile_id)
            ->where('is_start_or_end', 'End')
            ->orderBy('ordering_index')->get();
        

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

    public function updateData(Request $request, TransitionMatrix $matrix)
    {
        $data = $request->input('matrix', []);
        $total_balance  = 0;
        
        foreach ($data as $item) {
            TransitionMatrixData::updateOrCreate(
                [
                    'calculation_header_id' => $matrix->id,
                    'start_stage' => $item['start_stage'],
                    'end_stage' => $item['end_stage'],
                ],
                [
                    'transition_balance_month' => $item['transition_balance_month'],
                ]
            );
            $total_balance = $total_balance + $item['transition_balance_month'];
        }

        $matrix->update([
            'transition_balance' => $total_balance,
        ]);


        return back()->with('success', 'Matrix updated successfully.');
    }

    // public function rerun(TransitionMatrix $matrix)
    // {
    //     try {
    //         TransitionMatrixService::processTransitionMatrixData($matrix);
    //         return response()->json(['message' => 'Re-run completed successfully.']);
    //     } catch (\Exception $e) {
    //         return response()->json(['message' => 'Error during re-run: ' . $e->getMessage()], 500);
    //     }
    // }

    public function rerun(TransitionMatrix $matrix)
        {
            DB::beginTransaction();
            try {
                // Delete old data for this matrix
                TransitionMatrixData::where('calculation_header_id', $matrix->id)->delete();

                // Reprocess and insert new data
                TransitionMatrixService::processTransitionMatrixData($matrix);

                DB::commit();
                return response()->json(['message' => 'Re-run completed successfully.']);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['message' => 'Error during re-run: ' . $e->getMessage()], 500);
            }
        }


        public function keyLock(Request $request, $id)
        {
            $request->validate([
                'status' => 'nullable|in:draft,closed',
            ]);

            $matrix = TransitionMatrix::findOrFail($id);

            // Check if another CLOSED record exists with the same start & end period
            $existing = TransitionMatrix::where('id', '!=', $matrix->id)
                ->where('start_reporting_period', $matrix->start_reporting_period)
                ->where('end_reporting_period', $matrix->end_reporting_period)
                ->where('status', 'closed')
                ->exists();

            logger()->info('Auth check', [
                'user_id' => auth()->user()?->id,
                'roles' => auth()->user()?->getRoleNames(),
            ]);

            if ($existing) {
                return back()->with('error', 'A closed record already exists for the same reporting period.');
            }

            // Prevent non-admins from unlocking
            if (
                $matrix->status == 'closed'
                && !auth()->user()?->hasRole('admin')
            ) {
                return back()->with('error', 'Only an Administrator can unlock a closed LGD record');
            }

            // Toggle status
            $matrix->status = $matrix->status === 'closed' ? 'draft' : 'closed';
            $matrix->save();

            return back()->with('success', 'Probability Of Default (PD) record ' . ($matrix->status === 'closed' ? 'locked' : 'unlocked') . '.');
        }

}
