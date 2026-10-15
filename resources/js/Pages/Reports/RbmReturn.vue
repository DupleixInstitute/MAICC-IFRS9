<template>
  <app-layout title="RBM return (provisional)">
    <template #header>
      <BackToReports tab="regulatory"/>
      <h2 class="text-xl font-semibold leading-tight text-gray-800">RBM return (provisional)</h2>
      <p class="mt-0.5 text-sm text-gray-500">The classification and provisioning return of the RBM Credit Risk Management for DFIs Directive, 2018 (paragraphs 10 to 16), in the order of the directive; the loan loss reserve is reported on the call report the Registrar prescribes (paragraph 15(3)).</p>
    </template>
    <template #actions>
      <select v-model="form.period" @change="go" class="maiic-select w-36" aria-label="Period"><option v-for="p in periods" :key="p" :value="p">{{ p }}</option></select>
      <label class="flex items-center gap-1.5 text-sm text-gray-700"><input v-model="form.mega_farm" type="checkbox" @change="go" class="rounded border-gray-300 text-maiic-600"/> Include Mega Farm</label>
      <DownloadButton format="CSV" :href="ret ? route('rbm-return.export', { period, mega_farm: megaFarm ? 1 : 0 }) : ''" :disabled="!ret"/>
      <DownloadButton format="Excel" :href="ret ? route('rbm-return.export', { period, mega_farm: megaFarm ? 1 : 0, format: 'xlsx' }) : ''" :disabled="!ret"/>
      <DownloadButton format="PDF" label="Download PDF" :href="ret ? route('rbm-return.export', { period, mega_farm: megaFarm ? 1 : 0, format: 'pdf' }) : ''" :disabled="!ret"/>
    </template>
    <div class="space-y-5">
      <Notice v-if="error" id="error" tone="danger">{{ error }}</Notice>
      <template v-if="ret">
        <KpiRow :items="[
          { label: 'Facilities', value: ret.loans },
          { label: 'Total balance', value: fmt(ret.total_balance) },
          { label: 'NPL ratio (substandard and below)', value: ret.npl_ratio != null ? (ret.npl_ratio * 100).toFixed(2) + '%' : '-', tone: 'rose' },
          { label: 'Provision: the higher of the two', value: fmt(ret.sections.B_totals.higher_of_the_two), sub: 'minimum ' + fmt(ret.sections.B_totals.minimum_provision) + ', IFRS 9 ' + fmt(ret.sections.B_totals.ifrs9_allowance), tone: 'amber' },
        ]"/>
        <details v-if="ret.notes && ret.notes.length" class="text-sm text-gray-600">
          <summary class="cursor-pointer select-none text-xs font-semibold text-maiic-700">How this return is laid out</summary>
          <p v-for="(n, i) in ret.notes" :key="i" class="mt-1 text-xs">{{ n }}</p>
        </details>
        <div class="maiic-panel"><div class="border-b border-gray-200 p-4 dark:border-slate-700"><h3 class="text-base font-bold">A. Classification of facilities (paragraphs 10 and 11)</h3><p class="text-xs text-gray-500">Short-term: 12 months or less (standard to 30 days; special mention to 90; substandard to 180; doubtful to 365; loss beyond). Medium and long term: standard to 90; special mention to 180; substandard to 365; doubtful to 746; loss beyond (paragraph 10 and the Schedule). Facilities are reviewed for classification every quarter (paragraph 11).</p></div>
          <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Class</th><th class="num">Short-term accounts</th><th class="num">Short-term balance</th><th class="num">Medium/long accounts</th><th class="num">Medium/long balance</th><th class="num">Total accounts</th><th class="num">Total balance</th><th class="num">IFRS 9 allowance</th></tr></thead>
            <tbody><tr v-for="a in ret.sections.A_classification" :key="a.class"><td>{{ a.class }}</td><td class="num">{{ a.short_term.accounts }}</td><td class="num">{{ fmt(a.short_term.balance) }}</td><td class="num">{{ a.medium_long_term.accounts }}</td><td class="num">{{ fmt(a.medium_long_term.balance) }}</td><td class="num">{{ a.total.accounts }}</td><td class="num">{{ fmt(a.total.balance) }}</td><td class="num">{{ fmt(a.total.ifrs9_allowance) }}</td></tr></tbody>
            <tfoot><tr><th>Total</th><th></th><th></th><th></th><th></th><th class="num">{{ ret.loans }}</th><th class="num">{{ fmt(ret.total_balance) }}</th><th class="num">{{ fmt(ret.sections.B_totals.ifrs9_allowance) }}</th></tr></tfoot></table></div></div>
        <div class="maiic-panel"><div class="border-b border-gray-200 p-4 dark:border-slate-700"><h3 class="text-base font-bold">B. Provisions (paragraph 14 and the Schedule; loan loss reserve, paragraph 15)</h3>
          <p v-if="ret.sections.B_allowance_basis" class="mt-1 text-xs" :class="ret.sections.B_allowance_basis.status === 'mixed' ? 'text-amber-800' : 'text-gray-500'">IFRS 9 allowance: {{ ret.sections.B_allowance_basis.message }}</p></div>
          <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Class</th><th class="num">Balance</th><th class="num">Minimum rate</th><th class="num">Minimum provision</th><th class="num">IFRS 9 allowance</th><th class="num">Shortfall</th></tr></thead>
            <tbody><tr v-for="b in ret.sections.B_provisions" :key="b.class"><td>{{ b.class }}</td><td class="num">{{ fmt(b.balance) }}</td><td class="num">{{ (b.minimum_rate * 100).toFixed(0) }}%</td><td class="num">{{ fmt(b.minimum_provision) }}</td><td class="num">{{ fmt(b.ifrs9_allowance) }}</td><td class="num" :class="b.shortfall > 0 ? 'text-red-700' : ''">{{ fmt(b.shortfall) }}</td></tr></tbody>
            <tfoot><tr><th>Totals</th><th></th><th></th><th class="num">{{ fmt(ret.sections.B_totals.minimum_provision) }}</th><th class="num">{{ fmt(ret.sections.B_totals.ifrs9_allowance) }}</th><th class="num">higher of the two: {{ fmt(ret.sections.B_totals.higher_of_the_two) }}</th></tr>
              <tr v-if="ret.sections.B_totals.loan_loss_reserve != null"><th colspan="5">Loan loss reserve: the minimum provision over the IFRS 9 allowance, an appropriation of retained earnings (paragraph 15(1))</th><th class="num">{{ fmt(ret.sections.B_totals.loan_loss_reserve) }}</th></tr></tfoot></table></div></div>
        <div v-if="ret.sections.B_loss_write_offs" class="maiic-panel">
          <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 p-4 dark:border-slate-700">
            <div><h3 class="text-base font-bold">Loss facilities to be written off (paragraph 12)</h3><p class="max-w-4xl text-xs text-gray-500">{{ ret.sections.B_loss_write_offs.note }}</p></div>
            <span class="maiic-badge" :class="ret.sections.B_loss_write_offs.overdue ? 'maiic-badge-red' : 'maiic-badge-green'">{{ ret.sections.B_loss_write_offs.overdue }} of {{ ret.sections.B_loss_write_offs.facilities }} past the write-off quarter ({{ fmt(ret.sections.B_loss_write_offs.overdue_balance) }})</span>
          </div>
          <div v-if="ret.sections.B_loss_write_offs.rows.length" class="maiic-table-wrap"><table class="maiic-table maiic-table-compact"><thead><tr><th>Contract</th><th>Customer</th><th>Term</th><th class="num">Days past due</th><th>Loss since</th><th class="num">Months loss</th><th>Write off by</th><th class="num">Balance</th><th class="num">IFRS 9 allowance</th><th>Status</th></tr></thead>
            <tbody><tr v-for="l in lossRows" :key="l.contract_id"><td>{{ l.contract_id }}</td><td>{{ l.customer_name || '-' }}</td><td>{{ l.term === 'short' ? 'Short' : 'Medium/long' }}</td><td class="num">{{ l.days_past_due }}</td><td class="whitespace-nowrap">{{ l.loss_since_open ? 'at or before ' : '' }}{{ l.loss_since }}</td><td class="num">{{ l.months_classified_loss }}</td><td class="whitespace-nowrap">{{ l.write_off_due_by }}</td><td class="num">{{ fmt(l.balance) }}</td><td class="num">{{ fmt(l.ifrs9_allowance) }}</td><td><span class="maiic-badge" :class="l.status === 'overdue' ? 'maiic-badge-red' : 'maiic-badge-gold'">{{ l.status }}</span></td></tr></tbody>
            <tfoot><tr><th colspan="7">Total</th><th class="num">{{ fmt(ret.sections.B_loss_write_offs.balance) }}</th><th></th><th></th></tr></tfoot></table>
            <RowPager v-model="lossPage" :total="ret.sections.B_loss_write_offs.rows.length" class="border-t border-gray-100" /></div>
          <p v-else class="p-4 text-sm text-gray-500">No facility is classified loss at this month-end.</p>
        </div>
        <div class="maiic-panel p-4 text-sm"><h3 class="text-base font-bold">C. Interest in suspense (paragraph 13)</h3>
          <template v-for="(v, k) in ret.sections.C_interest_in_suspense" :key="k"><div v-if="k !== 'note' && k !== 'gl_1320_source'" class="flex justify-between gap-3 border-b border-gray-100 py-1 dark:border-slate-700"><span class="text-gray-500">{{ k.replace(/_/g, ' ') }}</span><span class="num">{{ v == null ? 'not held' : (typeof v === 'number' && Math.abs(v) >= 1000 ? fmt(v) : v) }}</span></div></template>
          <p v-if="ret.sections.C_interest_in_suspense.note" class="mt-2 text-xs text-gray-500">{{ ret.sections.C_interest_in_suspense.note }}</p></div>
        <div class="maiic-panel">
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
            <tbody><tr v-for="(f, i) in dRows" :key="f.contract_id + '-' + ((dPage - 1) * 15 + i)"><td>{{ f.contract_id }}</td><td>{{ f.customer_name || '-' }}</td><td class="whitespace-nowrap">{{ f.event_date }}<span v-if="f.in_period" class="maiic-badge maiic-badge-gold ml-1">this month</span></td><td>{{ words(f.classification) }}</td><td>{{ f.treatment ? words(f.treatment) : 'none booked: before the take-on, or not yet applied by the revenue run' }}</td>
              <td><span v-if="f.board_approval_ref" class="whitespace-nowrap">{{ f.board_approval_ref }}<span v-if="f.board_approval_date" class="text-gray-500"> ({{ f.board_approval_date }})</span></span><span v-else class="maiic-badge maiic-badge-red">none recorded</span></td><td>{{ f.class }}</td><td class="num">{{ fmt(f.balance) }}</td></tr></tbody>
            <tfoot><tr><th colspan="7">Total ({{ D.accounts }} facilit{{ D.accounts === 1 ? 'y' : 'ies' }}; a facility restructured twice is listed twice and counted once)</th><th class="num">{{ fmt(D.balance) }}</th></tr></tfoot></table>
            <RowPager v-model="dPage" :total="D.facilities.length" class="border-t border-gray-100" /></div>
          <p v-else-if="D.accounts != null" class="px-4 pb-4 text-sm text-gray-500">No facility in this return has a restructure confirmed on the register by the month-end.</p>
        </div>
        <div class="maiic-panel p-4 text-sm"><h3 class="text-base font-bold">E. Security against classified facilities</h3>
          <template v-for="(v, k) in ret.sections.E_security" :key="k"><div v-if="k !== 'note'" class="flex justify-between border-b border-gray-100 py-1 dark:border-slate-700"><span class="text-gray-500">{{ k.replace(/_/g, ' ') }}</span><span class="num">{{ typeof v === 'number' && Math.abs(v) >= 1000 ? fmt(v) : v }}</span></div></template>
          <p class="mt-2 text-xs text-gray-500">{{ ret.sections.E_security.note }}</p></div>
      </template>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import BackToReports from './Partials/BackToReports.vue'
