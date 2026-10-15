def edit(p, pairs):
    s = open(p, encoding='utf-8', newline='').read()
    nl = '\r\n' if '\r\n' in s else '\n'
    s = s.replace('\r\n', '\n')
    for old, new in pairs:
        assert old in s, (p, old[:80])
        s = s.replace(old, new, 1)
    open(p, 'w', encoding='utf-8', newline='').write(s.replace('\n', nl))
B = chr(92)
NS = B + 'App' + B + 'Services' + B + 'Fli' + B
edit(r'C:\xampp\htdocs\MAICC-IFRS9\app\Http\Controllers\Reports\Ifrs9ReportsController.php', [
("""        $setLabel = $set ? $set->name . ' v' . $set->version . ' (' . strtolower($set->status) . ')' : null;

        return $this->respond(['key' => 'macro-scenario', 'period' => $period,""",
"""        $setLabel = $set ? $set->name . ' v' . $set->version . ' (' . strtolower($set->status) . ')' : null;
        // a month whose PD held for want of a model version says so (fli_without_model, D-F1 note of 14 October 2026)
        $withoutModel = $period ? NSFliWithoutModel::disclosure($period) : null;

        return $this->respond(['key' => 'macro-scenario', 'period' => $period,""".replace('NS', NS)),
("""            ], $period && ($fwd = NSFliWithoutModel::disclosure($period)) !== null
                ? [['label' => 'Model in force', 'value' => NSFliWithoutModel::NOTE, 'tone' => 'amber', 'hint' => $fwd]] : []),""".replace('NS', NS),
"""            ], $withoutModel !== null ? [['label' => 'Model in force', 'value' => NSFliWithoutModel::NOTE, 'tone' => 'amber']] : []),""".replace('NS', NS)),
("""                 'rows' => $scenarios ?: [['No approved scenario set for this month', '-', '-', '-']]],""",
"""                 'rows' => $scenarios ?: [['No approved scenario set for this month', '-', '-', '-']]] + ($withoutModel !== null ? ['note' => $withoutModel] : []),"""),
("""        $kpis = $snap ? [""", """        // the record is not current when a step it depends on ran again (MonthEndCurrency); a month whose PD
        // held for want of a model version says so (fli_without_model)
        $stale = $snap ? NSMonthEndCurrency::state((string) $period)['record_stale'] : null;
        $withoutModel = $snap ? NSFliWithoutModel::disclosure((string) $period) : null;
        $kpis = $snap ? [""".replace('NS', NS)),
("""            ['label' => 'Close status', 'value' => ($stale = NSMonthEndCurrency::state($period)['record_stale']) !== null ? 'Not current: the month is mid-run' : ($snap->status === 'SIGNED_OFF' ? 'Signed off' : 'Recorded, awaiting sign-off'), 'tone' => $stale !== null ? 'rose' : 'grey'] + ($stale !== null ? ['hint' => $stale] : []),
        ] + (($fwd = NSFliWithoutModel::disclosure($period)) !== null ? [4 => ['label' => 'Model in force', 'value' => NSFliWithoutModel::NOTE, 'tone' => 'amber', 'hint' => $fwd]] : []) : [""".replace('NS', NS),
"""            ['label' => 'Close status', 'value' => $stale !== null ? 'Not current: the month is mid-run' : ($snap->status === 'SIGNED_OFF' ? 'Signed off' : 'Recorded, awaiting sign-off'), 'tone' => $stale !== null ? 'rose' : 'grey'],
        ] : ["""),
("""                'rows' => $rows ?: [['Run the FLI month-end close for this month (FLI Month-End, Overview)', '-', '-', '-']],
            ]]]);""", """                'rows' => $rows ?: [['Run the FLI month-end close for this month (FLI Month-End, Overview)', '-', '-', '-']],
            ] + (($stale ?? $withoutModel) !== null ? ['note' => trim(($stale ?? '') . ' ' . ($withoutModel ?? ''))] : [])]]);"""),
])
print('ok')
