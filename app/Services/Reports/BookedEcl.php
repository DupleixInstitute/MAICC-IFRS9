<?php

namespace App\Services\Reports;

use App\Services\MegaFarm\MegaFarmEclService;
use Illuminate\Support\Facades\DB;

/**
 * The loss allowance every report carries: the booked allowance, which is the
 * expected credit loss discounted at the loan's original EIR (IFRS 9 5.5.17,
 * B5.5.44; spec v4 6.10.1; the month-end runs `ifrs9:segment-ecl
 * --discounting=discounted`), never the undiscounted reporting-date measure
 * where a discounted one exists. Built 14 October 2026.
 *
 * Each loan falls on one basis:
 *  - discounted: the time-phased engine projected and discounted it at the
 *    original EIR (ecl_discount_status CALCULATED_TIME_PHASED); the allowance
 *    is ecl_value_discounted;
 *  - programme: a Mega Farm programme loan (GLs 1050301 to 1050307), measured
 *    by the programme method at MAIIC's share and outside the EIR engine
 *    (decision D30); the allowance is ecl_value as the programme run wrote it;
 *  - undiscounted: an ECL exists but no discounted figure does (no locked EIR,
 *    the projection could not run, or only a proxy rate was applied); there is
 *    no booked figure, so the report shows the undiscounted ECL in its place
 *    and says so with the count and the amount, never silently;
 *  - nil: an ECL of nil with no discounted figure (nothing to discount);
 *  - none: no ECL has been calculated for the loan.
 *
 * The reconciliation from the reporting-date measure (ecl_value, EAD x PD x
 * LGD) to the allowance shown is: plus the time-phased projection's excess
 * over that measure, less the effect of discounting at the EIR, both on the
 * discounted loans only. It ties by construction, loan by loan.
 */
final class BookedEcl
{
    public const DISCOUNTED_STATUS = 'CALCULATED_TIME_PHASED';

    public const BASES = ['discounted', 'programme', 'undiscounted', 'nil', 'none'];

    public const LABELS = [
        'discounted' => 'Discounted at the original EIR (booked)',
        'programme' => 'Mega Farm programme measure (D30, outside the EIR)',
        'undiscounted' => 'No discounted figure: undiscounted ECL shown',
        'nil' => 'Nil ECL, nothing to discount',
        'none' => 'No ECL calculated',
    ];

    /** sql('') as a constant, for queries built in constant expressions. */
    public const SQL = "(CASE WHEN ecl_discount_status = 'CALCULATED_TIME_PHASED' AND ecl_value_discounted IS NOT NULL THEN ecl_value_discounted ELSE COALESCE(ecl_value, 0) END)";

    /** The allowance shown for one loan row; $a is the table alias with its dot ('lb.') or ''. */
    public static function sql(string $a = ''): string
    {
        return "(CASE WHEN {$a}ecl_discount_status = '" . self::DISCOUNTED_STATUS . "' AND {$a}ecl_value_discounted IS NOT NULL"
            . " THEN {$a}ecl_value_discounted ELSE COALESCE({$a}ecl_value, 0) END)";
    }

    /** The basis of one loan row, one of BASES. */
    public static function basisSql(string $a = ''): string
    {
        $programme = "'" . implode("','", MegaFarmEclService::GLS) . "'";

        return "(CASE WHEN {$a}ecl_discount_status = '" . self::DISCOUNTED_STATUS . "' AND {$a}ecl_value_discounted IS NOT NULL THEN 'discounted'"
            . " WHEN {$a}ecl_value IS NULL THEN 'none'"
            . " WHEN {$a}product_code IN ({$programme}) THEN 'programme'"
            . " WHEN {$a}ecl_value = 0 THEN 'nil'"
            . " ELSE 'undiscounted' END)";
    }

