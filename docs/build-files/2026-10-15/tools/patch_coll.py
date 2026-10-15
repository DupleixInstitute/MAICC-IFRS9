P = 'C:/xampp/htdocs/MAICC-IFRS9/'

def patch(p, pairs):
    s = open(P + p, encoding='utf-8').read()
    for a, b in pairs:
        assert a in s, (p, a[:60])
        s = s.replace(a, b, 1)
    open(P + p, 'w', encoding='utf-8').write(s)

patch('app/Http/Controllers/CollateralController.php', [
("""                'period' => 'nullable|date',
            ]);""", """                'registration_date' => 'nullable|date',
            ]);"""),
("""            $period = $request->input('period') ?: $reportingPeriod;""",
 """            // the register date chosen on the screen; with none, every register date counts
            $period = $request->input('registration_date') ?: null;"""),
("""                    $q->whereDate('period', $period);""", """                    $q->whereDate('registration_date', $period);"""),
("""                        ->when($period, fn($q) => $q->whereDate('period', $period))""", """                        ->when($period, fn($q) => $q->whereDate('registration_date', $period))"""),
("""            $collateralList = CollateralRegister::all();
            return Inertia::render('Collateral/Components/Allocate',
                [
                'collateralList' => $collateralList,
                 'registerDates' => $registerDates]
                );""", """            $collateralList = CollateralRegister::all();
            // the month the form opens on: the latest loan book, not today's month
            $latest = \\Illuminate\\Support\\Facades\\DB::table('loan_books')->max('reporting_period');
            return Inertia::render('Collateral/Components/Allocate',
                [
                'collateralList' => $collateralList,
                 'registerDates' => $registerDates,
                 'latestPeriod' => $latest]
                );"""),
])
patch('routes/web.php', [("Route::post('/collateral/allocations/allocate', [CollateralController::class, 'allocateCollateral'])->name('collateral.allocations.allocate');\n", "")])
patch('resources/js/Pages/Collateral/Components/Allocate.vue', [
("""    registerDates: { type: Array, default: () => [] },
  },""", """    registerDates: { type: Array, default: () => [] },
    latestPeriod: { type: String, default: null },
  },"""),
("""  setup() {
    const currentDate = new Date()
""", """  setup(props) {
    // open on the latest loan book month; today's month has no loan book until it is built
    const [ly, lm] = (props.latestPeriod || '').split('-').map(Number)
    const currentDate = ly && lm ? new Date(ly, lm - 1, 1) : new Date()
"""),
])
print('ok')
