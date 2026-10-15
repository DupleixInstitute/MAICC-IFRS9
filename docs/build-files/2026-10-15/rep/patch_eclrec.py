import sys, re
p = sys.argv[1]
s = open(p, encoding='utf-8').read()


def rep(old, new, count=1):
    global s
    assert s.count(old) >= 1, old[:80]
    if count == 1:
        assert s.count(old) == 1, (old[:80], s.count(old))
    s = s.replace(old, new)


# value expressions
n = 0
for a in ['lb_start', 'lb_end']:
    old = 'DB::raw("COALESCE(' + a + '.{$valueColumn}, 0) as'
    new = 'DB::raw(self::valueSql(\'' + a + '.\', $valueColumn) . " as'
    n += s.count(old)
    s = s.replace(old, new)
old = 'DB::raw("(COALESCE(lb_end.{$valueColumn}, 0) - COALESCE(lb_start.{$valueColumn}, 0)) as movement_value")'
assert old in s
s = s.replace(old, 'DB::raw("(" . self::valueSql(\'lb_end.\', $valueColumn) . " - " . self::valueSql(\'lb_start.\', $valueColumn) . ") as movement_value")')
print('value replacements', n)

rep("""     * Build the (unlimited) query for a detailed section. $valueColumn is
     * internally derived ('carrying_amount' or 'ecl_value'), never user input.
     */""", """     * Build the (unlimited) query for a detailed section. $valueColumn is
     * internally derived ('carrying_amount' or 'ecl_value'), never user input;
     * the ECL is the booked allowance (BookedEcl), not the undiscounted measure.
     */""")

rep("""    private static function movementLabel(string $movementType): string
    {""", """    /**
     * The value of one loan row: the carrying amount, or the booked loss
     * allowance (discounted at the original EIR where the engine discounted
     * it; the programme measure for Mega Farm; otherwise the undiscounted ECL,
     * which the summary counts and names). Built 14 October 2026; it read the
     * undiscounted ecl_value until then.
     */
    private static function valueSql(string $alias, string $valueColumn): string
    {
        return $valueColumn === 'carrying_amount' ? "COALESCE({$alias}carrying_amount, 0)" : BookedEcl::sql($alias);
    }

    private static function movementLabel(string $movementType): string
    {""")

# generate: write-offs and basis
rep("""        $report = self::initializeReport($movementType);

        $rowCount = 0;
        $startTransitionCount = 0;
        $newTransitionCount = 0;

        foreach (self::fetchStartRowsChunked($portfolioId, $startPeriod, $endPeriod, $valueColumn) as $row) {
            self::processRow($row, $report);
            $rowCount++;
            $startTransitionCount++;
        }

        foreach (self::fetchNewRowsChunked($portfolioId, $startPeriod, $endPeriod, $valueColumn) as $row) {
            self::processRow($row, $report);
            $rowCount++;
            $newTransitionCount++;
        }
""", """        $report = self::initializeReport($movementType);

        $rowCount = 0;
        $startTransitionCount = 0;
        $newTransitionCount = 0;
        // amounts written off between the two month-ends (the ledger by the write-off rule and the
        // approved register; WriteOffSource), per contract; until 14 October 2026 this was nil
        $writeOffs = [];
        foreach (WriteOffSource::between(WriteOffSource::monthEnd($startPeriod), WriteOffSource::monthEnd($endPeriod)) as $w) {
            $writeOffs[(string) $w['contract_id']] = ($writeOffs[(string) $w['contract_id']] ?? 0) + (float) $w['amount'];
        }

        foreach (self::fetchStartRowsChunked($portfolioId, $startPeriod, $endPeriod, $valueColumn) as $row) {
            self::processRow($row, $report, $writeOffs[(string) $row->contract_id] ?? null, $valueColumn);
            $rowCount++;
            $startTransitionCount++;
        }

        foreach (self::fetchNewRowsChunked($portfolioId, $startPeriod, $endPeriod, $valueColumn) as $row) {
            self::processRow($row, $report, $writeOffs[(string) $row->contract_id] ?? null, $valueColumn);
            $rowCount++;
            $newTransitionCount++;
        }
        $scope = fn ($q) => $q->where('loan_portfolio_id', $portfolioId);
        $basis = $movementType === 'ecl_value'
            ? ['start' => BookedEcl::summary($startPeriod, $scope), 'end' => BookedEcl::summary($endPeriod, $scope)] : null;
""")
rep("""            'rows' => self::formatRows($report),
            'row_count' => $rowCount,""", """            'rows' => self::formatRows($report),
            'basis' => $basis,
            'row_count' => $rowCount,""")

