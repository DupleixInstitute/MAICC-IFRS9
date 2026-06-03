<?php

namespace App\Http\Controllers;

use App\Models\LoanProduct;
use App\Models\IndustryType;
use App\Models\ProductGroup;
use App\Models\TransitionMatrix;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use App\Models\TransitionProfileDefinition;
use App\Services\AuditLoggerService;
use App\Models\LoanPortfolio;
use App\Models\LoanBook;
use App\Models\ReportingPeriods;
use Illuminate\Support\Facades\DB;
use App\Models\TransitionMatrixData;
use App\Models\TransitionProfileOption;
use App\Services\TransitionMatrixService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use App\Models\TransitionMatrixEntry;
use Carbon\Carbon;
use ZipArchive;

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

        $query = TransitionMatrix::with(
            'transitionProfile', 
            'portfolio',
            'sector'
            )
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
            'sectors' => IndustryType::select('id', 'code','name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        \Log::info('Form data received:', $request->all());

       set_time_limit(300);

            $validated = $request->validate([
                'transition_profile_id' => 'required|exists:transition_profile_definitions,id',
                'pd_calculation_level' => 'required|in:portfolio,sector',
                'pd_calculation_id' => [
                    'nullable',
                    'required_if:pd_calculation_level,portfolio',
                    'integer',
                    Rule::exists('loan_portfolios', 'id')->when($request->pd_calculation_level === 'portfolio', function($rule) {
                        return $rule;
                    })
                ],
                'pd_calculation_code' => [
                    'nullable', 
                    'required_if:pd_calculation_level,sector',
                    'string',
                    Rule::exists('industry_types', 'code')->when($request->pd_calculation_level === 'sector', function($rule) {
                        return $rule;
                    })
                ],
                'start_reporting_period' => 'required',
                'end_reporting_period' => 'required',
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
            'pd_calculation_level'      => $request->pd_calculation_level,
            'pd_calculation_id'         => $request->pd_calculation_id,
            'pd_calculation_code'       => $request->pd_calculation_code,
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

                $scope = $matrix->pd_calculation_level;

                if ($scope === 'sector') {
                    $sector = $matrix->pd_calculation_code;
                } elseif ($scope === 'portfolio') {
                    $portfolio = $matrix->pd_calculation_id;
                } else {
                    throw new \Exception("Invalid PD calculation scope");
                }

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

                    $pdDecimal = ($stage === 3)
                        ? 1.0
                        : $pds[$stage]->transition_probability_month / 100;

                    if ($scope === 'sector') {

                        $affected = DB::update("
                            UPDATE loan_books
                            SET pd_prefli = ?
                            WHERE reporting_period = ?
                            AND ifrs9stage_pre_qualitative = ?
                            AND industry_code = ?
                        ", [
                            $pdDecimal, $period, $stage, $sector
                        ]);

                        DB::statement("
                            UPDATE loan_books
                            SET lifetime_pd = 1 - POWER((1 - ?), remaining_tenor)
                            WHERE reporting_period = ?
                            AND ifrs9stage_pre_qualitative = ?
                            AND remaining_tenor IS NOT NULL
                            AND industry_code = ?
                        ", [
                            $pdDecimal, $period, $stage, $sector
                        ]);

                    } elseif ($scope === 'portfolio') {

                        $affected = DB::update("
                            UPDATE loan_books
                            SET pd_prefli = ?
                            WHERE reporting_period = ?
                            AND ifrs9stage_pre_qualitative = ?
                            AND loan_portfolio_id  = ?
                        ", [
                            $pdDecimal, $period, $stage, $portfolio
                        ]);

                        DB::statement("
                            UPDATE loan_books
                            SET lifetime_pd = 1 - POWER((1 - ?), remaining_tenor)
                            WHERE reporting_period = ?
                            AND ifrs9stage_pre_qualitative = ?
                            AND remaining_tenor IS NOT NULL
                            AND loan_portfolio_id  = ?
                        ", [
                            $pdDecimal, $period, $stage, $portfolio
                        ]);
                    }

                    $totalUpdated += $affected ?? 0;
                }

                DB::commit();

                AuditLoggerService::log(
                    action: 'PD Monthly  Loan Book Update',
                    entityType: 'LoanBook',
                    entityId: $matrix->id,
                    data: [
                        'scope' => $scope,
                        'reporting_period' => $period,
                        'meta' => ['rows_affected' => $totalUpdated, 'profile_id' => $matrix->id]
                    ]
                );

                // ✅ Save reporting period
                $periodParts = explode('-', $validated['reporting_period']);
                $year  = (int)$periodParts[0];
                $month = (int)$periodParts[1];
                $periodDate = $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-01';

                ReportingPeriods::updateOrCreate(
                    ['period' => $periodDate],
                    [
                        'reporting_year' => $year,
                        'reporting_month' => $month,
                        'pd_id' => $matrix->id,
                        'pd_calculation_source' => $matrix->calculation_source,
                    ]
                );

                return back()->with([
                    'success' => 'Loan book PD updated successfully using backend scope logic',
                    'updated_count' => $totalUpdated,
                ]);

            } catch (\Exception $e) {
                DB::rollBack();

                return back()->withErrors([
                    'error' => 'Update failed: ' . $e->getMessage()
                ]);
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
                            ->where('pd_calculation_level', $matrix->pd_calculation_level)
                            ->where('pd_calculation_id', $matrix->pd_calculation_id)
                            ->where('pd_calculation_code', $matrix->pd_calculation_code)
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
                return back()->with('error', 'Only an Administrator can unlock a closed PD record');
            }

            // Toggle status
            $matrix->status = $matrix->status === 'closed' ? 'draft' : 'closed';
            $matrix->save();

            return back()->with('success', 'Probability Of Default (PD) record ' . ($matrix->status === 'closed' ? 'locked' : 'unlocked') . '.');
        }

        
          public function attachFile(TransitionMatrix $matrix, Request $request)
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
        $lgd = TransitionMatrix::findOrFail($id);

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
            $matrices = TransitionMatrix::select([
                    'id',
                    'transition_profile_id',
                    'portfolio_group_id',
                    'start_reporting_period',
                    'end_reporting_period',
                    'transition_years',
                    'records_count_transitioned',
                    'records_count_updated',
                    'reporting_periods_count',
                    'run_no',
                    'transition_balance',
                    'updated_balance',
                    'status',
                    'last_calculation_date',
                    'calculation_source',
                    'comments'
                ])
                ->with(['portfolio:id,name', 'transitionProfile:id,name'])
                ->where('start_reporting_period', '<=', $end)
                ->where('end_reporting_period', '>=', $start)
                ->where('status', 'closed')
                ->orderBy('portfolio_group_id')
                ->orderBy('start_reporting_period')
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
                'Transition Years',
                'Records Transitioned',
                'Records Updated',
                'Reporting Periods',
                'Calculation Runs',
                'Transition Balance',
                'Updated Balance',
                'Status',
                'Last Calculation Date',
                'Calculation Source',
                'Comments'
            ];

            $csvRows = [];

            // Add report range as first row
            $csvRows[] = ["Closed Transition Matrix Report: From {$request->start_period} To {$request->end_period}"];
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
                    $matrix->start_reporting_period,
                    $matrix->end_reporting_period,
                    $matrix->transition_years,
                    $matrix->records_count_transitioned,
                    $matrix->records_count_updated,
                    $matrix->reporting_periods_count,
                    $matrix->run_no,
                    $matrix->transition_balance,
                    $matrix->updated_balance,
                    $matrix->status === 'closed' ? 'Closed' : 'Draft',
                    $matrix->last_calculation_date,
                    ucfirst($matrix->calculation_source),
                    $matrix->comments ?? ''
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

            $filename = "Transition_Matrix_Report_{$request->start_period}_to_{$request->end_period}";
            $csvPath = storage_path("app/{$filename}.csv");
            file_put_contents($csvPath, $csvContent);

            Log::info("CSV file created: {$csvPath}, size: " . filesize($csvPath) . " bytes");

            if ($compressFile) {
                // Create ZIP file
                $zipPath = storage_path("app/{$filename}.zip");
                $zip = new ZipArchive();
                $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
                $zip->addFile($csvPath, "{$filename}.csv");
                $zip->close();

                Log::info("ZIP file created: {$zipPath}, size: " . filesize($zipPath) . " bytes");
                Log::info("Returning ZIP download response");
                return response()->download($zipPath, "{$filename}.zip", [
                    'Content-Type' => 'application/zip',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '.zip"'
                ])->deleteFileAfterSend(true);
            } else {
                // Return CSV directly
                if ($format === 'excel') {
                    // For Excel format, we'd need to use a library like Laravel Excel
                    // For now, return CSV with Excel MIME type
                    Log::info("Returning CSV download as Excel format");
                    return response()->download($csvPath, "{$filename}.csv", [
                        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                    ])->deleteFileAfterSend(true);
                } else {
                    Log::info("Returning CSV download response");
                    return response()->download($csvPath, "{$filename}.csv", [
                        'Content-Type' => 'text/csv',
                        'Content-Disposition' => 'attachment; filename="' . $filename . '.csv"'
                    ])->deleteFileAfterSend(true);
                }
            }
        }

        private function exportMatrixFormat($request, $start, $end, $format, $includeHeaders, $compressFile)
        {
            // Get matrices for the period
            $matrices = TransitionMatrix::with(['portfolio', 'transitionProfile'])
                ->where('start_reporting_period', '<=', $end)
                ->where('end_reporting_period', '>=', $start)
                ->where('status', 'closed')
                ->orderBy('portfolio_group_id')
                ->orderBy('start_reporting_period')
                ->get();

            if ($matrices->isEmpty()) {
                return back()->with('error', 'No locked periods found for the selected date range');
            }

            Log::info("Found " . $matrices->count() . " matrices for export");

            $csvRows = [];

            foreach ($matrices as $matrix) {
                // Get matrix data for this specific matrix
                $matrixData = TransitionMatrixData::where('calculation_header_id', $matrix->id)->get();

                // Matrix Header
                $csvRows[] = ["Closed Transition Matrix Report"];
                $csvRows[] = ["Profile: " . ($matrix->transitionProfile ? $matrix->transitionProfile->name : 'N/A')];
                $csvRows[] = ["Period: " . $matrix->start_reporting_period . " to " . $matrix->end_reporting_period];
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
                        // Use transition_balance_month as the balance
                        $balance = $entry->transition_balance_month ?? 0;
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
                    $startBalance = $entry->start_total_balance_month ?? 0;
                    $transitionBalance = $entry->transition_balance_month ?? 0;
                    $csvRows[] = [
                        $entry->portfolio_group ?? 'N/A',
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

            $filename = "Transition_Matrix_Report_{$request->start_period}_to_{$request->end_period}";
            $csvPath = storage_path("app/{$filename}.csv");
            file_put_contents($csvPath, $csvContent);

            Log::info("Matrix CSV file created: {$csvPath}, size: " . filesize($csvPath) . " bytes");

            if ($compressFile) {
                $zipPath = storage_path("app/{$filename}.zip");
                $zip = new ZipArchive();
                $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
                $zip->addFile($csvPath, "{$filename}.csv");
                $zip->close();

                Log::info("Matrix ZIP file created: {$zipPath}, size: " . filesize($zipPath) . " bytes");
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

    public function delete(TransitionMatrix $matrix)
    {
        // Simple file logging to verify method is called
        file_put_contents(storage_path('logs/delete_debug.log'),
            date('Y-m-d H:i:s') . " - Delete method called for matrix ID: " . $matrix->id . "\n",
            FILE_APPEND
        );

        Log::info('Delete method called for matrix ID: ' . $matrix->id);

        DB::beginTransaction();

        try {
            Log::info('Starting deletion process for matrix ID: ' . $matrix->id);

            // Delete related transition matrix data
            $deletedData = TransitionMatrixData::where('calculation_header_id', $matrix->id)->delete();
            Log::info('Deleted ' . $deletedData . ' transition matrix data records');

            // Delete related transition matrix entries
            $deletedEntries = TransitionMatrixEntry::where('transition_matrix_id', $matrix->id)->delete();
            Log::info('Deleted ' . $deletedEntries . ' transition matrix entries');

            // Delete the transition matrix itself (hard delete to avoid unique constraint conflicts)
            $matrix->forceDelete();
            Log::info('Force deleted matrix ID: ' . $matrix->id);

            DB::commit();

            Log::info('Delete transaction committed successfully');

            file_put_contents(storage_path('logs/delete_debug.log'),
                date('Y-m-d H:i:s') . " - Delete completed successfully for matrix ID: " . $matrix->id . "\n",
                FILE_APPEND
            );

            return redirect()->route('transition-matrices.index')
                ->with('success', 'Transition matrix and all related data deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete failed for matrix ID ' . $matrix->id . ': ' . $e->getMessage());

            file_put_contents(storage_path('logs/delete_debug.log'),
                date('Y-m-d H:i:s') . " - Delete failed for matrix ID: " . $matrix->id . " - Error: " . $e->getMessage() . "\n",
                FILE_APPEND
            );

            return back()->withErrors(['error' => 'Failed to delete transition matrix: ' . $e->getMessage()]);
        }
    }

}
