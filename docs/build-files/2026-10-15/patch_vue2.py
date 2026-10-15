p = 'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/Dashboard.vue'
s = open(p, encoding='utf-8').read()
D = 'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/'

def rep(old, new):
    global s
    assert s.count(old) == 1, (s.count(old), old[:90])
    s = s.replace(old, new)

def selects(key, prop, fk, tk):
    return f'''                        <select v-model="filterForm.{key}_from" @change="applyChartRange('{prop}')" :class="rangeSelect" title="First period of this chart">
                            <option :value="null">Last 12 months</option>
                            <option value="all">From the start</option>
                            <option v-for="p in periods" :key="'{fk}' + p" :value="p">From {{{{ periodLabel(p) }}}}</option>
                        </select>
                        <select v-model="filterForm.{key}_to" @change="applyChartRange('{prop}')" :class="rangeSelect" title="Last period of this chart">
                            <option :value="null">To {{{{ periodLabel(selectedPeriod) }}}}</option>
                            <option v-for="p in periods" :key="'{tk}' + p" :value="p">To {{{{ periodLabel(p) }}}}</option>
                        </select>
'''

# stress panel
i = s.index('''                <div class="maiic-panel min-w-0 p-6">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="font-bold text-gray-900 dark:text-slate-100">Base and stressed ECL by stage</h3>''')
j = s.index('''                <div class="maiic-panel min-w-0 p-6">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="font-bold text-gray-900 dark:text-slate-100">Observed default rate</h3>''')
s = s[:i] + open(D + 'stress_panel.html', encoding='utf-8').read() + '\n' + s[j:]

# mix panel: own range
rep('''<p class="text-xs text-gray-400">{{ trendRangeLabel }}<span v-if="selectedPortfolioName"> &middot; {{ selectedPortfolioName }}</span> &middot; the six largest in the last month, the rest as one line</p>''',
    '''<p class="text-xs text-gray-400">{{ rangeLabel(eclMixTrend && eclMixTrend.periods) }}<span v-if="selectedPortfolioName"> &middot; {{ selectedPortfolioName }}</span> &middot; the six largest in the last month, the rest as one line</p>''')
rep('''                    <div class="flex flex-wrap items-center gap-2">
                        <span v-if="mixHasData"''', '''                    <div class="flex flex-wrap items-center gap-2">
''' + selects('pf', 'eclMixTrend', 'pf', 'pt') + '''                        <span v-if="mixHasData"''')

# default panel: own range
rep('''<p class="text-xs text-gray-400">{{ trendRangeLabel }}<span v-if="selectedPortfolioName"> &middot; {{ selectedPortfolioName }} against the whole book</span>''',
    '''<p class="text-xs text-gray-400">{{ rangeLabel((defaultRates && defaultRates.rows || []).map(r => r.period)) }}<span v-if="selectedPortfolioName"> &middot; {{ selectedPortfolioName }} against the whole book</span>''')
rep('''                        <div class="flex overflow-hidden rounded-lg border border-gray-200 text-xs dark:border-slate-600">
                            <button @click="defaultView = 'chart'"''', '''                        <div class="flex flex-wrap items-center gap-2">
''' + selects('dr', 'defaultRates', 'df', 'dt').replace('\n                        <', '\n                            <').replace('                        <select', '                            <select', 2) + '''                        <div class="flex overflow-hidden rounded-lg border border-gray-200 text-xs dark:border-slate-600">
                            <button @click="defaultView = 'chart'"''')
rep('''                            <button @click="defaultView = 'table'" :class="tgl(defaultView === 'table')" class="px-3 py-1">Table</button>
                        </div>
                    </div>''', '''                            <button @click="defaultView = 'table'" :class="tgl(defaultView === 'table')" class="px-3 py-1">Table</button>
                        </div>
                        </div>
                    </div>''')

# trend selects use the shared class
s = s.replace('''class="rounded-md border border-maiic-200 bg-maiic-50 py-1 pl-2 pr-7 text-xs font-semibold text-maiic-800 focus:border-maiic-500 focus:ring-maiic-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"''', ''':class="rangeSelect"''')