    /**
     * The basis of a month's allowance, overall and by stage, with the
     * reconciliation from the undiscounted measure and the message a report
     * prints. $scope narrows the loan_books query (a portfolio, a product).
     *
     * @return array{period:string,status:string,message:?string,bases:array,stages:array,reconciliation:array,reasons:array}
     */
    public static function summary(string $period, ?callable $scope = null): array
    {
        $stage = 'ifrs9stage_post_qualitative';
        $q = DB::table('loan_books')->where('reporting_period', $period);
        if ($scope) {
            $scope($q);
        }
        // per loan first, grouped outside, so a strict GROUP BY accepts the derived basis
        $perLoan = (clone $q)->selectRaw(self::basisSql() . " AS basis, {$stage} AS stage, " . self::sql() . ' AS shown, COALESCE(ecl_value,0) AS undiscounted,'
            . " CASE WHEN ecl_discount_status = '" . self::DISCOUNTED_STATUS . "' AND ecl_value_discounted IS NOT NULL THEN ecl_value_discounted + COALESCE(ecl_discounting_effect,0) - COALESCE(ecl_value,0) ELSE 0 END AS projection,"
            . " CASE WHEN ecl_discount_status = '" . self::DISCOUNTED_STATUS . "' AND ecl_value_discounted IS NOT NULL THEN COALESCE(ecl_discounting_effect,0) ELSE 0 END AS effect,"
            . " COALESCE(ecl_discount_status, 'NOT_CALCULATED') AS status");
        $rows = DB::query()->fromSub($perLoan, 'b')
            ->selectRaw('basis, stage, COUNT(*) AS loans, SUM(shown) AS shown, SUM(undiscounted) AS undiscounted, SUM(projection) AS projection, SUM(effect) AS effect')
            ->groupBy('basis', 'stage')->get();
        $reasons = DB::query()->fromSub($perLoan, 'b')->where('basis', 'undiscounted')
            ->selectRaw('status, COUNT(*) AS loans, SUM(undiscounted) AS amount')
            ->groupBy('status')->get()
            ->map(fn ($r) => ['status' => $r->status, 'reason' => self::reason($r->status), 'loans' => (int) $r->loans, 'amount' => round((float) $r->amount, 2)])->all();

        $blank = fn () => ['loans' => 0, 'shown' => 0.0, 'undiscounted' => 0.0, 'projection' => 0.0, 'effect' => 0.0];
        $bases = array_fill_keys(self::BASES, null);
        $bases = array_map($blank, $bases);
        $stages = [];
        foreach ($rows as $r) {
            $s = in_array((string) $r->stage, ['1', '2', '3'], true) ? (int) $r->stage : 'unstaged';
            $stages[$s] ??= array_map($blank, array_fill_keys(self::BASES, null));
            foreach (['loans', 'shown', 'undiscounted', 'projection', 'effect'] as $k) {
                $v = $k === 'loans' ? (int) $r->{$k} : (float) $r->{$k};
                $bases[$r->basis][$k] += $v;
                $stages[$s][$r->basis][$k] += $v;
            }
        }
        $sum = fn (array $set, string $k) => array_sum(array_column($set, $k));
        $reconcile = fn (array $set) => [
            'undiscounted' => round($sum($set, 'undiscounted'), 2),
            'projection' => round($sum($set, 'projection'), 2),
            'discounting_effect' => round(-$sum($set, 'effect'), 2),
            'shown' => round($sum($set, 'shown'), 2),
            'booked' => round($set['discounted']['shown'] + $set['programme']['shown'], 2),
            'not_booked' => round($set['undiscounted']['shown'], 2),
            'not_booked_loans' => $set['undiscounted']['loans'],
        ];
        $withEcl = $sum($bases, 'loans') - $bases['none']['loans'];
        $status = $withEcl === 0 ? 'none' : ($bases['undiscounted']['loans'] > 0 ? 'mixed' : 'booked');
        $recon = $reconcile($bases);
        $stageRecon = [];
        foreach ($stages as $s => $set) {
            $stageRecon[$s] = $reconcile($set);
        }
        ksort($stageRecon);

        return [
            'period' => $period,
            'status' => $status,
            'message' => self::message($status, $recon, $bases, $reasons),
            'bases' => array_map(fn ($b) => array_map(fn ($v) => is_float($v) ? round($v, 2) : $v, $b), $bases),
            'stages' => $stageRecon,
            'reconciliation' => $recon,
            'reasons' => $reasons,
        ];
    }

