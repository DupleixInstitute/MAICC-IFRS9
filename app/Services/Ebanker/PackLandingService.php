<?php

namespace App\Services\Ebanker;

use App\Services\AuditLoggerService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * The one door of the landing zone (spec v4 sections 6.3 and 6.4).
 *
 * A pack is a folder of extract files plus manifest.json. Whatever route
 * delivered it, this service: reads the manifest; runs the gates (every file
 * named exists, its SHA-256 matches, its row count matches, its query id and
 * version are ones the register knows, every date in it parses in the format
 * the manifest states, every amount parses, the key is unique, and, across
 * the files, every ledger account is in the master, the balance history ties
 * to the ledger and every in-scope account has its month-end row; see
 * PackGates); then lands every row of every file verbatim as JSON in
 * ebanker_raw_rows, keyed by the query id and the source table's own key, as a
 * new version where the content differs from the version already held and
 * never as an overwrite; and records the load with its gate results and the
 * watermark (highest source key) per query.
 *
 * A gate failure quarantines the pack: the load row is written with the
 * failures named, the raw rows are kept against the load so the refused file
 * can be opened (system audit of 9 October 2026, finding M2), readers ignore
 * them because the load is not LANDED, and nothing downstream moves.
 */
class PackLandingService
{
    public const ROUTE_MANUAL = 'ROUTE_1_MANUAL';
    public const ROUTE_FOLDER = 'ROUTE_2_FOLDER';
    public const ROUTE_API = 'ROUTE_3_API';
    public const ROUTE_DIRECT = 'ROUTE_4_DIRECT';
    public const ROUTE_VENDOR = 'ROUTE_5_VENDOR';

    private const BATCH = 500;

