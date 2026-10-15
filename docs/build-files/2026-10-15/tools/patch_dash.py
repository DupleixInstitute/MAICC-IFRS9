P = 'C:/xampp/htdocs/MAICC-IFRS9/'

def patch(p, pairs):
    s = open(P + p, encoding='utf-8').read()
    for a, b in pairs:
        assert a in s, (p, a[:60])
        s = s.replace(a, b, 1)
    open(P + p, 'w', encoding='utf-8').write(s)

patch('app/Http/Controllers/DashboardController.php', [(r"""    $rp = ReportingPeriods::whereDate('period', $period . '-01')->first();

    return [
        'period' => $period,
        'loan_book_rows' => (int) LoanBook::where('reporting_period', $period)->count(),
        'pd_applied' => (bool) ($rp?->pd_id),
        'pd_source' => $rp?->pd_calculation_source,
        'lgd_applied' => (bool) ($rp?->lgd_id),
        'lgd_source' => $rp?->lgd_calculation_source,
        'ecl_calculated' => (bool) ($rp?->ecl_calculated),
    ];""", r"""    // read from the loans themselves, the same test the Workspace checklist uses:
    // the legacy reporting_periods pointers are no longer written by the segment engines
    $lb = LoanBook::where('reporting_period', $period)
        ->selectRaw('count(*) n, sum(pd_prefli is not null) pd, sum(lgd_value is not null or collection_lgd is not null) lgd, sum(pd_post_fli is not null) fli, sum(ecl_value is not null) ecl, max(pd_segment_run_id) pd_run')
        ->first();
    $n = (int) ($lb->n ?? 0);

    return [
        'period' => $period,
        'loan_book_rows' => $n,
        'pd_applied' => $n > 0 && (int) $lb->pd === $n,
        'pd_source' => $n > 0 ? number_format((int) $lb->pd) . ' of ' . number_format($n) . ' loans' . ($lb->pd_run ? ', PD by segment' : '') : null,
        'lgd_applied' => $n > 0 && (int) $lb->lgd === $n,
        'lgd_source' => $n > 0 ? number_format((int) $lb->lgd) . ' of ' . number_format($n) . ' loans' : null,
        'fli_applied' => $n > 0 && (int) $lb->fli === $n,
        'ecl_calculated' => $n > 0 && (int) $lb->ecl > 0,
        'ecl_loans' => (int) ($lb->ecl ?? 0),
    ];""")])
patch('resources/js/Pages/Dashboard.vue', [("""        { label: 'PD applied', done: m.pd_applied, detail: m.pd_applied ? `Source: ${m.pd_source || 'system'}` : 'Cumulative PD not yet applied', href: route('transition-matrix-cummulative.index') },
        { label: 'LGD applied', done: m.lgd_applied, detail: m.lgd_applied ? `Source: ${m.lgd_source || 'system'}` : 'Cumulative LGD not yet applied', href: route('lgd-cummulative.index') },
        { label: 'ECL calculated', done: m.ecl_calculated, detail: m.ecl_calculated ? 'Results available' : 'Not yet calculated', href: route('expected-credit-loss.index') },""",
"""        { label: 'PD applied', done: m.pd_applied, detail: m.pd_source || 'Not yet applied', href: route('transition-matrices.index') },
        { label: 'LGD applied', done: m.lgd_applied, detail: m.lgd_source || 'Not yet applied', href: route('loss-given-default.index') },
        { label: 'Forward-looking applied', done: m.fli_applied, detail: m.fli_applied ? 'Every loan carries a post-FLI PD' : 'Not yet applied for every loan', href: route('fli.overview', { period: m.period }) },
        { label: 'ECL calculated', done: m.ecl_calculated, detail: m.ecl_calculated ? `${formatCount(m.ecl_loans)} loans` : 'Not yet calculated', href: route('expected-credit-loss.index') },""")])
print('ok')
