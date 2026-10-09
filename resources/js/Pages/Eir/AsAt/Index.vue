<template>
  <app-layout title="EIR as at a Date" description="The EIR computation for the book, and for any loan, as at any date you name: the amortised cost, the gross carrying amount, the EIR and contractual interest to the date and the difference, which is the revenue shift (spec v4 section 6.11)">
    <template #actions>
      <a v-if="book" :href="route('eir-as-at.export', { date, format: 'xlsx' })" class="primary-btn text-sm">Download Excel</a>
      <a v-if="book" :href="route('eir-as-at.export', { date, format: 'pdf' })" class="secondary-btn text-sm">Download PDF</a>
      <a v-if="book" :href="route('eir-as-at.export', { date, format: 'csv' })" class="secondary-btn text-sm">Download CSV</a>
    </template>

    <div class="space-y-6">
      <form @submit.prevent="view" class="maiic-filterbar flex flex-wrap items-end gap-3">
        <label class="text-xs"><span class="maiic-flabel">View as at</span><input v-model="form.date" type="date" class="maiic-input" :max="lastLedgerDate" required/></label>
        <label class="text-xs"><span class="maiic-flabel">One contract (optional)</span>
          <select v-model="form.contract" class="maiic-select"><option value="">The whole book</option><option v-for="c in contracts" :key="c.id" :value="c.id">{{ c.id }} {{ c.name }}</option></select>
        </label>
        <button type="submit" class="primary-btn text-sm">View</button>
        <div class="flex items-center gap-1 text-xs text-gray-500">Year-ends:
          <button v-for="d in yearEnds" :key="d" type="button" @click="form.date = d; view()" class="secondary-btn text-xs" :disabled="lastLedgerDate && d > lastLedgerDate">{{ d }}</button>
        </div>
        <span class="ml-auto text-xs text-gray-500">Last posting loaded: {{ lastLedgerDate || '-' }}. A later date is refused, never estimated.</span>
      </form>

      <div v-if="error" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-200">{{ error }}</div>

      <template v-if="one">
        <div class="maiic-panel">
          <div class="border-b border-gray-200 p-5 dark:border-slate-700">
            <h3 class="text-base font-bold">Contract {{ one.contract_id }} · {{ one.customer_name }} · as at {{ one.as_at }} <span v-if="one.locked_period" class="maiic-badge maiic-badge-grey ml-2">locked period</span></h3>
            <p class="text-sm text-gray-500">{{ one.product_type }} · GL {{ one.gl_account_code }} · account {{ one.account }} · EIR {{ pct(one.eir.effective_annual) }} solved {{ one.eir.solved_at }}, locked {{ one.eir.locked_at }} · contractual {{ pct(one.eir.contractual_rate) }} {{ one.eir.rate_type }}</p>
          </div>
          <div class="grid grid-cols-1 gap-4 p-5 md:grid-cols-4">
            <div class="maiic-kpi" style="--accent:#7c3aed"><div class="maiic-kpi-label">Amortised cost</div><div class="maiic-kpi-value">{{ fmt(one.amortised_cost) }}</div><div class="text-xs text-gray-500">{{ one.amortised_cost_basis }}</div>
              <div v-if="one.takeon" class="mt-1 text-xs text-gray-500">Take-on loan, basis {{ one.takeon.basis }}: take-on balance {{ fmt(one.takeon.takeon_balance) }}<span v-if="one.takeon.recomputed_amortised_cost != null">, recomputed amortised cost {{ fmt(one.takeon.recomputed_amortised_cost) }} at the EIR {{ pct(one.takeon.recomputed_eir) }} from origination {{ one.takeon.origination_date }}</span></div>
            </div>
            <div class="maiic-kpi" style="--accent:#7c3aed"><div class="maiic-kpi-label">Gross carrying amount</div><div class="maiic-kpi-value">{{ fmt(one.gross_carrying_amount) }}</div><div class="text-xs text-gray-500">{{ one.gross_basis }}</div></div>
            <div class="maiic-kpi" style="--accent:#7c3aed"><div class="maiic-kpi-label">Difference, year to date</div><div class="maiic-kpi-value">{{ fmt(one.interest.difference_year_to_date) }}</div><div class="text-xs text-gray-500">EIR {{ fmt(one.interest.eir_year_to_date) }} less contractual {{ fmt(one.interest.contractual_year_to_date) }}</div></div>
            <div class="maiic-kpi" style="--accent:#7c3aed"><div class="maiic-kpi-label">Difference, cumulative</div><div class="maiic-kpi-value">{{ fmt(one.interest.difference_cumulative) }}</div><div class="text-xs text-gray-500">from {{ one.interest.cumulative_from || '-' }}</div></div>
          </div>
          <div class="grid grid-cols-1 gap-6 p-5 pt-0 lg:grid-cols-2">
            <div>
              <h4 class="maiic-section-title">Roll-forward</h4>
              <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Period</th><th class="num">Opening</th><th class="num">EIR interest</th><th class="num">Cash</th><th class="num">Closing</th><th>Basis</th></tr></thead>
                <tbody><tr v-for="r in one.roll_forward" :key="r.period" :class="r.period === one.period ? 'font-bold' : ''"><td>{{ r.period }}</td><td class="num">{{ fmt(r.opening) }}</td><td class="num">{{ fmt(r.interest) }}</td><td class="num">{{ fmt(r.cash) }}</td><td class="num">{{ fmt(r.closing) }}</td><td class="text-xs">{{ r.basis }}</td></tr>
                <tr v-if="!one.roll_forward.length"><td colspan="6" class="maiic-empty">No roll-forward yet: the revenue run has not reached this contract.</td></tr></tbody></table></div>
            </div>
            <div>
              <h4 class="maiic-section-title">Remaining expected cash flows (schedule v{{ one.eir.schedule_version }})</h4>
              <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Due</th><th class="num">Principal</th><th class="num">Interest</th><th class="num">Fee</th></tr></thead>
                <tbody><tr v-for="(f, i) in one.remaining_cash_flows.slice(0, 24)" :key="i"><td>{{ f.due_date }}</td><td class="num">{{ fmt(f.principal) }}</td><td class="num">{{ fmt(f.interest) }}</td><td class="num">{{ fmt(f.fee) }}</td></tr>
                <tr v-if="one.remaining_cash_flows.length > 24"><td colspan="4" class="text-xs text-gray-500">… {{ one.remaining_cash_flows.length - 24 }} more lines</td></tr></tbody></table></div>
              <h4 class="maiic-section-title">Modifications and solves</h4>
              <p class="text-sm text-gray-600">{{ one.modifications.length }} rate reset(s) to the date; {{ one.eir_history.length }} earlier solve(s). Inputs: {{ one.inputs.postings_read }} postings read, {{ one.inputs.roll_forward_rows }} roll-forward rows, governance values: {{ one.inputs.governance_snapshot }}.</p>
            </div>
          </div>
        </div>
      </template>

      <template v-if="book">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
          <div class="maiic-kpi" style="--accent:#7c3aed"><div class="maiic-kpi-label">Contracts with a locked EIR</div><div class="maiic-kpi-value">{{ book.total.contracts }}</div></div>
          <div class="maiic-kpi" style="--accent:#7c3aed"><div class="maiic-kpi-label">EIR interest, year to {{ book.as_at }}</div><div class="maiic-kpi-value">{{ fmt(book.total.eir_ytd) }}</div></div>
          <div class="maiic-kpi" style="--accent:#7c3aed"><div class="maiic-kpi-label">Contractual interest, year to date</div><div class="maiic-kpi-value">{{ fmt(book.total.contractual_ytd) }}</div></div>
          <div class="maiic-kpi" :style="'--accent:' + (book.total.difference >= 0 ? '#16a34a' : '#dc2626')"><div class="maiic-kpi-label">The revenue shift</div><div class="maiic-kpi-value">{{ fmt(book.total.difference) }}</div></div>
        </div>
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <div class="maiic-panel" v-for="(g, key) in { 'By product': book.by_product, 'By GL': book.by_gl }" :key="key">
            <div class="border-b border-gray-200 p-4 dark:border-slate-700"><h3 class="text-base font-bold">{{ key }}</h3></div>
            <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>{{ key.replace('By ', '') }}</th><th class="num">Contracts</th><th class="num">EIR YTD</th><th class="num">Contractual YTD</th><th class="num">Difference</th><th class="num">Amortised cost</th><th class="num">Gross</th></tr></thead>
              <tbody><tr v-for="r in g" :key="r.key"><td>{{ r.key }}</td><td class="num">{{ r.contracts }}</td><td class="num">{{ fmt(r.eir_ytd) }}</td><td class="num">{{ fmt(r.contractual_ytd) }}</td><td class="num">{{ fmt(r.difference) }}</td><td class="num">{{ fmt(r.amortised_cost) }}</td><td class="num">{{ fmt(r.gross) }}</td></tr></tbody>
              <tfoot><tr><th>Total</th><th class="num">{{ book.total.contracts }}</th><th class="num">{{ fmt(book.total.eir_ytd) }}</th><th class="num">{{ fmt(book.total.contractual_ytd) }}</th><th class="num">{{ fmt(book.total.difference) }}</th><th class="num">{{ fmt(book.total.amortised_cost) }}</th><th class="num">{{ fmt(book.total.gross) }}</th></tr></tfoot></table></div>
          </div>
        </div>
        <div class="maiic-panel">
          <div class="border-b border-gray-200 p-4 dark:border-slate-700"><h3 class="text-base font-bold">By contract</h3><p class="text-sm text-gray-500">Click a contract to see its full computation at this date</p></div>
          <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Contract</th><th>Customer</th><th>Product</th><th>GL</th><th class="num">EIR YTD</th><th class="num">Contractual YTD</th><th class="num">Difference</th><th class="num">Amortised cost</th><th class="num">Gross</th></tr></thead>
            <tbody><tr v-for="l in book.contracts" :key="l.contract_id" class="cursor-pointer" @click="form.contract = l.contract_id; view()"><td class="font-mono text-xs">{{ l.contract_id }}</td><td>{{ l.customer_name }}</td><td class="text-xs">{{ l.product }}</td><td class="text-xs">{{ l.gl }}</td><td class="num">{{ fmt(l.eir_ytd) }}</td><td class="num">{{ fmt(l.contractual_ytd) }}</td><td class="num">{{ fmt(l.difference) }}</td><td class="num">{{ fmt(l.amortised_cost) }}</td><td class="num">{{ fmt(l.gross) }}</td></tr></tbody></table></div>
        </div>
      </template>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'

export default {
  components: { AppLayout },
  props: { date: String, contract: String, lastLedgerDate: String, book: Object, one: Object, error: String, contracts: Array, yearEnds: Array },
  data() {
    return { form: { date: this.date, contract: this.contract || '' } }
  },
  methods: {
    fmt(v) { return v == null ? '-' : Number(v).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
    pct(v) { return v == null ? '-' : (Number(v) * 100).toFixed(4) + '%' },
    view() { router.get(route('eir-as-at.index'), { date: this.form.date, contract: this.form.contract || undefined }, { preserveState: true, preserveScroll: true }) },
  },
}
</script>
