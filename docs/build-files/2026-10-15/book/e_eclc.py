p = r'C:\xampp\htdocs\MAICC-IFRS9\app\Http\Controllers\ExpectedCreditLossController.php'
s = open(p, encoding='utf-8').read()
def rep(old, new):
    global s
    assert old in s, old
    s = s.replace(old, new, 1)

rep('''use App\\Services\\Ecl\\TimePhasedEclService;
''', '''use App\\Services\\Ecl\\TimePhasedEclService;
use App\\Services\\Reports\\EclBuildUpService;
''')

rep('''            if ($request->filled('stage')) {
                $query->where('ifrs9stage_post_qualitative', $request->input('stage'));
            }

            $loanBooks = $query->paginate(15)->withQueryString();
''', '''            if ($request->filled('stage')) {
                $query->where('ifrs9stage_post_qualitative', $request->input('stage'));
            }
            $this->applyBookFilters($query, $request);

            // The whole filtered population on the engine's basis: totals
            // for the total row and the build-up (ECL before FLI, the
            // forward-looking effect, overlays, booked ECL).
            $buildUpService = app(EclBuildUpService::class);
            $population = (clone $query)->toBase()->get(array_map(fn ($c) => 'loan_books.' . $c, EclBuildUpService::COLUMNS));
            $viewPeriod = ($request->filled('year') && $request->filled('month'))
                ? sprintf('%04d-%02d', (int) $request->input('year'), (int) $request->input('month'))
                : $latestPeriod;
            $buildUp = $buildUpService->buildUp($population, (string) $viewPeriod);
            $totals = ['loans' => $population->count(), 'ead' => $buildUp['ead'], 'ecl' => $buildUp['final'], 'ecl_pre' => $buildUp['pre_fli'],
                'ecl_discounted' => $population->whereNotNull('ecl_value_discounted')->isNotEmpty() ? round((float) $population->sum('ecl_value_discounted'), 2) : null];

            $loanBooks = $query->paginate(15)->withQueryString();
            $loanBooks->setCollection($loanBooks->getCollection()->map(function ($loan) {
                $loan->setAttribute('lineage', EclBuildUpService::lineage((object) $loan->getAttributes()));
                $loan->makeHidden(['build_basis', 'fli_by_scenario']);

                return $loan;
            }));
            $optionPeriod = $viewPeriod ?: LoanBook::max('reporting_period');
''')

rep('''                'portfolios'  => LoanPortfolio::all(),
                'sectors'     => IndustryType::all(),
                'runInfo'     => $this->runInfo($request, $latestPeriod),
            ]);
        }
''', '''                'portfolios'  => LoanPortfolio::all(),
                'sectors'     => IndustryType::all(),
                'runInfo'     => $this->runInfo($request, $latestPeriod),
                'buildUp'     => $buildUp,
                'totals'      => $totals,
                // the product groups and sectors present in the month shown, for the filters
                'productGroups' => LoanBook::where('reporting_period', $optionPeriod)->whereNotNull('product_group')->distinct()->orderBy('product_group')->pluck('product_group'),
                'bookSectors' => LoanBook::where('reporting_period', $optionPeriod)->whereNotNull('industry_type')->distinct()->orderBy('industry_type')->pluck('industry_type')
                    ->map(fn ($t) => ['value' => $t, 'label' => EclBuildUpService::sectorLabel($t)])->values(),
            ]);
        }

        /** Product group and sector filters, shared by the list and its summary. */
        private function applyBookFilters($query, Request $request): void
        {
            if ($request->filled('product_group')) {
                $query->where('product_group', $request->input('product_group'));
            }
            if ($request->filled('sector')) {
                $query->where('industry_type', $request->input('sector'));
            }
        }
''')

rep('''                'filters'     => $request->only([
                    'search',
                    'year',
                    'month',
                    'stage',
                    'portfolio'
                ]),''', '''                'filters'     => $request->only([
                    'search',
                    'year',
                    'month',
                    'stage',
                    'portfolio',
                    'product_group',
                    'sector',
                ]),''')

# summary: same filters, and the EAD the engine measured
rep('''            if ($request->filled('stage')) {
                $query->where('ifrs9stage_post_qualitative', $request->input('stage'));
            }

            // Current summary + stage split in ONE aggregate pass''', '''            if ($request->filled('stage')) {
                $query->where('ifrs9stage_post_qualitative', $request->input('stage'));
            }
            $this->applyBookFilters($query, $request);

            // Current summary + stage split in ONE aggregate pass''')
rep('''                 COALESCE(SUM(carrying_amount),0) as exposure,
''', '''                 COALESCE(SUM(carrying_amount),0) as exposure,
                 COALESCE(SUM(' . EclBuildUpService::EAD_SQL . '),0) as ead,
''')
rep('''                'total_exposure' => $currentExposure,
''', '''                'total_exposure' => $currentExposure,
                'total_ead' => (float) $agg->ead,
''')
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