# ---- script ----
rep('''    trend_from: props.trendFrom ?? null,
    trend_to: props.trendTo ?? null,
})''', '''    trend_from: props.trendFrom ?? null,
    trend_to: props.trendTo ?? null,
    // each chart below the summary keeps its own range
    pf_from: props.eclMixTrend?.range?.from ?? null,
    pf_to: props.eclMixTrend?.range?.to ?? null,
    st_from: props.scenarioEcl?.range?.from ?? null,
    st_to: props.scenarioEcl?.range?.to ?? null,
    dr_from: props.defaultRates?.range?.from ?? null,
    dr_to: props.defaultRates?.range?.to ?? null,
})
const rangeSelect = 'rounded-md border border-maiic-200 bg-maiic-50 py-1 pl-2 pr-7 text-xs font-semibold text-maiic-800 focus:border-maiic-500 focus:ring-maiic-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200\'''')
rep('''    router.get(route('dashboard'), query, { preserveState: false, preserveScroll: true })
}''', '''    router.get(route('dashboard'), query, { preserveState: false, preserveScroll: true })
}

// One chart's own from / to: reload that chart's prop alone, keep the page
// where it is (the other charts and the scroll position do not move).
function applyChartRange(prop) {
    const query = {}
    Object.entries(filterForm.value).forEach(([k, v]) => { if (v) query[k] = v })
    router.get(route('dashboard'), query, { preserveState: true, preserveScroll: true, replace: true, only: [prop] })
}

// "Jan 2026 to Aug 2026" for a list of periods (oldest first)
function rangeLabel(list) {
    const l = (list || []).filter(Boolean)
    if (!l.length) return 'No calculated periods in the selected range'
    return l.length === 1 ? periodLabel(l[0]) : `${periodLabel(l[0])} to ${periodLabel(l[l.length - 1])}`
}
// consecutive runs of the range's periods: "Jan 2026 to Jul 2026, Sep 2026"
function runsLabel(all, subset) {
    const want = new Set(subset)
    const runs = []
    let cur = null
    for (const p of all) {
        if (want.has(p)) { cur ? cur.push(p) : runs.push(cur = [p]) } else { cur = null }
    }
    return runs.map(r => r.length === 1 ? periodLabel(r[0]) : periodLabel(r[0]) + ' to ' + periodLabel(r[r.length - 1])).join(', ')
}''')

# scenario computed: replace the single-period helpers
i = s.index('const scenarioHasData = computed(')
j = s.index('const defaultHasData = computed(')
s = s[:i] + '''const scenarioStage = ref(3)
const stageOptions = [{ value: 3, label: 'All stages' }, { value: 0, label: 'Stage 1' }, { value: 1, label: 'Stage 2' }, { value: 2, label: 'Stage 3' }]
const scenarioHasData = computed(() => !!(props.scenarioEcl && (props.scenarioEcl.periods || []).length))
// mild to severe, then the weighted figure; a name outside the four keeps its place after them
const SCEN_RANK = { Upside: 0, Base: 1, Downside: 2, Severe: 3 }
const scenarioNames = computed(() => {
    const names = []
    Object.values(props.scenarioEcl?.by_period || {}).forEach(r => (r.series || []).forEach(x => { if (!names.includes(x.name)) names.push(x.name) }))
    const w = (n) => n === 'Probability-weighted' ? 9 : (SCEN_RANK[n] ?? 5)
    return names.sort((a, b) => w(a) - w(b))
})
const scenarioWeights = computed(() => {
    const out = {}
    Object.values(props.scenarioEcl?.by_period || {}).forEach(r => (r.series || []).forEach(x => { if (x.weight != null) out[x.name] = x.weight }))
    return out
})
function scenarioValue(p, name) {
    const x = (props.scenarioEcl?.by_period?.[p]?.series || []).find(s => s.name === name)
    return x ? x.values[scenarioStage.value] : null
}
function scenarioCell(p, name) {
    const v = scenarioValue(p, name)
    return v === null ? '-' : formatAmount(v)
}
const scenarioMissing = computed(() => {
    const s = props.scenarioEcl
    if (!s || !(s.missing || []).length) return null
    return runsLabel(s.range_periods || [], s.missing)
})
const scenarioTies = computed(() => {
    const rows = Object.entries(props.scenarioEcl?.by_period || {}).filter(([, r]) => r.ties !== null && r.ties !== undefined)
    if (!rows.length) return null
    const differs = rows.filter(([, r]) => !r.ties).map(([p]) => p)
    return { ok: differs.length === 0, differs }
})
function sourceLabel(r) {
    if (!r) return ''
    if (r.source === 'month_end') return 'Forward-looking month-end record ' + r.record.id + ' (' + (r.record.status === 'SIGNED_OFF' ? 'signed off' : 'recorded, not yet signed off') + ')'
    return 'Saved stress runs of the scenario set'
}
const scenarioSourceLine = computed(() => {
    const by = props.scenarioEcl?.by_period || {}
    const groups = {}
    Object.entries(by).forEach(([p, r]) => { const k = sourceLabel(r); (groups[k] = groups[k] || []).push(p) })
    return Object.entries(groups).map(([k, ps]) => k + ': ' + runsLabel(props.scenarioEcl.range_periods || [], ps)).join('; ') + '.'
})
const scenarioSetLines = computed(() => {
    const seen = {}
    Object.values(props.scenarioEcl?.by_period || {}).forEach(r => { if (r.set) seen[r.set.id] = r.set })
    return Object.values(seen).map(set => {
        const who = set.bootstrap
            ? 'approved under the system bootstrap label (automated data-readiness, not a MAIIC approval)'
            : set.status === 'APPROVED' || set.status === 'LOCKED'
                ? 'approved' + (set.approver ? ' by ' + set.approver : '')
                : String(set.status || '').toLowerCase()
        return 'Scenario set ' + set.id + ' v' + set.version + ', ' + set.name + ': ' + who + '.'
    })
})

''' + s[j:]

