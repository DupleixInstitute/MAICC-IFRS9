const breakdownMode = ref('table')
const breakdownChartHeight = computed(() => Math.max(180, (breakdown.value.rows || []).length * 38 + 70))

// ---- the three charts in place of the operations row ----------------------
const mixView = ref('chart')
const scenarioView = ref('chart')
const defaultView = ref('chart')

const mixHasData = computed(() => !!(props.eclMixTrend && (props.eclMixTrend.periods || []).length && (props.eclMixTrend.series || []).length))
const mixTitle = computed(() => props.eclMixTrend?.dimension === 'product' ? 'ECL by product group over months' : 'ECL by portfolio over months')
const mixDiffersTitle = computed(() => (props.eclMixTrend?.differs || [])
    .map(d => periodLabel(d.period) + ': ' + formatAmount(d.series_total) + ' against ' + formatAmount(d.trend_total)).join('; '))

const scenarioHasData = computed(() => !!(props.scenarioEcl && props.scenarioEcl.source && (props.scenarioEcl.series || []).length))
const scenarioSource = computed(() => {
    const s = props.scenarioEcl
    if (!s || !s.source) return null
    if (s.source === 'month_end') {
        const st = s.record?.status === 'SIGNED_OFF' ? 'signed off' : 'recorded, not yet signed off'
        return 'forward-looking month-end record ' + s.record.id + ' (' + st + ')'
    }
    return 'saved stress runs of the scenario set'
})
const scenarioSetLine = computed(() => {
    const set = props.scenarioEcl?.set
    if (!set) return null
    const who = set.bootstrap
        ? 'approved by the system bootstrap label (automated data-readiness, not a MAIIC approval)'
        : set.status === 'APPROVED' || set.status === 'LOCKED'
            ? 'approved' + (set.approver ? ' by ' + set.approver : '')
            : String(set.status || '').toLowerCase()
    return 'Scenario set ' + set.id + ' v' + set.version + ', ' + set.name + ': ' + who + '.'
})
function vsBase(s) {
    const base = (props.scenarioEcl?.series || []).find(x => x.is_base)
    if (!base || s === base || !base.values[3]) return '-'
    const d = (s.values[3] - base.values[3]) / base.values[3] * 100
    return (d > 0 ? '+' : '') + d.toFixed(1) + '%'
}

const defaultHasData = computed(() => (props.defaultRates?.rows || []).some(r => ['monthly', 'annual'].some(k => r[k]?.book?.rate != null || r[k]?.portfolio?.rate != null)))
function rateCell(x) {
    if (!x || x.rate === null || x.rate === undefined) return '-'
    return x.rate.toFixed(2) + '% (' + formatCount(x.defaults) + ' of ' + formatCount(x.loans) + ')'
}
function rateTitle(x) {
    if (!x) return 'No performing loan at the start of the window'
    return x.window + ' (' + x.months + (x.months === 1 ? ' month' : ' months') + '): ' + formatCount(x.defaults) + ' of ' + formatCount(x.loans) + ' loans, '
        + formatAmount(x.default_balance) + ' of ' + formatAmount(x.balance)
}

// Categorical hues in a fixed order (validated for colour-blind separation in
// light and dark); "Other" is always the neutral grey.
const CAT = () => theme.isDark.value
    ? ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181', '#008300', '#9085e9', '#e66767']
    : ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6', '#e34948']
const OTHER = () => theme.isDark.value ? '#64748b' : '#94a3b8'
// Scenario colours follow the scenario, from mild to severe.
const SCEN = () => theme.isDark.value
    ? { Upside: '#199e70', Base: '#3987e5', Downside: '#c98500', Severe: '#d55181' }
    : { Upside: '#1baf7a', Base: '#2a78d6', Downside: '#eda100', Severe: '#e34948' }

