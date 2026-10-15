import sys
p = sys.argv[1]
s = open(p, encoding='utf-8').read()


def rep(old, new):
    global s
    assert s.count(old) == 1, (old[:90], s.count(old))
    s = s.replace(old, new)


rep("""/**
 * Risk & Regulatory, RBM Return (provisional): the classification and
 * provisioning return of the DFI directive, section 17, filled from the
 * system in the directive's order, with the CSV. Re-laid out line for
 * line when MAIIC Risk's prescribed form arrives.
 */""", """/**
 * Risk & Regulatory, RBM Return (provisional): the classification and
 * provisioning return of the Credit Risk Management for DFIs Directive,
 * 2018 (paragraphs 10 to 16; the loan loss reserve on the call report,
 * paragraph 15(3)), filled from the system in the directive's order, with
 * the CSV. Re-laid out line for line when MAIIC Risk's prescribed form
 * arrives. Paragraph references corrected 14 October 2026 against the
 * Gazette (they cited section 17, the monetary penalties, for the return).
 */""")
rep("""            'subtitle' => 'Classification and provisioning return of the RBM DFI directive (section 17), filled from the system in the order of the directive. Provisional layout.',""",
    """            'subtitle' => 'Classification and provisioning return of the RBM Credit Risk Management for DFIs Directive, 2018 (paragraphs 10 to 16; the loan loss reserve on the call report, paragraph 15(3)), filled from the system in the order of the directive. Provisional layout.',""")
rep("""                ['heading' => 'A. Classification of facilities (sections 9 to 11)',""", """                ['heading' => 'A. Classification of facilities (paragraphs 10 and 11)',""")
rep("""                ['heading' => 'B. Provisions (section 12)', 'columns' => ['Class', 'Balance', 'Minimum rate', 'Minimum provision', 'IFRS 9 allowance', 'Shortfall'], 'align' => ['l', 'r', 'r', 'r', 'r', 'r'],
                    'rows' => array_merge(array_map(fn ($b) => [$b['class'], $m($b['balance']), number_format($b['minimum_rate'] * 100, 0) . '%', $m($b['minimum_provision']), $m($b['ifrs9_allowance']), $m($b['shortfall'])], $r['sections']['B_provisions']),
                        [['Totals', '', '', $m($t['minimum_provision']), $m($t['ifrs9_allowance']), 'Higher of the two: ' . $m($t['higher_of_the_two'])]])],
                ['heading' => 'C. Interest in suspense (section 13)', 'columns' => ['Item', 'Value'], 'align' => ['l', 'r'], 'rows' => $kv($r['sections']['C_interest_in_suspense'])],
                ['heading' => 'D. Restructured facilities (section 15)',""", """                ['heading' => 'B. Provisions (paragraph 14 and the Schedule; loan loss reserve, paragraph 15)', 'columns' => ['Class', 'Balance', 'Minimum rate', 'Minimum provision', 'IFRS 9 allowance', 'Shortfall'], 'align' => ['l', 'r', 'r', 'r', 'r', 'r'],
                    'note' => isset($r['sections']['B_allowance_basis']) ? 'IFRS 9 allowance: ' . $r['sections']['B_allowance_basis']['message'] : null,
                    'rows' => array_merge(array_map(fn ($b) => [$b['class'], $m($b['balance']), number_format($b['minimum_rate'] * 100, 0) . '%', $m($b['minimum_provision']), $m($b['ifrs9_allowance']), $m($b['shortfall'])], $r['sections']['B_provisions']),
                        [['Totals', '', '', $m($t['minimum_provision']), $m($t['ifrs9_allowance']), 'Higher of the two: ' . $m($t['higher_of_the_two'])]],
                        isset($t['loan_loss_reserve']) ? [['Loan loss reserve (paragraph 15(1)): the minimum provision over the IFRS 9 allowance', '', '', '', '', $m($t['loan_loss_reserve'])]] : [])],
                ...(isset($r['sections']['B_loss_write_offs']) ? [['heading' => 'Loss facilities to be written off (paragraph 12)', 'note' => $r['sections']['B_loss_write_offs']['note'],
                    'columns' => ['Contract', 'Customer', 'Days past due', 'Loss since', 'Months loss', 'Write off by', 'Balance', 'IFRS 9 allowance', 'Status'], 'align' => ['l', 'l', 'r', 'l', 'r', 'l', 'r', 'r', 'l'],
                    'rows' => array_merge(array_map(fn ($l) => [$l['contract_id'], (string) ($l['customer_name'] ?? '-'), (string) $l['days_past_due'], ($l['loss_since_open'] ? 'at or before ' : '') . $l['loss_since'], (string) $l['months_classified_loss'], $l['write_off_due_by'], $m($l['balance']), $m($l['ifrs9_allowance']), $l['status']], $r['sections']['B_loss_write_offs']['rows']),
                        [['Total', '', '', '', '', '', $m($r['sections']['B_loss_write_offs']['balance']), '', $r['sections']['B_loss_write_offs']['overdue'] . ' past the write-off quarter']])]] : []),
                ['heading' => 'C. Interest in suspense (paragraph 13)', 'columns' => ['Item', 'Value'], 'align' => ['l', 'r'], 'rows' => $kv($r['sections']['C_interest_in_suspense']),
                    'note' => (string) ($r['sections']['C_interest_in_suspense']['note'] ?? '')],
                ['heading' => 'D. Restructured facilities (paragraph 16(1))',""")
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
