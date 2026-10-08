<?php

namespace App\Services\Lgd;

use App\Models\LossGivenDefault;
use App\Services\AuditLoggerService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
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
            ->get(['s.contract_id', DB::raw('s.carrying_amount as start_balance'), DB::raw('COALESCE(e.carrying_amount, 0) as end_balance'), DB::raw("COALESCE(e.calculated_ifrs9_stage, '3') as closing_stage")]);
        if ($rows->isEmpty() || (float) $rows->sum('start_balance') <= 0) {
            throw new RuntimeException("No Stage 3 cohort at {$startPeriod} for portfolio {$portfolioId}.");
        }
        $startBalance = 0.0; $disbursed = 0.0; $cured = 0.0; $partly = 0.0; $full = 0.0; $endBalance = 0.0;
        foreach ($rows as $r) {
            $sb = (float) $r->start_balance; $eb = (float) $r->end_balance;
            $startBalance += $sb; $endBalance += $eb;
            if ($eb - $sb > 0) {
                $disbursed += $eb - $sb;
            }
            if ((string) $r->closing_stage === '1' || (string) $r->closing_stage === '2') {
                $cured += $sb;
            }
            if ($eb == 0.0) {
                $full += $sb;
            } elseif ($eb < $sb) {
                $partly += $sb - $eb;
            }
        }
        $recovered = ($partly + $full) - $disbursed;
        $cureRate = $startBalance > 0 ? $cured / $startBalance : 0.0;
        $recoveryRate = $startBalance > 0 ? $recovered / $startBalance : 0.0;
        $lgd = max(0.0, min(1.0, (1 - $cureRate) * (1 - $recoveryRate)));

        return DB::transaction(function () use ($period, $startPeriod, $portfolioId, $rows, $startBalance, $endBalance, $disbursed, $cured, $partly, $full, $recovered, $cureRate, $recoveryRate, $lgd, $userId, $label) {
            $row = LossGivenDefault::create([
                'reporting_period' => $period . '-01', 'start_period' => $startPeriod . '-01', 'lgd_calculation_level' => 'portfolio', 'lgd_calculation_id' => $portfolioId,
                'start_total_stage3' => round($startBalance, 2), 'end_total_stage3' => round($endBalance, 2), 'loss_given_default_percentage' => round($lgd, 6),
                'cured_amount' => round($cured, 2), 'cure_rate' => round($cureRate, 6), 'cure_rate_average_monthly' => 0, 'cure_amount_stage1' => 0, 'cure_amount_stage2' => 0,
                'partially_recovered_amount' => round($partly, 2), 'fully_recovered_amount' => round($full, 2), 'recovered_amount' => round($recovered, 2), 'recovery_rate' => round($recoveryRate, 6),
                'recovery_rate_average_monthly' => 0, 'total_disbursments' => round($disbursed, 2), 'last_reporting_period' => null, 'is_active_or_closed' => 'closed', 'calculation_source' => 'system',
                'created_by' => $userId, 'updated_by' => $userId, 'is_discounting' => false,
            ]);
            $updated = DB::table('loan_books')->where('reporting_period', $period)->where('loan_portfolio_id', $portfolioId)->update(['lgd_value' => round($lgd, 8), 'collection_lgd' => round($lgd, 8)]);
            AuditLoggerService::log('LGD Engine Run', 'loss_given_default', $row->id, ['reporting_period' => $period, 'rows_affected' => $updated,
                'new_values' => ['window' => "{$startPeriod} to {$period}", 'cohort' => $rows->count(), 'cure_rate' => round($cureRate, 6), 'recovery_rate' => round($recoveryRate, 6), 'lgd' => round($lgd, 6)], 'meta' => ['user' => $userId, 'label' => $label]]);

            return ['lgd_id' => $row->id, 'window' => "{$startPeriod} to {$period}", 'cohort' => $rows->count(), 'start_balance' => round($startBalance, 2), 'cure_rate' => round($cureRate, 6), 'recovery_rate' => round($recoveryRate, 6), 'lgd' => round($lgd, 6), 'updated' => $updated];
        });
    }
}
