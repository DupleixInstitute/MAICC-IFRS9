<?php

namespace App\Http\Controllers;

use App\Models\LoanPortfolio;
use App\Models\IndustryType;
use App\Models\SupportingDocument;
use App\Helpers\DocumentHelper;
use App\Models\LossGivenDefault;
use App\Models\LoanBook;
use App\Models\ReportingPeriods;
use App\Jobs\CalculateLGDJob;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Console\Input\Input;
use Illuminate\Support\Facades\Storage;

class LossGiveDefaultController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * 
     */

     // This function returns an Inertia response to render the index view
     // It retrieves all Loss Given Default records along with their associated portfolio groups
       public function index(Request $request)
            {
                $calculationLevel = $request->input('lgd_calculation_level');
                $startDate = $request->input('start_date');
                $endDate = $request->input('end_date');

                $query = LossGivenDefault::with('portfolioGroup', 'sector')
                    ->when($calculationLevel, function ($query, $calculationLevel) {
                        $query->where('lgd_calculation_level', $calculationLevel);
                    })
                    
                    ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('start_period', [$startDate, $endDate])
                            ->orWhereBetween('reporting_period', [$startDate, $endDate]);
                    });

                return inertia('LossGivenDefault/Index', [
                    'lossGivenDefaults' => $query->latest()->paginate(10),
                    'filters' => $request->only(['calculation_level', 'start_date', 'end_date']),
                    'flash' => [
                                'success' => session('success'),
                                'error' => session('error'),
                            ],
            ]);
            }


    // Function to create a new Loss Given Default record
    // This function returns an Inertia response to render the create view
    public function create()
    {
        
        return inertia('LossGivenDefault/Create',[
            'portfolio_group' => LoanPortfolio::select('id', 'name')->get(),
            'sectors' => IndustryType::select('code', 'name')->get(),
        ]);
     }

// public function calculateLGD()
// {
//     $startTime = microtime(true);

//     request()->validate([
//         'portfolio_group' => 'required|exists:loan_portfolios,id',
//         'reporting_period' => 'required|date',
//         'start_period' => 'required|date|before_or_equal:reporting_period',
//     ]);

//     $startPeriod = request()->input('start_period');
//     $reportingPeriod = request()->input('reporting_period');
//     $portfolioGroupId = request()->input('portfolio_group');
//     $calculationSource = 'system';

//     CalculateLGDJob::dispatch($startPeriod, $reportingPeriod, $portfolioGroupId);

//     $endTime = microtime(true);
//     $timeTaken = round($endTime - $startTime, 3);

