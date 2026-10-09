<?php

namespace App\Services\Reports;

use App\Services\Fli\OverlayService;
use App\Services\Rbm\RbmReturnService;
use App\Services\Scenario\ScenarioSetService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * How a loan's ECL was built, and how the book's ECL builds up, read from
 * the saved loan book only (reporting, never writes).
 *
 * The undiscounted ECL engine (ExpectedCreditLossController::calculateECL)
 * measures each loan as
 *     EAD x PD over the horizon x LGD
 * where EAD is the carrying amount plus the undrawn commitment times the
 * utilisation rate, and the PD over the horizon comes from the 12-month PD
 * by stage (ScenarioSetService::stagePd: Stage 1 over the shorter of twelve
 * months and the remaining life, Stage 2 over the remaining life, Stage 3 is
 * 100 percent). The same rule is applied here to the PD before FLI and to
 * the PD after FLI.
 *
 * The ECL before FLI of a loan is its booked ECL scaled by the ratio of the
 * two horizon PDs, the convention the scenario-set sensitivity and the
 * overlay register already use, so the build-up adds back to the booked ECL
 * exactly. It is only shown when the booked ECL is on that basis: each
 * loan's booked ECL must equal EAD x PD over the horizon x LGD recomputed
 * from its saved PD after FLI. If the book was measured some other way (the
 * time-phased engine, or the PD changed after the last ECL run) the build-up
 * is withheld with the reason, never estimated.
 */
class EclBuildUpService
{
    /** The engine's EAD, as SQL. */
    public const EAD_SQL = 'COALESCE(carrying_amount,0) + COALESCE(commitments,0) * COALESCE(facility_utilisation_rate,1)';

    /** The stage the engine measured, as SQL. */
    public const STAGE_SQL = 'COALESCE(ifrs9stage_post_qualitative, calculated_ifrs9_stage, ifrs9stage_pre_qualitative)';

    /** The loan_books columns the lineage reads. */
    public const COLUMNS = ['id', 'contract_id', 'customer_name', 'loan_portfolio_id', 'product_group', 'product_code', 'industry_type', 'industry_code',
        'ifrs9stage_post_qualitative', 'calculated_ifrs9_stage', 'ifrs9stage_pre_qualitative', 'overdue_days', 'tenor', 'remaining_tenor',
        'carrying_amount', 'commitments', 'facility_utilisation_rate', 'pd_prefli', 'fli_adj', 'pd_post_fli', 'fli_route', 'fli_method',
        'fli_fit_id', 'fli_set_id', 'fli_overlay_ids', 'lgd_value', 'collection_lgd', 'customer_lgd', 'ecl_value', 'ecl_value_discounted',
        'ecl_discount_status'];

    /** Above this many groups the rest are shown as one "Other" line. */
    public const TOP = 6;

    public function __construct(private OverlayService $overlays)
    {
    }

