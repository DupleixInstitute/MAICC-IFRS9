p = r'C:\xampp\htdocs\MAICC-IFRS9\app\Http\Controllers\LoanBookController.php'
s = open(p, encoding='utf-8').read()
start = s.index('    public function index(Request $request)')
end = s.index('    /*public function import(Request $request)')
new = r'''    public function index(Request $request)
    {
        $query = LoanBook::query()
            ->with('client')
            ->with('portfolio')
            ->with('contractEir:id,contract_id,eir_effective_annual,eir_nominal_annual,rate_type,rate_source,calculation_status,locked_at')
            ->orderBy('reporting_period', 'desc')
            ->orderBy('contract_id');
        $this->applyFilters($query, $request);

        $loanBooks = $query->paginate(15)->withQueryString();

        // Each row carries its IFRS 9 lineage (PD before and after FLI, the
        // horizon PD, LGD, EAD build-up, ECL and the check against the saved
        // figure), its locked EIR and the fit and scenario set it ran under.
        $buildUp = app(EclBuildUpService::class);
        $rows = collect($loanBooks->items());
        $fits = $buildUp->fits($rows->pluck('fli_fit_id')->filter()->unique()->values()->all());
        $sets = $buildUp->sets($rows->pluck('fli_set_id')->filter()->unique()->values()->all());
        $loanBooks->setCollection($loanBooks->getCollection()->map(function ($loan) use ($fits, $sets) {
            $loan->setAttribute('lineage', EclBuildUpService::lineage((object) $loan->getAttributes()));
            $loan->setAttribute('eir', $this->eirOf($loan->contractEir));
            $loan->setAttribute('sector', EclBuildUpService::sectorLabel($loan->industry_type));
            $loan->setAttribute('fli_by_scenario_list', collect(json_decode((string) $loan->fli_by_scenario, true) ?: [])
                ->map(fn ($v, $k) => ['scenario' => $k, 'weight' => (float) ($v['weight'] ?? 0), 'pd' => isset($v['pd']) ? (float) $v['pd'] : null])->values()->all());
            $loan->setAttribute('fit', $loan->fli_fit_id ? ($fits[(int) $loan->fli_fit_id] ?? ['id' => (int) $loan->fli_fit_id]) : null);
            $loan->setAttribute('scenario_set', $loan->fli_set_id ? ($sets[(int) $loan->fli_set_id] ?? ['id' => (int) $loan->fli_set_id]) : null);
            $loan->makeHidden(['build_basis', 'fli_by_scenario', 'contractEir']);

            return $loan;
        }));

        $period = $this->periodOf($request);

        return Inertia::render('LoanBooks/Index', [
            'loanBooks' => $loanBooks,
            'filters' => $request->only(['search', 'year', 'month', 'stage', 'portfolio', 'product_group', 'sector']),
            'portfolios' => LoanPortfolio::all(),
            'summary'   => $this->summary($request),
            // the month-ends that hold loans, newest first, for the period picker
            'periods'   => LoanBook::query()->select('reporting_period')->distinct()->orderByDesc('reporting_period')->pluck('reporting_period'),
            // the product groups and sectors present in the month shown
            'productGroups' => $period ? LoanBook::where('reporting_period', $period)->whereNotNull('product_group')->distinct()->orderBy('product_group')->pluck('product_group') : [],
            'sectors' => $period ? LoanBook::where('reporting_period', $period)->whereNotNull('industry_type')->distinct()->orderBy('industry_type')->pluck('industry_type')
                ->map(fn ($t) => ['value' => $t, 'label' => EclBuildUpService::sectorLabel($t)])->values() : [],
        ]);
    }

    /** The month shown: the year and month asked for, else the latest loan book. */
    private function periodOf(Request $request): ?string
    {
        if ($request->filled('year') && $request->filled('month')) {
            return sprintf('%04d-%02d', (int) $request->input('year'), (int) $request->input('month'));
        }

        return LoanBook::max('reporting_period');
    }

    /** The table's filters, shared by the list and its summary so the strip matches the rows. */
    private function applyFilters($query, Request $request): void
    {
        // Default to the latest reporting period so the book opens on the
        // current (calculated) figures instead of page 1 of the oldest
        // import. Picking a year/month overrides this.
        if (! $request->filled('year') && ! $request->filled('month')) {
            $latestPeriod = LoanBook::max('reporting_period');
            if ($latestPeriod) {
                $query->where('reporting_period', $latestPeriod);
            }
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('contract_id', 'like', "%{$search}%")
                  ->orWhere('customer_id', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhereHas('client', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }
        if ($request->filled('year')) {
            $query->where('reporting_year', (int) $request->input('year'));
        }
        if ($request->filled('month')) {
            $query->where('reporting_month', (int) $request->input('month'));
        }
        if ($request->filled('stage')) {
            $query->where('ifrs9stage_post_qualitative', $request->input('stage'));
        }
        if ($request->filled('portfolio')) {
            $query->where('loan_portfolio_id', $request->input('portfolio'));
        }
        if ($request->filled('product_group')) {
            $query->where('product_group', $request->input('product_group'));
        }
        if ($request->filled('sector')) {
            $query->where('industry_type', $request->input('sector'));
        }
    }

    /**
     * The EIR to show for a contract: the locked original rate only. An EIR
     * that is not locked (pending, calculated but not approved, in error) is
     * shown by its status, never as a rate.
     */
    private function eirOf($eir): array
    {
        if (! $eir) {
            return ['locked' => false, 'rate' => null, 'status' => 'No EIR record'];
        }
        $locked = $eir->locked_at !== null && $eir->eir_effective_annual !== null;
        $status = ucfirst(strtolower(str_replace('_', ' ', (string) ($eir->calculation_status ?: 'PENDING'))));

        return ['locked' => $locked, 'rate' => $locked ? (float) $eir->eir_effective_annual : null, 'status' => $locked ? 'Locked' : $status,
            'rate_type' => $eir->rate_type, 'locked_at' => $locked ? (string) $eir->locked_at : null];
    }

'''
s = s[:start] + new + s[end:]
start = s.index('    public function summary(Request $request)')
end = s.index('    public static function calculateIfrs9Stage')
new2 = r'''    public function summary(Request $request)
    {
        $query = LoanBook::query();
        $this->applyFilters($query, $request);
        $ead = EclBuildUpService::EAD_SQL;

        $result = $query->selectRaw("
            COUNT(*) as total_accounts,
            SUM(carrying_amount) as total_carrying,
            SUM(COALESCE(commitments,0)) as total_undrawn,
            SUM({$ead}) as total_ead,
            SUM(ecl_value) as total_ecl,
            SUM(CASE WHEN ifrs9stage_post_qualitative = 3 THEN {$ead} ELSE 0 END) as stage_3_exposure,
            COUNT(CASE WHEN ifrs9stage_post_qualitative = 1 THEN 1 END) as stage_1_count,
            COUNT(CASE WHEN ifrs9stage_post_qualitative = 2 THEN 1 END) as stage_2_count,
            COUNT(CASE WHEN ifrs9stage_post_qualitative = 3 THEN 1 END) as stage_3_count
        ")->first();

        // EAD is the exposure the ECL engine measured: the carrying amount
        // plus the undrawn commitment times the utilisation rate. Coverage is
        // ECL over that EAD, as on the dashboard.
        return [
            'total_loans' => (int) $result->total_accounts,
            'total_carrying' => (float) $result->total_carrying,
            'total_undrawn' => (float) $result->total_undrawn,
            'total_ead' => (float) $result->total_ead,
            'total_provision' => (float) $result->total_ecl,
            'ecl_coverage' => $result->total_ead > 0 ? ($result->total_ecl / $result->total_ead) * 100 : 0,
            'stage_3_exposure' => (float) $result->stage_3_exposure,
            'stage_1_count' => (int) $result->stage_1_count,
            'stage_2_count' => (int) $result->stage_2_count,
            'stage_3_count' => (int) $result->stage_3_count,
        ];
    }

'''
s = s[:start] + new2 + s[end:]
s = s.replace('use App\\Models\\LoanPortfolio;\n', 'use App\\Models\\LoanPortfolio;\nuse App\\Services\\Reports\\EclBuildUpService;\n', 1)
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