const pieView = ref('chart')
const trendView = ref('chart')
const pieChart = ref(null)
const trendChart = ref(null)
const breakdownChart = ref(null)
const mixChart = ref(null)
const scenarioChart = ref(null)
const defaultChart = ref(null)
const instances = {}

const tooltip = { backgroundColor: '#0b2b1a', titleColor: '#fbbf24', bodyColor: '#ffffff', padding: 12, cornerRadius: 10 }
const animation = () => reducedMotion() ? false : { duration: 700 }
const legend = (n) => ({ position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', padding: 14, color: n.legend, font: { size: 11 } } })
const moneyTick = (v) => money(v).replace(currencyCode.value + ' ', '')

function make(key, canvas, config) {
    if (instances[key]) { instances[key].destroy(); delete instances[key] }
    if (canvas) instances[key] = new Chart(canvas.getContext('2d'), config)
}

function renderPie(n) {
    const e = summary.value.total_eads || [0, 0, 0]
    const loans = summary.value.loans_by_stage || [0, 0, 0]
    make('pie', pieChart.value, {
        type: 'doughnut',
        data: {
            labels: ['Stage 1', 'Stage 2', 'Stage 3'],
            datasets: [{ data: e, backgroundColor: [C.green, C.gold, C.red], borderWidth: 3, borderColor: n.surface, hoverOffset: 10 }],
        },
        options: {
            maintainAspectRatio: false,
            animation: animation(),
            cutout: '62%',
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', padding: 16, color: n.legend, font: { size: 12 } } },
                tooltip: {
                    ...tooltip,
                    callbacks: {
                        label: (item) => {
                            const total = e[0] + e[1] + e[2]
                            const share = total ? ((item.parsed / total) * 100).toFixed(1) : 0
                            return ' ' + formatAmount(item.parsed) + ' (' + share + '%)'
                        },
                        afterLabel: (item) => ' ' + formatCount(loans[item.dataIndex] || 0) + ' loan' + (Number(loans[item.dataIndex]) === 1 ? '' : 's'),
                    },
                },
            },
        },
    })
}

function renderTrend(n) {
    const t = props.eclTrends || []
    const coverage = t.map(i => Number(i.ecl_percentage || 0))
    const peak = coverage.length ? Math.max(...coverage) : 0
    // Two bands: the stacked ECL bars use the lower ~60% of the chart and
    // the coverage line rides above them, so the line never runs through
    // the bars. Each keeps its own scale (left currency, right %). The
    // exposure is a light area on its own (hidden) scale behind both; its
    // value is in the tooltip and the table.
    const barPeak = t.length ? Math.max(...t.map(i => (i.ecl_by_stage || []).reduce((a, b) => a + Number(b || 0), 0))) : 0
    const eadPeak = t.length ? Math.max(...t.map(i => Number(i.total_ead || 0))) : 0
    const eadLine = theme.isDark.value ? '#64748b' : '#94a3b8'
    make('trend', trendChart.value, {
        data: {
            labels: t.map(i => periodLabel(i.period)),
            datasets: [
                { type: 'bar', order: 1, label: 'Stage 1 ECL', data: t.map(i => (i.ecl_by_stage || [])[0] || 0), backgroundColor: C.green, stack: 'ecl', yAxisID: 'y', borderRadius: 3, maxBarThickness: 56 },
                { type: 'bar', order: 1, label: 'Stage 2 ECL', data: t.map(i => (i.ecl_by_stage || [])[1] || 0), backgroundColor: C.gold, stack: 'ecl', yAxisID: 'y', borderRadius: 3, maxBarThickness: 56 },
                { type: 'bar', order: 1, label: 'Stage 3 ECL', data: t.map(i => (i.ecl_by_stage || [])[2] || 0), backgroundColor: C.red, stack: 'ecl', yAxisID: 'y', borderRadius: 3, maxBarThickness: 56 },
                {
                    type: 'line', label: 'Coverage %', data: coverage, yAxisID: 'y1', order: 0,
                    borderColor: n.line, backgroundColor: n.line, borderWidth: 3, tension: 0.35,
                    pointRadius: t.map(i => i.period === props.selectedPeriod ? 6 : 3.5),
                    pointBackgroundColor: t.map(i => i.period === props.selectedPeriod ? '#fbbf24' : n.surface),
                    pointBorderColor: n.line, pointBorderWidth: 2,
                },
                {
                    type: 'line', label: 'Exposure (EAD)', data: t.map(i => Number(i.total_ead || 0)), yAxisID: 'y2', order: 2,
                    borderColor: eadLine, backgroundColor: theme.isDark.value ? 'rgba(100, 116, 139, 0.16)' : 'rgba(148, 163, 184, 0.16)',
                    fill: 'origin', borderWidth: 1.5, borderDash: [4, 3], tension: 0.35, pointRadius: 0, pointHoverRadius: 4,
                },
            ],
        },
        options: {
            maintainAspectRatio: false,
            animation: animation(),
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: {
                    stacked: true, beginAtZero: true, suggestedMax: barPeak > 0 ? barPeak * 1.65 : undefined,
                    border: { display: false }, grid: { color: n.grid },
                    ticks: { color: n.text, font: { size: 11 }, callback: moneyTick },
                },
                y1: {
                    position: 'right', beginAtZero: true, suggestedMax: peak > 0 ? Math.ceil(peak * 1.1) : 10,
                    border: { display: false }, grid: { display: false },
                    ticks: { color: n.line, font: { size: 11 }, callback: (v) => v + '%' },
                },
                y2: { display: false, beginAtZero: true, suggestedMax: eadPeak > 0 ? eadPeak * 1.08 : undefined },
                x: { stacked: true, border: { display: false }, grid: { display: false }, ticks: { color: n.text, font: { size: 11 }, maxRotation: 40 } },
            },
            plugins: {
                legend: legend(n),
                tooltip: {
                    ...tooltip,
                    callbacks: {
                        label: (item) => item.dataset.yAxisID === 'y1'
                            ? ' Coverage: ' + Number(item.parsed.y).toFixed(2) + '%'
                            : ' ' + item.dataset.label + ': ' + formatAmount(item.parsed.y),
                    },
                },
            },
        },
    })
}

