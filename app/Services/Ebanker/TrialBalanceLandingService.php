<?php

namespace App\Services\Ebanker;

use App\Models\GlTrialBalanceLine;
use App\Services\AuditLoggerService;
use App\Services\Eir\TrialBalanceImportService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * The trial balances through the one door (spec v4 section 6.3, the
 * ebanker_trial_balances row of the table; system audit of 9 October 2026,
 * finding M1).
 *
 * Until the audit the monthly trial balance files from Finance went straight
 * into gl_trial_balance_lines through TrialBalanceImportService: no hash, no
 * load row, nothing an auditor could open to see the file a figure came
 * from. Now each file is a load of its own in ebanker_loads, with the file's
 * SHA-256 as the pack hash and the gate results against it; every GL line
 * of the file is one raw row under query TB_01, keyed by period, basis and
 * GL code, versioned when a re-delivered file changes a line and never
 * overwritten; and gl_trial_balance_lines, the GL side of the
 * reconciliation, is derived from the current landed rows by derive(). The
 * shape TrialBalanceImportService wrote is kept exactly, so the December
 * 2025 ties of the baselines stand.
 *
 * The gates are the importer's own rules, run here before anything is
 * written: rows found, debits equal credits, the file's Grand Total ties to
 * the rows, the period is known, and every amount is a number. A file that
 * fails is quarantined with its rows kept against the load and the row
 * named; derive() never reads a quarantined load.
 */
class TrialBalanceLandingService
{
    public const ROUTE = 'TRIAL_BALANCE_FILE';
    public const QUERY_ID = 'TB_01';

    public function __construct(private readonly TrialBalanceImportService $importer = new TrialBalanceImportService(), private readonly LandingZoneReader $zone = new LandingZoneReader())
    {
    }

    /**
     * Land every monthly file in a folder (post-closing), then the AFS
     * bridge sheet (pre-closing) when one is given, then derive the GL lines.
     *
     * @return array{files:list<array>,derived:array,failures:list<string>}
     */
    public function landDirectory(string $directory, ?int $userId = null, ?string $afsPath = null, string $afsSheet = 'Final E-Banker TB Dec 2025', string $afsPeriod = '2025-12-01'): array
    {
        $files = glob(rtrim($directory, '/\\') . '/*.xls') ?: [];
        sort($files);
        $results = [];
        $failures = [];
        foreach ($files as $file) {
            $r = $this->landFile($file, GlTrialBalanceLine::BASIS_POSTCLOSING, null, null, $userId);
            $results[] = $r;
            if ($r['status'] === 'QUARANTINED') {
                $failures[] = basename($file) . ' - ' . implode('; ', $r['failures']);
            }
        }
        if ($afsPath !== null && $afsPath !== '') {
            $r = $this->landFile($afsPath, GlTrialBalanceLine::BASIS_PRECLOSING, $afsPeriod, $afsSheet, $userId);
            $results[] = $r;
            if ($r['status'] === 'QUARANTINED') {
                $failures[] = basename($afsPath) . ' - ' . implode('; ', $r['failures']);
            }
        }

        return ['files' => $results, 'derived' => $this->derive(), 'failures' => $failures];
    }

