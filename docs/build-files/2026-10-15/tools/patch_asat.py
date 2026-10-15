P = 'C:/xampp/htdocs/MAICC-IFRS9/app/Services/Eir/EirAsAtService.php'
s = open(P, encoding='utf-8').read()

def rep(a, b):
    global s
    assert a in s, a[:70]
    s = s.replace(a, b, 1)

rep("""                'eir_ytd' => $v['interest']['eir_year_to_date'], 'contractual_ytd' => $v['interest']['contractual_year_to_date'], 'difference' => $v['interest']['difference_year_to_date'], 'amortised_cost' => $v['amortised_cost'], 'gross' => $v['gross_carrying_amount']];""",
"""                'eir_ytd' => $v['interest']['eir_year_to_date'], 'contractual_ytd' => $v['interest']['contractual_year_to_date'], 'difference' => $v['interest']['difference_year_to_date'], 'amortised_cost' => $v['amortised_cost'], 'gross' => $v['gross_carrying_amount'],
                // a credit balance: the customer has paid more than the ledger says is owed; it is owed back, not a negative loan
                'credit_balance' => $v['gross_carrying_amount'] < 0 ? round(-$v['gross_carrying_amount'], 2) : 0.0];""")
rep("""        return ['as_at' => $asAt->toDateString(), 'year' => $year, 'period' => $period, 'by_product' => $group('product'), 'by_gl' => $group('gl'), 'total' => $total, 'contracts' => $lines,
            'note' => 'Contracts with a locked EIR only; the revenue shift for the year to the date is the difference column (section 6.11).'];""",
"""        // the months of the year before the date with no revenue run: their EIR interest is rolled here, not locked
        $held = DB::table('eir_amortisation')->where('reporting_period', 'like', $year . '-%')->where('reporting_period', '<', $period)->distinct()->pluck('reporting_period')->all();
        $missing = [];
        for ($m = CarbonImmutable::parse($year . '-01-01'); $m->format('Y-m') < $period; $m = $m->addMonth()) {
            if (! in_array($m->format('Y-m'), $held, true) && DB::table('loan_books')->where('reporting_period', $m->format('Y-m'))->exists()) {
                $missing[] = $m->format('Y-m');
            }
        }
        $total['credit_balances'] = round(array_sum(array_column($lines, 'credit_balance')), 2);
        $total['credit_balance_contracts'] = count(array_filter($lines, fn ($l) => $l['credit_balance'] > 0));

        return ['as_at' => $asAt->toDateString(), 'year' => $year, 'period' => $period, 'by_product' => $group('product'), 'by_gl' => $group('gl'), 'total' => $total, 'contracts' => $lines,
            'missing_revenue_months' => $missing,
            'note' => 'Contracts with a locked EIR only; the revenue shift for the year to the date is the difference column (section 6.11).'];""")
open(P, 'w', encoding='utf-8').write(s)

V = 'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/Eir/AsAt/Index.vue'
v = open(V, encoding='utf-8').read()
a = '<td class="num">{{ fmt(l.gross) }}</td></tr></tbody></table></div>'
assert a in v
v = v.replace(a, '<td class="num">{{ fmt(l.gross) }}<div v-if="l.credit_balance > 0" class="text-[11px] font-semibold text-amber-700" title="The customer has paid more than the ledger says is owed: an amount owed back, not a negative loan">credit balance</div></td></tr></tbody></table></div>', 1)
b = '<div class="grid grid-cols-1 gap-6 2xl:grid-cols-2">'
assert b in v
v = v.replace(b, '''<p v-if="book.missing_revenue_months && book.missing_revenue_months.length" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-900">No EIR revenue run is held for {{ book.missing_revenue_months.join(', ') }}: the EIR interest of those months is rolled forward here at the monthly EIR, not read from a locked run. Run the revenue for them (Maintenance, Month-end).</p>
        <p v-if="book.total.credit_balance_contracts" class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-2 text-xs text-gray-700">{{ book.total.credit_balance_contracts }} contract(s) carry a credit balance of {{ fmt(book.total.credit_balances) }} in total: the customer paid more than was owed. The amount is owed back to the customer; it is shown in the gross column as a negative and marked.</p>
        ''' + b, 1)
open(V, 'w', encoding='utf-8').write(v)
print('ok')
