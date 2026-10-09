<template>
  <app-layout title="Macro Statistics" description="The economic series the forward-looking model reads, and where each came from (World Bank, IMF, Reserve Bank, the loan books)">
    <template #actions><a :href="route('macro-statistics.export')" class="secondary-btn text-sm">Export CSV</a></template>
    <div class="space-y-4">
      <!-- The five views are tabs of the Macro Statistics section row (config/menu.php, ?tab=). -->

      <!-- Dashboard -->
      <div v-if="tab === 'Dashboard'" class="grid grid-cols-1 gap-3 md:grid-cols-3">
        <div v-if="!series.length" class="maiic-panel maiic-empty md:col-span-3">No macro series are set up yet.</div>
        <div v-for="s in series" :key="s.id" class="maiic-kpi !py-3 cursor-pointer hover:shadow-md" style="--accent:#15803d" :title="'Open ' + s.code + ' in Data Entry'" @click="select(s)">
          <div class="maiic-kpi-label">{{ s.code }}, {{ s.unit }}</div>
          <div class="text-sm font-bold">{{ s.name }}</div>
          <div class="text-xs text-gray-500">{{ s.n }} observations{{ s.n ? ' ' + (s.first || '').slice(0, 7) + ' to ' + (s.last || '').slice(0, 7) : '' }}<span v-if="s.forecasts">, {{ s.forecasts }} forecast</span></div>
          <div class="text-xs text-gray-500" v-if="s.last_batch">last from {{ s.last_batch.source }} {{ (s.last_batch.fetched_at || '').slice(0, 10) }}</div>
          <div class="text-xs text-amber-700" v-else>no source committed yet</div>
        </div>
      </div>

      <!-- Variables -->
      <div v-if="tab === 'Variables'" class="maiic-panel">
        <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Code</th><th>Series</th><th>Unit</th><th>Frequency</th><th>Source</th><th>World Bank</th><th>IMF WEO</th><th>Other</th><th>Why MAIIC needs it</th></tr></thead>
          <tbody><tr v-for="s in series" :key="s.id"><td class="font-mono text-xs">{{ s.code }}</td><td>{{ s.name }}</td><td class="text-xs">{{ s.unit }}</td><td class="text-xs">{{ s.frequency }}</td><td class="text-xs">{{ s.source }}</td><td class="font-mono text-xs">{{ s.codes.world_bank || '' }}</td><td class="font-mono text-xs">{{ s.codes.imf_weo || '' }}</td><td class="font-mono text-xs">{{ s.codes.rbm || s.codes.landing_zone || '' }}</td><td class="text-xs">{{ s.why }}</td></tr></tbody></table></div>
        <p class="p-3 text-xs text-gray-500">A blank source code means the series has none at that source; it is not an error. The older editor is still available at <Link :href="route('macro-statistics.legacy')" class="underline">Macro Elements</Link>.</p>
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

      <!-- Import / export: how to, sample files, preview then commit, and the batches as the import history -->
      <div v-if="tab === 'Import / Export'" class="space-y-4">
        <div class="maiic-panel grid gap-5 p-5 text-sm lg:grid-cols-3">
          <div class="space-y-4 lg:col-span-2">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
              <div class="rounded-lg border border-gray-200 p-3">
                <div class="font-semibold text-gray-900">World Bank (live)</div>
                <p class="text-xs text-gray-500">Fetched from the World Bank, no file needed.</p>
                <label class="mt-2 block"><span class="maiic-flabel">Series</span>
                  <select v-model="wb.series_code" class="maiic-select"><option value="">Choose a series</option><option v-for="s in series.filter(x => x.codes.world_bank)" :key="s.id" :value="s.code">{{ s.code }}</option></select></label>
                <label class="mt-2 block"><span class="maiic-flabel">Country</span><input v-model="wb.country" class="maiic-input" placeholder="MWI"/></label>
                <div class="mt-2 grid grid-cols-2 gap-2">
                  <label><span class="maiic-flabel">From</span><input v-model="wb.from" type="number" class="maiic-input"/></label>
                  <label><span class="maiic-flabel">To</span><input v-model="wb.to" type="number" class="maiic-input"/></label>
                </div>
                <button type="button" class="secondary-btn mt-3 text-xs" :disabled="!wb.series_code" @click="preview('world_bank', wb)">Preview</button>
              </div>
              <div class="rounded-lg border border-gray-200 p-3">
                <div class="font-semibold text-gray-900">IMF World Economic Outlook</div>
                <p class="text-xs text-gray-500">The WEO download, tab-delimited.</p>
                <label class="mt-2 block"><span class="maiic-flabel">Series</span>
                  <select v-model="imf.series_code" class="maiic-select"><option value="">Choose a series</option><option v-for="s in series.filter(x => x.codes.imf_weo)" :key="s.id" :value="s.code">{{ s.code }}</option></select></label>
                <FileDrop class="mt-2" label="" accept=".xls,.txt,.tsv,.csv" placeholder="Choose the WEO file" @file="f => imf.file = f"/>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                  <button type="button" class="secondary-btn text-xs" :disabled="!imf.series_code || !imf.file" @click="preview('imf_weo', imf)">Preview</button>
                  <a :href="route('import-samples.show', 'imf-weo')" class="text-xs font-semibold text-maiic-700 underline" title="The columns the WEO reader needs, one row per series with a WEO code">Sample file</a>
                </div>
              </div>
              <div class="rounded-lg border border-gray-200 p-3">
                <div class="font-semibold text-gray-900">Reserve Bank policy rate</div>
                <p class="text-xs text-gray-500">A CSV of two columns, date and rate.</p>
                <FileDrop class="mt-2" label="" accept=".csv,.txt" placeholder="Choose the CSV file" @file="f => rbm.file = f"/>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                  <button type="button" class="secondary-btn text-xs" :disabled="!rbm.file" @click="preview('rbm_file', rbm)">Preview</button>
                  <a :href="route('import-samples.show', 'rbm-policy-rate')" class="text-xs font-semibold text-maiic-700 underline" title="A CSV with the two columns the policy-rate reader takes">Sample CSV</a>
                </div>
              </div>
            </div>

            <div v-if="previewError" class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-3 text-red-800">
              <svg class="mt-0.5 h-5 w-5 flex-none text-red-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
              <div><div class="font-semibold">The preview could not be made</div><p class="mt-0.5 text-xs">{{ previewError }}</p></div>
            </div>
            <div v-if="previewData">
              <div class="text-xs text-gray-500">{{ previewData.series_code }}, {{ previewData.source }}, {{ previewData.address }}, {{ previewData.rows.length }} rows<span v-if="previewData.estimates_start_after">, estimates start after {{ previewData.estimates_start_after }}</span><span v-if="previewData.note" class="text-amber-700">. {{ previewData.note }}</span></div>
              <div class="maiic-table-wrap mt-1 max-h-64 overflow-y-auto rounded-lg border border-gray-200"><table class="maiic-table"><thead><tr><th>Period</th><th class="num">Value</th><th>Type</th></tr></thead><tbody><tr v-for="r in previewData.rows" :key="r.period"><td>{{ r.period }}</td><td class="num">{{ r.value }}</td><td>{{ r.value_type }}</td></tr></tbody></table></div>
              <button v-if="canManage && previewData.rows.length" type="button" class="primary-btn mt-2 text-sm" @click="commit">Commit {{ previewData.rows.length }} rows as a batch</button>
            </div>
          </div>

          <ImportHowTo title="How to import macro data">
            <li>Choose a source. World Bank is fetched live; the IMF and Reserve Bank sources are files.</li>
            <li>IMF: use the WEO download as it comes (tab-delimited, even when named .xls). The reader needs the columns ISO, WEO Subject Code and one per year, plus Estimates Start After to mark the forecast years. It reads the row of the series' WEO code.</li>
            <li>Reserve Bank: two columns, date and rate. Dates as yyyy-mm-dd, dd/mm/yyyy or yyyy-mm; each is stored at its month end. A % sign is fine.</li>
            <li>Press Preview. Nothing is saved until you have seen the rows and pressed Commit.</li>
            <li>Each commit is a batch with its source, address, time, who and how many rows. The batches below are the import history.</li>
            <li>A single value is entered on the Data entry view, with a reason; there is no file for that.</li>
            <template #after>
              <div class="mt-4 flex flex-wrap gap-2">
                <a :href="route('import-samples.show', 'rbm-policy-rate')" class="secondary-btn text-xs" title="date,rate">Policy-rate sample CSV</a>
                <a :href="route('import-samples.show', 'imf-weo')" class="secondary-btn text-xs" title="Tab-delimited, one row per series with a WEO code, year cells blank">IMF WEO sample file</a>
              </div>
            </template>
          </ImportHowTo>
        </div>

        <div class="maiic-panel">
          <div class="border-b border-gray-200 px-4 py-3"><h3 class="maiic-section-title !my-0">Import history: committed batches</h3></div>
          <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Batch</th><th>Source</th><th>Address</th><th>Fetched</th><th>Committed by</th><th class="num">Rows</th><th>Series</th></tr></thead>
            <tbody>
              <tr v-for="b in pagedBatches" :key="b.id" class="text-xs"><td>{{ b.id }}</td><td>{{ b.source }}</td><td class="break-all">{{ b.address }}</td><td>{{ b.fetched_at }}</td><td>{{ b.committed_by_name || 'system' }}</td><td class="num">{{ b.rows }}</td><td>{{ batchSeries(b) }}</td></tr>
              <tr v-if="!(batches || []).length"><td colspan="7" class="maiic-empty">No batch has been committed yet. Preview a source above, then commit it.</td></tr>
            </tbody></table></div>
          <LocalPager v-model="batchPage" :total="(batches || []).length" class="border-t border-gray-100"/>
          <p v-if="(batches || []).length >= 30" class="px-4 pb-3 text-xs text-gray-500">The 30 most recent batches.</p>
        </div>
      </div>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import FileDrop from '@/Components/Data/FileDrop.vue'
