<template>
    <app-layout title="Staging Comparison" description="A month's stages and ECL under another staging policy, beside those booked, with the setting behind each difference. Nothing booked is changed.">
        <div class="w-full space-y-4">
            <div class="maiic-panel p-4">
                <div class="mb-3 text-sm font-semibold text-gray-900">Run a comparison</div>
                <div class="grid gap-3 md:grid-cols-3">
                    <label class="text-xs text-gray-600">Month
                        <select v-model="form.period" class="maiic-select mt-1 w-full">
                            <option v-for="p in periods" :key="p" :value="p">{{ p }}</option>
                        </select>
                    </label>
                    <label class="text-xs text-gray-600">Compare with
                        <select v-model="form.preset" class="maiic-select mt-1 w-full">
                            <option v-for="p in presets" :key="p" :value="p">{{ p === 'Custom' ? 'The policy in force, with the settings changed below' : p }}</option>
                        </select>
                    </label>
                    <label class="text-xs text-gray-600">Note (optional)
                        <input v-model="form.note" type="text" maxlength="500" class="maiic-input mt-1 w-full" placeholder="Why this comparison is run"/>
                    </label>
                </div>
                <details class="mt-3">
                    <summary class="cursor-pointer text-xs font-medium text-gray-700">Change a setting on top ({{ changedCount }} changed)</summary>
                    <div class="mt-2 grid gap-3 md:grid-cols-2">
                        <label v-for="(s, key) in settings" :key="key" class="text-xs text-gray-600">{{ s.label }}
                            <select v-model="form.settings[key]" class="maiic-select mt-1 w-full">
                                <option value="">As the policy compared with</option>
                                <option v-for="o in s.options" :key="o" :value="o">{{ o }}</option>
                            </select>
                        </label>
                    </div>
                </details>
                <div class="mt-4 flex justify-end">
                    <button type="button" class="primary-btn" :disabled="processing || !form.period" @click="run">{{ processing ? 'Running...' : 'Run the comparison' }}</button>
                </div>
            </div>

            <template v-if="run">
                <KpiRow :cards="[
                    { label: 'Month', value: run.reporting_period },
                    { label: 'Loans that change stage', value: run.loans_moved + ' of ' + run.loans, accent: '#d97706' },
                    { label: 'ECL booked', value: money(run.booked_ecl) },
                    { label: 'ECL compared', value: money(run.compared_ecl) },
                    { label: 'ECL effect', value: money(run.compared_ecl - run.booked_ecl), accent: run.compared_ecl - run.booked_ecl > 0 ? '#dc2626' : '#0f766e' },
                    { label: 'Stage 3 interest, one month', value: money(run.totals_decoded.stage3_interest_effect) },
                ]"/>

                <div class="maiic-panel p-4 text-sm text-gray-700">
                    Run {{ run.id }}: booked under <strong>{{ run.booked_preset }}</strong>, compared with <strong>{{ run.compared_preset }}</strong>.
                    <span v-if="run.note"> {{ run.note }}.</span>
                    <span v-if="run.totals_decoded.ecl_not_known"> {{ run.totals_decoded.ecl_not_known }} loan(s) move to a stage no loan of the book is in, so their ECL there is not known.</span>
                    The ECL at a new stage uses the booked coverage (ECL over EAD) of that stage in the loan's portfolio; the cure reads the prior months as booked.
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="maiic-panel">
                        <div class="px-4 pt-3 text-sm font-semibold text-gray-900">Stage movements</div>
                        <div class="maiic-table-wrap">
                            <table class="maiic-table">
                                <thead><tr><th>Movement</th><th class="num">Loans</th><th class="num">Carrying amount</th></tr></thead>
                                <tbody>
                                    <tr v-for="(m, k) in run.totals_decoded.moves" :key="k"><td>Stage {{ k.replace('>', ' to Stage ') }}</td><td class="num">{{ m.loans }}</td><td class="num">{{ money(m.carrying_amount) }}</td></tr>
                                    <tr v-if="!Object.keys(run.totals_decoded.moves).length"><td colspan="3" class="text-center text-gray-500">No loan changes stage.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="maiic-panel">
                        <div class="px-4 pt-3 text-sm font-semibold text-gray-900">ECL effect by reason</div>
                        <div class="maiic-table-wrap">
                            <table class="maiic-table">
                                <thead><tr><th>Reason (the setting behind it)</th><th class="num">ECL effect</th></tr></thead>
                                <tbody>
                                    <tr v-for="(v, k) in run.totals_decoded.ecl_by_reason" :key="k"><td>{{ k }}</td><td class="num">{{ money(v) }}</td></tr>
                                    <tr v-if="!Object.keys(run.totals_decoded.ecl_by_reason).length"><td colspan="2" class="text-center text-gray-500">No ECL effect.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="maiic-panel">
                    <div class="flex items-center justify-between px-4 pt-3">
                        <div class="text-sm font-semibold text-gray-900">Loans</div>
                        <div class="flex items-center gap-2">
                            <select :value="show" class="maiic-select !w-48" aria-label="Which loans" @change="filter($event.target.value)">
                                <option value="moved">Loans that change stage</option>
                                <option value="all">Every loan</option>
                            </select>
                            <a :href="route('staging-comparison.export', run.id)" class="secondary-btn">Export</a>
                        </div>
                    </div>
                    <div class="maiic-table-wrap">
                        <table class="maiic-table">
                            <thead>
                                <tr>
                                    <th>Loan</th><th class="num">Stage booked</th><th class="num">Stage compared</th><th class="num">Days (booked rules)</th><th class="num">Days (compared rules)</th>
                                    <th>Reason</th><th class="num">Carrying amount</th><th class="num">ECL booked</th><th class="num">ECL compared</th><th class="num">ECL effect</th><th>RBM class</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in rows.data" :key="r.id">
                                    <td class="whitespace-nowrap"><div class="font-mono text-sm font-semibold text-gray-900">{{ r.contract_id }}</div><div class="max-w-[13rem] truncate text-xs text-gray-500" :title="r.customer_name">{{ r.customer_name || '-' }}</div></td>
                                    <td class="num">{{ r.booked_stage }}</td>
                                    <td class="num" :class="r.compared_stage !== r.booked_stage ? 'font-semibold text-amber-700' : ''">{{ r.compared_stage }}</td>
                                    <td class="num">{{ r.booked_dpd }}</td>
                                    <td class="num">{{ r.compared_dpd }}</td>
                                    <td class="max-w-[16rem] text-xs">{{ r.reason || '-' }}</td>
                                    <td class="num">{{ money(r.carrying_amount) }}</td>
                                    <td class="num">{{ money(r.booked_ecl) }}</td>
                                    <td class="num">{{ r.compared_ecl === null ? 'not known' : money(r.compared_ecl) }}</td>
                                    <td class="num">{{ r.ecl_effect === null ? '-' : money(r.ecl_effect) }}</td>
                                    <td class="text-xs">{{ r.rbm_class }}</td>
                                </tr>
                                <tr v-if="!rows.data.length"><td colspan="11" class="text-center text-gray-500">No loan to show.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="rows.links" class="px-4 pb-4"><Pagination :links="rows.links"/></div>
                </div>
            </template>
            <div v-else class="maiic-panel p-6 text-center text-sm text-gray-500">No comparison has been run yet.</div>

            <div v-if="runs.length" class="maiic-panel">
                <div class="px-4 pt-3 text-sm font-semibold text-gray-900">Earlier runs</div>
                <div class="maiic-table-wrap">
                    <table class="maiic-table">
                        <thead><tr><th>Run</th><th>Month</th><th>Booked under</th><th>Compared with</th><th class="num">Change stage</th><th class="num">ECL effect</th><th>By, when</th></tr></thead>
                        <tbody>
                            <tr v-for="r in pagedRuns" :key="r.id" class="cursor-pointer hover:bg-gray-50" :class="run && r.id === run.id ? 'bg-amber-50' : ''" @click="open(r.id)">
                                <td>{{ r.id }}</td><td>{{ r.reporting_period }}</td><td>{{ r.booked_preset }}</td><td>{{ r.compared_preset }}</td>
                                <td class="num">{{ r.loans_moved }} of {{ r.loans }}</td><td class="num">{{ money(r.compared_ecl - r.booked_ecl) }}</td>
                                <td class="text-xs">{{ r.run_by || '-' }}, {{ r.created_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <RowPager v-model="runsPage" :total="runs.length"/>
            </div>
        </div>
        <teleport to="head"><title>Staging Comparison</title></teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import KpiRow from '@/Components/Maiic/KpiRow.vue'
import RowPager from '@/Components/Maiic/RowPager.vue'

export default {
    components: { AppLayout, Pagination, KpiRow, RowPager },
    props: {
        runs: { type: Array, default: () => [] },
        run: { type: Object, default: null },
        rows: { type: Object, default: () => ({ data: [] }) },
        show: { type: String, default: 'moved' },
        periods: { type: Array, default: () => [] },
        presets: { type: Array, default: () => [] },
        settings: { type: Object, default: () => ({}) },
    },
    data() {
        return {
            processing: false,
            runsPage: 1,
            form: { period: this.periods[0] || '', preset: 'Directive-aligned staging', note: '', settings: Object.fromEntries(Object.keys(this.settings).map((k) => [k, ''])) },
        }
    },
    computed: {
        changedCount() {
            return Object.values(this.form.settings).filter((v) => v !== '').length
        },
        pagedRuns() {
            return this.runs.slice((this.runsPage - 1) * 15, this.runsPage * 15)
        },
    },
    methods: {
        money(v) {
            return v === null || v === undefined ? '-' : Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        },
        run() {
            this.processing = true
            this.$inertia.post(this.route('staging-comparison.store'), this.form, { onFinish: () => { this.processing = false } })
        },
        open(id) {
            this.$inertia.get(this.route('staging-comparison.index'), { run: id, show: this.show })
        },
        filter(show) {
            this.$inertia.get(this.route('staging-comparison.index'), { run: this.run.id, show }, { preserveState: true, replace: true })
        },
    },
}
</script>
