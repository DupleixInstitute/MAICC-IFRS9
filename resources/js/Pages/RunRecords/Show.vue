<template>
    <app-layout :title="'Run ' + run.id + ', ' + run.period" description="What this run of the month ran on, frozen when it finished, with its fingerprint, the steps the engines took and the results it left.">
        <div class="w-full space-y-4">
            <KpiRow :cards="[
                { label: 'Kind', value: kinds[run.kind] || run.kind },
                { label: 'Status', value: run.status, accent: run.status === 'COMPLETED' ? '#0f766e' : (run.status === 'FAILED' ? '#be123c' : '#6b7280') },
                { label: 'Run of record', value: run.of_record_at && !run.of_record_until ? 'Yes' : (run.of_record_until ? 'Until ' + run.of_record_until : 'No'), accent: run.of_record_at && !run.of_record_until ? '#0f766e' : undefined },
                { label: 'Fingerprint', value: verifies === null ? 'not frozen' : (verifies ? 'Verifies' : 'Does not verify'), accent: verifies === false ? '#be123c' : '#0f766e' },
            ]"/>

            <div class="maiic-panel p-4 text-sm text-gray-700 space-y-1">
                <div><span class="text-gray-500">Run by</span> {{ run.by || run.label }} <span class="text-gray-500">from</span> {{ run.started_at }} <span class="text-gray-500">to</span> {{ run.finished_at || 'still running' }}</div>
                <div><span class="text-gray-500">Code version</span> <span class="font-mono text-xs">{{ run.code_version }}</span></div>
                <div><span class="text-gray-500">Fingerprint (SHA-256)</span> <span class="font-mono text-xs break-all">{{ run.fingerprint }}</span></div>
                <div v-if="run.of_record_at"><span class="text-gray-500">Named as the run of record</span> {{ run.of_record_at }}<span v-if="run.of_record_by"> by {{ run.of_record_by }}</span><span v-if="run.of_record_until">, until the month was unlocked on {{ run.of_record_until }}</span></div>
                <div v-if="run.superseded_by"><span class="text-gray-500">Superseded by run</span> <a :href="route('run-records.show', run.superseded_by)" class="text-sky-700 underline">{{ run.superseded_by }}</a></div>
                <div v-if="run.error" class="text-rose-700">{{ run.error }}</div>
                <div class="pt-2 space-x-2">
                    <a :href="route('run-records.settings', [run.period, 'xlsx'])" class="secondary-btn">Settings in force (Excel)</a>
                    <a :href="route('run-records.settings', [run.period, 'pdf'])" class="secondary-btn">PDF</a>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="maiic-panel">
                    <div class="px-4 pt-3 text-sm font-semibold text-gray-900">Steps</div>
                    <div class="maiic-table-wrap">
                        <table class="maiic-table">
                            <thead><tr><th>Engine</th><th>Started</th><th>Finished</th><th></th></tr></thead>
                            <tbody>
                                <tr v-for="(s, i) in steps" :key="i"><td>{{ s.engine }}</td><td class="text-xs">{{ s.started_at }}</td><td class="text-xs">{{ s.finished_at }}</td>
                                    <td class="text-xs" :class="s.ok === false ? 'text-rose-700' : 'text-emerald-700'">{{ s.ok === false ? ('failed: ' + (s.error || '')) : (s.ok ? 'done' : '') }}</td></tr>
                                <tr v-if="!steps.length"><td colspan="4" class="text-center text-gray-500">No engine step recorded.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="maiic-panel p-4 text-sm">
                    <div class="font-semibold text-gray-900">Results it left</div>
                    <div v-for="(v, k) in results" :key="k" class="mt-1"><span class="text-gray-500">{{ k }}</span> {{ typeof v === 'object' ? JSON.stringify(v) : v }}</div>
                    <div class="mt-3 font-semibold text-gray-900">Rows carrying this run's number</div>
                    <div v-for="(n, t) in rowsStamped" :key="t" class="mt-1"><span class="font-mono text-xs">{{ t }}</span> {{ n }}</div>
                    <div v-if="!Object.keys(rowsStamped).length" class="mt-1 text-gray-500">None now: a later run of the month carries them.</div>
                </div>
            </div>

            <div v-for="(items, heading) in sections" :key="heading" class="maiic-panel">
                <div class="px-4 pt-3 text-sm font-semibold text-gray-900">{{ heading }}</div>
                <div class="maiic-table-wrap">
                    <table class="maiic-table">
                        <tbody>
                            <tr v-for="(v, k) in items" :key="k"><td class="w-1/3 text-xs">{{ k }}</td><td class="text-xs">{{ v }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <teleport to="head"><title>Run record</title></teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import KpiRow from '@/Components/Maiic/KpiRow.vue'

export default {
    components: { AppLayout, KpiRow },
    props: {
        run: { type: Object, required: true },
        verifies: { type: Boolean, default: null },
        steps: { type: Array, default: () => [] },
        results: { type: Object, default: () => ({}) },
        sections: { type: Object, default: () => ({}) },
        rowsStamped: { type: Object, default: () => ({}) },
    },
    data() {
        return { kinds: { MONTH_END: 'Month-end job', BOOTSTRAP: 'Bootstrap', SCREEN: 'Engine run on a screen' } }
    },
}
</script>
