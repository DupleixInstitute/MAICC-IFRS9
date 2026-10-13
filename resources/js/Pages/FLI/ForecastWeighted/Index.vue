<template>
  <app-layout title="Weighted Forecast" description="The probability-weighted path of every driver under a scenario set, beside each scenario's path and the spread between them">
    <template #actions>
      <select v-model="sel.period" @change="go(false)" class="maiic-input !w-36" aria-label="Month">
        <option v-for="p in periods" :key="p" :value="p">{{ monthLabel(p) }}</option>
      </select>
      <select v-if="sets.length" v-model="sel.set" @change="go(true)" class="maiic-input !w-64" aria-label="Scenario set">
        <option v-for="s in sets" :key="s.id" :value="s.id">{{ s.name }}, v{{ s.version }} ({{ s.status }})</option>
      </select>
      <button v-if="canGovern && setId" type="button" class="primary-btn text-sm" @click="calculate"><font-awesome-icon icon="sync-alt" class="mr-1" />{{ saved.count ? 'Recalculate and store' : 'Calculate and store' }}</button>
      <button v-if="canGovern && saved.count" type="button" class="secondary-btn text-sm" @click="remove" title="Remove the stored path of this set"><font-awesome-icon icon="trash" /></button>
    </template>

    <div class="space-y-4">
      <p class="text-xs text-gray-500">
        IFRS 9 weights the loss, not the economy: the booked ECL is the weighted ECL of the scenarios (see Impact Analysis). This path is for reading the set, and is the input of the "weight the macro path" method kept for reproducing earlier months.
        <span v-if="asOf"> Values as known at {{ asOf }}.</span>
        <span v-if="saved.count"> Stored: revision {{ saved.revision }}, {{ saved.count }} values, {{ String(saved.at).slice(0, 16) }}.</span><span v-else-if="setId"> Not stored yet for this set.</span>
      </p>
      <p v-if="error" class="maiic-panel px-5 py-3 text-sm text-red-700">{{ error }}</p>
      <div v-if="!sets.length" class="maiic-panel maiic-empty">No scenario set for {{ monthLabel(period) }}. Start one from Scenario Profiles or on Scenario Sets.</div>

      <div v-else class="maiic-panel">
        <div class="maiic-table-wrap"><table class="maiic-table">
          <thead>
            <tr><th rowspan="2">Driver</th><th v-for="o in offsets" :key="o" :colspan="scenarios.length + 2" class="text-center">{{ year + o }}</th></tr>
            <tr><template v-for="o in offsets" :key="'h' + o"><th v-for="s in scenarios" :key="s.name + o" class="num">{{ s.name }} <span class="text-gray-400">{{ s.weight }}%</span></th><th class="num">Weighted</th><th class="num">Spread</th></template></tr>
          </thead>
          <tbody>
            <tr v-for="r in pagedRows" :key="r.statistic_code">
              <td class="min-w-[12rem]"><div class="font-semibold">{{ r.name }}</div><div class="text-xs text-gray-500">{{ r.statistic_code }}<span v-if="!r.shocked"> · no scenario moves it</span></div></td>
              <template v-for="o in offsets" :key="r.statistic_code + o">
                <td v-for="s in scenarios" :key="s.name" class="num" :class="s.is_base ? '' : (diff(r, o, s.name) ? 'text-maiic-800 font-semibold' : 'text-gray-400')" :title="r.years[o] ? r.years[o].source : ''">{{ n(r.years[o]?.scenarios[s.name]) }}</td>
                <td class="num font-bold">{{ n(r.years[o]?.weighted) }}</td>
                <td class="num text-xs text-gray-500">{{ n(r.years[o]?.spread) }}</td>
              </template>
            </tr>
            <tr v-if="!rows.length"><td :colspan="1 + offsets.length * (scenarios.length + 2)" class="maiic-empty">The set has no paths.</td></tr>
          </tbody>
        </table></div>
        <RowPager v-model="page" :total="rows.length" class="border-t border-gray-100" />
      </div>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import RowPager from '@/Components/Maiic/RowPager.vue'
import { router } from '@inertiajs/vue3'
import { confirmDialog } from '@/Components/confirmDialog'

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

export default {
  components: { AppLayout, RowPager },
  props: { period: String, periods: Array, sets: Array, setId: Number, rows: Array, scenarios: Array, year: Number, asOf: String, error: String, saved: Object, canGovern: Boolean },
  data() { return { sel: { period: this.period, set: this.setId }, page: 1 } },
  computed: {
    offsets() { const first = this.rows[0]; return first ? Object.keys(first.years).map(Number) : [0, 1, 2] },
    pagedRows() { return this.rows.slice((this.page - 1) * 15, this.page * 15) },
  },
  methods: {
    monthLabel(p) { if (!p) return '-'; const [y, m] = String(p).split('-'); return m ? `${MONTHS[Number(m) - 1]} ${y}` : p },
    go(withSet) { router.get(route('macro-forecast-weighted.index'), withSet ? { period: this.sel.period, set: this.sel.set } : { period: this.sel.period }) },
    n(v) { return v === null || v === undefined ? '-' : Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 }) },
    diff(r, o, name) { const y = r.years[o]; return y && y.scenarios[name] !== null && y.base !== null && Math.abs(y.scenarios[name] - y.base) > 1e-9 },
    calculate() { router.post(route('macro-forecast-weighted.calculate'), { set_id: this.setId }, { preserveScroll: true }) },
    async remove() { if (await confirmDialog({ title: 'Remove the stored weighted path?', message: 'The set itself is unchanged; the path can be stored again at any time.', confirmLabel: 'Remove', tone: 'danger' })) router.delete(route('macro-forecast-weighted.destroy', this.setId), { preserveScroll: true }) },
  },
}
</script>
