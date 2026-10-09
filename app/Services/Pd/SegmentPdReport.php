<?php

namespace App\Services\Pd;

use App\Services\Rbm\RbmReturnService;
use App\Services\TransitionMatrixService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * The PD reports (9 October 2026): the observed one-year PD by portfolio,
 * by RBM sector and by RBM class, beside the PD the ECL applied and the
 * ECL itself. These are reports: the ECL keeps the governed segmentation
 * basis (PdSegmentationService); a report on another cut only measures.
 */
class SegmentPdReport
{
    private const EAD = 'COALESCE(carrying_amount,0) + COALESCE(commitments,0) * COALESCE(facility_utilisation_rate,1)';

    public function __construct(private PdSegmentationService $segments)
    {
    }

    /**
     * The observed PD of every segment of a basis, the governed minimum
     * applied to it, and the period's loans, PD applied, EAD and ECL.
     *
     * @return array{ok:bool,error:?string,window:?string,settings:?array,rows:list<array>}
     */
    public function bySegment(string $period, string $basis): array
    {
        try {
            $cfg = $this->segments->settings($period);
            $m = $this->segments->measure($period, $basis, 12, null, $cfg['unverified']);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'window' => null, 'settings' => null, 'rows' => []];
        }
        $stage = TransitionMatrixService::gradeExpression('loan_books', 'ifrs9stage_post_qualitative');
        $loans = DB::table('loan_books')->where('reporting_period', $period)->whereIn('loan_portfolio_id', $m['portfolios'])
            ->get(['id', 'loan_portfolio_id', 'industry_code', 'industry_type', 'pd_prefli', 'pd_post_fli', 'ecl_value', DB::raw('(' . self::EAD . ') as ead'), DB::raw("{$stage} as stage")]
                + (Schema::hasColumn('loan_books', 'pd_applied_segment_key') ? [10 => 'pd_applied_segment_key'] : []));
        $now = [];
        foreach ($loans as $l) {
            $key = PdSegmentationService::segmentKey($basis, $l, $cfg['unverified']);
            $st = (string) ($l->stage ?? '-');
            $c = &$now[$key][$st];
            $c ??= ['loans' => 0, 'ead' => 0.0, 'ecl' => 0.0, 'pd_sum' => 0.0, 'pd_n' => 0, 'sources' => []];
            $c['loans']++;
            $c['ead'] += (float) $l->ead;
            $c['ecl'] += (float) $l->ecl_value;
            if ($l->pd_prefli !== null) {
                $c['pd_sum'] += (float) $l->pd_prefli;
                $c['pd_n']++;
            }
            if (! empty($l->pd_applied_segment_key)) {
                $c['sources'][$l->pd_applied_segment_key] = true;
            }
            unset($c);
        }
        $keys = array_values(array_unique(array_merge(array_keys($now), array_filter(array_keys($m['segments']), fn ($k) => PdSegmentationService::kindOf($k) === ($basis === 'book' ? 'book' : $basis)))));
        sort($keys, SORT_NATURAL);
        $rows = [];
        foreach ($keys as $key) {
            $seg = $m['segments'][$key] ?? null;
            foreach (['1', '2', '3'] as $st) {
                $c = $seg['stages'][$st] ?? null;
                $n = $now[$key][$st] ?? null;
                if ($c === null && $n === null) {
                    continue;
                }
                $test = $st === '3' ? null : PdSegmentationService::test($seg, $st, $cfg['min_loans'], $cfg['min_defaults']);
                $rows[] = ['key' => $key, 'label' => $this->segments->label($key), 'stage' => $st, 'cohort' => (int) ($c['loans'] ?? 0), 'defaults' => (int) ($c['default_loans'] ?? 0),
                    'observed' => $c['annual'] ?? null, 'meets' => $test === null ? null : $test['ok'], 'reason' => $test['reason'] ?? null,
                    'loans' => (int) ($n['loans'] ?? 0), 'ead' => (float) ($n['ead'] ?? 0), 'ecl' => (float) ($n['ecl'] ?? 0),
                    'applied' => ($n['pd_n'] ?? 0) > 0 ? $n['pd_sum'] / $n['pd_n'] : null, 'sources' => array_keys($n['sources'] ?? [])];
            }
        }
        $book = $m['segments']['book'] ?? null;

