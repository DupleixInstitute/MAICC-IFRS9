import sys
p = sys.argv[1]
s = open(p, encoding='utf-8').read()


def rep(old, new):
    global s
    assert old in s, old[:80]
    s = s.replace(old, new)


rep("""            ->selectRaw('COUNT(*) n, SUM(COALESCE(e.carrying_amount,0) - COALESCE(s.carrying_amount,0)) amount')->first();
        $writeOffs = $sum(DB::table('loan_books')->where('loan_portfolio_id', $portfolioId)->where('reporting_period', $end)
            ->where('contract_status', 'like', '%writ%')->selectRaw('COUNT(*) n, SUM(COALESCE(carrying_amount,0)) amount')->first());

        $expected = $opening->amount + $new->amount - $gone->amount + (float) $continuing->amount;
        $expectedLoans = $opening->n + $new->n - $gone->n;
""", """            ->selectRaw('COUNT(*) n, SUM(COALESCE(e.carrying_amount,0) - COALESCE(s.carrying_amount,0)) amount')->first();

        // Write-offs: the ledger's (the WriteOffClassifier's rule) and the approved register's, between the
        // two month-ends, on the portfolio's contracts (spec v4 3.8; built 14 October 2026). The loan book's
        // contract_status carries no write-off marker, so the old filter on it never matched. A write-off
        // takes its amount out of the line the contract would otherwise sit in (new, gone or continuing).
        $wo = \\App\\Services\\Reports\\WriteOffSource::between(\\App\\Services\\Reports\\WriteOffSource::monthEnd($start), \\App\\Services\\Reports\\WriteOffSource::monthEnd($end));
        $where = DB::table('loan_books')->where('loan_portfolio_id', $portfolioId)->whereIn('reporting_period', [$start, $end])
            ->whereIn('contract_id', array_values(array_unique(array_map(fn ($w) => (string) $w['contract_id'], $wo))) ?: ['-'])
            ->get(['contract_id', 'reporting_period'])->groupBy('contract_id')->map(fn ($rows) => $rows->pluck('reporting_period')->all());
        $woLines = ['new' => 0.0, 'gone' => 0.0, 'continuing' => 0.0];
        $woList = [];
        foreach ($wo as $w) {
            $in = $where->get((string) $w['contract_id']);
            if (! $in) {
                continue; // not this portfolio's, or in neither book
            }
            $line = in_array($start, $in, true) ? (in_array($end, $in, true) ? 'continuing' : 'gone') : 'new';
            $woLines[$line] += (float) $w['amount'];
            $woList[] = $w;
        }
        $writeOffs = (object) ['n' => count(array_unique(array_column($woList, 'contract_id'))), 'amount' => round(array_sum(array_column($woList, 'amount')), 2)];

        $expected = $opening->amount + ($new->amount + $woLines['new']) - ($gone->amount - $woLines['gone']) + ((float) $continuing->amount + $woLines['continuing']) - $writeOffs->amount;
        $expectedLoans = $opening->n + $new->n - $gone->n;
""")
rep("""                ['key' => 'new', 'label' => 'Add: new loans (in the closing book only)', 'loans' => $new->n, 'amount' => $new->amount],
                ['key' => 'gone', 'label' => 'Less: loans repaid in full or derecognised (in the opening book only)', 'loans' => $gone->n, 'amount' => -$gone->amount],
                ['key' => 'continuing', 'label' => 'Net change on continuing loans (interest and drawdowns less repayments)', 'loans' => (int) $continuing->n, 'amount' => (float) $continuing->amount],""",
    """                ['key' => 'new', 'label' => 'Add: new loans (in the closing book only)', 'loans' => $new->n, 'amount' => round($new->amount + $woLines['new'], 2)],
                ['key' => 'gone', 'label' => 'Less: loans repaid in full or derecognised (in the opening book only)', 'loans' => $gone->n, 'amount' => -round($gone->amount - $woLines['gone'], 2)],
                ['key' => 'continuing', 'label' => 'Net change on continuing loans (interest and drawdowns less repayments)', 'loans' => (int) $continuing->n, 'amount' => round((float) $continuing->amount + $woLines['continuing'], 2)],
                ['key' => 'written_off', 'label' => 'Less: amounts written off (ledger write-offs and the approved register)', 'loans' => $writeOffs->n, 'amount' => -$writeOffs->amount],""")
rep("""            'write_offs' => ['loans' => $writeOffs->n, 'amount' => $writeOffs->amount],""",
    """            'write_offs' => ['loans' => $writeOffs->n, 'amount' => $writeOffs->amount,
                'list' => array_map(fn ($w) => ['date' => $w['date'], 'contract_id' => $w['contract_id'], 'customer_name' => $w['customer_name'], 'amount' => $w['amount'], 'source' => $w['source'], 'reference' => $w['reference']], $woList)],""")
rep("""                ['label' => 'Closing loan book', 'value' => $m($r['lines'][5]['amount']), 'tone' => 'maiic'],
                ['label' => 'Difference', 'value' => $m($r['lines'][6]['amount']), 'tone' => $r['ties'] ? 'maiic' : 'rose'],""",
    """                ['label' => 'Closing loan book', 'value' => $m(collect($r['lines'])->firstWhere('key', 'closing')['amount']), 'tone' => 'maiic'],
                ['label' => 'Difference', 'value' => $m(collect($r['lines'])->firstWhere('key', 'variance')['amount']), 'tone' => $r['ties'] ? 'maiic' : 'rose'],""")
rep("""                'Write-offs: ' . ($w['loans'] ? number_format($w['loans']) . ' loans in the closing book carry a write-off status (' . $m($w['amount']) . ').' : 'no loan carries a write-off status, so loans that leave the book are shown as repaid or derecognised.'),""",
    """                'Write-offs: ' . ($w['loans'] ? number_format($w['loans']) . ' loans written off between the two month-ends, ' . $m($w['amount']) . ', from the ledger (the write-off rule: type 300, a batch debiting GL 1064, an ACCOUNT CLOSURE on a status-H account) and the approved write-off register.' : 'none in the ledger (by the write-off rule) or approved in the write-off register between the two month-ends.'),""")
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