// "Where the ECL sits" as horizontal bars: ECL per row on the bottom axis,
// coverage (and the RBM minimum on the RBM view) as markers on the top axis.
function renderBreakdown(n) {
    const rows = breakdown.value.rows || []
    const datasets = [
        { type: 'bar', label: 'ECL', data: rows.map(r => Number(r.ecl || 0)), xAxisID: 'x', backgroundColor: rows.map(r => r.other ? OTHER() : C.green), borderRadius: 4, maxBarThickness: 22, order: 1 },
        { type: 'line', label: 'Coverage %', data: rows.map(r => r.coverage === null ? null : Number(r.coverage)), xAxisID: 'x1', showLine: false,
          pointStyle: 'rectRot', pointRadius: 6, pointHoverRadius: 8, pointBackgroundColor: n.line, pointBorderColor: n.surface, pointBorderWidth: 2, borderColor: n.line, backgroundColor: n.line, order: 0 },
    ]
    if (breakdownView.value === 'rbm') {
        datasets.push({ type: 'line', label: 'RBM minimum %', data: rows.map(r => r.minimum == null ? null : Number(r.minimum) * 100), xAxisID: 'x1', showLine: false,
            pointStyle: 'line', rotation: 90, pointRadius: 9, pointBorderWidth: 3, borderColor: C.gold, backgroundColor: C.gold, pointBorderColor: C.gold, order: 0 })
    }
    const covPeak = Math.max(0, ...rows.map(r => Number(r.coverage || 0)), ...(breakdownView.value === 'rbm' ? rows.map(r => Number(r.minimum || 0) * 100) : []))
    make('breakdown', breakdownChart.value, {
        data: { labels: rows.map(r => r.label.length > 34 ? r.label.slice(0, 33) + '...' : r.label), datasets },
        options: {
            indexAxis: 'y',
            maintainAspectRatio: false,
            animation: animation(),
            interaction: { mode: 'index', intersect: false, axis: 'y' },
            scales: {
                x: { beginAtZero: true, position: 'bottom', border: { display: false }, grid: { color: n.grid }, ticks: { color: n.text, font: { size: 11 }, callback: moneyTick } },
                x1: { beginAtZero: true, position: 'top', suggestedMax: covPeak > 0 ? Math.min(100, Math.ceil(covPeak * 1.1)) : 10, border: { display: false }, grid: { display: false },
                      ticks: { color: n.line, font: { size: 11 }, callback: (v) => v + '%' } },
                y: { border: { display: false }, grid: { display: false }, ticks: { color: n.text, font: { size: 11 } } },
            },
            plugins: {
                legend: legend(n),
                tooltip: {
                    ...tooltip,
                    callbacks: {
                        title: (items) => rows[items[0].dataIndex]?.label,
                        label: (item) => item.dataset.xAxisID === 'x1'
                            ? ' ' + item.dataset.label.replace(' %', '') + ': ' + Number(item.parsed.x).toFixed(2) + '%'
                            : ' ECL: ' + formatAmount(item.parsed.x) + ' (' + formatCount(rows[item.dataIndex]?.loans) + ' loans)',
                    },
                },
            },
        },
    })
}

