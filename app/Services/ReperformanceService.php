<?php

namespace App\Services;

use App\Services\Eir\StagingPolicy;
use App\Services\Eir\StagingService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Re-performance of a month (Staging Module Specification v1, section 8.8,
 * item 6; acceptance 19): proof that the figures a run left can be reproduced
 * from what it froze, not only stated.
 *
 * In place (from a screen or the console), nothing is written but the
 * re-performance's own record:
 *   inputs    the month's inputs as they stand now, against the snapshot the
 *             run froze: for a locked month they cannot have moved (no
 *             back-dating, section 8.8 item 4), so any difference is named
 *   stages    every loan staged again under the staging rules the snapshot
 *             froze, through the evaluation the comparison run uses (section
 *             4), against the stage the run left
 *   figures   every loan's stage, PD, LGD, EAD, ECL, discounted ECL, EIR
 *             interest and closing gross as the month holds them now, against
 *             the figures the run left (run_record_loans)
 *
 * In a workspace (the console only): the database is copied into a named
 * throwaway workspace, the month's engines are run again there by the
 * month-end job, and every loan's figures in the workspace are checked
 * against the run's. The workspace is dropped afterwards unless kept.
 */
class ReperformanceService
{
    public const IN_PLACE = 'IN_PLACE';

    public const WORKSPACE = 'WORKSPACE';

    /** The sections of a snapshot fixed to the month; the rest (code, query register, loads, fee rules, locked EIRs) move on with later work by design. */
    public const MONTH_ANCHORED = ['Governance Centre settings', 'Staging thresholds', 'Scenario set', 'Forward-looking overlays', 'Forward-looking model', 'Forward-looking month record', 'Loan book'];

    /** Figure tolerances: money to the half-cent, rates to ten decimal places. */
    public const TOLERANCE = ['stage' => 0, 'pd' => 1e-10, 'lgd' => 1e-10, 'ead' => 0.005, 'ecl' => 0.005, 'ecl_discounted' => 0.005, 'eir_interest' => 0.005, 'closing_gross' => 0.005];

    public function __construct(private RunRecordService $runs, private StagingService $staging)
    {
    }

    /** The run a re-performance of the month checks: its run of record, else the run that stands for it. */
    public function runFor(string $period, ?int $runId = null): object
    {
        $run = $runId !== null ? DB::table('run_records')->where('id', $runId)->first() : $this->runs->monthRun($period);
        if ($run === null || $run->snapshot === null) {
            throw new LogicException("No completed run of {$period} with a frozen snapshot to re-perform.");
        }
        if (! $this->runs->verifies($run)) {
            throw new LogicException("Run {$run->id} does not verify against its fingerprint: its snapshot was altered after the run, so nothing can be re-performed from it.");
        }

        return $run;
    }

    public function inPlace(object $run, ?int $userId): int
    {
        $id = $this->open($run, self::IN_PLACE, null, $userId);
        try {
            $snapshot = json_decode((string) $run->snapshot, true);
            $found = array_merge($this->inputs($run, $snapshot), $this->stages($run, $snapshot), $this->figures($run, DB::connection(), 'figure'));
            $this->close($id, $found, ['loans' => $this->storedCount($run)]);
        } catch (Throwable $e) {
            $this->fail($id, $e);
            throw $e;
        }

        return $id;
    }

