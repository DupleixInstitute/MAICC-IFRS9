<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

/**
 * IFRS 9 reporting suite (MAIIC).
 *
 * One normalised payload shape powers a tabbed in-app hub and a matching
 * PDF for every report:
 *   [ key, title, subtitle, category, company, generated_at, period,
 *     periods[], controls?, kpis:[{label,value,tone}],
 *     sections:[{heading,columns,rows,align}] ]
 *
 * Only reporting periods with a calculated ECL are offered.
 */
class Ifrs9ReportsController extends Controller
{
    private const EAD_SQL = 'COALESCE(carrying_amount,0) + COALESCE(commitments,0) * COALESCE(facility_utilisation_rate,1)';

    // key => [title, subtitle, category]. Full catalogue: 30 reports covering
    // the contract Schedule 1 families (ECL/staging, movement, sector,
    // collateral, EIR revenue, RBM prudential, disclosure, governance) plus
    // interactive Sensitivity; EWS & AI under Analytics.
    private array $catalogue = [
        // Core ECL
        'executive'            => ['Executive Summary',             'One-page ECL position: KPIs, stage split, portfolios, exposures & data quality', 'Core ECL'],
        'ecl'                  => ['ECL Summary by Stage',          'Total exposure, PD, LGD & ECL by Stage 1/2/3', 'Core ECL'],
        'portfolio-trend'      => ['Portfolio ECL Trend',           'ECL & coverage over time, split by portfolio', 'Core ECL'],
        'sector-ecl'           => ['ECL by Sector',                 'Exposure, PD, LGD & ECL by RBM economic sector', 'Core ECL'],
        'product-group-ecl'    => ['ECL by Product Group',          'Exposure & ECL by lending product group', 'Core ECL'],
        'grade-ecl'            => ['ECL by Internal Grade',          'DFI internal risk-grade scale: exposure, PD, LGD & ECL by grade', 'Core ECL'],
        'account-ecl'          => ['Account-Level ECL Calculation', 'Loan-by-loan EAD x PD x LGD calculation trail', 'Core ECL'],
        'stage-allocation'     => ['Stage Allocation',              'How every exposure is classified into Stage 1/2/3', 'Core ECL'],
        // Staging & Movement
        'sicr-trigger'         => ['SICR Trigger',                  'Significant Increase in Credit Risk triggers', 'Staging & Movement'],
        'stage-migration'      => ['Stage Migration',               'Stage movement vs the prior reporting period', 'Staging & Movement'],
        'ecl-reconciliation'   => ['Opening to Closing ECL Reconciliation', 'Opening to closing ECL bridge', 'Staging & Movement'],
        'gross-movement'       => ['Gross Carrying Amount Movement', 'Opening, disbursements, repayments, closing', 'Staging & Movement'],
        'ecl-charge'           => ['ECL Charge / Release',          'Impairment charge or release to profit or loss', 'Staging & Movement'],
        // Model Components
        'pd-report'            => ['PD Report',                     '12-month and lifetime probability of default', 'Model Components'],
        'lgd-collateral'       => ['LGD & Collateral',              'Recovery, collateral cover and net unsecured exposure', 'Model Components'],
        'crm-agri'             => ['Credit Risk Mitigation (Agri)',  'Off-take, warehouse-receipt, group-guarantee & AIP cover vs LGD', 'Model Components'],
        'ead-report'           => ['EAD & Off-Balance Sheet',       'Exposure at default incl. undrawn commitments / CCF', 'Model Components'],
        // Forward-Looking
        'macro-scenario'       => ['Macro Scenario & Forward-Looking', 'Macro assumptions and economic scenarios', 'Forward-Looking'],
        'scenario-ecl'         => ['Scenario-Weighted ECL',         'Probability-weighted ECL across scenarios', 'Forward-Looking'],
        // RBM Prudential
        'rbm-classification'   => ['RBM Asset Classification',      'Prudential classification (RBM Directive 2018)', 'RBM Prudential'],
        'ifrs9-vs-rbm'         => ['IFRS 9 Stage vs RBM Mapping',   'Reconciliation of IFRS 9 stages to RBM classes', 'RBM Prudential'],
        'npl-arrears'          => ['NPL & Arrears',                 'Non-performing loans and arrears ageing', 'RBM Prudential'],
        'provision-comparison' => ['Provision Comparison',          'IFRS 9 ECL vs RBM prudential provision & shortfall', 'RBM Prudential'],
        'concentration'        => ['Concentration & Large Exposures', 'Single-name & portfolio concentration (HHI) and large exposures', 'RBM Prudential'],
        'coop-linkage'         => ['Cooperative & Anchor Linkage',   'Correlated (contagion) exposure by cooperative / anchor buyer', 'RBM Prudential'],
        // Disclosure & Audit
        'fs-disclosure'        => ['Financial Statement Disclosure', 'IFRS 9 note tables for the annual report', 'Disclosure & Audit'],
        // The user-action audit trail lives at Administration > Audit Trail;
        // this report is the data-integrity view.
        'data-quality'         => ['Data Quality & Exceptions',     'Data integrity, overrides and exception checks', 'Disclosure & Audit'],
        // Stress testing (interactive)
        // Analytics (separate from the 19 reports)
        'ews'                  => ['Early Warning System',          'Forward risk signals & watchlist before default', 'Analytics'],
        'ai-narrative'         => ['AI Executive Commentary',       'Auto-generated narrative on the ECL position', 'Analytics'],
    ];

    /* ===================================================================== */
    /*  Hub                                                                  */
    /* ===================================================================== */

    public function index()
    {
        $order = ['Core ECL', 'Staging & Movement', 'Model Components', 'Forward-Looking',
                  'RBM Prudential', 'Disclosure & Audit', 'Analytics'];

        $grouped = collect($this->catalogue)
            ->map(fn ($v, $k) => ['key' => $k, 'title' => $v[0], 'subtitle' => $v[1], 'category' => $v[2] ?? 'Other'])
            ->groupBy('category')
            ->map(fn ($items) => $items->values());

        $categories = collect($order)
            ->filter(fn ($c) => $grouped->has($c))
            ->map(fn ($c) => ['name' => $c, 'reports' => $grouped[$c]])
            ->values();

        return Inertia::render('Reports/Ifrs9/Index', [
            'categories' => $categories,
            'periods'    => $this->periods(),
            'company'    => $this->company(),
        ]);
    }

    /* ===================================================================== */
    /*  Core ECL                                                             */
    /* ===================================================================== */

    public function ecl(Request $request)
    {
        $period = $this->period($request);

        $rows = DB::table('expected_credit_loss')
            ->where('reporting_period', $period)
            ->orderBy('ecl_calculation_level')->orderBy('ifrs9_stage')
            ->get()
            ->map(fn ($r) => [
                ucfirst($r->ecl_calculation_level ?? '—'),
                'Stage ' . $r->ifrs9_stage,
                number_format($r->total_loans),
                $this->money($r->total_ead),
                $this->num($r->pd_value_used, 6),
                $this->num($r->lgd_value_used, 6),
                $this->money($r->total_ecl),
                $this->pct($r->total_ead ? $r->total_ecl / $r->total_ead : 0),
            ])->all();

        return $this->respond(['key' => 'ecl', 'period' => $period,
            'kpis' => $this->totalsKpis($period),
            'sections' => [[
                'heading' => 'Expected Credit Loss by Stage',
                'columns' => ['Level', 'Stage', 'Loans', 'EAD', 'Avg PD', 'Avg LGD', 'ECL', 'Coverage %'],
                'align' => ['l', 'l', 'r', 'r', 'r', 'r', 'r', 'r'],
                'rows' => $rows,
            ]]]);
    }

