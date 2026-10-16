<?php

namespace App\Services\Ebanker\DirectRead;

use App\Services\AuditLoggerService;
use App\Services\Ebanker\PackLandingService;
use App\Support\DatabaseScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Route 4 of the E-Banker feed (spec v4 section 6.5; data access request of 14 October 2026): the
 * system reads E-Banker itself with the registered statements and lands what it read through the
 * same door and gates as every other route.
 *
 * The read is written out as a pack (one CSV per statement plus manifest.json) under
 * storage/app/ebanker-direct and handed to PackLandingService::land as ROUTE_4_DIRECT, so the gates,
 * the versioning and the audit lines are those of a manual pack, and the folder stays as the record
 * of what was read. An incremental statement reads above the query's own watermark from the loads
 * already landed (0 before its first load, so the first read is its whole history); a statement
 * that returns no rows is named in the manifest, not landed. A failure stops the read before
 * anything lands, logged with the query id and the Oracle error number only.
 */
class DirectReadRunner
{
    public function __construct(private readonly PackLandingService $landing)
    {
    }

    /** @return array{pack_dir:string,reads:array<string,array>,landing:array} */
    public function run(EbankerSource $source, string $period, ?int $userId = null, bool $dryRun = false): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new InvalidArgumentException("Period '{$period}' is not YYYY-MM.");
        }
        $statements = RegisteredStatements::runnable();
        if ($statements === []) {
            throw new DirectReadException('The register holds no route 4 statement: run the EbankerQuerySeeder.');
        }
        PackLandingService::raiseMemoryLimit();
        $watermarks = $this->watermarks();
        $dir = DatabaseScope::storagePath('ebanker-direct') . DIRECTORY_SEPARATOR . 'pack-' . $period . '-' . now()->format('Ymd-His-v');
        mkdir($dir, 0775, true);

        $files = []; $notLanded = []; $reads = [];
        foreach ($statements as $q) {
            $binds = [];
            foreach (RegisteredStatements::bindsIn($q->sql) as $b) {
                $binds[$b] = $b === 'watermark' ? ($watermarks[$q->query_id] ?? 0) : $period . '-01';
            }
            $file = $q->query_id . '_' . Str::slug($q->title, '_') . '.csv';
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            $started = microtime(true);
            try {
                $rows = $this->write($path, $source->select($q->sql, $binds));
            } catch (Throwable $e) {
                $error = OracleConnectionSource::oracleError($e);
                AuditLoggerService::log('E-Banker Direct Read Failed', 'ebanker_queries', null, ['new_values' => ['query_id' => $q->query_id, 'error' => $error, 'read_before' => $reads],
                    'meta' => ['route' => PackLandingService::ROUTE_DIRECT, 'period' => $period, 'loaded_by' => $userId]]);
                throw new DirectReadException("Route 4 stopped at {$q->query_id}: {$error}. Nothing was landed.", 0, $e);
            }
            $reads[$q->query_id] = ['rows' => $rows, 'ms' => (int) round((microtime(true) - $started) * 1000), 'binds' => $binds];
            if ($rows === 0) {
                @unlink($path);
                $notLanded[] = ['query_id' => $q->query_id, 'reason' => 'no rows' . (isset($binds['watermark']) ? " above the watermark {$binds['watermark']}" : '')];
                continue;
            }
            $files[] = ['file' => $file, 'query_id' => $q->query_id, 'query_version' => (string) $q->version, 'rows' => $rows, 'sha256' => hash_file('sha256', $path)];
        }

        $manifest = [
            'pack' => "E-Banker direct read (route 4), {$period}, " . now()->format('Y-m-d H:i:s'),
            'assembled' => now()->toDateString(),
            'period' => $period,
            'route' => PackLandingService::ROUTE_DIRECT,
            'source' => 'E-Banker read by the IFRS 9 system itself (route 4), ' . $source->describe() . ', registered statements only; dates exported as yyyy-mm-dd',
            'date_format' => 'Y-m-d',
            'files' => $files,
            'not_landed' => $notLanded,
        ];
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        AuditLoggerService::log('E-Banker Direct Read', 'ebanker_queries', null, ['new_values' => ['period' => $period, 'reads' => $reads, 'dry_run' => $dryRun],
            'meta' => ['route' => PackLandingService::ROUTE_DIRECT, 'loaded_by' => $userId]]);

        return ['pack_dir' => $dir, 'reads' => $reads, 'landing' => $this->landing->land($dir, $userId, PackLandingService::ROUTE_DIRECT, $dryRun)];
    }

    /**
     * What a read changed against what the database already held (data access request, section 8):
     * a month already received by hand lands as all unchanged; every query with a new or a versioned
     * row is named, as a query to correct or a change in E-Banker to explain.
     *
     * @return array{status:string,load_id:int,rows_read:int,new:int,versioned:int,unchanged:int,changed:array<string,string>}
     */
    public static function summary(array $result): array
    {
        $out = ['status' => (string) $result['landing']['status'], 'load_id' => (int) $result['landing']['load_id'], 'rows_read' => array_sum(array_column($result['reads'], 'rows')),
            'new' => 0, 'versioned' => 0, 'unchanged' => 0, 'changed' => []];
        foreach ($result['landing']['files'] ?? [] as $file => $f) {
            if (! is_array($f) || ! array_key_exists('new', $f)) {
                continue;
            }
            $out['new'] += $f['new']; $out['versioned'] += $f['versioned']; $out['unchanged'] += $f['unchanged'];
            $queryId = collect(array_keys($result['reads']))->first(fn ($q) => str_starts_with($file, $q . '_')) ?? $file;
            if ($f['new'] + $f['versioned'] > 0) {
                $out['changed'][$queryId] = "{$f['new']} new, {$f['versioned']} versioned";
            }
        }

        return $out;
    }

    /** @return array<string, int> query id => the highest key landed for it, across the landed loads */
    public function watermarks(): array
    {
        $out = [];
        foreach (DB::table('ebanker_loads')->where('status', 'LANDED')->whereNotNull('watermarks')->pluck('watermarks') as $json) {
            foreach (json_decode((string) $json, true) ?: [] as $queryId => $key) {
                if (is_numeric($key)) {
                    $out[$queryId] = max($out[$queryId] ?? 0, (int) $key);
                }
            }
        }

        return $out;
    }

    /** One CSV as the landing reads it: a header row of the column names, then the rows. */
    private function write(string $path, iterable $rows): int
    {
        $h = fopen($path, 'wb');
        $headers = null; $n = 0;
        try {
            foreach ($rows as $row) {
                if ($headers === null) {
                    $headers = array_keys($row);
                    fputcsv($h, $headers, ',', '"', '\\');
                }
                fputcsv($h, array_map(fn ($c) => $row[$c] ?? '', $headers), ',', '"', '\\');
                $n++;
            }
        } finally {
            fclose($h);
        }

        return $n;
    }
}
