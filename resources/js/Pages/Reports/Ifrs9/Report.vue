<script setup>
import { reactive, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    report: { type: Object, required: true },
})

const PAGE_SIZE = 10

const toneClass = {
    maiic: 'from-maiic-500 to-maiic-600',
    rose: 'from-red-500 to-red-600',
    amber: 'from-amber-500 to-amber-600',
    emerald: 'from-maiic-500 to-maiic-600',
}

const controlValues = reactive(
    (props.report.controls?.fields || []).reduce((acc, f) => {
        acc[f.name] = f.value ?? ''
        return acc
    }, {})
)

// Per-section current page (1-based), keyed by section index.
const pages = reactive({})
const pageOf = (si) => pages[si] || 1
const pageCount = (sec) => Math.max(1, Math.ceil(sec.rows.length / PAGE_SIZE))

function pagedRows(sec, si) {
    if (sec.rows.length <= PAGE_SIZE) return sec.rows
    const start = (pageOf(si) - 1) * PAGE_SIZE
    return sec.rows.slice(start, start + PAGE_SIZE)
}

function goto(sec, si, p) {
    pages[si] = Math.min(Math.max(1, p), pageCount(sec))
}

// Windowed page numbers: first, last, and a window around the current page.
function pageNumbers(sec, si) {
    const total = pageCount(sec)
    const cur = pageOf(si)
    const out = []
    for (let p = 1; p <= total; p++) {
        if (p === 1 || p === total || (p >= cur - 2 && p <= cur + 2)) {
            out.push(p)
        } else if (out[out.length - 1] !== '…') {
            out.push('…')
        }
    }
    return out
}

const visibleControls = computed(() =>
    (props.report.controls?.fields || []).filter(f => {
        if (!f.show_when) return true
        return Object.entries(f.show_when).every(([k, v]) => controlValues[k] === v)
    })
)

function pdfUrl() {
    const params = new URLSearchParams()
    if (props.report.period) params.set('period', props.report.period)
    params.set('download', 'pdf')
    return route('ifrs9-reports.' + props.report.key) + '?' + params.toString()
}

function csvCell(v) {
    const s = String(v ?? '')
    return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s
}

function exportCsv() {
    const lines = []
    for (const sec of props.report.sections || []) {
        if (sec.heading) lines.push(csvCell(sec.heading))
        if (sec.columns) lines.push(sec.columns.map(csvCell).join(','))
        for (const row of sec.rows || []) lines.push(row.map(csvCell).join(','))
        lines.push('')
    }
    const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' })
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = (props.report.title || 'report').replace(/[^\w.-]+/g, '_')
        + '_' + (props.report.period || '') + '.csv'
    a.click()
    URL.revokeObjectURL(a.href)
}

function changePeriod(e) {
    router.get(route('ifrs9-reports.' + props.report.key), { period: e.target.value },
        { preserveState: false, preserveScroll: true })
}

function runControls() {
    router.get(
        route(props.report.controls.action),
        { period: props.report.period, ...controlValues },
        { preserveState: false, preserveScroll: true }
    )
}
</script>