    /**
     * One loan's lineage on the engine's undiscounted basis.
     *
     * @return array<string,mixed>
     */
    public static function lineage(object $l): array
    {
        $stage = (string) ($l->ifrs9stage_post_qualitative ?? $l->calculated_ifrs9_stage ?? $l->ifrs9stage_pre_qualitative ?? '');
        $carrying = (float) ($l->carrying_amount ?? 0);
        $undrawn = (float) ($l->commitments ?? 0);
        $utilisation = $l->facility_utilisation_rate === null ? 1.0 : (float) $l->facility_utilisation_rate;
        $ead = $carrying + $undrawn * $utilisation;

        // the engine's pre-FLI mode reads COALESCE(pd_prefli, pd_post_fli)
        $pre = $l->pd_prefli ?? $l->pd_post_fli;
        $post = $l->pd_post_fli;
        $tenor = $l->remaining_tenor === null || (float) $l->remaining_tenor < 1 ? null : (float) $l->remaining_tenor;
        $months = $tenor ?? 12.0;
        $shape = (object) ['stage' => $stage, 'remaining_tenor' => $l->remaining_tenor];
        $hPre = $pre === null && $stage !== '3' ? 0.0 : ScenarioSetService::stagePd($shape, (float) $pre);
        $hPost = $post === null && $stage !== '3' ? 0.0 : ScenarioSetService::stagePd($shape, (float) $post);

        $lgd = $l->lgd_value === null ? null : (float) $l->lgd_value;
        $booked = $l->ecl_value === null ? null : (float) $l->ecl_value;
        $recalcPost = $hPost * (float) $lgd * $ead;
        $recalcPre = $hPre * (float) $lgd * $ead;
        $tol = fn ($a) => 0.02 + 1e-9 * abs($a);
        $tiesPost = $booked !== null && abs($recalcPost - $booked) <= $tol($booked);
        $tiesPre = $booked !== null && abs($recalcPre - $booked) <= $tol($booked);

        // ECL before FLI: the booked ECL re-based to the PD before FLI
        $eclPre = null;
        if ($booked !== null && $tiesPost) {
            $eclPre = $hPost > 0 ? $booked * $hPre / $hPost : $recalcPre;
        }

        return [
            'stage' => $stage === '' ? null : (int) $stage,
            'carrying' => $carrying,
            'undrawn' => $undrawn,
            'utilisation' => $utilisation,
            'utilisation_recorded' => $l->facility_utilisation_rate !== null,
            'ead' => $ead,
            'pd_pre' => $l->pd_prefli === null ? null : (float) $l->pd_prefli,
            'fli_adj' => $l->fli_adj === null ? null : (float) $l->fli_adj,
            'pd_post' => $post === null ? null : (float) $post,
            'horizon' => $stage === '3' ? 'defaulted' : ($stage === '2' ? 'lifetime' : '12m'),
            'horizon_months' => $stage === '3' ? null : ($stage === '2' ? $months : min(12.0, $months)),
            'tenor_recorded' => $tenor !== null,
            'horizon_pd_pre' => $hPre,
            'horizon_pd_post' => $hPost,
            'lgd' => $lgd,
            'lgd_basis' => self::lgdBasis($l),
            'ecl' => $booked,
            'ecl_recalc' => round($recalcPost, 2),
            'ties' => $tiesPost,
            'ties_pre' => $tiesPre,
            'ecl_pre' => $eclPre === null ? null : round($eclPre, 2),
            'ecl_pre_raw' => $eclPre,
            'fli_effect' => $eclPre === null ? null : round($booked - $eclPre, 2),
            'coverage' => $ead > 0 && $booked !== null ? $booked / $ead * 100 : null,
            'rbm_class' => RbmReturnService::classify((int) ($l->overdue_days ?? 0), (int) ($l->tenor ?? 0)),
        ];
    }

    /** Which saved LGD the ECL used: the run copies its choice into lgd_value. */
    private static function lgdBasis(object $l): ?string
    {
        if ($l->lgd_value === null) {
            return null;
        }
        $v = (float) $l->lgd_value;
        $c = $l->collection_lgd === null ? null : (float) $l->collection_lgd;
        $u = $l->customer_lgd === null ? null : (float) $l->customer_lgd;
        if ($c !== null && abs($v - $c) < 1e-8) {
            return 'Collection LGD';
        }
        if ($u !== null && abs($v - $u) < 1e-8) {
            return 'Customer LGD';
        }
        if ($c !== null && $u !== null && abs($v - $c * $u) < 1e-8) {
            return 'Customer x collection LGD';
        }

        return 'As saved on the loan';
    }

    /** The loans of a period, optionally one portfolio, with the lineage columns. */
    public function loans(string $period, ?int $portfolioId = null): Collection
    {
        return DB::table('loan_books')->where('reporting_period', $period)
            ->when($portfolioId, fn ($q) => $q->where('loan_portfolio_id', $portfolioId))
            ->get(self::COLUMNS);
    }

