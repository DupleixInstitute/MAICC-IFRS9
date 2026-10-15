import sys
p = sys.argv[1]
s = open(p, encoding='utf-8').read()


def rep(old, new):
    global s
    assert old in s, old[:80]
    s = s.replace(old, new)


rep("""                $e = $r['ecl']['lines'][$side][$s] - $row['ecl'];
                $n = $month['stages'][$s]['contracts'] - $row['loans'];""", """                // the saved run holds the undiscounted ECL (total_ecl) and the discounted ECL of the
                // loans it discounted (total_ecl_discounted); each is compared with its own basis
                $e = $month['stages'][$s]['ecl_undiscounted'] - $row['ecl'];
                $ed = $row['ecl_discounted'] !== null ? $month['stages'][$s]['ecl_discounted'] - $row['ecl_discounted'] : 0;
                $n = $month['stages'][$s]['contracts'] - $row['loans'];""")
rep("""                if ($e !== 0) { $d[] = "Stage {$s} ECL differs by " . $money($e); }""",
    """                if ($e !== 0) { $d[] = "Stage {$s} undiscounted ECL differs by " . $money($e); }
                if ($ed !== 0) { $d[] = "Stage {$s} discounted ECL differs by " . $money($ed); }""")
rep("""                : ['key' => 'saved_' . $side, 'label' => self::period($period) . ' agrees with the saved ECL run', 'level' => 'pass',
                    'detail' => 'ECL, EAD and number of loans by stage agree with the saved ECL run.'];""",
    """                : ['key' => 'saved_' . $side, 'label' => self::period($period) . ' agrees with the saved ECL run', 'level' => 'pass',
                    'detail' => 'ECL (undiscounted and discounted), EAD and number of loans by stage agree with the saved ECL run.'];

            // the basis of the allowance: the note is issued only when every loan with an
            // ECL carries the booked (discounted) allowance (spec 6.10.1; fail closed)
            $basis = $r['basis'][$period];
            $checks[] = match ($basis['status']) {
                'booked' => ['key' => 'basis_' . $side, 'label' => self::period($period) . ': the allowance is the booked (discounted) allowance', 'level' => 'pass',
                    'detail' => $basis['message']],
                'mixed' => ['key' => 'basis_' . $side, 'label' => self::period($period) . ': loans without a discounted allowance', 'level' => 'fail',
                    'detail' => $basis['message'] . ' The note must not be issued until every loan carries its discounted allowance: lock the EIR of each loan named on the ECL run and run the month-end ECL again.'],
                default => ['key' => 'basis_' . $side, 'label' => self::period($period) . ': no ECL', 'level' => 'fail', 'detail' => (string) $basis['message']],
            };""")

rep("""        $checks[] = ['key' => 'write_offs', 'label' => 'Amounts written off', 'level' => 'info',
            'detail' => 'The loan book carries no write-off marker for a loan that leaves the book, so contracts that leave the book are shown as derecognised and amounts written off are nil.'];""",
    """        $wo = $r['write_offs'];
        $bySource = [];
        foreach ($wo as $w) {
            $bySource[$w['source']] = ($bySource[$w['source']] ?? 0) + $w['amount'];
        }
        $checks[] = ['key' => 'write_offs', 'label' => 'Amounts written off', 'level' => 'info',
            'detail' => $wo
                ? number_format(count(array_unique(array_column($wo, 'contract_id')))) . ' contracts written off between ' . self::period($opening) . ' and ' . self::period($closing) . ', ' . $money((int) round(array_sum(array_column($wo, 'amount')) * 100))
                    . ' (' . implode('; ', array_map(fn ($k, $v) => ($k === 'ledger' ? 'in the ledger by the write-off rule' : 'approved in the write-off register') . ' ' . $money((int) round($v * 100)), array_keys($bySource), $bySource)) . '). '
                    . 'The gross line takes the amount written off; the allowance line takes the allowance each write-off used (the opening allowance, up to the amount written off).'
                : 'No write-off between ' . self::period($opening) . ' and ' . self::period($closing) . ' in the ledger (by the write-off rule) or approved in the write-off register.'];
        if ($r['write_offs_outside']) {
            $checks[] = ['key' => 'write_offs_outside', 'label' => 'Write-offs on contracts outside the note', 'level' => 'warn',
                'detail' => number_format(count($r['write_offs_outside'])) . ' write-offs (' . $money((int) round(array_sum(array_column($r['write_offs_outside'], 'amount')) * 100)) . ') are on contracts in neither month\\'s book for this scope, so they are not in the reconciliation: '
                    . implode(', ', array_unique(array_column($r['write_offs_outside'], 'contract_id'))) . '.'];
        }""")

