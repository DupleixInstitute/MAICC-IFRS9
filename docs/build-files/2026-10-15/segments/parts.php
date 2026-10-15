    /* ---- PD by segment (9 October 2026): reports; the ECL keeps the governed basis ---- */

    /** The observed one-year PD of each portfolio, the governed minimum, and the PD and ECL the period carries. */
    public function pdByPortfolio(Request $request)
    {
        return $this->pdSegmentPart($request, 'portfolio', 'Portfolio');
    }

    /** The observed one-year PD of each RBM sector; a held-apart E-Banker code is its own line. */
    public function pdBySector(Request $request)
    {
        return $this->pdSegmentPart($request, 'sector', 'RBM sector');
    }

    private function pdSegmentPart(Request $request, string $basis, string $what)
    {
        $period = $this->period($request);
        $svc = app(\App\Services\Pd\SegmentPdReport::class);
        $r = $period ? $svc->bySegment($period, $basis) : ['ok' => false, 'error' => 'No period with a calculated ECL.', 'rows' => []];
        $sections = [];
        if (! $r['ok']) {
            $sections[] = ['heading' => "PD by {$what}", 'columns' => ['Note'], 'align' => ['l'], 'rows' => [['Not measured: ' . $r['error']]]];
        } else {
            $cfg = $r['settings'];
            $rows = array_map(fn ($x) => [
                $x['label'], 'Stage ' . $x['stage'], number_format($x['cohort']), number_format($x['defaults']),
                $x['stage'] === '3' ? 'in default' : ($x['observed'] === null ? '-' : $this->pct($x['observed'])),
                $x['stage'] === '3' ? '-' : ($x['meets'] ? 'Yes' : 'No, thin'),
                $x['applied'] === null ? '-' : $this->pct($x['applied']),
                $x['sources'] === [] ? '-' : implode(', ', array_map(fn ($k) => $k === $x['key'] ? 'own' : ($k === 'book' ? 'pooled book' : $k), $x['sources'])),
                number_format($x['loans']), $this->money($x['ead']), $this->money($x['ecl']), $x['ead'] > 0 ? $this->pct($x['ecl'] / $x['ead']) : '-',
            ], $r['rows']);
            if ($r['book']) {
                $rows[] = ['Pooled book (the parent)', 'Stage 1 / 2', '', '', ($r['book']['1'] === null ? '-' : $this->pct($r['book']['1'])) . ' / ' . ($r['book']['2'] === null ? '-' : $this->pct($r['book']['2'])), '', '', '', '', '', '', ''];
            }
            $governed = $cfg['basis'] === $basis ? 'This is the governed basis: the ECL uses these PDs.' : "The governed basis is '{$cfg['basis_option']}'; this cut is a report only and the ECL does not use it.";
            $sections[] = ['heading' => "Observed one-year PD by {$what} ({$r['window']})",
                'note' => "{$governed} Observed PD: the balance in the stage at the window start that was in Stage 3 at its end, or left the book unpaid. Minimum for a segment's own PD: {$cfg['minimum_option']}; a thin stage: {$cfg['thin_option']}. PD applied: the average pre-FLI twelve-month PD the loans carry this month, and the segment it came from.",
                'columns' => [$what, 'Stage', 'Loans at start', 'Defaults', 'Observed PD', 'Own PD allowed', 'PD applied', 'PD from', 'Loans now', 'EAD', 'ECL', 'Coverage'],
                'align' => ['l', 'l', 'r', 'r', 'r', 'l', 'r', 'l', 'r', 'r', 'r', 'r'],
                'rows' => $rows];
            if ($basis === 'sector') {
                $codes = $cfg['unverified'];
                $held = $svc->unverifiedCodes($period, $codes);
                $sections[] = ['heading' => 'Loans on an E-Banker sector code held apart',
                    'note' => $codes === [] ? 'No E-Banker code is held apart under the governed setting.' : 'E-Banker gives code ' . implode(', ', $codes) . ' (other civil engineering) to a loan whose sector was never captured; these loans are not read as construction risk. Their codes are not changed here: the fix is to capture the right sector in E-Banker.',
                    'columns' => ['Code', 'Product group', 'Loans', 'EAD', 'ECL'], 'align' => ['l', 'l', 'r', 'r', 'r'],
                    'rows' => array_map(fn ($h) => [$h['industry_code'], $h['product_group'] ?? '-', number_format($h['n']), $this->money($h['ead']), $this->money($h['ecl'])], $held)];
            }
        }

        return $this->respond(['key' => 'sector-ecl', 'period' => $period,
            'subtitle' => "Observed one-year probability of default by {$what}, against the governed minimum, with the PD and ECL of the period",
            'kpis' => $this->totalsKpis($period), 'sections' => $sections]);
    }

    /** The observed one-year PD by RBM class, the directive's minimum provision and the IFRS 9 coverage per class. */
    public function pdByRbmClass(Request $request)
    {
        $period = $this->period($request);
        $r = $period ? app(\App\Services\Pd\SegmentPdReport::class)->byRbmClass($period) : ['ok' => false, 'error' => 'No period with a calculated ECL.', 'rows' => []];
        $sections = [];
        if (! $r['ok']) {
            $sections[] = ['heading' => 'PD by RBM class', 'columns' => ['Note'], 'align' => ['l'], 'rows' => [['Not measured: ' . $r['error']]]];
        } else {
            $sections[] = ['heading' => "Observed one-year PD by RBM class ({$r['window']})",
                'note' => 'The class is the one a loan was in at the start of the window (days past due and term, as the directive bands them). Observed PD: the performing loans (Stage 1 or 2) of the class that were in Stage 3 at the end of the window, or had left the book unpaid. Loans already in Stage 3 at the start are counted apart. The coverage and PD applied are this month\'s, by the class the loans are in now. A report: the ECL does not use the RBM class.',
                'columns' => ['RBM class', 'Loans at start', 'Already Stage 3', 'Performing', 'Defaulted', 'Observed PD (count)', 'Observed PD (balance)', 'RBM minimum', 'Loans now', 'EAD', 'IFRS 9 ECL', 'IFRS 9 coverage', 'PD applied'],
                'align' => ['l', 'r', 'r', 'r', 'r', 'r', 'r', 'r', 'r', 'r', 'r', 'r', 'r'],
                'rows' => array_map(fn ($x) => [$x['class'], number_format($x['start_loans']), number_format($x['start_stage3']), number_format($x['performing']), number_format($x['defaults']),
                    $x['pd_count'] === null ? '-' : $this->pct($x['pd_count']), $x['pd_balance'] === null ? '-' : $this->pct($x['pd_balance']), $this->pct($x['minimum']),
                    number_format($x['loans']), $this->money($x['ead']), $this->money($x['ecl']), $x['coverage'] === null ? '-' : $this->pct($x['coverage']),
                    $x['applied_pd'] === null ? '-' : $this->pct($x['applied_pd'])], $r['rows'])];
        }

        return $this->respond(['key' => 'rbm-classification', 'period' => $period,
            'subtitle' => 'Observed one-year probability of default by RBM class, beside the minimum provision and the IFRS 9 coverage',
            'kpis' => $this->totalsKpis($period), 'sections' => $sections]);
    }

