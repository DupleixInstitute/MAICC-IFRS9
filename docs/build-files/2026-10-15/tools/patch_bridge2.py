import sys
p = r'c:\xampp\htdocs\MAICC-IFRS9\app\Services\Fli\FliBridgeService.php'
s = open(p, encoding='utf-8').read()
def rep(a, b):
    global s
    if a not in s: sys.exit('NOT FOUND ' + a[:70])
    s = s.replace(a, b, 1)
rep("""            'proxy_rows' => $this->proxies(), 'proxies'""", """            'proxy_rows' => $this->proxies() + $this->externalProxies(), 'proxies'""")
rep("""    private function add(?array $acc, object $r): array""", """    /**
     * The external credit-loss series entered on Credit Loss Data (FLI module
     * D-F7): an industry or peer measure such as the Reserve Bank's NPL ratio,
     * which gives the model a longer history than MAIIC's own books. Each
     * definition becomes the proxy EXT_<code> (EXT_<code>_P<portfolio> when the
     * row belongs to one MAIIC portfolio), knowable from the month its
     * publisher released it. The EXT_ rows are rebuilt from the register on
     * every refresh, so a row deleted there leaves the sweep too.
     */
    private function externalProxies(): int
    {
        if (! DB::getSchemaBuilder()->hasTable('macro_credit_loss_data') || ! DB::getSchemaBuilder()->hasColumn('credit_loss_series', 'known_from')) {
            return 0;
        }
        $hasPublished = DB::getSchemaBuilder()->hasColumn('macro_credit_loss_data', 'published_at');
        $rows = DB::table('macro_credit_loss_data as d')->join('macro_credit_loss_definitions as c', 'c.id', '=', 'd.definition_id')
            ->leftJoin('loan_portfolios as p', 'p.id', '=', 'd.portfolio_id')->whereNotNull('d.value')->whereNotNull('d.period')
            ->get(array_merge(['d.portfolio_id', 'd.period', 'd.value', 'd.source', 'c.code', 'c.name', 'p.name as portfolio'], $hasPublished ? ['d.published_at'] : []));
        $batch = [];
        foreach ($rows as $r) {
            $code = 'EXT_' . strtoupper(preg_replace('/[^A-Z0-9]+/i', '_', trim((string) $r->code))) . ($r->portfolio_id ? '_P' . (int) $r->portfolio_id : '');
            $ym = substr(str_replace('-', '', (string) $r->period), 0, 6);
            $published = $hasPublished && ! empty($r->published_at) ? substr(str_replace('-', '', (string) $r->published_at), 0, 6) : null;
            $batch[$code . '|' . $ym] = $this->row($code, trim($r->name . ($r->portfolio ? ", {$r->portfolio}" : '') . ' (external)'), $ym, (float) $r->value)
                + ['known_from' => $published !== null && $published > $ym ? $published : null, 'source' => mb_substr((string) $r->source, 0, 64)];
        }
        DB::transaction(function () use ($batch) {
            DB::table('credit_loss_series')->where('proxy_code', 'like', 'EXT\_%')->delete();
            foreach (array_chunk(array_values($batch), 500) as $chunk) {
                DB::table('credit_loss_series')->insert($chunk);
            }
        });

        return count($batch);
    }

    private function add(?array $acc, object $r): array""")
open(p, 'w', encoding='utf-8').write(s)
print('ok')
