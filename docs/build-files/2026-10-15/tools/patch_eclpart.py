P = 'C:/xampp/htdocs/MAICC-IFRS9/app/Http/Controllers/Reports/Ifrs9ReportsController.php'
s = open(P, encoding='utf-8').read()

def rep(a, b):
    global s
    assert a in s, a[:70]
    s = s.replace(a, b, 1)

rep("""        $rows = DB::table('expected_credit_loss')
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
                'align' => ['l', 'l', 'r', 'r', 'r', 'r', 'r', 'r'],""", """        // each row names the portfolio (or the segment) it was calculated for
        $portfolios = DB::table('loan_portfolios')->pluck('name', 'id');
        $rows = DB::table('expected_credit_loss')
            ->where('reporting_period', $period)
            ->orderBy('ecl_calculation_level')->orderBy('ecl_calculation_id')->orderBy('ifrs9_stage')
            ->get()
            ->map(fn ($r) => [
                ($r->ecl_calculation_level === 'portfolio' ? ($portfolios[$r->ecl_calculation_id] ?? null) : null)
                    ?? ($r->ecl_calculation_code ?: ucfirst($r->ecl_calculation_level ?? '-') . ' ' . $r->ecl_calculation_id),
                'Stage ' . $r->ifrs9_stage,
                number_format($r->total_loans),
                $this->money($r->total_ead),
                $this->pct($r->pd_value_used),
                $this->pct($r->lgd_value_used),
                $this->money($r->total_ecl),
                $this->pct((float) $r->total_ead != 0.0 ? $r->total_ecl / $r->total_ead : 0),
            ])->all();

        return $this->respond(['key' => 'ecl', 'period' => $period,
            'kpis' => $this->totalsKpis($period),
            'sections' => [[
                'heading' => 'Expected Credit Loss by Portfolio and Stage',
                'columns' => ['Portfolio', 'Stage', 'Loans', 'EAD', 'Avg PD', 'Avg LGD', 'ECL', 'Coverage %'],
                'align' => ['l', 'l', 'r', 'r', 'r', 'r', 'r', 'r'],""")
open(P, 'w', encoding='utf-8').write(s)
print('ok')