function renderMix(n) {
    const m = props.eclMixTrend
    if (!mixHasData.value) return make('mix', null)
    const cat = CAT()
    let slot = 0
    make('mix', mixChart.value, {
        type: 'line',
        data: {
            labels: m.periods.map(periodLabel),
            datasets: m.series.map(s => {
                const colour = s.other ? OTHER() : cat[slot++ % cat.length]
                return { label: s.label, data: s.values, borderColor: colour, backgroundColor: colour, borderWidth: 2, tension: 0.3,
                    borderDash: s.other ? [5, 4] : [], pointRadius: 3, pointHoverRadius: 6, pointBackgroundColor: colour, pointBorderColor: n.surface, pointBorderWidth: 2 }
            }),
        },
        options: {
            maintainAspectRatio: false,
            animation: animation(),
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { beginAtZero: true, border: { display: false }, grid: { color: n.grid }, ticks: { color: n.text, font: { size: 11 }, callback: moneyTick } },
                x: { border: { display: false }, grid: { display: false }, ticks: { color: n.text, font: { size: 11 }, maxRotation: 40 } },
            },
            plugins: {
                legend: legend(n),
                tooltip: {
                    ...tooltip,
                    itemSort: (a, b) => b.parsed.y - a.parsed.y,
                    callbacks: {
                        label: (item) => ' ' + item.dataset.label + ': ' + formatAmount(item.parsed.y),
                        footer: (items) => items.length ? 'Total ECL: ' + formatAmount(m.totals[items[0].dataIndex]) : '',
                    },
                },
            },
        },
    })
}

function renderScenario(n) {
    const s = props.scenarioEcl
    if (!scenarioHasData.value) return make('scenario', null)
    const sc = SCEN()
    const cat = CAT()
    // mild to severe, then the weighted figure; unknown names take the next free hue
    const rank = { Upside: 0, Base: 1, Downside: 2, Severe: 3 }
    const ordered = [...s.series].sort((a, b) => (a.weighted ? 9 : (rank[a.name] ?? 5)) - (b.weighted ? 9 : (rank[b.name] ?? 5)))
    let spare = 6
    make('scenario', scenarioChart.value, {
        type: 'bar',
        data: {
            labels: ['Stage 1', 'Stage 2', 'Stage 3', 'Total'],
            datasets: ordered.map(x => {
                const colour = x.weighted ? n.line : (sc[x.name] || cat[spare++ % cat.length])
                return { label: x.weighted ? x.name : x.name + (x.weight !== null ? ' (' + x.weight.toFixed(0) + '%)' : ''), data: x.values,
                    backgroundColor: colour, borderColor: n.surface, borderWidth: { left: 1, right: 1, top: 0, bottom: 0 }, borderRadius: 4, borderSkipped: 'bottom', maxBarThickness: 26 }
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
                        label: (item) => ' ' + item.dataset.label + ': ' + formatAmount(item.parsed.y),
                        footer: (items) => items.length && (s.loans || []).length ? formatCount(s.loans[items[0].dataIndex]) + ' loans' : '',
                    },
                },
            },
        },
    })
}