    /**
     * The engines run again on a copy (the console only; MySQL or MariaDB). $workspace names a throwaway
     * database ending in "_reperform"; it must not be this database. The month is unlocked in the copy
     * only, the month-end job and the revenue recalculation are run there, and each loan is compared.
     */
    public function inWorkspace(object $run, string $workspace, ?int $userId, bool $keep = false, ?callable $say = null): int
    {
        $say ??= fn (string $m) => null;
        $source = DB::connection()->getDatabaseName();
        if (! preg_match('/^[A-Za-z0-9_]+_reperform$/', $workspace) || strcasecmp($workspace, $source) === 0) {
            throw new LogicException("The workspace is a throwaway database named ..._reperform, never this database ({$source}).");
        }
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            throw new LogicException('A workspace re-performance copies the database, which needs MySQL or MariaDB.');
        }
        $id = $this->open($run, self::WORKSPACE, $workspace, $userId);
        try {
            $say("Copying {$source} into {$workspace}.");
            $this->copyDatabase($source, $workspace);
            Config::set('database.connections.reperform', array_merge(config('database.connections.' . config('database.default')), ['database' => $workspace]));
            DB::purge('reperform');
            $ws = DB::connection('reperform');
            // the copy only: its lock and its run records are its own and go, so the month can be run again there
            $ws->table('reporting_period_locks')->where('reporting_period', $run->reporting_period)->delete();
            $user = (string) ($userId ?? $run->started_by ?? 1);
            foreach ([['maiic:task', 'month-end', $run->reporting_period, '--user=' . $user],
                ['eir:run-revenue', $run->reporting_period, '--user=' . $user, '--recalculate', '--reason=Re-performance of run ' . $run->id . ' in a workspace copy']] as $command) {
                $say('Running ' . implode(' ', $command) . " in {$workspace}.");
                $p = new Process(array_merge([PHP_BINARY, base_path('artisan')], $command), base_path(), ['DB_DATABASE' => $workspace, 'APP_ENV' => app()->environment()], null, 7200);
                $p->run();
                if (! $p->isSuccessful()) {
                    throw new LogicException('The engines failed in the workspace: ' . mb_substr(trim($p->getOutput() . ' ' . $p->getErrorOutput()), -800));
                }
            }
            $found = $this->figures($run, $ws, 'workspace');
            $this->close($id, $found, ['loans' => $this->storedCount($run), 'workspace' => $workspace, 'kept' => $keep]);
        } catch (Throwable $e) {
            $this->fail($id, $e);
            throw $e;
        } finally {
            DB::purge('reperform');
            if (! $keep) {
                try {
                    DB::statement('DROP DATABASE IF EXISTS `' . $workspace . '`');
                } catch (Throwable) {
                }
            }
        }

