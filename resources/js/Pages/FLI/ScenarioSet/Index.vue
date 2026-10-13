<template>
  <app-layout title="Economic Scenarios" description="The scenario workbench: each driver's recent actuals and every scenario's path for the forecast years, the weights and the narrative, against the previous version">
    <template #actions>
      <select v-model="sel.period" @change="go(false)" class="maiic-input !w-36" aria-label="Month">
        <option v-for="p in periods" :key="p" :value="p">{{ monthLabel(p) }}</option>
      </select>
      <select v-if="sets.length" v-model="sel.set" @change="go(true)" class="maiic-input !w-64" aria-label="Scenario set">
        <option v-for="s in sets" :key="s.id" :value="s.id">{{ s.name }}, v{{ s.version }} ({{ s.status }})</option>
      </select>
      <Link :href="route('scenario-sets.index', { period })" class="primary-btn text-sm"><font-awesome-icon icon="pen" class="mr-1" />Edit on Scenario Sets</Link>
    </template>

    <div class="space-y-4">
      <div v-if="!workbench" class="maiic-panel maiic-empty">No scenario set for {{ monthLabel(period) }}. Start one from Scenario Profiles.</div>

      <template v-else>
        <!-- weights and narrative -->
        <div class="maiic-panel">
          <div class="border-b border-gray-200 px-5 py-3 dark:border-slate-700"><h3 class="font-semibold text-gray-900">The scenarios</h3>
            <p class="text-xs text-gray-500">Values as known at {{ workbench.as_of }}<span v-if="workbench.compare">; compared with {{ workbench.compare.name }}, v{{ workbench.compare.version }} ({{ monthLabel(workbench.compare.period) }}, {{ workbench.compare.status }})</span>.</p></div>
          <div class="maiic-table-wrap"><table class="maiic-table">
            <thead><tr><th>Scenario</th><th class="num">Weight</th><th class="num">Before</th><th>Anchored to</th><th>Calibration note</th></tr></thead>
            <tbody><tr v-for="s in workbench.scenarios" :key="s.name">
              <td class="font-semibold"><span class="mr-1 inline-block h-2.5 w-2.5 rounded-full" :style="{ background: colour(s.name) }"></span>{{ s.name }}<span v-if="s.is_base" class="ml-1 text-xs text-maiic-700">base</span></td>
              <td class="num font-semibold">{{ Number(s.weight) }}%</td>
              <td class="num" :class="s.previous_weight !== null && Number(s.previous_weight) !== Number(s.weight) ? 'text-amber-700 font-semibold' : 'text-gray-400'">{{ s.previous_weight === null ? '-' : s.previous_weight + '%' }}</td>
              <td class="max-w-[22rem] text-xs">{{ s.anchored_to || s.narrative || '-' }}</td>
              <td class="max-w-[22rem] text-xs text-gray-500">{{ s.calibration_note || '-' }}</td>
            </tr></tbody>
          </table></div>
        </div>

        <!-- one card per driver -->
        <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
          <div v-for="s in pagedSeries" :key="s.code" class="maiic-panel">
            <div class="flex items-start justify-between gap-2 border-b border-gray-100 px-4 py-2 dark:border-slate-700">
              <div><div class="font-semibold text-gray-900">{{ s.name }}</div><div class="text-[11px] text-gray-500">{{ s.code }}{{ s.shocked ? '' : ' · every scenario on the base path' }}</div></div>
            </div>
            <svg :viewBox="`0 0 ${W} ${H}`" class="h-32 w-full px-2" role="img" :aria-label="s.name + ' history and scenario paths'">
              <line :x1="xAt(3.5)" :x2="xAt(3.5)" y1="6" :y2="H - 14" stroke="currentColor" class="text-gray-300" stroke-dasharray="3 3" />
              <polyline :points="histPoints(s)" fill="none" stroke="#64748b" stroke-width="2" />
              <polyline v-for="sc in s.scenarios" :key="sc.name" :points="pathPoints(s, sc)" fill="none" :stroke="colour(sc.name)" stroke-width="2" />
              <text v-for="(y, i) in years" :key="y" :x="xAt(i)" :y="H - 2" text-anchor="middle" class="fill-gray-500 text-[9px]">{{ y }}</text>
            </svg>
            <table class="maiic-table text-xs">
              <thead><tr><th></th><th v-for="o in [0, 1, 2]" :key="o" class="num">{{ workbench.year + o }}</th></tr></thead>
              <tbody>
                <tr v-for="sc in s.scenarios" :key="sc.name"><td><span class="mr-1 inline-block h-2 w-2 rounded-full" :style="{ background: colour(sc.name) }"></span>{{ sc.name }}</td>
                  <td v-for="o in [0, 1, 2]" :key="o" class="num" :class="changed(sc, o) ? 'font-semibold text-amber-700' : ''" :title="sc.previous ? 'Before: ' + fmt(sc.previous[o]) : ''">{{ fmt(sc.values[o]) }}</td></tr>
              </tbody>
            </table>
          </div>
        </div>
        <RowPager v-model="page" :per-page="9" :total="workbench.series.length" />
      </template>

      <!-- the older named sets, as they were -->
      <details v-if="scenarioSets.length" class="maiic-panel group">
        <summary class="flex cursor-pointer select-none items-center justify-between px-5 py-3"><span class="font-semibold text-gray-900">Named sets from the earlier screens ({{ scenarioSets.length }})</span><span class="text-xs font-semibold text-maiic-700">Show</span></summary>
        <table class="maiic-table"><thead><tr><th>Name</th><th>Description</th><th>Active</th><th>Created</th><th></th></tr></thead>
          <tbody><tr v-for="s in scenarioSets" :key="s.id"><td>{{ s.name }}</td><td class="text-xs">{{ s.description }}</td><td>{{ s.is_active ? 'Yes' : 'No' }}</td><td class="text-xs">{{ String(s.created_at).slice(0, 10) }}</td>
            <td class="num"><Link :href="route('fli.scenarios.edit', s.id)" class="maiic-action maiic-action-edit" title="Edit"><font-awesome-icon icon="pen" /></Link></td></tr></tbody></table>
      </details>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import RowPager from '@/Components/Maiic/RowPager.vue'