    /**
     * The book's ECL build-up for a set of loans: ECL before FLI, the
     * forward-looking effect of the model, the manual overlays the route
     * applied (each overlay's own line from the register), and the booked ECL.
     *
     * @return array<string,mixed>
     */
    public function buildUp(Collection $loans, string $period): array
    {
        $n = $loans->count();
        $final = 0.0; $pre = 0.0; $calculated = 0; $tiePost = 0; $tiePre = 0; $timePhased = 0; $ead = 0.0;
        foreach ($loans as $l) {
            $x = self::lineage($l);
            $ead += $x['ead'];
            if ($x['ecl'] === null) {
                continue;
            }
            $calculated++;
            $final += $x['ecl'];
            $pre += $x['ecl_pre_raw'] ?? 0;
            $tiePost += $x['ties'] ? 1 : 0;
            $tiePre += $x['ties_pre'] ? 1 : 0;
            $timePhased += ($l->ecl_discount_status ?? null) === 'CALCULATED_TIME_PHASED' ? 1 : 0;
        }

        $out = ['period' => $period, 'loans' => $n, 'calculated' => $calculated, 'ead' => round($ead, 2), 'final' => round($final, 2),
            'available' => false, 'basis' => null, 'reason' => null, 'pre_fli' => null, 'fli_total' => null, 'fli_model' => null,
            'overlays' => null, 'overlay_lines' => [], 'lineage' => $this->runUnder($loans)];

        if ($calculated === 0) {
            $out['reason'] = 'No ECL has been calculated for these loans yet.';

            return $out;
        }
        if ($tiePost === $calculated) {
            $lines = $this->overlayLines($loans, $period);
            $overlayTotal = round(array_sum(array_column($lines, 'ecl')), 2);
            $out['available'] = true;
            $out['basis'] = 'post_fli';
            $out['pre_fli'] = round($pre, 2);
            $out['fli_total'] = round($final - $pre, 2);
            $out['overlays'] = $overlayTotal;
            $out['overlay_lines'] = $lines;
            $out['fli_model'] = round($final - $pre - $overlayTotal, 2);

            return $out;
        }
        if ($tiePre === $calculated) {
            // the run was made on the PD before FLI: nothing forward-looking is booked
            $out['available'] = true;
            $out['basis'] = 'pre_fli';
            $out['pre_fli'] = round($final, 2);
            $out['fli_total'] = 0.0;
            $out['fli_model'] = 0.0;
            $out['overlays'] = 0.0;
            $out['reason'] = 'The saved ECL was run on the PD before FLI, so no forward-looking effect is booked. Run the ECL on the PD after FLI to include it.';

            return $out;
        }
        $out['reason'] = $timePhased > 0
            ? 'The saved ECL was measured by the time-phased engine, which projects each loan month by month, so it cannot be split into before and after FLI from the saved PDs.'
            : number_format($calculated - $tiePost) . ' of ' . number_format($calculated) . ' loans no longer match their saved PD, LGD and EAD (the PD or the book changed after the last ECL run). Run the ECL again to see the build-up.';

        return $out;
    }

    /**
     * Each approved overlay the route actually applied to these loans (its id
     * is on the loan), with its ECL line from the register's own rule.
     *
     * @return list<array{overlay_id:int,scope:string,scope_value:?string,adjustment:float,reason:?string,loans:int,ecl:float}>
     */
    private function overlayLines(Collection $loans, string $period): array
    {
        $applied = [];
        foreach ($loans as $l) {
            foreach ((array) json_decode((string) ($l->fli_overlay_ids ?? ''), true) as $id) {
                $applied[(int) $id] = true;
            }
        }
        if ($applied === []) {
            return [];
        }
        $shaped = $loans->map(fn ($l) => (object) ['contract_id' => $l->contract_id, 'product_group' => $l->product_group,
            'stage' => (string) ($l->ifrs9stage_post_qualitative ?? $l->calculated_ifrs9_stage ?? $l->ifrs9stage_pre_qualitative ?? ''),
            'pd_prefli' => $l->pd_prefli ?? $l->pd_post_fli, 'pd_post_fli' => $l->pd_post_fli, 'ecl_value' => $l->ecl_value,
            'remaining_tenor' => $l->remaining_tenor])->filter(fn ($l) => $l->pd_prefli !== null)->values();
        $lines = [];
        foreach ($this->overlays->inForce($period) as $o) {
            if (! isset($applied[(int) $o->id])) {
                continue;
            }
            $line = $this->overlays->eclLine($o, $shaped);
            $lines[] = ['overlay_id' => (int) $o->id, 'scope' => $o->scope, 'scope_value' => $o->scope_value, 'adjustment' => (float) $o->adjustment,
                'reason' => $o->reason, 'loans' => $line['loans'], 'ecl' => $line['amount']];
        }

        return $lines;
    }

    /**
     * The FLI route, method, fit and scenario set the loans ran under, with
     * a count of each (normally one of each for a period).
     *
     * @return list<array<string,mixed>>
     */
    public function runUnder(Collection $loans): array
    {
        $groups = $loans->groupBy(fn ($l) => implode('|', [$l->fli_route, $l->fli_method, $l->fli_fit_id, $l->fli_set_id]));
        $fits = $this->fits($loans->pluck('fli_fit_id')->filter()->unique()->all());
        $sets = $this->sets($loans->pluck('fli_set_id')->filter()->unique()->all());

        return $groups->map(function ($g) use ($fits, $sets) {
            $f = $g->first();

            return ['route' => $f->fli_route, 'method' => $f->fli_method, 'loans' => $g->count(),
                'fit' => $f->fli_fit_id ? ($fits[(int) $f->fli_fit_id] ?? ['id' => (int) $f->fli_fit_id]) : null,
                'set' => $f->fli_set_id ? ($sets[(int) $f->fli_set_id] ?? ['id' => (int) $f->fli_set_id]) : null];
        })->sortByDesc('loans')->values()->all();
    }

