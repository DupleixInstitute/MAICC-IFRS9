<template>
  <app-layout title="Adjusted Forecast" description="The credit-loss measure and the PD the model in force forecasts under each scenario, year by year, and each portfolio's PD term structure">
    <template #actions>
      <select v-model="sel" @change="go" class="maiic-input !w-40" aria-label="Month">
        <option v-for="p in periods" :key="p" :value="p">{{ monthLabel(p) }}</option>
      </select>
      <button v-if="canGovern" type="button" class="primary-btn text-sm" :disabled="!model || !set" @click="project"><font-awesome-icon icon="sync-alt" class="mr-1" />{{ result ? 'Project again' : 'Project the scenarios' }}</button>
    </template>

    <div class="space-y-4">
      <KpiRow :cards="kpis" />
      <p v-if="error" class="maiic-panel px-5 py-3 text-sm text-red-700">{{ error }}</p>
      <p v-if="stale" class="maiic-panel border-l-4 !border-l-maiicgold-500 px-5 py-3 text-sm">The route ran after this projection: project again so it reflects the PDs the ECL now uses.</p>
      <div v-if="!model || !set" class="maiic-panel maiic-empty">
        The projection needs {{ !model ? 'a model version in force' : '' }}{{ !model && !set ? ' and ' : '' }}{{ !set ? 'an approved scenario set' : '' }} for {{ monthLabel(period) }}.
      </div>
      <div v-else-if="!result" class="maiic-panel maiic-empty">Not projected yet for {{ monthLabel(period) }}. Use Project the scenarios at the top right.</div>

      <template v-if="result">
        <!-- the forecast of the measure -->
        <div class="maiic-panel">
          <div class="border-b border-gray-200 px-5 py-3 dark:border-slate-700"><h3 class="font-semibold text-gray-900">The credit-loss measure, forecast by scenario</h3><p class="text-xs text-gray-500">Model version {{ result.model_version?.version }} ({{ result.model_version?.relationship }}); set {{ result.set?.name }}, v{{ result.set?.version }}. Year 1 is the adjustment the route books.</p></div>
          <div class="maiic-table-wrap"><table class="maiic-table">
            <thead><tr><th>Scenario</th><th v-for="y in pathYears" :key="y" class="num">Year {{ y + 1 }}</th></tr></thead>
            <tbody>
              <tr v-for="(rows, name) in result.adjustments" :key="name">
                <td class="font-semibold">{{ name }}</td>
                <td v-for="r in rows" :key="r.year" class="num" :title="r.source + (r.driver !== null ? ', driver ' + r.driver : '')"><div>{{ pct(r.adjustment, 1) }}</div><div class="text-[11px] text-gray-500">measure {{ r.predicted_proxy }}</div></td>
              </tr>
            </tbody>
          </table></div>
          <p class="px-5 py-2 text-xs text-gray-500">{{ result.horizon?.label }}. After the horizon the adjustment falls in equal steps to nil (IFRS 9 B5.5.50).</p>
        </div>

        <!-- each portfolio's term structure -->
        <div class="maiic-panel">
          <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-3 dark:border-slate-700">
            <div><h3 class="font-semibold text-gray-900">PD term structure by portfolio</h3><p class="text-xs text-gray-500">Conditional PD of each year, the probability of surviving to it, the marginal PD and the cumulative PD, under each scenario.</p></div>
            <select v-model="portfolio" class="maiic-input !w-64" aria-label="Portfolio"><option v-for="s in result.segments" :key="s.portfolio_id" :value="s.portfolio_id">{{ s.portfolio }}</option></select>
          </div>
          <div v-if="seg" class="px-5 py-3 text-sm text-gray-700">
            {{ seg.loans }} performing loans ({{ seg.stage2_loans }} in Stage 2), exposure MWK {{ Number(seg.ead).toLocaleString(undefined, { maximumFractionDigits: 0 }) }}, PD before FLI {{ pct(seg.pd_pre, 2) }}, projected over {{ seg.years }} years.
          </div>
          <div v-if="seg" class="grid gap-4 px-5 pb-4 lg:grid-cols-2">
            <div v-for="(terms, name) in seg.terms" :key="name" class="rounded-lg border border-gray-200 dark:border-slate-700">
              <div class="flex items-center justify-between border-b border-gray-100 px-3 py-2 text-sm dark:border-slate-700"><span class="font-semibold">{{ name }}</span><span class="text-xs text-gray-500">lifetime PD {{ pct(seg.summary[name]?.lifetime_pd, 2) }}</span></div>
              <table class="maiic-table"><thead><tr><th class="num">Year</th><th class="num">Conditional</th><th class="num">Survival</th><th class="num">Marginal</th><th class="num">Cumulative</th></tr></thead>
                <tbody><tr v-for="t in terms" :key="t.period_index"><td class="num">{{ t.period_index }}</td><td class="num">{{ pct(t.conditional_pd, 2) }}</td><td class="num">{{ pct(t.survival_open, 2) }}</td><td class="num">{{ pct(t.marginal_pd, 2) }}</td><td class="num font-semibold">{{ pct(t.cumulative_pd, 2) }}</td></tr></tbody></table>
            </div>
          </div>
          <p v-if="seg" class="px-5 pb-3 text-xs text-gray-500">For comparison, the ECL engine's annualised lifetime PD over the same {{ seg.years }} years, 1 - (1 - PD before FLI)^years, is {{ pct(seg.engine_lifetime_pd, 2) }}. The booked Stage 2 ECL is measured by the engine on the PD the route writes; this term structure is the evidence year by year.</p>
        </div>
      </template>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import KpiRow from '@/Components/Maiic/KpiRow.vue'
import { router } from '@inertiajs/vue3'

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

export default {
  components: { AppLayout, KpiRow },
  props: { period: String, periods: Array, model: Object, set: Object, horizon: Object, error: String, result: Object, stale: Boolean, canGovern: Boolean },
  data() { return { sel: this.period, portfolio: this.result?.segments?.[0]?.portfolio_id ?? null } },
  computed: {
    seg() { return (this.result?.segments || []).find(s => s.portfolio_id === this.portfolio) },
    pathYears() { const f = Object.values(this.result?.adjustments || {})[0]; return f ? f.map(r => r.year) : [] },
    kpis() {
      return [
        { label: 'Model in force', value: this.model ? 'Version ' + this.model.version : 'None', raw: true, accent: '#0284c7', sub: this.model ? this.model.statistic_code + ' to ' + this.model.proxy_code + ', lag ' + this.model.lag_months : '' },
        { label: 'Scenario set', value: this.set ? this.set.name : 'None approved', raw: true, accent: '#7c3aed', sub: this.set ? 'v' + this.set.version + ' ' + this.set.status : '' },
        { label: 'Forecast horizon', value: this.horizon ? this.horizon.horizon + ' + ' + this.horizon.reversion + ' years' : '-', raw: true, accent: '#d97706', sub: 'path, then reversion' },
        { label: 'Projected', value: this.result ? String(this.result.created_at).slice(0, 16) : 'Not yet', raw: true, accent: this.stale ? '#dc2626' : '#15803d' },
      ]
    },
  },
  methods: {
    monthLabel(p) { if (!p) return '-'; const [y, m] = String(p).split('-'); return m ? `${MONTHS[Number(m) - 1]} ${y}` : p },
    go() { router.get(route('forecasting.manual'), { period: this.sel }) },
    pct(v, d) { return v === null || v === undefined ? '-' : (Number(v) * 100).toFixed(d) + '%' },
    project() { router.post(route('forecasting.manual.process'), { period: this.period }, { preserveScroll: true }) },
  },
}
</script>
