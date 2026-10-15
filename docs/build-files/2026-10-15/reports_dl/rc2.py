exec(open(r'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/reports_dl/ed.py').read())
edit('app/Http/Controllers/ReportsController.php', [(
"""        if ($request->export && $serviceParams['portfolio_id'] && $serviceParams['start_period'] && $serviceParams['end_period']) {
            try {""",
"""        // The summary reconciliation is the IFRS 9 note (its transfers net to
        // nil and every column ties); only the detailed contract lists
        // (new loans, derecognised loans, stage transitions) download here.
        if ($request->export && $serviceParams['report_type'] !== 'detailed') {
            return redirect()->route('ifrs9-reports.fs-disclosure', array_filter([
                'opening' => $request->start_period,
                'closing' => $request->end_period,
                'portfolio_id' => $request->portfolio_id,
                'unit' => 'full',
                'download' => 'csv',
            ]));
        }

        if ($request->export && $serviceParams['portfolio_id'] && $serviceParams['start_period'] && $serviceParams['end_period']) {
            try {"""
)])
print('ok')
