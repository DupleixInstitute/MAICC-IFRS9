import sys
p = sys.argv[1]
s = open(p, encoding='utf-8').read()


def rep(old, new):
    global s
    assert old in s, old[:80]
    s = s.replace(old, new)


rep(""" * Reporting only: it reads loan_books.ecl_value, carrying_amount and
 * ifrs9stage_post_qualitative (the stage the ECL was provided on) as stored
 * and never recalculates. Three grouped SQL queries, never contract rows in
 * PHP; the attribution itself is EclMovementAttribution (pure, unit-tested).
 */""", """ * Reporting only: it reads the booked allowance (BookedEcl: the ECL
 * discounted at the original EIR, the Mega Farm programme measure, and for a
 * loan with no discounted figure its undiscounted ECL, counted and named),
 * carrying_amount and ifrs9stage_post_qualitative (the stage the ECL was
 * provided on) as stored and never recalculates. Grouped SQL queries, never
 * contract rows in PHP, except the few contracts written off in the period
 * (WriteOffSource: the ledger's write-offs by the WriteOffClassifier and the
 * approved register), which are attributed one by one so the write-off line
 * carries the amount written off (IFRS 7.35H, 35I). The attribution itself is
 * EclMovementAttribution (pure, unit-tested). Booked basis and write-offs
 * built 14 October 2026; until then the note read the undiscounted ecl_value
 * and showed nil written off.
 */""")

rep("""        $closingMonth = self::monthTotals($closing, $portfolioId);
        $openingMonth = self::monthTotals($opening, $portfolioId);
        $pairs = self::pairGroups($opening, $closing, $portfolioId);

        // Contracts new in P1 = P1 totals by stage less the P0 contracts matched into that stage.
        $matched = [];
        foreach ($pairs as $g) {
            if ($g['matched'] && $g['to'] !== null) {
                foreach (['contracts', 'ecl_close', 'gross_close'] as $k) {
                    $matched[$g['to']][$k] = ($matched[$g['to']][$k] ?? 0) + $g[$k];
                }
            }
        }""", """        $closingMonth = self::monthTotals($closing, $portfolioId);
        $openingMonth = self::monthTotals($opening, $portfolioId);
        $writeOffs = self::writeOffs($opening, $closing, $portfolioId);
        $pairs = self::pairGroups($opening, $closing, $portfolioId, array_keys($writeOffs['groups']));

        // Contracts new in P1 = P1 totals by stage less the P0 contracts matched into that stage,
        // less the contracts written off in the period that are in the P1 book (attributed one by one).
        $matched = [];
        foreach (array_merge($pairs, array_values($writeOffs['groups'])) as $g) {
            if ($g['to'] !== null && ($g['matched'] || isset($g['written_off_gross']))) {
                foreach (['contracts', 'ecl_close', 'gross_close'] as $k) {
                    $matched[$g['to']][$k] = ($matched[$g['to']][$k] ?? 0) + $g[$k];
                }
            }
        }""")

rep("""            'ecl' => Attribution::attribute(self::attributionInput($pairs, $newGroups, 'ecl')),
            'gross' => Attribution::attribute(self::attributionInput($pairs, $newGroups, 'gross')),
            'months' => [$opening => $openingMonth, $closing => $closingMonth],
            'saved' => $saved,
            'groups' => self::workingGroups($pairs, $newGroups),
        ];""", """            'ecl' => Attribution::attribute(self::attributionInput($pairs, $newGroups, 'ecl', $writeOffs['groups'])),
            'gross' => Attribution::attribute(self::attributionInput($pairs, $newGroups, 'gross', $writeOffs['groups'])),
            'months' => [$opening => $openingMonth, $closing => $closingMonth],
            'saved' => $saved,
            'groups' => self::workingGroups($pairs, $newGroups, $writeOffs['groups']),
            'basis' => [
                $opening => BookedEcl::summary($opening, $portfolioId ? fn ($q) => $q->where('loan_portfolio_id', $portfolioId) : null),
                $closing => BookedEcl::summary($closing, $portfolioId ? fn ($q) => $q->where('loan_portfolio_id', $portfolioId) : null),
            ],
            'write_offs' => $writeOffs['list'],
            'write_offs_outside' => $writeOffs['outside'],
            'enforcement' => WriteOffSource::enforcementDisclosure(),
        ];""")

