import sys
p = sys.argv[1]
s = open(p, encoding='utf-8').read()


def rep(old, new):
    global s
    assert s.count(old) == 1, (old[:90], s.count(old))
    s = s.replace(old, new)


# ---- portfolio trend from the loan books, booked basis
rep("""        $rowsRaw = DB::table('expected_credit_loss as e')
            ->leftJoin('loan_portfolios as p', 'p.id', 'e.ecl_calculation_id')
            ->where('e.ecl_calculation_level', 'portfolio')
            ->whereIn('e.reporting_period', $periods)
            ->groupBy('e.reporting_period', 'p.name')
            ->selectRaw("e.reporting_period rp, COALESCE(p.name,'Unmapped') name, SUM(e.total_ecl) ecl")
            ->get();
""", """        // the booked allowance per portfolio from the loan books (the saved run rows hold the
        // undiscounted total and a discounted total of only the loans discounted, so they cannot
        // give the booked figure of a partly discounted month); built 14 October 2026
        $rowsRaw = DB::table('loan_books as lb')
            ->leftJoin('loan_portfolios as p', 'p.id', 'lb.loan_portfolio_id')
            ->whereIn('lb.reporting_period', $periods)
            ->whereNotNull('lb.ecl_value')
            ->groupBy('lb.reporting_period', 'p.name')
            ->selectRaw("lb.reporting_period rp, COALESCE(p.name,'Unmapped') name, SUM(" . \\App\\Services\\Reports\\BookedEcl::sql('lb.') . ") ecl")
            ->get();
        $undiscounted = DB::query()->fromSub(DB::table('loan_books')->whereIn('reporting_period', $periods)
            ->selectRaw('reporting_period rp, ' . \\App\\Services\\Reports\\BookedEcl::basisSql() . ' basis, COALESCE(ecl_value,0) ecl'), 'b')
            ->where('basis', 'undiscounted')->groupBy('rp')->selectRaw('rp, COUNT(*) n, SUM(ecl) ecl')->get()->keyBy('rp');
""")
rep("""            $line[] = $this->money($tot);
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
            ]]]);""", """            $line[] = $this->money($tot);
            $u = $undiscounted->get($p);
            $line[] = $u ? number_format($u->n) . ' loans, ' . $this->money($u->ecl) : '-';
            $rows[] = $line;
        }

        return $this->respond(['key' => 'portfolio-trend', 'period' => $period,
            'subtitle' => 'The loss allowance (discounted at the EIR where the engine discounted it) by portfolio over the last ' . count($periods) . ' periods',
            'kpis' => $this->totalsKpis($period),
            'sections' => [[
                'heading' => 'ECL by Portfolio over Time',
                'note' => 'The last column counts the loans of each month that have no discounted allowance and are included at their undiscounted ECL.',
                'columns' => array_merge(['Period'], $portNames->all(), ['Total', 'Of which undiscounted']),
                'align' => array_merge(['l'], array_fill(0, $portNames->count() + 2, 'r')),
                'rows' => $rows,
            ]]]);""")

# ---- ECL by portfolio and stage (saved runs): show the discounted figure beside the undiscounted
rep("""                $this->pct($r->lgd_value_used),
                $this->money($r->total_ecl),
                $this->pct((float) $r->total_ead != 0.0 ? $r->total_ecl / $r->total_ead : 0),
            ])->all();""", """                $this->pct($r->lgd_value_used),
                $this->money($r->total_ecl),
                $r->total_ecl_discounted !== null ? $this->money($r->total_ecl_discounted) : '-',
                ($r->discount_status ?? '-') . ((int) ($r->discount_unresolved_loans ?? 0) > 0 ? ' (' . number_format($r->discount_unresolved_loans) . ' loans not discounted)' : ''),
            ])->all();""")
rep("""                'heading' => 'Expected Credit Loss by Portfolio and Stage',
                'columns' => ['Portfolio', 'Stage', 'Loans', 'EAD', 'Avg PD', 'Avg LGD', 'ECL', 'Coverage %'],
                'align' => ['l', 'l', 'r', 'r', 'r', 'r', 'r', 'r'],""", """                'heading' => 'Expected Credit Loss by Portfolio and Stage (the saved ECL runs)',
                'note' => 'As saved by each ECL run: the undiscounted ECL, and the discounted ECL of the loans the run discounted. Where a run is PARTIAL the discounted column covers only the loans it discounted; the booked allowance of the month is in the basis section below.',
                'columns' => ['Portfolio', 'Stage', 'Loans', 'EAD', 'Avg PD', 'Avg LGD', 'ECL undiscounted', 'ECL discounted', 'Discounting'],
                'align' => ['l', 'l', 'r', 'r', 'r', 'r', 'r', 'r', 'l'],""")

