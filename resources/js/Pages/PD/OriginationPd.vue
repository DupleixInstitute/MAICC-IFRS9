<template>
    <app-layout title="PD at origination" description="Each loan's starting risk, written once and never changed, with a label saying how good a starting reading it is; and the PD-change test run in shadow, which moves no loan.">
        <div class="w-full space-y-4">
            <KpiRow :cards="Object.keys(labels).map(k => ({ label: labels[k], value: counts[k] || 0 }))"/>

            <div class="maiic-panel p-4 text-sm text-gray-700">
                The PD history begins in <strong>{{ historyStart || 'no month yet' }}</strong>. A loan made before then has, as its starting reading, the earliest PD the system holds, labelled "Before the PD history". The PD-change test (setting 9) stays "Not applied", so no stage depends on these readings; the shadow test below shows which loans it would move.
            </div>

            <div class="maiic-panel">
                <div class="flex flex-wrap items-end justify-between gap-3 px-4 pt-3">
                    <div class="text-sm font-semibold text-gray-900">PD-change test in shadow</div>
                    <div class="flex items-end gap-2">
                        <label class="text-xs text-gray-600">Month
                            <select v-model="form.shadow" class="mt-1 rounded-md border-gray-300 text-sm" @change="apply">
                                <option v-for="m in months" :key="m" :value="m">{{ m }}</option>
                            </select>
                        </label>
                        <a :href="route('origination-pd.export', query)" class="secondary-btn">Excel</a>
                    </div>
                </div>
                <div v-if="shadow" class="px-4 pt-2 text-sm text-gray-700">
                    At least <strong>{{ shadow.thresholds.ratio_label }}</strong> the starting PD and <strong>{{ shadow.thresholds.points_label }}</strong> higher:
                    <strong>{{ shadow.would_move }}</strong> of {{ shadow.tested }} Stage 1 loans would move to Stage 2.
                    <span v-if="Object.keys(shadow.excluded).length" class="text-gray-500">Not tested: <span v-for="(n, why) in shadow.excluded" :key="why" class="mr-2">{{ why }} {{ n }};</span></span>
                </div>
                <div v-if="shadow" class="maiic-table-wrap">
                    <table class="maiic-table">
                        <thead><tr><th>Contract</th><th>Customer</th><th>Starting month</th><th>Label</th><th>PD at start</th><th>PD now</th><th>Times</th><th>Points higher</th><th>Would move</th></tr></thead>
                        <tbody>
                            <tr v-for="r in shadow.rows.slice(0, 200)" :key="r.contract_id">
                                <td class="font-mono text-xs">{{ r.contract_id }}</td><td class="text-xs">{{ r.customer }}</td><td class="text-xs">{{ r.starting_month }}</td><td class="text-xs">{{ r.starting_basis }}</td>
                                <td class="text-right">{{ pct(r.pd_start) }}</td><td class="text-right">{{ pct(r.pd_now) }}</td><td class="text-right">{{ r.ratio.toFixed(2) }}</td><td class="text-right">{{ r.points.toFixed(2) }}</td>
                                <td><span v-if="r.would_move" class="maiic-badge bg-amber-100 text-amber-800">Stage 2 if adopted</span></td>
                            </tr>
                            <tr v-if="!shadow.rows.length"><td colspan="9" class="text-center text-gray-500">No Stage 1 loan with a usable starting reading this month.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="maiic-panel">
                <div class="flex flex-wrap items-end justify-between gap-3 px-4 pt-3">
                    <div class="text-sm font-semibold text-gray-900">Starting readings</div>
                    <label class="text-xs text-gray-600">Label
                        <select v-model="form.basis" class="mt-1 rounded-md border-gray-300 text-sm" @change="apply">
                            <option value="">All</option>
                            <option v-for="(label, key) in labels" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </label>
                </div>
                <div class="maiic-table-wrap">
                    <table class="maiic-table">
                        <thead><tr><th>Contract</th><th>Customer</th><th>Made on</th><th>PD taken in</th><th>Label</th><th>12-month PD (after FLI)</th><th>12-month PD (before FLI)</th><th>Lifetime PD</th><th>Stage</th></tr></thead>
                        <tbody>
                            <tr v-for="r in records" :key="r.contract_id">
                                <td class="font-mono text-xs">{{ r.contract_id }}</td><td class="text-xs">{{ r.customer_name }}</td><td class="text-xs">{{ r.origination_date }}</td>
                                <td class="text-xs">{{ r.reporting_period }}<span v-if="r.months_after_origination" class="text-gray-500"> ({{ r.months_after_origination }} months after)</span></td>
                                <td class="text-xs">{{ r.label }}</td>
                                <td class="text-right">{{ pct(r.pd_12m_post_fli) }}</td><td class="text-right">{{ pct(r.pd_12m_pre_fli) }}</td><td class="text-right">{{ pct(r.pd_lifetime) }}</td><td>{{ r.stage }}</td>
                            </tr>
                            <tr v-if="!records.length"><td colspan="9" class="text-center text-gray-500">No starting reading yet.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-2 text-xs text-gray-500">The first 500 shown; the Excel export holds them all.</div>
            </div>
        </div>
        <teleport to="head"><title>PD at origination</title></teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import KpiRow from '@/Components/Maiic/KpiRow.vue'

export default {
    components: { AppLayout, KpiRow },
    props: {
        records: { type: Array, default: () => [] },
        counts: { type: Object, default: () => ({}) },
        labels: { type: Object, default: () => ({}) },
        historyStart: { type: String, default: null },
        months: { type: Array, default: () => [] },
        filters: { type: Object, default: () => ({}) },
        shadow: { type: Object, default: null },
    },
    data() {
        return { form: { basis: this.filters.basis || '', shadow: this.filters.shadow || '' } }
    },
    computed: {
        query() {
            return Object.fromEntries(Object.entries(this.form).filter(([, v]) => v))
        },
    },
    methods: {
        apply() {
            this.$inertia.get(this.route('origination-pd.index'), this.query, { preserveScroll: true })
        },
        pct(v) {
            return v === null || v === undefined ? '-' : (Number(v) * 100).toFixed(2) + '%'
        },
    },
}
</script>