rep("""    /** Query 1: every P0 contract with its P1 stage and values, grouped. */
    private static function pairGroups(string $opening, string $closing, ?int $portfolioId): array
    {
        $stage = self::STAGE;
        $portfolioJoin = $portfolioId ? ' AND e.loan_portfolio_id = s.loan_portfolio_id' : '';
        $portfolioWhere = $portfolioId ? ' AND s.loan_portfolio_id = ?' : '';
        $bindings = array_merge([$closing, $opening], $portfolioId ? [$portfolioId] : []);
        $rows = DB::select(
            "SELECT s.{$stage} AS from_stage, e.{$stage} AS to_stage, (e.id IS NOT NULL) AS matched,
                    COUNT(*) AS contracts,
                    SUM(COALESCE(s.ecl_value, 0)) AS ecl_open, SUM(COALESCE(e.ecl_value, 0)) AS ecl_close,
                    SUM(COALESCE(s.carrying_amount, 0)) AS gross_open, SUM(COALESCE(e.carrying_amount, 0)) AS gross_close
               FROM loan_books AS s
               LEFT JOIN loan_books AS e ON e.contract_id = s.contract_id AND e.reporting_period = ?{$portfolioJoin}
              WHERE s.reporting_period = ?{$portfolioWhere}
              GROUP BY s.{$stage}, e.{$stage}, (e.id IS NOT NULL)",
            $bindings
        );""", """    /** Query 1: every P0 contract with its P1 stage and values, grouped; the contracts written off in the period are left to writeOffs(). */
    private static function pairGroups(string $opening, string $closing, ?int $portfolioId, array $exclude = []): array
    {
        $stage = self::STAGE;
        $portfolioJoin = $portfolioId ? ' AND e.loan_portfolio_id = s.loan_portfolio_id' : '';
        $portfolioWhere = $portfolioId ? ' AND s.loan_portfolio_id = ?' : '';
        $excludeWhere = $exclude ? ' AND s.contract_id NOT IN (' . implode(',', array_fill(0, count($exclude), '?')) . ')' : '';
        $bindings = array_merge([$closing, $opening], $portfolioId ? [$portfolioId] : [], array_map('strval', $exclude));
        $eclS = BookedEcl::sql('s.');
        $eclE = BookedEcl::sql('e.');
        $rows = DB::select(
            "SELECT s.{$stage} AS from_stage, e.{$stage} AS to_stage, (e.id IS NOT NULL) AS matched,
                    COUNT(*) AS contracts,
                    SUM({$eclS}) AS ecl_open, SUM(CASE WHEN e.id IS NULL THEN 0 ELSE {$eclE} END) AS ecl_close,
                    SUM(COALESCE(s.carrying_amount, 0)) AS gross_open, SUM(COALESCE(e.carrying_amount, 0)) AS gross_close
               FROM loan_books AS s
               LEFT JOIN loan_books AS e ON e.contract_id = s.contract_id AND e.reporting_period = ?{$portfolioJoin}
              WHERE s.reporting_period = ?{$portfolioWhere}{$excludeWhere}
              GROUP BY s.{$stage}, e.{$stage}, (e.id IS NOT NULL)",
            $bindings
        );""")