# ---- opening to closing ECL and the charge: from the IFRS 9 note's attribution (write-offs included)
rep("""    public function eclReconciliation(Request $request)
    {
        $period = $this->period($request);
        $prev = $this->previousPeriod($period);
        $closing = (float) ($this->periodTotals($period)->ecl ?? 0);
        $opening = $prev ? (float) ($this->periodTotals($prev)->ecl ?? 0) : 0.0;
        $movement = $closing - $opening;
""", """    /**
     * The opening-to-closing allowance and the charge, from the IFRS 9 note's
     * contract-by-contract attribution, so the write-offs (the ledger's and the
     * approved register's) and the booked basis are the note's (built 14
     * October 2026; the bridge had been closing less opening, with nil written off).
     */
    private function noteMovement(?string $prev, ?string $period): ?array
    {
        if (! $prev || ! $period) {
            return null;
        }
        $r = \\App\\Services\\Reports\\Ifrs9NoteService::build($prev, $period);

        return $r['status'] === 'no_ecl' ? null : $r;
    }

    public function eclReconciliation(Request $request)
    {
        $period = $this->period($request);
        $prev = $this->previousPeriod($period);
        $note = $this->noteMovement($prev, $period);
        if ($note) {
            $line = fn (string $k, $s = 'total') => $note['ecl']['lines'][$k][$s] / 100;
            $charge = fn ($s) => $line('closing', $s) - $line('opening', $s) - $line('written_off', $s);
            $labels = \\App\\Services\\Reports\\Ifrs9NoteService::lineLabels('ECL allowance', $prev, $period);
            $rows = [];
            foreach ($labels as $k => $label) {
                $rows[] = array_merge([$label], array_map(fn ($s) => $this->money($line($k, $s)), [1, 2, 3, 'total']));
            }

            return $this->respond(['key' => 'ecl-reconciliation', 'period' => $period,
                'subtitle' => "Opening {$prev} -> Closing {$period}, contract by contract (the IFRS 9 note's attribution)",
                'kpis' => [
                    ['label' => 'Opening ECL', 'value' => $this->money($line('opening')), 'tone' => 'maiic'],
                    ['label' => 'Amounts written off', 'value' => $this->money($line('written_off')), 'tone' => 'amber'],
                    ['label' => 'Charge / (release)', 'value' => $this->money($charge('total')), 'tone' => $charge('total') >= 0 ? 'rose' : 'emerald'],
                    ['label' => 'Closing ECL', 'value' => $this->money($line('closing')), 'tone' => 'rose'],
                ],
                'sections' => [[
                    'heading' => 'ECL Reconciliation',
                    'columns' => ['Particulars', 'Stage 1', 'Stage 2', 'Stage 3', 'Total'],
                    'align' => ['l', 'r', 'r', 'r', 'r'],
                    'rows' => array_merge($rows, [array_merge(['Charge / (release) to profit or loss (closing less opening, less amounts written off)'], array_map(fn ($s) => $this->money($charge($s)), [1, 2, 3, 'total']))]),
                ], $this->writeOffSection($note)]]);
        }
        $closing = (float) ($this->periodTotals($period)->ecl ?? 0);
        $opening = 0.0;
        $movement = $closing;
""")
rep("""    public function eclCharge(Request $request)
    {
        $period = $this->period($request);
        $prev = $this->previousPeriod($period);
""", """    /** The write-offs of the period as a hub section (the note's list). */
    private function writeOffSection(array $note): array
    {
        $wo = $note['write_offs'] ?? [];

        return [
            'heading' => 'Amounts written off',
            'note' => ($wo ? 'From the ledger (the write-off rule: type 300, a batch debiting GL 1064, an ACCOUNT CLOSURE on a status-H account) and the approved write-off register. The allowance line takes the allowance each write-off used. ' : 'No write-off in the ledger or approved in the register between the two months. ')
                . 'Still subject to enforcement activity (IFRS 7.35L): ' . lcfirst($note['enforcement'] ?? ''),
            'columns' => ['Date', 'Contract', 'Customer', 'Gross written off', 'Record'],
            'align' => ['l', 'l', 'l', 'r', 'l'],
            'rows' => array_map(fn ($w) => [$w['date'], $w['contract_id'], $w['customer_name'] ?: '-', $this->money($w['amount']), ($w['source'] === 'ledger' ? 'Ledger: ' : 'Register: ') . $w['reference']], $wo),
        ];
    }

    public function eclCharge(Request $request)
    {
        $period = $this->period($request);
        $prev = $this->previousPeriod($period);
        $note = $this->noteMovement($prev, $period);
        if ($note) {
            $line = fn (string $k, $s) => $note['ecl']['lines'][$k][$s] / 100;
            $rows = [];
            foreach ([1, 2, 3] as $s) {
                $rows[] = ['Stage ' . $s, $this->money($line('opening', $s)), $this->money($line('written_off', $s)), $this->money($line('closing', $s)),
                    $this->money($line('closing', $s) - $line('opening', $s) - $line('written_off', $s))];
            }
            $tot = $line('closing', 'total') - $line('opening', 'total') - $line('written_off', 'total');
            $rows[] = ['Total', $this->money($line('opening', 'total')), $this->money($line('written_off', 'total')), $this->money($line('closing', 'total')), $this->money($tot)];

            return $this->respond(['key' => 'ecl-charge', 'period' => $period,
                'subtitle' => "Charge / (release) {$prev} -> {$period}: the movement in the allowance other than the amounts written off",
                'kpis' => [
                    ['label' => 'P&L Impact', 'value' => $this->money($tot), 'tone' => $tot >= 0 ? 'rose' : 'emerald'],
                    ['label' => 'Direction', 'value' => $tot >= 0 ? 'Charge' : 'Release', 'tone' => 'amber'],
                    ['label' => 'Allowance used by write-offs', 'value' => $this->money(-$line('written_off', 'total')), 'tone' => 'grey'],
                ],
                'sections' => [[
                    'heading' => 'Impairment Charge / (Release) by Stage',
                    'columns' => ['Stage', 'Prior ECL', 'Written off', 'Current ECL', 'Charge / (Release)'],
                    'align' => ['l', 'r', 'r', 'r', 'r'],
                    'rows' => $rows,
                ], $this->writeOffSection($note)]]);
        }
""")