import ImportHowTo from '@/Components/Data/ImportHowTo.vue'
import LocalPager from '@/Components/Data/LocalPager.vue'

// ?tab= of the section row -> the view shown (first view when none is given).
const VIEWS = { dashboard: 'Dashboard', variables: 'Variables', entry: 'Data Entry', scenarios: 'Scenario Assumptions', import: 'Import / Export' }
const KEYS = Object.fromEntries(Object.entries(VIEWS).map(([k, v]) => [v, k]))
const viewFromUrl = () => VIEWS[new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '').get('tab')] || 'Dashboard'

export default {
  components: { AppLayout, Link, FileDrop, ImportHowTo, LocalPager },
  props: { series: Array, selected: Number, observations: Array, batches: Array, sets: Array, canManage: Boolean, defaultCountry: String },
  data() {
    return { tabs: ['Dashboard', 'Variables', 'Data Entry', 'Scenario Assumptions', 'Import / Export'], tab: viewFromUrl(), sel: this.selected,
      wb: { series_code: '', country: this.defaultCountry, from: 2000, to: new Date().getFullYear() }, imf: { series_code: '', file: null }, rbm: { file: null },
      m: { period: '', value: '', is_forecast: false, reason: '' }, batchPage: 1, previewData: null, previewError: null, previewSource: null }
  },
  computed: {
    pagedBatches() { return (this.batches || []).slice((this.batchPage - 1) * 15, this.batchPage * 15) },
    tabItems() {
      return [
        { key: 'Dashboard', label: 'Dashboard', count: this.series.length },
        { key: 'Variables', label: 'Variables', count: this.series.length },
        { key: 'Data Entry', label: 'Data entry', count: (this.observations || []).length },
        { key: 'Scenario Assumptions', label: 'Scenario assumptions', count: (this.sets || []).length },
        { key: 'Import / Export', label: 'Import / export', count: (this.batches || []).length },
      ]
    },
  },
  methods: {
    batchSeries(b) {
      try { return JSON.parse(b.series || '[]').map(x => x.code).join(', ') } catch (e) { return '' }
    },
    select(s) { this.sel = s.id; this.tab = 'Data Entry'; this.go() },
    go() { router.get(route('macro-statistics.index'), { series: this.sel, tab: KEYS[this.tab] }, { preserveState: true, preserveScroll: true, only: ['observations', 'selected', 'tabCounts', 'menu'] }) },
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
