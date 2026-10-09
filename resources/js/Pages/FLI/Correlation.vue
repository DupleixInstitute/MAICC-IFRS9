<template>
  <app-layout title="Correlation Finder" description="Which economic series, at what lag, in what form, explains the credit losses, and how well: every driver against every proxy over the lag grid, ranked, with the guardrail's verdict on each pair and the regression engine's fit on the candidates (spec v4 section 14.4)">
    <template #actions>
      <form @submit.prevent="go" class="flex items-center gap-2">
        <select v-model="form.period" class="maiic-select text-sm"><option v-for="p in periods" :key="p" :value="p">{{ p }}</option><option v-if="!periods.includes(form.period)" :value="form.period">{{ form.period }}</option></select>
        <button type="button" @click="go" class="secondary-btn text-sm">View</button>
        <button v-if="canRun" type="button" @click="runFinder" :disabled="running" class="primary-btn text-sm">{{ running ? 'Running…' : 'Run the finder for ' + form.period }}</button>
        <Link :href="route('fli-adjustments.index', { period: form.period })" class="secondary-btn text-sm">FLI Adjustments</Link>
      </form>
    </template>

    <div class="space-y-6">
      <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
        <div class="maiic-kpi" style="--accent:#0284c7"><div class="maiic-kpi-label">Latest sweep for {{ period }}</div><div class="text-sm font-bold">{{ run ? ('run #' + run.id + ' at ' + run.run_at) : 'none yet' }}</div><div v-if="run" class="truncate font-mono text-xs text-gray-500" :title="run.inputs_hash">inputs {{ (run.inputs_hash || '').slice(0, 12) }}</div></div>
        <div class="maiic-kpi" style="--accent:#0284c7"><div class="maiic-kpi-label">Series in the bridge</div><div class="maiic-kpi-value">{{ series.macro }} × {{ series.proxies }}</div><div class="text-xs text-gray-500">drivers × credit-loss proxies</div></div>
        <div class="maiic-kpi" style="--accent:#0284c7"><div class="maiic-kpi-label">Suggestions by verdict</div><div class="text-sm font-bold"><span v-for="(n, v) in byVerdict" :key="v" class="mr-3">{{ v }} {{ n }}</span><span v-if="!Object.keys(byVerdict).length">-</span></div></div>
        <div class="maiic-kpi" style="--accent:#0284c7"><div class="maiic-kpi-label">Fits of the period</div><div class="maiic-kpi-value">{{ fits.length }}</div><div class="text-xs text-gray-500">{{ fits.filter(f => f.verdict === 'applied').length }} applied, {{ fits.filter(f => f.verdict === 'declined').length }} declined</div></div>
      </div>

      <div v-if="lastRun" class="maiic-panel p-5 text-sm">
        <h3 class="text-base font-bold">The finder's last run from this screen for {{ period }}</h3>
        <pre class="mt-2 max-h-48 overflow-auto whitespace-pre-wrap rounded bg-gray-50 p-3 font-mono text-xs dark:bg-slate-900/60">{{ lastRun.output }}</pre>
      </div>

      <div class="maiic-panel">
        <div class="border-b border-gray-200 p-5 dark:border-slate-700"><h3 class="text-base font-bold">Ranked suggestions of the latest sweep</h3><p class="text-sm text-gray-500">One per driver and proxy at its best lag, strongest first. A pair the guardrail rejects shows why; it may be studied but cannot be approved as a model.</p></div>
        <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th class="num">#</th><th>Driver</th><th>Proxy</th><th class="num">Lag</th><th class="num">Score</th><th class="num">R²</th><th>Sign</th><th>Verdict</th><th>Reason</th></tr></thead>
          <tbody><tr v-for="(s, i) in suggestions" :key="s.id" :class="s.verdict === 'rejected' ? 'text-red-700 dark:text-red-300' : ''"><td class="num">{{ i + 1 }}</td><td>{{ s.statistic_code }}</td><td class="text-xs">{{ s.proxy_code }}</td><td class="num">{{ s.lag_months }}</td><td class="num">{{ n(s.score, 4) }}</td><td class="num">{{ n(s.r_squared, 4) }}</td><td>{{ s.sign_ok === null ? '' : (s.sign_ok ? 'as expected' : 'wrong') }}</td><td><span class="maiic-badge" :class="badge(s.verdict)">{{ s.verdict }}</span></td><td class="text-xs">{{ s.reason }}</td></tr>
          <tr v-if="!suggestions.length"><td colspan="9" class="maiic-empty">No sweep for this period: run the finder.</td></tr></tbody></table></div>
      </div>

      <div class="maiic-panel">
        <div class="border-b border-gray-200 p-5 dark:border-slate-700"><h3 class="text-base font-bold">The regression engine's fits for {{ period }}</h3><p class="text-sm text-gray-500">Each candidate fitted and applied or declined by the guardrail: enough observations, the expected sign, R² at or above the cut-off, significance at the governed alpha. An applied fit goes forward on FLI Adjustments under maker-checker.</p></div>
        <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Fit</th><th>Driver</th><th>Proxy</th><th class="num">Lag</th><th>Expected sign</th><th class="num">Slope</th><th class="num">r</th><th class="num">R²</th><th class="num">p</th><th class="num">n</th><th>Verdict</th><th>Declined because</th><th>Approval</th></tr></thead>
          <tbody><tr v-for="f in fits" :key="f.id" :class="f.verdict === 'declined' ? 'text-red-700 dark:text-red-300' : ''"><td class="font-mono text-xs">{{ f.id }}</td><td>{{ f.statistic_code }}</td><td class="text-xs">{{ f.proxy_code }}</td><td class="num">{{ f.lag_months }}</td><td class="text-xs">{{ f.expected_sign || 'not stated' }}</td><td class="num">{{ n(f.slope, 6) }}</td><td class="num">{{ n(f.correlation_r, 4) }}</td><td class="num">{{ n(f.r_squared, 4) }}</td><td class="num">{{ n(f.p_value, 4) }}</td><td class="num">{{ f.n_obs }}</td><td><span class="maiic-badge" :class="f.verdict === 'applied' ? 'maiic-badge-green' : 'maiic-badge-red'">{{ f.verdict }}</span></td><td class="text-xs">{{ f.declined_reason }}</td><td class="text-xs">{{ f.approval_status }}</td></tr>
          <tr v-if="!fits.length"><td colspan="13" class="maiic-empty">No fits for this period.</td></tr></tbody></table></div>
      </div>

      <div class="maiic-panel">
        <div class="border-b border-gray-200 p-5 dark:border-slate-700"><h3 class="text-base font-bold">Sweeps</h3><p class="text-sm text-gray-500">Every sweep is an immutable run with the hash of the inputs it read; a later sweep is a new run, not an edit.</p></div>
        <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Run</th><th>Period</th><th>Run at</th><th>By</th><th>Status</th><th class="num">Suggestions</th><th>Inputs hash</th></tr></thead>
          <tbody><tr v-for="r in runs" :key="r.id"><td class="font-mono text-xs">{{ r.id }}</td><td><Link :href="route('fli-correlation.index', { period: r.period })" class="hover:underline">{{ r.period }}</Link></td><td class="text-xs">{{ r.run_at }}</td><td class="text-xs">{{ r.run_by || 'console' }}</td><td class="text-xs">{{ r.status }}</td><td class="num">{{ r.suggestions }}</td><td class="font-mono text-xs">{{ (r.inputs_hash || '').slice(0, 16) }}</td></tr>
          <tr v-if="!runs.length"><td colspan="7" class="maiic-empty">No sweep has run.</td></tr></tbody></table></div>
      </div>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'

export default {
  components: { AppLayout, Link },
  props: { period: String, run: Object, suggestions: Array, fits: Array, runs: Array, byVerdict: Object, series: Object, periods: Array, lastRun: Object, canRun: Boolean },
  data() { return { form: { period: this.period }, running: false } },
  methods: {
    n(v, d) { return v == null ? '' : Number(v).toFixed(d) },
    // the finder's verdicts: recommended, usable_with_caveat, rejected
    badge(v) { return v === 'recommended' ? 'maiic-badge-green' : (v === 'rejected' ? 'maiic-badge-red' : 'maiic-badge-gold') },
    go() { router.get(route('fli-correlation.index'), { period: this.form.period }) },
    runFinder() {
      this.running = true
      router.post(route('fli-correlation.run'), { period: this.form.period }, { preserveScroll: true, onFinish: () => { this.running = false } })
    },
  },
}
</script>