    /** Why a loan with an ECL has no discounted figure, from its discount status. */
    public static function reason(?string $status): string
    {
        return match ($status) {
            'TIME_PHASED_UNRESOLVED' => 'the time-phased projection could not run (for most, no locked original EIR); the run names each loan',
            'CALCULATED_HORIZON_PROXY', 'FLOATING_RATE_PROXY' => 'discounted only on a proxy horizon or rate, not the time-phased projection at the original EIR, so not booked',
            'EIR_UNAVAILABLE' => 'no original EIR to discount at',
            'HORIZON_UNAVAILABLE' => 'no maturity horizon to discount over',
            'NOT_REQUESTED' => 'the ECL run for the month was undiscounted',
            default => 'no discounted ECL was calculated for the month',
        };
    }

    private static function message(string $status, array $recon, array $bases, array $reasons): ?string
    {
        $m = fn (float $v) => number_format($v, 2);
        if ($status === 'none') {
            return 'No ECL has been calculated for the month.';
        }
        $parts = [];
        if ($bases['discounted']['loans'] > 0) {
            $parts[] = number_format($bases['discounted']['loans']) . ' loans discounted at the original EIR (' . $m($bases['discounted']['shown']) . ')';
        }
        if ($bases['programme']['loans'] > 0) {
            $parts[] = number_format($bases['programme']['loans']) . ' Mega Farm programme loans at the programme measure (' . $m($bases['programme']['shown']) . ')';
        }
        $text = 'The allowance shown is the booked allowance: ' . implode('; ', $parts) . '.';
        if ($status === 'mixed') {
            $why = implode('; ', array_map(fn ($r) => number_format($r['loans']) . ' because ' . $r['reason'], $reasons));
            $text .= ' ' . number_format($recon['not_booked_loans']) . ' loans have no discounted allowance (' . $why . '): they are shown at their undiscounted ECL of '
                . $m($recon['not_booked']) . ', which is not on the booked basis. The booked allowance on the other loans is ' . $m($recon['booked']) . '.';
        }

        return $text;
    }

    /**
     * The basis as a report section in the hub's shape (heading, note, rows):
     * the loans and amount on each basis, then the reconciliation from the
     * undiscounted measure to the allowance shown.
     */
    public static function section(array $summary, callable $money): array
    {
        $rows = [];
        foreach (self::BASES as $b) {
            $v = $summary['bases'][$b];
            if ($v['loans'] === 0) {
                continue;
            }
            $rows[] = [self::LABELS[$b], number_format($v['loans']), $b === 'none' ? '-' : $money($v['shown'])];
        }
        $r = $summary['reconciliation'];
        $rows[] = ['Allowance shown', number_format(array_sum(array_column($summary['bases'], 'loans'))), $money($r['shown'])];
        $rows[] = ['Reconciliation: ECL at the reporting date, undiscounted (EAD x PD x LGD)', '', $money($r['undiscounted'])];
        $rows[] = ['Add: lifetime time-phased projection over the reporting-date measure (discounted loans)', '', $money($r['projection'])];
        $rows[] = ['Less: effect of discounting at the original EIR (discounted loans)', '', $money($r['discounting_effect'])];
        $rows[] = ['Allowance shown', '', $money($r['shown'])];

        return [
            'heading' => 'Basis of the loss allowance' . ($summary['status'] === 'mixed' ? ': not wholly booked' : ''),
            'note' => $summary['message'],
            'columns' => ['Basis', 'Loans', 'Amount'],
            'align' => ['l', 'r', 'r'],
            'rows' => $rows,
        ];
    }
}
