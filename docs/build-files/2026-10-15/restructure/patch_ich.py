p = 'C:/xampp/htdocs/MAICC-IFRS9/app/Services/Ebanker/InstalmentChartHistory.php'
s = open(p, encoding='utf-8').read()


def rep(a, b):
    global s
    assert s.count(a) == 1, a[:70]
    s = s.replace(a, b)


rep(""" * paid ahead; Msusa Investments and Beaulaland Food Processors carry serials
 * in pairs on one date. (BenCal Technologies 000104460000059 has the same
 * defect, serials 25 to 61 on 1 February 2025, but only in the dated copies
 * RS_02b to RS_02e, which are not read here: it is not corrected.) Where two or more instalments of""",
""" * paid ahead; Msusa Investments and Beaulaland Food Processors carry serials
 * in pairs on one date. (BenCal Technologies 000104460000059 has the same
 * defect, serials 25 to 61 on 1 February 2025, in the dated copies of the
 * chart: see below.) Where two or more instalments of""")

rep(""" * Receipts are the cash the ledger credits""",
""" * The dated copies of the chart (14 October 2026, with the restructure
 * register). Some loans have no row in either extract: their chart is only in
 * the copies E-Banker kept, RS_02b of 30 August 2024, RS_02d of 28 September
 * 2024 and RS_02c of 14 November 2024 (RS_02e, FIXLOAN_INST_CHART1, has no
 * date and is never read for a date). For such a loan the chart of a copy is
 * in force from its rows' creation (the first copy) or from its own date, to
 * the date of the copy; where the next copy holds the same instalments it
 * runs on, and where it differs the days between the two copies have no chart
 * (no record dates the change). After the last copy the chart stays in force
 * only while every instalment plan it is built on is still E-Banker's live
 * plan (P3_11, DELETE_FLAG N, extracted on 6 October 2026); otherwise it ends
 * on the copy's date. BenCal Technologies: RS_02b holds plans 313 and 75 and
 * RS_02d and RS_02c hold the same instalments on plans 1326 to 1328, which
 * P3_11 holds live, so the chart is in force from its creation on 28 March
 * 2024, and its collapsed serials are dated by their monthly plan.
 *
 * Receipts are the cash the ledger credits""")

rep("""class InstalmentChartHistory
{
""", """class InstalmentChartHistory
{
    /** The dated copies of the chart, by the date in the E-Banker table's name (FIX_INST_CHART28SEP: 2024, its rows created up to 7 August 2024). */
    public const COPIES = ['RS_02b' => '2024-08-30', 'RS_02d' => '2024-09-28', 'RS_02c' => '2024-11-14'];

""")

rep("""     * @return array{instalments:list<array{0:string,1:float}>,rows:int,collapsed:bool,unreadable:bool,first_due:?string}|null
     */""", """     * @return array{instalments:list<array{0:string,1:float}>,rows:int,collapsed:bool,unreadable:bool,first_due:?string,source:?string}|null
     */""")

rep("""        return ['instalments' => $instalments, 'rows' => count($rows), 'collapsed' => $collapsed, 'unreadable' => $unreadable, 'first_due' => $instalments[0][0] ?? null];""",
"""        $copy = array_values(array_filter(array_unique(array_map(fn ($r) => $r['copy'] ?? null, $rows))));

        return ['instalments' => $instalments, 'rows' => count($rows), 'collapsed' => $collapsed, 'unreadable' => $unreadable, 'first_due' => $instalments[0][0] ?? null,
            'source' => $copy === [] ? null : 'dated copy ' . implode(', ', $copy)];""")

rep("""        return $this->rows = array_map('array_values', $by);
    }""", r"""        $by = array_map('array_values', $by);

        return $this->rows = $by + $this->fromCopies(array_keys($by));
    }

    /**
     * The chart of a loan held only in the dated copies, placed in time (see the class note).
     *
     * @param  list<string>  $known  accounts the live or superseded extracts already hold
     * @return array<string, list<array>>
     */
    private function fromCopies(array $known): array
    {
        $known = array_flip($known);
        $copies = [];
        foreach (self::COPIES as $query => $copyDate) {
            foreach ($this->zone->family([$query]) as $row) {
                $p = $row['payload'];
                $account = (string) ($row['account'] ?? '');
                if ($account === '' || isset($known[$account]) || ($p['ACTIVE_FLAG'] ?? 'Y') !== 'Y' || ($p['DELETE_FLAG'] ?? 'N') === 'Y') {
                    continue;
                }
                $due = self::date((string) ($p['INSTALLMENT_DATE'] ?? '')) ?? $row['row_date'];
                if ($due === null) {
                    continue;
                }
                $copies[$account][$query][] = [
                    'created' => self::date((string) ($p['TRANSACTION_DATE'] ?? '')), 'due' => $due,
                    'amount' => (float) str_replace(',', '', (string) ($p['INSTALLMENT_AMT'] ?? '0')),
                    'det' => (string) ($p['INST_DET_ID'] ?? ''), 'serial' => (int) ($p['INST_SR_NO'] ?? 0),
                ];
            }
        }
        if ($copies === []) {
            return [];
        }
        $livePlans = [];
        foreach ($this->zone->family(['P3_11']) as $row) {
            if (($row['payload']['DELETE_FLAG'] ?? 'N') !== 'Y' && ($id = (string) ($row['payload']['INST_DET_ID'] ?? '')) !== '') {
                $livePlans[$id] = true;
            }
        }
        $content = function (array $rows): string {
            $k = array_map(fn ($r) => $r['due'] . '=' . number_format($r['amount'], 2, '.', '') . '#' . $r['serial'], $rows);
            sort($k);

            return implode('|', $k);
        };
        $out = [];
        foreach ($copies as $account => $byCopy) {
            $dated = array_keys(array_intersect_key(self::COPIES, $byCopy)); // in date order
            $start = null;
            $segment = null;
            $label = [];
            $flush = function (?string $end) use (&$out, $account, &$segment, &$start, &$label): void {
                foreach ($segment as $r) {
                    $out[$account][] = ['created' => $start, 'replaced' => $end, 'due' => $r['due'], 'amount' => $r['amount'], 'det' => $r['det'], 'serial' => $r['serial'],
                        'copy' => implode(' and ', $label)];
                }
            };
            foreach ($dated as $i => $q) {
                $rows = $byCopy[$q];
                if ($segment !== null && $content($segment) === $content($rows)) {
                    $segment = $rows; // the same instalments, perhaps on renumbered plans: the chart runs on
                    $label[] = $q;
                    continue;
                }
                if ($segment !== null) {
                    // the previous chart ends after the previous copy's date; the days up to this copy have no chart
                    $flush(CarbonImmutable::parse(self::COPIES[$dated[$i - 1]])->addDay()->toDateString());
                }
                $created = array_filter(array_column($rows, 'created'));
                $start = $segment === null ? ($created === [] ? self::COPIES[$q] : min($created)) : self::COPIES[$q];
                $segment = $rows;
                $label = [$q];
            }
            $last = end($dated);
            $plans = array_unique(array_filter(array_column($segment, 'det')));
            $live = $plans !== [] && array_diff($plans, array_keys($livePlans)) === [];
            $flush($live ? null : CarbonImmutable::parse(self::COPIES[$last])->addDay()->toDateString());
        }

        return $out;
    }""")
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
