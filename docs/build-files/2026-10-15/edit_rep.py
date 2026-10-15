def edit(p, pairs):
    s = open(p, encoding='utf-8', newline='').read()
    nl = '\r\n' if '\r\n' in s else '\n'
    s = s.replace('\r\n', '\n')
    for old, new in pairs:
        assert old in s, (p, old[:80])
        s = s.replace(old, new, 1)
    open(p, 'w', encoding='utf-8', newline='').write(s.replace('\n', nl))
edit(r'C:\xampp\htdocs\MAICC-IFRS9\app\Services\Reports\Ifrs9NoteService.php', [
("""            . ($close['fli_adjusted'] > 0 ? ' The PD includes the forward-looking (macro-economic) adjustment for ' . number_format($close['fli_adjusted']) . ' loans.' : '');
""", """            . ($close['fli_adjusted'] > 0 ? ' The PD includes the forward-looking (macro-economic) adjustment for ' . number_format($close['fli_adjusted']) . ' loans.' : '')
            // a month whose PD held for want of a model version says so (fli_without_model, D-F1 note of 14 October 2026)
            . (($fwd = \App\Services\Fli\FliWithoutModel::disclosure($closing)) !== null ? ' ' . $fwd : '');
"""),
])
edit(r'C:\xampp\htdocs\MAICC-IFRS9\app\Http\Controllers\Reports\Ifrs9ReportsController.php', [
("""                ['label' => 'Macro vintage', 'value' => $snap->macro_vintage ?? '-', 'tone' => 'grey'],
            ]),""", """                ['label' => 'Macro vintage', 'value' => $snap->macro_vintage ?? '-', 'tone' => 'grey'],
            ], $period && ($fwd = \App\Services\Fli\FliWithoutModel::disclosure($period)) !== null
                ? [['label' => 'Model in force', 'value' => \App\Services\Fli\FliWithoutModel::NOTE, 'tone' => 'amber', 'hint' => $fwd]] : []),"""),
("""            ['label' => 'Close status', 'value' => $snap->status === 'SIGNED_OFF' ? 'Signed off' : 'Recorded, awaiting sign-off', 'tone' => 'grey'],
        ] : [""", """            ['label' => 'Close status', 'value' => ($stale = \App\Services\Fli\MonthEndCurrency::state($period)['record_stale']) !== null ? 'Not current: the month is mid-run' : ($snap->status === 'SIGNED_OFF' ? 'Signed off' : 'Recorded, awaiting sign-off'), 'tone' => $stale !== null ? 'rose' : 'grey'] + ($stale !== null ? ['hint' => $stale] : []),
        ] + (($fwd = \App\Services\Fli\FliWithoutModel::disclosure($period)) !== null ? [4 => ['label' => 'Model in force', 'value' => \App\Services\Fli\FliWithoutModel::NOTE, 'tone' => 'amber', 'hint' => $fwd]] : []) : ["""),
])
print('ok')
