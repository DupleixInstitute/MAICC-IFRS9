p = r'c:\xampp\htdocs\MAICC-IFRS9\resources\js\Pages\Reports\RbmReturn.vue'
s = open(p, encoding='utf-8').read()


def rep(old, new):
    global s
    assert s.count(old) == 1, old[:80]
    s = s.replace(old, new, 1)


rep("""        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
          <div class="maiic-panel p-4 text-sm"><h3 class="text-base font-bold">C. Interest in suspense (paragraph 13)</h3>""",
    """        <div class="maiic-panel">
          <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 p-4 dark:border-slate-700">
            <div><h3 class="text-base font-bold">D. Restructured facilities (paragraph 16(1))</h3><p class="max-w-4xl text-xs text-gray-500">{{ D.note }}</p></div>
            <span v-if="D.without_board_approval" class="maiic-badge maiic-badge-red">{{ D.without_board_approval }} without a Board approval recorded</span>
          </div>
          <div v-if="D.accounts != null" class="p-4">
            <KpiRow :items="[
              { label: 'Facilities restructured', value: D.accounts, sub: 'confirmed on the register by the month-end' },
              { label: 'Their balance', value: fmt(D.balance) },
              { label: 'Restructured in the month', value: D.restructured_in_period },
              { label: 'Without Board approval', value: D.without_board_approval, tone: D.without_board_approval ? 'rose' : 'maiic', sub: 'paragraph 16(1) requires one' },
              { label: 'Candidates awaiting confirmation', value: D.candidates_awaiting_confirmation, tone: D.candidates_awaiting_confirmation ? 'amber' : 'grey', sub: 'not reported as restructured' },
            ]"/>
          </div>
          <div v-if="D.facilities && D.facilities.length" class="maiic-table-wrap"><table class="maiic-table maiic-table-compact"><thead><tr><th>Contract</th><th>Customer</th><th>Restructured on</th><th>Classification</th><th>Treatment</th><th>Board approval</th><th>Class</th><th class="num">Balance</th></tr></thead>
            <tbody><tr v-for="(f, i) in D.facilities" :key="f.contract_id + '-' + i"><td>{{ f.contract_id }}</td><td>{{ f.customer_name || '-' }}</td><td class="whitespace-nowrap">{{ f.event_date }}<span v-if="f.in_period" class="maiic-badge maiic-badge-gold ml-1">this month</span></td><td>{{ words(f.classification) }}</td><td>{{ f.treatment ? words(f.treatment) : 'not booked (before the take-on, or not yet run)' }}</td>
              <td><span v-if="f.board_approval_ref" class="whitespace-nowrap">{{ f.board_approval_ref }}<span v-if="f.board_approval_date" class="text-gray-500"> ({{ f.board_approval_date }})</span></span><span v-else class="maiic-badge maiic-badge-red">none recorded</span></td><td>{{ f.class }}</td><td class="num">{{ fmt(f.balance) }}</td></tr></tbody>
            <tfoot><tr><th colspan="7">Total ({{ D.accounts }} facilit{{ D.accounts === 1 ? 'y' : 'ies' }}; a facility restructured twice is listed twice and counted once)</th><th class="num">{{ fmt(D.balance) }}</th></tr></tfoot></table></div>
          <p v-else-if="D.accounts != null" class="px-4 pb-4 text-sm text-gray-500">No facility in this return has a restructure confirmed on the register by the month-end.</p>
        </div>
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <div class="maiic-panel p-4 text-sm"><h3 class="text-base font-bold">C. Interest in suspense (paragraph 13)</h3>""")
rep("""          <div class="maiic-panel p-4 text-sm"><h3 class="text-base font-bold">D. Restructured facilities (paragraph 16(1))</h3><p class="text-gray-500">{{ ret.sections.D_restructured.note }}</p></div>
""", "")
rep("""            <template v-for="(v, k) in ret.sections.E_security" :key="k"><div v-if="k !== 'note'" class="flex justify-between border-b border-gray-100 py-1">""",
    """            <template v-for="(v, k) in ret.sections.E_security" :key="k"><div v-if="k !== 'note'" class="flex justify-between border-b border-gray-100 py-1 dark:border-slate-700">""")
rep("""  computed: { ret() { return this.return } },""", """  computed: { ret() { return this.return }, D() { return (this.return && this.return.sections.D_restructured) || {} } },""")
rep("""    fmt(v) { return v == null ? '-' : Number(v).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },""",
    """    fmt(v) { return v == null ? '-' : Number(v).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
    words(v) { const t = String(v || '').toLowerCase().replace(/_/g, ' '); return t.charAt(0).toUpperCase() + t.slice(1) },""")
open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok')
