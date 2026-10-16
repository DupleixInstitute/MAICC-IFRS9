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

            <div class="maiic-panel">
                <div class="flex flex-wrap items-center justify-between gap-2 px-4 pt-3">
                    <div class="text-sm font-semibold text-gray-900">Re-performance</div>
                    <button v-if="canReperform && run.fingerprint" type="button" class="primary-btn" :disabled="processing" @click="reperform">Re-perform from the snapshot</button>
                </div>
                <p class="px-4 pt-1 text-xs text-gray-600">In place: the month's inputs now against what this run froze, every loan staged again under the frozen rules, and every loan's figures against the ones this run left. Nothing is written but the re-performance's record. A re-run of every engine on a copy of the database is done from the console (run-records:reperform with --workspace).</p>
                <div class="maiic-table-wrap">
                    <table class="maiic-table">
                        <thead><tr><th>#</th><th>How</th><th>Result</th><th>Loans</th><th>Differences</th><th>When</th><th>By</th><th></th></tr></thead>
                        <tbody>
                            <tr v-for="p in reperformances" :key="p.id">
                                <td>{{ p.id }}</td>
                                <td class="text-xs">{{ p.mode === 'WORKSPACE' ? 'Workspace ' + p.workspace : 'In place' }}</td>
                                <td><span class="maiic-badge" :class="p.status === 'REPRODUCED' ? 'bg-emerald-100 text-emerald-800' : (p.status === 'RUNNING' ? 'bg-sky-100 text-sky-800' : 'bg-rose-100 text-rose-800')">{{ p.status }}</span>
                                    <div v-if="p.error" class="mt-1 text-[11px] text-rose-700">{{ p.error }}</div></td>
                                <td>{{ p.summary ? p.summary.loans : '' }}</td>
                                <td class="text-xs">{{ p.summary ? p.summary.differences : '' }}<span v-if="p.summary && p.summary.by_check && Object.keys(p.summary.by_check).length" class="block text-gray-500">{{ JSON.stringify(p.summary.by_check) }}</span></td>
                                <td class="whitespace-nowrap text-xs">{{ p.finished_at || p.started_at }}</td>
                                <td class="text-xs">{{ p.by }}</td>
                                <td class="whitespace-nowrap text-xs"><a :href="route('run-records.reperformance', [p.id, 'xlsx'])" class="text-sky-700 underline">Excel</a> <a :href="route('run-records.reperformance', [p.id, 'pdf'])" class="text-sky-700 underline">PDF</a></td>
                            </tr>
                            <tr v-if="!reperformances.length"><td colspan="8" class="text-center text-gray-500">Not re-performed yet.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="differences.length" class="maiic-table-wrap border-t border-gray-100">
                    <table class="maiic-table">
                        <thead><tr><th>Check</th><th>Loan</th><th>Item</th><th>Stored</th><th>Re-performed</th><th>Note</th></tr></thead>
                        <tbody>
                            <tr v-for="(d, i) in differences" :key="i"><td class="text-xs">{{ d.check }}</td><td class="font-mono text-xs">{{ d.contract_id }}</td><td class="text-xs">{{ d.item }}</td>
                                <td class="text-xs">{{ d.stored }}</td><td class="text-xs">{{ d.reperformed }}</td><td class="text-xs">{{ d.note }}</td></tr>
                        </tbody>
                    </table>
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
        reperformances: { type: Array, default: () => [] },
        differences: { type: Array, default: () => [] },
        canReperform: { type: Boolean, default: false },
    },
    data() {
        return { kinds: { MONTH_END: 'Month-end job', BOOTSTRAP: 'Bootstrap', SCREEN: 'Engine run on a screen' }, processing: false }
    },
    methods: {
        reperform() {
            this.processing = true
            this.$inertia.post(this.route('run-records.reperform', this.run.id), {}, { preserveScroll: true, onFinish: () => { this.processing = false } })
        },
    },
}
</script>
