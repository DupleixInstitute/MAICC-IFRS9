<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\LossGivenDefault;
use App\Services\Ecl\EclDiscountRateService;
use Carbon\Carbon;

class CalculateDiscountingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $maxExceptions = 3;
    public int $timeout = 3600;

    private int $lgdId;
    private string $discountRateSource;
    private ?float $manualInterestRate;

    public function __construct(int $lgdId, string $discountRateSource, ?float $manualInterestRate = null)
    {
        $this->lgdId = $lgdId;
        $this->discountRateSource = $discountRateSource;
        $this->manualInterestRate = $manualInterestRate;
    }

    public function handle(EclDiscountRateService $rates): void
    {
        try {
            Log::info('Starting discounting calculation', ['lgd_id' => $this->lgdId]);

            $lgd = LossGivenDefault::findOrFail($this->lgdId);
            $discounted = 0;
            $skipped = [];

            // loss_given_default has no `portfolio_group` column; the loan
            // portfolio is referenced via `lgd_calculation_id`, which matches
            // lgd_payment_tracking_long.portfolio_group (= loan_portfolio_id).
            $portfolioId = $lgd->lgd_calculation_id;

            // Process in chunks to avoid memory overload
            DB::table('lgd_payment_tracking_long')
                ->where('portfolio_group', $portfolioId)
                ->where('reporting_period', '>=', $lgd->start_period)
                ->where('reporting_period', '<=', $lgd->reporting_period)
                ->where('ifrs9_stage', 3)
                ->where('payment_amount', '>', 0)
                ->orderBy('id')
                ->chunk(500, function ($payments) use ($rates, &$discounted, &$skipped) {
                    $insertData = [];

                    $resolution = $rates->resolve(
                        $payments->map(fn ($p) => ['contract_id' => $p->contract_id, 'period' => (string) $p->reporting_period])->all(),
                        $this->discountRateSource,
                        $this->manualInterestRate,
                    );

                    foreach ($payments as $payment) {
                        $key = $payment->contract_id . '|' . $rates->periodKey((string) $payment->reporting_period);
                        $resolved = $resolution['rates'][$key] ?? null;

                        // No rate, no discounting. Discounting a stage-3
                        // recovery at an assumed rate produces an allowance
                        // whose basis cannot be explained, so the payment is
                        // left out and counted instead.
                        if ($resolved === null) {
                            $reason = $resolution['unresolved'][$key] ?? 'Unknown reason.';
                            $skipped[$reason] = ($skipped[$reason] ?? 0) + 1;
                            continue;
                        }

                        $discountingDays = $this->calculateDiscountingDays($payment->reporting_period, $payment->payment_period);
                        $discountingDays = min($discountingDays, 3650);

                        $discountedAmount = $payment->payment_amount / pow(1 + $resolved['rate'], $discountingDays / 365);
                        $discountLoss = $payment->payment_amount - $discountedAmount;

                        $insertData[] = [
                            'contract_id' => $payment->contract_id,
                            'lgd_id' => $this->lgdId,
                            'reporting_period' => $payment->reporting_period,
                            'payment_period' => $payment->payment_period,
                            'interest_rate' => $resolved['rate'],
                            'discounting_days' => $discountingDays,
                            // The basis actually applied, not the basis asked
                            // for: a floating facility discounted at its
                            // original EIR must not read as the current rate.
                            'discount_rate_source' => $resolved['applied_source'],
                            'payment_amount' => $payment->payment_amount,
                            'discounted_amount' => $discountedAmount,
                            'discounted_loss' => $discountLoss,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    $discounted += count($insertData);
                    if ($insertData !== []) {
                        DB::table('discounted_payments')->insert($insertData);
                    }
                });

            Log::info('Discounting calculation completed', [
                'lgd_id' => $this->lgdId,
                'requested_source' => $this->discountRateSource,
                'payments_discounted' => $discounted,
                'payments_skipped' => array_sum($skipped),
                'skipped_reasons' => $skipped,
            ]);

        } catch (\Exception $e) {
            Log::error('Discounting calculation failed', [
                'lgd_id' => $this->lgdId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function calculateDiscountingDays($reportingPeriod, $paymentPeriod): int
    {
        $reporting = Carbon::parse($reportingPeriod);
        $payment = Carbon::parse($paymentPeriod);
        return $payment->diffInDays($reporting);
    }

    public function tags(): array
    {
        return ['discounting', 'lgd:' . $this->lgdId];
    }
}

// namespace App\Jobs;

// use Illuminate\Bus\Queueable;
// use Illuminate\Contracts\Queue\ShouldQueue;
// use Illuminate\Foundation\Bus\Dispatchable;
// use Illuminate\Queue\InteractsWithQueue;
// use Illuminate\Queue\SerializesModels;
// use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Log;
// use Carbon\Carbon;
// use App\Models\LossGivenDefault;
// use App\Models\DiscountedPayment;
// use App\Models\LoanBook;

// class CalculateDiscountingJob implements ShouldQueue
// {
//     use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

//     /**
//      * The number of times the job may be attempted.
//      */
//     public int $tries = 3;

//     /**
//      * The maximum number of unhandled exceptions to allow before failing.
//      */
//     public int $maxExceptions = 3;

//     /**
//      * The number of seconds the job can run before timing out.
//      */
//     public int $timeout = 3600; // 1 hour

//     private $lgdId;
//     private $discountRateSource;
//     private $manualInterestRate;

//     /**
//      * Create a new job instance.
//      *
//      * @param int $lgdId
//      * @param string $discountRateSource
//      * @param float|null $manualInterestRate Interest rate as decimal (e.g., 0.12 for 12%)
//      */
//     public function __construct(int $lgdId, string $discountRateSource, ?float $manualInterestRate = null)
//     {
//         $this->lgdId = $lgdId;
//         $this->discountRateSource = $discountRateSource;
//         $this->manualInterestRate = $manualInterestRate;
//     }

//     /**
//      * Execute the job.
//      */
//     public function handle(): void
//     {
//         try {
//             Log::info('Starting discounting calculation', ['lgd_id' => $this->lgdId]);

//             $lgd = LossGivenDefault::findOrFail($this->lgdId);

//             // Get payment tracking data for contracts with IFRS9 stage = 3
//             $paymentData = $this->getPaymentTrackingData($lgd);

//             if ($paymentData->isEmpty()) {
//                 Log::warning('No payment data found for discounting', ['lgd_id' => $this->lgdId]);
//                 return;
//             }

//             $totalPayments = 0;
//             $totalDiscounted = 0;
//             $discountedPartly = 0;
//             $discountedFull = 0;

//             foreach ($paymentData as $payment) {
//                 $interestRate = $this->getInterestRate($payment, $lgd);
//                 $discountingDays = $this->calculateDiscountingDays($payment->reporting_period, $payment->payment_period);

//                 if ($discountingDays > 3650) {
//                     Log::warning('Discounting days exceed limit', [
//                         'contract_id' => $payment->contract_id,
//                         'days' => $discountingDays,
//                         'lgd_id' => $this->lgdId
//                     ]);
//                     $discountingDays = 3650;
//                 }

//                 $discountedAmount = DiscountedPayment::calculateDiscountedAmount(
//                     $payment->payment_amount,
//                     $interestRate,
//                     $discountingDays
//                 );

//                 $discountLoss = $payment->payment_amount - $discountedAmount;

//                 // Store discounted payment
//                 DiscountedPayment::upsertDiscountPayment([
//                     'contract_id' => $payment->contract_id,
//                     'lgd_id' => $this->lgdId,
//                     'reporting_period' => $payment->reporting_period,
//                     'payment_period' => $payment->payment_period,
//                     'interest_rate' => $interestRate,
//                     'discounting_days' => $discountingDays,
//                     'discount_rate_source' => $this->discountRateSource,
//                     'payment_amount' => $payment->payment_amount,
//                     'discounted_amount' => $discountedAmount,
//                     'discounted_loss' => $discountLoss,
//                 ]);

//                 // Accumulate totals
//                 $totalPayments += $payment->payment_amount;
//                 $totalDiscounted += $discountedAmount;

//                 if ($payment->payment_type === 'partial') {
//                     $discountedPartly += $discountedAmount;
//                 } elseif ($payment->payment_type === 'full') {
//                     $discountedFull += $discountedAmount;
//                 }
//             }

//             // Update LGD record with discounting results
//             $lgd->update([
//                 'is_discounting' => true,
//                 'discount_rate_source' => $this->discountRateSource,
//                 'interest_rate' => $this->discountRateSource === 'manual' ? $this->manualInterestRate : null,
//                 'discounted_payment_partly' => $discountedPartly,
//                 'discounted_payment_full' => $discountedFull,
//                 'discount_loss' => $totalPayments - $totalDiscounted,
//                 'total_payment' => $totalPayments,
//             ]);

//             Log::info('Discounting calculation completed', [
//                 'lgd_id' => $this->lgdId,
//                 'total_payments' => $totalPayments,
//                 'total_discounted' => $totalDiscounted,
//                 'discount_loss' => $totalPayments - $totalDiscounted
//             ]);

//         } catch (\Exception $e) {
//             Log::error('Discounting calculation failed', [
//                 'lgd_id' => $this->lgdId,
//                 'error' => $e->getMessage(),
//                 'trace' => $e->getTraceAsString()
//             ]);

//             throw $e;
//         }
//     }

//     /**
//      * Get payment tracking data for contracts with IFRS9 stage = 3
//      */
//     private function getPaymentTrackingData($lgd)
//     {
//         return DB::table('lgd_payment_tracking_long')
//             ->where('portfolio_group', $lgd->portfolio_group)
//             ->where('reporting_period', '>=', $lgd->start_period)
//             ->where('reporting_period', '<=', $lgd->reporting_period)
//             ->where('ifrs9_stage', 3)
//             ->where('payment_amount', '>', 0)
//             ->get();
//     }

//     /**
//      * Get interest rate based on source
//      */
//     private function getInterestRate($payment, $lgd): float
//     {
//         if ($this->discountRateSource === 'manual') {
//             return $this->manualInterestRate;
//         }

//         // Get interest rate from loan book
//         $loanBook = LoanBook::where('contract_id', $payment->contract_id)
//             ->where('reporting_period', $payment->reporting_period)
//             ->first();

//         return $loanBook->interest_rate ?? 0.10; // Default 10% if not found
//     }

//     /**
//      * Calculate discounting days: payment_period - reporting_period
//      */
//     private function calculateDiscountingDays($reportingPeriod, $paymentPeriod): int
//     {
//         $reporting = Carbon::parse($reportingPeriod);
//         $payment = Carbon::parse($paymentPeriod);

//         return $payment->diffInDays($reporting);
//     }

//     /**
//      * Get the tags that should be assigned to the job.
//      */
//     public function tags(): array
//     {
//         return ['discounting', 'lgd:' . $this->lgdId];
//     }
// }