    /**
     * Land a pack. Returns the load id and a summary.
     *
     * @return array{load_id:int,status:string,gates:array,files:array<string,array>,watermarks:array<string,int|string|null>}
     */
    public function land(string $packDir, ?int $userId = null, string $route = self::ROUTE_MANUAL, bool $dryRun = false): array
    {
        $manifestPath = rtrim($packDir, '/\\') . DIRECTORY_SEPARATOR . 'manifest.json';
        if (! is_readable($manifestPath)) {
            throw new InvalidArgumentException("No manifest.json in {$packDir}: a pack is its files plus a manifest.");
        }
        $raw = file_get_contents($manifestPath);
        $manifest = json_decode($raw, true);
        if (! is_array($manifest) || ! isset($manifest['files']) || ! is_array($manifest['files'])) {
            throw new InvalidArgumentException('manifest.json is not a pack manifest: it needs a "files" list.');
        }
        $packHash = hash('sha256', $raw);
        $dateFormat = $this->dateFormat($manifest);

        $existing = DB::table('ebanker_loads')->where('pack_hash', $packHash)->first();
        if ($existing && $existing->status === 'LANDED') {
            return ['load_id' => (int) $existing->id, 'status' => 'ALREADY_LANDED', 'gates' => json_decode($existing->gates, true) ?? [], 'files' => [], 'watermarks' => json_decode($existing->watermarks, true) ?? []];
        }

        $known = DB::table('ebanker_queries')->get()->keyBy('query_id');
        $checks = new PackGates(new LandingZoneReader(), $this);
        $gates = ['pack' => [], 'files' => []];
        $parsed = [];
        foreach ($manifest['files'] as $entry) {
            $file = (string) ($entry['file'] ?? '');
            $path = rtrim($packDir, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
            $queryId = (string) ($entry['query_id'] ?? '');
            $f = ['file' => $file, 'query_id' => $queryId, 'failures' => [], 'gates' => []];
            if (! str_ends_with(strtolower($file), '.csv')) {
                $f['skipped'] = 'not a CSV: kept in the pack, not landed';
                $gates['files'][$file] = $f;
                continue;
            }
            if (! is_readable($path)) {
                $f['failures'][] = 'file missing';
                $gates['files'][$file] = $f;
                continue;
            }
            $sha = hash_file('sha256', $path);
            if (! empty($entry['sha256']) && strtolower($entry['sha256']) !== $sha) {
                $f['failures'][] = "SHA-256 differs from the manifest: file {$sha}, manifest {$entry['sha256']}";
            }
            $query = $known->get($queryId);
            if ($query === null) {
                $f['failures'][] = "query id '{$queryId}' is not in the register";
            }
            [$headers, $rows, $rowErrors] = $this->readCsv($path);
            if (isset($entry['rows']) && $entry['rows'] !== null && (int) $entry['rows'] !== count($rows)) {
                $f['failures'][] = "row count {$entry['rows']} in the manifest, " . count($rows) . ' in the file';
            }
            foreach ($rowErrors as $e) {
                $f['failures'][] = $e;
            }
            // every amount, balance and rate parses once commas are stripped (M2 d)
            $f['gates']['numeric'] = $checks->numeric($file, $headers, $rows);
            array_push($f['failures'], ...$f['gates']['numeric']['failures']);
            $version = isset($entry['query_version']) ? (string) $entry['query_version'] : null;
            if ($query !== null) {
                // the version the manifest states is the version the register holds (M2 e)
                $f['gates']['query_version'] = $checks->queryVersion($file, $version, $query);
                array_push($f['failures'], ...$f['gates']['query_version']['failures']);
                $version = $f['gates']['query_version']['register'];
                $dateCol = $query->date_column;
                if ($dateCol !== null && in_array($dateCol, $headers, true)) {
                    foreach ($rows as $i => $r) {
                        $v = trim((string) ($r[$dateCol] ?? ''));
                        if ($v !== '' && $this->parseDate($v, $dateFormat) === null) {
                            $f['failures'][] = "row " . ($i + 2) . ": '{$v}' in {$dateCol} is not a date in the format {$dateFormat}";
                            break;
                        }
                    }
                }
                // whether the dates are ISO is recorded; the declared format is still accepted (M2 g, D23)
                $f['gates']['dates_iso'] = $checks->datesIso($file, $headers, $rows, $dateCol);
                $missing = array_diff($this->keyColumns($query), $headers);
                if ($missing !== []) {
                    $f['failures'][] = 'key column ' . implode(',', $missing) . ' is not in the file';
                } elseif ($query->key_column !== null) {
                    // the key must be unique within the file, else two rows would
                    // fight over one version and the second would silently win
                    $seen = [];
                    foreach ($rows as $i => $r) {
                        $k = $this->sourceKey($query, $r, $i);
                        if (isset($seen[$k])) {
                            $f['failures'][] = "row " . ($i + 2) . ": key {$query->key_column} = '{$k}' already appears at row " . ($seen[$k] + 2);
                            break;
                        }
                        $seen[$k] = $i;
                    }
                }
                $parsed[$file] = ['file' => $file, 'query' => $query, 'headers' => $headers, 'rows' => $rows, 'sha256' => $sha, 'version' => $version, 'declared' => $entry['rows'] ?? null];
            }
            $f['sha256'] = $sha;
            $f['rows'] = count($rows);
            $gates['files'][$file] = $f;
        }
        // the gates that look across the files and at what earlier loads hold (M2 a, b, c)
        $gates['pack'] = $checks->acrossPack(array_values($parsed), $dateFormat);
        $gates['pack']['dates_iso'] = $this->datesIsoSummary($gates['files']);
        $gates['pack']['accepted_exceptions'] = $manifest['accepted_exceptions'] ?? [];
        $failed = array_filter($gates['files'], fn ($f) => ($f['failures'] ?? []) !== []);
        $packFailed = array_filter($gates['pack'], fn ($g) => is_array($g) && ($g['level'] ?? null) === PackGates::LEVEL_ERROR && ($g['result'] ?? null) === 'FAIL');
        $status = $failed === [] && $packFailed === [] ? 'LANDED' : 'QUARANTINED';
        $plans = $status === 'LANDED' ? array_values($parsed) : [];

        if ($dryRun) {
            return ['load_id' => 0, 'status' => 'DRY_RUN_' . $status, 'gates' => $gates, 'files' => $gates['files'], 'watermarks' => []];
        }

        return DB::transaction(function () use ($existing, $packHash, $manifest, $route, $dateFormat, $gates, $status, $plans, $parsed, $userId, $failed, $packFailed) {
            $loadId = $existing ? (int) $existing->id : (int) DB::table('ebanker_loads')->insertGetId([
                'pack_hash' => $packHash, 'pack_name' => (string) ($manifest['pack'] ?? basename(dirname($packHash))), 'route' => $route,
                'period' => $manifest['period'] ?? null, 'run_at' => isset($manifest['assembled']) ? substr((string) $manifest['assembled'], 0, 10) : null,
                'date_format' => $dateFormat, 'manifest' => json_encode($manifest), 'status' => 'PENDING', 'loaded_by' => $userId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($existing) {
                // a pack refused before and offered again: its quarantined rows go, the new attempt is recorded whole
                DB::table('ebanker_raw_rows')->where('load_id', $loadId)->delete();
                DB::table('ebanker_pack_files')->where('load_id', $loadId)->delete();
            }
            if ($status === 'QUARANTINED') {
                $kept = 0;
                foreach ($parsed as $plan) {
                    $kept += $this->quarantineFile($loadId, $plan, $dateFormat, $gates['files'][$plan['file']]['failures'] ?? []);
                }
                DB::table('ebanker_loads')->where('id', $loadId)->update(['status' => 'QUARANTINED', 'gates' => json_encode($gates), 'updated_at' => now()]);
                $named = array_map(fn ($f) => $f['failures'], $failed) + array_map(fn ($g) => $g['failures'], $packFailed);
                AuditLoggerService::log('E-Banker Pack Quarantined', 'ebanker_loads', $loadId, ['new_values' => ['failures' => $named, 'rows_kept' => $kept], 'meta' => ['route' => $route, 'loaded_by' => $userId]]);
                return ['load_id' => $loadId, 'status' => 'QUARANTINED', 'gates' => $gates, 'files' => $gates['files'], 'watermarks' => [], 'rows_kept' => $kept];
            }
            $watermarks = [];
            $fileResults = [];
            foreach ($plans as $plan) {
                $res = $this->landFile($loadId, $plan, $dateFormat);
                $fileResults[$plan['file']] = $res;
                // a watermark is the last key loaded of an incremental table (spec 6.4); the masters come whole
                if ($plan['query']->incremental && $plan['query']->key_column !== null && $res['max_key'] !== null) {
                    $watermarks[$plan['query']->query_id] = max($watermarks[$plan['query']->query_id] ?? 0, $res['max_key']);
                }
                DB::table('ebanker_pack_files')->updateOrInsert(['load_id' => $loadId, 'file' => $plan['file']], [
                    'query_id' => $plan['query']->query_id, 'query_version' => $plan['version'], 'sha256' => $plan['sha256'],
                    'rows_declared' => $plan['declared'], 'rows_loaded' => $res['loaded'], 'rows_new' => $res['new'], 'rows_versioned' => $res['versioned'],
                    'rows_unchanged' => $res['unchanged'], 'status' => 'LANDED', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('ebanker_loads')->where('id', $loadId)->update([
                'status' => 'LANDED', 'gates' => json_encode($gates), 'watermarks' => json_encode($watermarks), 'loaded_at' => now(), 'updated_at' => now(),
            ]);
            AuditLoggerService::log('E-Banker Pack Landed', 'ebanker_loads', $loadId, ['new_values' => ['files' => count($plans), 'watermarks' => $watermarks], 'meta' => ['route' => $route, 'loaded_by' => $userId]]);

            return ['load_id' => $loadId, 'status' => 'LANDED', 'gates' => $gates, 'files' => $fileResults, 'watermarks' => $watermarks];
        });
    }

    /** @return array{loaded:int,new:int,versioned:int,unchanged:int,max_key:int|string|null} */
    private function landFile(int $loadId, array $plan, string $dateFormat): array
    {
        $q = $plan['query'];
        $keyCol = $q->key_column; $accCol = $q->account_column; $dateCol = $q->date_column;
        $current = [];
        if ($keyCol !== null) {
            // only rows of landed loads are a lineage to version against; a quarantined load's rows are not
            foreach (DB::table('ebanker_raw_rows')->where('query_id', $q->query_id)->whereNull('superseded_at')
                ->whereIn('load_id', DB::table('ebanker_loads')->select('id')->where('status', 'LANDED'))
                ->get(['id', 'source_key', 'row_hash', 'version']) as $r) {
                $current[$r->source_key] = $r;
            }
        }
        $new = $versioned = $unchanged = 0; $maxKey = null; $batch = [];
        $now = now();
        foreach ($plan['rows'] as $i => $row) {
            $key = $this->sourceKey($q, $row, $i);
            $payload = json_encode($row, JSON_UNESCAPED_UNICODE);
            $hash = hash('sha256', $payload);
            $version = 1;
            if (isset($current[$key])) {
                if ($current[$key]->row_hash === $hash) {
                    $unchanged++;
                    continue;
                }
                DB::table('ebanker_raw_rows')->where('id', $current[$key]->id)->update(['superseded_at' => $now]);
                $version = (int) $current[$key]->version + 1;
                $versioned++;
            } else {
                $new++;
            }
            $date = $dateCol !== null ? $this->parseDate(trim((string) ($row[$dateCol] ?? '')), $dateFormat) : null;
            $batch[] = [
                'load_id' => $loadId, 'query_id' => $q->query_id, 'source_key' => $key,
                'account' => $accCol !== null ? (trim((string) ($row[$accCol] ?? '')) ?: null) : null,
                'row_date' => $date, 'payload' => $payload, 'row_hash' => $hash, 'version' => $version,
                'created_at' => $now, 'updated_at' => $now,
            ];
            if ($keyCol !== null && ctype_digit($key)) {
                $maxKey = max((int) $maxKey, (int) $key);
            }
            if (count($batch) >= self::BATCH) {
                DB::table('ebanker_raw_rows')->insert($batch); $batch = [];
            }
        }
        if ($batch !== []) {
            DB::table('ebanker_raw_rows')->insert($batch);
        }

        return ['loaded' => $new + $versioned, 'new' => $new, 'versioned' => $versioned, 'unchanged' => $unchanged, 'max_key' => $maxKey];
    }

    /**
     * The rows of a file in a refused pack, kept verbatim against the
     * quarantined load (spec 6.4). They are not a version of anything: no
     * current row is superseded, and every reader leaves them out because the
     * load is not LANDED. A key that repeats inside the file (one of the
     * reasons a pack is refused) is kept with its row number appended so
     * that both rows survive.
     */
    private function quarantineFile(int $loadId, array $plan, string $dateFormat, array $failures): int
    {
        $q = $plan['query'];
        $accCol = $q->account_column; $dateCol = $q->date_column;
        $now = now(); $batch = []; $seen = []; $kept = 0;
        foreach ($plan['rows'] as $i => $row) {
            $key = $this->sourceKey($q, $row, $i);
            if (isset($seen[$key])) {
                $key .= '#' . ($i + 2);
            }
            $seen[$key] = true;
            $payload = json_encode($row, JSON_UNESCAPED_UNICODE);
            $batch[] = [
                'load_id' => $loadId, 'query_id' => $q->query_id, 'source_key' => $key,
                'account' => $accCol !== null ? (trim((string) ($row[$accCol] ?? '')) ?: null) : null,
                'row_date' => $dateCol !== null ? $this->parseDate(trim((string) ($row[$dateCol] ?? '')), $dateFormat) : null,
                'payload' => $payload, 'row_hash' => hash('sha256', $payload), 'version' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ];
            $kept++;
            if (count($batch) >= self::BATCH) {
                DB::table('ebanker_raw_rows')->insert($batch); $batch = [];
            }
        }
        if ($batch !== []) {
            DB::table('ebanker_raw_rows')->insert($batch);
        }
        DB::table('ebanker_pack_files')->updateOrInsert(['load_id' => $loadId, 'file' => $plan['file']], [
            'query_id' => $q->query_id, 'query_version' => $plan['version'], 'sha256' => $plan['sha256'],
            'rows_declared' => $plan['declared'], 'rows_loaded' => $kept, 'rows_new' => 0, 'rows_versioned' => 0, 'rows_unchanged' => 0,
            'status' => 'QUARANTINED', 'note' => $failures === [] ? null : implode('; ', $failures), 'created_at' => $now, 'updated_at' => $now,
        ]);

        return $kept;
    }

    /** One row for the pack: whether every date column of every file was ISO, and the first that was not. */
    private function datesIsoSummary(array $files): array
    {
        $checked = 0; $iso = 0; $first = null;
        foreach ($files as $f) {
            $g = $f['gates']['dates_iso'] ?? null;
            if ($g === null || $g['iso'] === null) {
                continue;
            }
            $checked++;
            if ($g['iso']) {
                $iso++;
            } else {
                $first ??= $g['first_non_iso'];
            }
        }

        return ['level' => PackGates::LEVEL_WARNING, 'result' => $checked === 0 ? 'SKIPPED' : ($iso === $checked ? 'PASS' : 'WARN'), 'checked' => $checked, 'iso' => $iso,
            'detail' => $checked === 0 ? 'no dated file in the pack' : "{$iso} of {$checked} dated files are ISO throughout" . ($iso === $checked ? '' : '; the format the manifest declares is accepted (D23)'),
            'failures' => $first === null ? [] : [$first]];
    }

    /**
     * The register names a key as one column (CUMVOUCH_DET_ID) or, where an
     * extract returns one row per pair, as several joined by commas
     * (CUST_SEC_DET_ID,NEW_AC_NUMBER for the security details).
     *
     * @return list<string>
     */
    private function keyColumns(object $query): array
    {
        return $query->key_column === null ? [] : array_values(array_filter(array_map('trim', explode(',', $query->key_column))));
    }

    /** The row's source key: the key column(s) joined by '|', or the row number when the query has no key. */
    private function sourceKey(object $query, array $row, int $index): string
    {
        $cols = $this->keyColumns($query);
        if ($cols === []) {
            return (string) ($index + 1);
        }
        $key = implode('|', array_map(fn ($c) => trim((string) ($row[$c] ?? '')), $cols));

        return trim($key, '|') === '' ? 'row-' . ($index + 1) : $key;
    }

    /** @return array{0:list<string>,1:list<array<string,string>>,2:list<string>} headers, rows, errors */
    public function readCsv(string $path): array
    {
        $text = file_get_contents($path);
        if ($text === false) {
            throw new RuntimeException("Cannot read {$path}");
        }
        // The export tool writes UTF-8 with a BOM for most files and Windows-1252 for a few; both are read.
        if (str_starts_with($text, "\xEF\xBB\xBF")) {
            $text = substr($text, 3);
        } elseif (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }
        $lines = preg_split("/\r\n|\n|\r/", $text);
        $headers = null; $rows = []; $errors = [];
        foreach ($lines as $n => $line) {
            if (trim($line) === '') continue;
            $cells = str_getcsv($line);
            if ($headers === null) {
                $headers = array_map(fn ($h) => trim((string) $h), $cells);
                // SQL Developer writes an unnamed first column holding the row number; drop it.
                if ($headers[0] === '') { array_shift($headers); $this->dropFirst = true; } else { $this->dropFirst = false; }
                continue;
            }
            if ($this->dropFirst) array_shift($cells);
            if (count($cells) !== count($headers)) {
                $errors[] = 'row ' . ($n + 1) . ': ' . count($cells) . ' cells against ' . count($headers) . ' headers';
                if (count($errors) >= 5) break;
                continue;
            }
            $rows[] = array_combine($headers, array_map(fn ($c) => (string) $c, $cells));
        }
        if ($headers === null) {
            $errors[] = 'the file has no header row';
        }

        return [$headers ?? [], $rows, $errors];
    }

    private bool $dropFirst = false;

    private function dateFormat(array $manifest): string
    {
        $src = strtolower((string) ($manifest['source'] ?? ''));
        if (isset($manifest['date_format'])) {
            return (string) $manifest['date_format'];
        }
        if (str_contains($src, 'm/d/yyyy')) {
            return 'm/d/Y';
        }

        return 'Y-m-d';
    }

    public function parseDate(string $value, string $format): ?string
    {
        if ($value === '') {
            return null;
        }
        // The export tool writes m/d/yyyy without zero-padding (1/31/2026), so the
        // strict round-trip is checked on both the padded and the unpadded form.
        // A timestamp column (LOS_REQUEST_DETAILS.TRANSACTION_DATE) is exported
        // with its time, 9/3/2025 10:57:57 AM; the date part is what is kept.
        $unpadded = strtr($format, ['m' => 'n', 'd' => 'j']);
        $candidates = [
            $format, $unpadded,
            $format . ' H:i:s', $unpadded . ' H:i:s',
            $format . ' g:i:s A', $unpadded . ' g:i:s A',
            $format . ' h:i:s A', $unpadded . ' h:i:s A',
            'Y-m-d H:i:s', 'Y-m-d',
        ];
        foreach (array_unique($candidates) as $fmt) {
            try {
                $d = CarbonImmutable::createFromFormat('!' . $fmt, $value);
            } catch (\Throwable) {
                continue;
            }
            if ($d !== false && $d->format($fmt) === $value) {
                return $d->toDateString();
            }
        }

        return null;
    }
}