//     return redirect()->back()->with('success', "LGD calculated successfully in {$timeTaken} seconds.");
// }


        // public function calculateLGD()
        //         {

        //             $startTime = microtime(true);

        //             ini_set('max_execution_time', 300);

        //             request()->validate([
        //                 'portfolio_group' => 'required|exists:loan_portfolios,id',   
        //                 'reporting_period' => 'required|date',
        //                 'start_period' => 'required|date|before_or_equal:reporting_period',
        //             ]);

        //             $startPeriod = Carbon::parse(request()->input('start_period'))->format('Y-m');
        //             $reportingPeriod = Carbon::parse(request()->input('reporting_period'))->format('Y-m');

        //             if ($startPeriod > $reportingPeriod) {
        //                 return redirect()->back()->withErrors(['start_period' => 'Start period should not be later than reporting period.']);
        //             }

        //             $portfolioGroup = LoanPortfolio::select('id', 'name')
        //                 ->where('id', request()->input('portfolio_group'))
        //                 ->first();

              
        // $data = DB::select("
        //     SELECT 
        //         lb_start.contract_id,
        //         lb_start.principal_balance AS start_balance,
        //         COALESCE(lb_end.principal_balance, 0) AS end_balance,
        //         CASE 
        //             WHEN lb_end.contract_id IS NULL 
        //             THEN lb_start.principal_balance 
        //             WHEN lb_end.principal_balance > lb_start.principal_balance 
        //             THEN lb_end.principal_balance - lb_start.principal_balance 
        //             ELSE 0 
        //         END AS disbursment_amount,
        //         CASE 
        //             WHEN lb_end.contract_id IS NULL 
        //             THEN lb_start.principal_balance 
        //             WHEN lb_end.principal_balance < lb_start.principal_balance 
        //             THEN lb_start.principal_balance - lb_end.principal_balance 
        //             ELSE 0 
        //         END AS recovered_amount,
        //         CASE 
        //             WHEN lb_end.calculated_ifrs9_stage IN ('1', '2') 
        //             THEN lb_start.contract_id 
        //             ELSE NULL 
        //         END AS cured_contract_id
        //     FROM loan_books lb_start
        //     LEFT JOIN loan_books lb_end 
        //         ON lb_start.contract_id = lb_end.contract_id 
        //         AND lb_end.loan_portfolio_id = lb_start.loan_portfolio_id
        //         AND LEFT(lb_end.reporting_period, 7) = ?
        //     WHERE LEFT(lb_start.reporting_period, 7) = ?
        //     AND lb_start.calculated_ifrs9_stage = '3'
        //     AND lb_start.loan_portfolio_id = ?
        // ", [
        //     $startPeriod,
        //     $reportingPeriod,
        //     $portfolioGroup->id,
        // ]);

        //     $startBalance = collect($data)->sum('start_balance');

   
        //     if ($startBalance == 0) {
        //         return redirect()->back()->withErrors([
        //             'start_total_stage3' => 'Start balance is zero — check if data matches your filters (reporting period, stage, portfolio).'
        //         ]);
        //     }

        //     $endBalance = collect($data)->sum('end_balance');
        //     $totalDisbursments = array_sum(array_column($data, 'disbursment_amount'));
        //     $totalRecoveredAmount = array_sum(array_column($data, 'recovered_amount'));
        //     $curedLoanIds = array_filter(array_column($data, 'cured_contract_id'));

        //     $partlyRecoveredAmount = array_sum(array_filter(array_column($data, 'recovered_amount'), fn($value) => $value > 0 && $value != max(array_column($data, 'recovered_amount'))));
        //     $fullyRecoveredAmount = $totalRecoveredAmount - $partlyRecoveredAmount;

        //     $recoveryRate = $startBalance > 0 ? ($totalRecoveredAmount / $startBalance) : 0;

        //     $cureAmounts = LoanBook::whereIn('contract_id', $curedLoanIds)
        //         ->whereRaw('LEFT(reporting_period,7) = ?', [$reportingPeriod])
        //         ->whereIn('calculated_ifrs9_stage', [1, 2])
        //         ->groupBy('calculated_ifrs9_stage')
        //         ->selectRaw('calculated_ifrs9_stage, SUM(principal_balance) as total_cure_amount')
        //         ->pluck('total_cure_amount', 'calculated_ifrs9_stage');

        //     $cureAmountStage1 = $cureAmounts[1] ?? 0;
        //     $cureAmountStage2 = $cureAmounts[2] ?? 0;
        //     $curedAmount = $cureAmountStage1 + $cureAmountStage2;
        //     $cureRate = $startBalance > 0 ? ($curedAmount / $startBalance) : 0;

        //     $lgd = (1 - $cureRate) * (1 - $recoveryRate);

        //     LossGivenDefault::create([
        //         'reporting_period' => $reportingPeriod,
        //         'start_period' => $startPeriod,
        //         'portfolio_group' => $portfolioGroup->id,
        //         'start_total_stage3' => $startBalance,
        //         'end_total_stage3' => $endBalance,
        //         'loss_given_default_percentage' => $lgd,
        //         'cured_amount' => $curedAmount,
        //         'cure_rate' => $cureRate,
        //         'cure_amount_stage1' => $cureAmountStage1,
        //         'cure_amount_stage2' => $cureAmountStage2,
        //         'recovered_amount' => $totalRecoveredAmount,
        //         'recovery_rate' => $recoveryRate,
        //         'partly_recovered_amount' => $partlyRecoveredAmount,
        //         'fully_recovered_amount' => $fullyRecoveredAmount,
        //         'total_disbursments' => $totalDisbursments,
        //         'created_by' => auth()->user()->id ?? null,
        //         'updated_by' => auth()->user()->id ?? null,
        //         'calculation_source' => 'system',
        //         'is_active_or_closed' => 1,
        //     ]);

        //     $endTime = microtime(true);
        //     $timeTaken = round($endTime - $startTime, 3);

        //     return redirect()->back()->with('success', "Loss Given Default calculated successfully.In ,{$timeTaken}");
        // }


        // Function to calculate Loss Given Default (LGD)
        // This function processes the request, validates inputs, and performs the LGD calculation using database data
    public function calculateLGD(Request $request)
        {
            $startTime = microtime(true);
            ini_set('max_execution_time', 300);

            request()->validate([
                'lgd_calculation_level' => 'required|in:portfolio,sector',
                'lgd_calculation_id' => 'nullable|required_if:lgd_calculation_level,portfolio|exists:loan_portfolios,id',
                'lgd_calculation_code' => 'nullable|required_if:lgd_calculation_level,sector|exists:industry_types,code',
                'reporting_period' => 'required|date',
                'start_period' => 'required|date|before_or_equal:reporting_period',
            ]);

            $lgdCalculationLevel = $request->lgd_calculation_level;

            $portfolioGroup = null;
            $sector = null;


            $lgdCalculationLevel = $request->lgd_calculation_level;

            $portfolioGroup = null;
            $sector = null;

            if ($lgdCalculationLevel === 'portfolio') {
                $filterColumn = 'loan_portfolio_id';
                $filterValue = $request->input('lgd_calculation_id');  // Correct
                $portfolioGroup = LoanPortfolio::find($filterValue);
                if (!$portfolioGroup) {
                    return back()->with('error', 'Selected Portfolio Group does not exist.');
                }
            } elseif ($lgdCalculationLevel === 'sector') {
                $filterColumn = 'industry_code';
                $filterValue = $request->input('lgd_calculation_code');  // FIXED: Added the missing 'l'
                $sector = IndustryType::where('code', $filterValue)->first();
                if (!$sector) {
                    return back()->with('error', 'Selected Sector does not exist.');
                }
            }




            // Validate that start_period is not later than reporting_period
            // This ensures that the start period is not later than the reporting period
            //$portfolioGroup = LoanPortfolio::findOrFail(request()->input('portfolio_group'));

 

            $startPeriod = Carbon::parse(request()->input('start_period'))->format('Y-m');
            $reportingPeriod = Carbon::parse(request()->input('reporting_period'))->format('Y-m');


            // Query to fetch loan books data for the specified reporting period and portfolio group
            // This query retrieves the start and end balances, and the closing stage for each contract
            // DEBUG: Log the periods
                \Log::info('LGD Calculation Periods:', [
                    'start_period' => $startPeriod,
                    'reporting_period' => $reportingPeriod,
                    'filter_column' => $filterColumn,
                    'filter_value' => $filterValue
                ]);

            // Query to fetch loan books data
            // IMPORTANT: lb_start uses start_period (earlier), lb_end uses reporting_period (later)
            $data = DB::table('loan_books as lb_start')
                ->leftJoin('loan_books as lb_end', function($join) use ($reportingPeriod, $filterColumn) {
                    $join->on('lb_start.contract_id', '=', 'lb_end.contract_id')
                        ->on("lb_start.$filterColumn", '=', "lb_end.$filterColumn")
                        ->where('lb_end.reporting_period', '=', $reportingPeriod);  // Later period
                })
                ->where('lb_start.reporting_period', '=', $startPeriod)  // Earlier period
                ->where('lb_start.ifrs9stage_pre_qualitative', 3)
                ->where("lb_start.$filterColumn", $filterValue)
                ->select(
                    'lb_start.contract_id',
                    DB::raw('lb_start.carrying_amount as start_balance'),
                    DB::raw('COALESCE(lb_end.carrying_amount, 0) as end_balance'),
                    DB::raw('COALESCE(lb_end.ifrs9stage_pre_qualitative, 3) as closing_stage')
                )
                ->get();

            // DEBUG: Log the query results
            // \Log::info('LGD Query Results:', [
            //     'data_count' => $data->count(),
            //     'first_record' => $data->first(),
            //     'all_data' => $data->toArray()
            // ]);

            // Initialize variables to hold the calculated values
            // These variables will accumulate the totals for the LGD calculation
            $startBalance = 0;
            $endBalance = 0;
            $totalDisbursments = 0;
            $totalRecoveredAmount = 0;
            $cureAmountStage1 = 0;
            $cureAmountStage2 = 0;
            $partlyRecoveredAmount = 0;
            $fullyRecoveredAmount = 0;
            $writtenOffs = 0;

            // Iterate through the data to calculate the required values
            // This loop processes each row of data to compute the start and end balances, disbursements, recoveries, and cure amounts
            foreach ($data as $row) {
                $startBalance += $row->start_balance;
                $endBalance += $row->end_balance;
                $netMovement = $row->end_balance - $row->start_balance;
                $disbursement = $netMovement > 0 ? $netMovement : 0;

                $curedStage1 = $row->closing_stage == 1 ? $row->end_balance : 0;
                $curedStage2 = $row->closing_stage == 2 ? $row->end_balance : 0;

                if($row->closing_stage == 3){
                    if($row->end_balance == 0){
                        $paidInFull = $row->start_balance;
                        $paidPartly = 0;
                    }elseif($row->end_balance < $row->start_balance){
                        $paidPartly = $row->start_balance - $row->end_balance;
                        $paidInFull = 0;
                    }else{
                        $paidInFull = 0;
                        $paidPartly = 0;
                    }
                    }

                $totalDisbursments += $disbursement;
                $totalRecoveredAmount += (($paidInFull + $paidPartly) - $totalDisbursments);
                $cureAmountStage1 += $curedStage1;
                $cureAmountStage2 += $curedStage2;
                $partlyRecoveredAmount += $paidPartly;
                $fullyRecoveredAmount += $paidInFull;
            }

            // Check if start balance is zero
            // If it is, return an error message indicating that the start balance is zero
            if ($startBalance == 0) {
                return redirect()->back()->withErrors([
                    'start_total_stage3' => 'Start balance is zero — check if data matches your filters (reporting period, stage, portfolio).'
                ]);
            }

            // Calculate the cure rate and recovery rate
            // These rates are derived from the cured amounts and recovered amounts relative to the start balance
            $curedAmount = $cureAmountStage1 + $cureAmountStage2;
            $cureRate = $curedAmount / $startBalance;
            $recoveryRate = $totalRecoveredAmount / $startBalance;
            $lgd = (1 - $cureRate) * (1 - $recoveryRate);
            $lgd = max(0, min(1, $lgd));

            // Create a new LossGivenDefault record

            try{
            LossGivenDefault::updateOrCreate([ 
                'reporting_period' => $reportingPeriod,
                'lgd_calculation_level' => $lgdCalculationLevel,
                'lgd_calculation_id' => $lgdCalculationLevel === 'portfolio' ? $portfolioGroup->id : null,
                'lgd_calculation_code' => $lgdCalculationLevel === 'sector' ? $filterValue : null,
                'calculation_source' => 'system',
            ], [
                'start_period' => $startPeriod,
                //'portfolio_group' => $portfolioGroup->id,
                'start_total_stage3' => $startBalance,
                'end_total_stage3' => $endBalance,
                'loss_given_default_percentage' => $lgd,
                'cured_amount' => $curedAmount,
                'cure_rate' => $cureRate,
                'cure_amount_stage1' => $cureAmountStage1,
                'cure_amount_stage2' => $cureAmountStage2,
                'recovered_amount' => $totalRecoveredAmount,
                'recovery_rate' => $recoveryRate,
                'partly_recovered_amount' => $partlyRecoveredAmount,
                'fully_recovered_amount' => $fullyRecoveredAmount,
                'total_disbursments' => $totalDisbursments,
                'written_offs' => $writtenOffs,
                'created_by' => auth()->user()->id ?? null,
                'updated_by' => auth()->user()->id ?? null,
                'is_active_or_closed' => $request->input('is_active_or_closed', 'active'),
            ]);

            $timeTaken = round(microtime(true) - $startTime, 3);

            return redirect()->route('loss-given-default.index')->with('success', 'Loss Given Default calculated successfully in ' . $timeTaken . ' seconds.');
        }catch(\Exception $e){
            return back()->with('error', 'An error occurred while calculating LGD: ' . $e->getMessage());
        
        }
        }


    // Function to store manual LGD calculations
    // This function processes the request, validates inputs, and stores the manual LGD calculation in the database
        public function storeManualCalculation(Request $request)
        {
            $mode = $request->mode;

            $baseValidation = [
                'lgd_calculation_level' => 'required|in:portfolio,sector',
                'lgd_calculation_id' => 'nullable|required_if:lgd_calculation_level,portfolio|exists:loan_portfolios,id',
                'lgd_calculation_code' => 'nullable|required_if:lgd_calculation_level,sector|exists:industry_types,code',
                'reporting_period' => 'required|date',
                'start_period' => 'required|date|before_or_equal:reporting_period',
                'loss_given_default_percentage' => 'required|numeric|min:0|max:1',
                'mode' => 'required|in:amount,percentage',
            ];

            $amountFields = [
                'start_total_stage3' => 'required|numeric',
                'end_total_stage3' => 'required|numeric',
                'cure_amount_stage1' => 'required|numeric',
                'cure_amount_stage2' => 'required|numeric',
                'partially_recovered_amount' => 'required|numeric',
                'fully_recovered_amount' => 'required|numeric',
                'total_disbursments' => 'required|numeric',
            ];

            $percentageFields = [
                'cure_rate' => 'required|numeric|min:0|max:1',
                'recovery_rate' => 'required|numeric|min:0|max:1',
            ];

            $request->validate(array_merge(
                $baseValidation,
                $mode === 'amount' ? $amountFields : $percentageFields
            ));

            // ✅ Clamp LGD for absolute safety
            $lgd = max(0, min(1, $request->loss_given_default_percentage));

            $data = [
                'reporting_period' => $request->reporting_period,
                'start_period' => $request->start_period,
                'lgd_calculation_level' => $request->lgd_calculation_level,
                'lgd_calculation_id' => $request->lgd_calculation_id,
                'lgd_calculation_code' => $request->lgd_calculation_code,
                'loss_given_default_percentage' => $lgd,
                'cure_rate' => $request->cure_rate ?? 0,
                'cured_amount' => $request->cured_amount ?? 0,
                'recovery_rate' => $request->recovery_rate ?? 0,
                'recovered_amount' => $request->recovered_amount ?? 0,
                'total_disbursments' => $request->total_disbursments ?? 0,
                'partially_recovered_amount' => $request->partially_recovered_amount ?? 0,
                'fully_recovered_amount' => $request->fully_recovered_amount ?? 0,
                'cure_rate_average_monthly' => 0,
                'recovery_rate_average_monthly' => 0,
                'last_reporting_period' => null,
                'is_active_or_closed' => $request->is_active_or_closed ?? 'active',
                'calculation_source' => 'manual',
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ];

            if ($mode === 'amount') {
                $data['start_total_stage3'] = $request->start_total_stage3;
                $data['end_total_stage3'] = $request->end_total_stage3;
            } else {
                $data['start_total_stage3'] = 0;
                $data['end_total_stage3'] = 0;
                $data['cured_amount'] = 0;
                $data['recovered_amount'] = 0;
            }

            LossGivenDefault::create($data);

            return redirect('loss-given-default.index')->with('success', 'Loss Given Default record created successfully.');
        }

    
    public function editManual($id)
    {
        $lossGivenDefault = LossGivenDefault::find($id);
        if (!$lossGivenDefault) {
            return back()->with('error', 'Loss Given Default record not found');
        }

        return inertia('LossGivenDefault/Create', [
            'portfolio_group' => LoanPortfolio::select('id', 'name')->get(),
            'sectors' => IndustryType::select('code', 'name')->get(),
        ]);
    }

    // Function to toggle the lock status of a Loss Given Default record
    // This function checks the current status of the record and toggles it between 'active' and 'closed'
        public function keyLock($id, Request $request)
            {
                $request->validate([
                    'is_active_or_closed' => 'nullable|in:active,closed',
                ]);

                // Find the Loss Given Default record by ID
                $lgd = LossGivenDefault::findOrFail($id);

                logger()->info('Auth check', [
                    'user_id' => auth()->user()?->id,
                    'roles' => auth()->user()?->getRoleNames(),
                ]);

                // Check if the user is trying to unlock a closed LGD record
                // If the record is closed and the user is not an admin, return an error message
                if (
                    $lgd->is_active_or_closed === 'closed' &&
                    !auth()->user()?->hasRole('admin')
                ) {
                    return back()->with('error', 'Only an Administrator can unlock a closed LGD record');
                }

                // Toggle between 'active' and 'closed'
                $lgd->is_active_or_closed = $lgd->is_active_or_closed === 'closed' ? 'active' : 'closed';
                $lgd->save();

                return redirect()->back()->with('success', 'LGD record status updated.');
            }

    // Function to update loan books with the LGD value
    // This function updates the loan books for a specific reporting period with the LGD value from
        public function updateLoanBooks(Request $request)
        {
            ini_set('max_execution_time', 300);
            $startTime = microtime(true);

            // ✅ VALIDATION
            $validated = $request->validate([
                'reporting_period' => 'required|date_format:Y-m',
                'lgd_id' => 'required|exists:loss_given_default,id',
                'include_customer_lgd' => 'nullable|boolean',
            ]);

            $lgd = LossGivenDefault::findOrFail($validated['lgd_id']);

            // ✅ AUTO SCOPE (portfolio OR sector)
            $scope = $lgd->lgd_calculation_level; // 'portfolio' or 'sector'

            if (!in_array($scope, ['portfolio', 'sector'])) {
                return back()->with('error', 'Invalid LGD calculation level.');
            }

            if ($lgd->is_active_or_closed !== 'closed') {
                return back()->with('error', 'Cannot update loan books for an active LGD record.');
            }

            $collectionLgd = $lgd->loss_given_default_percentage;
            $period = Carbon::parse($validated['reporting_period'])->format('Y-m');

            $rowsUpdated = 0; // ✅ prevent undefined variable

            // ✅ CUSTOMER LGD ENABLED
            if ($request->boolean('include_customer_lgd')) {

                if ($scope === 'portfolio') {
                    $rowsUpdated = DB::update("
                        UPDATE loan_books
                        SET 
                            collection_lgd = ?,
                            lgd_value = COALESCE(customer_lgd, ?) * ?
                        WHERE LEFT(reporting_period, 7) = ?
                        AND portfolio_group = ?
                    ", [
                        $collectionLgd, 1, $collectionLgd,
                        $period, $lgd->lgd_calculation_id
                    ]);
                }

                if ($scope === 'sector') {
                    $rowsUpdated = DB::update("
                        UPDATE loan_books
                        SET 
                            collection_lgd = ?,
                            lgd_value = COALESCE(customer_lgd, ?) * ?
                        WHERE LEFT(reporting_period, 7) = ?
                        AND industry_code = ?
                    ", [
                        $collectionLgd, 1, $collectionLgd,
                        $period, $lgd->lgd_calculation_code
                    ]);
                }

            // ✅ CUSTOMER LGD DISABLED
            } else {

                if ($scope === 'portfolio') {
                    $rowsUpdated = DB::update("
                        UPDATE loan_books
                        SET 
                            collection_lgd = ?,
                            lgd_value = ?
                        WHERE LEFT(reporting_period, 7) = ?
                        AND portfolio_group = ?
                    ", [
                        $collectionLgd, $collectionLgd,
                        $period, $lgd->lgd_calculation_id
                    ]);
                }

                if ($scope === 'sector') {
                    $rowsUpdated = DB::update("
                        UPDATE loan_books
                        SET 
                            collection_lgd = ?,
                            lgd_value = ?
                        WHERE LEFT(reporting_period, 7) = ?
                        AND industry_code = ?
                    ", [
                        $collectionLgd, $collectionLgd,
                        $period, $lgd->lgd_calculation_code
                    ]);
                }
            }

            $timeTaken = round((microtime(true) - $startTime) / 60, 2);

            // ✅ REPORTING PERIOD SAVE
            ReportingPeriods::updateOrCreate(
                ['period' => $period . '-01'],
                [
                    'reporting_year' => (int)substr($period, 0, 4),
                    'reporting_month' => (int)substr($period, 5, 2),
                    'lgd_id' => $lgd->id,
                    'lgd_calculation_source' => $lgd->calculation_source,
                    'lgd_calculation_time' => $timeTaken,
                ]
            );

            return back()->with(
                'success',
                "Loan books updated successfully in {$timeTaken} minutes. Rows updated: {$rowsUpdated}"
            );
        }

        //Attach supporting File if it is Manual Calculation for supporting info of the calculation
        public function attachFile(LossGivenDefault $lgd, Request $request)
        {
            Log::info('Attach file request started', [
                'lgd_id' => $lgd->id,
                'has_file' => $request->hasFile('file'),
            ]);

            $request->validate([
                'file' => 'required|file|max:51200|mimes:pdf,doc,docx,xls,xlsx,jpg,png',
            ]);

            if ($lgd->is_active_or_closed === 'closed') {
                Log::warning('Attempt to attach file to closed LGD', ['lgd_id' => $lgd->id]);
                return back()->withErrors(['file' => 'Cannot attach file to a closed LGD record.']);
            }

            // Delete old documents
            $deleted = $lgd->supportingDocuments()->delete();
            Log::info('Old supporting documents deleted', ['count' => $deleted]);

            try {
                $document = \App\Helpers\DocumentHelper::upload(
                    $request->file('file'),
                    $lgd,
                    'lgd_support'
                );

                Log::info('File uploaded successfully', [
                    'document_id' => $document->id,
                    'path' => $document->path,
                ]);

                return back()->with('success', 'File attached successfully.');

            } catch (\Throwable $e) {

                Log::error('File upload failed', [
                    'error' => $e->getMessage(),
                    'lgd_id' => $lgd->id,
                ]);

                return back()->withErrors(['file' => 'Upload failed. Check logs.']);
            }
        }


   public function downloadFile($id)
    {
        $lgd = LossGivenDefault::findOrFail($id);

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

    
 // Function to delete a Loss Given Default record
        // This function finds the record by ID and deletes it from the database
    public function destroy($id){
        $lossGivenDefaults = LossGivenDefault::find($id);

        if(!$lossGivenDefaults){
            return back()->with('error', 'LGD record not found');
        }

        $lossGivenDefaults->delete();

        return back()->with('success', 'LGD record deleted successfully');
    }

}