import { Link, router } from '@inertiajs/vue3'

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
const PALETTE = { Base: '#15803d', Upside: '#0284c7', Downside: '#d97706', Severe: '#dc2626' }
const OTHER = ['#7c3aed', '#db2777', '#0d9488', '#ca8a04']

export default {
  components: { AppLayout, RowPager, Link },
  props: { period: String, periods: Array, sets: Array, setId: Number, workbench: Object, scenarioSets: Array },
  data() { return { sel: { period: this.period, set: this.setId }, page: 1, W: 320, H: 120 } },
  computed: {
    pagedSeries() { return (this.workbench?.series || []).slice((this.page - 1) * 9, this.page * 9) },
    years() { const y = this.workbench?.year || 0; return [y - 4, y - 3, y - 2, y - 1, y, y + 1, y + 2] },
  },
  methods: {
    monthLabel(p) { if (!p) return '-'; const [y, m] = String(p).split('-'); return m ? `${MONTHS[Number(m) - 1]} ${y}` : p },
    go(withSet) { router.get(route('fli.scenarios.index'), withSet ? { period: this.sel.period, set: this.sel.set } : { period: this.sel.period }) },
    colour(name) { if (PALETTE[name]) return PALETTE[name]; const i = (this.workbench?.scenarios || []).findIndex(s => s.name === name); return OTHER[Math.max(0, i) % OTHER.length] },
    fmt(v) { return v === null || v === undefined ? '-' : Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 }) },
    changed(sc, o) { return sc.previous && sc.previous[o] !== null && sc.values[o] !== null && Math.abs(sc.previous[o] - sc.values[o]) > 1e-9 },
    xAt(i) { return 14 + i * (this.W - 28) / 6 },
    range(s) {
      const vals = [...Object.values(s.history), ...s.scenarios.flatMap(sc => Object.values(sc.values))].filter(v => v !== null).map(Number)
      if (!vals.length) return [0, 1]
      const lo = Math.min(...vals), hi = Math.max(...vals)
      return lo === hi ? [lo - 1, hi + 1] : [lo, hi]
    },
    yAt(s, v) { const [lo, hi] = this.range(s); return 8 + (1 - (v - lo) / (hi - lo)) * (this.H - 26) },
    histPoints(s) { return Object.values(s.history).map((v, i) => v === null ? null : `${this.xAt(i)},${this.yAt(s, v)}`).filter(Boolean).join(' ') },
    pathPoints(s, sc) {
      const last = Object.values(s.history).filter(v => v !== null).pop()
      const pts = last !== undefined ? [`${this.xAt(3)},${this.yAt(s, last)}`] : []
      Object.values(sc.values).forEach((v, o) => { if (v !== null) pts.push(`${this.xAt(4 + o)},${this.yAt(s, v)}`) })
      return pts.join(' ')
    },
  },
}
</script>
