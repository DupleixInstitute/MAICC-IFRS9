<?php

namespace App\Http\Controllers;

use App\Models\LoanBook;
use App\Services\AuditLoggerService;
use App\Services\Maintenance\CommandRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Maintenance console: run a fixed WHITELIST of artisan commands from the
 * browser, in a background process with live output (ported from the Stress
 * Testing app). The browser only sends a catalogue key and, for a month-end
 * step, a period chosen from the periods the system holds; the command line is
 * built here from a fixed template, so nothing typed reaches the shell.
 * Destructive entries need the confirm phrase typed; one command runs at a
 * time; every launch is written to the audit log.
 */
class MaintenanceController extends Controller
{
    public function __construct(private readonly CommandRunner $runner)
    {
    }

    /**
     * The whitelist. danger: safe | caution | destructive. `period` = the step
     * runs for one month-end ({period}); `{user}` is the signed-in user's id.
     *
     * @return array<int, array<string, mixed>>
     */
    private function catalog(): array
    {
        return [
            // ---------------------------------------------------------------- first-time setup
            ['key' => 'bootstrap', 'group' => 'setup', 'step' => 1,
                'label' => 'Bootstrap the system (first install)',
                'command' => 'eir:bootstrap --fresh --force-wipe --with-client-inputs --build --run-engines --verify --user={user}',
                'description' => 'Wipes this database and rebuilds it from the committed client inputs: seeds roles, governance defaults, staging thresholds and the help centre, lands the E-Banker pack and the take-on workbook, builds every loan book, runs staging, the EIR and the engines, then checks the golden numbers.',
                'danger' => 'destructive', 'confirm' => 'BOOTSTRAP',
                'note' => 'Destroys every record in the connected database. Only for a first install or a deliberate rebuild.'],
            ['key' => 'migrate', 'group' => 'setup', 'step' => 2,
                'label' => 'Apply database updates (migrations)',
                'command' => 'migrate --force',
                'description' => 'Applies any new database migrations shipped with a code update. Existing data is kept.',
                'danger' => 'caution', 'note' => 'Take a backup first on a live system (Installation Guide chapter 10).'],
            ['key' => 'seed-permissions', 'group' => 'setup', 'step' => 3,
                'label' => 'Refresh roles and permissions',
                'command' => 'db:seed --class=MaiicAdminPermissionsSeeder --force',
                'description' => 'Adds any permission a new screen needs and grants it to the internal staff roles. Nothing is removed.',
                'danger' => 'caution', 'note' => 'Additive.'],
            ['key' => 'seed-governance', 'group' => 'setup', 'step' => 4,
                'label' => 'Add missing governance defaults',
                'command' => 'db:seed --class=GovernanceSettingsSeeder --force',
                'description' => 'Adds the Governance Centre settings a new release introduces, in force from inception. A setting that already exists is never changed.',
                'danger' => 'caution', 'note' => 'Additive: approved values are left as they are.'],
            ['key' => 'seed-sicr', 'group' => 'setup', 'step' => 5,
                'label' => 'Seed the qualitative SICR factors',
                'command' => 'db:seed --class=SicrQualitativeFactorsSeeder --force',
                'description' => 'Adds the starter set of qualitative factors (forbearance, borrower condition, agricultural and climate, behavioural, credit-impaired evidence) with the stage each implies.',
                'danger' => 'caution', 'note' => 'Additive: existing groups and items are kept.'],

            // ---------------------------------------------------------------- month-end, in order
            ['key' => 'build', 'group' => 'month', 'step' => 1, 'period' => true,
                'label' => 'Build the loan book',
                'command' => 'eir:build-loan-books {period}',
                'description' => 'Builds the month\'s loan book from the landed E-Banker pack by the method in force. A locked month is refused.',
                'danger' => 'caution', 'note' => 'Writes the month\'s loan book; approve the build on Imports & Feeds, Loan Book Builds.'],
            ['key' => 'stage', 'group' => 'month', 'step' => 2, 'period' => true,
                'label' => 'Stage the book',
                'command' => 'eir:stage {period} --user={user}',
                'description' => 'Days past due against the governed thresholds, the missed-instalment trigger, then the approved qualitative SICR triggers.',
                'danger' => 'caution', 'note' => 'After re-staging, run the PD and LGD, the forward-looking route and the ECL again.'],
            ['key' => 'segments', 'group' => 'month', 'step' => 3, 'period' => true,
                'label' => 'PD and LGD by segment',
                'command' => 'ifrs9:segment-parameters {period} --user={user}',
                'description' => 'Measures and applies the PD and the LGD of each segment on the governed basis (Governance Centre, PD & LGD segments).',
                'danger' => 'caution', 'note' => 'Clears the month\'s post-FLI PD: run the forward-looking route and the ECL after it.'],
            ['key' => 'fli-correlate', 'group' => 'month', 'step' => 4, 'period' => true,
                'label' => 'Forward-looking: correlation sweep and fits',
                'command' => 'fli:correlate {period}',
                'description' => 'Sweeps the macro series against the credit-loss measures as at the month-end and fits the candidates under the guardrail. Propose and approve a fit on Regression (FLI Adjustments) next.',
                'danger' => 'caution', 'note' => 'Writes candidate fits only; nothing reaches a loan until a fit is approved.'],
            ['key' => 'fli-apply', 'group' => 'month', 'step' => 5, 'period' => true,
                'label' => 'Forward-looking: apply the approved fit',
                'command' => 'fli:apply {period} --user={user}',
                'description' => 'Applies the approved fit and the approved scenario set to every loan of the month (the post-FLI PD).',
                'danger' => 'caution', 'note' => 'Refuses when the month has no approved fit or scenario set.'],
            ['key' => 'ecl', 'group' => 'month', 'step' => 6, 'period' => true,
                'label' => 'Calculate the ECL',
                'command' => 'ifrs9:segment-ecl {period} --pd=pd_post_fli',
                'description' => 'The expected credit loss of every portfolio on the post-FLI PD.',
                'danger' => 'caution', 'note' => 'Refuses, naming the loans, when a loan with exposure has no stage, PD or LGD.'],
            ['key' => 'revenue', 'group' => 'month', 'step' => 7, 'period' => true,
                'label' => 'EIR revenue',
                'command' => 'eir:run-revenue {period} --user={user}',
                'description' => 'The EIR amortised-cost and interest revenue roll-forward for the month.',
                'danger' => 'caution', 'note' => 'Existing rows of the month are left as they are.'],
            ['key' => 'megafarm', 'group' => 'month', 'step' => 8, 'period' => true,
                'label' => 'Mega Farm programme',
                'command' => 'megafarm:ecl {period} --user={user}',
                'description' => 'Runs the Mega Farm programme through the ECL module under its governed scope and method.',
                'danger' => 'caution', 'note' => 'Writes the programme\'s ECL run for the month.'],

            // ---------------------------------------------------------------- imports
            ['key' => 'macro', 'group' => 'imports', 'step' => 1,
                'label' => 'Import the World Bank macro series',
                'command' => 'macro:import-worldbank',
                'description' => 'Fetches Malawi\'s macro series from the World Bank with provenance; falls back to the committed snapshot when offline.',
                'danger' => 'caution', 'note' => 'Additive. The scheduler also runs it on the second day of each month.'],
            ['key' => 'feed', 'group' => 'imports', 'step' => 2,
                'label' => 'Land new E-Banker packs from the feed folder',
                'command' => 'eir:poll-feed-folder',
                'description' => 'Lands every new pack found in the feed folder through the gates (route 2). The scheduler runs it every morning at 06:30.',
                'danger' => 'caution', 'note' => 'A pack that fails a gate is quarantined with the file and row named.'],

            // ---------------------------------------------------------------- diagnostics
            ['key' => 'about', 'group' => 'diagnostics', 'step' => 1,
                'label' => 'System snapshot',
                'command' => 'about',
                'description' => 'Application, Laravel and PHP versions, debug mode, caches and drivers. Read only.',
                'danger' => 'safe'],
            ['key' => 'migrate-status', 'group' => 'diagnostics', 'step' => 2,
                'label' => 'Database update status',
                'command' => 'migrate:status',
                'description' => 'Which migrations have run and which are pending. Read only.',
                'danger' => 'safe'],
            ['key' => 'baselines', 'group' => 'diagnostics', 'step' => 3,
                'label' => 'Check the golden numbers',
                'command' => 'eir:baselines',
                'description' => 'Runs the acceptance ties and the golden numbers against the database and reports PASS or FAIL for each.',
                'danger' => 'safe'],
            ['key' => 'smoke-reports', 'group' => 'diagnostics', 'step' => 4,
                'label' => 'Smoke-test the reports',
                'command' => 'ifrs9:smoke-reports',
                'description' => 'Opens every IFRS 9 report and renders its PDF, and reports any that fail.',
                'danger' => 'safe'],
        ];
    }