<template>
    <AppLayout :title="report.title">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ report.title }}</h2>
                <Link :href="route('ifrs9-reports.index')" class="text-sm text-maiic-600 hover:underline">
                    &larr; All reports
                </Link>
            </div>
        </template>

        <div class="py-10">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-widest text-maiic-600 font-semibold">{{ report.company }}</p>
                            <h1 class="text-2xl font-bold text-gray-900 mt-1">{{ report.title }}</h1>
                            <p class="text-gray-500 mt-1">{{ report.subtitle }}</p>
                            <p class="text-xs text-gray-400 mt-2">Generated {{ report.generated_at }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <div v-if="report.period">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Period</label>
                                <select :value="report.period" @change="changePeriod"
                                        class="rounded-lg border-gray-300 text-sm py-2 px-3 shadow-sm focus:ring-maiic-500 focus:border-maiic-500">
                                    <option v-for="p in report.periods" :key="p" :value="p">{{ p }}</option>
                                </select>
                            </div>
                            <button @click="exportCsv"
                                    class="inline-flex items-center px-4 py-2 bg-white border border-maiic-600 text-maiic-700 hover:bg-maiic-50 text-sm font-medium rounded-lg shadow self-end">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V7M3 7l9 6 9-6M3 7h18"/>
                                </svg>
                                Export CSV
                            </button>
                            <a :href="pdfUrl()"
                               class="inline-flex items-center px-4 py-2 bg-maiic-600 hover:bg-maiic-700 text-white text-sm font-medium rounded-lg shadow self-end">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
                                </svg>
                                Download PDF
                            </a>
                        </div>
                    </div>
                </div>

                <div v-if="report.controls" class="bg-white rounded-2xl shadow-sm border border-maiic-200 p-6 mb-6">
                    <h3 class="font-semibold text-gray-900 mb-3">Run with your own inputs</h3>
                    <div class="flex flex-wrap items-end gap-4">
                        <div v-for="f in visibleControls" :key="f.name">
                            <label class="block text-xs font-medium text-gray-500 mb-1">{{ f.label }}</label>
                            <select v-if="f.type === 'select'" v-model="controlValues[f.name]"
                                    class="rounded-lg border-gray-300 text-sm py-2 px-3 shadow-sm focus:ring-maiic-500 focus:border-maiic-500 w-52">
                                <option v-for="o in f.options" :key="o.value" :value="o.value">{{ o.label }}</option>
                            </select>
                            <input v-else v-model="controlValues[f.name]" type="text"
                                   class="rounded-lg border-gray-300 text-sm py-2 px-3 shadow-sm focus:ring-maiic-500 focus:border-maiic-500 w-44"
                                   placeholder="e.g. 10,25,50"/>
                        </div>
                        <button @click="runControls"
                                class="inline-flex items-center px-5 py-2 bg-maiic-600 hover:bg-maiic-700 text-white text-sm font-medium rounded-lg shadow">
                            Run
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">Enter comma-separated percentages. Leave blank for defaults.</p>
                </div>

                <div v-if="report.kpis.length" class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                    <div v-for="k in report.kpis" :key="k.label"
                         :class="['rounded-2xl p-5 text-white bg-gradient-to-br shadow', toneClass[k.tone] || toneClass.maiic]">
                        <p class="text-xs uppercase tracking-wider opacity-85">{{ k.label }}</p>
                        <p class="text-2xl font-bold mt-2">{{ k.value }}</p>
                        <p v-if="k.sub" class="text-xs mt-1 opacity-80">{{ k.sub }}</p>
                    </div>
                </div>

                <div v-for="(sec, si) in report.sections" :key="si"
                     class="bg-white rounded-2xl shadow-sm border border-gray-100 mb-6 overflow-hidden">
                    <div class="px-6 py-4 border-l-4 border-maiic-600 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-900">{{ sec.heading }}</h3>
                        <span v-if="sec.rows.length > PAGE_SIZE" class="text-xs text-gray-400">
                            {{ sec.rows.length }} rows
                        </span>
                    </div>
                    <div class="overflow-x-auto">
                        <table v-if="sec.rows.length" class="min-w-full text-sm">
                            <thead class="bg-maiic-900 text-white">
                                <tr>
                                    <th v-for="(c, ci) in sec.columns" :key="ci"
                                        :class="['px-5 py-3 font-medium uppercase text-xs tracking-wider whitespace-nowrap',
                                                 (sec.align && sec.align[ci] === 'r') ? 'text-right' : 'text-left']">
                                        {{ c }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(row, ri) in pagedRows(sec, si)" :key="ri"
                                    :class="ri % 2 ? 'bg-maiic-50/40' : 'bg-white'">
                                    <td v-for="(cell, ci) in row" :key="ci"
                                        :class="['px-5 py-3 text-gray-700 whitespace-nowrap',
                                                 (sec.align && sec.align[ci] === 'r') ? 'text-right tabular-nums' : 'text-left']">
                                        {{ cell }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-else class="px-6 py-8 text-gray-400 italic">No data available for this section.</p>
                    </div>

                    <div v-if="sec.rows.length > PAGE_SIZE"
                         class="flex flex-wrap items-center justify-between gap-3 px-6 py-3 border-t border-gray-100 bg-gray-50/60">
                        <p class="text-xs text-gray-500">
                            Showing {{ (pageOf(si) - 1) * PAGE_SIZE + 1 }}-{{ Math.min(pageOf(si) * PAGE_SIZE, sec.rows.length) }}
                            of {{ sec.rows.length }}
                        </p>
                        <div class="flex items-center gap-1">
                            <button @click="goto(sec, si, pageOf(si) - 1)" :disabled="pageOf(si) === 1"
                                    class="px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white text-gray-600 hover:bg-maiic-50 disabled:opacity-40 disabled:cursor-not-allowed">
                                Prev
                            </button>
                            <template v-for="(p, idx) in pageNumbers(sec, si)" :key="idx">
                                <span v-if="p === '…'" class="px-2 text-gray-400">…</span>
                                <button v-else @click="goto(sec, si, p)"
                                        :class="['px-3 py-1.5 text-sm rounded-md border',
                                                 p === pageOf(si)
                                                    ? 'bg-maiic-600 border-maiic-600 text-white'
                                                    : 'bg-white border-gray-300 text-gray-600 hover:bg-maiic-50']">
                                    {{ p }}
                                </button>
                            </template>
                            <button @click="goto(sec, si, pageOf(si) + 1)" :disabled="pageOf(si) === pageCount(sec)"
                                    class="px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white text-gray-600 hover:bg-maiic-50 disabled:opacity-40 disabled:cursor-not-allowed">
                                Next
                            </button>
                        </div>
                    </div>
                </div>

                <p v-if="!report.sections.length" class="text-gray-400 italic">
                    This report has no content for the selected period.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
