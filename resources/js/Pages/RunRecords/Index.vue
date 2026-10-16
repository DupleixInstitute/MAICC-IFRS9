<template>
    <app-layout title="Run records" description="Every run of every month: who ran it, when, on which code, what it ran on (frozen and fingerprinted) and which run a locked month reports. Compare two months to see what changed.">
        <div class="w-full space-y-4">
            <div class="maiic-panel p-4">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
                    <label class="text-xs text-gray-600">Month
                        <select v-model="form.period" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            <option value="">All</option>
                            <option v-for="m in months" :key="m" :value="m">{{ m }}</option>
                        </select>
                    </label>
                    <label class="text-xs text-gray-600">Kind
                        <select v-model="form.kind" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            <option value="">All</option>
                            <option value="MONTH_END">Month-end job</option>
                            <option value="BOOTSTRAP">Bootstrap</option>
                            <option value="SCREEN">Engine run on a screen</option>
                        </select>
                    </label>
                    <label class="text-xs text-gray-600">Compare month
                        <select v-model="form.from" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            <option value="">-</option>
                            <option v-for="m in months" :key="'f' + m" :value="m">{{ m }}</option>
                        </select>
                    </label>
                    <label class="text-xs text-gray-600">with month
                        <select v-model="form.to" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            <option value="">-</option>
                            <option v-for="m in months" :key="'t' + m" :value="m">{{ m }}</option>
                        </select>
                    </label>
                    <div class="flex items-end gap-2 lg:col-span-2">
                        <button type="button" class="primary-btn" @click="apply">Apply</button>
                        <a v-if="form.period" :href="route('run-records.settings', [form.period, 'xlsx'])" class="secondary-btn">Settings in force (Excel)</a>
                        <a v-if="form.period" :href="route('run-records.settings', [form.period, 'pdf'])" class="secondary-btn">PDF</a>
                    </div>
                </div>
            </div>

            <div v-if="compare" class="maiic-panel">
                <div class="flex flex-wrap items-center justify-between gap-2 px-4 pt-3">
                    <div class="text-sm font-semibold text-gray-900">What changed from {{ compare.from }} (run {{ compare.from_run || 'none' }}) to {{ compare.to }} (run {{ compare.to_run || 'none' }})</div>
                    <span v-if="compare.from_run && compare.to_run" class="space-x-2">
                        <a :href="route('run-records.changes', [compare.from, compare.to, 'xlsx'])" class="secondary-btn">Excel</a>
                        <a :href="route('run-records.changes', [compare.from, compare.to, 'pdf'])" class="secondary-btn">PDF</a>
                    </span>
                </div>
                <div v-if="!compare.from_run || !compare.to_run" class="p-4 text-sm text-gray-600">Both months need a completed run on record.</div>
                <div v-else class="maiic-table-wrap">
                    <table class="maiic-table">
                        <thead><tr><th>Section</th><th>Item</th><th>{{ compare.from }}</th><th>{{ compare.to }}</th></tr></thead>
                        <tbody>
                            <tr v-for="(r, i) in compare.rows" :key="i"><td class="text-xs">{{ r[0] }}</td><td class="text-xs">{{ r[1] }}</td><td class="text-xs">{{ r[2] }}</td><td class="text-xs">{{ r[3] }}</td></tr>
                            <tr v-if="!compare.rows.length"><td colspan="4" class="text-center text-gray-500">No input differs.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="maiic-panel">
                <div class="maiic-table-wrap">
                    <table class="maiic-table">
                        <thead><tr><th>Run</th><th>Month</th><th>Kind</th><th>Status</th><th>By</th><th>Started</th><th>Finished</th><th>Code</th><th>Fingerprint</th><th>Steps</th></tr></thead>
                        <tbody>
                            <tr v-for="r in runs" :key="r.id">
                                <td><a :href="route('run-records.show', r.id)" class="text-sky-700 underline">{{ r.id }}</a></td>
                                <td>{{ r.period }}</td>
                                <td class="text-xs">{{ kinds[r.kind] || r.kind }}</td>
                                <td>
                                    <span class="maiic-badge" :class="badge(r)">{{ r.status }}</span>
                                    <div v-if="r.of_record" class="mt-1 text-[11px] font-semibold text-emerald-700">Run of record</div>
                                    <div v-else-if="r.was_of_record" class="mt-1 text-[11px] text-gray-500">Was the run of record</div>
                                    <div v-else-if="r.superseded_by" class="mt-1 text-[11px] text-gray-500">by run {{ r.superseded_by }}</div>
                                </td>
                                <td class="text-xs">{{ r.by || r.label }}</td>
                                <td class="whitespace-nowrap text-xs">{{ r.started_at }}</td>
                                <td class="whitespace-nowrap text-xs">{{ r.finished_at }}</td>
                                <td class="font-mono text-[11px]">{{ r.code }}</td>
                                <td class="font-mono text-[11px]">{{ r.fingerprint }}</td>
                                <td>{{ r.steps }}</td>
                            </tr>
                            <tr v-if="!runs.length"><td colspan="10" class="text-center text-gray-500">No run on record yet.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <teleport to="head"><title>Run records</title></teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'

export default {
    components: { AppLayout },
    props: {
        runs: { type: Array, default: () => [] },
        filters: { type: Object, default: () => ({}) },
        compare: { type: Object, default: null },
        months: { type: Array, default: () => [] },
    },
    data() {
        return {
            form: { period: this.filters.period || '', kind: this.filters.kind || '', from: this.filters.from || '', to: this.filters.to || '' },
            kinds: { MONTH_END: 'Month-end job', BOOTSTRAP: 'Bootstrap', SCREEN: 'Engine run on a screen' },
        }
    },
    methods: {
        apply() {
            this.$inertia.get(this.route('run-records.index'), Object.fromEntries(Object.entries(this.form).filter(([, v]) => v)), { preserveScroll: true })
        },
        badge(r) {
            return { COMPLETED: 'bg-emerald-100 text-emerald-800', RUNNING: 'bg-sky-100 text-sky-800', FAILED: 'bg-rose-100 text-rose-800', SUPERSEDED: 'bg-gray-100 text-gray-600' }[r.status] || 'bg-gray-100 text-gray-700'
        },
    },
}
</script>
