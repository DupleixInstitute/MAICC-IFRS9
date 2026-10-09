<?php

namespace App\Services\Pd;

use App\Models\ExpectedCreditLoss;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The PD segments behind an ECL row (9 October 2026). Every time an
 * expected_credit_loss row is saved, by whichever route (the ECL screen,
 * ifrs9:recalculate-ecl, the bootstrap), it is stamped from the loans it
 * sums: the PD segment run they carry (only when they all carry the same
 * one; otherwise null, which the reports show as "mixed or none") and the
 * segments they belong to. So the lineage cannot go stale when the ECL is
 * recalculated outside the segment run.
 */
final class EclSegmentLineage
{
    public static function stamp(ExpectedCreditLoss $row): void
    {
        if (! Schema::hasColumn('expected_credit_loss', 'pd_segment_run_id') || ! Schema::hasColumn('loan_books', 'pd_segment_run_id')) {
            return;
        }
        $q = DB::table('loan_books')->where('reporting_period', $row->reporting_period)
            ->whereRaw('COALESCE(ifrs9stage_post_qualitative, calculated_ifrs9_stage, ifrs9stage_pre_qualitative) = ?', [(string) $row->ifrs9_stage]);
        if ($row->ecl_calculation_level === 'portfolio') {
            $q->where('loan_portfolio_id', $row->ecl_calculation_id);
        } elseif ($row->ecl_calculation_level === 'sector') {
            $q->where('industry_code', $row->ecl_calculation_code);
        }
        $stats = (clone $q)->selectRaw('COUNT(*) n, COUNT(pd_segment_run_id) with_run, COUNT(DISTINCT pd_segment_run_id) runs, MAX(pd_segment_run_id) run')->first();
        $keys = (clone $q)->whereNotNull('pd_segment_key')->distinct()->orderBy('pd_segment_key')->pluck('pd_segment_key')->all();
        $row->pd_segment_run_id = ($stats && (int) $stats->n > 0 && (int) $stats->with_run === (int) $stats->n && (int) $stats->runs === 1) ? $stats->run : null;
        $row->pd_segment_keys = $keys === [] ? null : implode(',', $keys);
    }
}
