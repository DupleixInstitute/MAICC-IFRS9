<template>
  <app-layout title="Impact Analysis" description="What the forward-looking information does to the ECL: each scenario, the weighted result, by stage and portfolio, against last month, and a proposed set measured before it is approved">
    <template #actions>
      <select v-model="form.period" @change="go" class="maiic-input !w-40" aria-label="Month">
        <option v-for="p in periods" :key="p" :value="p">{{ monthLabel(p) }}</option>
      </select>
    </template>

    <div class="space-y-4">
      <KpiRow :cards="kpis" />

      <div v-if="!snapshot" class="maiic-panel maiic-empty">
        {{ monthLabel(period) }} has not been recorded yet. Record it on the Overview (step 10) to see the ECL under each scenario.
        <Link :href="route('fli.overview', { period })" class="ml-1 font-semibold text-maiic-700 underline dark:text-maiic-300">Open the Overview</Link>
      </div>

      <template v-else>
        <!-- each scenario and the weighted result, one set of rows -->
        <div class="grid gap-4 xl:grid-cols-2">
          <div class="maiic-panel">
            <div class="border-b border-gray-200 px-5 py-3 dark:border-slate-700"><h3 class="font-semibold text-gray-900">ECL under each scenario</h3><p class="text-xs text-gray-500">The weighted figure is the ECL booked: the difference is the rounding of the stored PDs.</p></div>
            <div class="space-y-2 p-5">
              <div v-for="r in snapshot.per_scenario" :key="r.scenario" class="flex items-center gap-3 text-sm">
                <span class="w-24 flex-none font-semibold">{{ r.scenario }}</span>
                <span class="w-12 flex-none text-right text-xs text-gray-500">{{ r.weight }}%</span>
                <div class="h-4 flex-1 overflow-hidden rounded bg-gray-100 dark:bg-slate-800"><div class="h-full rounded" :class="r.is_base ? 'bg-maiic-600' : 'bg-maiicgold-400'" :style="{ width: bar(r.ecl) + '%' }"></div></div>
                <span class="w-32 flex-none text-right tabular-nums">{{ short(r.ecl) }}</span>
              </div>
              <div class="flex items-center gap-3 border-t border-gray-100 pt-2 text-sm font-bold dark:border-slate-700">
                <span class="w-24 flex-none">Weighted</span><span class="w-12 flex-none"></span>
                <div class="h-4 flex-1 overflow-hidden rounded bg-gray-100 dark:bg-slate-800"><div class="h-full rounded bg-sky-600" :style="{ width: bar(snapshot.weighted_ecl) + '%' }"></div></div>
                <span class="w-32 flex-none text-right tabular-nums">{{ short(snapshot.weighted_ecl) }}</span>
              </div>
            </div>
          </div>

          <div class="maiic-panel">
            <div class="border-b border-gray-200 px-5 py-3 dark:border-slate-700"><h3 class="font-semibold text-gray-900">Against {{ prior ? monthLabel(prior.period) : 'the previous month-end' }}</h3></div>
            <table class="maiic-table">
              <thead><tr><th></th><th class="num">{{ prior ? monthLabel(prior.period) : 'Previous' }}</th><th class="num">{{ monthLabel(period) }}</th><th class="num">Movement</th></tr></thead>
              <tbody>
                <tr v-for="l in movementLines" :key="l.label" :class="l.bold ? 'font-semibold' : ''"><td>{{ l.label }}</td><td class="num">{{ prior ? short(l.prior) : '-' }}</td><td class="num">{{ short(l.now) }}</td><td class="num">{{ prior ? short(l.now - l.prior) : '-' }}</td></tr>
              </tbody>
            </table>
            <p v-if="!prior" class="px-5 py-3 text-xs text-gray-500">No earlier month is recorded, so there is no movement to show yet.</p>
          </div>
        </div>

        <!-- by stage and by portfolio -->
        <div class="grid gap-4 2xl:grid-cols-2">
          <div class="maiic-panel">
            <div class="border-b border-gray-200 px-5 py-3 dark:border-slate-700"><h3 class="font-semibold text-gray-900">By stage</h3></div>
            <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Stage</th><th v-for="s in scenarioNames" :key="s" class="num">{{ s }}</th><th class="num">Booked</th></tr></thead>
              <tbody><tr v-for="(v, stage) in snapshot.by_stage" :key="stage"><td>Stage {{ stage }}</td><td v-for="s in scenarioNames" :key="s" class="num">{{ short(v[s]) }}</td><td class="num font-semibold">{{ short(v._booked) }}</td></tr></tbody></table></div>
            <p class="px-5 py-2 text-xs text-gray-500">Stage 3 is measured on the default that has happened, so no scenario moves it.</p>
          </div>
          <div class="maiic-panel">
            <div class="border-b border-gray-200 px-5 py-3 dark:border-slate-700"><h3 class="font-semibold text-gray-900">By portfolio</h3></div>
            <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Portfolio</th><th v-for="s in scenarioNames" :key="s" class="num">{{ s }}</th><th class="num">Booked</th></tr></thead>
              <tbody><tr v-for="p in snapshot.by_portfolio" :key="p.portfolio_id"><td>{{ p.portfolio }}</td><td v-for="s in scenarioNames" :key="s" class="num">{{ short(p.values[s]) }}</td><td class="num font-semibold">{{ short(p.values._booked) }}</td></tr></tbody></table></div>
          </div>
        </div>
      </template>

      <!-- a set measured before approval -->
      <div class="maiic-panel">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-3 dark:border-slate-700">
          <div><h3 class="font-semibold text-gray-900">A scenario set measured before it is approved</h3><p class="text-xs text-gray-500">The ECL each scenario would give under the model in force, against the ECL booked now. Nothing is written.</p></div>
          <select v-if="sets.length" v-model="form.set" @change="go" class="maiic-input !w-72" aria-label="Scenario set">
            <option v-for="s in sets" :key="s.id" :value="s.id">{{ s.name }}, v{{ s.version }} ({{ s.status }})</option>
          </select>
        </div>
        <p v-if="whatIfError" class="px-5 py-3 text-sm text-red-700">{{ whatIfError }}</p>
        <p v-else-if="!whatIf" class="maiic-empty">No scenario set for {{ monthLabel(period) }}.</p>
        <div v-else class="maiic-table-wrap">
          <table class="maiic-table">
            <thead><tr><th>Scenario</th><th class="num">Weight</th><th class="num">Driver</th><th class="num">Adjustment to the PD</th><th class="num">ECL</th><th class="num">Weighted part</th></tr></thead>
            <tbody>
              <tr v-for="r in whatIf.scenarios" :key="r.scenario"><td>{{ r.scenario }}</td><td class="num">{{ r.weight }}%</td><td class="num">{{ r.driver }}</td><td class="num">{{ (r.adjustment * 100).toFixed(2) }}%</td><td class="num">{{ mwk(r.ecl) }}</td><td class="num">{{ mwk(r.contribution) }}</td></tr>
              <tr class="font-semibold"><td colspan="5">Weighted ECL under {{ whatIf.set.name }}, v{{ whatIf.set.version }}</td><td class="num">{{ mwk(whatIf.weighted) }}</td></tr>
              <tr><td colspan="5">ECL booked now</td><td class="num">{{ mwk(whatIf.booked) }}</td></tr>
              <tr :class="Math.abs(whatIf.difference) > 1 ? 'text-amber-800 font-semibold' : ''"><td colspan="5">Change if this set were applied</td><td class="num">{{ mwk(whatIf.difference) }}</td></tr>
            </tbody>
          </table>
          <p class="px-5 py-2 text-xs text-gray-500">{{ whatIf.note }} Model version {{ whatIf.model_version }}; {{ whatIf.loans }} loans.</p>
        </div>
      </div>

      <!-- drill to the loan -->
      <div class="maiic-panel">
        <div class="border-b border-gray-200 px-5 py-3 dark:border-slate-700"><h3 class="font-semibold text-gray-900">The loans the forward-looking information moved most</h3><p class="text-xs text-gray-500">{{ loans.length }} loans, largest effect first.</p></div>
        <div class="maiic-table-wrap"><table class="maiic-table">
          <thead><tr><th>Contract</th><th>Customer</th><th>Portfolio</th><th class="num">Stage</th><th class="num">PD before</th><th class="num">PD after</th><th class="num">ECL before FLI</th><th class="num">ECL booked</th><th class="num">Effect</th></tr></thead>
          <tbody>
            <tr v-for="l in pagedLoans" :key="l.id"><td class="font-mono text-xs">{{ l.contract_id }}</td><td class="max-w-[14rem] truncate" :title="l.customer">{{ l.customer }}</td><td>{{ l.portfolio }}</td><td class="num">{{ l.stage }}</td><td class="num">{{ pct(l.pd_pre) }}</td><td class="num">{{ pct(l.pd_post) }}</td><td class="num">{{ num(l.ecl_pre) }}</td><td class="num">{{ num(l.ecl) }}</td><td class="num font-semibold" :class="l.fli_effect < 0 ? 'text-maiic-700' : 'text-red-700'">{{ num(l.fli_effect) }}</td></tr>
            <tr v-if="!loans.length"><td colspan="9" class="maiic-empty">No loan has an ECL before and after FLI for this month.</td></tr>
          </tbody></table></div>
        <RowPager v-model="page" :total="loans.length" class="border-t border-gray-100" />
      </div>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import KpiRow from '@/Components/Maiic/KpiRow.vue'
