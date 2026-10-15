import sys
p = r'c:\xampp\htdocs\MAICC-IFRS9\app\Services\Fli\FliBridgeService.php'
s = open(p, encoding='utf-8').read()
def rep(a, b):
    global s
    if a not in s: sys.exit('NOT FOUND ' + a[:70])
    s = s.replace(a, b, 1)
rep("""        $series = DB::table('macro_statistics')->get(['id', 'statistic_code', 'statistic_name', 'frequency']);
        $n = 0;""", """        $series = DB::table('macro_statistics')->get(['id', 'statistic_code', 'statistic_name', 'frequency']);
        $hasPublished = DB::getSchemaBuilder()->hasColumn('macro_statistics_data', 'published_at');
        $n = 0;""")
rep("""            $rows = DB::table('macro_statistics_data')->where('macro_stat_definition_id', $s->id)->orderBy('period')->get(['period', 'value', 'is_forecast', 'source']);""",
"""            $rows = DB::table('macro_statistics_data')->where('macro_stat_definition_id', $s->id)->orderBy('period')->get(array_merge(['period', 'value', 'is_forecast', 'source'], $hasPublished ? ['published_at'] : []));""")
rep("""                $points[substr(str_replace('-', '', $r->period), 0, 6)] = ['value' => (float) $r->value, 'type' => (int) $r->is_forecast ? 'forecast' : 'actual', 'source' => $r->source];""",
"""                // FLI module D-F10: a forecast is knowable from the date its publisher released it,
                // when that is recorded; otherwise from the date the bridge first held it
                $points[substr(str_replace('-', '', $r->period), 0, 6)] = ['value' => (float) $r->value, 'type' => (int) $r->is_forecast ? 'forecast' : 'actual', 'source' => $r->source,
                    'vintage' => ! empty($r->published_at) ? 'published ' . substr((string) $r->published_at, 0, 10) : null];""")
rep("""                            $out[$p] = ['value' => $a + ($b - $a) * $m / $months, 'type' => $points[$keys[$i + 1]]['type'], 'source' => 'interpolated from the annual observations', 'note' => 'interpolated'];""",
"""                            $out[$p] = ['value' => $a + ($b - $a) * $m / $months, 'type' => $points[$keys[$i + 1]]['type'], 'source' => 'interpolated from the annual observations', 'note' => 'interpolated', 'vintage' => $points[$keys[$i + 1]]['vintage'] ?? null];""")
rep("""                    'source' => substr((string) $p['source'], 0, 64), 'value_type' => $p['type'], 'vintage' => 'bridge ' . now()->toDateString(), 'imported_at' => now(), 'created_at' => now(), 'updated_at' => now()];""",
"""                    'source' => substr((string) $p['source'], 0, 64), 'value_type' => $p['type'], 'vintage' => $p['vintage'] ?? $this->firstHeld($s->statistic_code, $period), 'imported_at' => now(), 'created_at' => now(), 'updated_at' => now()];""")
rep("""    private function proxies(): int""", """    /**
     * The vintage of an observation with no release date: the date the bridge
     * first held it, kept across refreshes so that re-running the bridge never
     * makes an old forecast look newer (or a new one look older) than it is.
     */
    private function firstHeld(string $code, string $period): string
    {
        $this->held ??= DB::table('macro_series')->where('vintage', 'like', 'bridge %')->get(['statistic_code', 'observation_period', 'vintage'])
            ->mapWithKeys(fn ($r) => [$r->statistic_code . '|' . $r->observation_period => $r->vintage])->all();

        return $this->held[$code . '|' . $period] ?? 'bridge ' . now()->toDateString();
    }

    private ?array $held = null;

    private function proxies(): int""")
open(p, 'w', encoding='utf-8').write(s)
print('ok')
