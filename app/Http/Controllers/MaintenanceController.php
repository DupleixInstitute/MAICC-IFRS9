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
            ['key' => 'update', 'group' => 'setup', 'step' => 2,
                'label' => 'Update the system after a new release',
                'command' => 'maiic:task update',
                'description' => 'Applies the database updates of a new release, then adds any permission, Governance Centre default and qualitative SICR factor the release brings. Approved settings and existing records are left as they are.',
                'danger' => 'caution', 'note' => 'Additive. Run it once after every deployment.'],

            // ---------------------------------------------------------------- month-end
            ['key' => 'build', 'group' => 'month', 'step' => 1, 'period' => true,
                'label' => 'Build the loan book',
                'command' => 'eir:build-loan-books {period}',
                'description' => 'Builds the loan book of the month from the landed E-Banker pack. A second person approves the build on Imports & Feeds, Loan Book Builds, before the calculations run.',
                'danger' => 'caution', 'note' => 'A locked month is refused.'],
            ['key' => 'month-end', 'group' => 'month', 'step' => 2, 'period' => true,
                'label' => 'Run the month-end calculations',
                'command' => 'maiic:task month-end {period} --user={user}',
                'description' => 'In order: staging, the PD and LGD by segment, the forward-looking route with the scenario projection, the ECL and the month record, the EIR revenue, then the Mega Farm programme. It stops at the first step that cannot run and says why.',
                'danger' => 'caution', 'note' => 'Needs an approved loan book build, a model version in force and an approved scenario set. A second person then signs the month off on Forward-Looking Information, Overview.'],
            ['key' => 'fli-correlate', 'group' => 'month', 'step' => 3, 'period' => true,
                'label' => 'Recalibrate the forward-looking model',
                'command' => 'fli:correlate {period}',
                'description' => 'Refreshes the credit-loss measures and sweeps every economic variable against them as at the month-end, then fits the candidates under the guardrail. Done once a year, or when an event breaks the relationship; a fit reaches the loans only after it is approved on Regression.',
                'danger' => 'caution', 'note' => 'Writes candidate fits only.'],

            // ---------------------------------------------------------------- imports and checks
            ['key' => 'refresh-data', 'group' => 'imports', 'step' => 1,
                'label' => 'Fetch new data',
                'command' => 'maiic:task refresh-data',
                'description' => 'Fetches the World Bank macro series with their provenance, then lands every new E-Banker pack in the feed folder through the gates. The scheduler also runs both on its own.',
                'danger' => 'caution', 'note' => 'Additive. A pack that fails a gate is quarantined with the file and row named.'],
            ['key' => 'health-check', 'group' => 'imports', 'step' => 2,
                'label' => 'Check the system',
                'command' => 'maiic:task health-check',
                'description' => 'A snapshot of the environment, the database update status, the golden numbers and acceptance ties, then every report and its PDF. Reports PASS or FAIL for each.',
                'danger' => 'safe', 'note' => 'Read only.'],
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
