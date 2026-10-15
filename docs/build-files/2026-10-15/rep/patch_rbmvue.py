import sys
p = sys.argv[1]
s = open(p, encoding='utf-8').read()


def rep(old, new):
    global s
    assert s.count(old) == 1, (old[:90], s.count(old))
    s = s.replace(old, new)


rep("""The classification and provisioning return of the RBM DFI directive (section 17), in the order of the directive.""",
    """The classification and provisioning return of the RBM Credit Risk Management for DFIs Directive, 2018 (paragraphs 10 to 16), in the order of the directive; the loan loss reserve is reported on the call report the Registrar prescribes (paragraph 15(3)).""")
rep("""<h3 class="text-base font-bold">A. Classification of facilities (sections 9 to 11)</h3>""",
    """<h3 class="text-base font-bold">A. Classification of facilities (paragraphs 10 and 11)</h3>""")
rep("""loss beyond (Gazette section 10).</p>""", """loss beyond (paragraph 10 and the Schedule). Facilities are reviewed for classification every quarter (paragraph 11).</p>""")
rep("""<h3 class="text-base font-bold">B. Provisions (section 12)</h3></div>""",
    """<h3 class="text-base font-bold">B. Provisions (paragraph 14 and the Schedule; loan loss reserve, paragraph 15)</h3>
          <p v-if="ret.sections.B_allowance_basis" class="mt-1 text-xs" :class="ret.sections.B_allowance_basis.status === 'mixed' ? 'text-amber-800' : 'text-gray-500'">IFRS 9 allowance: {{ ret.sections.B_allowance_basis.message }}</p></div>""")
rep("""<th class="num">higher of the two: {{ fmt(ret.sections.B_totals.higher_of_the_two) }}</th></tr></tfoot></table></div></div>""",
    """<th class="num">higher of the two: {{ fmt(ret.sections.B_totals.higher_of_the_two) }}</th></tr>
              <tr v-if="ret.sections.B_totals.loan_loss_reserve != null"><th colspan="5">Loan loss reserve: the minimum provision over the IFRS 9 allowance, an appropriation of retained earnings (paragraph 15(1))</th><th class="num">{{ fmt(ret.sections.B_totals.loan_loss_reserve) }}</th></tr></tfoot></table></div></div>
        <div v-if="ret.sections.B_loss_write_offs" class="maiic-panel">
          <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 p-4 dark:border-slate-700">
            <div><h3 class="text-base font-bold">Loss facilities to be written off (paragraph 12)</h3><p class="max-w-4xl text-xs text-gray-500">{{ ret.sections.B_loss_write_offs.note }}</p></div>
            <span class="maiic-badge" :class="ret.sections.B_loss_write_offs.overdue ? 'maiic-badge-red' : 'maiic-badge-green'">{{ ret.sections.B_loss_write_offs.overdue }} of {{ ret.sections.B_loss_write_offs.facilities }} past the write-off quarter ({{ fmt(ret.sections.B_loss_write_offs.overdue_balance) }})</span>
          </div>
          <div v-if="ret.sections.B_loss_write_offs.rows.length" class="maiic-table-wrap"><table class="maiic-table maiic-table-compact"><thead><tr><th>Contract</th><th>Customer</th><th>Term</th><th class="num">Days past due</th><th>Loss since</th><th class="num">Months loss</th><th>Write off by</th><th class="num">Balance</th><th class="num">IFRS 9 allowance</th><th>Status</th></tr></thead>
            <tbody><tr v-for="l in ret.sections.B_loss_write_offs.rows" :key="l.contract_id"><td>{{ l.contract_id }}</td><td>{{ l.customer_name || '-' }}</td><td>{{ l.term === 'short' ? 'Short' : 'Medium/long' }}</td><td class="num">{{ l.days_past_due }}</td><td class="whitespace-nowrap">{{ l.loss_since_open ? 'at or before ' : '' }}{{ l.loss_since }}</td><td class="num">{{ l.months_classified_loss }}</td><td class="whitespace-nowrap">{{ l.write_off_due_by }}</td><td class="num">{{ fmt(l.balance) }}</td><td class="num">{{ fmt(l.ifrs9_allowance) }}</td><td><span class="maiic-badge" :class="l.status === 'overdue' ? 'maiic-badge-red' : 'maiic-badge-gold'">{{ l.status }}</span></td></tr></tbody>
            <tfoot><tr><th colspan="7">Total</th><th class="num">{{ fmt(ret.sections.B_loss_write_offs.balance) }}</th><th></th><th></th></tr></tfoot></table></div>
          <p v-else class="p-4 text-sm text-gray-500">No facility is classified loss at this month-end.</p>
        </div>""")
rep("""<h3 class="text-base font-bold">C. Interest in suspense (section 13)</h3>
            <div v-for="(v, k) in ret.sections.C_interest_in_suspense" :key="k" class="flex justify-between border-b border-gray-100 py-1 dark:border-slate-700"><span class="text-gray-500">{{ k.replace(/_/g, ' ') }}</span><span class="num">{{ typeof v === 'number' && Math.abs(v) >= 1000 ? fmt(v) : v }}</span></div></div>""",
    """<h3 class="text-base font-bold">C. Interest in suspense (paragraph 13)</h3>
            <template v-for="(v, k) in ret.sections.C_interest_in_suspense" :key="k"><div v-if="k !== 'note' && k !== 'gl_1320_source'" class="flex justify-between gap-3 border-b border-gray-100 py-1 dark:border-slate-700"><span class="text-gray-500">{{ k.replace(/_/g, ' ') }}</span><span class="num">{{ v == null ? 'not held' : (typeof v === 'number' && Math.abs(v) >= 1000 ? fmt(v) : v) }}</span></div></template>
            <p v-if="ret.sections.C_interest_in_suspense.note" class="mt-2 text-xs text-gray-500">{{ ret.sections.C_interest_in_suspense.note }}</p></div>""")
rep("""<h3 class="text-base font-bold">D. Restructured facilities (section 15)</h3>""",
    """<h3 class="text-base font-bold">D. Restructured facilities (paragraph 16(1))</h3>""")
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
