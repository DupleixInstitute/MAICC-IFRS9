<?php

namespace App\Services\Lgd;

use App\Services\AuditLoggerService;
use App\Services\Eir\GovernanceService;
use App\Services\Pd\PdSegmentationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * The LGD by portfolio (9 October 2026): the cohort workout of
 * LgdEngineService measured for each portfolio of the period over one
 * shared window. A portfolio whose Stage 3 cohort at the window start is
 * smaller than the governed minimum (lgd_segment_min_cohort) follows the
 * governed thin-segment rule (pd_segment_thin_rule): it takes the pooled
 * book's LGD, provided the pooled book meets the minimum, or the run stops
 * and names the portfolio. Each measured cohort is written to
 * loss_given_default (level portfolio or book), the outcome per portfolio
 * to segment_parameter_results, and each loan carries the segment whose
 * LGD it took and the loss_given_default row behind it.
 */
class LgdSegmentationService
{
    public function __construct(private GovernanceService $governance, private LgdEngineService $engine, private PdSegmentationService $segments)
    {
    }

    public static function parseMinimum(string $option): int
    {
        if (preg_match('/(\d+)/', $option, $m) !== 1) {
            throw new InvalidArgumentException("The LGD minimum '{$option}' names no number of loans.");
        }

        return (int) $m[1];
    }

    /** @return array{run_id:string,window:string,portfolios:list<array>,pooled:?array,updated:int} */
    public function run(string $period, ?array $portfolioIds = null, int $windowMonths = 12, ?int $userId = null): array
    {
        \App\Support\ReportingPeriodLock::assertOpen($period, 'the LGD engine');
        $asOf = PdSegmentationService::periodEnd($period);
        $minimumOption = $this->governance->get('lgd_segment_min_cohort', $asOf);
        $thinOption = $this->governance->get('pd_segment_thin_rule', $asOf);
        $min = self::parseMinimum($minimumOption);
        $thin = PdSegmentationService::parseThinRule($thinOption);
        $portfolioIds = $portfolioIds ?? $this->segments->scope($period);
        if ($portfolioIds === []) {
            throw new RuntimeException("The loan book of {$period} holds no loan.");
        }

        // one window for every portfolio: twelve months back, or the first staged month of the scope
        $end = CarbonImmutable::parse($period . '-01');
        $start = $end->subMonths($windowMonths);
        $available = DB::table('loan_books')->whereNotNull('calculated_ifrs9_stage')->whereIn('loan_portfolio_id', $portfolioIds)->min('reporting_period');
        if ($available === null) {
            throw new RuntimeException('No staged loan book: run eir:stage first.');
        }
        $startPeriod = max($start->format('Y-m'), $available);

        $pooled = $this->engine->measure($period, $portfolioIds, $windowMonths, $startPeriod);
        $pooledOk = $pooled['cohort'] >= $min && $pooled['start_balance'] > 0;
        $outcomes = [];
        $failed = [];
        foreach ($portfolioIds as $pid) {
            $m = $this->engine->measure($period, [$pid], $windowMonths, $startPeriod);
            $loans = DB::table('loan_books')->where('reporting_period', $period)->where('loan_portfolio_id', $pid)->count();
            $own = $m['cohort'] >= $min && $m['start_balance'] > 0;
            $why = $m['cohort'] === 0 ? "no Stage 3 loan at {$startPeriod}" : "{$m['cohort']} Stage 3 loan" . ($m['cohort'] === 1 ? '' : 's') . " at {$startPeriod}, below the minimum of {$min}";
            if ($m['cohort'] > 0 && $m['start_balance'] <= 0) {
                $why = "the Stage 3 loans at {$startPeriod} carry no balance";
            }
            $o = ['portfolio_id' => (int) $pid, 'key' => "portfolio:{$pid}", 'label' => $this->segments->label("portfolio:{$pid}"), 'measure' => $m, 'loans' => $loans];
            if ($own) {
                $o += ['status' => 'own', 'from' => "portfolio:{$pid}", 'lgd' => $m['lgd'], 'reason' => null];
            } elseif ($thin === 'fail') {
                $o += ['status' => 'failed', 'from' => null, 'lgd' => null, 'reason' => "Thin: {$why}; the governed rule is to fail closed"];
            } elseif (! $pooledOk) {
                $o += ['status' => 'failed', 'from' => null, 'lgd' => null, 'reason' => "Thin: {$why}; the pooled book has {$pooled['cohort']} Stage 3 loans, also below the minimum"];
            } else {
                $o += ['status' => 'parent', 'from' => 'book', 'lgd' => $pooled['lgd'], 'reason' => "Thin: {$why}; takes the pooled book's LGD"];
            }
            if ($o['status'] === 'failed' && $loans > 0) {
                $failed[] = $o;
            }
            $outcomes[] = $o;
        }

        $runId = (string) Str::uuid();
        $window = "{$startPeriod} to {$period}";
        if ($failed !== []) {
            $reason = 'No LGD for ' . implode('; ', array_map(fn ($f) => "{$f['label']} ({$f['key']}), {$f['loans']} loan(s): {$f['reason']}", $failed));
            $this->record($runId, $period, $startPeriod, $windowMonths, $minimumOption, $thinOption, $outcomes, $pooled, $pooledOk, [], 'failed', $reason, 0, $userId);
            AuditLoggerService::log('LGD Segment Run Failed', 'segment_parameter_runs', null, ['reporting_period' => $period, 'new_values' => ['run_id' => $runId, 'window' => $window, 'reason' => $reason], 'meta' => ['user' => $userId]]);
            throw new RuntimeException("LGD by portfolio for {$period} stopped (fail closed). {$reason}");
        }

        $hasLineage = Schema::hasColumn('loan_books', 'lgd_applied_segment_key');
        $updated = 0;
        $sources = [];
        DB::transaction(function () use ($period, $outcomes, $pooled, $userId, $hasLineage, $runId, $startPeriod, $windowMonths, $minimumOption, $thinOption, $pooledOk, &$updated, &$sources) {
            if (collect($outcomes)->contains(fn ($o) => $o['status'] === 'parent')) {
                $sources['book'] = $this->engine->persist($pooled, $period, 'book', null, 'book', $userId)->id;
            }
            foreach ($outcomes as $o) {
                if ($o['status'] === 'own') {
                    $sources[$o['key']] = $this->engine->persist($o['measure'], $period, 'portfolio', $o['portfolio_id'], $o['key'], $userId)->id;
                }
                if ($o['from'] === null) {
                    continue; // a portfolio with no loan this period needs no LGD
                }
                $row = ['lgd_value' => round($o['lgd'], 8), 'collection_lgd' => round($o['lgd'], 8)];
                if ($hasLineage) {
                    $row += ['lgd_applied_segment_key' => $o['from'], 'lgd_source_id' => $sources[$o['from']]];
                }
                $updated += DB::table('loan_books')->where('reporting_period', $period)->where('loan_portfolio_id', $o['portfolio_id'])->update($row);
            }
            $this->record($runId, $period, $startPeriod, $windowMonths, $minimumOption, $thinOption, $outcomes, $pooled, $pooledOk, $sources, 'applied', null, $updated, $userId);
        });
        AuditLoggerService::log('LGD Segment Run', 'segment_parameter_runs', null, ['reporting_period' => $period, 'rows_affected' => $updated,
            'new_values' => ['run_id' => $runId, 'window' => $window, 'sources' => $sources, 'lgd' => array_map(fn ($o) => [$o['key'] => $o['lgd'], 'from' => $o['from']], $outcomes)], 'meta' => ['user' => $userId]]);

        return ['run_id' => $runId, 'window' => $window, 'updated' => $updated, 'sources' => $sources,
            'pooled' => ['cohort' => $pooled['cohort'], 'lgd' => $pooled['lgd'], 'ok' => $pooledOk],
            'portfolios' => array_map(fn ($o) => ['key' => $o['key'], 'label' => $o['label'], 'cohort' => $o['measure']['cohort'], 'own_lgd' => $o['measure']['cohort'] > 0 ? $o['measure']['lgd'] : null,
                'status' => $o['status'], 'from' => $o['from'], 'lgd' => $o['lgd'], 'loans' => $o['loans'], 'reason' => $o['reason']], $outcomes)];
    }

