<template>
  <app-layout title="Macro Statistics" description="The macroeconomic series the forward-looking model reads, with the source each came from: the World Bank for actuals, the IMF World Economic Outlook for the forecast years, the Reserve Bank by file for the policy rate, and the PLR from the landing zone (spec v4 section 13)">
    <template #actions><a :href="route('macro-statistics.export')" class="secondary-btn text-sm">Export CSV</a></template>
    <div class="space-y-4">
      <div class="flex flex-wrap gap-1 border-b border-gray-200 dark:border-slate-700">
        <button v-for="t in tabs" :key="t" @click="tab = t" class="px-4 py-2 text-sm font-semibold" :class="tab === t ? 'border-b-2 border-teal-500 text-teal-700 dark:text-teal-300' : 'text-gray-500'">{{ t }}</button>
      </div>

      <!-- Dashboard -->
      <div v-if="tab === 'Dashboard'" class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div v-for="s in series" :key="s.id" class="maiic-kpi cursor-pointer" style="--accent:#0d9488" @click="select(s)">
          <div class="maiic-kpi-label">{{ s.code }} · {{ s.unit }}</div>
          <div class="text-sm font-bold">{{ s.name }}</div>
          <div class="text-xs text-gray-500">{{ s.n }} observations{{ s.n ? ' ' + (s.first || '').slice(0, 7) + ' to ' + (s.last || '').slice(0, 7) : '' }}<span v-if="s.forecasts"> · {{ s.forecasts }} forecast</span></div>
          <div class="text-xs text-gray-500" v-if="s.last_batch">last from {{ s.last_batch.source }} {{ (s.last_batch.fetched_at || '').slice(0, 10) }}</div>
          <div class="text-xs text-amber-700" v-else>no source committed yet</div>
        </div>
      </div>

      <!-- Variables -->
      <div v-if="tab === 'Variables'" class="maiic-panel">
        <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Code</th><th>Series</th><th>Unit</th><th>Frequency</th><th>Source</th><th>World Bank</th><th>IMF WEO</th><th>Other</th><th>Why MAIIC needs it</th></tr></thead>
          <tbody><tr v-for="s in series" :key="s.id"><td class="font-mono text-xs">{{ s.code }}</td><td>{{ s.name }}</td><td class="text-xs">{{ s.unit }}</td><td class="text-xs">{{ s.frequency }}</td><td class="text-xs">{{ s.source }}</td><td class="font-mono text-xs">{{ s.codes.world_bank || '' }}</td><td class="font-mono text-xs">{{ s.codes.imf_weo || '' }}</td><td class="font-mono text-xs">{{ s.codes.rbm || s.codes.landing_zone || '' }}</td><td class="text-xs">{{ s.why }}</td></tr></tbody></table></div>
        <p class="p-3 text-xs text-gray-500">A series with no code says so; it is not an error. Codes are a seeder change (MacroSeriesSeeder), not a code change. The legacy editor remains at <Link :href="route('macro-statistics.legacy')" class="underline">Macro Elements</Link>.</p>
      </div>

      <!-- Data entry -->
      <div v-if="tab === 'Data Entry'" class="space-y-4">
        <div class="maiic-filterbar flex flex-wrap items-end gap-3">
          <label class="text-xs"><span class="maiic-flabel">Series</span><select v-model="sel" @change="go" class="maiic-select"><option v-for="s in series" :key="s.id" :value="s.id">{{ s.code }} {{ s.name }}</option></select></label>
          <form v-if="canManage" @submit.prevent="manual" class="flex flex-wrap items-end gap-2 text-xs">
            <label><span class="maiic-flabel">Period</span><input v-model="m.period" type="date" class="maiic-input" required/></label>
            <label><span class="maiic-flabel">Value</span><input v-model="m.value" type="number" step="0.000001" class="maiic-input" required/></label>
            <label class="flex items-center gap-1"><input v-model="m.is_forecast" type="checkbox"/> forecast</label>
            <label><span class="maiic-flabel">Reason (required: a manual entry overrides a source)</span><input v-model="m.reason" class="maiic-input w-72" required/></label>
            <button type="submit" class="primary-btn text-sm">Record</button>
          </form>
        </div>
        <div class="maiic-panel"><div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Period</th><th class="num">Value</th><th>Actual / forecast</th><th>Source</th><th>Batch</th></tr></thead>
          <tbody><tr v-for="o in observations" :key="o.id"><td>{{ o.period }}</td><td class="num">{{ Number(o.value).toLocaleString('en-GB', { maximumFractionDigits: 4 }) }}</td><td>{{ o.is_forecast ? 'forecast' : 'actual' }}</td><td class="text-xs">{{ o.source }}</td><td class="text-xs">{{ o.batch_source }} {{ (o.fetched_at || '').slice(0, 10) }}</td></tr>
          <tr v-if="!observations.length"><td colspan="5" class="maiic-empty">No observations for this series yet.</td></tr></tbody></table></div></div>
      </div>

      <!-- Scenario assumptions -->
      <div v-if="tab === 'Scenario Assumptions'" class="maiic-panel p-5 text-sm">
        <p>The base path of every series comes from the sources here; every other scenario is a governed shock on the base, by series and year offset, kept with the scenario set under the Governance Centre so that a change to the base carries through to the shocked paths.</p>
        <ul class="mt-3 space-y-1"><li v-for="s in sets" :key="s.id"><Link :href="route('scenario-sets.index', { period: s.reporting_period })" class="text-sky-700 hover:underline dark:text-sky-300">{{ s.reporting_period }} v{{ s.version }} {{ s.name }}</Link> <span class="maiic-badge maiic-badge-grey">{{ s.status }}</span></li></ul>
        <p v-if="!sets.length" class="mt-2 text-gray-500">No scenario set yet: propose the first set under Governance Centre, Scenario Sets.</p>
      </div>

      <!-- Import / export -->
      <div v-if="tab === 'Import / Export'" class="space-y-4">
        <div class="maiic-panel p-5 text-sm">
          <h3 class="text-base font-bold">Preview, then commit</h3>
          <p class="text-gray-500">Nothing reaches the table until a person has seen the rows. Each commit is a batch with its source, address, time, who and how many rows.</p>
          <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
            <div class="rounded border border-gray-200 p-3 dark:border-slate-700"><div class="font-semibold">World Bank (live)</div>
              <select v-model="wb.series_code" class="maiic-select mt-1"><option value="">series…</option><option v-for="s in series.filter(x => x.codes.world_bank)" :key="s.id" :value="s.code">{{ s.code }}</option></select>
              <div class="mt-1 flex gap-1"><input v-model="wb.country" class="maiic-input" placeholder="MWI"/><input v-model="wb.from" type="number" class="maiic-input" placeholder="from"/><input v-model="wb.to" type="number" class="maiic-input" placeholder="to"/></div>
              <button @click="preview('world_bank', wb)" class="secondary-btn mt-2 text-xs">Preview</button></div>
            <div class="rounded border border-gray-200 p-3 dark:border-slate-700"><div class="font-semibold">IMF World Economic Outlook (file)</div>
              <select v-model="imf.series_code" class="maiic-select mt-1"><option value="">series…</option><option v-for="s in series.filter(x => x.codes.imf_weo)" :key="s.id" :value="s.code">{{ s.code }}</option></select>
              <input type="file" class="mt-1 text-xs" @change="imf.file = $event.target.files[0]"/>
              <button @click="preview('imf_weo', imf)" class="secondary-btn mt-2 text-xs">Preview</button></div>
            <div class="rounded border border-gray-200 p-3 dark:border-slate-700"><div class="font-semibold">Reserve Bank policy rate (CSV: date,rate)</div>
              <input type="file" class="mt-1 text-xs" @change="rbm.file = $event.target.files[0]"/>
              <button @click="preview('rbm_file', rbm)" class="secondary-btn mt-2 text-xs">Preview</button></div>
          </div>
          <div v-if="previewError" class="mt-3 rounded bg-red-50 p-2 text-xs text-red-700">{{ previewError }}</div>
          <div v-if="previewData" class="mt-3">
            <div class="text-xs text-gray-500">{{ previewData.series_code }} · {{ previewData.source }} · {{ previewData.address }} · {{ previewData.rows.length }} rows<span v-if="previewData.estimates_start_after"> · estimates start after {{ previewData.estimates_start_after }}</span><span v-if="previewData.note" class="text-amber-700"> · {{ previewData.note }}</span></div>
            <div class="maiic-table-wrap mt-1 max-h-64 overflow-y-auto"><table class="maiic-table"><thead><tr><th>Period</th><th class="num">Value</th><th>Type</th></tr></thead><tbody><tr v-for="r in previewData.rows" :key="r.period"><td>{{ r.period }}</td><td class="num">{{ r.value }}</td><td>{{ r.value_type }}</td></tr></tbody></table></div>
            <button v-if="canManage && previewData.rows.length" @click="commit" class="primary-btn mt-2 text-sm">Commit {{ previewData.rows.length }} rows as a batch</button>
          </div>
        </div>
        <div class="maiic-panel"><div class="border-b border-gray-200 p-4 dark:border-slate-700"><h3 class="text-base font-bold">Batches</h3></div>
          <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Batch</th><th>Source</th><th>Address</th><th>Fetched</th><th>Committed by</th><th class="num">Rows</th><th>Series</th></tr></thead>
            <tbody><tr v-for="b in batches" :key="b.id" class="text-xs"><td>{{ b.id }}</td><td>{{ b.source }}</td><td class="break-all">{{ b.address }}</td><td>{{ b.fetched_at }}</td><td>{{ b.committed_by_name || 'system' }}</td><td class="num">{{ b.rows }}</td><td>{{ (JSON.parse(b.series || '[]')).map(x => x.code).join(', ') }}</td></tr></tbody></table></div></div>
      </div>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'

