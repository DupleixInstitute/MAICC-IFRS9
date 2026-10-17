<?php

namespace App\Console\Commands;

use App\Services\Ebanker\ClientBookReconciliationService;
use App\Services\Ebanker\LoanBookBuildService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The client's own loan books against the rebuilt book, from the command line (Client Book
 * Reconciliation Specification v1). The screen is Customer & Loan Data, Client Book
 * Reconciliation; this does the same landing and comparing, and the bootstrap's step 6.13 runs
 * `manifest` over the committed books.
 *
 *   php artisan eir:client-book land "Loan Book 31 Aug 2026.xls" --period=2026-08 --label="E-Banker loan book report, 31 August 2026" --user=1
 *   php artisan eir:client-book compare --book=4
 *   php artisan eir:client-book manifest            land and compare every book of docs/bootstrap/client_books/manifest.json
 *   php artisan eir:client-book list
 *   php artisan eir:client-book schedule "Dec 2025 stages.csv" --period=2025-12 --label="Finance stages, December 2025" --reason="..." --user=5
 *   php artisan eir:client-book schedules         apply every override schedule of docs/bootstrap/override_schedules/manifest.json (bootstrap label)
 */
class ClientBook extends Command
{
    public const MANIFEST = 'docs/bootstrap/client_books/manifest.json';

