p = 'app/Services/Eir/ScheduleGeneratorService.php'
s = open(p, encoding='utf-8').read()


def rep(a, b):
    global s
    assert a in s, a[:80]
    s = s.replace(a, b)


rep("""        $interestBaseFor = fn (float $balance): float => $interestBasis === self::INTEREST_ON_SANCTION ? $sanctioned : $balance;""",
"""        // Interest on the sanctioned amount still runs only once money has been
        // lent: E-Banker's first charge on a facility runs from its first
        // drawdown ("IntDR Fm 02-MAY-25 To 31-MAY-25"), whatever the basis.
        $interestBaseFor = fn (float $balance): float => $interestBasis === self::INTEREST_ON_SANCTION ? ($tranched && $balance <= 0.0 ? 0.0 : $sanctioned) : $balance;""")

rep("""                // a tranche arrived: the instalment is sized again over the
                // instalments left, on the balance now drawn
                $left = $nPayments - $t + 1;
                $instalment = $emiCalcType === self::EMI_EQUAL_PRINCIPAL
                    ? round($balance / $left, 2)
                    : $this->levelInstalment($balance, $periodRate, $left);""",
"""                // a tranche arrived: the instalment is sized again over the
                // instalments left, on the balance now drawn. This period's
                // interest is already known (it ran part on the old balance and
                // part on the new), so the level instalment I is the one that,
                // paid now and on every date left, retires the balance exactly:
                // balance + interest - I = I x a(left - 1), a the annuity factor.
                $left = $nPayments - $t + 1;
                $instalment = $emiCalcType === self::EMI_EQUAL_PRINCIPAL
                    ? round($balance / $left, 2)
                    : ($balance + $interest) / (1 + $this->annuityFactor($periodRate, $left - 1));""")

rep("""    /**
     * Level annuity instalment: P.r / (1 - (1+r)^-n); straight-line when
     * the rate is zero.
     */""",
"""    /** The present value of 1 a period for n periods at the period rate: (1 - (1+r)^-n) / r; n when r is nil. */
    private function annuityFactor(float $periodRate, int $periods): float
    {
        if ($periods <= 0) {
            return 0.0;
        }

        return $periodRate == 0.0 ? (float) $periods : (1 - pow(1 + $periodRate, -$periods)) / $periodRate;
    }

    /**
     * Level annuity instalment: P.r / (1 - (1+r)^-n); straight-line when
     * the rate is zero.
     */""")

open(p, 'w', encoding='utf-8', newline='\n').write(s)

p = 'app/Services/Eir/ScheduleWorkflowService.php'
s = open(p, encoding='utf-8').read()
# E-Banker counts the days of an interest charge inclusively ("Fm 02-MAY-25 To 31-MAY-25" is 30 days)
rep("""            $b=$prior*$rate*$from->diffInDays($end)/365;""", """            $b=$prior*$rate*($from->diffInDays($end)+1)/365;""")
rep("""                if ($d->isSameMonth($m)) $b+=$t['amount']*$rate*$d->diffInDays($end)/365;""", """                if ($d->isSameMonth($m)) $b+=$t['amount']*$rate*($d->diffInDays($end)+1)/365;""")
rep("""            $sanction=$approved*$rate*$from->diffInDays($end)/365;""", """            $sanction=$approved*$rate*($from->diffInDays($end)+1)/365;""")
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
