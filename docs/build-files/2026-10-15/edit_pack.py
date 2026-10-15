def edit(p, pairs):
    s = open(p, encoding='utf-8', newline='').read()
    nl = '\r\n' if '\r\n' in s else '\n'
    s = s.replace('\r\n', '\n')
    for old, new in pairs:
        assert old in s, (p, old[:80])
        s = s.replace(old, new, 1)
    open(p, 'w', encoding='utf-8', newline='').write(s.replace('\n', nl))


edit(r'C:\xampp\htdocs\MAICC-IFRS9\app\Services\Ebanker\PackLandingService.php', [
("""    /** @return array{loaded:int,new:int,versioned:int,unchanged:int,max_key:int|string|null} */
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
            $version = 1;""", """    /**
     * Land one file's rows: a row whose key is held with the same content is unchanged, with other
     * content it is a new version of the held row (superseding it), and an unheld key is new.
     *
     * A query with no key column (CV_07, GL_02, P3_12, DD_09, DD_10 and the other probes and masters
     * the register lists without one) is keyed by the row's content (contentKey: its SHA-256 and the
     * occurrence of that content in the file), never by its row number: until 14 October 2026 a
     * re-sent CV_07 landed its 47 rows again as 47 new current rows, and family() kept, row number by
     * row number, whichever load came later. Identical rows are now recognised as held. Such a query
     * comes whole (none is incremental), so its file is the query's current extract: a held row of it
     * whose content is not in the new file is superseded ('withdrawn'), which is how a changed row of
     * a keyless query is versioned.
     *
     * @return array{loaded:int,new:int,versioned:int,unchanged:int,withdrawn:int,max_key:int|string|null}
     */
    private function landFile(int $loadId, array $plan, string $dateFormat): array
    {
        $q = $plan['query'];
        $keyCol = $q->key_column; $accCol = $q->account_column; $dateCol = $q->date_column;
        $keyless = $keyCol === null;
        $current = [];
        // only rows of landed loads are a lineage to version against; a quarantined load's rows are not
        foreach (DB::table('ebanker_raw_rows')->where('query_id', $q->query_id)->whereNull('superseded_at')
            ->whereIn('load_id', DB::table('ebanker_loads')->select('id')->where('status', 'LANDED'))
            ->get(['id', 'source_key', 'row_hash', 'version']) as $r) {
            $current[$r->source_key] = $r;
        }
        $new = $versioned = $unchanged = 0; $maxKey = null; $batch = [];
        $occurrence = []; $inFile = [];
        $now = now();
        foreach ($plan['rows'] as $i => $row) {
            $payload = json_encode($row, JSON_UNESCAPED_UNICODE);
            $hash = hash('sha256', $payload);
            if ($keyless) {
                $occurrence[$hash] = ($occurrence[$hash] ?? 0) + 1;
                $key = self::contentKey($hash, $occurrence[$hash]);
                $inFile[$key] = true;
            } else {
                $key = $this->sourceKey($q, $row, $i);
            }
            $version = 1;"""),
("""        if ($batch !== []) {
            DB::table('ebanker_raw_rows')->insert($batch);
        }

        return ['loaded' => $new + $versioned, 'new' => $new, 'versioned' => $versioned, 'unchanged' => $unchanged, 'max_key' => $maxKey];
    }""", """        if ($batch !== []) {
            DB::table('ebanker_raw_rows')->insert($batch);
        }
        // a keyless query comes whole: what it held and this file no longer carries is withdrawn (superseded, never deleted)
        $withdrawn = 0;
        if ($keyless) {
            $gone = array_values(array_map(fn ($r) => $r->id, array_diff_key($current, $inFile)));
            foreach (array_chunk($gone, self::BATCH) as $ids) {
                $withdrawn += DB::table('ebanker_raw_rows')->whereIn('id', $ids)->update(['superseded_at' => $now]);
            }
        }

        return ['loaded' => $new + $versioned, 'new' => $new, 'versioned' => $versioned, 'unchanged' => $unchanged, 'withdrawn' => $withdrawn, 'max_key' => $maxKey];
    }

    /**
     * The source key of a row of a query with no key column: its content (the SHA-256 of the row as
     * landed, cut to fit source_key) and the occurrence of that content in the file, so two identical
     * rows of one file both land and a re-sent file is recognised row for row. Public for the
     * migration that re-keyed the rows landed before 14 October 2026 and for the tests.
     */
    public static function contentKey(string $rowHash, int $occurrence): string
    {
        return 'c:' . substr($rowHash, 0, 50) . '#' . $occurrence;
    }"""),
("""                    'rows_declared' => $plan['declared'], 'rows_loaded' => $res['loaded'], 'rows_new' => $res['new'], 'rows_versioned' => $res['versioned'],
                    'rows_unchanged' => $res['unchanged'], 'status' => 'LANDED', 'created_at' => now(), 'updated_at' => now(),""",
"""                    'rows_declared' => $plan['declared'], 'rows_loaded' => $res['loaded'], 'rows_new' => $res['new'], 'rows_versioned' => $res['versioned'],
                    'rows_unchanged' => $res['unchanged'], 'status' => 'LANDED',
                    'note' => $res['withdrawn'] > 0 ? $res['withdrawn'] . ' row(s) of the earlier extract of this query are not in this file and were superseded (a query with no key comes whole)' : null,
                    'created_at' => now(), 'updated_at' => now(),"""),
("""        $now = now(); $batch = []; $seen = []; $kept = 0;
        foreach ($plan['rows'] as $i => $row) {
            $key = $this->sourceKey($q, $row, $i);
            if (isset($seen[$key])) {
                $key .= '#' . ($i + 2);
            }
            $seen[$key] = true;
            $payload = json_encode($row, JSON_UNESCAPED_UNICODE);""", """        $now = now(); $batch = []; $seen = []; $kept = 0; $occurrence = [];
        foreach ($plan['rows'] as $i => $row) {
            $payload = json_encode($row, JSON_UNESCAPED_UNICODE);
            if ($q->key_column === null) {
                // keyed by content, as a landed file is (landFile)
                $h = hash('sha256', $payload);
                $occurrence[$h] = ($occurrence[$h] ?? 0) + 1;
                $key = self::contentKey($h, $occurrence[$h]);
            } else {
                $key = $this->sourceKey($q, $row, $i);
            }
            if (isset($seen[$key])) {
                $key .= '#' . ($i + 2);
            }
            $seen[$key] = true;"""),
("""    /** The row's source key: the key column(s) joined by '|', or the row number when the query has no key. */""",
"""    /** The row's source key: the key column(s) joined by '|' (a query with no key column is keyed by content, contentKey). */"""),
])
print('ok')
