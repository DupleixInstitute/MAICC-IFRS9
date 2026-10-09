<?php

namespace App\Services\Pd;

use App\Models\TransitionMatrix;
use App\Models\TransitionMatrixData;
use App\Services\AuditLoggerService;
use App\Services\TransitionMatrixService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The PD engine as a service (spec v4 section 6.10.1, step 4): the
 * transition matrix over a window of staged loan books, then the
 * probability of reaching Stage 3 from each stage written to the loan book
 * as the pre-FLI PD, with the lifetime PD from the remaining tenor. This is
 * what the Monthly Probability screen does; lifted here so the bootstrap and
 * the scheduler can run it without a request.
 */
class PdEngineService
{
    /** @return array{matrix_id:int,window:string,transitioned:int,pds:array<int,float>,updated:int} */
    public function run(string $period, int $portfolioId, int $windowMonths = 12, ?string $userName = 'system', ?int $userId = null): array
    {
        \App\Support\ReportingPeriodLock::assertOpen($period, 'the PD engine');
        $profileId = (int) (DB::table('transition_profile_definitions')->where('profile_code', 'M101')->value('id') ?? DB::table('transition_profile_definitions')->min('id'));
        if (! $profileId) {
            throw new RuntimeException('No transition profile: run MaiicTransitionProfileSeeder.');
        }
        $end = CarbonImmutable::parse($period . '-01');
        $start = $end->subMonths($windowMonths);
        $available = DB::table('loan_books')->whereNotNull('ifrs9stage_pre_qualitative')->where('loan_portfolio_id', $portfolioId)->min('reporting_period');
        if ($available === null) {
            throw new RuntimeException('No staged loan book: run eir:stage first.');
        }
        if ($start->format('Y-m') < $available) {
            $start = CarbonImmutable::parse($available . '-01');
        }
        if ($start->format('Y-m') >= $end->format('Y-m')) {
            throw new RuntimeException("The window {$start->format('Y-m')} to {$end->format('Y-m')} holds no transition.");
        }
        $matrix = TransitionMatrix::create([
            'transition_profile_id' => $profileId, 'start_reporting_period' => $start->format('Y-m'), 'end_reporting_period' => $end->format('Y-m'),
            'pd_start_stage_total_type' => '1', 'pd_calculation_level' => 'portfolio', 'pd_calculation_id' => $portfolioId, 'pd_calculation_code' => null,
            'calculation_source' => 'system', 'start_year' => $start->year, 'start_month' => $start->month, 'end_year' => $end->year, 'end_month' => $end->month,
            'transition_years' => max(1, (int) round($start->diffInMonths($end) / 12)), 'run_no' => 1, 'records_count_updated' => 0, 'records_count_transitioned' => 0,
            'reporting_periods_count' => 0, 'updated_balance' => 0, 'transition_balance' => 0, 'last_calculation_date' => now(), 'portfolio_count' => 0, 'book_updated_at' => now(),
            'take_on_flag' => 0, 'comments' => 'PdEngineService for ' . $period . ' (engine step 4)', 'user_name' => $userName ?? 'system',
        ]);
        TransitionMatrixService::processTransitionMatrixData($matrix);
        $matrix->refresh();

        // the probability of reaching Stage 3 from each start stage, as the Monthly Probability screen applies it
        $pds = TransitionMatrixData::where('calculation_header_id', $matrix->id)->where('end_stage', 3)->whereNotNull('transition_probability_month')->get()->keyBy('start_stage');
        if ($pds->isEmpty()) {
            throw new RuntimeException("The matrix {$matrix->id} has no transition to Stage 3.");
        }
        // The matrix measures the default rate over the window actually
        // available. A window shorter than twelve months is annualised,
        // 1 - (1 - p)^(12/months), so a three-month rate is not written as a
        // twelve-month PD (system audit of 9 October 2026, finding H8); the
        // window is recorded on the matrix.
        $windowActual = max(1, $start->diffInMonths($end));
        $annualise = fn (float $p) => $windowActual >= 12 ? $p : 1 - pow(1 - max(0.0, min(1.0, $p)), 12 / $windowActual);
        $applied = [];
        $updated = 0;
        DB::transaction(function () use ($pds, $period, $portfolioId, $annualise, &$applied, &$updated) {
            foreach ([1, 2, 3] as $stage) {
                if ($stage !== 3 && ! isset($pds[$stage])) {
                    continue;
                }
                $pd = $stage === 3 ? 1.0 : $annualise((float) $pds[$stage]->transition_probability_month / 100);
                $applied[$stage] = round($pd, 6);
                $updated += DB::update('UPDATE loan_books SET pd_prefli = ?, `12m_pd` = ? WHERE reporting_period = ? AND ifrs9stage_pre_qualitative = ? AND loan_portfolio_id = ?', [$pd, round($pd * 100, 2), $period, (string) $stage, $portfolioId]);
                // lifetime PD over the remaining tenor (months): 1 - (1 - annual PD)^(months/12), at least one month
                foreach (DB::table('loan_books')->where('reporting_period', $period)->where('ifrs9stage_pre_qualitative', (string) $stage)->where('loan_portfolio_id', $portfolioId)->whereNotNull('remaining_tenor')->get(['id', 'remaining_tenor']) as $loan) {
                    $years = max(1.0, (float) $loan->remaining_tenor) / 12;
                    DB::table('loan_books')->where('id', $loan->id)->update(['lifetime_pd' => round(min(1.0, 1 - (1 - $pd) ** $years), 8)]);
                }
            }
            DB::table('transition_matrices')->where('id', $pds->first()->calculation_header_id)->update(['records_count_updated' => $updated, 'book_updated_at' => now(), 'status' => 'closed']);
        });
        AuditLoggerService::log('PD Engine Run', 'transition_matrices', $matrix->id, ['reporting_period' => $period, 'rows_affected' => $updated, 'new_values' => ['window' => $start->format('Y-m') . ' to ' . $end->format('Y-m'), 'pds' => $applied], 'meta' => ['user' => $userId]]);

        return ['matrix_id' => $matrix->id, 'window_months' => $windowActual, 'annualised' => $windowActual < 12, 'window' => $start->format('Y-m') . ' to ' . $end->format('Y-m'), 'transitioned' => (int) $matrix->records_count_transitioned, 'pds' => $applied, 'updated' => $updated];
    }
}