    /** @return array<int,array<string,mixed>> */
    public function fits(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('fli_fits as f')->leftJoin('fli_relationships as r', 'r.id', '=', 'f.fli_relationship_id')->whereIn('f.id', $ids)
            ->get(['f.id', 'f.approval_status', 'f.r_squared', 'f.approver_label', 'r.statistic_code', 'r.proxy_code', 'r.lag_months'])
            ->mapWithKeys(fn ($f) => [(int) $f->id => ['id' => (int) $f->id, 'status' => $f->approval_status, 'r_squared' => $f->r_squared === null ? null : (float) $f->r_squared,
                'relationship' => $f->statistic_code ? "{$f->statistic_code} to {$f->proxy_code}" . ($f->lag_months !== null ? ", lag {$f->lag_months} months" : '') : null,
                'approver_label' => $f->approver_label]])->all();
    }

    /** @return array<int,array<string,mixed>> */
    public function sets(array $ids): array
    {
        if ($ids === [] || ! DB::getSchemaBuilder()->hasTable('governed_scenario_sets')) {
            return [];
        }

        return DB::table('governed_scenario_sets')->whereIn('id', $ids)->get(['id', 'name', 'version', 'status', 'reporting_period'])
            ->mapWithKeys(fn ($s) => [(int) $s->id => ['id' => (int) $s->id, 'name' => $s->name, 'version' => (int) $s->version, 'status' => $s->status, 'period' => $s->reporting_period]])->all();
    }

    /** A sector label without the import's numbering ("5-5.  Construction" reads "Construction"). */
    public static function sectorLabel(?string $type): string
    {
        $t = trim((string) $type);
        if ($t === '') {
            return 'Not recorded';
        }

        return trim(preg_replace('/^\d+\s*-\s*\d+\.\s*/', '', $t)) ?: $t;
    }

    /**
     * ECL and exposure by product group, sector or RBM class. Product group
     * and sector show the largest six by ECL and the rest as one line; RBM
     * shows the five classes in the directive's order.
     *
     * @return array{rows:list<array<string,mixed>>,total:array<string,float|int>}
     */
    public function breakdown(Collection $loans, string $by): array
    {
        $acc = [];
        foreach ($loans as $l) {
            $x = self::lineage($l);
            $key = match ($by) {
                'product' => trim((string) $l->product_group) !== '' ? (string) $l->product_group : 'Not recorded',
                'sector' => self::sectorLabel($l->industry_type),
                default => $x['rbm_class'],
            };
            $acc[$key] ??= ['label' => $key, 'loans' => 0, 'carrying' => 0.0, 'ead' => 0.0, 'ecl' => 0.0];
            $acc[$key]['loans']++;
            $acc[$key]['carrying'] += $x['carrying'];
            $acc[$key]['ead'] += $x['ead'];
            $acc[$key]['ecl'] += (float) ($x['ecl'] ?? 0);
        }

        if ($by === 'rbm') {
            $rows = [];
            foreach (RbmReturnService::CLASSES as $c) {
                $rows[] = ($acc[$c] ?? ['label' => $c, 'loans' => 0, 'carrying' => 0.0, 'ead' => 0.0, 'ecl' => 0.0])
                    + ['minimum' => RbmReturnService::MINIMUM[$c] ?? null];
            }
        } else {
            $rows = array_values($acc);
            usort($rows, fn ($a, $b) => $b['ecl'] <=> $a['ecl'] ?: $b['ead'] <=> $a['ead']);
            if (count($rows) > self::TOP + 1) {
                $rest = array_slice($rows, self::TOP);
                $rows = array_slice($rows, 0, self::TOP);
                $rows[] = ['label' => 'Other (' . count($rest) . ')', 'other' => true,
                    'loans' => array_sum(array_column($rest, 'loans')), 'carrying' => array_sum(array_column($rest, 'carrying')),
                    'ead' => array_sum(array_column($rest, 'ead')), 'ecl' => array_sum(array_column($rest, 'ecl'))];
            }
        }
        $total = ['loans' => array_sum(array_column($rows, 'loans')), 'carrying' => round(array_sum(array_column($rows, 'carrying')), 2),
            'ead' => round(array_sum(array_column($rows, 'ead')), 2), 'ecl' => round(array_sum(array_column($rows, 'ecl')), 2)];
        $rows = array_map(fn ($r) => array_merge($r, ['carrying' => round($r['carrying'], 2), 'ead' => round($r['ead'], 2), 'ecl' => round($r['ecl'], 2),
            'coverage' => $r['ead'] > 0 ? $r['ecl'] / $r['ead'] * 100 : null,
            'share' => $total['ecl'] > 0 ? $r['ecl'] / $total['ecl'] * 100 : null]), $rows);

        return ['rows' => $rows, 'total' => $total];
    }
}
