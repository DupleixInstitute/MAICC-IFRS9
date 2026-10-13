<?php

namespace App\Services\Maintenance;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Launches a whitelisted artisan command as a detached background process and
 * streams its real stdout/stderr to a per-run log file, so the admin Maintenance
 * Console can show the same output as the shell, then poll for completion.
 *
 * Cross-platform: writes a small launcher script, fires it detached (ported from the Stress Testing app's console), and appends
 * a sentinel line ("===EXIT:<code>===") so the poller can detect completion.
 */
class CommandRunner
{
    private string $dir;

    public function __construct()
    {
        $this->dir = storage_path('app/command-runs');
        if (! is_dir($this->dir)) {
            mkdir($this->dir, 0775, true);
        }
    }

    /** The CLI php binary (web SAPI's PHP_BINARY is unreliable). */
    private function phpBinary(): string
    {
        $configured = config('maintenance.php_binary');
        if ($configured && is_file($configured)) {
            return $configured;
        }
        if (PHP_OS_FAMILY === 'Windows' && is_file('C:\\xampp\\php\\php.exe')) {
            return 'C:\\xampp\\php\\php.exe';
        }

        return PHP_BINARY ?: 'php';
    }

    /**
     * Launch `php artisan <command>` detached. Returns the run id.
     *
     * @param array<string,mixed> $extra Extra metadata persisted on the run
     *                                   (e.g. danger, group, key) for the history UI.
     */
    public function launch(string $command, string $label, array $extra = []): string
    {
        $id = Carbon::now()->format('Ymd-His') . '-' . Str::lower(Str::random(5));
        $log = $this->dir . '/' . $id . '.log';
        $meta = $this->dir . '/' . $id . '.json';

        file_put_contents($meta, json_encode(array_merge([
            'danger'     => 'safe',
            'group'      => null,
            'key'        => null,
        ], $extra, [
            'id'         => $id,
            'command'    => $command,
            'label'      => $label,
            'status'     => 'running',
            'exit_code'  => null,
            'started_by' => Auth::user()?->name,
            'started_at' => Carbon::now()->toIso8601String(),
        ]), JSON_PRETTY_PRINT));

        $php = $this->phpBinary();
        $artisan = base_path('artisan');

        if (PHP_OS_FAMILY === 'Windows') {
            $bat = $this->dir . '/' . $id . '.bat';
            $content = "@echo off\r\n"
                . '"' . $php . '" "' . $artisan . '" ' . $command . ' > "' . $log . '" 2>&1' . "\r\n"
                . 'echo ===EXIT:%ERRORLEVEL%=== >> "' . $log . '"' . "\r\n";
            file_put_contents($bat, $content);
            // start /B detaches; the cmd window is hidden. The pipe closes
            // immediately (process detached) — suppress the benign warning.
            $h = @popen('start /B "" "' . $bat . '"', 'r');
            if ($h !== false) {
                @pclose($h);
            }
        } else {
            $sh = $this->dir . '/' . $id . '.sh';
            $content = "#!/usr/bin/env bash\n"
                . escapeshellarg($php) . ' ' . escapeshellarg($artisan) . ' ' . $command . ' > ' . escapeshellarg($log) . " 2>&1\n"
                . 'echo "===EXIT:$?===" >> ' . escapeshellarg($log) . "\n";
            file_put_contents($sh, $content);
            chmod($sh, 0755);
            exec('nohup ' . escapeshellarg($sh) . ' > /dev/null 2>&1 &');
        }

        return $id;
    }

    /**
     * Current status + output for a run.
     *
     * @return array{id:string,status:string,exit_code:?int,output:string,label:?string,started_by:?string,started_at:?string}|null
     */
    public function status(string $id): ?array
    {
        $id = preg_replace('/[^A-Za-z0-9\-]/', '', $id); // path-safety
        $meta = $this->dir . '/' . $id . '.json';
        $log  = $this->dir . '/' . $id . '.log';
        if (! is_file($meta)) {
            return null;
        }
        $m = json_decode((string) file_get_contents($meta), true) ?: [];
        $output = is_file($log) ? (string) file_get_contents($log) : '';

        $status = $m['status'] ?? 'running';
        $exit = $m['exit_code'] ?? null;
        if (preg_match('/===EXIT:(-?\d+)===/', $output, $mm)) {
            $exit = (int) $mm[1];
            $status = $exit === 0 ? 'done' : 'failed';
            $output = trim(preg_replace('/===EXIT:-?\d+===\s*$/', '', $output));
            if (($m['status'] ?? null) === 'running') {
                $m['status'] = $status;
                $m['exit_code'] = $exit;
                $m['finished_at'] = Carbon::now()->toIso8601String();
                file_put_contents($meta, json_encode($m, JSON_PRETTY_PRINT));
            }
        }

        return [
            'id'          => $id,
            'status'      => $status,
            'exit_code'   => $exit,
            'output'      => $this->stripAnsi($output),
            'label'       => $m['label'] ?? null,
            'command'     => $m['command'] ?? null,
            'danger'      => $m['danger'] ?? 'safe',
            'group'       => $m['group'] ?? null,
            'started_by'  => $m['started_by'] ?? null,
            'started_at'  => $m['started_at'] ?? null,
            'finished_at' => $m['finished_at'] ?? null,
            'duration'    => $this->duration($m['started_at'] ?? null, $m['finished_at'] ?? null),
        ];
    }

    /** Human-friendly duration between two ISO timestamps, or null. */
    private function duration(?string $start, ?string $end): ?string
    {
        if (! $start || ! $end) {
            return null;
        }
        $secs = Carbon::parse($end)->diffInSeconds(Carbon::parse($start), true);
        if ($secs < 60) {
            return $secs . 's';
        }
        $m = intdiv($secs, 60);
        $s = $secs % 60;

        return $s ? "{$m}m {$s}s" : "{$m}m";
    }

    /** @return array<int, array<string,mixed>> recent runs (newest first) */
    public function recent(int $limit = 15): array
    {
        $files = glob($this->dir . '/*.json') ?: [];
        rsort($files);
        $runs = [];
        foreach (array_slice($files, 0, $limit) as $f) {
            $id = basename($f, '.json');
            // Finalize from the log so finished_at / exit_code are accurate even
            // if nothing polled status() after the run completed.
            $s = $this->status($id);
            if ($s === null) {
                continue;
            }
            $runs[] = [
                'id'          => $s['id'],
                'label'       => $s['label'] ?? '-',
                'command'     => $s['command'] ?? '',
                'status'      => $s['status'],
                'exit_code'   => $s['exit_code'],
                'danger'      => $s['danger'],
                'group'       => $s['group'],
                'started_by'  => $s['started_by'],
                'started_at'  => $s['started_at'],
                'finished_at' => $s['finished_at'],
                'duration'    => $s['duration'],
            ];
        }

        return $runs;
    }

    /** The run still in progress, if any: one maintenance command at a time. */
    public function running(): ?array
    {
        foreach ($this->recent(10) as $r) {
            if (($r['status'] ?? '') === 'running') {
                return $r;
            }
        }

        return null;
    }

    private function stripAnsi(string $s): string
    {
        return preg_replace('/\e\[[0-9;]*m/', '', $s) ?? $s;
    }
}
