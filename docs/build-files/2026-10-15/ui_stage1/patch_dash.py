p='C:/xampp/htdocs/MAICC-IFRS9/app/Http/Controllers/DashboardController.php'
s=open(p,encoding='utf-8').read()
def rep(a,b):
    global s
    assert s.count(a)==1,a
    s=s.replace(a,b)
rep('''use Illuminate\Support\Facades\Log;
''','''use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
''')
rep('''public function index(Request $request)
{
    return Inertia::render('Dashboard', $this->dashboardState($request));
}''','''public function index(Request $request)
{
    $state = $this->dashboardState($request);
    // The status strip reads the period chosen on the filter bar, else the
    // latest loan book loaded (which may be ahead of the latest ECL month).
    $chosen = $request->filled('period') && $state['selectedPeriod'] === $request->input('period');
    $period = $chosen ? $state['selectedPeriod'] : (LoanBook::max('reporting_period') ?? $state['selectedPeriod']);
    $state['statusStrip'] = $this->statusStrip($period, $chosen);

    return Inertia::render('Dashboard', $state);
}

/**
 * The status strip at the top of the dashboard: where the month stands for
 * one reporting period, read from the tables the engines write (nothing is
 * calculated here and nothing is assumed). A figure with nothing behind it
 * is null, and the page prints "-" or "Not run".
 */
private function statusStrip(?string $period, bool $chosen): array
{
    $link = fn (string $name, array $params = []) => Route::has($name) ? route($name, $params) : null;
    $has = fn (string $table) => Schema::hasTable($table);

    $strip = [
        'period' => $period,
        'period_source' => $period ? ($chosen ? 'chosen' : 'latest_loan_book') : null,
        'links' => [
            'period' => $link('accounting.financial_periods.index'),
            'loan_book' => $period ? $link('loan_applications.loan-book', ['year' => (int) substr($period, 0, 4), 'month' => (int) substr($period, 5, 2)]) : $link('loan_applications.loan-book'),
            'ecl' => $link('expected-credit-loss.index'),
            'eir' => $link('eir-reconciliation.index'),
            'fli' => $period ? $link('fli.overview', ['period' => $period]) : $link('fli.overview'),
        ],
        'loan_book' => null,
        'ecl' => null,
        'eir' => null,
        'fli' => null,
        'approvals' => [],
    ];

    if ($period) {
        $lb = LoanBook::where('reporting_period', $period)
            ->selectRaw('count(*) n, sum(carrying_amount) gross, sum(ecl_value is not null) ecl_loans, max(ecl_calculated_at) ecl_at')
            ->first();
        $n = (int) ($lb->n ?? 0);
        $strip['loan_book'] = $n > 0 ? ['loans' => $n, 'gross' => (float) $lb->gross] : null;

        $lastRun = ExpectedCreditLoss::where('reporting_period', $period)->max('created_at');
        $strip['ecl'] = [
            'loans' => $n,
            'ecl_loans' => (int) ($lb->ecl_loans ?? 0),
            'last_run_at' => $lastRun ? Carbon::parse($lastRun)->toIso8601String() : null,
        ];

        if ($has('eir_amortisation') && $has('contract_eir')) {
            $strip['eir'] = [
                'contracts_with_revenue' => (int) DB::table('eir_amortisation')->where('reporting_period', $period)->distinct()->count('contract_id'),
                'locked_contracts' => (int) DB::table('contract_eir')->whereNotNull('locked_at')->count(),
            ];
        }

        if ($has('fli_run_snapshots')) {
            $snap = DB::table('fli_run_snapshots as s')->leftJoin('users as u', 'u.id', '=', 's.signed_off_by')
                ->where('s.reporting_period', $period)->whereIn('s.status', ['RECORDED', 'SIGNED_OFF'])
                ->orderByDesc('s.id')->first(['s.status', 's.created_at', 's.signed_off_at', 's.approver_label', 'u.name as signer']);
            $strip['fli'] = $snap ? [
                'status' => $snap->status,
                'at' => ($snap->signed_off_at ?? $snap->created_at) ? Carbon::parse($snap->signed_off_at ?? $snap->created_at)->toIso8601String() : null,
                'by' => $snap->status === 'SIGNED_OFF' ? ($snap->approver_label ?: $snap->signer) : null,
            ] : null;
        }
    }

    // Maker-checker items waiting for a second person, in every period. Each
    // count is read from the table its screen approves from.
    $queue = [
        ['Governed settings', 'governance_settings', fn ($q) => $q->where('status', 'PROPOSED'), 'eir-governance.index', []],
        ['Schedules to review', 'contract_eir', fn ($q) => $q->whereIn('schedule_approval_status', ['DRAFT', 'PENDING_REVIEW']), 'eir-data.index', ['tab' => 'schedules']],
        ['Write-offs', 'loan_write_offs', fn ($q) => $q->where('status', 'PROPOSED'), 'eir-write-offs.index', []],
        ['FLI fits', 'fli_fits', fn ($q) => $q->where('approval_status', 'PROPOSED'), 'fli.overview', []],
        ['Scenario sets', 'governed_scenario_sets', fn ($q) => $q->where('status', 'PROPOSED'), 'scenario-sets.index', []],
        ['FLI overlays', 'fli_overlays', fn ($q) => $q->where('status', 'PROPOSED'), 'fli-overlays.index', []],
        ['External FLI models', 'fli_model_versions', fn ($q) => $q->where('status', 'PROPOSED'), 'fli-models.index', []],
        ['SICR triggers', 'finance_sicr_triggers', fn ($q) => $q->where('status', 'PROPOSED'), 'sicr-triggers.index', []],
    ];
    foreach ($queue as [$label, $table, $where, $route, $params]) {
        if (! $has($table)) {
            continue;
        }
        $count = (int) $where(DB::table($table))->count();
        if ($count > 0) {
            $strip['approvals'][] = ['label' => $label, 'count' => $count, 'href' => $link($route, $params)];
        }
    }

    return $strip;
}''')
open(p,'w',encoding='utf-8',newline='').write(s)
