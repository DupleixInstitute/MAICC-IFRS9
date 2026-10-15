import os, re, sys
sys.path.insert(0, os.path.dirname(__file__))
from vh import load, save, rep, rx
os.chdir(r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/FLI')

p='Correlation.vue'; s=load(p)
s=rep(s,'''description="Which economic series, at what lag, in what form, explains the credit losses, and how well: every driver against every proxy over the lag grid, ranked, with the guardrail's verdict on each pair and the regression engine's fit on the candidates (spec v4 section 14.4)"''','''description="Which economic series, at what lag, best explains the credit losses: every driver tested against every loss measure, ranked"''')
s=rep(s,'''<select v-model="form.period" class="maiic-select text-sm">''','''<select v-model="form.period" class="maiic-select !w-36 text-sm" aria-label="Period">''')
s=rep(s,'''{{ running ? 'Running…' : 'Run the finder for ' + form.period }}''','''{{ running ? 'Running...' : 'Run the finder for ' + form.period }}''')
s=rep(s,'''        <Link :href="route('fli-adjustments.index', { period: form.period })" class="secondary-btn text-sm">FLI Adjustments</Link>
''','')
s=rep(s,'''<div class="space-y-6">
      <div class="grid grid-cols-1 gap-4 md:grid-cols-4">''','''<div class="space-y-4">
      <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">''')
s=s.replace('<div class="maiic-kpi" style="--accent:#0284c7">','<div class="maiic-kpi !py-3" style="--accent:#0284c7">')
s=s.replace('class="maiic-kpi-value"','class="text-xl font-bold"')
s=rep(s,'''{{ run ? ('run #' + run.id + ' at ' + run.run_at) : 'none yet' }}''','''{{ run ? ('Run #' + run.id + ' at ' + run.run_at) : 'None yet' }}''')
s=rep(s,'''{{ series.macro }} × {{ series.proxies }}</div><div class="text-xs text-gray-500">drivers × credit-loss proxies</div>''','''{{ series.macro }} by {{ series.proxies }}</div><div class="text-xs text-gray-500">drivers by credit-loss measures</div>''')
s=rep(s,'''<span v-for="(n, v) in byVerdict" :key="v" class="mr-3">{{ v }} {{ n }}</span>''','''<span v-for="(n, v) in byVerdict" :key="v" class="mr-3">{{ verdictLabel(v) }} {{ n }}</span>''')
# last run output -> disclosure
s=rx(s,r'<div v-if="lastRun" class="maiic-panel p-5 text-sm">\s*<h3 class="text-base font-bold">The finder\'s last run from this screen for \{\{ period \}\}</h3>\s*<pre',
'''<details v-if="lastRun" class="text-xs text-gray-600">
        <summary class="cursor-pointer select-none font-semibold text-maiic-700 hover:underline">Output of the finder's last run for {{ period }}</summary>
        <pre''')
s=rx(s,r'(\{\{ lastRun\.output \}\}</pre>)\s*</div>', r'\1\n      </details>')
s=rep(s,'''<div class="border-b border-gray-200 p-5 dark:border-slate-700"><h3 class="text-base font-bold">Ranked suggestions of the latest sweep</h3><p class="text-sm text-gray-500">One per driver and proxy at its best lag, strongest first. A pair the guardrail rejects shows why; it may be studied but cannot be approved as a model.</p></div>''','''<div class="border-b border-gray-200 px-5 py-4 dark:border-slate-700"><h3 class="font-semibold text-gray-900">Ranked suggestions</h3><p class="text-xs text-gray-500">{{ suggestions.length }} pair(s), one per driver and measure at its best lag, strongest first. A rejected pair shows why and cannot be approved as a model.</p></div>''')
s=rep(s,'''<tr v-for="(s, i) in suggestions" :key="s.id"''','''<tr v-for="(s, i) in pagedSuggestions" :key="s.id"''')
s=rep(s,'''<td class="num">{{ i + 1 }}</td>''','''<td class="num">{{ (page - 1) * 15 + i + 1 }}</td>''')
s=rep(s,'''<span class="maiic-badge" :class="badge(s.verdict)">{{ s.verdict }}</span>''','''<span class="maiic-badge" :class="badge(s.verdict)">{{ verdictLabel(s.verdict) }}</span>''')
s=rep(s,'''<tr v-if="!suggestions.length"><td colspan="9" class="maiic-empty">No sweep for this period: run the finder.</td></tr></tbody></table></div>
      </div>''','''<tr v-if="!suggestions.length"><td colspan="9" class="maiic-empty">No sweep for this period yet. Use <strong>Run the finder</strong> at the top right.</td></tr></tbody></table></div>
        <RowPager v-model="page" :total="suggestions.length" class="border-t border-gray-100" />
      </div>''')
# fits panel -> disclosure
s=rep(s,'''      <div class="maiic-panel">
        <div class="border-b border-gray-200 p-5 dark:border-slate-700"><h3 class="text-base font-bold">The regression engine's fits for {{ period }}</h3><p class="text-sm text-gray-500">Each candidate fitted and applied or declined by the guardrail: enough observations, the expected sign, R² at or above the cut-off, significance at the governed alpha. An applied fit goes forward on FLI Adjustments under maker-checker.</p></div>''','''      <details class="maiic-panel group">
        <summary class="flex cursor-pointer select-none items-center justify-between px-5 py-4"><span><span class="font-semibold text-gray-900">Regression fits for {{ period }}</span><span class="ml-2 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ fits.length }}</span><span class="ml-2 text-xs text-gray-500">applied or declined on observations, sign, R-squared and significance; an applied fit is approved on the Regression tab</span></span><span class="text-xs font-semibold text-maiic-700 group-open:hidden">Show</span><span class="hidden text-xs font-semibold text-maiic-700 group-open:inline">Hide</span></summary>''')
s=rep(s,'''<span class="maiic-badge" :class="f.verdict === 'applied' ? 'maiic-badge-green' : 'maiic-badge-red'">{{ f.verdict }}</span>''','''<span class="maiic-badge" :class="f.verdict === 'applied' ? 'maiic-badge-green' : 'maiic-badge-red'">{{ f.verdict === 'applied' ? 'Applied' : 'Declined' }}</span>''')
s=rep(s,'''          <tr v-if="!fits.length"><td colspan="13" class="maiic-empty">No fits for this period.</td></tr></tbody></table></div>
      </div>''','''          <tr v-if="!fits.length"><td colspan="13" class="maiic-empty">No fits for this period.</td></tr></tbody></table></div>
      </details>''')
s=rep(s,'''      <div class="maiic-panel">
        <div class="border-b border-gray-200 p-5 dark:border-slate-700"><h3 class="text-base font-bold">Sweeps</h3><p class="text-sm text-gray-500">Every sweep is an immutable run with the hash of the inputs it read; a later sweep is a new run, not an edit.</p></div>''','''      <details class="maiic-panel group">
        <summary class="flex cursor-pointer select-none items-center justify-between px-5 py-4"><span><span class="font-semibold text-gray-900">Sweeps</span><span class="ml-2 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">{{ runs.length }}</span><span class="ml-2 text-xs text-gray-500">every sweep is kept with a fingerprint of its inputs; a later sweep is a new run, not an edit</span></span><span class="text-xs font-semibold text-maiic-700 group-open:hidden">Show</span><span class="hidden text-xs font-semibold text-maiic-700 group-open:inline">Hide</span></summary>''')
s=rep(s,'''          <tr v-if="!runs.length"><td colspan="7" class="maiic-empty">No sweep has run.</td></tr></tbody></table></div>
      </div>''','''          <tr v-if="!runs.length"><td colspan="7" class="maiic-empty">No sweep has run.</td></tr></tbody></table></div>
      </details>''')
s=rep(s,'''import { Link, router } from '@inertiajs/vue3'
''','''import { Link, router } from '@inertiajs/vue3'
import RowPager from '@/Components/Maiic/RowPager.vue'
''')
s=rep(s,'''components: { AppLayout, Link },''','''components: { AppLayout, Link, RowPager },''')
s=rep(s,'''data() { return { form: { period: this.period }, running: false } },''','''data() { return { form: { period: this.period }, running: false, page: 1 } },
  computed: {
    pagedSuggestions() { return this.suggestions.slice((this.page - 1) * 15, this.page * 15) },
  },''')
s=rep(s,'''    n(v, d) {''','''    verdictLabel(v) { return { recommended: 'Recommended', usable_with_caveat: 'Usable with caveat', rejected: 'Rejected' }[v] || v },
    n(v, d) {''')
save(p,s)
print('ok')