    protected $signature = 'eir:client-book {action : land, compare, manifest, list, schedule or schedules} {file? : The client book file (land) or the override schedule (schedule)}
        {--reason= : The reason recorded on every override of a schedule}
        {--period= : Month-end YYYY-MM (land)} {--kind=ebanker_report : ebanker_report, audited_model or template} {--label= : The book\'s name (land)}
        {--book= : Client book id (compare)} {--user=1 : Who lands it} {--bootstrap : Land under the bootstrap label} {--inputs=docs/bootstrap : Folder of the committed inputs (manifest)}';

    protected $description = 'Land a loan book the client gave for a month-end and compare it with the rebuilt book: every difference on the register and in a CSV';

    public function handle(ClientBookReconciliationService $service): int
    {
        try {
            return match ((string) $this->argument('action')) {
                'land' => $this->land($service),
                'compare' => $this->compare($service, (int) $this->option('book')),
                'manifest' => $this->manifest($service),
                'list' => $this->listBooks(),
                'schedule' => $this->schedule($service),
                'schedules' => $this->schedules($service),
                default => throw new \InvalidArgumentException('The action is land, compare, manifest or list.'),
            };
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function land(ClientBookReconciliationService $service): int
    {
        $file = (string) $this->argument('file');
        $path = is_file($file) ? $file : base_path($file);
        $r = $service->land($path, (string) $this->option('period'), (string) $this->option('kind'), (string) $this->option('label'), (int) $this->option('user'),
            $this->option('bootstrap') ? LoanBookBuildService::BOOTSTRAP_LABEL : null);
        $this->info("Client book {$r['book_id']}: {$r['status']} ({$r['rows']} rows).");

        return $this->compare($service, $r['book_id']);
    }

    private function compare(ClientBookReconciliationService $service, int $book): int
    {
        $c = $service->compare($book);
        $this->info("{$c['period']}: {$c['compared']} loans matched, {$c['same']} the same, {$c['differences']} to decide.");
        $this->table(['Cause', 'Loans'], collect($c['by_cause'])->map(fn ($n, $k) => [$k, $n])->values()->all());
        $this->line('Differences: ' . storage_path('app/' . $c['csv']));

        return self::SUCCESS;
    }

    private function manifest(ClientBookReconciliationService $service): int
    {
        foreach (self::landManifest($service, base_path((string) $this->option('inputs')), (int) $this->option('user')) as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }

    /**
     * Land and compare every book of the committed manifest whose month the system has built.
     * A month not built is named and skipped; a file that fails its checks stops nothing else.
     *
     * @return list<string> one line per book
     */
    public static function landManifest(ClientBookReconciliationService $service, string $inputs, int $user): array
    {
        $path = rtrim($inputs, '/\\') . '/client_books/manifest.json';
        if (! is_file($path)) {
            return ['no client book manifest at ' . $path];
        }
        $built = DB::table('loan_books')->distinct()->pluck('reporting_period')->flip();
        $out = [];
        foreach (json_decode((string) file_get_contents($path), true)['books'] ?? [] as $b) {
            $file = rtrim($inputs, '/\\') . '/' . $b['file'];
            if (! isset($built[$b['period']])) {
                $out[] = "{$b['period']} {$b['kind']}: not compared, the system has not built {$b['period']}";
                continue;
            }
            try {
                if (! is_file($file)) {
                    throw new \RuntimeException("the file {$b['file']} is not in the inputs");
                }
                if (isset($b['sha256']) && hash_file('sha256', $file) !== $b['sha256']) {
                    throw new \RuntimeException("{$b['file']} is not the file the manifest names (sha256 differs)");
                }
                $l = $service->land($file, $b['period'], $b['kind'], $b['label'], $user, LoanBookBuildService::BOOTSTRAP_LABEL);
                $c = $service->compare($l['book_id']);
                $causes = collect($c['by_cause'])->map(fn ($n, $k) => "{$k} {$n}")->implode(', ');
                $out[] = "{$b['period']} {$b['kind']} (book {$l['book_id']}): {$c['compared']} matched, {$c['same']} the same, {$c['differences']} to decide" . ($causes !== '' ? " ({$causes})" : '');
            } catch (Throwable $e) {
                $out[] = "{$b['period']} {$b['kind']}: NOT LANDED: " . substr($e->getMessage(), 0, 200);
            }
        }

        return $out;
    }

    /** One override schedule from the command line: each filled cell proposed; a different person approves it on the screen. */
    private function schedule(ClientBookReconciliationService $service): int
    {
        $file = (string) $this->argument('file');
        $r = $service->applySchedule(is_file($file) ? $file : base_path($file), (string) $this->option('period'), (string) $this->option('label'), (string) $this->option('reason'), (int) $this->option('user'));
        $this->info("Schedule landed as client book {$r['book_id']} ({$r['status']}): proposed {$r['proposed']['stage']} stage, {$r['proposed']['days']} day count, {$r['proposed']['amount']} gross amount override(s). A different person approves it.");
        foreach ($r['skipped'] as $line) {
            $this->line('  not proposed: ' . $line);
        }

        return self::SUCCESS;
    }

    private function schedules(ClientBookReconciliationService $service): int
    {
        foreach (self::applySchedules($service, base_path((string) $this->option('inputs')), (int) $this->option('user')) as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }

    /**
     * The client's committed override schedules (docs/bootstrap/override_schedules/manifest.json), as a clean install
     * lands its other client inputs: each landed, every filled cell proposed and approved under the bootstrap label
     * (an automated approval of a committed client input, not a MAIIC approval), and a month with a day-count or gross
     * amount override built again with it (a stage override needs only the staging that follows). A schedule whose
     * month is not built is named and skipped; a file that fails its checks stops nothing else.
     *
     * @return list<string> one line per schedule
     */
    public static function applySchedules(ClientBookReconciliationService $service, string $inputs, int $user): array
    {
        $path = rtrim($inputs, '/\\') . '/override_schedules/manifest.json';
        if (! is_file($path)) {
            return ['no override schedule manifest at ' . $path];
        }
        $out = [];
        foreach (json_decode((string) file_get_contents($path), true)['schedules'] ?? [] as $s) {
            $file = rtrim($inputs, '/\\') . '/' . $s['file'];
            try {
                if (! is_file($file)) {
                    throw new \RuntimeException("the file {$s['file']} is not in the inputs");
                }
                if (isset($s['sha256']) && hash_file('sha256', $file) !== $s['sha256']) {
                    throw new \RuntimeException("{$s['file']} is not the file the manifest names (sha256 differs)");
                }
                $r = $service->applySchedule($file, $s['period'], $s['label'], $s['reason'], $user, null, LoanBookBuildService::BOOTSTRAP_LABEL);
                $a = $service->approveSchedule($r['book_id'], $user, LoanBookBuildService::BOOTSTRAP_LABEL);
                $rebuilt = '';
                if ($r['proposed']['days'] + $r['proposed']['amount'] > 0 && $a['approved'] > 0) {
                    $b = app(LoanBookBuildService::class)->build($s['period'], $s['period'], null, null, null, LoanBookBuildService::BOOTSTRAP_LABEL);
                    $rebuilt = "; {$s['period']} built again ({$b['status']}, build {$b['build_id']})";
                }
                $out[] = "{$s['period']} (book {$r['book_id']}): proposed {$r['proposed']['stage']} stage, {$r['proposed']['days']} day count, {$r['proposed']['amount']} gross amount; approved {$a['approved']} under the bootstrap label"
                    . ($a['refused'] !== [] ? '; refused ' . count($a['refused']) . ': ' . substr($a['refused'][0], 0, 120) : '') . (count($r['skipped']) ? '; ' . count($r['skipped']) . ' not proposed' : '') . $rebuilt;
            } catch (Throwable $e) {
                $out[] = "{$s['period']}: NOT APPLIED: " . substr($e->getMessage(), 0, 200);
            }
        }

        return $out;
    }

    private function listBooks(): int
    {
        $rows = DB::table('client_loan_books')->orderBy('reporting_period')->orderBy('id')->get()->map(function ($b) {
            $s = $b->summary ? json_decode($b->summary, true) : [];

            return [$b->id, $b->reporting_period, $b->kind, mb_substr($b->source_label, 0, 50), $b->row_count, $b->status, $s['same'] ?? '-', $s['differences'] ?? '-'];
        });
        $this->table(['Id', 'Month', 'Kind', 'Book', 'Rows', 'Status', 'Same', 'To decide'], $rows->all());

        return self::SUCCESS;
    }
}