        return ['ok' => true, 'error' => null, 'window' => "{$m['window_start']} to {$m['window_end']}", 'settings' => $cfg, 'rows' => $rows,
            'book' => $book ? ['1' => $book['stages']['1']['annual'] ?? null, '2' => $book['stages']['2']['annual'] ?? null] : null];
    }

    /**
     * The observed one-year PD by RBM class: the loans of each class at the
     * start of the window (classed by their days past due and term as the
     * directive does), how many of the performing ones (Stage 1 or 2) were
     * in Stage 3 at the end or had left the book unpaid, by count and by
     * balance; beside it the directive's minimum provision and, for the
     * period, the loans, EAD, ECL and IFRS 9 coverage of the class.
     *
     * @return array{ok:bool,error:?string,window:?string,rows:list<array>}
     */
    public function byRbmClass(string $period): array
    {
        $portfolios = $this->segments->scope($period);
        if ($portfolios === []) {
            return ['ok' => false, 'error' => "The loan book of {$period} holds no loan.", 'window' => null, 'rows' => []];
        }
        try {
            [$start, $end] = $this->segments->window($period, 12, $portfolios);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'window' => null, 'rows' => []];
        }
        $sg = TransitionMatrixService::gradeExpression('s', 'ifrs9stage_post_qualitative');
        $eg = TransitionMatrixService::gradeExpression('e', 'ifrs9stage_post_qualitative');
        $cohort = DB::table('loan_books as s')
            ->leftJoin('loan_books as e', fn ($j) => $j->on('s.contract_id', '=', 'e.contract_id')->where('e.reporting_period', '=', $end))
            ->where('s.reporting_period', $start)->whereIn('s.loan_portfolio_id', $portfolios)
            ->get(['s.contract_id', 's.overdue_days', 's.tenor', 's.carrying_amount', DB::raw("{$sg} as start_stage"), DB::raw("{$eg} as end_stage"), DB::raw('e.contract_id as end_id')]);
        $hasStatus = Schema::hasColumn('loan_books', 'contract_status');
        $classes = [];
        foreach (RbmReturnService::CLASSES as $c) {
            $classes[$c] = ['start' => 0, 'start_s3' => 0, 'perf' => 0, 'perf_bal' => 0.0, 'def' => 0, 'def_bal' => 0.0, 'stay' => 0,
                'now' => 0, 'ead' => 0.0, 'ecl' => 0.0, 'pd_sum' => 0.0, 'pd_n' => 0];
        }
        foreach ($cohort as $r) {
            if ($r->start_stage === null || $r->start_stage === '') {
                continue;
            }
            $class = RbmReturnService::classify((int) ($r->overdue_days ?? 0), (int) ($r->tenor ?? 0));
            $endStage = $r->end_id === null
                ? TransitionMatrixService::exitGrade('loan_books', 'contract_id', (string) $r->contract_id, $start, $end, '3', $hasStatus, true)
                : (string) ($r->end_stage ?? 'Paid');
            $x = &$classes[$class];
            $x['start']++;
            if ((string) $r->start_stage === '3') {
                $x['start_s3']++;
                if ($endStage === '3') {
                    $x['stay']++;
                }
            } else {
                $x['perf']++;
                $x['perf_bal'] += (float) $r->carrying_amount;
                if ($endStage === '3') {
                    $x['def']++;
                    $x['def_bal'] += (float) $r->carrying_amount;
                }
            }
            unset($x);
        }
        foreach (DB::table('loan_books')->where('reporting_period', $period)->whereIn('loan_portfolio_id', $portfolios)
            ->get(['overdue_days', 'tenor', 'pd_prefli', 'ecl_value', DB::raw('(' . self::EAD . ') as ead')]) as $l) {
            $x = &$classes[RbmReturnService::classify((int) ($l->overdue_days ?? 0), (int) ($l->tenor ?? 0))];
            $x['now']++;
            $x['ead'] += (float) $l->ead;
            $x['ecl'] += (float) $l->ecl_value;
            if ($l->pd_prefli !== null) {
                $x['pd_sum'] += (float) $l->pd_prefli;
                $x['pd_n']++;
            }
            unset($x);
        }
        $rows = [];
        foreach ($classes as $class => $x) {
            $rows[] = ['class' => $class, 'start_loans' => $x['start'], 'start_stage3' => $x['start_s3'], 'performing' => $x['perf'], 'defaults' => $x['def'],
                'pd_count' => $x['perf'] > 0 ? $x['def'] / $x['perf'] : null, 'pd_balance' => $x['perf_bal'] > 0 ? $x['def_bal'] / $x['perf_bal'] : null,
                'stayed_in_default' => $x['stay'], 'minimum' => RbmReturnService::MINIMUM[$class], 'loans' => $x['now'], 'ead' => $x['ead'], 'ecl' => $x['ecl'],
                'coverage' => $x['ead'] > 0 ? $x['ecl'] / $x['ead'] : null, 'applied_pd' => $x['pd_n'] > 0 ? $x['pd_sum'] / $x['pd_n'] : null];
        }

        return ['ok' => true, 'error' => null, 'window' => "{$start} to {$end}", 'rows' => $rows];
    }

    /** The loans of the period on an E-Banker code the governance holds apart, by product group. */
    public function unverifiedCodes(string $period, array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        return DB::table('loan_books')->where('reporting_period', $period)->whereIn('industry_code', $codes)
            ->groupBy('industry_code', 'product_group')->orderBy('product_group')
            ->selectRaw('industry_code, product_group, COUNT(*) n, SUM(' . self::EAD . ') ead, SUM(COALESCE(ecl_value,0)) ecl')->get()
            ->map(fn ($r) => (array) $r)->all();
    }
}
