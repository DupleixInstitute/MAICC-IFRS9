<?php

namespace App\Http\Controllers;

use App\Models\LoanPortfolio;
use App\Models\LossGivenDefault;
use App\Models\LoanBook;
use App\Models\ReportingPeriods;
use App\Jobs\CalculateLGDJob;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Console\Input\Input;

class LossGiveDefaultController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * 
     */

     // This function returns an Inertia response to render the index view
     // It retrieves all Loss Given Default records along with their associated portfolio groups
    public function index()
    {
        $lossGivenDefaults = LossGivenDefault::with('portfolioGroup')->get();
        return inertia('LossGivenDefault/Index', [
            'lossGivenDefaults' => $lossGivenDefaults,
        ]);
    }

    // Function to create a new Loss Given Default record
    // This function returns an Inertia response to render the create view
    public function create()
    {
        
        return inertia('LossGivenDefault/Create',[
            'portfolio_group' => LoanPortfolio::select('id', 'name')->get(),
            
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
                'portfolio_group' => 'required|exists:loan_portfolios,id',
                'reporting_period' => 'required|date',
                'start_period' => 'required|date|before_or_equal:reporting_period',
            ]);

            // Validate that start_period is not later than reporting_period
            // This ensures that the start period is not later than the reporting period
            $portfolioGroup = LoanPortfolio::findOrFail(request()->input('portfolio_group'));
            $startPeriod = Carbon::parse(request()->input('start_period'))->format('Y-m');
            $reportingPeriod = Carbon::parse(request()->input('reporting_period'))->format('Y-m');


            // Query to fetch loan books data for the specified reporting period and portfolio group
            // This query retrieves the start and end balances, and the closing stage for each contract
            $data = DB::table('loan_books as lb_start')
                ->leftJoin('loan_books as lb_end', function($join) use ($reportingPeriod) {
                    $join->on('lb_start.contract_id', '=', 'lb_end.contract_id')
                        ->on('lb_start.loan_portfolio_id', '=', 'lb_end.loan_portfolio_id')
                        ->where('lb_end.reporting_period', '=', $reportingPeriod);
                })
                ->where('lb_start.reporting_period', '=', $startPeriod)
                ->where('lb_start.calculated_ifrs9_stage', 3)
                ->where('lb_start.loan_portfolio_id', $portfolioGroup->id)
                ->select(
                    'lb_start.contract_id',
                    DB::raw('lb_start.principal_balance as start_balance'),
                    DB::raw('COALESCE(lb_end.principal_balance, 0) as end_balance'),
                    DB::raw('COALESCE(lb_end.calculated_ifrs9_stage, 3) as closing_stage')
                )
                ->get();

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

            // Create a new LossGivenDefault record
            LossGivenDefault::create([
                'reporting_period' => $reportingPeriod,
                'start_period' => $startPeriod,
                'portfolio_group' => $portfolioGroup->id,
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
                'calculation_source' => 'system',
                'is_active_or_closed' => $request->input('is_active_or_closed', 'active'),
            ]);

            $timeTaken = round(microtime(true) - $startTime, 3);

            return back()->with('success', 'Loss Given Default calculated successfully in ' . $timeTaken . ' seconds.');
        }


    // Function to store manual LGD calculations
    // This function processes the request, validates inputs, and stores the manual LGD calculation in the database
        public function storeManualCalculation(Request $request)
                
                    {
                        $mode = $request->input('mode');

                        $baseValidation = [
                            'reporting_period' => 'required|date',
                            'start_period' => 'required|date',
                            'portfolio_group' => 'required|integer',
                            'loss_given_default_percentage' => 'required|numeric',
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
                            'cure_rate' => 'required|numeric',
                            'recovery_rate' => 'required|numeric',
                        ];

                        $request->validate(array_merge(
                            $baseValidation,
                            $mode === 'amount' ? $amountFields : $percentageFields
                        ));

                        $data = [
                            'reporting_period' => $request->reporting_period,
                            'start_period' => $request->start_period,
                            'portfolio_group' => $request->portfolio_group,
                            'loss_given_default_percentage' => $request->loss_given_default_percentage,
                            'cure_rate' => $request->cure_rate ?? 0,
                            'cured_amount' =>$request->cured_amount ?? 0,
                            'recovery_rate' => $request->recovery_rate ?? 0,
                            'recovered_amount' => $request->recovered_amount ?? 0,
                            'total_disbursments' => $request->total_disbursments ?? 0,
                            'partially_recovered_amount' => $request->partially_recovered_amount,
                            'fully_recovered_amount' => $request->fully_recovered_amount,
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
                            $data['cure_amount_stage1'] = $request->cure_amount_stage1 ?? 0;
                            $data['cure_amount_stage2'] = $request->cure_amount_stage2 ?? 0;
                            $data['partially_recovered_amount'] = $request->partially_recovered_amount ?? 0;
                            $data['fully_recovered_amount'] = $request->fully_recovered_amount ?? 0;
                            $data['total_disbursments'] = $request->total_disbursments ?? 0;
                        } else {
                            $data['start_total_stage3'] = 0;
                            $data['end_total_stage3'] = 0;
                            $data['cured_amount'] = 0;
                            $data['recovered_amount'] = 0;
                        }

                        LossGivenDefault::create($data);

                return back()->with('success', 'Loss Given Default record created successfully.');
            }

    // Function to upadate manual method of LGD Calculation
    public function updateManual($id, Request $request)
    {
        $request->validate([
            'reporting_period' => 'required|date',
            'start_period' => 'required|date',
            'portfolio_group' => 'required|integer',
            'loss_given_default_percentage' => 'required|numeric',
            'mode' => 'required|in:amount,percentage',
        ]);

        $lossGivenDefault = LossGivenDefault::find($id);
        if (!$lossGivenDefault) {
            return back()->with('error', 'Loss Given Default record not found');
        }

        $data = $request->all();
        $lossGivenDefault->update($data);

        return redirect()->route('loss-given-default.index')->with('success', 'Loss Given Default record updated successfully');
    }
    
    public function editManual($id)
    {
        $lossGivenDefault = LossGivenDefault::find($id);
        if (!$lossGivenDefault) {
            return back()->with('error', 'Loss Given Default record not found');
        }

        return inertia('LossGivenDefault/Create', [
            'lossGivenDefault' => $lossGivenDefault,
            'portfolios' => LoanPortfolio::select('id', 'name')->get(),
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
            
            $request->validate([
                'reporting_period' => 'required|date_format:Y-m',
                'lgd_id' => 'required|exists:loss_given_default,id',
            ]);

            $lgd = LossGivenDefault::findOrFail($request->lgd_id);

            // 1. Update loan_books with LGD value
            if($lgd->is_active_or_closed !== 'closed'){
                return back()->with('error', 'Cannot update loan books for an active LGD record.');
            } else{
            LoanBook::where('reporting_period', $request->reporting_period)
                ->update(['lgd_value' => $lgd->loss_given_default_percentage]); 
                }
            
            $endTime = microtime(true);
            $timeTaken = round(($endTime - $startTime) / 60, 2); // time in minutes, rounded to 2 decimal places

            
            // Parse year and month from reporting_period (e.g., '2025-06')
            $period = Carbon::parse($request->reporting_period)->format('Y-m'); // e.g., "2025-06"
            $periodParts = explode('-', $period); // ["2025", "06"]

            $year = $periodParts[0] . '-01-01';     // "2025-01-01"
            $month = $periodParts[0] . '-' . $periodParts[1] . '-01'; // "2025-06-01"

            
            // 2. Save or update reporting_period record
            ReportingPeriods::updateOrCreate(
                ['period' => $period],
                [
                    'reporting_year' => $year,
                    'reporting_month' => $month,
                    'lgd_id' => $lgd->id,
                    'lgd_calculation_source' => $lgd->calculation_source,
                    'lgd_calculation_time' => $timeTaken,
                ]
            );
            return redirect()->back()->with('success', 'Loan books updated in ' . $timeTaken . ' seconds.');
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