export default {
  components: { AppLayout, Link },
  props: { series: Array, selected: Number, observations: Array, batches: Array, sets: Array, canManage: Boolean, defaultCountry: String },
  data() {
    return { tabs: ['Dashboard', 'Variables', 'Data Entry', 'Scenario Assumptions', 'Import / Export'], tab: 'Dashboard', sel: this.selected,
      wb: { series_code: '', country: this.defaultCountry, from: 2000, to: new Date().getFullYear() }, imf: { series_code: '', file: null }, rbm: { file: null },
      m: { period: '', value: '', is_forecast: false, reason: '' }, previewData: null, previewError: null, previewSource: null }
  },
  methods: {
    select(s) { this.sel = s.id; this.tab = 'Data Entry'; this.go() },
    go() { router.get(route('macro-statistics.index'), { series: this.sel }, { preserveState: true, preserveScroll: true, only: ['observations', 'selected'] }) },
    manual() {
      const code = (this.series.find(s => s.id === this.sel) || {}).code
      router.post(route('macro-statistics.manual'), { series_code: code, ...this.m }, { preserveScroll: true, onSuccess: () => { this.m = { period: '', value: '', is_forecast: false, reason: '' }; this.go() } })
    },
    async preview(source, form) {
      this.previewError = null; this.previewData = null; this.previewSource = source
      const fd = new FormData(); fd.append('source', source)
      Object.entries(form).forEach(([k, v]) => { if (v !== null && v !== '' && v !== undefined) fd.append(k, v) })
      const res = await fetch(route('macro-statistics.preview'), { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' } })
      const data = await res.json()
      if (!res.ok || data.error) { this.previewError = data.error || 'Preview failed'; return }
      this.previewData = data
    },
    commit() { router.post(route('macro-statistics.commit'), { preview: this.previewData, source: this.previewSource }, { preserveScroll: true, onSuccess: () => { this.previewData = null } }) },
  },
}
</script>