# ---- provision comparison on the gross carrying amount, as the RBM return
rep("""            ->selectRaw($this->rbmClassCase() . " rbm, SUM(" . self::EAD_SQL . ") ead, SUM(" . self::ECL_SQL . ") ecl, COUNT(*) n")""",
    """            ->selectRaw($this->rbmClassCase() . " rbm, SUM(COALESCE(carrying_amount,0)) ead, SUM(" . self::ECL_SQL . ") ecl, COUNT(*) n")""")
rep("""            'subtitle' => 'IFRS 9 ECL vs RBM prudential provision (RBM Directive 2018 rates, by DPD class)',""",
    """            'subtitle' => 'IFRS 9 loss allowance against the RBM minimum provision: the Schedule\\'s rates (paragraph 14) on the gross balance by class (paragraph 10), as the RBM Return',""")
rep("""                'heading' => 'IFRS 9 ECL vs RBM Prudential Provision (by DPD class)',
                'columns' => ['RBM Class', 'EAD', 'RBM Rate', 'RBM Provision', 'IFRS 9 ECL', 'ECL - RBM'],""",
    """                'heading' => 'IFRS 9 ECL vs RBM Prudential Provision (by class, on the gross balance)',
                'note' => 'The directive\\'s minimum rates apply to the gross balance (the carrying amount), as on the RBM Return; undrawn commitments are not a classified balance. Where the RBM provision is higher, paragraph 15(1) treats the excess as an appropriation of retained earnings to the loan loss reserve.',
                'columns' => ['RBM Class', 'Gross balance', 'RBM Rate', 'RBM Provision', 'IFRS 9 ECL', 'ECL - RBM'],""")

# ---- the dead rbmRate with the wrong rates
rep("""    private function rbmRate(string $stage): float
    {
        return ['1' => 0.01, '2' => 0.01, '3' => 0.50][$stage] ?? 0.0;
    }

""", "")

# ---- paragraph references in the RBM classification subtitle
rep("""(RBM Credit Risk Management for DFIs Directive, 2018, section 10 and the Schedule)""",
    """(RBM Credit Risk Management for DFIs Directive, 2018, paragraph 10 and the Schedule to paragraph 14)""")

# ---- the basis of the allowance on every report that carries one
rep("""        if ($this->collecting) {
            return $report;
        }
""", """        if ($this->collecting) {
            return $report;
        }

        // every report that carries a loss allowance says on which basis it stands (BookedEcl)
        if (in_array($report['key'], self::ECL_REPORTS, true) && ! empty($report['period'])) {
            $summary = \\App\\Services\\Reports\\BookedEcl::summary($report['period']);
            if ($summary['status'] !== 'none') {
                $report['sections'][] = \\App\\Services\\Reports\\BookedEcl::section($summary, fn ($v) => $this->money($v)) + ['part' => 'Basis of the allowance', 'part_note' => ''];
                if ($summary['status'] === 'mixed') {
                    $report['subtitle'] = trim(($report['subtitle'] ?? '') . ' (the allowance includes ' . number_format($summary['reconciliation']['not_booked_loans']) . ' loans with no discounted figure, at their undiscounted ECL: see the basis section)');
                }
            }
        }
""")
rep("""    private const ECL_SQL = \\App\\Services\\Reports\\BookedEcl::SQL;""", """    private const ECL_SQL = \\App\\Services\\Reports\\BookedEcl::SQL;

    /** The reports that carry a loss allowance, and so its basis section. */
    private const ECL_REPORTS = ['executive', 'ecl', 'portfolio-trend', 'sector-ecl', 'product-group-ecl', 'grade-ecl', 'account-ecl', 'stage-allocation',
        'sicr-trigger', 'stage-migration', 'ecl-reconciliation', 'ecl-charge', 'rbm-classification', 'npl-arrears', 'provision-comparison',
        'concentration', 'coop-linkage', 'crm-agri', 'data-quality'];""")
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