import RowPager from '@/Components/Maiic/RowPager.vue'
import { Link, router } from '@inertiajs/vue3'

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

export default {
  components: { AppLayout, KpiRow, RowPager, Link },
  props: { period: String, periods: Array, snapshot: Object, prior: Object, portfolios: Object, sets: Array, compareId: Number, whatIf: Object, whatIfError: String, loans: Array },
  data() { return { form: { period: this.period, set: this.compareId }, page: 1 } },
  computed: {
    scenarioNames() { return (this.snapshot?.per_scenario || []).map(r => r.scenario) },
    maxEcl() { return Math.max(1, ...(this.snapshot?.per_scenario || []).map(r => Number(r.ecl)), Number(this.snapshot?.weighted_ecl || 0)) },
    pagedLoans() { return this.loans.slice((this.page - 1) * 15, this.page * 15) },
    movementLines() {
      const s = this.snapshot, p = this.prior || {}
      if (!s) return []
      return [
        { label: 'ECL before FLI', now: Number(s.pre_fli_ecl), prior: p.pre_fli },
        { label: 'Forward-looking effect', now: Number(s.model_effect), prior: p.model_effect },
        { label: 'Manual overlays', now: Number(s.overlay_effect), prior: p.overlay_effect },
        { label: 'ECL booked', now: Number(s.booked_ecl), prior: p.booked, bold: true },
      ]
    },
    kpis() {
      const s = this.snapshot
      if (!s) return [{ label: 'Month', value: this.monthLabel(this.period), raw: true }]
      const fli = Number(s.model_effect) + Number(s.overlay_effect)
      return [
        { label: 'ECL before FLI', value: this.short(s.pre_fli_ecl), raw: true, accent: '#64748b' },
        { label: 'Forward-looking effect', value: this.short(fli), raw: true, accent: fli < 0 ? '#15803d' : '#dc2626', sub: (fli / Number(s.pre_fli_ecl) * 100).toFixed(1) + '% of the ECL before FLI' },
        { label: 'ECL booked', value: this.short(s.booked_ecl), raw: true, accent: '#d97706' },
        { label: 'Weighted over the scenarios', value: this.short(s.weighted_ecl), raw: true, accent: '#0284c7', sub: s.reconciles ? 'ties to the booked ECL' : 'does not tie' },
        { label: 'Status', value: s.status === 'SIGNED_OFF' ? 'Signed off' : 'Recorded', raw: true, accent: s.status === 'SIGNED_OFF' ? '#15803d' : '#d97706' },
      ]
    },
  },
  methods: {
    monthLabel(p) { if (!p) return '-'; const [y, m] = String(p).split('-'); return m ? `${MONTHS[Number(m) - 1]} ${y}` : p },
    go() { router.get(route('fli.impact'), { period: this.form.period, set: this.form.period === this.period ? this.form.set : undefined }) },
    bar(v) { return Math.max(1, Number(v) / this.maxEcl * 100) },
    mwk(v) { return v === null || v === undefined ? '-' : 'MWK ' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
    num(v) { return v === null || v === undefined ? '-' : Number(v).toLocaleString(undefined, { maximumFractionDigits: 0 }) },
    pct(v) { return v === null || v === undefined ? '-' : (Number(v) * 100).toFixed(2) + '%' },
    short(v) { if (v === null || v === undefined || isNaN(v)) return '-'; const n = Number(v); const a = Math.abs(n); const s = a >= 1e9 ? (a / 1e9).toFixed(2) + 'bn' : a >= 1e6 ? (a / 1e6).toFixed(1) + 'm' : Math.round(a).toLocaleString(); return (n < 0 ? '(' : '') + 'MWK ' + s + (n < 0 ? ')' : '') },
  },
}
</script>
