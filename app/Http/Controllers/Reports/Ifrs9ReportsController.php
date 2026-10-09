<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\ReportDownload;
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
    // Every stage split reads ifrs9stage_post_qualitative, the stage the staging engine
    // measured and the ECL was provided on (audit H5); the DPD-only stage is ifrs9stage_pre_qualitative.
    private const EAD_SQL = 'COALESCE(carrying_amount,0) + COALESCE(commitments,0) * COALESCE(facility_utilisation_rate,1)';

    // key => [title, subtitle]. The title and subtitle head the report page,
    // its PDF and its workbook. Where each report sits in the hub, and the
    // question its tile answers, is set in hub() below.
    private array $catalogue = [
        'executive'            => ['Executive summary',               'The ECL position on one page: headline figures, stage split, portfolios, largest exposures and data quality'],
        'ecl'                  => ['ECL by stage',                    'ECL, PD and LGD by stage, how every exposure is staged, and the PD applied'],
        'portfolio-trend'      => ['Portfolio ECL trend',             'ECL and coverage over time, by portfolio'],
        'sector-ecl'           => ['ECL by segment',                  'Exposure and ECL by sector, product group and internal grade, with concentration and cooperative links'],
        'product-group-ecl'    => ['ECL by product group',            'Exposure and ECL by lending product group'],
        'grade-ecl'            => ['ECL by internal grade',           'Exposure, PD, LGD and ECL on the internal risk-grade scale'],
        'account-ecl'          => ['Account-level ECL calculation',   'Loan-by-loan EAD x PD x LGD calculation trail'],
        'stage-allocation'     => ['Stage allocation',                'How every exposure is classified into Stage 1, 2 and 3'],
        'sicr-trigger'         => ['SICR triggers',                   'What moved loans into Stage 2 (significant increase in credit risk)'],
        'stage-migration'      => ['Stage movement and SICR',         'Stage movement against the prior month, and the triggers that moved loans into Stage 2'],
        'ecl-reconciliation'   => ['Opening to closing ECL',          'The ECL bridge from the prior period to this one, by stage'],
        'gross-movement'       => ['Gross carrying amount movement',  'Opening balance, disbursements, repayments and closing balance'],
        'ecl-charge'           => ['ECL charge or release',           'The impairment charge or release to profit or loss'],
        'pd-report'            => ['PD report',                       '12-month and lifetime probability of default'],
        'lgd-collateral'       => ['Model inputs',                    'LGD and collateral, agricultural risk cover, and EAD including undrawn commitments'],
        'crm-agri'             => ['Credit risk mitigation (agri)',   'Off-take, warehouse-receipt, group-guarantee and AIP cover against LGD'],
        'ead-report'           => ['EAD and off-balance sheet',       'Exposure at default including undrawn commitments'],
        'macro-scenario'       => ['Forward-looking and scenarios',   'Macro-economic scenarios and the probability-weighted ECL'],
        'scenario-ecl'         => ['Scenario-weighted ECL',           'Probability-weighted ECL across the scenarios'],
        'rbm-classification'   => ['RBM classification and provisioning', 'RBM classes, IFRS 9 stage against class, provision comparison, NPLs and arrears'],
        'ifrs9-vs-rbm'         => ['IFRS 9 stage against RBM class',  'How the IFRS 9 stages map to the RBM classes'],
        'npl-arrears'          => ['NPL and arrears',                 'Non-performing loans and arrears ageing'],
        'provision-comparison' => ['Provision comparison',            'IFRS 9 ECL against the RBM prudential provision, and any shortfall'],
        'concentration'        => ['Concentration and large exposures', 'Single-name and portfolio concentration (HHI) and large exposures'],
        'coop-linkage'         => ['Cooperative and anchor linkage',  'Correlated exposure by cooperative or anchor buyer'],
        'fs-disclosure'        => ['IFRS 9 note (annual financial statements)', 'Loss allowance and gross carrying amount reconciliations by stage, position, charge and basis'],
        // The user-action audit trail lives at Administration > Audit Trail;
        // this report is the data-integrity view.
        'data-quality'         => ['Data quality and exceptions',     'Data integrity, overrides and exception checks'],
        'ews'                  => ['Early warning signals',           'Forward risk signals and the watchlist, before default'],
        'ai-narrative'         => ['Executive commentary',            'A written commentary on the ECL position, generated from the calculated figures'],
    ];

    /* ===================================================================== */
    /*  Hub                                                                  */
    /* ===================================================================== */

    /**
     * Every report screen of the system, grouped as the hub shows it. A tile
     * names its route, the permission that route enforces (the hub hides a
     * tile the user cannot open), whether it takes the hub's period, the
     * question it answers and the downloads it offers.
     */
    private function hub(): array
    {
        $std = ['PDF', 'Excel', 'CSV'];
        $r = fn (string $key, string $icon, string $question, array $absorbs = []) => [
            'key' => $key, 'title' => $this->catalogue[$key][0], 'description' => $question, 'icon' => $icon,
            'route' => 'ifrs9-reports.' . $key, 'period' => true, 'permission' => 'reports.ifrs9', 'formats' => $std,
            'absorbs' => $absorbs,
        ];
        $screen = fn (string $key, string $title, string $route, string $permission, string $icon, string $question, array $formats, bool $period = false, array $absorbs = []) => [
            'key' => $key, 'title' => $title, 'description' => $question, 'icon' => $icon,
            'route' => $route, 'period' => $period, 'permission' => $permission, 'formats' => $formats,
            'absorbs' => $absorbs,
        ];

        return [
            ['key' => 'month-end', 'name' => 'Month-end ECL', 'description' => 'The ECL position for a month: where it stands, where it sits, how it moved and the inputs behind it.', 'reports' => [
                $r('executive', 'star', 'Where does the ECL stand this month? Headline figures, stages, portfolios, largest exposures, data quality and a written commentary.', ['ai-narrative']),
                $r('ecl', 'layers', 'How much ECL is held in each stage, how the book is staged, and on what PD and LGD?', ['stage-allocation', 'pd-report']),
                $r('sector-ecl', 'pie', 'Where do exposure and ECL sit: by sector, product group and internal grade, with concentration and cooperative links?', ['product-group-ecl', 'grade-ecl', 'concentration', 'coop-linkage']),
                $r('portfolio-trend', 'trend', 'How have ECL and coverage moved month by month, by portfolio?'),
                $r('account-ecl', 'list', 'How was the ECL worked out for each loan (EAD x PD x LGD)? Top 200 by exposure.'),
                $r('stage-migration', 'arrows', 'Which loans changed stage since the prior month, and which triggers put loans into Stage 2?', ['sicr-trigger']),
                $r('lgd-collateral', 'shield', 'What LGD, collateral cover, agricultural risk cover and EAD sit behind the ECL?', ['crm-agri', 'ead-report']),
                $r('macro-scenario', 'globe', 'Which macro-economic scenarios feed the ECL, and what is the probability-weighted result?', ['scenario-ecl']),
            ]],
            ['key' => 'annual', 'name' => 'Annual report and audit', 'description' => 'The IFRS 9 note for the financial statements and the reconciliations the auditors ask for.', 'reports' => [
                $screen('fs-disclosure', 'IFRS 9 note (annual financial statements)', 'ifrs9-reports.fs-disclosure', 'reports.ifrs9', 'document',
                    'The loss allowance and gross carrying amount reconciliations by stage, the position with the comparative, the charge and the basis of measurement, between any two months.',
                    ['PDF', 'Word', 'Excel', 'CSV'], false, ['ecl-reconciliation', 'gross-movement', 'ecl-charge', 'ecl-stage-reconciliation']),
                $screen('loan-book-reconciliation', 'Loan book reconciliation', 'reports.loan-book-reconciliation', 'reports', 'book',
                    'Does opening balance plus disbursements less repayments and write-offs agree to the closing book?', ['PDF', 'Excel', 'CSV']),
                $r('data-quality', 'check', 'Which records are missing data, overridden or out of line?'),
            ]],
            ['key' => 'regulatory', 'name' => 'Regulatory (RBM)', 'description' => 'The Reserve Bank of Malawi return and the classification and provisioning views behind it.', 'reports' => [
                $screen('rbm-return', 'RBM return (provisional)', 'rbm-return.index', 'reports.ifrs9', 'bank',
                    "The classification and provisioning return in the directive's order: classes, minimum provisions, interest in suspense and security.", ['PDF', 'Excel', 'CSV'], true),
                $r('rbm-classification', 'bank', 'How is the book classified under the RBM directive, how do stages map to classes, is the ECL above the minimum provision, and how large are NPLs and arrears?',
                    ['ifrs9-vs-rbm', 'provision-comparison', 'npl-arrears']),
            ]],
            ['key' => 'eir', 'name' => 'EIR', 'description' => 'Effective interest rate revenue and its reconciliation to the ledger.', 'reports' => [
                $screen('eir-as-at', 'EIR as at a date', 'eir-as-at.index', 'eir.view', 'calendar',
                    'What were the EIR interest, the contractual interest and the amortised cost at any date, by product, GL and contract?', ['PDF', 'Excel', 'CSV']),
                $screen('eir-reconciliation', 'GL reconciliation (EIR)', 'eir-reconciliation.index', 'eir.view', 'scale',
                    'Does the interest posted in the ledger agree with what each contract charges, and why not?', ['PDF', 'Excel']),
            ]],
            ['key' => 'risk', 'name' => 'Risk and analytics', 'description' => 'What if, and what next: stress scenarios and early warnings.', 'reports' => [
                $screen('stress-testing', 'Stress testing', 'stress-testing.index', 'reports.ifrs9', 'bolt',
                    'How much would ECL rise under a drought, a currency shock or a macro scenario?', ['PDF', 'Excel', 'CSV']),
                $r('ews', 'alert', 'Which loans show warning signs before they default?'),
            ]],
            ['key' => 'exports', 'name' => 'Exports', 'description' => "Data exports and the auditor's pack.", 'reports' => [
                $screen('loan-book-export', 'Loan book export', 'reports.loan-book-export', 'reports', 'download',
                    'The loan book between two periods, as a summary or loan by loan.', ['CSV']),
                $screen('ecl-export', 'ECL export', 'reports.ecl-export', 'reports', 'download',
                    'ECL by stage, or the full loan book with PD, LGD and ECL, for one period.', ['CSV']),
                $screen('disbursement-report', 'Disbursements (vintage)', 'reports.disbursement-report', 'reports', 'cash',
                    'How much was disbursed each month, and how much of it was still outstanding one, two and three months later?', ['CSV']),
                $screen('auditor-pack', 'Auditor pack', 'auditor-pack.index', 'eir.export', 'archive',
                    'One zip per period with the compliance workbooks, the EIR book, the baselines and the ECL by stage, each file with its SHA-256.', ['ZIP']),
            ]],
        ];
    }

    public function index()
    {
        $user = auth()->user();
        $categories = collect($this->hub())
            ->map(function ($cat) use ($user) {
                $cat['reports'] = collect($cat['reports'])
                    ->filter(fn ($t) => $user && $user->can($t['permission']))
                    ->values()->all();

                return $cat;
            })
            ->filter(fn ($cat) => count($cat['reports']) > 0)
            ->values();

        return Inertia::render('Reports/Ifrs9/Index', [
            'categories' => $categories,
            'periods'    => $this->periods(),
            'company'    => $this->company(),
        ]);
    }

    /* ===================================================================== */
    /*  Consolidated reports: one report, several parts                      */
    /* ===================================================================== */
    //
    // A consolidated report runs each part's own builder for the same period
    // (so every figure is exactly what that report showed on its own) and
    // shows the parts one after the other: as tabs on screen, as headed
    // parts in the PDF and as separate sheets in the Excel workbook. The old
    // routes of the parts still answer on their own.

    private bool $collecting = false;

    public function executiveSummary(Request $request)
    {
        return $this->merged('executive', $request, [
            ['executivePart', 'Executive summary'],
            ['aiNarrative', 'Executive commentary'],
        ]);
    }

    public function ecl(Request $request)
    {
        return $this->merged('ecl', $request, [
            ['eclPart', 'ECL summary by stage'],
            ['stageAllocation', 'Stage allocation'],
            ['pdReport', 'PD by stage'],
        ]);
    }

    public function sectorEcl(Request $request)
    {
        return $this->merged('sector-ecl', $request, [
            ['sectorEclPart', 'By sector'],
            ['productGroupEcl', 'By product group'],
            ['gradeEcl', 'By internal grade'],
            ['concentration', 'Concentration and large exposures'],
            ['coopLinkage', 'Cooperative and anchor links'],
        ]);
    }

    public function stageMigration(Request $request)
    {
        return $this->merged('stage-migration', $request, [
            ['stageMigrationPart', 'Stage migration'],
            ['sicrTrigger', 'SICR triggers'],
        ]);
    }

    public function lgdCollateral(Request $request)
    {
        return $this->merged('lgd-collateral', $request, [
            ['lgdCollateralPart', 'LGD and collateral'],
            ['crmAgri', 'Agricultural risk cover'],
            ['eadReport', 'EAD and off-balance sheet'],
        ]);
    }

    public function macroScenario(Request $request)
    {
        return $this->merged('macro-scenario', $request, [
            ['macroScenarioPart', 'Macro scenarios'],
            ['scenarioEcl', 'Scenario-weighted ECL'],
        ]);
    }

    public function rbmClassification(Request $request)
    {
        return $this->merged('rbm-classification', $request, [
            ['rbmClassificationPart', 'RBM classification'],
            ['ifrs9VsRbm', 'IFRS 9 stage against RBM class'],
            ['provisionComparison', 'Provision comparison'],
            ['nplArrears', 'NPL and arrears'],
        ]);
    }

    /** One part: the report's own payload, built without responding. */
    private function part(string $method, Request $request, string $title): array
    {
        $this->collecting = true;
        try {
            $report = $this->{$method}($request);
        } finally {
            $this->collecting = false;
        }
        $report['title'] = $title;

        return $report;
    }

    private function merged(string $key, Request $request, array $parts)
    {
        $built = array_map(fn ($p) => $this->part($p[0], $request, $p[1]), $parts);
        $first = $built[0];
        $sections = [];
        $controls = null;
        foreach ($built as $i => $p) {
            $own = [];
            // A part whose headline figures differ from the report's keeps them as a small table.
            if ($i > 0 && ! empty($p['kpis']) && $p['kpis'] != $first['kpis']) {
                $own[] = ['heading' => 'Key figures', 'columns' => ['Figure', 'Value', 'Note'], 'align' => ['l', 'r', 'l'],
                    'rows' => array_map(fn ($k) => [$k['label'], (string) $k['value'], (string) ($k['sub'] ?? '')], $p['kpis'])];
            }
            foreach (array_merge($own, $p['sections']) as $s) {
                $s['part'] = $p['title'];
                $s['part_note'] = $p['subtitle'] ?? '';
                $sections[] = $s;
            }
            if (! empty($p['controls'])) {
                $controls = array_merge($p['controls'], ['action' => 'ifrs9-reports.' . $key, 'part' => $p['title']]);
            }
        }

        return $this->respond([
            'key' => $key,
            'period' => $first['period'] ?? $this->period($request),
            'kpis' => $first['kpis'],
            'sections' => $sections,
            'controls' => $controls,
            'parts' => array_map(fn ($p) => [
                'title' => $p['title'],
                'subtitle' => $p['subtitle'] ?? '',
                'rows' => array_sum(array_map(fn ($s) => count($s['rows'] ?? []), $p['sections'] ?? [])),
            ], $built),
            'sheets' => $built,
        ]);
    }

    /* ===================================================================== */
    /*  Core ECL                                                             */
    /* ===================================================================== */

    public function eclPart(Request $request)
    {
        $period = $this->period($request);

        $rows = DB::table('expected_credit_loss')
            ->where('reporting_period', $period)
            ->orderBy('ecl_calculation_level')->orderBy('ifrs9_stage')
            ->get()
            ->map(fn ($r) => [
                ucfirst($r->ecl_calculation_level ?? '-'),
                'Stage ' . $r->ifrs9_stage,
                number_format($r->total_loans),
                $this->money($r->total_ead),
                $this->num($r->pd_value_used, 6),
                $this->num($r->lgd_value_used, 6),
                $this->money($r->total_ecl),
                $this->pct((float) $r->total_ead != 0.0 ? $r->total_ecl / $r->total_ead : 0),
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
            ->selectRaw("contract_id, customer_name, ifrs9stage_post_qualitative stage,
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
            ->selectRaw("ifrs9stage_post_qualitative stage, COUNT(*) loans,
                SUM(" . self::EAD_SQL . ") ead, SUM(COALESCE(ecl_value,0)) ecl,
                SUM(CASE WHEN COALESCE(overdue_days,0)=0 THEN 1 ELSE 0 END) current_n,
                SUM(CASE WHEN COALESCE(overdue_days,0)>0 THEN 1 ELSE 0 END) arrears_n")
            ->where('reporting_period', $period)
            ->groupBy('ifrs9stage_post_qualitative')->orderBy('ifrs9stage_post_qualitative')->get()
            ->map(fn ($r) => ['Stage ' . $r->stage, number_format($r->loans),
                number_format($r->current_n), number_format($r->arrears_n),
                $this->money($r->ead), $this->money($r->ecl),
                $this->pct((float) $r->ead != 0.0 ? $r->ecl / $r->ead : 0)])->all();

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
            ->where('ifrs9stage_post_qualitative', 2)
            ->groupBy('trig')->get()
            ->map(fn ($r) => [$r->trig, number_format($r->loans), $this->money($r->ead), $this->money($r->ecl)])->all();

        $s2 = DB::table('loan_books')->where('reporting_period', $period)
            ->where('ifrs9stage_post_qualitative', 2)->count();

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

    public function stageMigrationPart(Request $request)
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
            ->selectRaw("p.ifrs9stage_post_qualitative from_s, c.ifrs9stage_post_qualitative to_s,
                COUNT(*) n")
            ->groupBy('p.ifrs9stage_post_qualitative', 'c.ifrs9stage_post_qualitative')->get();

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
            ->selectRaw("ifrs9stage_post_qualitative s, SUM(COALESCE(ecl_value,0)) ecl")
            ->where('reporting_period', $period)->groupBy('ifrs9stage_post_qualitative')->pluck('ecl', 's');

        return $this->respond(['key' => 'ecl-reconciliation', 'period' => $period,
            'subtitle' => $prev ? "Opening {$prev} -> Closing {$period}" : 'No prior period, closing only',
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
            ->selectRaw("ifrs9stage_post_qualitative s, COUNT(*) n,
                SUM(COALESCE(carrying_amount,0)) ca, SUM(COALESCE(disbursed,0)) disb,
                SUM(COALESCE(repayments,0)) rep")
            ->groupBy('ifrs9stage_post_qualitative')->orderBy('ifrs9stage_post_qualitative')->get();

        $prevByStage = $prev ? DB::table('loan_books')->where('reporting_period', $prev)
            ->selectRaw("ifrs9stage_post_qualitative s, SUM(COALESCE(carrying_amount,0)) ca")
            ->groupBy('ifrs9stage_post_qualitative')->pluck('ca', 's') : collect();

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

        $cur = DB::table('loan_books')->selectRaw("ifrs9stage_post_qualitative s, SUM(COALESCE(ecl_value,0)) ecl")
            ->where('reporting_period', $period)->groupBy('ifrs9stage_post_qualitative')->pluck('ecl', 's');
        $pre = $prev ? DB::table('loan_books')->selectRaw("ifrs9stage_post_qualitative s, SUM(COALESCE(ecl_value,0)) ecl")
            ->where('reporting_period', $prev)->groupBy('ifrs9stage_post_qualitative')->pluck('ecl', 's') : collect();

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
            ->selectRaw("ifrs9stage_post_qualitative s, COUNT(*) n,
                AVG(COALESCE(lifetime_pd,0)) pdlt,
                AVG(COALESCE(pd_prefli,0)) pdpre, AVG(COALESCE(pd_post_fli,0)) pdpost")
            ->where('reporting_period', $period)
            ->groupBy('ifrs9stage_post_qualitative')->orderBy('ifrs9stage_post_qualitative')->get()
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

    public function lgdCollateralPart(Request $request)
    {
        $period = $this->period($request);

        // Collateral comes from the collateral allocations of the period
        // (collateral management). Older databases carried the allocated
        // values on loan_books; use those only where the columns exist.
        if (Schema::hasColumn('loan_books', 'allocated_gross_value')) {
            $query = DB::table('loan_books as lb')
                ->selectRaw('lb.ifrs9stage_post_qualitative s, COUNT(*) n,
                    SUM(' . self::EAD_SQL . ') ead,
                    SUM(COALESCE(lb.allocated_gross_value,0)) coll_gross,
                    SUM(COALESCE(lb.allocated_discounted_value,0)) coll_disc,
                    AVG(COALESCE(lb.customer_lgd,0)) clgd, AVG(COALESCE(lb.collection_lgd,0)) collgd');
        } else {
            [$y, $m] = array_map('intval', explode('-', (string) $period) + [0, 0]);
            $alloc = DB::table('collateral_allocations')
                ->selectRaw('contract_id, SUM(allocated_collateral) gross, SUM(discounted_collateral) disc')
                ->where('reporting_year', $y)->where('reporting_month', $m)
                ->groupBy('contract_id');
            $query = DB::table('loan_books as lb')
                ->leftJoinSub($alloc, 'ca', 'ca.contract_id', '=', 'lb.contract_id')
                ->selectRaw('lb.ifrs9stage_post_qualitative s, COUNT(*) n,
                    SUM(' . self::EAD_SQL . ') ead,
                    SUM(COALESCE(ca.gross,0)) coll_gross,
                    SUM(COALESCE(ca.disc,0)) coll_disc,
                    AVG(COALESCE(lb.customer_lgd,0)) clgd, AVG(COALESCE(lb.collection_lgd,0)) collgd');
        }

        $rows = $query->where('lb.reporting_period', $period)
            ->groupBy('lb.ifrs9stage_post_qualitative')->orderBy('lb.ifrs9stage_post_qualitative')->get()
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
                'heading' => 'LGD and collateral by stage',
                'columns' => ['Stage', 'Loans', 'EAD', 'Collateral (gross)', 'Collateral (discounted)', 'Net unsecured', 'Avg customer LGD', 'Avg collection LGD'],
                'align' => ['l', 'r', 'r', 'r', 'r', 'r', 'r', 'r'],
                'rows' => $rows,
            ]]]);
    }

    public function eadReport(Request $request)
    {
        $period = $this->period($request);
        $rows = DB::table('loan_books')
            ->selectRaw("ifrs9stage_post_qualitative s, COUNT(*) n,
                SUM(COALESCE(carrying_amount,0)) ca, SUM(COALESCE(commitments,0)) comm,
                AVG(COALESCE(facility_utilisation_rate,1)) ccf, SUM(" . self::EAD_SQL . ") ead")
            ->where('reporting_period', $period)
            ->groupBy('ifrs9stage_post_qualitative')->orderBy('ifrs9stage_post_qualitative')->get()
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

    public function macroScenarioPart(Request $request)
    {
        $period = $this->period($request);

        $macro = [];
        foreach (['macro_economic_variables', 'macro_economic_data', 'macroeconomic_variables', 'macro_variables'] as $tbl) {
            if (Schema::hasTable($tbl)) {
                try {
                    $macro = DB::table($tbl)->orderByDesc('id')->limit(40)->get()
                        ->map(fn ($r) => [$r->name ?? $r->variable ?? '-',
                            $r->period ?? $r->reporting_period ?? '-',
                            isset($r->value) ? $this->num($r->value, 4) : '-'])->all();
                } catch (\Throwable $e) {
                    $macro = [];
                }
                break;
            }
        }
        if (empty($macro)) {
            $macro = [['No macro-economic variables table found for this install', '-', '-']];
        }

        $scenarios = [];
        if (Schema::hasTable('scenario_sets')) {
            $scenarios = DB::table('scenario_sets')
                ->leftJoin('scenario_probabilities as sp', 'sp.scenario_set_id', '=', 'scenario_sets.id')
                ->selectRaw('scenario_sets.name set_name, sp.scenario_name, sp.probability')
                ->orderBy('scenario_sets.id')->get()
                ->map(fn ($r) => [$r->set_name, $r->scenario_name ?? '-',
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
                 'rows' => $scenarios ?: [['No scenario sets defined', '-', '-']]],
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
                ->map(fn ($r) => [$r->name, $r->scenario_name ?? '-', $this->pct(($r->probability ?? 0) / 100)])->all();
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
                'rows' => $sets ?: [['No scenario sets defined', '-', '-']],
            ]]]);
    }

    /* ===================================================================== */
    /*  RBM Prudential                                                       */
    /* ===================================================================== */

    public function rbmClassificationPart(Request $request)
    {
        $period = $this->period($request);
        return $this->respond(array_merge(['key' => 'rbm-classification', 'period' => $period],
            $this->rbmBuild($period)));
    }

    public function ifrs9VsRbm(Request $request)
    {
        $period = $this->period($request);
        $rows = DB::table('loan_books')->where('reporting_period', $period)
            ->selectRaw("ifrs9stage_post_qualitative s, COUNT(*) n, SUM(" . self::EAD_SQL . ") ead")
            ->groupBy('ifrs9stage_post_qualitative')->orderBy('ifrs9stage_post_qualitative')->get()
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
        // the day bands are the directive's; the class a band carries depends on the term, so it is a column of its own
        $buckets = DB::table('loan_books')->where('reporting_period', $period)
            ->selectRaw("CASE
                    WHEN COALESCE(overdue_days,0) <= 30 THEN '0-30'
                    WHEN overdue_days <= 90  THEN '31-90'
                    WHEN overdue_days <= 180 THEN '91-180'
                    WHEN overdue_days <= 365 THEN '181-365'
                    WHEN overdue_days <= 746 THEN '366-746'
                    ELSE '747+' END bucket,
                " . $this->rbmClassCase() . " rbm,
                MIN(COALESCE(overdue_days,0)) ord,
                COUNT(*) n, SUM(" . self::EAD_SQL . ") ead, SUM(COALESCE(ecl_value,0)) ecl")
            ->groupBy('bucket', 'rbm')->orderBy('ord')->orderBy('rbm')->get()
            ->map(fn ($r) => [$r->bucket, $r->rbm, number_format($r->n), $this->money($r->ead), $this->money($r->ecl)])->all();

        $npl = DB::table('loan_books')->where('reporting_period', $period)
            ->where('ifrs9stage_post_qualitative', 3)
            ->selectRaw("COUNT(*) n, SUM(" . self::EAD_SQL . ") ead")->first();
        $tot = $this->periodTotals($period);

        return $this->respond(['key' => 'npl-arrears', 'period' => $period,
            'kpis' => [
                ['label' => 'NPL Exposure', 'value' => $this->money($npl->ead ?? 0), 'tone' => 'rose'],
                ['label' => 'NPL Loans', 'value' => number_format($npl->n ?? 0), 'tone' => 'amber'],
                ['label' => 'NPL Ratio', 'value' => $this->pct((float) ($tot->ead ?? 0) != 0.0 ? ($npl->ead ?? 0) / $tot->ead : 0), 'tone' => 'rose'],
                ['label' => 'Loans', 'value' => number_format($tot->loans ?? 0), 'tone' => 'emerald'],
            ],
            'sections' => [[
                'heading' => 'Arrears Ageing (days past due, with the RBM class by term of facility)',
                'columns' => ['DPD Band', 'RBM Class', 'Loans', 'Exposure (EAD)', 'ECL'],
                'align' => ['l', 'l', 'r', 'r', 'r'],
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

    /**
     * The IFRS 9 note for the annual financial statements (IFRS 7.35H, 35I,
     * 35M) between two months with a calculated ECL. It absorbs the former
     * note tables; downloads as PDF, Word, Excel or CSV only when every
     * column ties.
     */
    public function fsDisclosure(Request $request)
    {
        $months = \App\Services\Reports\Ifrs9NoteService::eclMonths();
        [$defOpen, $defClose] = \App\Services\Reports\Ifrs9NoteService::defaultPeriods($months);
        $opening = in_array($request->query('opening'), $months, true) ? $request->query('opening') : $defOpen;
        $closing = in_array($request->query('closing'), $months, true) ? $request->query('closing') : $defClose;
        $portfolioId = $request->integer('portfolio_id') ?: null;
        $unit = \App\Services\Reports\Ifrs9NoteDocuments::unitKey($request->query('unit'));

        $note = null;
        $error = null;
        if ($opening && $closing) {
            try {
                $report = \App\Services\Reports\Ifrs9NoteService::build($opening, $closing, $portfolioId);
                $note = \App\Services\Reports\Ifrs9NoteDocuments::build($report, $unit);
            } catch (\InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        } else {
            $error = count($months) < 2
                ? 'The note compares two months with a calculated ECL, and fewer than two months have one. Run the ECL calculation for another month first.'
                : 'Choose the opening and closing months.';
        }

        $format = $request->query('download');
        if (in_array($format, ['pdf', 'docx', 'xlsx', 'csv'], true)) {
            abort_if($note === null, 422, $error ?: 'The note is not available for these months.');
            \App\Services\Reports\Ifrs9NoteDocuments::assertUsable($note);

            return match ($format) {
                'pdf' => \App\Services\Reports\Ifrs9NoteDocuments::pdf($note),
                'docx' => \App\Services\Reports\Ifrs9NoteDocuments::docx($note),
                'xlsx' => \App\Services\Reports\Ifrs9NoteDocuments::excel($note),
                default => ReportDownload::csv(\App\Services\Reports\Ifrs9NoteDocuments::payload($note), basename(\App\Services\Reports\Ifrs9NoteDocuments::filename($note, 'csv'), '.csv')),
            };
        }

        return Inertia::render('Reports/Ifrs9/Note', [
            'note' => $note,
            'error' => $error,
            'months' => $months,
            'opening' => $opening,
            'closing' => $closing,
            'unit' => $unit,
            'units' => collect(\App\Services\Reports\Ifrs9NoteDocuments::units(\App\Services\Reports\Ifrs9NoteService::currency()))->map(fn ($u, $k) => ['value' => $k, 'label' => $u['label']])->values(),
            'portfolios' => DB::table('loan_portfolios')->orderBy('name')->get(['id', 'name']),
            'portfolioId' => $portfolioId,
            'tab' => collect($this->hub())->first(fn ($cat) => collect($cat['reports'])->contains('key', 'fs-disclosure'))['key'] ?? null,
        ]);
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
            ['Missing stage', number_format($b("(ifrs9stage_post_qualitative IS NULL OR ifrs9stage_post_qualitative='')"))],
            ['ECL not calculated', number_format($b('ecl_value IS NULL'))],
            ['Zero ECL on Stage 3', number_format($b("ifrs9stage_post_qualitative=3 AND COALESCE(ecl_value,0)=0"))],
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
    /*  Executive Summary, one-page composite                               */
    /* ===================================================================== */

    public function executivePart(Request $request)
    {
        $period = $this->period($request);
        $EAD    = '(' . self::EAD_SQL . ')';

        $stage = DB::table('loan_books')->where('reporting_period', $period)
            ->groupBy('ifrs9stage_post_qualitative')->orderBy('ifrs9stage_post_qualitative')
            ->selectRaw("ifrs9stage_post_qualitative s, COUNT(*) n,
                SUM($EAD) ead, SUM(COALESCE(ecl_value,0)) ecl")->get()
            ->map(fn ($r) => ['Stage ' . $r->s, number_format($r->n), $this->money($r->ead),
                $this->money($r->ecl), $this->pct((float) $r->ead != 0.0 ? $r->ecl / $r->ead : 0)])->all();

        $port = DB::table('loan_books as lb')->leftJoin('loan_portfolios as p', 'p.id', 'lb.loan_portfolio_id')
            ->where('reporting_period', $period)->groupBy('p.name')
            ->selectRaw("COALESCE(p.name,'Unmapped') name, COUNT(*) n,
                SUM($EAD) ead, SUM(COALESCE(ecl_value,0)) ecl")
            ->orderByDesc(DB::raw('SUM(COALESCE(ecl_value,0))'))->get()
            ->map(fn ($r) => [$r->name, number_format($r->n), $this->money($r->ead),
                $this->money($r->ecl), $this->pct((float) $r->ead != 0.0 ? $r->ecl / $r->ead : 0)])->all();

        $top = DB::table('loan_books')->where('reporting_period', $period)
            ->selectRaw("contract_id, customer_name, ifrs9stage_post_qualitative s,
                $EAD ead, COALESCE(ecl_value,0) ecl")
            ->orderByDesc(DB::raw($EAD))->limit(10)->get()
            ->map(fn ($r) => [$r->contract_id, $r->customer_name ?: '(Unnamed)', 'Stage ' . $r->s,
                $this->money($r->ead), $this->money($r->ecl)])->all();

        $dq = fn ($w) => DB::table('loan_books')->where('reporting_period', $period)->whereRaw($w)->count();
        $flags = [
            ['Missing sector tag', number_format($dq("(industry_type IS NULL OR industry_type='')"))],
            ['Unmapped portfolio', number_format($dq('(loan_portfolio_id IS NULL OR loan_portfolio_id = 1)'))],
            ['ECL not calculated', number_format($dq('ecl_value IS NULL'))],
            ['Zero ECL on Stage 3', number_format($dq("ifrs9stage_post_qualitative=3 AND COALESCE(ecl_value,0)=0"))],
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

    public function sectorEclPart(Request $request)
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
                $this->pct((float) $r->ead != 0.0 ? $r->ecl / $r->ead : 0)])->all();

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
                $this->money($r->ecl), $this->pct((float) $r->ead != 0.0 ? $r->ecl / $r->ead : 0)])->all();

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
     * A-G master scale (mapped from the 12-month PD). Grades are shown in
     * scale order with their PD band so the report doubles as the rating
     * scale definition.
     */
    public function gradeEcl(Request $request)
    {
        $period = $this->period($request);
        $EAD    = '(' . self::EAD_SQL . ')';

        $bands = [
            'A' => '0 - 2%', 'B' => '2 - 5%', 'C' => '5 - 10%', 'D' => '10 - 20%',
            'E' => '20 - 40%', 'F' => '40 - 100%', 'G' => 'Default (100%)',
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
     * cooperative guarantees or AIP backing, not real estate, and LGD
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
                $this->pct((float) $r->ead != 0.0 ? $r->ecl / $r->ead : 0)])->all();

        return $this->respond(['key' => 'crm-agri', 'period' => $period,
            'subtitle' => 'How the book is actually secured, and the LGD each enhancement implies, ' . $period,
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
     * group migrated wholesale to default (simplified, full asset-
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
            'subtitle' => 'Correlated exposure by cooperative / anchor buyer, ' . $period,
            'kpis' => [
                ['label' => 'Cooperative/Anchor Groups', 'value' => number_format($linked->count()), 'tone' => 'maiic'],
                ['label' => 'Largest Linked Group', 'value' => $top ? $top->coop : '-', 'tone' => 'amber'],
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
            ->selectRaw("contract_id, customer_name, ifrs9stage_post_qualitative s,
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
    /*  Stress Testing, interactive                                         */
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
            ->where('ifrs9stage_post_qualitative', 1)->where('overdue_days', '>', 0)
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
                ->where('p.ifrs9stage_post_qualitative', 1)
                ->where('c.ifrs9stage_post_qualitative', 2)->count();
        }

        $watch = DB::table('loan_books')->where('reporting_period', $period)
            ->whereIn('ifrs9stage_post_qualitative', [1, 2])
            ->where('overdue_days', '>', 0)
            ->selectRaw("contract_id, customer_name, ifrs9stage_post_qualitative s,
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
            ->selectRaw("ifrs9stage_post_qualitative s, SUM(" . self::EAD_SQL . ") ead")
            ->groupBy('ifrs9stage_post_qualitative')->pluck('ead', 's');
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
        $story[] = "This commentary is auto-generated from the calculated ECL data and supports, not replaces, "
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
     * RBM Credit Risk Management for DFIs Directive (2018): five classes by
     * days past due, the bands by the term of the facility (section 2: short
     * term is 12 months or less), the minimum provision per class (section
     * 12). The same bands and rates as RbmReturnService, so this report and
     * the RBM Return agree on every loan. NPL = Substandard + Doubtful + Loss.
     *
     *   class            short-term   medium/long   rate   (Gazette section 10, pages 683 and 685)
     *   Pass             0 to 30      0 to 90       0 %
     *   Special Mention  31 to 90     91 to 180     5 %
     *   Substandard      91 to 180    181 to 365    20 %
     *   Doubtful         181 to 365   366 to 746    50 %
     *   Loss             over 365     over 746      100 %
     */
    private const RBM = [
        'Pass'            => ['short' => [0, 30],    'long' => [0, 90],    'rate' => 0.00],
        'Special Mention' => ['short' => [31, 90],   'long' => [91, 180],  'rate' => 0.05],
        'Substandard'     => ['short' => [91, 180],  'long' => [181, 365], 'rate' => 0.20],
        'Doubtful'        => ['short' => [181, 365], 'long' => [366, 746], 'rate' => 0.50],
        'Loss'            => ['short' => [366, null], 'long' => [747, null], 'rate' => 1.00],
    ];

    /** SQL CASE that maps overdue_days and the tenor to the RBM class label, by the directive's bands by term. */
    private function rbmClassCase(): string
    {
        return "CASE
            WHEN COALESCE(overdue_days,0) <= 30 THEN 'Pass'
            WHEN COALESCE(tenor,0) <= 12 AND overdue_days <= 90  THEN 'Special Mention'
            WHEN COALESCE(tenor,0) <= 12 AND overdue_days <= 180 THEN 'Substandard'
            WHEN COALESCE(tenor,0) <= 12 AND overdue_days <= 365 THEN 'Doubtful'
            WHEN COALESCE(tenor,0) <= 12                         THEN 'Loss'
            WHEN overdue_days <= 90  THEN 'Pass'
            WHEN overdue_days <= 180 THEN 'Special Mention'
            WHEN overdue_days <= 365 THEN 'Substandard'
            WHEN overdue_days <= 746 THEN 'Doubtful'
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
            'subtitle' => 'Prudential classification by days past due under the directive\'s bands by term: 30/90/180/365 days for a facility of 12 months or less, 90/180/365/746 otherwise (RBM Credit Risk Management for DFIs Directive, 2018, section 10 and the Schedule); the same bands and rates as the RBM Return',
            'kpis' => [
                ['label' => 'NPL Ratio (substandard and below)', 'value' => $this->pct($totEad ? $nplEad / $totEad : 0), 'tone' => 'rose'],
                ['label' => 'RBM Provision', 'value' => $this->money($totProv), 'tone' => 'amber'],
                ['label' => 'IFRS 9 ECL', 'value' => $this->money($totEcl), 'tone' => 'rose'],
                ['label' => 'ECL − RBM', 'value' => $this->money($totEcl - $totProv), 'tone' => ($totEcl - $totProv) >= 0 ? 'emerald' : 'rose'],
            ],
            'sections' => [[
                'heading' => 'RBM Asset Classification (by days past due and term of facility)',
                'columns' => ['RBM Class', 'Loans', 'Exposure (EAD)', 'Minimum Rate', 'RBM Provision', 'IFRS 9 ECL', 'ECL − RBM'],
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

        if ($this->collecting) {
            return $report;
        }

        // The hub group the report sits in, so "Back to reports" reopens it.
        $report['tab'] = collect($this->hub())
            ->first(fn ($cat) => collect($cat['reports'])->contains(fn ($t) => $t['key'] === $report['key'] || in_array($report['key'], $t['absorbs'] ?? [], true)))['key'] ?? null;

        $filename = 'MAIIC-' . $report['key'] . '-' . ($report['period'] ?? 'all');

        $format = request()->query('download');
        if (in_array($format, ['pdf', 'xlsx', 'csv'], true)) {
            return ReportDownload::respond($report, $filename, $format);
        }

        unset($report['sheets']);

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
