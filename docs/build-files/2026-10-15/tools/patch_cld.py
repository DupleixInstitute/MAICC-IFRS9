import sys
p = r'c:\xampp\htdocs\MAICC-IFRS9\app\Imports\CreditLossDataImport.php'
s = open(p, encoding='utf-8').read()
a = """                            'notes' => $normalizedRow['notes'] ?? null,
                            'created_by' => $this->userId,"""
if a not in s: sys.exit('nf1')
s = s.replace(a, """                            'notes' => $normalizedRow['notes'] ?? null,
                            // the publisher's release date, when the file gives one (FLI module D-F10)
                            'published_at' => $this->parseDate($normalizedRow['published_at'] ?? $normalizedRow['release_date'] ?? $normalizedRow['published'] ?? '') ?: null,
                            'created_by' => $this->userId,""", 1)
open(p, 'w', encoding='utf-8').write(s)

p = r'c:\xampp\htdocs\MAICC-IFRS9\app\Http\Controllers\CreditLossDataController.php'
s = open(p, encoding='utf-8').read()
def rep(a, b):
    global s
    if a not in s: sys.exit('NOT FOUND ' + a[:60])
    s = s.replace(a, b, 1)
rep("'unique:credit_loss_definitions,code'", "'unique:macro_credit_loss_definitions,code'")
rep("""            'portfolio_id' => ['required', 'exists:loan_portfolios,id'],
            'period' => ['required', 'date'], // We validate it's a date first""", """            // empty = an external or industry series (FLI module D-F7), not one MAIIC portfolio
            'portfolio_id' => ['nullable', 'exists:loan_portfolios,id'],
            'period' => ['required', 'date'], // We validate it's a date first
            'published_at' => ['nullable', 'date'],""")
rep("""        $validated = $request->validate([
            'value' => ['nullable', 'numeric'],
            'source' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $creditLossData->update($validated);""", """        $validated = $request->validate([
            'value' => ['nullable', 'numeric'],
            'source' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'published_at' => ['nullable', 'date'],
        ]);

        $creditLossData->update($validated);""")
rep("""            'file' => 'required|file|mimes:csv,txt,xlsx|max:2048',
            'portfolio_id' => ['required', 'exists:loan_portfolios,id'],""", """            'file' => 'required|file|mimes:csv,txt,xlsx|max:2048',
            'portfolio_id' => ['nullable', 'exists:loan_portfolios,id'],""")
# index: add the derived measures and the external register
a = s.index("            return Inertia::render('FLI/CreditLossData/Index', [")
b = s.index("        public function period(")
s = s[:a] + """            // The measures the model is calibrated on: those derived from MAIIC's own staged
            // loan books (the bridge refreshes them with the correlation sweep), and the
            // external series entered here, which the bridge adds as EXT_ measures.
            $derivedCodes = ['NPL_RATIO', 'STAGE3_SHARE', 'DEFAULT_RATE_12M'];
            $derived = DB::table('credit_loss_series')->whereIn('proxy_code', $derivedCodes)->orderByDesc('observation_period')->get(['proxy_code', 'observation_period', 'value'])
                ->groupBy('observation_period')->map(fn ($g, $p) => ['period' => substr($p, 0, 4) . '-' . substr($p, 4, 2)] + $g->pluck('value', 'proxy_code')->map(fn ($v) => (float) $v)->all())->values();
            $external = DB::table('credit_loss_series')->where('proxy_code', 'like', 'EXT\\_%')
                ->selectRaw('proxy_code, max(proxy_name) name, count(*) n, min(observation_period) first, max(observation_period) last, max(known_from) known')->groupBy('proxy_code')->get();
            $rows = CreditLossData::with(['definition', 'creator', 'portfolio'])
                ->when($period, function ($q) use ($period) { [$y, $m] = explode('-', $period); $q->whereYear('period', $y)->whereMonth('period', $m); })
                ->when($definitionId, fn ($q) => $q->where('definition_id', $definitionId))
                ->when($request->filled('portfolio_id'), fn ($q) => $request->input('portfolio_id') === 'external' ? $q->whereNull('portfolio_id') : $q->where('portfolio_id', $request->input('portfolio_id')))
                ->orderByDesc('period')->orderBy('definition_id')->paginate(15)->withQueryString();

            return Inertia::render('FLI/CreditLossData/Index', [
                'rows' => $rows,
                'derived' => $derived,
                'externalSeries' => $external,
                'lastSweep' => DB::table('audit_logs')->where('action', 'FLI Step Launched')->where('new_values', 'like', '%fli:correlate%')->max('created_at'),
                'canGovern' => (bool) $request->user()?->can('eir.govern'),
""" + s[a + len("            return Inertia::render('FLI/CreditLossData/Index', [\n"):b] + s[b:]
open(p, 'w', encoding='utf-8').write(s)
print('ok')