        return $id;
    }

    // ----- the checks ----------------------------------------------------------

    /** The month-anchored inputs now, against the frozen snapshot. */
    private function inputs(object $run, array $snapshot): array
    {
        $now = RunRecordService::flatten($this->runs->snapshot($run->reporting_period, (string) $run->code_version));
        $then = RunRecordService::flatten($snapshot);
        $out = [];
        foreach (self::MONTH_ANCHORED as $section) {
            foreach (array_unique(array_merge(array_keys($then[$section] ?? []), array_keys($now[$section] ?? []))) as $item) {
                $a = $then[$section][$item] ?? '(none)';
                $b = $now[$section][$item] ?? '(none)';
                if ($a !== $b) {
                    $out[] = ['check' => 'input', 'contract_id' => null, 'item' => mb_substr($section . ': ' . $item, 0, 255), 'stored' => $a, 'reperformed' => $b,
                        'note' => 'the input of the month no longer matches what the run froze'];
                }
            }
        }

        return $out;
    }

    /** Every loan staged again under the frozen rules, against the stage the run left. */
    private function stages(object $run, array $snapshot): array
    {
        $values = array_map(fn ($s) => $s['value'] ?? null, (array) ($snapshot['governance_settings'] ?? []));
        $policy = StagingPolicy::fromValues($values);
        $evaluated = $this->staging->evaluate($run->reporting_period, $policy)['loans'];
        $stored = DB::table('run_record_loans')->where('run_record_id', $run->id)->pluck('stage', 'contract_id');
        $out = [];
        foreach ($stored as $contract => $stage) {
            $again = $evaluated[(string) $contract]['stage'] ?? null;
            if ($again === null) {
                $out[] = ['check' => 'stage', 'contract_id' => (string) $contract, 'item' => 'stage', 'stored' => (string) $stage, 'reperformed' => '(not staged)', 'note' => 'the loan is not in the month now'];
            } elseif ((int) $again !== (int) $stage) {
                $out[] = ['check' => 'stage', 'contract_id' => (string) $contract, 'item' => 'stage', 'stored' => (string) $stage, 'reperformed' => (string) $again,
                    'note' => 'staged again under the frozen rules: ' . ($evaluated[(string) $contract]['reason'] ?? '') . ', ' . ($evaluated[(string) $contract]['dpd'] ?? '?') . ' days'];
            }
        }

        return $out;
    }

    /** Every loan's figures from a connection (this database now, or the workspace), against the run's. */
    private function figures(object $run, \Illuminate\Database\ConnectionInterface $db, string $check): array
    {
        $now = [];
        foreach (RunRecordService::loanFigures($db, $run->reporting_period) as $chunk) {
            foreach ($chunk as $l) {
                $now[$l['contract_id']] = $l;
            }
        }
        $out = [];
        foreach (DB::table('run_record_loans')->where('run_record_id', $run->id)->orderBy('contract_id')->get() as $s) {
            $l = $now[(string) $s->contract_id] ?? null;
            if ($l === null) {
                $out[] = ['check' => $check, 'contract_id' => (string) $s->contract_id, 'item' => 'loan', 'stored' => 'in the month', 'reperformed' => '(missing)', 'note' => 'the loan is not in the month'];
                continue;
            }
            foreach (self::TOLERANCE as $figure => $tolerance) {
                $a = $s->{$figure};
                $b = $l[$figure] ?? null;
                if ($a === null && $b === null) {
                    continue;
                }
                if ($a === null || $b === null || abs((float) $a - (float) $b) > $tolerance) {
                    $out[] = ['check' => $check, 'contract_id' => (string) $s->contract_id, 'item' => $figure, 'stored' => $a === null ? null : (string) $a, 'reperformed' => $b === null ? null : (string) $b, 'note' => null];
                }
            }
            unset($now[(string) $s->contract_id]);
        }
        foreach (array_keys($now) as $contract) {
            $out[] = ['check' => $check, 'contract_id' => (string) $contract, 'item' => 'loan', 'stored' => '(not in the run)', 'reperformed' => 'in the month', 'note' => 'a loan the run did not have'];
        }

        return $out;
    }

    // ----- the record ----------------------------------------------------------

    private function storedCount(object $run): int
    {
        return DB::table('run_record_loans')->where('run_record_id', $run->id)->count();
    }

    private function open(object $run, string $mode, ?string $workspace, ?int $userId): int
    {
        return (int) DB::table('reperformances')->insertGetId(['run_record_id' => $run->id, 'reporting_period' => $run->reporting_period, 'mode' => $mode, 'workspace' => $workspace,
            'status' => 'RUNNING', 'started_by' => $userId, 'started_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function close(int $id, array $found, array $summary): void
    {
        foreach (array_chunk($found, 500) as $chunk) {
            DB::table('reperformance_rows')->insert(array_map(fn ($f) => ['reperformance_id' => $id] + array_map(fn ($v) => is_string($v) ? mb_substr($v, 0, 2000) : $v, $f), $chunk));
        }
        $by = [];
        foreach ($found as $f) {
            $by[$f['check']] = ($by[$f['check']] ?? 0) + 1;
        }
        $status = $found === [] ? 'REPRODUCED' : 'DIFFERENCES';
        DB::table('reperformances')->where('id', $id)->update(['status' => $status, 'finished_at' => now(), 'summary' => json_encode($summary + ['differences' => count($found), 'by_check' => $by]), 'updated_at' => now()]);
        $r = DB::table('reperformances')->find($id);
        AuditLoggerService::log('Month Re-performed', 'reperformances', $id, ['reporting_period' => $r->reporting_period,
            'new_values' => ['run' => $r->run_record_id, 'mode' => $r->mode, 'status' => $status, 'differences' => count($found)], 'meta' => ['user' => $r->started_by]]);
    }

    private function fail(int $id, Throwable $e): void
    {
        DB::table('reperformances')->where('id', $id)->update(['status' => 'FAILED', 'finished_at' => now(), 'error' => mb_substr($e->getMessage(), 0, 2000), 'updated_at' => now()]);
    }

    /** mysqldump the database into a fresh workspace database, with the routines and triggers. */
    private function copyDatabase(string $source, string $workspace): void
    {
        $c = config('database.connections.' . config('database.default'));
        $bin = rtrim((string) env('MYSQL_BIN_PATH', is_dir('C:/xampp/mysql/bin') ? 'C:/xampp/mysql/bin' : ''), '/\\');
        $exe = fn (string $name) => $bin === '' ? $name : $bin . '/' . $name;
        $auth = ['--host=' . $c['host'], '--port=' . $c['port'], '--user=' . $c['username']];
        $env = ['MYSQL_PWD' => (string) ($c['password'] ?? '')];
        DB::statement('DROP DATABASE IF EXISTS `' . $workspace . '`');
        DB::statement('CREATE DATABASE `' . $workspace . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $dumpFile = storage_path('app/reperform-' . $workspace . '.sql');
        $dump = new Process(array_merge([$exe('mysqldump')], $auth, ['--single-transaction', '--routines', '--triggers', '--result-file=' . $dumpFile, $source]), null, $env, null, 3600);
        $dump->run();
        if (! $dump->isSuccessful()) {
            throw new LogicException('The copy failed (mysqldump): ' . mb_substr($dump->getErrorOutput(), 0, 500));
        }
        $load = Process::fromShellCommandline('"${:MYSQL}" ' . implode(' ', array_map('escapeshellarg', $auth)) . ' ' . escapeshellarg($workspace) . ' < "${:DUMP}"', null,
            $env + ['MYSQL' => $exe('mysql'), 'DUMP' => $dumpFile], null, 3600);
        $load->run();
        @unlink($dumpFile);
        if (! $load->isSuccessful()) {
            throw new LogicException('The copy failed (mysql): ' . mb_substr($load->getErrorOutput(), 0, 500));
        }
    }
}
