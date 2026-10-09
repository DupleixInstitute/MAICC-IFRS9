<template>
  <app-layout title="Mega Farm Programme" description="Government money MAIIC manages on its behalf: the programme's loans, their staging and PD, and MAIIC's share of the ECL">
    <template #actions>
      <select v-if="periods.length" v-model="form.period" class="maiic-select w-36" aria-label="Period" title="Period" @change="go">
        <option v-for="p in periods" :key="p" :value="p">{{ p }}</option>
      </select>
      <button v-if="canGovern && period" type="button" class="inline-flex items-center gap-2 rounded-lg bg-maiic-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-maiic-700" @click="run">
        <font-awesome-icon icon="calculator"/> Run programme ECL
      </button>
    </template>

    <div class="space-y-4">
      <!-- One line: settings in force and whether they are approved -->
      <p class="flex flex-wrap items-center gap-2 text-xs text-gray-600">
        <span class="maiic-badge" :class="confirmed ? 'maiic-badge-green' : 'maiic-badge-gold'">{{ confirmed ? 'Settings approved' : 'Settings not yet approved' }}</span>
        <span>Scope: <b>{{ settings.mega_farms_scope || '-' }}</b></span><span class="text-gray-300">|</span>
        <span>PD method: <b>{{ settings.megafarm_pd_method || '-' }}</b></span><span class="text-gray-300">|</span>
        <span>Scalar ceiling: <b>{{ settings.megafarm_scalar_ceiling || '-' }}</b></span>
        <span v-if="!confirmed" class="text-amber-700">Runs use the seeded settings and are system figures, not a MAIIC approval.</span>
        <Link :href="route('eir-governance.index')" class="font-semibold text-maiic-700 hover:underline">Change in the Governance Centre</Link>
      </p>

      <div v-if="!periods.length" class="maiic-panel px-6 py-10 text-center">
        <div class="text-base font-bold text-gray-800">No Mega Farm loans in any loan book yet</div>
        <p class="mx-auto mt-1 max-w-lg text-sm text-gray-500">The programme's loans arrive with the monthly E-Banker extracts. Once a month is loaded, its loans, staging and ECL appear here.</p>
      </div>

      <template v-else>
        <div v-if="period" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
          <div class="maiic-kpi" style="--accent:#15803d"><div class="maiic-kpi-label">Loans in {{ period }}</div><div class="maiic-kpi-value text-xl">{{ count(totals.loans) }}</div></div>
          <div class="maiic-kpi" style="--accent:#0e7490"><div class="maiic-kpi-label">Gross carrying amount (MWK)</div><div class="maiic-kpi-value text-xl" :title="fmt(totals.carrying)">{{ compact(totals.carrying) }}</div></div>
          <div class="maiic-kpi" style="--accent:#dc2626"><div class="maiic-kpi-label">Stage 3 ({{ count(totals.stage3Loans) }} loans)</div><div class="maiic-kpi-value text-xl" :title="fmt(totals.stage3)">{{ compact(totals.stage3) }}</div></div>
          <div class="maiic-kpi" style="--accent:#d97706"><div class="maiic-kpi-label">MAIIC's ECL, last run</div><div class="maiic-kpi-value text-xl" :title="fmt(totals.ecl)">{{ compact(totals.ecl) }}</div></div>
        </div>

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
          <div class="border-b border-gray-200 px-5 pt-4">
            <nav class="flex gap-6 overflow-x-auto">
              <button v-for="t in tabs" :key="t.key" type="button" class="whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-semibold"
                      :class="tab === t.key ? 'border-maiic-600 text-maiic-700' : 'border-transparent text-gray-500 hover:text-gray-800'" @click="tab = t.key">
                {{ t.label }}
                <span class="ml-1 rounded-full px-2 py-0.5 text-xs" :class="tab === t.key ? 'bg-maiic-100 text-maiic-800' : 'bg-gray-100 text-gray-600'">{{ t.count }}</span>
              </button>
            </nav>
          </div>

          <div v-if="tab === 'scheme'" class="maiic-table-wrap">
            <table class="maiic-table">
              <thead><tr><th>Scheme</th><th>GL</th><th class="num">Loans</th><th class="num">Carrying (MWK)</th><th class="num">Stage 3 (MWK)</th><th class="num">Average PD</th><th class="num">MAIIC ECL (MWK)</th></tr></thead>
              <tbody>
                <tr v-for="b in book" :key="b.product_code">
                  <td class="font-semibold">{{ b.product_group }}</td><td class="font-mono text-xs">{{ b.product_code }}</td>
                  <td class="num">{{ count(b.loans) }}</td><td class="num">{{ fmt(b.carrying) }}</td><td class="num">{{ fmt(b.stage3_carrying) }}</td>
                  <td class="num">{{ b.pd != null ? (Number(b.pd) * 100).toFixed(2) + '%' : '-' }}</td><td class="num font-semibold">{{ fmt(b.ecl) }}</td>
                </tr>
                <tr v-if="book.length" class="total">
                  <td colspan="2">Total</td><td class="num">{{ count(totals.loans) }}</td><td class="num">{{ fmt(totals.carrying) }}</td><td class="num">{{ fmt(totals.stage3) }}</td><td class="num"></td><td class="num">{{ fmt(totals.ecl) }}</td>
                </tr>
                <tr v-if="!book.length"><td colspan="7" class="maiic-empty">No programme loans in {{ period }}.</td></tr>
              </tbody>
            </table>
          </div>

          <div v-else-if="tab === 'stage'" class="maiic-table-wrap">
            <table class="maiic-table">
              <thead><tr><th>Stage</th><th class="num">Loans</th><th class="num">Carrying (MWK)</th><th class="num">MAIIC ECL (MWK)</th><th class="num">Coverage</th></tr></thead>
              <tbody>
                <tr v-for="s in byStage" :key="s.stage">
                  <td><span class="maiic-badge" :class="String(s.stage) === '3' ? 'maiic-badge-red' : String(s.stage) === '2' ? 'maiic-badge-gold' : s.stage ? 'maiic-badge-green' : 'maiic-badge-grey'">{{ s.stage ? 'Stage ' + s.stage : 'Not staged' }}</span></td>
                  <td class="num">{{ count(s.loans) }}</td><td class="num">{{ fmt(s.carrying) }}</td><td class="num font-semibold">{{ fmt(s.ecl) }}</td>
                  <td class="num">{{ Number(s.carrying) ? (Number(s.ecl || 0) / Number(s.carrying) * 100).toFixed(2) + '%' : '-' }}</td>
                </tr>
                <tr v-if="!byStage.length"><td colspan="5" class="maiic-empty">No programme loans in {{ period }}.</td></tr>
              </tbody>
            </table>
          </div>

          <div v-else>
            <p class="border-b border-gray-200 px-4 py-2 text-xs text-gray-500">Each run records its scope, method, scalar, LGD and share, or why it was declined. Nothing is filled in when a method's conditions are not met.</p>
            <div class="maiic-table-wrap">
              <table class="maiic-table">
                <thead><tr><th class="num">Run</th><th>Period</th><th>Scope</th><th>Method</th><th class="num">Loans</th><th class="num">Gross (MWK)</th><th class="num">Programme ECL</th><th class="num">Share</th><th class="num">MAIIC ECL</th><th>Basis</th><th>Run by</th></tr></thead>
                <tbody>
                  <tr v-for="r in pagedRuns" :key="r.id" class="align-top">
                    <td class="num">{{ r.id }}</td><td class="whitespace-nowrap">{{ r.reporting_period }}</td><td class="text-xs">{{ r.scope }}</td><td class="text-xs">{{ r.method }}</td>
                    <td class="num">{{ count(r.loans) }}</td><td class="num">{{ fmt(r.gross) }}</td><td class="num">{{ fmt(r.programme_ecl) }}</td>
                    <td class="num">{{ (Number(r.share) * 100).toFixed(0) }}%</td><td class="num font-semibold">{{ fmt(r.maiic_ecl) }}</td>
                    <td class="text-xs">
                      <span v-if="r.declined" class="maiic-badge maiic-badge-red">Declined</span>
                      <span v-if="r.declined" class="ml-1 text-red-700">{{ r.declined }}</span>
                      <template v-else>
                        <div>PD: {{ pdText(r.basis.pd_by_scenario || r.basis.pd_by_stage) }}</div>
                        <div>Scalar {{ r.basis.scalar ?? '-' }}<span v-if="r.basis.basis"> (measured {{ r.basis.basis.measured_scalar ?? '-' }}, ceiling {{ r.basis.basis.ceiling ?? '-' }})</span>, LGD {{ r.basis.lgd != null ? (Number(r.basis.lgd) * 100).toFixed(2) + '%' : '-' }}</div>
                      </template>
                      <div v-if="r.approver_label" class="text-gray-500">{{ r.approver_label }}</div>
                    </td>
                    <td class="whitespace-nowrap text-xs">{{ r.run_by_name || 'System' }}<br><span class="text-gray-500">{{ (r.created_at || '').slice(0, 16) }}</span></td>
                  </tr>
                  <tr v-if="!runs.length"><td colspan="11" class="maiic-empty">No run yet. Use Run programme ECL to calculate the selected month.</td></tr>
                </tbody>
              </table>
            </div>
            <div v-if="runs.length > 15" class="border-t border-gray-100 p-4"><ClientPager v-model="runPage" :total="runs.length"/></div>
          </div>
        </div>
      </template>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import ClientPager from '@/Components/ClientPager.vue'