# renderScenario: time series by period
i = s.index('function renderScenario(n) {')
j = s.index('function renderDefaults(n) {')
s = s[:i] + '''function renderScenario(n) {
    const s = props.scenarioEcl
    if (!scenarioHasData.value) return make('scenario', null)
    const sc = SCEN()
    const cat = CAT()
    let spare = 6
    const st = scenarioStage.value
    const loansAt = (i) => (s.by_period[s.periods[i]]?.loans || [])[st]
    make('scenario', scenarioChart.value, {
        type: 'bar',
        data: {
            labels: s.periods.map(periodLabel),
            datasets: scenarioNames.value.map(name => {
                const weighted = name === 'Probability-weighted'
                const colour = weighted ? n.line : (sc[name] || cat[spare++ % cat.length])
                const w = scenarioWeights.value[name]
                return { label: weighted ? name : name + (w != null ? ' (' + w.toFixed(0) + '%)' : ''), data: s.periods.map(p => scenarioValue(p, name)),
                    backgroundColor: colour, borderColor: n.surface, borderWidth: { left: 1, right: 1, top: 0, bottom: 0 }, borderRadius: 4, borderSkipped: 'bottom', maxBarThickness: 30 }
            }),
        },
        options: {
            maintainAspectRatio: false,
            animation: animation(),
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { beginAtZero: true, border: { display: false }, grid: { color: n.grid }, ticks: { color: n.text, font: { size: 11 }, callback: moneyTick } },
                x: { border: { display: false }, grid: { display: false }, ticks: { color: n.text, font: { size: 11 } } },
            },
            plugins: {
                legend: legend(n),
                tooltip: {
                    ...tooltip,
                    callbacks: {
                        label: (item) => item.parsed.y === null ? null : ' ' + item.dataset.label + ': ' + formatAmount(item.parsed.y),
                        footer: (items) => items.length && loansAt(items[0].dataIndex) != null ? formatCount(loansAt(items[0].dataIndex)) + ' loans' : '',
                    },
                },
            },
        },
    })
}

''' + s[j:]

# watchers: per chart, after the DOM updates (a partial reload can add a canvas)
rep('''onMounted(renderCharts)
watch(() => [props.summary, props.eclTrends, props.eclMixTrend, props.scenarioEcl, props.defaultRates], renderCharts)
watch(theme.isDark, renderCharts)
watch(breakdownView, () => renderBreakdown(N()))''', '''onMounted(renderCharts)
watch(() => [props.summary, props.eclTrends], renderCharts)
watch(theme.isDark, renderCharts)
watch(breakdownView, () => renderBreakdown(N()))
watch(() => props.eclMixTrend, () => renderMix(N()), { flush: 'post' })
watch(() => [props.scenarioEcl, scenarioStage.value], () => renderScenario(N()), { flush: 'post' })
watch(() => props.defaultRates, () => renderDefaults(N()), { flush: 'post' })''')

open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
