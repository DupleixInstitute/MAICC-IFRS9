<?php

namespace App\Http\Controllers;

use App\Models\LoanPortfolio;
use App\Models\LossGivenDefault;
use App\Models\LoanBook;
use App\Models\ReportingPeriods;
use App\Models\DiscountedPayment;
use App\Jobs\CalculateLGDJob;
use App\Jobs\CalculateDiscountingJob;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Input\Input;
use ZipArchive;
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
            ini_set('max_execution_time', 3000);

          $request->validate([
                    'portfolio_group' => 'required|exists:loan_portfolios,id',
                    'reporting_period' => 'required|date',
                    'start_period' => 'required|date|before_or_equal:reporting_period',
                    'is_discounting' => 'boolean',

                    'discount_rate_source' => 'nullable|required_if:is_discounting,true|in:manual,loan_book,eir',

                    'interest_rate' => 'nullable|required_if:discount_rate_source,manual|numeric|min:0|max:100',
                ]);

            // Validate that start_period is not later than reporting_period
            // This ensures that the start period is not later than the reporting period
            $portfolioGroup = LoanPortfolio::findOrFail(request()->input('portfolio_group'));
            $startPeriod = Carbon::parse(request()->input('start_period'))->format('Y-m');
            $reportingPeriod = Carbon::parse(request()->input('reporting_period'))->format('Y-m');


            // Get discounting settings from request
            $isDiscounting = $request->input('is_discounting', false);
            $discountRateSource = $request->input('discount_rate_source');
            $manualInterestRate = $request->input('interest_rate', 0);

            // Manual interest rate (already converted to decimal in Vue)

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
                    DB::raw('lb_start.carrying_amount as start_balance'),
                    DB::raw('COALESCE(lb_end.carrying_amount, 0) as end_balance'),
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

            // Discounting variables
            $totalDiscountedAmount = 0;
            $discountedPartlyAmount = 0;
            $discountedFullAmount = 0;
            $totalDiscountLoss = 0;
            $discountedPayments = [];

            // Debug: Log query results
            \Log::info('LGD Calculation Debug', [
                'portfolio_group' => $portfolioGroup->id,
                'start_period' => $startPeriod,
                'reporting_period' => $reportingPeriod,
                'data_count' => $data->count(),
                'sample_data' => $data->take(3)->toArray()
            ]);

            // Iterate through the data to calculate the required values
            // This loop processes each row of data to compute the start and end balances, disbursements, recoveries, and cure amounts
            foreach ($data as $row) {
                $startBalance += $row->start_balance;
                $endBalance += $row->end_balance;
                $netMovement = $row->end_balance - $row->start_balance;
                $disbursement = $netMovement > 0 ? $netMovement : 0;

                $curedStage1 = $row->closing_stage == 1 ? $row->start_balance : 0;
                $curedStage2 = $row->closing_stage == 2 ? $row->start_balance : 0;

                // Initialize these variables for each row
                $paidInFull = 0;
                $paidPartly = 0;

                if($row->closing_stage == 3 || $row->closing_stage == 2 || $row->closing_stage == 1){
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
                $cureAmountStage1 += $curedStage1;
                $cureAmountStage2 += $curedStage2;
                $partlyRecoveredAmount += $paidPartly;
                $fullyRecoveredAmount += $paidInFull;
            }

            // If discounting is enabled, process payments from lgd_payment_tracking_long
            if ($isDiscounting) {
                // Get payment tracking data for contracts with IFRS9 stage = 3
                $paymentTrackingData = DB::table('lgd_payment_tracking_long')
                    ->where('portfolio_group', $portfolioGroup->id)
                    ->where('reporting_period', '>=', $startPeriod . '-01')
                    ->where('reporting_period', '<=', $reportingPeriod . '-01')
                    ->where('ifrs9_stage', 3)
                    ->where('payment_amount', '>', 0)
                    ->select(
                        'contract_id',
                        'reporting_period',
                        'payment_amount',
                        'payment_type',
                        'months_since_default'
                    )
                    ->get();

                // Debug: Log payment tracking data
                \Log::info('Payment Tracking Debug', [
                    'portfolio_group' => $portfolioGroup->id,
                    'start_period' => $startPeriod,
                    'reporting_period' => $reportingPeriod,
                    'start_period_formatted' => $startPeriod . '-01',
                    'reporting_period_formatted' => $reportingPeriod . '-01',
                    'payment_data_count' => $paymentTrackingData->count(),
                    'sample_payment_data' => $paymentTrackingData->take(3)->toArray()
                ]);

                // // Additional debug: Check payment tracking table structure
                // $paymentTrackingSample = DB::table('lgd_payment_tracking_long')
                //     ->where('portfolio_group', $portfolioGroup->id)
                //     ->take(5)
                //     ->get()
                //     ->toArray();

                // \Log::info('Payment Tracking Sample Data', [
                //     'portfolio_group' => $portfolioGroup->id,
                //     'sample_records' => $paymentTrackingSample
                // ]);

                $rateService = app(\App\Services\Ecl\EclDiscountRateService::class);
                $rateResolution = $rateService->resolve(
                    array_map(
                        fn ($p) => ['contract_id' => $p->contract_id, 'period' => (string) $p->reporting_period],
                        is_array($paymentTrackingData) ? $paymentTrackingData : $paymentTrackingData->all()
                    ),
                    $discountRateSource,
                    $discountRateSource === 'manual' ? (float) $manualInterestRate : null,
                );
                $unresolvedDiscountRates = [];

                foreach ($paymentTrackingData as $payment) {
                    $rateKey = $payment->contract_id . '|' . $rateService->periodKey((string) $payment->reporting_period);
                    $resolvedRate = $rateResolution['rates'][$rateKey] ?? null;

                    // A payment whose discount basis cannot be established is
                    // excluded rather than discounted at an assumed rate.
                    if ($resolvedRate === null) {
                        $unresolvedDiscountRates[$payment->contract_id] = $rateResolution['unresolved'][$rateKey] ?? 'Unknown reason.';
                        continue;
                    }
                    $interestRate = $resolvedRate['rate'];

                    // Calculate discounting days based on reporting period
                    $paymentDate = Carbon::parse($payment->reporting_period)->endOfMonth();
                    $reportingDate = Carbon::parse($reportingPeriod)->endOfMonth();
                    $discountingDays = max(0, $paymentDate->diffInDays($reportingDate, false));
                    $discountingDays = min($discountingDays, 3650); // Cap at 3650 days

                    \Log::info('Discounting Calculation', [
                        'contract_id' => $payment->contract_id,
                        'payment_amount' => $payment->payment_amount,
                        'interest_rate' => $interestRate,
                        'months_since_default' => $payment->months_since_default,
                        'discounting_days' => $discountingDays,
                        'reporting_period' => $payment->reporting_period
                    ]);

                    // Calculate discounted amount
                    $discountedAmount = DiscountedPayment::calculateDiscountedAmount(
                        $payment->payment_amount,
                        $interestRate,
                        $discountingDays
                    );

                    $discountLoss = $payment->payment_amount - $discountedAmount;

                    \Log::info('Discounting Result', [
                        'contract_id' => $payment->contract_id,
                        'payment_amount' => $payment->payment_amount,
                        'discounted_amount' => $discountedAmount,
                        'discount_loss' => $discountLoss,
                        'interest_rate_used' => $interestRate,
                        'discounting_days_used' => $discountingDays
                    ]);

                    // Store discounted payment data
                    $discountedPayments[] = [
                        'contract_id' => $payment->contract_id,
                        'reporting_period' => $payment->reporting_period,
                        'payment_period' => $payment->reporting_period, // Use reporting_period as payment_period
                        'interest_rate' => $interestRate,
                        'discounting_days' => $discountingDays,
                        'discount_rate_source' => $resolvedRate['applied_source'],
                        'payment_amount' => $payment->payment_amount,
                        'discounted_amount' => $discountedAmount,
                        'discounted_loss' => $discountLoss,
                    ];

                    // Accumulate discounted totals
                    $totalDiscountedAmount += $discountedAmount;
                    $totalDiscountLoss += $discountLoss;

                    // Debug: Log payment type and accumulation
                    \Log::info('Payment Type Accumulation', [
                        'contract_id' => $payment->contract_id,
                        'payment_type' => $payment->payment_type,
                        'discounted_amount' => $discountedAmount,
                        'discounted_partly_before' => $discountedPartlyAmount,
                        'discounted_full_before' => $discountedFullAmount,
                    ]);

                    if ($payment->payment_type === 'partial') {
                        $discountedPartlyAmount += $discountedAmount;
                    } elseif ($payment->payment_type === 'full') {
                        $discountedFullAmount += $discountedAmount;
                    }

                    \Log::info('Payment Type Accumulation After', [
                        'contract_id' => $payment->contract_id,
                        'discounted_partly_after' => $discountedPartlyAmount,
                        'discounted_full_after' => $discountedFullAmount,
                    ]);
                }

                // Update recovered amount with discounted total
                $totalRecoveredAmount = $totalDiscountedAmount;
            } else {
                $totalRecoveredAmount = ($partlyRecoveredAmount + $fullyRecoveredAmount) - $totalDisbursments;
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
            $lgd = LossGivenDefault::create([
                'reporting_period' => $reportingPeriod,
                'start_period' => $startPeriod,
                'lgd_calculation_id' => $portfolioGroup->id,
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
                // Discounting fields
                'is_discounting' => $isDiscounting,
                'discount_rate_source' => $isDiscounting ? $discountRateSource : null,
                'interest_rate' => $isDiscounting && $discountRateSource === 'manual' ? $manualInterestRate : null,
                'discounted_payment_partly' => $discountedPartlyAmount,
                'discounted_payment_full' => $discountedFullAmount,
                'discount_loss' => $totalDiscountLoss,
                'total_payment' => $isDiscounting ? ($totalDiscountedAmount + $totalDiscountLoss) : ($totalRecoveredAmount + $totalDisbursments),
            ]);

            // Store discounted payments if discounting is enabled
            if ($isDiscounting && !empty($discountedPayments)) {
                \Log::info('Storing Discounted Payments', [
                    'lgd_id' => $lgd->id,
                    'payments_count' => count($discountedPayments),
                    'total_discounted_amount' => $totalDiscountedAmount,
                    'total_discount_loss' => $totalDiscountLoss
                ]);

                foreach ($discountedPayments as $paymentData) {
                    DiscountedPayment::upsertDiscountPayment(array_merge($paymentData, ['lgd_id' => $lgd->id]));
                }
            }

            // Debug: Log final LGD creation with discounting values
            \Log::info('LGD Record Created', [
                'lgd_id' => $lgd->id,
                'start_balance' => $startBalance,
                'end_balance' => $endBalance,
                'recovered_amount' => $totalRecoveredAmount,
                'is_discounting' => $isDiscounting,
                'discounted_payment_partly' => $discountedPartlyAmount,
                'discounted_payment_full' => $discountedFullAmount,
                'total_discount_loss' => $totalDiscountLoss,
                'total_payment' => $isDiscounting ? ($totalDiscountedAmount + $totalDiscountLoss) : ($totalRecoveredAmount + $totalDisbursments),
                'discounted_amount' => $isDiscounting ? $totalDiscountedAmount : 'N/A'
            ]);

            $timeTaken = round(microtime(true) - $startTime);

            return redirect()->route('loss-given-default.index')->with('success', 'Loss Given Default record created successfully.');
    }


    /**
     * Get interest rate for a specific contract from loan book
     */
    // The discount rate is resolved by App\Services\Ecl\EclDiscountRateService,
    // which returns the basis actually applied and refuses to invent one. The
    // former helper here returned a hardcoded 10% whenever the tape had no
    // rate, and used the tape's percentage as if it were a decimal.

    // Function to store manual LGD calculations
    // This function processes the request, validates inputs, and stores the manual LGD calculation in the database
        public function storeManualCalculation(Request $request)

                    {
                        $mode = $request->input('mode');

                        $baseValidation = [
                            'reporting_period' => 'required|date',
                            'start_period' => 'required|date',
                            'portfolio_group' => 'required|integer',
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

                        $data = [
                            'reporting_period' => $request->reporting_period,
                            'start_period' => $request->start_period,
                            'lgd_calculation_id' => $request->portfolio_group,
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

                    return redirect()->route('loss-given-default.index')->with('success', 'Loss Given Default record created successfully.');
            }

    // Function to upadate manual method of LGD Calculation
        public function updateManual($id, Request $request)
        {
            $mode = $request->input('mode');

            $baseValidation = [
                'reporting_period' => 'required|date',
                'start_period' => 'required|date',
                'portfolio_group' => 'required|integer',
                'loss_given_default_percentage' => 'required|numeric|min:0|max:100',
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

            $lossGivenDefault = LossGivenDefault::find($id);

            if (!$lossGivenDefault) {
                return back()->with('error', 'Loss Given Default record not found');
            }

            $data = [
                'reporting_period' => $request->reporting_period,
                'start_period' => $request->start_period,
                'lgd_calculation_id' => $request->portfolio_group,
                'loss_given_default_percentage' => $request->loss_given_default_percentage,
                'cure_rate' => $request->cure_rate ?? 0,
                'cured_amount' => $request->cured_amount ?? 0,
                'recovery_rate' => $request->recovery_rate ?? 0,
                'recovered_amount' => $request->recovered_amount ?? 0,
                'total_disbursments' => $request->total_disbursments ?? 0,
                'partially_recovered_amount' => $request->partially_recovered_amount ?? 0,
                'fully_recovered_amount' => $request->fully_recovered_amount ?? 0,
                'is_active_or_closed' => $request->is_active_or_closed ?? 'active',
                'updated_by' => auth()->id(),
            ];

            if ($mode === 'amount') {
                $data['start_total_stage3'] = $request->start_total_stage3;
                $data['end_total_stage3'] = $request->end_total_stage3;
                $data['cure_amount_stage1'] = $request->cure_amount_stage1;
                $data['cure_amount_stage2'] = $request->cure_amount_stage2;
            } else {
                // Clear amount-based values when switching to percentage mode
                $data['start_total_stage3'] = 0;
                $data['end_total_stage3'] = 0;
                $data['cure_amount_stage1'] = 0;
                $data['cure_amount_stage2'] = 0;
                $data['cured_amount'] = 0;
                $data['recovered_amount'] = 0;
            }

            $lossGivenDefault->update($data);

            return redirect()
                ->route('loss-given-default.index')
                ->with('success', 'Loss Given Default record updated successfully.');
        }


        public function editManual($id)
        {
            $lossGivenDefault = LossGivenDefault::with('portfolioGroup')->find($id);

            if (!$lossGivenDefault) {
                return back()->with('error', 'Loss Given Default record not found');
            }

            // Prevent editing closed LGDs (audit safe)
            if ($lossGivenDefault->is_active_or_closed === 'closed') {
                return back()->with('error', 'This LGD is closed and cannot be edited.');
            }

            // Normalize percentages for UI display
            $lossGivenDefault->cure_rate = $lossGivenDefault->cure_rate * 100;
            $lossGivenDefault->recovery_rate = $lossGivenDefault->recovery_rate * 100;

            return inertia('LossGivenDefault/Create', [
                'lossGivenDefault' => $lossGivenDefault,
                'portfolio_group' => LoanPortfolio::select('id', 'name')->get(),
                'isEdit' => true,
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



        public function downloadReportByPeriod(Request $request)
        {
            $request->validate([
                'start_period' => 'required|date_format:Y-m',
                'end_period' => 'required|date_format:Y-m',
            ]);

            $start = Carbon::createFromFormat('Y-m', $request->start_period)->startOfMonth();
            $end = Carbon::createFromFormat('Y-m', $request->end_period)->endOfMonth();

           $lgds = LossGivenDefault::select([
                'id',
                'lgd_calculation_id',
                'loss_given_default_percentage',
                'start_period',
                'reporting_period',
                'start_total_stage3',
                'end_total_stage3',
                'cure_rate',
                'cured_amount',
                'cure_amount_stage1',
                'cure_amount_stage2',
                'recovery_rate',
                'recovered_amount',
                'partially_recovered_amount',
                'fully_recovered_amount',
                'total_disbursments',
                'written_offs',
                'calculation_source',
                'is_active_or_closed',
            ])
            ->with(['portfolioGroup:id,name'])
            ->where('start_period', '<=', $end)
            ->where('reporting_period', '>=', $start)
            ->orderBy('start_period')
            ->orderBy('reporting_period')
            ->orderBy('lgd_calculation_id')
            ->get();

            if ($lgds->isEmpty()) {
                return back()->with('error', 'No LGD records valid for selected period');
            }

            /* ---------- PDF ---------- */
            // $pdf = app('dompdf.wrapper');
            // $pdf->loadView('reports.lgd_period_report', [
            //     'lgds' => $lgds,
            //     'period' => $request->period,
            // ]);

            // $pdfPath = storage_path("app/LGD_Report_{$request->period}.pdf");
            // $pdf->save($pdfPath);

            /* ---------- CSV ---------- */
            $csvHeader = [
                'Portfolio',
                'LGD %',
                'Valid From',
                'Valid To',
                'Start Balance Stage 3',
                'End Balance Stage 3',
                'Cure Rate',
                'Cured Amount',
                'Cure Amount Stage 1',
                'Cure Amount Stage 2',
                'Recovery Rate',
                'Recovered Amount',
                'Partly Recovered Amount',
                'Fully Recovered Amount',
                'Total Disbursements',
                'Written Offs',
                'Source',
                'Status',
            ];

            $csvRows = [];

            // Add report range as first row
            $csvRows[] = ["LGD Report: From {$request->start_period} To {$request->end_period}"];
            $csvRows[] = []; // empty row for spacing

            // Add column headers
            $csvRows[] = $csvHeader;

            // Add LGD data
            foreach ($lgds as $lgd) {
                $csvRows[] = [
                    $lgd->portfolioGroup->name,
                    $lgd->loss_given_default_percentage,
                    Carbon::parse($lgd->start_period)->format('Y-m'),
                    Carbon::parse($lgd->reporting_period)->format('Y-m'),
                    $lgd->start_total_stage3,
                    $lgd->end_total_stage3,
                    $lgd->cure_rate,
                    $lgd->cured_amount,
                    $lgd->cure_amount_stage1,
                    $lgd->cure_amount_stage2,
                    $lgd->recovery_rate,
                    $lgd->recovered_amount,
                    $lgd->partially_recovered_amount,
                    $lgd->fully_recovered_amount,
                    $lgd->total_disbursments,
                    $lgd->written_offs,
                    $lgd->calculation_source,
                    $lgd->is_active_or_closed,
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

            $csvPath = storage_path("app/LGD_Montly_Report_{$request->start_period}_to_{$request->end_period}.csv");
            file_put_contents($csvPath, $csvContent);


            /* ---------- ZIP ---------- */
            $zipPath = storage_path("app/LGD_Monthly_Report_{$request->start_period}_to_{$request->end_period}.zip");

            $zip = new ZipArchive();
            $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            //$zip->addFile($pdfPath, "LGD_Report_{$request->start_period}_to_{$request->end_period}.pdf");
            $zip->addFile($csvPath, "LGD_Montly_Report_{$request->start_period}_to_{$request->end_period}.csv");
            $zip->close();

            return response()->download($zipPath)->deleteFileAfterSend(true);
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

    /**
     * Display discounted payments for a specific LGD
     */
    public function discountedPaymentsIndex($lgdId, Request $request)
    {
        $lgd = LossGivenDefault::with(['portfolioGroup', 'discountedPayments'])
            ->findOrFail($lgdId);

        $query = $lgd->discountedPayments()
            ->with(['lossGivenDefault'])
            ->orderBy('reporting_period')
            ->orderBy('payment_period');

        // $averageDaysToRepayments = $lgd->discountedPayments()->avg('months_since_default') * 30; // Convert months to days

        // Apply filters
        if ($request->filled('contract_id')) {
            $query->where('contract_id', 'like', '%' . $request->input('contract_id') . '%');
        }

        if ($request->filled('discount_rate_source')) {
            $query->where('discount_rate_source', $request->input('discount_rate_source'));
        }

        if ($request->filled('payment_type')) {
            $query->where('payment_type', $request->input('payment_type'));
        }

        $discountedPayments = $query->paginate(15);

        $totals = DiscountedPayment::where('lgd_id', $lgdId)
            ->selectRaw('
                SUM(discounting_days * payment_amount) as weighted_days,
                SUM(payment_amount) as total_payment
            ')
            ->first();

        $averageDaysToRepayments = $totals->total_payment > 0
            ? $totals->weighted_days / $totals->total_payment
            : 0;

        // Debug: Log pagination data
        \Log::info('Pagination Debug', [
            'lgd_id' => $lgdId,
            'total' => $discountedPayments->total(),
            'current_page' => $discountedPayments->currentPage(),
            'last_page' => $discountedPayments->lastPage(),
            'per_page' => $discountedPayments->perPage(),
            'data_count' => $discountedPayments->count(),
        ]);

        // Convert pagination to array for proper Inertia serialization
        $paginationData = [
            'data' => $discountedPayments->items(),
            'current_page' => $discountedPayments->currentPage(),
            'last_page' => $discountedPayments->lastPage(),
            'per_page' => $discountedPayments->perPage(),
            'total' => $discountedPayments->total(),
            'from' => $discountedPayments->firstItem(),
            'to' => $discountedPayments->lastItem(),
        ];

        // The Index page reads lgd.averageDaysToRepayments (and declares a
        // required averageDaysToRepayments prop), so expose it both ways.
        $lgd->averageDaysToRepayments = round($averageDaysToRepayments, 1);

        return inertia('DiscountedPayments/Index', [
            'lgd' => $lgd,
            'discountedPayments' => $paginationData,
            'averageDaysToRepayments' => round($averageDaysToRepayments, 1),
            'filters' => $request->only(['contract_id', 'discount_rate_source', 'payment_type']),
        ]);
    }

    /**
     * Trigger discounting calculation for existing LGD
     */
    public function triggerDiscounting(Request $request, $lgdId)
    {
        $request->validate([
            'discount_rate_source' => 'required_if:is_discounting,true|in:manual,loan_book,eir',
            'interest_rate' => 'required_if:discount_rate_source,manual|numeric|min:0|max:100',
        ]);

        $lgd = LossGivenDefault::findOrFail($lgdId);

        // Update LGD with discounting settings
        $lgd->update([
            'is_discounting' => true,
            'discount_rate_source' => $request->discount_rate_source,
            'interest_rate' => $request->discount_rate_source === 'manual' ? ($request->interest_rate / 100) : null,
        ]);

        // Dispatch discounting calculation job
        CalculateDiscountingJob::dispatch(
            $lgd->id,
            $request->discount_rate_source,
            $request->discount_rate_source === 'manual' ? ($request->interest_rate / 100) : null
        );

        return back()->with('success', 'Discounting calculation started. It will run in the background.');
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
}
