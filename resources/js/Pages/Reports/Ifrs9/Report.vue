<script setup>
import { reactive, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import BackToReports from '../Partials/BackToReports.vue'
import DownloadButton from '../Partials/DownloadButton.vue'
import KpiRow from '../Partials/KpiRow.vue'
import ClientPager from '../Partials/ClientPager.vue'
import ReportIcon from '../Partials/ReportIcon.vue'

const props = defineProps({
    report: { type: Object, required: true },
})

const PAGE_SIZE = 15

const controlValues = reactive(
    (props.report.controls?.fields || []).reduce((acc, f) => {
        acc[f.name] = f.value ?? ''
        return acc
    }, {})
)

// Per-section current page (1-based), keyed by section index.
const pages = reactive({})
const pageOf = (si) => pages[si] || 1
function pagedRows(sec, si) {
    if (sec.rows.length <= PAGE_SIZE) return sec.rows
    const start = (pageOf(si) - 1) * PAGE_SIZE
    return sec.rows.slice(start, start + PAGE_SIZE)
}

const visibleControls = computed(() =>
    (props.report.controls?.fields || []).filter(f => {
        if (!f.show_when) return true
        return Object.entries(f.show_when).every(([k, v]) => controlValues[k] === v)
    })
)

function downloadUrl(kind) {
    const params = new URLSearchParams()
    if (props.report.period) params.set('period', props.report.period)
    params.set('download', kind)
    return route('ifrs9-reports.' + props.report.key) + '?' + params.toString()
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

const isNum = (sec, ci) => sec.align && sec.align[ci] === 'r'
const isTotal = (row) => /^(total|totals|grand total|net|closing)/i.test(String(row?.[0] ?? '').trim())
</script>

<template>
    <AppLayout :title="report.title">
        <template #header>
            <BackToReports :tab="report.tab || ''"/>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ report.title }}</h2>
            <p class="mt-0.5 text-sm text-gray-500">{{ report.subtitle }}</p>
        </template>

        <template #actions>
            <template v-if="report.period">
                <label for="report-period" class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Period</label>
                <select id="report-period" :value="report.period" @change="changePeriod" class="maiic-select w-36">
                    <option v-for="p in report.periods" :key="p" :value="p">{{ p }}</option>
                </select>
            </template>
            <DownloadButton format="CSV" :href="downloadUrl('csv')" title="The report's tables as CSV data"/>
            <DownloadButton format="Excel" :href="downloadUrl('xlsx')" title="A formatted Excel workbook"/>
            <DownloadButton format="PDF" label="Download PDF" :href="downloadUrl('pdf')" title="A branded A4 PDF"/>
        </template>

        <div class="w-full space-y-5">
            <p class="text-xs text-gray-400">{{ report.company }} &middot; prepared {{ report.generated_at }}</p>

            <div v-if="report.controls" class="maiic-filterbar">
                <div class="flex flex-wrap items-end gap-4">
                    <div v-for="f in visibleControls" :key="f.name">
                        <label class="maiic-flabel">{{ f.label }}</label>
                        <select v-if="f.type === 'select'" v-model="controlValues[f.name]" class="maiic-select w-52">
                            <option v-for="o in f.options" :key="o.value" :value="o.value">{{ o.label }}</option>
                        </select>
                        <input v-else v-model="controlValues[f.name]" type="text" class="maiic-input w-44" placeholder="e.g. 10,25,50"/>
                    </div>
                    <button type="button" @click="runControls"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-maiic-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-maiic-700">
                        <ReportIcon name="play" class="h-4 w-4"/> Run
                    </button>
                </div>
                <p class="mt-2 text-xs text-gray-400">Enter comma-separated percentages. Leave blank for the defaults.</p>
            </div>

            <KpiRow :items="report.kpis || []"/>

            <div v-for="(sec, si) in report.sections" :key="si" class="maiic-panel">
                <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-4 py-3">
                    <h3 class="font-semibold text-gray-900">{{ sec.heading }}</h3>
                    <span v-if="sec.rows.length" class="maiic-badge maiic-badge-grey">{{ sec.rows.length.toLocaleString() }} rows</span>
                </div>
                <p v-if="sec.note" class="border-b border-gray-100 px-4 py-2 text-xs text-gray-500">{{ sec.note }}</p>
                <div v-if="sec.rows.length" class="maiic-table-wrap">
                    <table class="maiic-table">
                        <thead>
                            <tr>
                                <th v-for="(c, ci) in sec.columns" :key="ci" :class="{ num: isNum(sec, ci) }">{{ c }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, ri) in pagedRows(sec, si)" :key="ri" :class="{ total: isTotal(row) }">
                                <td v-for="(cell, ci) in row" :key="ci" :class="isNum(sec, ci) ? 'num' : ''">{{ cell }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="maiic-empty">No data for this section in {{ report.period || 'this period' }}.</p>
                <ClientPager v-if="sec.rows.length > PAGE_SIZE" :total="sec.rows.length" :per-page="PAGE_SIZE"
                             :model-value="pageOf(si)" @update:model-value="p => pages[si] = p"/>
            </div>

            <div v-if="!report.sections.length" class="maiic-panel maiic-empty">
                This report has no content for the selected period. Choose another period, or run the ECL calculation for this one.
            </div>
        </div>
    </AppLayout>
</template>
