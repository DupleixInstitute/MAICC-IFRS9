p = r'C:\xampp\htdocs\MAICC-IFRS9\app\Services\Fli\FliMonthEnd.php'
s = open(p, encoding='utf-8', newline='').read()
nl = '\r\n' if '\r\n' in s else '\n'
s = s.replace('\r\n', '\n')
def rep(old, new):
    global s
    assert old in s, old[:80]
    s = s.replace(old, new)
rep("""        $eclDone = $applyDone && $book->ecl_at && $appliedAt && CarbonImmutable::parse($book->ecl_at)->gte(CarbonImmutable::parse($appliedAt));
        $steps[] = $this->step('ecl', 'ECL calculated on the post-FLI PD', $eclDone ? 'done' : ($applyDone ? 'todo' : 'blocked'),
            $eclDone ? 'Calculated ' . $book->ecl_at : ($applyDone ? 'The route ran after the last ECL calculation: calculate the ECL.' : 'Needs the route applied first.'),""",
"""        // a restaging, a PD or LGD run or a route after the ECL leaves it stale: the month is mid-run (MonthEndCurrency)
        $currency = MonthEndCurrency::state($period);
        $eclDone = $applyDone && $book->ecl_at && $appliedAt && CarbonImmutable::parse($book->ecl_at)->gte(CarbonImmutable::parse($appliedAt)) && $currency['ecl_stale'] === null;
        $steps[] = $this->step('ecl', 'ECL calculated on the post-FLI PD', $eclDone ? 'done' : ($applyDone ? 'todo' : 'blocked'),
            $eclDone ? 'Calculated ' . $book->ecl_at : ($applyDone ? ($currency['ecl_stale'] ?? 'The route ran after the last ECL calculation') . ' Calculate the ECL.' : 'Needs the route applied first.' . ($currency['ecl_stale'] ? ' ' . $currency['ecl_stale'] : '')),""")
rep("""        $snapCurrent = $snap && $eclDone && $snap['created_at'] >= $book->ecl_at;""",
"""        $snapCurrent = $snap && $eclDone && $snap['created_at'] >= $book->ecl_at && empty($snap['stale']);""")
rep("""            ! $snapCurrent ? ($eclDone ? 'Record the month: the ECL under each scenario, the reconciliation and what it ran under.' : 'Needs the ECL first.')""",
"""            ! $snapCurrent ? ($eclDone ? 'Record the month: the ECL under each scenario, the reconciliation and what it ran under.' . (! empty($snap['stale']) ? ' ' . $snap['stale'] : '') : 'Needs the ECL first.' . (! empty($snap['stale']) ? ' ' . $snap['stale'] : ''))""")
s = s.replace('\n', nl)
open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok')
