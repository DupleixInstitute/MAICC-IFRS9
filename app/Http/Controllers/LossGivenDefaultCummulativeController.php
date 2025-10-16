<?php

namespace App\Http\Controllers;

use App\Models\LoanPortfolio;
use App\Models\LossGivenDefaultCummulative;
use App\Models\LoanBook;
use App\Models\LossGivenDefault;
use App\Models\ReportingPeriods;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LossGivenDefaultCummulativeController extends Controller
{
    // Function to display the list of cummulative LGD records
    // This function retrieves all cummulative LGD records and their associated portfolio groups
    public function index()
    {
        $lgdCummulatives = LossGivenDefaultCummulative::with('portfolioGroup')->get();
        return inertia('LossGivenDefault/Cummulative', [
            'lgdCummulatives' => $lgdCummulatives,
        ]);
    }

    // Function to create a new cummulative LGD record
    // This function returns an Inertia response to render the create view
    public function create()
    {
        return inertia('LossGivenDefault/CummulativeCreate', [
            'portfolio_group' => LoanPortfolio::select('id', 'name')->get()
        ]);
    }

    // Function to calculate and save cummulative LGD using the LossGivenDefault model
    // This function validates the request, retrieves records based on the portfolio group and reporting period,
    // @return \Illuminate\Http\RedirectResponse
        public function cummulativeLGD()
        {
            ini_set('max_execution_time', 300);

            request()->validate([
                'portfolio_group' => 'required|exists:loan_portfolios,id',
                'reporting_period' => 'required|date',
                'start_period' => 'required|date|before_or_equal:reporting_period',
            ]);

            // Parse the reporting period and start period to ensure they are in the correct format
            // The format 'Y-m-01' ensures that the date is set to the first

            $startPeriod = Carbon::parse(request()->input('start_period'))->format('Y-m-01');
            $reportingPeriod = Carbon::parse(request()->input('reporting_period'))->format('Y-m-01');
            $portfolioGroupId = request()->input('portfolio_group');

            // Validate that start period is before or equal to reporting period
            if($startPeriod > $reportingPeriod) {
                return back()->withErrors(['Start period must be before or equal to reporting period.']);
            }

            // Variables to hold the data for the cummulative LGD record
            // Retrieve records for the LossGivenDefault model based on the portfolio group and reporting period
            $records = LossGivenDefault::where('portfolio_group', $portfolioGroupId)
                ->whereBetween('reporting_period', [$startPeriod, $reportingPeriod])
                ->where('is_active_or_closed', 'closed')
                ->get();

            if ($records->isEmpty()) {
                return back()->withErrors(['No closed LGD records found in the selected period.']);
            }

            $periodPairs = $records->unique(function ($item) {
                return $item->start_period.$item->reporting_period;
            });

            // Calculate the required sums from the records
            $startBalance = $records->sum('start_total_stage3');
            $endBalance = $records->sum('end_total_stage3');
            $curedAmount = $records->sum('cured_amount');
            $recoveredAmount = $records->sum('recovered_amount');
            $partlyRecoveredAmount = $records->sum('partially_recovered_amount');
            $fullyRecoveredAmount = $records->sum('fully_recovered_amount');
            $totalDisbursments = $records->sum('total_disbursments');

            // Calculate rates
            $cureRate = $startBalance > 0 ? ($curedAmount / $startBalance) : 0;
            $recoveryRate = $startBalance > 0 ? ($recoveredAmount / $startBalance) : 0;

            $lgd = (1 - $cureRate) * (1 - $recoveryRate);

            // Save cummulative LGD
            LossGivenDefaultCummulative::create([
                'reporting_period' => $reportingPeriod,
                'start_period' => $startPeriod,
                'portfolio_group' => $portfolioGroupId,
                'start_total_stage3' => $startBalance,
                'end_total_stage3' => $endBalance,
                'cured_amount' => $curedAmount,
                'cure_amount_stage1' => $records->sum('cure_amount_stage1'),
                'cure_amount_stage2' => $records->sum('cure_amount_stage2'),
                'cure_rate_cummulative' => $cureRate,
                'recovered_amount' => $recoveredAmount,
                'partially_recovered_amount' => $partlyRecoveredAmount,
                'fully_recovered_amount' => $fullyRecoveredAmount,
                'recovery_rate_cummulative' => $recoveryRate,
                'total_disbursments' => $totalDisbursments,
                'lgd_cummulative' => $lgd,
                'lgd_cummulative_percent' => $lgd * 100,
                'periods_count' => $periodPairs->count(),
                'periods_list' => $periodPairs->map(function ($record) {
                                    return [
                                       'start' => Carbon::parse($record->start_period)->format('Y-m'),
                                        'end' => Carbon::parse($record->reporting_period)->format('Y-m'),
                                    ];
                                })->values()->toArray(),
                'calculation_source' => 'system',
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
                'is_active_or_closed' => 'active',
            ]);

            

            return back()->with('success', 'Cummulative LGD calculated and saved successfully.');
        }

    // Function to create a manual cummulative LGD record 
    // @param Request $request
    public function manualCummulativeLGD(Request $request)
    {
        $request->validate([
            'portfolio_group' => 'required|exists:loan_portfolios,id',
            'reporting_period' => 'required|date',
            'start_period' => 'required|date|before_or_equal:reporting_period',
            'mode' => 'required|in:amount,percentage',
        ]);

        // Periods for calculation must be in the format 'Y-m-01'
        $reportingPeriod = Carbon::parse($request->input('reporting_period'))->format('Y-m-01');
        $startPeriod = Carbon::parse($request->input('start_period'))->format('Y-m-01');
        
        // Validate that start period is before or equal to reporting period
         // If the start period is after the reporting period, return an error
        if ($startPeriod > $reportingPeriod) {
            return back()->withErrors(['Start period must be before or equal to reporting period.']);
        }

        // Variables to hold the data for the cummulative LGD record
        $data = [
            'reporting_period' => $reportingPeriod,
            'start_period' => $startPeriod,
            'portfolio_group' => $request->input('portfolio_group'),
            'lgd_cummulative' => $request->input('lgd_cummulative'),
            'lgd_cummulative_percent' => $request->input('lgd_cummulative_percent',0),
            'cured_amount' => $request->input('cured_amount'),
            'cure_rate_cummulative' => $request->input('cure_rate_cummulative', 0),
            'recovery_rate_cummulative' => $request->input('recovery_rate_cummulative', 0),
            'cure_rate_average_monthly' => 0,
            'recovery_rate_average_monthly' => 0,
            'last_reporting_period' => null,
            'is_active_or_closed' => 1,
            'calculation_source' => 'manual',
            'periods_count' => $request->input('periods_count', 0),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ];

        // If the mode is 'amount', validate and add the required fields
        // If the mode is 'percentage', validate and add the required fields

        if ($request->mode === 'amount') {
            $request->validate([
                'start_total_stage3' => 'required|numeric',
                'end_total_stage3' => 'required|numeric',
                'cure_amount_stage1' => 'required|numeric',
                'cure_amount_stage2' => 'required|numeric',
                'partially_recovered_amount' => 'required|numeric',
                'fully_recovered_amount' => 'required|numeric',
                'total_disbursments' => 'required|numeric',
                'periods_count' => 'required|numeric',
            ]);

            $data += [
                'start_total_stage3' => $request->input('start_total_stage3'),
                'end_total_stage3' => $request->input('end_total_stage3'),
                'cure_amount_stage1' => $request->input('cure_amount_stage1'),
                'cure_amount_stage2' => $request->input('cure_amount_stage2'),
                'partially_recovered_amount' => $request->input('partially_recovered_amount'),
                'fully_recovered_amount' => $request->input('fully_recovered_amount'),
                'total_disbursments' => $request->input('total_disbursments'),
                'periods_count' => $request->input('periods_count')
            ];
        } else {
            $request->validate([
                'cure_rate_cummulative' => 'required|numeric',
                'recovery_rate_cummulative' => 'required|numeric',
                'period_count' => 'required|numeric'
            ]);

            $data += [
                'start_total_stage3' => 0,
                'end_total_stage3' => 0,
                'cured_amount' => 0,
                'recovered_amount' => 0,
            ];
        }

        // Create the cummulative LGD record
        LossGivenDefaultCummulative::create($data);

        return redirect()->back()->with('success', 'Loss Given Default record created successfully.');
    }

    // Locking Function for LGD record which prevents record from being deleted in the frontend
    public function keyLock($id, Request $request)
            {
                $request->validate([
                    'is_active_or_closed' => 'nullable|in:active,closed',
                ]);

                $lgd_cummulatives = LossGivenDefaultCummulative::findOrFail($id);

                logger()->info('Auth check', [
                    'user_id' => auth()->user()?->id,
                    'roles' => auth()->user()?->getRoleNames(),
                ]);

                // Check if the user is an admin or the record is already closed
                if (
                    $lgd_cummulatives->is_active_or_closed === 'closed' &&
                    !auth()->user()?->hasRole('admin')
                ) {
                    return back()->with('error', 'Only an Administrator can unlock a closed LGD record');
                }

                // Toggle between 'active' and 'closed'
                $lgd_cummulatives->is_active_or_closed = $lgd_cummulatives->is_active_or_closed === 'closed' ? 'active' : 'closed';
                $lgd_cummulatives->save();

                return redirect()->back()->with('success', 'LGD record status updated.');
            }


        // Update loan books with the LGD value
        // @param Request $request
        // 
       public function updateLoanBooks(Request $request)
        {
            ini_set('max_execution_time', 300);
            $startTime = microtime(true);

            $request->validate([
                'reporting_period' => 'required|date_format:Y-m',
                'lgd_id' => 'required|exists:loss_given_default_cummulative,id',
            ]);

            $lgd = LossGivenDefaultCummulative::findOrFail($request->lgd_id);
           

            // 2. Update loan_books with LGD value
            if($lgd->is_active_or_closed !== 'closed'){
                return back()->with('error', 'Cannot update loan books for an active LGD record.');
            } else{
            LoanBook::where('reporting_period', $request->reporting_period)
                ->update(['lgd_value' => $lgd->lgd_cummulative]); 
                } 

            $endTime = microtime(true);
            $timeTaken = round(($endTime - $startTime) / 60, 2);
                
            // Parse year and month from reporting_period (e.g., '2025-06')
            $period = Carbon::parse($request->reporting_period)->format('Y-m'); // e.g., "2025-06"
            $periodParts = explode('-', $period); // ["2025", "06"]

            $year = $periodParts[0] . '-01-01';     // "2025-01-01"
            $month = $periodParts[0] . '-' . $periodParts[1] . '-01'; // "2025-06-01"
            // 2. Save or update reporting_period record
            ReportingPeriods::updateOrCreate(
                ['period' => $request->reporting_period],
                [
                    'reporting_year' => $year,
                    'reporting_month' => $month,
                    'lgd_id' => $lgd->id,
                    'lgd_calculation_source' => $lgd->calculation_source,
                    'lgd_calculation_time' => $timeTaken,
                ]
            );
            return redirect()->back()->with('success', 'Loan books updated');
        }

    // Delete a specific Loss Given Default Cummulative record
    // @param int $id
    public function destroy($id)
    {
        $lgdCummulative = LossGivenDefaultCummulative::find($id);

        if (!$lgdCummulative) {
        return back()->with('error','Record cannot be found');
        }
        $lgdCummulative->delete();
        return back()->with('success', 'Deleted Successfully');
    }
}