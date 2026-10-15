exec(open(r'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/reports_dl/ed.py').read())
p = 'app/Services/Reports/ECLExportService.php'
raw = open(p, encoding='utf-8', newline='').read().replace('\r\n', '\n')
a = raw.index("    private static function generateSummaryData(")
b = raw.index("    /**\n     * Generate total loan book data")
new = '''    private static function generateSummaryData($portfolioId, $reportingPeriod, $columns): array
    {
        // By the stage the ECL was provided on (after the qualitative
        // triggers), with the EAD the engine measured (carrying amount plus
        // the drawn share of undrawn commitments) and the PD after the
        // forward-looking adjustment, so the totals tie to the saved ECL run.
        $ead = 'COALESCE(carrying_amount,0) + COALESCE(commitments,0) * COALESCE(facility_utilisation_rate,1)';
        $data = DB::table('loan_books')
            ->selectRaw("
                ifrs9stage_post_qualitative as stage,
                COUNT(*) as loans,
                SUM(COALESCE(carrying_amount,0)) as gross,
                SUM({$ead}) as total_ead,
                SUM(COALESCE(pd_post_fli, pd_prefli, 0) * ({$ead})) as pd_x_ead,
                SUM(COALESCE(lgd_value, 0) * ({$ead})) as lgd_x_ead,
                SUM(COALESCE(ecl_value,0)) as total_ecl
            ")
            ->where('loan_portfolio_id', $portfolioId)
            ->where('reporting_period', $reportingPeriod)
            ->groupBy('ifrs9stage_post_qualitative')
            ->orderBy('ifrs9stage_post_qualitative')
            ->get();

        $row = fn ($stage, $loans, $gross, $ead, $pdx, $lgdx, $ecl) => [
            'Stage' => $stage,
            'Loans' => (int) $loans,
            'Gross carrying amount' => round((float) $gross, 2),
            'EAD' => round((float) $ead, 2),
            'PD (EAD-weighted)' => $ead != 0 ? round($pdx / $ead, 6) : null,
            'LGD (EAD-weighted)' => $ead != 0 ? round($lgdx / $ead, 6) : null,
            'ECL' => round((float) $ecl, 2),
            'Coverage of EAD' => $ead != 0 ? round($ecl / $ead, 6) : null,
        ];

        $rows = $data->map(fn ($r) => $row($r->stage, $r->loans, $r->gross, (float) $r->total_ead, (float) $r->pd_x_ead, (float) $r->lgd_x_ead, (float) $r->total_ecl))->values();
        if ($rows->isNotEmpty()) {
            $rows->push($row('Total', $data->sum('loans'), $data->sum('gross'), (float) $data->sum('total_ead'),
                (float) $data->sum('pd_x_ead'), (float) $data->sum('lgd_x_ead'), (float) $data->sum('total_ecl')));
        }

        return $rows->toArray();
    }

'''
s = raw[:a] + new + raw[b:]
s = s.replace("return response()->download($filePath)->deleteFileAfterSend(true);",
              "return response()->download($filePath, null, ['Content-Type' => 'text/csv'])->deleteFileAfterSend(true);")
open(p, 'w', encoding='utf-8', newline='').write(s.replace('\n', '\r\n') if '\r\n' in open(p, encoding='utf-8', newline='').read() else s)
print('ok')
