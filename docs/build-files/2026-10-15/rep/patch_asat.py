import sys
p = sys.argv[1]
s = open(p, encoding='utf-8').read()


def rep(old, new):
    global s
    assert s.count(old) == 1, (old[:90], s.count(old))
    s = s.replace(old, new)


rep(""" * the cash the ledger shows received in each, and accrues the part month
 * the way section 3 says E-Banker does: simple interest at the contractual
 * rate on the prior month-end balance for the actual days over 365 (system
 * audit of 9 October 2026, finding M13: the part month had compounded the
 * EIR instead).""", """ * the cash the ledger shows received in each, and accrues the part month
 * twice, kept apart (review item R12, built 14 October 2026; until then the
 * contractual accrual was returned as the EIR interest):
 *  - the EIR interest: the month's interest at the monthly EIR on the prior
 *    month-end amortised cost, as the revenue run computes it, for the days of
 *    the month elapsed (so the month-end equals the run's month), which is what
 *    moves the amortised cost;
 *  - the contractual accrual: the way section 3 says E-Banker accrues, simple
 *    interest at the contractual rate on the prior month-end ledger balance
 *    for the actual days over 365 (finding M13), accrued and not yet posted.""")

rep("""            } else {
                // the part month: section 3, simple interest at the contractual rate on the prior month-end balance, actual days over 365
                $days = $priorEnd->diffInDays($asAt);
                if ($rate !== null) {
                    $interestPeriod = round($balance * $rate * $days / 365, 2);
                    $basis = sprintf('prior month-end closing %s at the contractual rate %s simple on %d/365 days (section 3), less cash received %s', number_format($balance, 2), number_format($rate * 100, 4) . '%', $days, number_format($cashInMonth, 2));
                } else {
                    $interestPeriod = round($balance * ((1 + $eir) ** ($days / 365) - 1), 2);
                    $basis = sprintf('prior month-end closing %s on %d/365 days at the EIR daily equivalent, since the contract carries no contractual rate, less cash received %s', number_format($balance, 2), $days, number_format($cashInMonth, 2));
                }
            }""", """            } else {
                // the part month (review item R12): the EIR interest is the month's interest at the monthly EIR on the
                // prior month-end amortised cost for the days elapsed, so the month-end equals the revenue run's month;
                // the contractual accrual (section 3: the contractual rate, simple, on the prior month-end ledger balance,
                // actual days over 365) is returned beside it as what E-Banker has accrued and not yet posted
                $days = $priorEnd->diffInDays($asAt);
                $daysInMonth = $asAt->daysInMonth;
                $interestPeriod = round($balance * $monthly * $days / $daysInMonth, 2);
                $basis = sprintf('prior month-end closing %s at the monthly EIR %s for %d of %d days, less cash received %s', number_format($balance, 2), number_format($monthly * 100, 4) . '%', $days, $daysInMonth, number_format($cashInMonth, 2));
                $ledgerAtPriorEnd = round(-array_sum(array_map(fn ($p) => $p['date'] <= $priorEnd->toDateString() ? $p['amount'] : 0.0, $ledger)), 2);
                if ($rate !== null) {
                    $contractualAccrued = round($ledgerAtPriorEnd * $rate * $days / 365, 2);
                    $contractualBasis = sprintf('prior month-end ledger balance %s at the contractual rate %s simple on %d/365 days (section 3), accrued and not yet posted', number_format($ledgerAtPriorEnd, 2), number_format($rate * 100, 4) . '%', $days);
                } else {
                    $contractualBasis = 'the contract carries no contractual rate';
                }
            }""")

rep("""        // the amortised cost and the EIR interest at the date
        $rolled = [];""", """        // the amortised cost and the EIR interest at the date; the contractual accrual of a part month beside it
        $rolled = [];
        $contractualAccrued = null;
        $contractualBasis = null;""")

# year to date and cumulative contractual: posted plus the part month accrued, like with like
rep("""        $ytdPosted = 0.0;
        foreach ($ledger as $p) {
            if (str_starts_with($p['date'], $year) && $isInterest($p)) {
                $ytdPosted -= $p['amount'];
            }
        }""", """        $ytdPosted = 0.0;
        foreach ($ledger as $p) {
            if (str_starts_with($p['date'], $year) && $isInterest($p)) {
                $ytdPosted -= $p['amount'];
            }
        }
        // like with like inside a month: the part month's EIR interest is set against the contractual interest posted plus accrued
        $ytdPosted += $contractualAccrued ?? 0.0;""")
rep("""        $cumPosted = 0.0;
        foreach ($ledger as $p) {
            if ($first !== null && substr($p['date'], 0, 7) >= $first && $isInterest($p)) {
                $cumPosted -= $p['amount'];
            }
        }""", """        $cumPosted = 0.0;
        foreach ($ledger as $p) {
            if ($first !== null && substr($p['date'], 0, 7) >= $first && $isInterest($p)) {
                $cumPosted -= $p['amount'];
            }
        }
        $cumPosted += $contractualAccrued ?? 0.0;""")
rep("""            'interest' => ['eir_period_to_date' => $interestPeriod, 'contractual_period_to_date' => round($postedInMonth, 2),""",
    """            'interest' => ['eir_period_to_date' => $interestPeriod, 'contractual_period_to_date' => round($postedInMonth, 2),
                'contractual_accrued_period_to_date' => $contractualAccrued, 'contractual_accrued_basis' => $contractualBasis,""")
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