    private function record(string $runId, string $period, string $startPeriod, int $windowMonths, string $minimumOption, string $thinOption, array $outcomes, array $pooled, bool $pooledOk, array $sources, string $status, ?string $reason, int $updated, ?int $userId): void
    {
        if (! Schema::hasTable('segment_parameter_runs')) {
            return;
        }
        $months = max(1, (int) CarbonImmutable::parse($startPeriod . '-01')->diffInMonths(CarbonImmutable::parse($period . '-01')));
        DB::table('segment_parameter_runs')->insert(['run_id' => $runId, 'measure' => 'LGD', 'reporting_period' => $period, 'basis' => 'portfolio', 'window_start' => $startPeriod, 'window_end' => $period,
            'window_months' => $months, 'minimum_rule' => $minimumOption, 'thin_rule' => $thinOption, 'unverified_codes' => null, 'status' => $status, 'failure_reason' => $reason,
            'loans_updated' => $updated, 'user_id' => $userId, 'created_at' => now(), 'updated_at' => now()]);
        $row = fn (string $key, string $label, ?string $parent, ?int $pid, array $m, string $st, ?string $from, ?float $applied, int $loans, ?string $why) => [
            'run_id' => $runId, 'measure' => 'LGD', 'reporting_period' => $period, 'segment_key' => $key, 'segment_label' => mb_substr($label, 0, 200), 'parent_key' => $parent, 'portfolio_id' => $pid,
            'sector_code' => null, 'stage' => null, 'cohort_loans' => $m['cohort'], 'cohort_balance' => round($m['start_balance'], 2), 'default_loans' => $m['cohort'], 'default_balance' => round($m['start_balance'], 2),
            'observed_rate' => $m['cohort'] > 0 ? round($m['lgd'], 8) : null, 'annual_rate' => null, 'status' => $st, 'applied_from_key' => $from, 'applied_rate' => $applied === null ? null : round($applied, 8),
            'period_loans' => $loans, 'reason' => $why, 'matrix_id' => $sources[$key] ?? null, 'created_at' => now(), 'updated_at' => now()];
        $rows = [];
        $takes = 0;
        foreach ($outcomes as $o) {
            $rows[] = $row($o['key'], $o['label'], 'book', $o['portfolio_id'], $o['measure'], $o['status'], $o['from'], $o['lgd'], $o['loans'], $o['reason']);
            if ($o['status'] === 'parent') {
                $takes += $o['loans'];
            }
        }
        $rows[] = $row('book', 'Pooled book', null, null, $pooled, $pooledOk ? 'ok' : 'thin', null, null, $takes, $pooledOk ? null : "{$pooled['cohort']} Stage 3 loans at {$startPeriod}, below the minimum");
        DB::table('segment_parameter_results')->insert($rows);
    }
}
