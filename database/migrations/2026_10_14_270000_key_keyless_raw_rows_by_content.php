<?php

use App\Services\Ebanker\PackLandingService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| The rows of a query with no key column, keyed by their content (14 October 2026)
|--------------------------------------------------------------------------
| PackLandingService gave a row of a keyless query (CV_07, GL_02, P3_12,
| DD_09, DD_10 and the other probes and masters the register lists without a
| key) its row number as its source key, so a re-sent file landed every row
| again as new current rows (CV_07 re-sent: 47 duplicates) and family() kept,
| row number by row number, whichever load came later. Such rows are now keyed
| by content (PackLandingService::contentKey: the row's SHA-256 and the
| occurrence of that content among the query's rows in the load).
|
| This migration re-keys the rows already landed by a pack route the same way,
| load by load in landing order, and supersedes (never deletes) a current row
| whose content an earlier landed load already holds as current: the earlier
| row stays current, as a re-landing of the same content now leaves it. A
| query landed once (every keyless query of a fresh bootstrap) keeps exactly
| the rows it had; only the duplicates leave the current set. Idempotent.
*/
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ebanker_raw_rows') || ! Schema::hasTable('ebanker_queries') || ! Schema::hasTable('ebanker_loads')) {
            return;
        }
        $now = now();
        $superseded = 0;
        foreach (DB::table('ebanker_queries')->whereNull('key_column')->pluck('query_id') as $queryId) {
            $loads = DB::table('ebanker_loads')->where('route', 'like', 'ROUTE%')
                ->whereIn('id', DB::table('ebanker_raw_rows')->select('load_id')->where('query_id', $queryId))
                ->orderBy('id')->get(['id', 'status']);
            $held = [];
            foreach ($loads as $load) {
                $occurrence = [];
                $rekey = [];
                $dupes = [];
                foreach (DB::table('ebanker_raw_rows')->where('load_id', $load->id)->where('query_id', $queryId)->orderBy('id')->get(['id', 'row_hash', 'source_key', 'superseded_at']) as $r) {
                    $n = $occurrence[$r->row_hash] = ($occurrence[$r->row_hash] ?? 0) + 1;
                    $key = PackLandingService::contentKey((string) $r->row_hash, $n);
                    if ($r->source_key !== $key) {
                        $rekey[$r->id] = $key;
                    }
                    if ($load->status !== 'LANDED' || $r->superseded_at !== null) {
                        continue;
                    }
                    if (isset($held[$key])) {
                        $dupes[] = $r->id; // the same content is already current from an earlier load
                    } else {
                        $held[$key] = true;
                    }
                }
                foreach (array_chunk($rekey, 500, true) as $chunk) {
                    $case = implode(' ', array_map(fn ($id) => 'WHEN ' . (int) $id . ' THEN ?', array_keys($chunk)));
                    DB::update('UPDATE ebanker_raw_rows SET source_key = CASE id ' . $case . ' END WHERE id IN (' . implode(',', array_map('intval', array_keys($chunk))) . ')', array_values($chunk));
                }
                foreach (array_chunk($dupes, 500) as $ids) {
                    $superseded += DB::table('ebanker_raw_rows')->whereIn('id', $ids)->update(['superseded_at' => $now]);
                }
            }
        }
        if ($superseded > 0 && Schema::hasTable('audit_logs')) {
            DB::table('audit_logs')->insert(['user_id' => null, 'action' => 'Keyless Raw Rows Re-keyed', 'entity_type' => 'ebanker_raw_rows', 'entity_id' => null, 'rows_affected' => $superseded,
                'new_values' => json_encode(['superseded_duplicates' => $superseded, 'reason' => 'a keyless query re-sent with the same rows; keyed by content from 14 October 2026']), 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        // the row-number keys are not restored: they were the defect
    }
};