    /**
     * Land one trial balance file as a load of its own.
     *
     * @return array{load_id:int,status:string,file:string,period:?string,basis:string,lines:int,debit:float,credit:float,grand_total:?float,source_period_stamp:?string,gates:array,failures:list<string>,new:int,versioned:int,unchanged:int,retired:int}
     */
    public function landFile(string $path, string $basis = GlTrialBalanceLine::BASIS_POSTCLOSING, ?string $period = null, ?string $sheetName = null, ?int $userId = null): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Trial balance file not found: {$path}");
        }
        $sha = hash_file('sha256', $path);
        $name = basename($path);
        $existing = DB::table('ebanker_loads')->where('pack_hash', $sha)->first();
        if ($existing && $existing->status === 'LANDED') {
            $gates = json_decode($existing->gates, true) ?? [];
            $m = json_decode($existing->manifest, true) ?? [];

            return ['load_id' => (int) $existing->id, 'status' => 'ALREADY_LANDED', 'file' => $name, 'period' => $m['period'] ?? null, 'basis' => $m['basis'] ?? $basis,
                'lines' => (int) ($m['rows'] ?? 0), 'debit' => (float) ($m['debit'] ?? 0), 'credit' => (float) ($m['credit'] ?? 0), 'grand_total' => $m['grand_total'] ?? null,
                'source_period_stamp' => $m['source_period_stamp'] ?? null, 'gates' => $gates, 'failures' => [], 'new' => 0, 'versioned' => 0, 'unchanged' => (int) ($m['rows'] ?? 0), 'retired' => 0];
        }

        // ----- read, then the gates ------------------------------------------
        $gates = [];
        $failures = [];
        try {
            $read = $this->importer->read($path, $period, $sheetName);
        } catch (Throwable $e) {
            $read = ['period' => null, 'source_period_stamp' => null, 'sheet' => $sheetName ?? '', 'rows' => [], 'debit' => 0.0, 'credit' => 0.0, 'grand_total' => null];
            $failures[] = 'the file could not be read: ' . $e->getMessage();
        }
        $gates['period_known'] = $this->gate($read['period'] !== null, $read['period'] === null ? ["{$name}: no period stamp in the file and none supplied"] : [], $read['period'] ?? 'none');
        $gates['rows_found'] = $this->gate($read['rows'] !== [], $read['rows'] === [] ? ["{$name}: no trial balance rows found; expected 'code...title' in column B"] : [], count($read['rows']) . ' GL lines');
        $numeric = [];
        foreach ($read['rows'] as $r) {
            foreach (['debit_text' => 'Debit', 'credit_text' => 'Credit'] as $col => $label) {
                $v = (string) ($r[$col] ?? '');
                if ($v !== '' && $v !== '-' && ! PackGates::isNumeric(str_replace(['(', ')'], '', $v))) {
                    $numeric[] = "{$name} row {$r['row']}: '{$v}' in {$label} is not a number";
                }
            }
            if (count($numeric) >= 10) {
                break;
            }
        }
        $gates['numeric'] = $this->gate($numeric === [], $numeric, 'every Debit and Credit cell is a number');
        $ties = [];
        if ($read['rows'] !== []) {
            try {
                $this->importer->assertTies($path, $read['rows'], $read['debit'], $read['credit'], $read['grand_total']);
            } catch (Throwable $e) {
                $ties[] = $e->getMessage();
            }
        }
        $gates['debits_equal_credits_and_grand_total_ties'] = $this->gate($ties === [], $ties,
            'debits ' . number_format($read['debit'], 2) . ', credits ' . number_format($read['credit'], 2) . ', grand total ' . ($read['grand_total'] === null ? 'not printed' : number_format($read['grand_total'], 2)));
        foreach ($gates as $g) {
            array_push($failures, ...$g['failures']);
        }
        $failures = array_values(array_unique($failures));
        $status = $failures === [] ? 'LANDED' : 'QUARANTINED';
        $manifest = ['file' => $name, 'sha256' => $sha, 'basis' => $basis, 'period' => $read['period'], 'sheet' => $read['sheet'], 'source_period_stamp' => $read['source_period_stamp'],
            'rows' => count($read['rows']), 'debit' => $read['debit'], 'credit' => $read['credit'], 'grand_total' => $read['grand_total']];

        return DB::transaction(function () use ($existing, $sha, $name, $basis, $read, $gates, $failures, $status, $manifest, $userId) {
            $loadId = $existing ? (int) $existing->id : (int) DB::table('ebanker_loads')->insertGetId([
                'pack_hash' => $sha, 'pack_name' => 'Trial balance ' . $name, 'route' => self::ROUTE, 'period' => $read['period'] === null ? null : substr($read['period'], 0, 7),
                'date_format' => 'excel', 'manifest' => json_encode($manifest), 'status' => 'PENDING', 'loaded_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($existing) {
                DB::table('ebanker_raw_rows')->where('load_id', $loadId)->delete();
                DB::table('ebanker_pack_files')->where('load_id', $loadId)->delete();
            }
            $now = now();
            $new = $versioned = $unchanged = $retired = 0;
            $current = [];
            $periodKey = $read['period'] ?? 'none';
            if ($status === 'LANDED') {
                foreach ($this->zone->current()->where('query_id', self::QUERY_ID)->where('source_key', 'like', $periodKey . '|' . $basis . '|%')->get(['id', 'source_key', 'row_hash', 'version']) as $r) {
                    $current[$r->source_key] = $r;
                }
            }
            $batch = []; $seen = [];
            foreach ($read['rows'] as $r) {
                $payload = ['ROW' => $r['row'], 'GL_CODE' => $r['gl_code'], 'GL_TITLE' => $r['gl_title'], 'DEBIT' => $r['debit'], 'CREDIT' => $r['credit'],
                    'PERIOD' => $read['period'], 'BASIS' => $basis, 'SOURCE_FILE' => $name, 'SOURCE_SHEET' => $read['sheet'], 'SOURCE_PERIOD_STAMP' => $read['source_period_stamp']];
                $key = $periodKey . '|' . $basis . '|' . $r['gl_code'];
                if (isset($seen[$key])) {
                    $key .= '#' . $r['row'];   // a GL code printed twice is kept twice; the derivation takes the last
                }
                $seen[$key] = true;
                $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
                $hash = hash('sha256', $json);
                $version = 1;
                if (isset($current[$key])) {
                    if ($current[$key]->row_hash === $hash) {
                        $unchanged++;
                        unset($current[$key]);
                        continue;
                    }
                    DB::table('ebanker_raw_rows')->where('id', $current[$key]->id)->update(['superseded_at' => $now]);
                    $version = (int) $current[$key]->version + 1;
                    $versioned++;
                    unset($current[$key]);
                } else {
                    $new++;
                }
                $batch[] = ['load_id' => $loadId, 'query_id' => self::QUERY_ID, 'source_key' => $key, 'account' => null, 'row_date' => $read['period'],
                    'payload' => $json, 'row_hash' => $hash, 'version' => $version, 'created_at' => $now, 'updated_at' => $now];
                if (count($batch) >= 500) {
                    DB::table('ebanker_raw_rows')->insert($batch); $batch = [];
                }
            }
            if ($batch !== []) {
                DB::table('ebanker_raw_rows')->insert($batch);
            }
            // a line the re-delivered file no longer prints is retired, never deleted
            foreach ($current as $gone) {
                DB::table('ebanker_raw_rows')->where('id', $gone->id)->update(['superseded_at' => $now]);
                $retired++;
            }
            DB::table('ebanker_pack_files')->insert(['load_id' => $loadId, 'file' => $name, 'query_id' => self::QUERY_ID, 'query_version' => '1', 'sha256' => $sha,
                'rows_declared' => null, 'rows_loaded' => count($read['rows']), 'rows_new' => $new, 'rows_versioned' => $versioned, 'rows_unchanged' => $unchanged,
                'status' => $status, 'note' => $failures === [] ? null : implode('; ', $failures), 'created_at' => $now, 'updated_at' => $now]);
            DB::table('ebanker_loads')->where('id', $loadId)->update(['status' => $status, 'gates' => json_encode($gates), 'loaded_at' => $status === 'LANDED' ? $now : null, 'updated_at' => $now]);
            AuditLoggerService::log($status === 'LANDED' ? 'Trial Balance Landed' : 'Trial Balance Quarantined', 'ebanker_loads', $loadId,
                ['new_values' => ['file' => $name, 'sha256' => $sha, 'period' => $read['period'], 'basis' => $basis, 'rows' => count($read['rows']), 'failures' => $failures], 'meta' => ['route' => self::ROUTE, 'loaded_by' => $userId]]);

            return ['load_id' => $loadId, 'status' => $status, 'file' => $name, 'period' => $read['period'], 'basis' => $basis, 'lines' => count($read['rows']),
                'debit' => $read['debit'], 'credit' => $read['credit'], 'grand_total' => $read['grand_total'], 'source_period_stamp' => $read['source_period_stamp'],
                'gates' => $gates, 'failures' => $failures, 'new' => $new, 'versioned' => $versioned, 'unchanged' => $unchanged, 'retired' => $retired];
        });
    }

    /**
     * gl_trial_balance_lines from the current landed TB_01 rows: one line per
     * period, basis and GL code, the attributes TrialBalanceImportService
     * wrote. A line the zone no longer holds is removed, so the GL side is
     * always exactly the landed files. Idempotent.
     *
     * @return array{lines:int,imported:int,updated:int,removed:int,periods:list<string>}
     */
    public function derive(): array
    {
        $rows = $this->zone->trialBalanceLines();
        if ($rows === []) {
            // nothing landed yet: the GL side is left as it is rather than emptied
            return ['lines' => 0, 'imported' => 0, 'updated' => 0, 'removed' => 0, 'periods' => []];
        }
        $imported = $updated = 0;
        $keep = [];
        $periods = [];
        // the file's own order: by period, basis, then the row in the sheet, so ids follow the print
        uasort($rows, fn ($a, $b) => [$a['payload']['PERIOD'] ?? '', $a['payload']['BASIS'] ?? '', (int) ($a['payload']['ROW'] ?? 0)] <=> [$b['payload']['PERIOD'] ?? '', $b['payload']['BASIS'] ?? '', (int) ($b['payload']['ROW'] ?? 0)]);
        foreach ($rows as $row) {
            $p = $row['payload'];
            if (empty($p['PERIOD']) || empty($p['GL_CODE'])) {
                continue;
            }
            $attributes = [
                'period' => $p['PERIOD'], 'source_period_stamp' => $p['SOURCE_PERIOD_STAMP'] ?? null,
                'gl_code' => (string) $p['GL_CODE'], 'gl_title' => (string) ($p['GL_TITLE'] ?? ''),
                'debit' => (float) ($p['DEBIT'] ?? 0), 'credit' => (float) ($p['CREDIT'] ?? 0),
                'basis' => (string) ($p['BASIS'] ?? GlTrialBalanceLine::BASIS_POSTCLOSING),
                'source_file' => (string) ($p['SOURCE_FILE'] ?? ''), 'source_sheet' => $p['SOURCE_SHEET'] ?? null,
            ];
            $existing = GlTrialBalanceLine::whereDate('period', $attributes['period'])->where('gl_code', $attributes['gl_code'])->where('basis', $attributes['basis'])->first();
            if ($existing) {
                $existing->update($attributes);
                $updated++;
                $keep[] = $existing->id;
            } else {
                $keep[] = GlTrialBalanceLine::create($attributes)->id;
                $imported++;
            }
            $periods[substr((string) $p['PERIOD'], 0, 7)] = true;
        }
        $removed = GlTrialBalanceLine::whereNotIn('id', array_unique($keep))->delete();

        return ['lines' => count($keep), 'imported' => $imported, 'updated' => $updated, 'removed' => (int) $removed, 'periods' => array_keys($periods)];
    }

    /** @return array{result:string,detail:string,failures:list<string>} */
    private function gate(bool $pass, array $failures, string $detail): array
    {
        return ['level' => PackGates::LEVEL_ERROR, 'result' => $pass ? 'PASS' : 'FAIL', 'detail' => $detail, 'failures' => $failures];
    }
}