    public function index(Request $request): Response
    {
        $recent = $this->runner->recent(40);
        $periods = $this->safe(fn () => LoanBook::query()->select('reporting_period')->distinct()
            ->orderByDesc('reporting_period')->limit(36)->pluck('reporting_period')->all(), []);

        return Inertia::render('Admin/Maintenance', [
            'catalog' => array_map(fn ($c) => array_diff_key($c, ['command' => 1]) + ['shown' => $this->shown($c['command'])], $this->catalog()),
            'recent' => $recent,
            'periods' => $periods,
            'status' => [
                'database' => $this->safe(fn () => (string) DB::connection()->getDatabaseName(), null),
                'loan_book_months' => count($periods),
                'latest_month' => $periods[0] ?? null,
                'ecl_months' => $this->safe(fn () => (int) DB::table('reporting_periods')->where('ecl_calculated', 1)->count(), 0),
                'pending_migrations' => null,
                'running' => $this->runner->running(),
            ],
            'can' => ['run' => (bool) $request->user()?->hasRole('admin')],
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        $entry = collect($this->catalog())->firstWhere('key', (string) $request->input('key'));
        if (! $entry) {
            return response()->json(['ok' => false, 'message' => 'Unknown command.'], 422);
        }
        if (($entry['danger'] ?? '') === 'destructive' && (string) $request->input('confirm') !== ($entry['confirm'] ?? '')) {
            return response()->json(['ok' => false, 'message' => 'Type the confirmation phrase to run this command.'], 422);
        }
        if ($busy = $this->runner->running()) {
            return response()->json(['ok' => false, 'message' => "\"{$busy['label']}\" is still running. Wait for it to finish."], 409);
        }

        $period = null;
        if (! empty($entry['period'])) {
            $period = (string) $request->input('period');
            $known = LoanBook::query()->where('reporting_period', $period)->exists();
            // a new month can be built; every other step needs the month's loan book
            if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) || (! $known && $entry['key'] !== 'build')) {
                return response()->json(['ok' => false, 'message' => 'Choose a month the system holds a loan book for.'], 422);
            }
        }

        $command = strtr($entry['command'], ['{period}' => (string) $period, '{user}' => (string) (int) $request->user()->id]);
        $label = $entry['label'] . ($period ? " ({$period})" : '');
        $id = $this->runner->launch($command, $label, ['danger' => $entry['danger'], 'group' => $entry['group'], 'key' => $entry['key']]);

        AuditLoggerService::log('Maintenance Command Launched', 'maintenance', null, [
            'reporting_period' => $period,
            'new_values' => ['command' => $command, 'danger' => $entry['danger'], 'run_id' => $id],
            'meta' => ['label' => $label],
        ]);

        return response()->json(['ok' => true, 'id' => $id, 'command' => $command]);
    }

    public function output(string $id): JsonResponse
    {
        $status = $this->runner->status($id);

        return $status === null
            ? response()->json(['ok' => false, 'message' => 'Run not found.'], 404)
            : response()->json(['ok' => true, 'run' => $status]);
    }

    /** The command line as the user sees it ({user} stays a placeholder). */
    private function shown(string $command): string
    {
        return 'php artisan ' . str_replace('{user}', '<you>', $command);
    }

    private function safe(callable $fn, mixed $default): mixed
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return $default;
        }
    }
}