    public function accountEcl(Request $request)
    {
        $period = $this->period($request);
        $rows = DB::table('loan_books')
            ->selectRaw("contract_id, customer_name, ifrs9stage_pre_qualitative stage,
                " . self::EAD_SQL . " ead, COALESCE(pd_post_fli,pd_prefli) pd,
                COALESCE(lgd_value,0) lgd, COALESCE(ecl_value,0) ecl")
            ->where('reporting_period', $period)
            ->orderByDesc(DB::raw(self::EAD_SQL))->limit(200)->get()
            ->map(fn ($r) => [$r->contract_id, $r->customer_name ?: '(Unnamed)', 'Stage ' . $r->stage,
                $this->money($r->ead), $this->num($r->pd, 6), $this->num($r->lgd, 6),
                $this->money($r->ead * $r->pd * $r->lgd), $this->money($r->ecl)])->all();

        return $this->respond(['key' => 'account-ecl', 'period' => $period,
            'subtitle' => 'Loan-by-loan ECL = EAD x PD x LGD (top 200 by exposure)',
            'kpis' => $this->totalsKpis($period),
            'sections' => [[
                'heading' => 'Account-Level ECL Calculation Trail',
                'columns' => ['Contract', 'Client', 'Stage', 'EAD', 'PD', 'LGD', 'EAD x PD x LGD', 'Booked ECL'],
                'align' => ['l', 'l', 'l', 'r', 'r', 'r', 'r', 'r'],
                'rows' => $rows,
            ]]]);
    }

    public function stageAllocation(Request $request)
    {
        $period = $this->period($request);
        $rows = DB::table('loan_books')
            ->selectRaw("ifrs9stage_pre_qualitative stage, COUNT(*) loans,
                SUM(" . self::EAD_SQL . ") ead, SUM(COALESCE(ecl_value,0)) ecl,
                SUM(CASE WHEN COALESCE(overdue_days,0)=0 THEN 1 ELSE 0 END) current_n,
                SUM(CASE WHEN COALESCE(overdue_days,0)>0 THEN 1 ELSE 0 END) arrears_n")
            ->where('reporting_period', $period)
            ->groupBy('ifrs9stage_pre_qualitative')->orderBy('ifrs9stage_pre_qualitative')->get()
            ->map(fn ($r) => ['Stage ' . $r->stage, number_format($r->loans),
                number_format($r->current_n), number_format($r->arrears_n),
                $this->money($r->ead), $this->money($r->ecl),
                $this->pct($r->ead ? $r->ecl / $r->ead : 0)])->all();

        return $this->respond(['key' => 'stage-allocation', 'period' => $period,
            'kpis' => $this->totalsKpis($period),
            'sections' => [[
                'heading' => 'Exposure Classification by IFRS 9 Stage',
                'columns' => ['Stage', 'Loans', 'Current', 'In Arrears', 'Exposure (EAD)', 'ECL', 'Coverage %'],
                'align' => ['l', 'r', 'r', 'r', 'r', 'r', 'r'],
                'rows' => $rows,
            ]]]);
    }

    /* ===================================================================== */
    /*  Staging & Movement                                                   */
    /* ===================================================================== */

    public function sicrTrigger(Request $request)
    {
        $period = $this->period($request);
        $byTrigger = DB::table('loan_books')
            ->selectRaw("CASE
                    WHEN COALESCE(sicr,0)=1 THEN 'SICR flag set'
                    WHEN COALESCE(overdue_days,0) BETWEEN 31 AND 90 THEN 'Arrears 31-90 DPD'
                    WHEN COALESCE(overdue_days,0) > 0 THEN 'Arrears 1-30 DPD'
                    ELSE 'Other / qualitative' END trig,
                COUNT(*) loans, SUM(" . self::EAD_SQL . ") ead, SUM(COALESCE(ecl_value,0)) ecl")
            ->where('reporting_period', $period)
            ->where('ifrs9stage_pre_qualitative', 2)
            ->groupBy('trig')->get()
            ->map(fn ($r) => [$r->trig, number_format($r->loans), $this->money($r->ead), $this->money($r->ecl)])->all();

        $s2 = DB::table('loan_books')->where('reporting_period', $period)
            ->where('ifrs9stage_pre_qualitative', 2)->count();

        return $this->respond(['key' => 'sicr-trigger', 'period' => $period,
            'subtitle' => 'Why exposures moved to Stage 2 (Significant Increase in Credit Risk)',
            'kpis' => [
                ['label' => 'Stage 2 Loans', 'value' => number_format($s2), 'tone' => 'amber'],
                ['label' => 'Period', 'value' => $period, 'tone' => 'maiic'],
            ],
            'sections' => [[
                'heading' => 'Stage 2 by SICR Trigger',
                'columns' => ['Trigger', 'Loans', 'Exposure (EAD)', 'ECL'],
                'align' => ['l', 'r', 'r', 'r'],
                'rows' => $byTrigger,
            ]]]);
    }

    public function stageMigration(Request $request)
    {
        $period = $this->period($request);
        $prev = $this->previousPeriod($period);

        if (! $prev) {
            return $this->respond(['key' => 'stage-migration', 'period' => $period,
                'subtitle' => 'No prior ECL-calculated period to compare against.', 'sections' => []]);
        }

        $rows = DB::table('loan_books as c')
            ->join('loan_books as p', function ($j) use ($prev) {
                $j->on('c.contract_id', '=', 'p.contract_id')->where('p.reporting_period', '=', $prev);
            })
            ->where('c.reporting_period', $period)
            ->selectRaw("p.ifrs9stage_pre_qualitative from_s, c.ifrs9stage_pre_qualitative to_s,
                COUNT(*) n")
            ->groupBy('p.ifrs9stage_pre_qualitative', 'c.ifrs9stage_pre_qualitative')->get();

        $states = ['1', '2', '3'];
        $grid = [];
        foreach ($states as $f) {
            $row = ['Stage ' . $f];
            foreach ($states as $t) {
                $cell = $rows->first(fn ($x) => (string) $x->from_s === $f && (string) $x->to_s === $t);
                $row[] = $cell ? number_format($cell->n) : '0';
            }
            $grid[] = $row;
        }

        return $this->respond(['key' => 'stage-migration', 'period' => $period,
            'subtitle' => "Movement {$prev} -> {$period} (loan count)",
            'kpis' => $this->totalsKpis($period),
            'sections' => [[
                'heading' => 'Stage Migration Matrix (count)',
                'columns' => ['From \\ To', 'Stage 1', 'Stage 2', 'Stage 3'],
                'align' => ['l', 'r', 'r', 'r'],
                'rows' => $grid,
            ]]]);
    }

    public function eclReconciliation(Request $request)
    {
        $period = $this->period($request);
        $prev = $this->previousPeriod($period);
        $closing = (float) ($this->periodTotals($period)->ecl ?? 0);
        $opening = $prev ? (float) ($this->periodTotals($prev)->ecl ?? 0) : 0.0;
        $movement = $closing - $opening;

        $byStage = DB::table('loan_books')
            ->selectRaw("ifrs9stage_pre_qualitative s, SUM(COALESCE(ecl_value,0)) ecl")
            ->where('reporting_period', $period)->groupBy('ifrs9stage_pre_qualitative')->pluck('ecl', 's');

        return $this->respond(['key' => 'ecl-reconciliation', 'period' => $period,
            'subtitle' => $prev ? "Opening {$prev} -> Closing {$period}" : 'No prior period — closing only',
            'kpis' => [
                ['label' => 'Opening ECL', 'value' => $this->money($opening), 'tone' => 'maiic'],
                ['label' => 'Net Movement', 'value' => $this->money($movement), 'tone' => $movement >= 0 ? 'rose' : 'emerald'],
                ['label' => 'Closing ECL', 'value' => $this->money($closing), 'tone' => 'rose'],
            ],
            'sections' => [[
                'heading' => 'ECL Reconciliation',
                'columns' => ['Particulars', 'Amount'],
                'align' => ['l', 'r'],
                'rows' => [
                    ['Opening ECL allowance (' . ($prev ?? 'n/a') . ')', $this->money($opening)],
                    ['Net charge / (release) for the period', $this->money($movement)],
                    ['Closing ECL allowance (' . $period . ')', $this->money($closing)],
                ],
            ], [
                'heading' => 'Closing ECL by Stage',
                'columns' => ['Stage', 'ECL'],
                'align' => ['l', 'r'],
                'rows' => collect(['1', '2', '3'])->map(fn ($s) => ['Stage ' . $s, $this->money($byStage[$s] ?? 0)])->all(),
            ]]]);
    }

    public function grossMovement(Request $request)
    {
        $period = $this->period($request);
        $prev = $this->previousPeriod($period);

        $cur = DB::table('loan_books')->where('reporting_period', $period)
            ->selectRaw("ifrs9stage_pre_qualitative s, COUNT(*) n,
                SUM(COALESCE(carrying_amount,0)) ca, SUM(COALESCE(disbursed,0)) disb,
                SUM(COALESCE(repayments,0)) rep")
            ->groupBy('ifrs9stage_pre_qualitative')->orderBy('ifrs9stage_pre_qualitative')->get();

        $prevByStage = $prev ? DB::table('loan_books')->where('reporting_period', $prev)
            ->selectRaw("ifrs9stage_pre_qualitative s, SUM(COALESCE(carrying_amount,0)) ca")
            ->groupBy('ifrs9stage_pre_qualitative')->pluck('ca', 's') : collect();

        $rows = $cur->map(function ($r) use ($prevByStage) {
            $open = (float) ($prevByStage[$r->s] ?? 0);
            return ['Stage ' . $r->s, number_format($r->n), $this->money($open),
                $this->money($r->disb), $this->money($r->rep), $this->money($r->ca),
                $this->money($r->ca - $open)];
        })->all();

        return $this->respond(['key' => 'gross-movement', 'period' => $period,
            'subtitle' => $prev ? "Gross carrying amount movement {$prev} -> {$period}" : 'Closing position (no prior period)',
            'kpis' => $this->totalsKpis($period),
            'sections' => [[
                'heading' => 'Gross Carrying Amount Movement by Stage',
                'columns' => ['Stage', 'Loans', 'Opening', 'Disbursements', 'Repayments', 'Closing', 'Net Movement'],
                'align' => ['l', 'r', 'r', 'r', 'r', 'r', 'r'],
                'rows' => $rows,
            ]]]);
    }

    public function eclCharge(Request $request)
    {
        $period = $this->period($request);
        $prev = $this->previousPeriod($period);

        $cur = DB::table('loan_books')->selectRaw("ifrs9stage_pre_qualitative s, SUM(COALESCE(ecl_value,0)) ecl")
            ->where('reporting_period', $period)->groupBy('ifrs9stage_pre_qualitative')->pluck('ecl', 's');
        $pre = $prev ? DB::table('loan_books')->selectRaw("ifrs9stage_pre_qualitative s, SUM(COALESCE(ecl_value,0)) ecl")
            ->where('reporting_period', $prev)->groupBy('ifrs9stage_pre_qualitative')->pluck('ecl', 's') : collect();

        $rows = collect(['1', '2', '3'])->map(function ($s) use ($cur, $pre) {
            $c = (float) ($cur[$s] ?? 0);
            $p = (float) ($pre[$s] ?? 0);
            return ['Stage ' . $s, $this->money($p), $this->money($c), $this->money($c - $p)];
        })->all();
        $tot = (float) array_sum($cur->all()) - (float) array_sum($pre->all());

        return $this->respond(['key' => 'ecl-charge', 'period' => $period,
            'subtitle' => $prev ? "Charge / (release) {$prev} -> {$period}" : 'No prior period',
            'kpis' => [
                ['label' => 'P&L Impact', 'value' => $this->money($tot), 'tone' => $tot >= 0 ? 'rose' : 'emerald'],
                ['label' => 'Direction', 'value' => $tot >= 0 ? 'Charge' : 'Release', 'tone' => 'amber'],
            ],
            'sections' => [[
                'heading' => 'Impairment Charge / (Release) by Stage',
                'columns' => ['Stage', 'Prior ECL', 'Current ECL', 'Charge / (Release)'],
                'align' => ['l', 'r', 'r', 'r'],
                'rows' => $rows,
            ]]]);
    }

    /* ===================================================================== */
    /*  Model Components                                                     */
    /* ===================================================================== */

    public function pdReport(Request $request)
    {
        $period = $this->period($request);
        $rows = DB::table('loan_books')
            ->selectRaw("ifrs9stage_pre_qualitative s, COUNT(*) n,
                AVG(COALESCE(lifetime_pd,0)) pdlt,
                AVG(COALESCE(pd_prefli,0)) pdpre, AVG(COALESCE(pd_post_fli,0)) pdpost")
            ->where('reporting_period', $period)
            ->groupBy('ifrs9stage_pre_qualitative')->orderBy('ifrs9stage_pre_qualitative')->get()
            ->map(fn ($r) => ['Stage ' . $r->s, number_format($r->n),
                $this->num($r->pdpre, 6), $this->num($r->pdlt, 6), $this->num($r->pdpost, 6)])->all();

        return $this->respond(['key' => 'pd-report', 'period' => $period,
            'subtitle' => '12-month vs lifetime PD, pre and post forward-looking adjustment',
            'kpis' => $this->totalsKpis($period),
            'sections' => [[
                'heading' => 'Average Probability of Default by Stage',
                'columns' => ['Stage', 'Loans', 'Avg PD (pre-FLI / 12m)', 'Avg Lifetime PD', 'Avg PD (post-FLI)'],
                'align' => ['l', 'r', 'r', 'r', 'r'],
                'rows' => $rows,
            ]]]);
    }

    public function lgdCollateral(Request $request)
    {
        $period = $this->period($request);
        $rows = DB::table('loan_books')
            ->selectRaw("ifrs9stage_pre_qualitative s, COUNT(*) n,
                SUM(" . self::EAD_SQL . ") ead,
                SUM(COALESCE(allocated_gross_value,0)) coll_gross,
                SUM(COALESCE(allocated_discounted_value,0)) coll_disc,
                AVG(COALESCE(customer_lgd,0)) clgd, AVG(COALESCE(collection_lgd,0)) collgd")
            ->where('reporting_period', $period)
            ->groupBy('ifrs9stage_pre_qualitative')->orderBy('ifrs9stage_pre_qualitative')->get()
            ->map(function ($r) {
                $netUnsec = max(0, $r->ead - $r->coll_disc);
                return ['Stage ' . $r->s, number_format($r->n), $this->money($r->ead),
                    $this->money($r->coll_gross), $this->money($r->coll_disc),
                    $this->money($netUnsec), $this->num($r->clgd, 6), $this->num($r->collgd, 6)];
            })->all();

        return $this->respond(['key' => 'lgd-collateral', 'period' => $period,
            'subtitle' => 'Collateral cover, net unsecured exposure and LGD (both methods)',
            'kpis' => $this->totalsKpis($period),
            'sections' => [[
                'heading' => 'LGD & Collateral by Stage',
                'columns' => ['Stage', 'Loans', 'EAD', 'Collateral (gross)', 'Collateral (discounted)', 'Net Unsecured', 'Avg Customer LGD', 'Avg Collection LGD'],
                'align' => ['l', 'r', 'r', 'r', 'r', 'r', 'r', 'r'],
                'rows' => $rows,
            ]]]);
    }

    public function eadReport(Request $request)
    {
        $period = $this->period($request);
        $rows = DB::table('loan_books')
            ->selectRaw("ifrs9stage_pre_qualitative s, COUNT(*) n,
                SUM(COALESCE(carrying_amount,0)) ca, SUM(COALESCE(commitments,0)) comm,
                AVG(COALESCE(facility_utilisation_rate,1)) ccf, SUM(" . self::EAD_SQL . ") ead")
            ->where('reporting_period', $period)
            ->groupBy('ifrs9stage_pre_qualitative')->orderBy('ifrs9stage_pre_qualitative')->get()
            ->map(fn ($r) => ['Stage ' . $r->s, number_format($r->n), $this->money($r->ca),
                $this->money($r->comm), $this->num($r->ccf, 4), $this->money($r->ead)])->all();

        return $this->respond(['key' => 'ead-report', 'period' => $period,
            'subtitle' => 'On + off balance sheet exposure at default',
            'kpis' => $this->totalsKpis($period),
            'sections' => [[
                'heading' => 'Exposure at Default by Stage',
                'columns' => ['Stage', 'Loans', 'Carrying Amount', 'Undrawn Commitments', 'Avg CCF', 'EAD'],
                'align' => ['l', 'r', 'r', 'r', 'r', 'r'],
                'rows' => $rows,
            ]]]);
    }

    /* ===================================================================== */
    /*  Forward-Looking                                                      */
    /* ===================================================================== */

    public function macroScenario(Request $request)
    {
        $period = $this->period($request);

        $macro = [];
        foreach (['macro_economic_variables', 'macro_economic_data', 'macroeconomic_variables', 'macro_variables'] as $tbl) {
            if (Schema::hasTable($tbl)) {
                try {
                    $macro = DB::table($tbl)->orderByDesc('id')->limit(40)->get()
                        ->map(fn ($r) => [$r->name ?? $r->variable ?? '—',
                            $r->period ?? $r->reporting_period ?? '—',
                            isset($r->value) ? $this->num($r->value, 4) : '—'])->all();
                } catch (\Throwable $e) {
                    $macro = [];
                }
                break;
            }
        }
        if (empty($macro)) {
            $macro = [['No macro-economic variables table found for this install', '—', '—']];
        }

        $scenarios = [];
        if (Schema::hasTable('scenario_sets')) {
            $scenarios = DB::table('scenario_sets')
                ->leftJoin('scenario_probabilities as sp', 'sp.scenario_set_id', '=', 'scenario_sets.id')
                ->selectRaw('scenario_sets.name set_name, sp.scenario_name, sp.probability')
                ->orderBy('scenario_sets.id')->get()
                ->map(fn ($r) => [$r->set_name, $r->scenario_name ?? '—',
                    $this->pct(($r->probability ?? 0) / 100)])->all();
        }

        return $this->respond(['key' => 'macro-scenario', 'period' => $period,
            'subtitle' => 'Forward-looking macro assumptions and economic scenarios',
            'kpis' => $this->totalsKpis($period),
            'sections' => [
                ['heading' => 'Macro-Economic Assumptions',
                 'columns' => ['Variable', 'Period', 'Value'], 'align' => ['l', 'l', 'r'], 'rows' => $macro],
                ['heading' => 'Economic Scenarios & Weights',
                 'columns' => ['Scenario Set', 'Scenario', 'Probability'], 'align' => ['l', 'l', 'r'],
                 'rows' => $scenarios ?: [['No scenario sets defined', '—', '—']]],
            ]]);
    }

    public function scenarioEcl(Request $request)
    {
        $period = $this->period($request);
        $sets = [];
        if (Schema::hasTable('scenario_sets')) {
            $sets = DB::table('scenario_sets')
                ->leftJoin('scenario_probabilities as sp', 'sp.scenario_set_id', '=', 'scenario_sets.id')
                ->selectRaw('scenario_sets.name, sp.scenario_name, sp.probability')
                ->orderBy('scenario_sets.id')->get()
                ->map(fn ($r) => [$r->name, $r->scenario_name ?? '—', $this->pct(($r->probability ?? 0) / 100)])->all();
        }

        $fli = DB::table('loan_books')->where('reporting_period', $period)
            ->selectRaw("SUM(COALESCE(ecl_value,0)) ecl, AVG(COALESCE(fli_adj,0)) fli")->first();

        return $this->respond(['key' => 'scenario-ecl', 'period' => $period,
            'subtitle' => 'Probability-weighted forward-looking scenarios',
            'kpis' => [
                ['label' => 'Probability-Weighted ECL', 'value' => $this->money($fli->ecl ?? 0), 'tone' => 'rose'],
                ['label' => 'Avg FLI Adjustment', 'value' => $this->num($fli->fli ?? 0, 6), 'tone' => 'amber'],
            ],
            'sections' => [[
                'heading' => 'Scenario Sets & Weights',
                'columns' => ['Scenario Set', 'Scenario', 'Probability'],
                'align' => ['l', 'l', 'r'],
                'rows' => $sets ?: [['No scenario sets defined', '—', '—']],
            ]]]);
    }

    /* ===================================================================== */
    /*  RBM Prudential                                                       */
    /* ===================================================================== */

    public function rbmClassification(Request $request)
    {
        $period = $this->period($request);
        return $this->respond(array_merge(['key' => 'rbm-classification', 'period' => $period],
            $this->rbmBuild($period)));
    }

    public function ifrs9VsRbm(Request $request)
    {
        $period = $this->period($request);
        $rows = DB::table('loan_books')->where('reporting_period', $period)
            ->selectRaw("ifrs9stage_pre_qualitative s, COUNT(*) n, SUM(" . self::EAD_SQL . ") ead")
            ->groupBy('ifrs9stage_pre_qualitative')->orderBy('ifrs9stage_pre_qualitative')->get()
            ->map(fn ($r) => ['Stage ' . $r->s, $this->rbmClass((string) $r->s),
                number_format($r->n), $this->money($r->ead)])->all();

        return $this->respond(['key' => 'ifrs9-vs-rbm', 'period' => $period,
            'subtitle' => 'IFRS 9 stage mapped to RBM prudential classification',
            'kpis' => $this->totalsKpis($period),
            'sections' => [[
                'heading' => 'IFRS 9 Stage vs RBM Class',
                'columns' => ['IFRS 9 Stage', 'RBM Classification', 'Loans', 'Exposure (EAD)'],
                'align' => ['l', 'l', 'r', 'r'],
                'rows' => $rows,
            ]]]);
    }

    public function nplArrears(Request $request)
    {
        $period = $this->period($request);
        $buckets = DB::table('loan_books')->where('reporting_period', $period)
            ->selectRaw("CASE
                    WHEN COALESCE(overdue_days,0) <= 30 THEN '0-30 (Pass)'
                    WHEN overdue_days <= 89  THEN '31-89 (Special Mention)'
                    WHEN overdue_days <= 179 THEN '90-179 (Substandard)'
                    WHEN overdue_days <= 364 THEN '180-364 (Doubtful)'
                    ELSE '365+ (Loss)' END bucket,
                MIN(COALESCE(overdue_days,0)) ord,
                COUNT(*) n, SUM(" . self::EAD_SQL . ") ead, SUM(COALESCE(ecl_value,0)) ecl")
            ->groupBy('bucket')->orderBy('ord')->get()
            ->map(fn ($r) => [$r->bucket, number_format($r->n), $this->money($r->ead), $this->money($r->ecl)])->all();

        $npl = DB::table('loan_books')->where('reporting_period', $period)
            ->where('ifrs9stage_pre_qualitative', 3)
            ->selectRaw("COUNT(*) n, SUM(" . self::EAD_SQL . ") ead")->first();
        $tot = $this->periodTotals($period);

        return $this->respond(['key' => 'npl-arrears', 'period' => $period,
            'kpis' => [
                ['label' => 'NPL Exposure', 'value' => $this->money($npl->ead ?? 0), 'tone' => 'rose'],
                ['label' => 'NPL Loans', 'value' => number_format($npl->n ?? 0), 'tone' => 'amber'],
                ['label' => 'NPL Ratio', 'value' => $this->pct(($tot->ead ?? 0) ? ($npl->ead ?? 0) / $tot->ead : 0), 'tone' => 'rose'],
                ['label' => 'Loans', 'value' => number_format($tot->loans ?? 0), 'tone' => 'emerald'],
            ],
            'sections' => [[
                'heading' => 'Arrears Ageing (Days Past Due)',
                'columns' => ['DPD Bucket', 'Loans', 'Exposure (EAD)', 'ECL'],
                'align' => ['l', 'r', 'r', 'r'],
                'rows' => $buckets,
            ]]]);
    }

    public function provisionComparison(Request $request)
    {
        $period = $this->period($request);
        $raw = DB::table('loan_books')->where('reporting_period', $period)
            ->selectRaw($this->rbmClassCase() . " rbm, SUM(" . self::EAD_SQL . ") ead, SUM(COALESCE(ecl_value,0)) ecl, COUNT(*) n")
            ->groupBy('rbm')->get()->keyBy('rbm');

        $secTot = 0;
        $eclTot = 0;
        $out = [];
        foreach (self::RBM as $class => $def) {
            $r    = $raw->get($class);
            $ead  = (float) ($r->ead ?? 0);
            $ecl  = (float) ($r->ecl ?? 0);
            $prud = $ead * $def['rate'];
            $secTot += $prud;
            $eclTot += $ecl;
            $out[] = [$class, $this->money($ead), $this->pct($def['rate']),
                $this->money($prud), $this->money($ecl), $this->money($ecl - $prud)];
        }

        return $this->respond(['key' => 'provision-comparison', 'period' => $period,
            'subtitle' => 'IFRS 9 ECL vs RBM prudential provision (RBM Directive 2018 rates, by DPD class)',
            'kpis' => [
                ['label' => 'IFRS 9 ECL', 'value' => $this->money($eclTot), 'tone' => 'rose'],
                ['label' => 'RBM Provision', 'value' => $this->money($secTot), 'tone' => 'amber'],
                ['label' => 'Shortfall / (Excess)', 'value' => $this->money($secTot - $eclTot), 'tone' => ($secTot - $eclTot) > 0 ? 'rose' : 'emerald'],
            ],
            'sections' => [[
                'heading' => 'IFRS 9 ECL vs RBM Prudential Provision (by DPD class)',
                'columns' => ['RBM Class', 'EAD', 'RBM Rate', 'RBM Provision', 'IFRS 9 ECL', 'ECL − RBM'],
                'align' => ['l', 'r', 'r', 'r', 'r', 'r'],
                'rows' => $out,
            ]]]);
    }

    /* ===================================================================== */
    /*  Disclosure & Audit                                                   */
    /* ===================================================================== */

    public function fsDisclosure(Request $request)
    {
        $period = $this->period($request);
        $stage = DB::table('loan_books')->where('reporting_period', $period)
            ->selectRaw("ifrs9stage_pre_qualitative s, COUNT(*) n, SUM(" . self::EAD_SQL . ") ead,
                SUM(COALESCE(ecl_value,0)) ecl")
            ->groupBy('ifrs9stage_pre_qualitative')->orderBy('ifrs9stage_pre_qualitative')->get()
            ->map(fn ($r) => ['Stage ' . $r->s . ' — ' . $this->rbmClass((string) $r->s),
                number_format($r->n), $this->money($r->ead), $this->money($r->ecl),
                $this->money($r->ead - $r->ecl)])->all();

        return $this->respond(['key' => 'fs-disclosure', 'period' => $period,
            'subtitle' => 'IFRS 9 financial statement note tables',
            'kpis' => $this->totalsKpis($period),
            'sections' => [[
                'heading' => 'Note: Loans & Advances by ECL Stage',
                'columns' => ['Stage / Class', 'Accounts', 'Gross Carrying (EAD)', 'Loss Allowance (ECL)', 'Net Carrying'],
                'align' => ['l', 'r', 'r', 'r', 'r'],
                'rows' => $stage,
            ]]]);
    }

    public function dataQuality(Request $request)
    {
        $period = $this->period($request);
        $b = fn ($w) => DB::table('loan_books')->where('reporting_period', $period)->whereRaw($w)->count();

        // RBM prudential classification, concentration reporting and the FLI
        // macro link all depend on every loan carrying a sector tag and a real
        // (non-blended) portfolio. Hard-flag any gap up front.
        $missingSector    = $b("(industry_type IS NULL OR industry_type='' OR industry_code IS NULL OR industry_code='')");
        $unmappedPortfolio = $b('(loan_portfolio_id IS NULL OR loan_portfolio_id = 1)');

        $rows = [
            ['Missing sector tag (RBM classification)', number_format($missingSector)],
            ['Unmapped to a real portfolio (blended "Loans")', number_format($unmappedPortfolio)],
            ['Missing customer name', number_format($b("(customer_name IS NULL OR customer_name='')"))],
            ['Missing / zero EAD', number_format($b('COALESCE(carrying_amount,0)=0'))],
            ['Negative balance', number_format($b('carrying_amount < 0'))],
            ['Missing stage', number_format($b("(ifrs9stage_pre_qualitative IS NULL OR ifrs9stage_pre_qualitative='')"))],
            ['ECL not calculated', number_format($b('ecl_value IS NULL'))],
            ['Zero ECL on Stage 3', number_format($b("ifrs9stage_pre_qualitative=3 AND COALESCE(ecl_value,0)=0"))],
            ['ECL exceeds EAD', number_format($b('COALESCE(ecl_value,0) > (' . self::EAD_SQL . ')'))],
            ['Missing remaining tenor', number_format($b('COALESCE(remaining_tenor,0)=0'))],
        ];

        return $this->respond(['key' => 'data-quality', 'period' => $period,
            'subtitle' => 'Data integrity & exception checks for ' . $period,
            'kpis' => [
                ['label' => 'Loans Checked', 'value' => number_format($this->periodTotals($period)->loans ?? 0), 'tone' => 'maiic'],
                ['label' => 'Missing Sector Tag', 'value' => number_format($missingSector), 'tone' => $missingSector > 0 ? 'rose' : 'emerald'],
                ['label' => 'Unmapped Portfolio', 'value' => number_format($unmappedPortfolio), 'tone' => $unmappedPortfolio > 0 ? 'rose' : 'emerald'],
            ],
            'sections' => [[
                'heading' => 'Data Quality Exceptions',
                'columns' => ['Check', 'Records'],
                'align' => ['l', 'r'],
                'rows' => $rows,
            ]]]);
    }

    /* ===================================================================== */
    /*  Executive Summary — one-page composite                               */
    /* ===================================================================== */

    public function executiveSummary(Request $request)
    {
        $period = $this->period($request);
        $EAD    = '(' . self::EAD_SQL . ')';

        $stage = DB::table('loan_books')->where('reporting_period', $period)
            ->groupBy('ifrs9stage_pre_qualitative')->orderBy('ifrs9stage_pre_qualitative')
            ->selectRaw("ifrs9stage_pre_qualitative s, COUNT(*) n,
                SUM($EAD) ead, SUM(COALESCE(ecl_value,0)) ecl")->get()
            ->map(fn ($r) => ['Stage ' . $r->s, number_format($r->n), $this->money($r->ead),
                $this->money($r->ecl), $this->pct($r->ead ? $r->ecl / $r->ead : 0)])->all();

        $port = DB::table('loan_books as lb')->leftJoin('loan_portfolios as p', 'p.id', 'lb.loan_portfolio_id')
            ->where('reporting_period', $period)->groupBy('p.name')
            ->selectRaw("COALESCE(p.name,'Unmapped') name, COUNT(*) n,
                SUM($EAD) ead, SUM(COALESCE(ecl_value,0)) ecl")
            ->orderByDesc(DB::raw('SUM(COALESCE(ecl_value,0))'))->get()
            ->map(fn ($r) => [$r->name, number_format($r->n), $this->money($r->ead),
                $this->money($r->ecl), $this->pct($r->ead ? $r->ecl / $r->ead : 0)])->all();

        $top = DB::table('loan_books')->where('reporting_period', $period)
            ->selectRaw("contract_id, customer_name, ifrs9stage_pre_qualitative s,
                $EAD ead, COALESCE(ecl_value,0) ecl")
            ->orderByDesc(DB::raw($EAD))->limit(10)->get()
            ->map(fn ($r) => [$r->contract_id, $r->customer_name ?: '(Unnamed)', 'Stage ' . $r->s,
                $this->money($r->ead), $this->money($r->ecl)])->all();

        $dq = fn ($w) => DB::table('loan_books')->where('reporting_period', $period)->whereRaw($w)->count();
        $flags = [
            ['Missing sector tag', number_format($dq("(industry_type IS NULL OR industry_type='')"))],
            ['Unmapped portfolio', number_format($dq('(loan_portfolio_id IS NULL OR loan_portfolio_id = 1)'))],
            ['ECL not calculated', number_format($dq('ecl_value IS NULL'))],
            ['Zero ECL on Stage 3', number_format($dq("ifrs9stage_pre_qualitative=3 AND COALESCE(ecl_value,0)=0"))],
        ];

        return $this->respond(['key' => 'executive', 'period' => $period,
            'subtitle' => 'Consolidated IFRS 9 ECL position for ' . $period,
            'kpis' => $this->totalsKpis($period),
            'sections' => [
                ['heading' => 'ECL by IFRS 9 Stage', 'columns' => ['Stage', 'Loans', 'EAD', 'ECL', 'Coverage'],
                 'align' => ['l', 'r', 'r', 'r', 'r'], 'rows' => $stage],
                ['heading' => 'ECL by Portfolio', 'columns' => ['Portfolio', 'Loans', 'EAD', 'ECL', 'Coverage'],
                 'align' => ['l', 'r', 'r', 'r', 'r'], 'rows' => $port],
                ['heading' => 'Top 10 Exposures', 'columns' => ['Contract', 'Client', 'Stage', 'EAD', 'ECL'],
                 'align' => ['l', 'l', 'l', 'r', 'r'], 'rows' => $top],
                ['heading' => 'Data Quality Flags', 'columns' => ['Check', 'Records'],
                 'align' => ['l', 'r'], 'rows' => $flags],
            ]]);
    }

    /* ===================================================================== */
    /*  Portfolio ECL Trend                                                  */
    /* ===================================================================== */

    public function portfolioTrend(Request $request)
    {
        $period = $this->period($request);

        // Last 12 periods up to the selected one, per portfolio, from the ECL store.
        $periods = collect($this->periods())->filter(fn ($p) => $p <= $period)
            ->take(12)->values()->reverse()->values();

        $rowsRaw = DB::table('expected_credit_loss as e')
            ->leftJoin('loan_portfolios as p', 'p.id', 'e.ecl_calculation_id')
            ->where('e.ecl_calculation_level', 'portfolio')
            ->whereIn('e.reporting_period', $periods)
            ->groupBy('e.reporting_period', 'p.name')
            ->selectRaw("e.reporting_period rp, COALESCE(p.name,'Unmapped') name, SUM(e.total_ecl) ecl")
            ->get();

        $portNames = $rowsRaw->pluck('name')->unique()->sort()->values();
        $pivot = [];
        foreach ($rowsRaw as $r) {
            $pivot[$r->rp][$r->name] = (float) $r->ecl;
        }

        $rows = [];
        foreach ($periods as $p) {
            $line = [$p];
            $tot = 0;
            foreach ($portNames as $n) {
                $val = $pivot[$p][$n] ?? 0;
                $tot += $val;
                $line[] = $this->money($val);
            }
            $line[] = $this->money($tot);
            $rows[] = $line;
        }

        return $this->respond(['key' => 'portfolio-trend', 'period' => $period,
            'subtitle' => 'Total ECL by portfolio over the last ' . count($periods) . ' periods',
            'kpis' => $this->totalsKpis($period),
            'sections' => [[
                'heading' => 'ECL by Portfolio over Time',
                'columns' => array_merge(['Period'], $portNames->all(), ['Total']),
                'align' => array_merge(['l'], array_fill(0, $portNames->count() + 1, 'r')),
                'rows' => $rows,
            ]]]);
    }

    /* ===================================================================== */
    /*  ECL by Sector / Product Group                                        */
    /* ===================================================================== */

    public function sectorEcl(Request $request)
    {
        $period = $this->period($request);
        $EAD    = '(' . self::EAD_SQL . ')';

        $rows = DB::table('loan_books')->where('reporting_period', $period)
            ->groupBy('industry_type')
            ->selectRaw("COALESCE(NULLIF(industry_type,''),'Untagged') sec, COUNT(*) n,
                SUM($EAD) ead, AVG(COALESCE(pd_post_fli,pd_prefli,0)) pd,
                AVG(COALESCE(lgd_value,0)) lgd, SUM(COALESCE(ecl_value,0)) ecl")
            ->orderByDesc(DB::raw('SUM(COALESCE(ecl_value,0))'))->get()
            ->map(fn ($r) => [$r->sec, number_format($r->n), $this->money($r->ead),
                $this->num($r->pd, 6), $this->num($r->lgd, 6), $this->money($r->ecl),
                $this->pct($r->ead ? $r->ecl / $r->ead : 0)])->all();

        return $this->respond(['key' => 'sector-ecl', 'period' => $period,
            'subtitle' => 'ECL by RBM economic sector for ' . $period,
            'kpis' => $this->totalsKpis($period),
            'sections' => [[
                'heading' => 'ECL by Economic Sector',
                'columns' => ['Sector', 'Loans', 'EAD', 'Avg PD', 'Avg LGD', 'ECL', 'Coverage'],
                'align' => ['l', 'r', 'r', 'r', 'r', 'r', 'r'],
                'rows' => $rows,
            ]]]);
    }

    public function productGroupEcl(Request $request)
    {
        $period = $this->period($request);
        $EAD    = '(' . self::EAD_SQL . ')';

        $rows = DB::table('loan_books')->where('reporting_period', $period)
            ->groupBy('product_group')
            ->selectRaw("COALESCE(NULLIF(product_group,''),'Unspecified') pg, COUNT(*) n,
                SUM($EAD) ead, SUM(COALESCE(ecl_value,0)) ecl")
            ->orderByDesc(DB::raw('SUM(COALESCE(ecl_value,0))'))->get()
            ->map(fn ($r) => [$r->pg, number_format($r->n), $this->money($r->ead),
                $this->money($r->ecl), $this->pct($r->ead ? $r->ecl / $r->ead : 0)])->all();

        return $this->respond(['key' => 'product-group-ecl', 'period' => $period,
            'subtitle' => 'ECL by lending product group for ' . $period,
            'kpis' => $this->totalsKpis($period),
            'sections' => [[
                'heading' => 'ECL by Product Group',
                'columns' => ['Product Group', 'Loans', 'EAD', 'ECL', 'Coverage'],
                'align' => ['l', 'r', 'r', 'r', 'r'],
                'rows' => $rows,
            ]]]);
    }

    /**
     * ECL by MAIIC internal risk grade. As a DFI, MAIIC reports on its own
     * A–G master scale (mapped from the 12-month PD). Grades are shown in
     * scale order with their PD band so the report doubles as the rating
     * scale definition.
     */
    public function gradeEcl(Request $request)
    {
        $period = $this->period($request);
        $EAD    = '(' . self::EAD_SQL . ')';

        $bands = [
            'A' => '0 – 2%', 'B' => '2 – 5%', 'C' => '5 – 10%', 'D' => '10 – 20%',
            'E' => '20 – 40%', 'F' => '40 – 100%', 'G' => 'Default (100%)',
        ];

        $raw = DB::table('loan_books')->where('reporting_period', $period)
            ->groupBy('internal_grade_code')
            ->selectRaw("internal_grade_code g, COUNT(*) n, SUM($EAD) ead,
                AVG(COALESCE(`12m_pd`,0)) pd, AVG(COALESCE(lgd_value,0)) lgd,
                SUM(COALESCE(ecl_value,0)) ecl")
            ->get()->keyBy('g');

        $rows = [];
        foreach ($bands as $g => $band) {
            $r = $raw->get($g);
            $ead = (float) ($r->ead ?? 0);
            $ecl = (float) ($r->ecl ?? 0);
            $rows[] = [$g, $band, number_format((int) ($r->n ?? 0)),
                $this->money($ead), $this->num($r->pd ?? 0, 6), $this->num($r->lgd ?? 0, 6),
                $this->money($ecl), $this->pct($ead ? $ecl / $ead : 0)];
        }

        return $this->respond(['key' => 'grade-ecl', 'period' => $period,
            'subtitle' => 'MAIIC internal risk-grade master scale & ECL for ' . $period,
            'kpis' => $this->totalsKpis($period),
            'sections' => [[
                'heading' => 'ECL by Internal Risk Grade (A = lowest risk … G = default)',
                'columns' => ['Grade', 'PD Band', 'Loans', 'EAD', 'Avg PD', 'Avg LGD', 'ECL', 'Coverage'],
                'align' => ['l', 'l', 'r', 'r', 'r', 'r', 'r', 'r'],
                'rows' => $rows,
            ]]]);
    }

    /**
     * Credit Risk Mitigation for agri lending. Smallholder input loans are
     * secured by off-take/contract-farming, warehouse receipts, group/
     * cooperative guarantees or AIP backing — not real estate — and LGD
     * follows the enhancement's typical recovery.
     */
    public function crmAgri(Request $request)
    {
        $period = $this->period($request);
        $EAD    = '(' . self::EAD_SQL . ')';

        $rows = DB::table('loan_books')->where('reporting_period', $period)
            ->groupBy('credit_enhancement')
            ->selectRaw("COALESCE(NULLIF(credit_enhancement,''),'Unspecified') ce, COUNT(*) n,
                SUM($EAD) ead, AVG(COALESCE(collection_lgd,0)) lgd,
                SUM(COALESCE(ecl_value,0)) ecl")
            ->orderByDesc(DB::raw("SUM($EAD)"))->get()
            ->map(fn ($r) => [$r->ce, number_format($r->n), $this->money($r->ead),
                $this->pct($r->lgd), $this->money($r->ecl),
                $this->pct($r->ead ? $r->ecl / $r->ead : 0)])->all();

        return $this->respond(['key' => 'crm-agri', 'period' => $period,
            'subtitle' => 'How the book is actually secured, and the LGD each enhancement implies — ' . $period,
            'kpis' => $this->totalsKpis($period),
            'sections' => [[
                'heading' => 'Exposure & LGD by Credit Enhancement',
                'columns' => ['Credit Enhancement', 'Loans', 'EAD', 'Avg LGD', 'ECL', 'Coverage'],
                'align' => ['l', 'r', 'r', 'r', 'r', 'r'],
                'rows' => $rows,
            ]]]);
    }

    /**
     * Cooperative / anchor linkage. Individual smallholder loans tied to the
     * same cooperative or anchor buyer default together. This shows exposure
     * by linkage and an indicative contagion loss if the largest linkage
     * group migrated wholesale to default (simplified — full asset-
     * correlation modelling is a separate workstream).
     */
    public function coopLinkage(Request $request)
    {
        $period = $this->period($request);
        $EAD    = '(' . self::EAD_SQL . ')';

        $g = DB::table('loan_books')->where('reporting_period', $period)
            ->groupBy('cooperative')
            ->selectRaw("COALESCE(NULLIF(cooperative,''),'Unspecified') coop, COUNT(*) n,
                SUM($EAD) ead, AVG(COALESCE(collection_lgd,0)) lgd,
                SUM(COALESCE(ecl_value,0)) ecl")
            ->orderByDesc(DB::raw("SUM($EAD)"))->get();

        $totEad = (float) $g->sum('ead');
        $rows = $g->map(fn ($r) => [$r->coop, number_format($r->n), $this->money($r->ead),
            $this->pct($totEad ? $r->ead / $totEad : 0), $this->money($r->ecl)])->all();

        // Indicative contagion: the largest linked group migrates to default
        // (ECL ≈ EAD × its average LGD), less the ECL already held on it.
        $linked = $g->reject(fn ($r) => str_starts_with($r->coop, 'Direct'));
        $top = $linked->first();
        $contagion = $top ? ($top->ead * $top->lgd - $top->ecl) : 0;

        return $this->respond(['key' => 'coop-linkage', 'period' => $period,
            'subtitle' => 'Correlated exposure by cooperative / anchor buyer — ' . $period,
            'kpis' => [
                ['label' => 'Cooperative/Anchor Groups', 'value' => number_format($linked->count()), 'tone' => 'maiic'],
                ['label' => 'Largest Linked Group', 'value' => $top ? $top->coop : '—', 'tone' => 'amber'],
                ['label' => 'Largest Group Exposure', 'value' => $this->money($top->ead ?? 0), 'tone' => 'rose'],
                ['label' => 'Contagion Loss (top group defaults)', 'value' => $this->money(max(0, $contagion)), 'tone' => 'rose'],
            ],
            'sections' => [[
                'heading' => 'Exposure by Cooperative / Anchor (correlated default risk)',
                'columns' => ['Cooperative / Anchor', 'Loans', 'EAD', '% of Book', 'ECL'],
                'align' => ['l', 'r', 'r', 'r', 'r'],
                'rows' => $rows,
            ]]]);
    }

    /* ===================================================================== */
    /*  Concentration & Large Exposures                                      */
    /* ===================================================================== */

    public function concentration(Request $request)
    {
        $period    = $this->period($request);
        $EAD       = '(' . self::EAD_SQL . ')';
        $threshold = (float) ($request->query('threshold', 1000000));

        $totEad = (float) (DB::table('loan_books')->where('reporting_period', $period)
            ->selectRaw("SUM($EAD) e")->value('e') ?: 0);

        // Single-name concentration (group by customer).
        $names = DB::table('loan_books')->where('reporting_period', $period)
            ->groupBy('customer_name')
            ->selectRaw("COALESCE(customer_name,'(Unnamed)') nm, COUNT(*) n,
                SUM($EAD) ead, SUM(COALESCE(ecl_value,0)) ecl")
            ->orderByDesc(DB::raw("SUM($EAD)"))->limit(20)->get();

        $topRows = $names->map(fn ($r) => [$r->nm, number_format($r->n), $this->money($r->ead),
            $this->money($r->ecl), $this->pct($totEad ? $r->ead / $totEad : 0)])->all();

        $top1  = $names->first();
        $top10 = $names->take(10)->sum('ead');

        // Portfolio concentration + Herfindahl-Hirschman Index.
        $ports = DB::table('loan_books as lb')->leftJoin('loan_portfolios as p', 'p.id', 'lb.loan_portfolio_id')
            ->where('reporting_period', $period)->groupBy('p.name')
            ->selectRaw("COALESCE(p.name,'Unmapped') nm, SUM($EAD) ead")->get();
        $hhi = 0.0;
        $portRows = $ports->sortByDesc('ead')->map(function ($r) use ($totEad, &$hhi) {
            $share = $totEad ? $r->ead / $totEad : 0;
            $hhi  += ($share * 100) ** 2;
            return [$r->nm, $this->money($r->ead), $this->pct($share)];
        })->values()->all();

        // Large exposures over the threshold.
        $large = DB::table('loan_books')->where('reporting_period', $period)
            ->whereRaw("$EAD >= ?", [$threshold])
            ->selectRaw("contract_id, customer_name, ifrs9stage_pre_qualitative s,
                $EAD ead, COALESCE(ecl_value,0) ecl")
            ->orderByDesc(DB::raw($EAD))->limit(50)->get()
            ->map(fn ($r) => [$r->contract_id, $r->customer_name ?: '(Unnamed)', 'Stage ' . $r->s,
                $this->money($r->ead), $this->money($r->ecl)])->all();

        return $this->respond(['key' => 'concentration', 'period' => $period,
            'subtitle' => 'Single-name & portfolio concentration for ' . $period,
            'controls' => [
                'action' => 'ifrs9-reports.concentration',
                'fields' => [
                    ['name' => 'threshold', 'label' => 'Large-exposure threshold (MWK)', 'value' => (string) $threshold],
                ],
            ],
            'kpis' => [
                ['label' => 'Largest Single Name', 'value' => $this->pct($totEad && $top1 ? $top1->ead / $totEad : 0), 'tone' => 'rose'],
                ['label' => 'Top 10 Names', 'value' => $this->pct($totEad ? $top10 / $totEad : 0), 'tone' => 'amber'],
                ['label' => 'Portfolio HHI', 'value' => number_format($hhi, 0), 'tone' => $hhi > 2500 ? 'rose' : 'maiic'],
                ['label' => 'Total EAD', 'value' => $this->money($totEad), 'tone' => 'emerald'],
            ],
            'sections' => [
                ['heading' => 'Top 20 Single-Name Exposures', 'columns' => ['Customer', 'Loans', 'EAD', 'ECL', '% of Book'],
                 'align' => ['l', 'r', 'r', 'r', 'r'], 'rows' => $topRows],
                ['heading' => 'Portfolio Concentration (HHI = ' . number_format($hhi, 0) . ')',
                 'columns' => ['Portfolio', 'EAD', '% of Book'], 'align' => ['l', 'r', 'r'], 'rows' => $portRows],
                ['heading' => 'Large Exposures ≥ ' . $this->money($threshold),
                 'columns' => ['Contract', 'Client', 'Stage', 'EAD', 'ECL'],
                 'align' => ['l', 'l', 'l', 'r', 'r'], 'rows' => $large],
            ]]);
    }

    /* ===================================================================== */
    /*  Stress Testing — interactive                                         */
    /* ===================================================================== */

    /**
     * Retired (Ticket #003): the hub Sensitivity tile duplicated the
     * standalone Stress Testing engine. Its macro mode now lives there
     * too, so old links land on the consolidated module.
     */
    public function sensitivity(Request $request)
    {
        return redirect()->route('stress-testing.index');
    }


    /* ===================================================================== */
    /*  Analytics                                                            */
    /* ===================================================================== */

    public function ews(Request $request)
    {
        $period = $this->period($request);
        $prev = $this->previousPeriod($period);

        $s1arrears = DB::table('loan_books')->where('reporting_period', $period)
            ->where('ifrs9stage_pre_qualitative', 1)->where('overdue_days', '>', 0)
            ->selectRaw("COUNT(*) n, SUM(" . self::EAD_SQL . ") ead")->first();

        $highUtil = DB::table('loan_books')->where('reporting_period', $period)
            ->where('facility_utilisation_rate', '>=', 0.9)
            ->selectRaw("COUNT(*) n, SUM(" . self::EAD_SQL . ") ead")->first();

        $migrated = 0;
        if ($prev) {
            $migrated = DB::table('loan_books as c')
                ->join('loan_books as p', function ($j) use ($prev) {
                    $j->on('c.contract_id', '=', 'p.contract_id')->where('p.reporting_period', '=', $prev);
                })
                ->where('c.reporting_period', $period)
                ->where('p.ifrs9stage_pre_qualitative', 1)
                ->where('c.ifrs9stage_pre_qualitative', 2)->count();
        }

        $watch = DB::table('loan_books')->where('reporting_period', $period)
            ->whereIn('ifrs9stage_pre_qualitative', [1, 2])
            ->where('overdue_days', '>', 0)
            ->selectRaw("contract_id, customer_name, ifrs9stage_pre_qualitative s,
                overdue_days dpd, " . self::EAD_SQL . " ead")
            ->orderByDesc(DB::raw(self::EAD_SQL))->limit(40)->get()
            ->map(fn ($r) => [$r->contract_id, $r->customer_name ?: '(Unnamed)', 'Stage ' . $r->s,
                number_format($r->dpd), $this->money($r->ead),
                $r->dpd >= 60 ? 'HIGH' : ($r->dpd >= 30 ? 'MEDIUM' : 'WATCH')])->all();

        return $this->respond(['key' => 'ews', 'period' => $period,
            'subtitle' => 'Forward-looking risk signals to act before accounts default',
            'kpis' => [
                ['label' => 'Stage 1 in Arrears', 'value' => number_format($s1arrears->n ?? 0), 'tone' => 'amber'],
                ['label' => 'S1 Arrears Exposure', 'value' => $this->money($s1arrears->ead ?? 0), 'tone' => 'rose'],
                ['label' => 'High Utilisation (>=90%)', 'value' => number_format($highUtil->n ?? 0), 'tone' => 'amber'],
                ['label' => 'New S1->S2 Migrations', 'value' => number_format($migrated), 'tone' => 'rose'],
            ],
            'sections' => [[
                'heading' => 'Early-Warning Watchlist (largest at-risk performing exposures)',
                'columns' => ['Contract', 'Client', 'Stage', 'Days Past Due', 'Exposure', 'Severity'],
                'align' => ['l', 'l', 'l', 'r', 'r', 'l'],
                'rows' => $watch,
            ]]]);
    }

    public function aiNarrative(Request $request)
    {
        $period = $this->period($request);
        $prev = $this->previousPeriod($period);
        $t = $this->periodTotals($period);
        $p = $prev ? $this->periodTotals($prev) : null;

        $cov = ($t->ead ?? 0) ? ($t->ecl / $t->ead) : 0;
        $stages = DB::table('loan_books')->where('reporting_period', $period)
            ->selectRaw("ifrs9stage_pre_qualitative s, SUM(" . self::EAD_SQL . ") ead")
            ->groupBy('ifrs9stage_pre_qualitative')->pluck('ead', 's');
        $s3 = (float) ($stages[3] ?? 0);
        $nplRatio = ($t->ead ?? 0) ? $s3 / $t->ead : 0;

        $delta = $p ? ($t->ecl - $p->ecl) : 0;
        $deltaPct = ($p && $p->ecl) ? $delta / $p->ecl : 0;

        $story = [];
        $story[] = "For the reporting period {$period}, MAIIC's total exposure at default stands at "
            . $this->money($t->ead) . " across " . number_format($t->loans) . " facilities, against an IFRS 9 "
            . "expected credit loss allowance of " . $this->money($t->ecl) . " (coverage ratio " . $this->pct($cov) . ").";
        if ($p) {
            $dir = $delta >= 0 ? 'increased' : 'decreased';
            $story[] = "The ECL allowance {$dir} by " . $this->money(abs($delta)) . " (" . $this->pct(abs($deltaPct))
                . ") versus {$prev}, recognised as a " . ($delta >= 0 ? 'charge to' : 'release from') . " profit or loss.";
        }
        $story[] = "Stage 3 (credit-impaired) exposure is " . $this->money($s3) . ", an NPL ratio of "
            . $this->pct($nplRatio) . ". " . ($nplRatio > 0.10
                ? "This is elevated and warrants intensified collections and provisioning review."
                : "This remains within a manageable range.");
        $story[] = $cov > 0.15
            ? "Overall coverage is conservative relative to the book's risk profile."
            : "Management should confirm coverage adequately reflects forward-looking risk and any required overlays.";
        $story[] = "This commentary is auto-generated from the calculated ECL data and supports — not replaces — "
            . "management and audit judgement.";

        return $this->respond(['key' => 'ai-narrative', 'period' => $period,
            'subtitle' => 'Auto-generated executive commentary (rule-based AI assistant)',
            'kpis' => $this->totalsKpis($period),
            'sections' => [[
                'heading' => 'AI Executive Commentary',
                'columns' => ['#', 'Commentary'],
                'align' => ['l', 'l'],
                'rows' => collect($story)->values()->map(fn ($s, $i) => [(string) ($i + 1), $s])->all(),
            ]]]);
    }

    /* ===================================================================== */
    /*  Helpers                                                              */
    /* ===================================================================== */

    /**
     * RBM Financial Asset Classification Directive (2018) — 5 categories by
     * days past due, with minimum provisioning rates. NPL = Substandard +
     * Doubtful + Loss (90+ DPD).
     */
    private const RBM = [
        'Pass'            => ['min' => 0,   'max' => 30,    'rate' => 0.01],
        'Special Mention' => ['min' => 31,  'max' => 89,    'rate' => 0.01],
        'Substandard'     => ['min' => 90,  'max' => 179,   'rate' => 0.20],
        'Doubtful'        => ['min' => 180, 'max' => 364,   'rate' => 0.50],
        'Loss'            => ['min' => 365, 'max' => 999999, 'rate' => 1.00],
    ];

    /** SQL CASE that maps overdue_days to the RBM class label. */
    private function rbmClassCase(): string
    {
        return "CASE
            WHEN COALESCE(overdue_days,0) <= 30  THEN 'Pass'
            WHEN overdue_days <= 89              THEN 'Special Mention'
            WHEN overdue_days <= 179             THEN 'Substandard'
            WHEN overdue_days <= 364             THEN 'Doubtful'
            ELSE 'Loss' END";
    }

    private function rbmRateForClass(string $class): float
    {
        return self::RBM[$class]['rate'] ?? 0.0;
    }

    /** Indicative IFRS 9 stage <-> RBM class cross-reference. */
    private function rbmClass(string $stage): string
    {
        return ['1' => 'Pass', '2' => 'Special Mention', '3' => 'Non-Performing'][$stage] ?? 'Unclassified';
    }

    private function rbmRate(string $stage): float
    {
        return ['1' => 0.01, '2' => 0.01, '3' => 0.50][$stage] ?? 0.0;
    }

    private function rbmBuild(string $period): array
    {
        $raw = DB::table('loan_books')->where('reporting_period', $period)
            ->selectRaw($this->rbmClassCase() . " rbm, COUNT(*) n, SUM(" . self::EAD_SQL . ") ead,
                SUM(COALESCE(ecl_value,0)) ecl")
            ->groupBy('rbm')->get()->keyBy('rbm');

        $rows = [];
        $totProv = 0;
        $totEcl  = 0;
        $nplEad  = 0;
        $totEad  = 0;
        foreach (self::RBM as $class => $def) {
            $r    = $raw->get($class);
            $n    = (int) ($r->n ?? 0);
            $ead  = (float) ($r->ead ?? 0);
            $ecl  = (float) ($r->ecl ?? 0);
            $prov = $ead * $def['rate'];
            $totProv += $prov;
            $totEcl  += $ecl;
            $totEad  += $ead;
            if (in_array($class, ['Substandard', 'Doubtful', 'Loss'], true)) {
                $nplEad += $ead;
            }
            $rows[] = [$class, number_format($n), $this->money($ead),
                $this->pct($def['rate']), $this->money($prov), $this->money($ecl),
                $this->money($ecl - $prov)];
        }

        return [
            'subtitle' => 'Prudential asset classification by days past due (RBM Financial Asset Classification Directive, 2018)',
            'kpis' => [
                ['label' => 'NPL Ratio (90+ DPD)', 'value' => $this->pct($totEad ? $nplEad / $totEad : 0), 'tone' => 'rose'],
                ['label' => 'RBM Provision', 'value' => $this->money($totProv), 'tone' => 'amber'],
                ['label' => 'IFRS 9 ECL', 'value' => $this->money($totEcl), 'tone' => 'rose'],
                ['label' => 'ECL − RBM', 'value' => $this->money($totEcl - $totProv), 'tone' => ($totEcl - $totProv) >= 0 ? 'emerald' : 'rose'],
            ],
            'sections' => [[
                'heading' => 'RBM Asset Classification (by Days Past Due)',
                'columns' => ['RBM Class', 'Loans', 'Exposure (EAD)', 'RBM Rate', 'RBM Provision', 'IFRS 9 ECL', 'ECL − RBM'],
                'align' => ['l', 'r', 'r', 'r', 'r', 'r', 'r'],
                'rows' => $rows,
            ]],
        ];
    }

    private function previousPeriod(?string $period): ?string
    {
        $periods = $this->periods();
        $i = array_search($period, $periods, true);
        return ($i !== false && isset($periods[$i + 1])) ? $periods[$i + 1] : null;
    }

    private function periodTotals(?string $period)
    {
        return DB::table('loan_books')
            ->selectRaw("COUNT(*) loans, SUM(" . self::EAD_SQL . ") ead, SUM(COALESCE(ecl_value,0)) ecl")
            ->where('reporting_period', $period)
            ->first();
    }

    /** The reporting period immediately before $period (from the period list). */
    private function priorPeriod(?string $period): ?string
    {
        if (! $period) {
            return null;
        }
        $periods = $this->periods();              // desc order
        $i = array_search($period, $periods, true);
        return ($i !== false && isset($periods[$i + 1])) ? $periods[$i + 1] : null;
    }

    /** "▲ 12.3% vs <prior>" / "▼ ..." / "no prior period". */
    private function deltaSub(?float $current, ?float $prior, ?string $priorLabel): string
    {
        if ($priorLabel === null) {
            return 'no prior period';
        }
        if (! $prior) {
            return 'vs ' . $priorLabel . ' (n/a)';
        }
        $pct = (($current - $prior) / $prior) * 100;
        $arrow = $pct > 0.05 ? '▲' : ($pct < -0.05 ? '▼' : '►');
        return $arrow . ' ' . number_format(abs($pct), 1) . '% vs ' . $priorLabel;
    }

    private function totalsKpis(?string $period): array
    {
        $t  = $this->periodTotals($period);
        $pp = $this->priorPeriod($period);
        $p  = $pp ? $this->periodTotals($pp) : null;

        $cov  = ($t->ead ?? 0) ? $t->ecl / $t->ead : 0;
        $pcov = ($p && ($p->ead ?? 0)) ? $p->ecl / $p->ead : 0;

        return [
            ['label' => 'Exposure (EAD)', 'value' => $this->money($t->ead ?? 0), 'tone' => 'maiic',
             'sub' => $this->deltaSub((float) ($t->ead ?? 0), $p ? (float) $p->ead : null, $pp)],
            ['label' => 'ECL Provision',  'value' => $this->money($t->ecl ?? 0), 'tone' => 'rose',
             'sub' => $this->deltaSub((float) ($t->ecl ?? 0), $p ? (float) $p->ecl : null, $pp)],
            ['label' => 'Coverage Ratio', 'value' => $this->pct($cov), 'tone' => 'amber',
             'sub' => $this->deltaSub($cov, $p ? $pcov : null, $pp)],
            ['label' => 'Loans',          'value' => number_format($t->loans ?? 0), 'tone' => 'emerald',
             'sub' => $this->deltaSub((float) ($t->loans ?? 0), $p ? (float) $p->loans : null, $pp)],
        ];
    }

    private function periods(): array
    {
        if (! Schema::hasTable('expected_credit_loss')) {
            return [];
        }
        return DB::table('expected_credit_loss')
            ->select('reporting_period')->distinct()
            ->orderByDesc('reporting_period')->pluck('reporting_period')->all();
    }

    private function period(Request $request): ?string
    {
        $periods = $this->periods();
        $requested = $request->query('period');
        if ($requested && in_array($requested, $periods, true)) {
            return $requested;
        }
        return $periods[0] ?? null;
    }

    private function company(): string
    {
        try {
            return optional(Setting::where('setting_key', 'company_name')->first())->setting_value ?: config('app.name');
        } catch (\Throwable $e) {
            return config('app.name');
        }
    }

    private function respond(array $report)
    {
        [$title, $subtitle] = $this->catalogue[$report['key']];

        $report = array_merge([
            'title'        => $title,
            'subtitle'     => $subtitle,
            'company'      => $this->company(),
            'generated_at' => now()->format('d M Y H:i'),
            'generated_by' => optional(auth()->user())->name,
            'periods'      => $this->periods(),
            'kpis'         => [],
            'sections'     => [],
        ], $report);

        if (($report['subtitle'] ?? null) === null) {
            $report['subtitle'] = $subtitle;
        }

        $filename = 'IFRS9-' . $report['key'] . '-' . ($report['period'] ?? 'all');

        if (request()->query('download') === 'pdf') {
            return Pdf::loadView('reports.ifrs9.report', ['report' => $report])
                ->setPaper('a4', 'landscape')
                ->download($filename . '.pdf');
        }

        if (request()->query('download') === 'xlsx') {
            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\Ifrs9ReportExport($report),
                $filename . '.xlsx'
            );
        }

        return Inertia::render('Reports/Ifrs9/Report', ['report' => $report]);
    }

    private function money($v): string
    {
        return number_format((float) $v, 2);
    }

    private function num($v, int $dp = 2): string
    {
        return number_format((float) $v, $dp);
    }

    private function pct($v): string
    {
        return number_format((float) $v * 100, 2) . '%';
    }
}