import { confirmDialog } from '@/Components/confirmDialog'
import { Link, router } from '@inertiajs/vue3'

export default {
  components: { AppLayout, ClientPager, Link },
  props: { period: String, periods: Array, book: Array, byStage: Array, settings: Object, runs: Array, canGovern: Boolean, confirmed: Boolean },
  data() { return { form: { period: this.period }, tab: 'scheme', runPage: 1 } },
  computed: {
    totals() {
      const sum = k => this.book.reduce((a, b) => a + Number(b[k] || 0), 0)
      return { loans: sum('loans'), carrying: sum('carrying'), stage3: sum('stage3_carrying'), stage3Loans: sum('stage3_loans'), ecl: sum('ecl') }
    },
    tabs() {
      return [
        { key: 'scheme', label: 'By scheme', count: this.book.length },
        { key: 'stage', label: 'By stage', count: this.byStage.length },
        { key: 'runs', label: 'Runs', count: this.runs.length },
      ]
    },
    pagedRuns() { return this.runs.slice((this.runPage - 1) * 15, this.runPage * 15) },
  },
  methods: {
    fmt(v) { return v == null ? '-' : Number(v).toLocaleString('en-GB', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) },
    count(v) { return Number(v || 0).toLocaleString('en-GB') },
    compact(v) {
      const n = Number(v || 0), a = Math.abs(n)
      if (a >= 1e9) return (n / 1e9).toFixed(2) + ' bn'
      if (a >= 1e6) return (n / 1e6).toFixed(2) + ' m'
      return this.fmt(n)
    },
    // {"1":0.9456,"2":0,"3":1} -> "Stage 1 94.56%, Stage 2 0.00%, Stage 3 100.00%"
    pdText(o) {
      if (!o || typeof o !== 'object') return '-'
      return Object.entries(o).map(([k, v]) => (/^\d$/.test(k) ? 'Stage ' + k : k) + ' ' + (Number(v) * 100).toFixed(2) + '%').join(', ')
    },
    go() { router.get(route('megafarm.index'), { period: this.form.period }) },
    async run() {
      if (!(await confirmDialog({
        title: 'Run the Mega Farm ECL for ' + this.form.period + '?',
        message: 'The programme ECL is calculated under the settings in force and saved as a new run.',
        confirmLabel: 'Run',
      }))) return
      router.post(route('megafarm.run'), { period: this.form.period }, { preserveScroll: true })
    },
  },
}
</script>