rep("""    /** Queries 2 and 3: one month's totals by stage straight from loan_books. */""", """    /**
     * The contracts written off between the two month-ends (WriteOffSource), each
     * as a group of one with its opening and closing values and the amounts the
     * write-off line takes: the gross written off, and the allowance it used
     * (the smaller of the opening allowance and the amount written off; a loan
     * originated in the period has no opening allowance to use). A write-off on
     * a contract in neither book (or outside the portfolio) is listed, not
     * attributed.
     *
     * @return array{groups:array<string,array>,list:array,outside:array}
     */
    private static function writeOffs(string $opening, string $closing, ?int $portfolioId): array
    {
        $list = WriteOffSource::between(WriteOffSource::monthEnd($opening), WriteOffSource::monthEnd($closing));
        $amounts = [];
        foreach ($list as $w) {
            $amounts[(string) $w['contract_id']] = ($amounts[(string) $w['contract_id']] ?? 0) + (int) round($w['amount'] * 100);
        }
        if (! $amounts) {
            return ['groups' => [], 'list' => [], 'outside' => []];
        }
        $stage = self::STAGE;
        $rows = DB::table('loan_books')->whereIn('reporting_period', [$opening, $closing])->whereIn('contract_id', array_map('strval', array_keys($amounts)))
            ->when($portfolioId, fn ($q) => $q->where('loan_portfolio_id', $portfolioId))
            ->selectRaw("contract_id, reporting_period, {$stage} AS stage, " . BookedEcl::sql() . ' AS ecl, COALESCE(carrying_amount, 0) AS gross')
            ->get()->groupBy('contract_id');
        $groups = [];
        $outside = [];
        foreach ($amounts as $cid => $cents) {
            $cid = (string) $cid;
            $mine = $rows->get($cid);
            $s = $mine?->firstWhere('reporting_period', $opening);
            $e = $mine?->firstWhere('reporting_period', $closing);
            if (! $s && ! $e) {
                $outside[$cid] = $cents;
                continue;
            }
            $eclOpen = $s ? Attribution::cents($s->ecl) : 0;
            $groups[$cid] = [
                'contract_id' => $cid,
                'from' => $s ? self::stage($s->stage) : null,
                'to' => $e ? self::stage($e->stage) : null,
                'matched' => $s && $e,
                'contracts' => 1,
                'ecl_open' => $eclOpen, 'ecl_close' => $e ? Attribution::cents($e->ecl) : 0,
                'gross_open' => $s ? Attribution::cents($s->gross) : 0, 'gross_close' => $e ? Attribution::cents($e->gross) : 0,
                'written_off_gross' => $cents,
                'written_off_ecl' => $s ? max(0, min($eclOpen, $cents)) : 0,
            ];
        }

        return ['groups' => $groups,
            'list' => array_values(array_filter($list, fn ($w) => isset($groups[(string) $w['contract_id']]))),
            'outside' => array_values(array_filter($list, fn ($w) => isset($outside[(string) $w['contract_id']])))];
    }

    /** Queries 2 and 3: one month's totals by stage straight from loan_books. */""")

rep("""                SUM(COALESCE(carrying_amount,0)) AS gross, SUM({$ead}) AS ead, SUM(COALESCE(ecl_value,0)) AS ecl,""",
    """                SUM(COALESCE(carrying_amount,0)) AS gross, SUM({$ead}) AS ead, SUM(" . BookedEcl::sql() . ") AS ecl,
                SUM(COALESCE(ecl_value,0)) AS ecl_undiscounted, SUM(CASE WHEN ecl_discount_status = '" . BookedEcl::DISCOUNTED_STATUS . "' THEN COALESCE(ecl_value_discounted,0) ELSE 0 END) AS ecl_discounted,""")
rep("""                'ecl' => Attribution::cents($r->ecl),
                'pd' => [$r->pd_min""", """                'ecl' => Attribution::cents($r->ecl),
                'ecl_undiscounted' => Attribution::cents($r->ecl_undiscounted),
                'ecl_discounted' => Attribution::cents($r->ecl_discounted),
                'pd' => [$r->pd_min""")