rep("""        $p['parameters'] = 'ECL for each loan is EAD x PD x LGD, where EAD is the carrying amount plus the expected drawn share of undrawn commitments. Applied at '""",
    """        $p['parameters'] = 'ECL for each loan is measured at the reporting date as EAD x PD x LGD, where EAD is the carrying amount plus the expected drawn share of undrawn commitments, and is then projected over the loan\\'s remaining life and discounted at its original effective interest rate (IFRS 9 5.5.17, B5.5.44): the discounted amount is the loss allowance. Applied at '""")

rep("""        $p['write_offs'] = 'The loan book has no write-off marker for a loan that leaves the book, so amounts written off are shown as nil and contracts leaving the book are shown as derecognised.';""",
    """        $basis = $r['basis'][$closing];
        $rc = $basis['reconciliation'];
        $p['discounting'] = 'At ' . self::period($closing) . ' the reporting-date ECL is ' . self::money(Attribution::cents($rc['undiscounted']), $r['currency'])
            . '; the lifetime projection adds ' . self::money(Attribution::cents($rc['projection']), $r['currency'])
            . ' and discounting at the original EIR deducts ' . self::money(Attribution::cents(-$rc['discounting_effect']), $r['currency'])
            . ', giving the allowance of ' . self::money(Attribution::cents($rc['shown']), $r['currency']) . '. ' . $basis['message'];

        $wo = $r['write_offs'];
        $p['write_offs'] = 'A loan is written off when there is no reasonable expectation of recovering it (IFRS 9 5.4.4). The amounts written off are those E-Banker posted as write-offs (the write-off transaction type, a batch whose other leg debits GL 1064 Term Loans Write Offs, or an ACCOUNT CLOSURE transfer on an account marked H in the account master) and those approved in the write-off register by a person other than the proposer. '
            . ($wo ? 'Between ' . self::period($opening) . ' and ' . self::period($closing) . ', ' . number_format(count(array_unique(array_column($wo, 'contract_id')))) . ' contracts were written off, '
                . self::money((int) round(array_sum(array_column($wo, 'amount')) * 100), $r['currency']) . '. '
                : 'No loan was written off between ' . self::period($opening) . ' and ' . self::period($closing) . '. ')
            . 'Amounts written off that are still subject to enforcement activity (IFRS 7.35L): ' . lcfirst($r['enforcement']);""")

rep("""        $p['movements'] = 'The movement is attributed contract by contract between ' . self::period($opening) . ' and ' . self::period($closing)
            . '. A contract in both books whose stage changed moves its opening balance from the old stage to the new one (transfers, which net to nil in total); the rest of its change is remeasurement in its new stage. '
            . 'Contracts only in the closing book are new financial assets originated. Contracts only in the opening book have been repaid or otherwise derecognised. Remeasurement includes the effect of changes in PD and LGD between the two months.';""",
    """        $p['movements'] = 'The movement is attributed contract by contract between ' . self::period($opening) . ' and ' . self::period($closing)
            . '. A contract in both books whose stage changed moves its opening balance from the old stage to the new one (transfers, which net to nil in total); the rest of its change is remeasurement in its new stage. '
            . 'Contracts only in the closing book are new financial assets originated. Contracts only in the opening book have been repaid or otherwise derecognised. A contract written off in the period carries the amount written off on the write-off line (gross) and the allowance that write-off used (allowance); the rest of its change follows the same rules. Remeasurement includes the effect of changes in PD, LGD and the discounting between the two months.';""")

open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
