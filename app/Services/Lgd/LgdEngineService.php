<?php

namespace App\Services\Lgd;

use App\Models\LossGivenDefault;
use App\Services\AuditLoggerService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * The LGD engine as a service (spec v4 section 6.10.1, step 5): the cohort
 * workout the Monthly LGD screen performs, lifted so the bootstrap and the
 * scheduler can run it without a request. The loans in Stage 3 at the start
 * of the window are followed to the reporting period: a loan back in Stage
 * 1 or 2 has cured; a balance paid down or paid off has been recovered;
 * LGD = (1 - cure rate) x (1 - recovery rate), floored and capped. The
 * result is written as a system calculation and applied to the period's
 * loan book as lgd_value and collection_lgd, which the ECL reads.
 */
class LgdEngineService
{
    /** @return array{lgd_id:int,window:string,cohort:int,start_balance:float,cure_rate:float,recovery_rate:float,lgd:float,updated:int} */
    public function run(string $period, int $portfolioId, int $windowMonths = 12, ?int $userId = null, ?string $label = null): array
    {
        \App\Support\ReportingPeriodLock::assertOpen($period, 'the LGD engine');
        $end = CarbonImmutable::parse($period . '-01');
        $start = $end->subMonths($windowMonths);
        $available = DB::table('loan_books')->whereNotNull('calculated_ifrs9_stage')->where('loan_portfolio_id', $portfolioId)->min('reporting_period');
        if ($available === null) {
            throw new RuntimeException('No staged loan book: run eir:stage first.');
        }
        if ($start->format('Y-m') < $available) {
            $start = CarbonImmutable::parse($available . '-01');
        }
        $startPeriod = $start->format('Y-m');
        $rows = DB::table('loan_books as s')->leftJoin('loan_books as e', fn ($j) => $j->on('s.contract_id', '=', 'e.contract_id')->on('s.loan_portfolio_id', '=', 'e.loan_portfolio_id')->where('e.reporting_period', '=', $period))
            ->where('s.reporting_period', $startPeriod)->where('s.calculated_ifrs9_stage', '3')->where('s.loan_portfolio_id', $portfolioId)
            ->get(['s.contract_id', DB::raw('s.carrying_amount as start_balance'), DB::raw('e.carrying_amount as end_balance'), DB::raw("COALESCE(e.calculated_ifrs9_stage, '3') as closing_stage")]);
        if ($rows->isEmpty() || (float) $rows->sum('start_balance') <= 0) {
            throw new RuntimeException("No Stage 3 cohort at {$startPeriod} for portfolio {$portfolioId}.");
        }

        /*
        | The workout (system audit of 9 October 2026, finding H7).
        |
        | Cure: a loan back in Stage 1 or 2 at the end of the window. A cured
        | loan is repaying under its contract, so it is out of the recovery
        | measure; cure and recovery are exclusive.
        |
        | Recovery, on the loans that did not cure: the cash received over the
        | window (the rise in the cumulative repayments the loan book carries,
        | month by month), never more than the starting balance; where the book
        | carries no repayments column the fall in the carrying amount stands
        | in. A loan absent from the end book is a write-off unless its last
        | book row shows it settled (zero balance, or a closed status), in
        | which case the balance it last carried counts as recovered. Before
        | this a loan absent from the end book was read as fully recovered.
        |
        | Discounting: each month's cash is discounted to the start of the
        | window at the loan's locked EIR (its contractual rate when no EIR is
        | locked), so the recovery rate is a present value as IFRS 9 B5.5.28
        | requires; is_discounting records that.
        */
        $hasRepayments = Schema::hasColumn('loan_books', 'repayments');
        $hasStatus = Schema::hasColumn('loan_books', 'contract_status');
        $hasRate = Schema::hasColumn('loan_books', 'interest_rate');
        $lockedRates = Schema::hasTable('contract_eir') ? DB::table('contract_eir')->whereNotNull('locked_at')->pluck('eir_effective_annual', 'contract_id') : collect();
        $settled = ['closed', 'paid', 'settled', 'paid_up', 'repaid', 'matured'];

        $startBalance = 0.0; $cured = 0.0; $nonCuredStart = 0.0; $recovered = 0.0; $recoveredPv = 0.0; $writtenOff = 0.0; $endBalance = 0.0; $full = 0.0; $partly = 0.0; $discounted = false;
        foreach ($rows as $r) {
            $sb = (float) $r->start_balance;
            $startBalance += $sb;
            $endBalance += (float) ($r->end_balance ?? 0);
            if ((string) $r->closing_stage === '1' || (string) $r->closing_stage === '2') {
                $cured += $sb;
                continue;
            }
            $nonCuredStart += $sb;
            $rate = isset($lockedRates[$r->contract_id]) ? (float) $lockedRates[$r->contract_id] : null;

            // the loan's rows through the window, oldest first
            $history = DB::table('loan_books')->where('contract_id', $r->contract_id)->where('loan_portfolio_id', $portfolioId)
                ->where('reporting_period', '>=', $startPeriod)->where('reporting_period', '<=', $period)->orderBy('reporting_period')->get();
            $first = $history->first(); $last = $history->last();
            if ($rate === null && $hasRate && $first && $first->interest_rate !== null) {
                $rate = (float) $first->interest_rate; $rate = $rate > 1 ? $rate / 100 : $rate;
            }
            $rate = $rate ?? 0.0;
            if ($rate > 0) { $discounted = true; }

            $cash = 0.0; $pv = 0.0;
            if ($hasRepayments && $first) {
                $prev = (float) $first->repayments;
                foreach ($history->slice(1) as $row) {
                    $m = max(0.0, (float) $row->repayments - $prev); $prev = (float) $row->repayments;
                    $months = CarbonImmutable::parse($startPeriod . '-01')->diffInMonths(CarbonImmutable::parse($row->reporting_period . '-01'));
                    $cash += $m; $pv += $m / (1 + $rate) ** ($months / 12);
                }
            } else {
                $eb = $r->end_balance === null ? (float) ($last->carrying_amount ?? 0) : (float) $r->end_balance;
                $cash = max(0.0, $sb - $eb); $pv = $cash / (1 + $rate) ** ($windowMonths / 24); // mid-window when the months are not known
            }
            // absent at the end: settled, or written off
            if ($r->end_balance === null) {
                $lastBalance = (float) ($last->carrying_amount ?? 0);
                $isSettled = $lastBalance <= 0.0 || ($hasStatus && in_array(strtolower((string) ($last->contract_status ?? '')), $settled, true));
                if ($isSettled && $lastBalance > 0) {
                    $months = CarbonImmutable::parse($startPeriod . '-01')->diffInMonths(CarbonImmutable::parse($last->reporting_period . '-01'));
                    $cash += $lastBalance; $pv += $lastBalance / (1 + $rate) ** ($months / 12);
                } elseif (! $isSettled) {
                    $writtenOff += max(0.0, $sb - $cash);
                }
            }
            $cash = min($cash, $sb); $pv = min($pv, $sb);
            $recovered += $cash; $recoveredPv += $pv;
            if ($cash >= $sb - 0.005) { $full += $sb; } elseif ($cash > 0) { $partly += $cash; }
        }
        $cureRate = $startBalance > 0 ? $cured / $startBalance : 0.0;
        $recoveryRate = $nonCuredStart > 0 ? $recoveredPv / $nonCuredStart : 0.0;
        $lgd = max(0.0, min(1.0, (1 - $cureRate) * (1 - $recoveryRate)));
        $disbursed = 0.0;

        return DB::transaction(function () use ($period, $startPeriod, $portfolioId, $rows, $startBalance, $endBalance, $disbursed, $cured, $partly, $full, $recovered, $recoveredPv, $writtenOff, $discounted, $cureRate, $recoveryRate, $lgd, $userId, $label) {
            $row = LossGivenDefault::create([
                'reporting_period' => $period . '-01', 'start_period' => $startPeriod . '-01', 'lgd_calculation_level' => 'portfolio', 'lgd_calculation_id' => $portfolioId,
                'start_total_stage3' => round($startBalance, 2), 'end_total_stage3' => round($endBalance, 2), 'loss_given_default_percentage' => round($lgd, 6),
                'cured_amount' => round($cured, 2), 'cure_rate' => round($cureRate, 6), 'cure_rate_average_monthly' => 0, 'cure_amount_stage1' => 0, 'cure_amount_stage2' => 0,
                'partially_recovered_amount' => round($partly, 2), 'fully_recovered_amount' => round($full, 2), 'recovered_amount' => round($recovered, 2), 'recovery_rate' => round($recoveryRate, 6),
                'recovery_rate_average_monthly' => 0, 'total_disbursments' => round($disbursed, 2), 'last_reporting_period' => null, 'is_active_or_closed' => 'closed', 'calculation_source' => 'system',
                'written_offs' => round($writtenOff, 2), 'total_payment' => round($recovered, 2), 'discounted_payment_partly' => round($recoveredPv, 2),
                'created_by' => $userId, 'updated_by' => $userId, 'is_discounting' => $discounted, 'discount_rate_source' => $discounted ? 'loan_book' : null, // the column is an enum(manual, loan_book): the rate is the loan's locked EIR, else its contractual rate from the book
            ]);
            $updated = DB::table('loan_books')->where('reporting_period', $period)->where('loan_portfolio_id', $portfolioId)->update(['lgd_value' => round($lgd, 8), 'collection_lgd' => round($lgd, 8)]);
            AuditLoggerService::log('LGD Engine Run', 'loss_given_default', $row->id, ['reporting_period' => $period, 'rows_affected' => $updated,
                'new_values' => ['window' => "{$startPeriod} to {$period}", 'cohort' => $rows->count(), 'cure_rate' => round($cureRate, 6), 'recovery_rate' => round($recoveryRate, 6), 'lgd' => round($lgd, 6)], 'meta' => ['user' => $userId, 'label' => $label]]);

            return ['lgd_id' => $row->id, 'window' => "{$startPeriod} to {$period}", 'cohort' => $rows->count(), 'start_balance' => round($startBalance, 2), 'cure_rate' => round($cureRate, 6), 'recovery_rate' => round($recoveryRate, 6), 'lgd' => round($lgd, 6), 'updated' => $updated];
        });
    }
}