rep("""            $stages[$s] ??= ['contracts' => 0, 'gross' => 0, 'ead' => 0, 'ecl' => 0, 'pd' => [null, null], 'lgd' => [null, null]];""",
    """            $stages[$s] ??= ['contracts' => 0, 'gross' => 0, 'ead' => 0, 'ecl' => 0, 'ecl_undiscounted' => 0, 'ecl_discounted' => 0, 'pd' => [null, null], 'lgd' => [null, null]];""")

rep("""            $out[$s] ??= ['ecl' => 0, 'ead' => 0, 'loans' => 0, 'pd' => null, 'lgd' => null, 'level' => $row->ecl_calculation_level, 'updated_at' => (string) $row->updated_at];
            $out[$s]['ecl'] += Attribution::cents($row->total_ecl);""", """            $out[$s] ??= ['ecl' => 0, 'ecl_discounted' => null, 'ead' => 0, 'loans' => 0, 'pd' => null, 'lgd' => null, 'level' => $row->ecl_calculation_level, 'updated_at' => (string) $row->updated_at];
            $out[$s]['ecl'] += Attribution::cents($row->total_ecl);
            if (isset($row->total_ecl_discounted) && $row->total_ecl_discounted !== null) {
                $out[$s]['ecl_discounted'] = ($out[$s]['ecl_discounted'] ?? 0) + Attribution::cents($row->total_ecl_discounted);
            }""")

rep("""    private static function workingGroups(array $pairs, array $newGroups): array
    {""", """    private static function workingGroups(array $pairs, array $newGroups, array $writeOffs = []): array
    {""")
rep("""        foreach ($newGroups as $n) {
            $rows[] = ['from' => null, 'to' => $n['to'], 'category' => 'New', 'contracts' => $n['contracts'],
                'ecl_open' => 0, 'ecl_close' => $n['ecl_close'], 'gross_open' => 0, 'gross_close' => $n['gross_close']];
        }
        usort(""", """        foreach ($newGroups as $n) {
            $rows[] = ['from' => null, 'to' => $n['to'], 'category' => 'New', 'contracts' => $n['contracts'],
                'ecl_open' => 0, 'ecl_close' => $n['ecl_close'], 'gross_open' => 0, 'gross_close' => $n['gross_close']];
        }
        $wo = [];
        foreach ($writeOffs as $g) {
            $k = ($g['from'] ?? '-') . '|' . ($g['to'] ?? '-');
            $wo[$k] ??= ['from' => $g['from'], 'to' => $g['to'], 'category' => 'Written off', 'contracts' => 0, 'ecl_open' => 0, 'ecl_close' => 0, 'gross_open' => 0, 'gross_close' => 0];
            foreach (['contracts', 'ecl_open', 'ecl_close', 'gross_open', 'gross_close'] as $f) {
                $wo[$k][$f] += $g[$f];
            }
        }
        $rows = array_merge($rows, array_values($wo));
        usort(""")

rep("""    private static function attributionInput(array $pairs, array $newGroups, string $what): array
    {""", """    private static function attributionInput(array $pairs, array $newGroups, string $what, array $writeOffs = []): array
    {""")
rep("""        foreach ($newGroups as $n) {
            $groups[] = ['from' => null, 'to' => $n['to'], 'contracts' => $n['contracts'], 'opening' => 0, 'closing' => $n[$what . '_close']];
        }

        return $groups;""", """        foreach ($newGroups as $n) {
            $groups[] = ['from' => null, 'to' => $n['to'], 'contracts' => $n['contracts'], 'opening' => 0, 'closing' => $n[$what . '_close']];
        }
        foreach ($writeOffs as $g) {
            if ($g['from'] === null && $g['to'] === null) {
                continue; // unstaged in both books: reported by the checks
            }
            $groups[] = ['from' => $g['from'], 'to' => $g['to'], 'contracts' => 1, 'opening' => $g[$what . '_open'], 'closing' => $g[$what . '_close'],
                'written_off_amount' => $g['written_off_' . $what]];
        }

        return $groups;""")

open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