function renderDefaults(n) {
    const d = props.defaultRates
    if (!defaultHasData.value) return make('default', null)
    const rows = d.rows
    const cat = CAT()
    const pick = (k, w) => rows.map(r => r[w]?.[k]?.rate ?? null)
    const line = (label, data, colour, dashed) => ({ label, data, borderColor: colour, backgroundColor: colour, borderWidth: 2, tension: 0.25, spanGaps: false,
        borderDash: dashed ? [6, 4] : [], pointRadius: 3.5, pointHoverRadius: 6, pointBackgroundColor: dashed ? n.surface : colour, pointBorderColor: colour, pointBorderWidth: 2 })
    const datasets = []
    if (props.selectedPortfolioId) {
        datasets.push(line((selectedPortfolioName.value || 'Portfolio') + ', month', pick('portfolio', 'monthly'), cat[1], false))
        datasets.push(line((selectedPortfolioName.value || 'Portfolio') + ', 12 months', pick('portfolio', 'annual'), cat[1], true))
    }
    datasets.push(line('Whole book, month', pick('book', 'monthly'), cat[0], false))
    datasets.push(line('Whole book, 12 months', pick('book', 'annual'), cat[0], true))
    const detail = (w, k, i) => rows[i]?.[w]?.[k]
    make('default', defaultChart.value, {
        type: 'line',
        data: { labels: rows.map(r => periodLabel(r.period)), datasets },
        options: {
            maintainAspectRatio: false,
            animation: animation(),
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { beginAtZero: true, border: { display: false }, grid: { color: n.grid }, ticks: { color: n.text, font: { size: 11 }, callback: (v) => v + '%' } },
                x: { border: { display: false }, grid: { display: false }, ticks: { color: n.text, font: { size: 11 }, maxRotation: 40 } },
            },
            plugins: {
                legend: legend(n),
                tooltip: {
                    ...tooltip,
                    callbacks: {
                        label: (item) => {
                            const w = item.dataset.label.endsWith('12 months') ? 'annual' : 'monthly'
                            const k = item.dataset.label.startsWith('Whole book') ? 'book' : 'portfolio'
                            const x = detail(w, k, item.dataIndex)
                            return ' ' + item.dataset.label + ': ' + Number(item.parsed.y).toFixed(2) + '%' + (x ? ' (' + formatCount(x.defaults) + ' of ' + formatCount(x.loans) + ' loans)' : '')
                        },
                    },
                },
            },
        },
    })
}

function renderCharts() {
    Object.keys(instances).forEach(k => { instances[k].destroy(); delete instances[k] })
    if (props.error) return
    const n = N()
    renderPie(n)
    renderTrend(n)
    renderBreakdown(n)
    renderMix(n)
    renderScenario(n)
    renderDefaults(n)
}

onMounted(renderCharts)
watch(() => [props.summary, props.eclTrends, props.eclMixTrend, props.scenarioEcl, props.defaultRates], renderCharts)
watch(theme.isDark, renderCharts)
watch(breakdownView, () => renderBreakdown(N()))
onBeforeUnmount(() => {
    Object.keys(instances).forEach(k => { instances[k].destroy(); delete instances[k] })
})
</script>
