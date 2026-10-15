p='app/Http/Controllers/DashboardController.php'
raw=open(p,'rb').read().decode('utf-8')
crlf='\r\n' in raw
s=raw.replace('\r\n','\n')
old="public function index(Request $request)\n{\n"
assert s.count(old)==1
s=s.replace(old,"""public function index(Request $request)
{
    return Inertia::render('Dashboard', $this->dashboardState($request));
}

/**
 * The figures the dashboard shows for a period / portfolio / compare-to
 * choice. The page and its PDF (eclReportPdf) both read them from here, so
 * the download always matches the screen.
 */
private function dashboardState(Request $request): array
{
""")
a="        return Inertia::render('Dashboard', [\n            'summary' => $this->emptySummary(),"
assert s.count(a)==1
s=s.replace(a,"        return [\n            'summary' => $this->emptySummary(),")
b="            'error' => 'No ECL has been calculated yet. Load the loan book, apply PD and LGD, then run the ECL calculation.',\n        ]);"
assert s.count(b)==1
s=s.replace(b,"            'error' => 'No ECL has been calculated yet. Load the loan book, apply PD and LGD, then run the ECL calculation.',\n        ];")
c="    return Inertia::render('Dashboard', [\n        'summary' => $summary,"
assert s.count(c)==1
s=s.replace(c,"    return [\n        'summary' => $summary,")
d="        'recentImports' => $recentImports,\n    ]);\n}\n"
assert s.count(d)==1
s=s.replace(d,"        'recentImports' => $recentImports,\n    ];\n}\n")
if crlf: s=s.replace('\n','\r\n')
open(p,'wb').write(s.encode('utf-8'))
print('ok', crlf)