import DownloadButton from './Partials/DownloadButton.vue'
import KpiRow from './Partials/KpiRow.vue'
import Notice from '@/Components/Maiic/Notice.vue'
import RowPager from '@/Components/Maiic/RowPager.vue'

export default {
  components: { AppLayout, BackToReports, DownloadButton, KpiRow, Notice, RowPager },
  props: { period: String, return: Object, error: String, megaFarm: Boolean, periods: Array },
  // the two facility lists show 15 rows a page; a new period or Mega Farm choice starts at page 1
  data() { return { form: { period: this.period, mega_farm: this.megaFarm }, lossPage: 1, dPage: 1 } },
  computed: {
    ret() { return this.return },
    D() { return (this.return && this.return.sections.D_restructured) || {} },
    lossRows() { const rows = (this.return && this.return.sections.B_loss_write_offs && this.return.sections.B_loss_write_offs.rows) || []; return rows.slice((this.lossPage - 1) * 15, this.lossPage * 15) },
    dRows() { return (this.D.facilities || []).slice((this.dPage - 1) * 15, this.dPage * 15) },
  },
  watch: { return() { this.lossPage = 1; this.dPage = 1 } },
  methods: {
    fmt(v) { return v == null ? '-' : Number(v).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
    words(v) { const t = String(v || '').toLowerCase().replace(/_/g, ' '); return t.charAt(0).toUpperCase() + t.slice(1) },
    go() { router.get(route('rbm-return.index'), { period: this.form.period, mega_farm: this.form.mega_farm ? 1 : 0 }) },
  },
}
</script>