# CSV: basis lines after the table
rep("""            foreach ($report['rows'] as $row) {
                fputcsv($handle, [
                    $row['particulars'],
                    number_format($row['stage_1'], 2, '.', ''),
                    number_format($row['stage_2'], 2, '.', ''),
                    number_format($row['stage_3'], 2, '.', ''),
                    number_format($row['total'], 2, '.', ''),
                ]);
            }
""", """            foreach ($report['rows'] as $row) {
                fputcsv($handle, [
                    $row['particulars'],
                    number_format($row['stage_1'], 2, '.', ''),
                    number_format($row['stage_2'], 2, '.', ''),
                    number_format($row['stage_3'], 2, '.', ''),
                    number_format($row['total'], 2, '.', ''),
                ]);
            }
            foreach ($report['basis'] ?? [] as $side => $b) {
                $r = $b['reconciliation'];
                fputcsv($handle, []);
                fputcsv($handle, [($side === 'start' ? 'Opening' : 'Closing') . ' allowance (' . $b['period'] . '): basis', $b['message']]);
                fputcsv($handle, ['ECL at the reporting date, undiscounted', '', '', '', number_format($r['undiscounted'], 2, '.', '')]);
                fputcsv($handle, ['Add: lifetime projection over the reporting-date measure', '', '', '', number_format($r['projection'], 2, '.', '')]);
                fputcsv($handle, ['Less: effect of discounting at the original EIR', '', '', '', number_format($r['discounting_effect'], 2, '.', '')]);
                fputcsv($handle, ['Allowance shown', '', '', '', number_format($r['shown'], 2, '.', '')]);
                fputcsv($handle, ['Of which with no discounted figure, shown undiscounted (' . $r['not_booked_loans'] . ' loans)', '', '', '', number_format($r['not_booked'], 2, '.', '')]);
            }
""")

# fetch rows: drop the hard-coded zero
s = s.replace("""                DB::raw('0 as is_written_off_in_period'),
""", "")

# processRow with write-off
rep("""    private static function processRow(object $row, array &$report): void
    {
        $startStage = self::normalizeStage($row->start_stage);
        $endStage = self::normalizeStage($row->end_stage);
        $startValue = (float) $row->start_value;
        $endValue = (float) $row->end_value;
""", """    /**
     * $writtenOff is the gross written off on the contract between the two
     * month-ends (WriteOffSource), or null. On the carrying amount the
     * write-off line takes it whole; on the allowance it takes the allowance
     * the write-off used, the opening allowance up to the amount written off.
     * The rest of the contract's change follows the usual lines.
     */
    private static function processRow(object $row, array &$report, ?float $writtenOff = null, string $valueColumn = 'ecl_value'): void
    {
        $startStage = self::normalizeStage($row->start_stage);
        $endStage = self::normalizeStage($row->end_stage);
        $startValue = (float) $row->start_value;
        $endValue = (float) $row->end_value;
        $hasStart = $row->has_start_record == 1;
        $hasEnd = $row->has_end_record == 1;
        $wo = 0.0;
        if ($writtenOff !== null && $writtenOff > 0) {
            $wo = $valueColumn === 'carrying_amount' ? $writtenOff : ($hasStart ? max(0.0, min($startValue, $writtenOff)) : 0.0);
            $woStage = $hasEnd ? $endStage : $startStage;
            if ($woStage !== null && $wo != 0.0) {
                $report['Amounts written off'][$woStage] -= $wo;
                if ($hasEnd) {
                    $endValue += $wo; // the rest of the change, as if the amount had not been written off
                }
            }
        }
""")
rep("""        if ($endStage !== null) {
            $report[$closingKey][$endStage] += $endValue;
        }

        $hasStart = $row->has_start_record == 1;
        $hasEnd = $row->has_end_record == 1;
""", """        if ($endStage !== null) {
            $report[$closingKey][$endStage] += $endValue - ($hasEnd ? $wo : 0.0);
        }
""")
rep("""        if ($hasStart && !$hasEnd && $startStage !== null) {
            if ($row->is_written_off_in_period ?? false) {
                $report['Amounts written off'][$startStage] -= $startValue;
            } else {
                $report['Financial assets derecognised'][$startStage] -= $startValue;
            }
        }""", """        if ($hasStart && !$hasEnd && $startStage !== null) {
            // gone from the book: what was written off is on its own line, the rest derecognised
            $report['Financial assets derecognised'][$startStage] -= $startValue - $wo;
        }""")
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
