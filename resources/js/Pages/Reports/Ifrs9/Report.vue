<script setup>
import { reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    report: { type: Object, required: true },
})

const toneClass = {
    maiic: 'from-maiic-500 to-maiic-600',
    rose: 'from-rose-500 to-rose-600',
    amber: 'from-amber-500 to-amber-600',
    emerald: 'from-emerald-500 to-emerald-600',
}

const controlValues = reactive(
    (props.report.controls?.fields || []).reduce((acc, f) => {
        acc[f.name] = f.value ?? ''
        return acc
    }, {})
)

function pdfUrl() {
    const params = new URLSearchParams()
    if (props.report.period) params.set('period', props.report.period)
    params.set('download', 'pdf')
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
                        <div v-for="f in report.controls.fields" :key="f.name">
                            <label class="block text-xs font-medium text-gray-500 mb-1">{{ f.label }}</label>
                            <input v-model="controlValues[f.name]" type="text"
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
                    </div>
                </div>

                <div v-for="(sec, si) in report.sections" :key="si"
                     class="bg-white rounded-2xl shadow-sm border border-gray-100 mb-6 overflow-hidden">
                    <div class="px-6 py-4 border-l-4 border-maiic-600">
                        <h3 class="font-semibold text-gray-900">{{ sec.heading }}</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table v-if="sec.rows.length" class="min-w-full text-sm">
                            <thead class="bg-maiic-900 text-white">
                                <tr>
                                    <th v-for="(c, ci) in sec.columns" :key="ci"
                                        :class="['px-5 py-3 font-medium uppercase text-xs tracking-wider',
                                                 (sec.align && sec.align[ci] === 'r') ? 'text-right' : 'text-left']">
                                        {{ c }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(row, ri) in sec.rows" :key="ri"
                                    :class="ri % 2 ? 'bg-maiic-50/40' : 'bg-white'">
                                    <td v-for="(cell, ci) in row" :key="ci"
                                        :class="['px-5 py-3 text-gray-700',
                                                 (sec.align && sec.align[ci] === 'r') ? 'text-right tabular-nums' : 'text-left']">
                                        {{ cell }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-else class="px-6 py-8 text-gray-400 italic">No data available for this section.</p>
                    </div>
                </div>

                <p v-if="!report.sections.length" class="text-gray-400 italic">
                    This report has no content for the selected period.
                </p>
            </div>
        </div>
    </AppLayout>
</template>
