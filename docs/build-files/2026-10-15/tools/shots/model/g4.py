import os, re, sys
sys.path.insert(0, os.path.dirname(__file__))
from vh import load, save, rep, rx
os.chdir(r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages')

p='Macro/Index.vue'; s=load(p)
s=rep(s,'''description="The macroeconomic series the forward-looking model reads, with the source each came from: the World Bank for actuals, the IMF World Economic Outlook for the forecast years, the Reserve Bank by file for the policy rate, and the PLR from the landing zone (spec v4 section 13)"''','''description="The economic series the forward-looking model reads, and where each came from (World Bank, IMF, Reserve Bank, the loan books)"''')
s=rep(s,'''      <div class="flex flex-wrap gap-1 border-b border-gray-200 dark:border-slate-700">
        <button v-for="t in tabs" :key="t" @click="tab = t" class="px-4 py-2 text-sm font-semibold" :class="tab === t ? 'border-b-2 border-teal-500 text-teal-700 dark:text-teal-300' : 'text-gray-500'">{{ t }}</button>
      </div>''','''      <PageTabs v-model="tab" :tabs="tabItems" />''')
s=rep(s,'''<div v-if="tab === 'Dashboard'" class="grid grid-cols-1 gap-4 md:grid-cols-3">''','''<div v-if="tab === 'Dashboard'" class="grid grid-cols-1 gap-3 md:grid-cols-3">
        <div v-if="!series.length" class="maiic-panel maiic-empty md:col-span-3">No macro series are set up yet.</div>''')
s=rep(s,'''<div v-for="s in series" :key="s.id" class="maiic-kpi cursor-pointer" style="--accent:#0d9488" @click="select(s)">''','''<div v-for="s in series" :key="s.id" class="maiic-kpi !py-3 cursor-pointer hover:shadow-md" style="--accent:#15803d" :title="'Open ' + s.code + ' in Data Entry'" @click="select(s)">''')
s=rep(s,'''{{ s.code }} · {{ s.unit }}''','''{{ s.code }}, {{ s.unit }}''')
s=rep(s,'''<span v-if="s.forecasts"> · {{ s.forecasts }} forecast</span>''','''<span v-if="s.forecasts">, {{ s.forecasts }} forecast</span>''')
s=rep(s,'''<p class="p-3 text-xs text-gray-500">A series with no code says so; it is not an error. Codes are a seeder change (MacroSeriesSeeder), not a code change. The legacy editor remains at <Link :href="route('macro-statistics.legacy')" class="underline">Macro Elements</Link>.</p>''','''<p class="p-3 text-xs text-gray-500">A blank source code means the series has none at that source; it is not an error. The older editor is still available at <Link :href="route('macro-statistics.legacy')" class="underline">Macro Elements</Link>.</p>''')
s=rep(s,'''<div class="text-xs text-gray-500">{{ previewData.series_code }} · {{ previewData.source }} · {{ previewData.address }} · {{ previewData.rows.length }} rows<span v-if="previewData.estimates_start_after"> · estimates start after {{ previewData.estimates_start_after }}</span><span v-if="previewData.note" class="text-amber-700"> · {{ previewData.note }}</span></div>''','''<div class="text-xs text-gray-500">{{ previewData.series_code }}, {{ previewData.source }}, {{ previewData.address }}, {{ previewData.rows.length }} rows<span v-if="previewData.estimates_start_after">, estimates start after {{ previewData.estimates_start_after }}</span><span v-if="previewData.note" class="text-amber-700">. {{ previewData.note }}</span></div>''')
s=s.replace('<option value="">series\u2026</option>','<option value="">Series</option>')
s=rep(s,"import { Link, router } from '@inertiajs/vue3'","import { Link, router } from '@inertiajs/vue3'\nimport PageTabs from '@/Components/Maiic/PageTabs.vue'")
s=rep(s,"components: { AppLayout, Link },","components: { AppLayout, Link, PageTabs },")
s=rep(s,'''  methods: {
    select(s)''','''  computed: {
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
    select(s)''')
save(p,s)

p='FLI/ExternalCalculations/Index.vue'; s=load(p)
s=rep(s,'''        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                FLI External Calculations
            </h2>
        </template>''','''        <template #header>
            <div>
                <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
                    <span>IFRS 9 Model Setup</span><span>/</span><span>Scenarios &amp; Overlays</span><span>/</span><span class="font-medium text-maiic-700">External Calculations</span>
                </div>
                <h2 class="text-xl font-semibold text-gray-800">External Calculations</h2>
                <p class="mt-1 text-sm text-gray-600">The older one-slope forward-looking calculation, kept for reference</p>
            </div>
        </template>''')
s=rep(s,'''<!-- Retired in favour of FLI Adjustments (system audit of 9 October 2026, finding M6) -->''','''<!-- Retired in favour of FLI Adjustments -->''')
s=rep(s,'''<div class="py-12">''','''<div>''')
save(p,s)
print('ok')
