<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import BackToReports from '../Partials/BackToReports.vue'
import DownloadButton from '../Partials/DownloadButton.vue'
import KpiRow from '../Partials/KpiRow.vue'
import TabBar from '../Partials/TabBar.vue'
import ReportIcon from '../Partials/ReportIcon.vue'

const props = defineProps({
    note: { type: Object, default: null },
    error: { type: String, default: null },
    months: { type: Array, default: () => [] },
    opening: { type: String, default: null },
    closing: { type: String, default: null },
    unit: { type: String, default: 'k' },
    units: { type: Array, default: () => [] },
    portfolios: { type: Array, default: () => [] },
    portfolioId: { type: Number, default: null },
    tab: { type: String, default: '' },
})

const form = ref({ opening: props.opening, closing: props.closing, unit: props.unit, portfolio_id: props.portfolioId })
const view = ref('note')
const COLS = [1, 2, 3, 'total']
const STAGES = [['Stage 1', '12-month ECL'], ['Stage 2', 'Lifetime ECL not credit-impaired'], ['Stage 3', 'Lifetime ECL credit-impaired']]

const usable = computed(() => props.note && props.note.status === 'ok')
const hasTables = computed(() => props.note && props.note.tables)
const label = (p) => { if (!p) return ''; const [y, m] = p.split('-'); return new Date(+y, +m - 1, 1).toLocaleString('en-GB', { month: 'short', year: 'numeric' }) }

function apply() {
    const q = { opening: form.value.opening, closing: form.value.closing, unit: form.value.unit }
    if (form.value.portfolio_id) q.portfolio_id = form.value.portfolio_id
    router.get(route('ifrs9-reports.fs-disclosure'), q, { preserveScroll: true })
}

function url(kind) {
    const q = { opening: props.opening, closing: props.closing, unit: props.unit, download: kind }
    if (props.portfolioId) q.portfolio_id = props.portfolioId
    return route('ifrs9-reports.fs-disclosure', q)
}

const decimals = computed(() => props.note?.unit?.decimals ?? 0)
function amount(v) {
    v = Number(v || 0)
    if (v === 0) return '-'
    const t = (Math.abs(v) / 10 ** decimals.value).toLocaleString('en-GB', { minimumFractionDigits: decimals.value, maximumFractionDigits: decimals.value })
    return v < 0 ? '(' + t + ')' : t
}
const pct = (f) => f === null || f === undefined ? '-' : (f * 100).toFixed(1) + '%'
const count = (n) => !n ? '-' : Number(n).toLocaleString()

const checks = computed(() => props.note?.checks || [])
const failed = computed(() => checks.value.filter(c => c.level === 'fail').length)
const tabs = computed(() => [
    { key: 'note', label: 'The note' },
    { key: 'workings', label: 'Workings and checks', count: checks.value.length },
])

const figures = computed(() => {
    if (!hasTables.value) return []
    const ecl = Object.fromEntries(props.note.tables.ecl.rows.map(r => [r.key, r.values]))
    const pos = props.note.position.closing
    const h = props.note.unit.label
    return [
        { label: 'Opening ECL (' + h + ')', value: amount(ecl.opening.total) },
        { label: 'Closing ECL (' + h + ')', value: amount(ecl.closing.total), tone: 'rose' },
        { label: 'Charge for the period', value: amount(props.note.charge.total), tone: 'amber' },
        { label: 'Coverage at ' + label(props.closing), value: pct(pos.coverage.total) },
        { label: 'Contracts at ' + label(props.closing), value: count(pos.contracts.total) },
    ]
})
const levelBadge = { pass: 'maiic-badge-green', warn: 'maiic-badge-gold', fail: 'maiic-badge-red', info: 'maiic-badge-grey' }
</script>

<template>
    <AppLayout title="IFRS 9 note">
        <template #header>
            <BackToReports :tab="tab"/>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">IFRS 9 note for the annual financial statements</h2>
            <p class="mt-0.5 text-sm text-gray-500">The IFRS 7 note: loss allowance and gross carrying amount by stage between two months.</p>
        </template>

        <template #actions>
            <select v-model="form.opening" @change="apply" class="maiic-select w-40" aria-label="Opening month" title="Opening month">
                <option v-for="m in months" :key="'o' + m" :value="m" :disabled="form.closing && m >= form.closing">From {{ label(m) }}</option>
            </select>
            <select v-model="form.closing" @change="apply" class="maiic-select w-40" aria-label="Closing month" title="Closing month">
                <option v-for="m in months" :key="'c' + m" :value="m" :disabled="form.opening && m <= form.opening">To {{ label(m) }}</option>
            </select>
            <select v-model="form.unit" @change="apply" class="maiic-select w-36" aria-label="Units" title="Units of the note">
                <option v-for="u in units" :key="u.value" :value="u.value">{{ u.label }}</option>
            </select>
            <select v-if="portfolios.length > 1" v-model="form.portfolio_id" @change="apply" class="maiic-select w-40" aria-label="Portfolio">
                <option :value="null">All portfolios</option>
                <option v-for="p in portfolios" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <DownloadButton format="CSV" :href="url('csv')" :disabled="!usable" title="The note and workings as CSV data"/>
            <DownloadButton format="Excel" :href="url('xlsx')" :disabled="!usable" title="The note and workings as an Excel workbook"/>
            <DownloadButton format="Word" label="Word" :href="url('docx')" :disabled="!usable" title="The note as a Word document, ready to paste into the financial statements"/>
            <DownloadButton format="PDF" label="Download PDF" :href="url('pdf')" :disabled="!usable" title="The note and workings as a branded PDF"/>
        </template>

        <div class="w-full space-y-4">
            <div v-if="error || (note && note.status === 'no_ecl')" class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <ReportIcon name="alert" class="mt-0.5 h-5 w-5 flex-none text-amber-600"/>
                <p>{{ error || note.message }}</p>
            </div>

            <template v-if="hasTables">
                <div v-if="note.status === 'failed'" class="flex items-center gap-2 rounded-xl border border-red-300 bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-800">
                    <ReportIcon name="alert" class="h-5 w-5 flex-none text-red-600"/>
                    Do not use this note: {{ note.message }} Downloads are stopped until it ties. See Workings and checks ({{ failed }} failed).
                </div>
                <p v-else class="flex items-center gap-2 text-sm text-maiic-800">
                    <ReportIcon name="check" class="h-5 w-5 text-maiic-600"/>
                    Every column of both reconciliations ties, and the figures agree with the loan book and the saved ECL runs. {{ note.scope }}; amounts in {{ note.unit.label }}.
                </p>

                <KpiRow :items="figures"/>

                <div class="maiic-panel">
                    <TabBar v-model="view" :tabs="tabs"/>

                    <div v-if="view === 'note'" class="space-y-6 p-4">
                        <section v-for="(t, key) in { ecl: note.tables.ecl, gross: note.tables.gross }" :key="key">
                            <h3 class="font-semibold text-gray-900">{{ key === 'ecl' ? '(a)' : '(b)' }} {{ t.title }}</h3>
                            <p class="mb-2 text-xs text-gray-500">{{ t.caption }}</p>
                            <div class="maiic-table-wrap rounded-lg border border-gray-200">
                                <table class="maiic-table">
                                    <thead><tr>
                                        <th class="w-[34%]"></th>
                                        <th v-for="(s, i) in STAGES" :key="i" class="num">{{ s[0] }}<span class="block text-[10px] font-normal normal-case tracking-normal opacity-80">{{ s[1] }}</span></th>
                                        <th class="num">Total<span class="block text-[10px] font-normal normal-case tracking-normal opacity-80">{{ note.unit.heading }}</span></th>
                                    </tr></thead>
                                    <tbody>
                                        <tr v-for="row in t.rows" :key="row.key" :class="{ total: row.kind === 'closing', 'font-semibold': row.kind === 'opening' }">
                                            <td>{{ row.label }}</td>
                                            <td v-for="c in COLS" :key="c" class="num">{{ amount(row.values[c]) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section>
                            <h3 class="font-semibold text-gray-900">(c) Exposure, loss allowance and coverage by stage</h3>
                            <p class="mb-2 text-xs text-gray-500">At {{ note.position.closing.label }}, with {{ note.position.opening.label }} for comparison (IFRS 7.35M).</p>
                            <div class="maiic-table-wrap rounded-lg border border-gray-200">
                                <table class="maiic-table">
                                    <thead><tr><th class="w-[34%]"></th><th class="num">Stage 1</th><th class="num">Stage 2</th><th class="num">Stage 3</th><th class="num">Total</th></tr></thead>
                                    <tbody>
                                        <template v-for="side in ['closing', 'opening']" :key="side">
                                            <tr><td colspan="5" class="font-bold text-maiic-800">{{ note.position[side].label }}</td></tr>
                                            <tr><td>Gross carrying amount</td><td v-for="c in COLS" :key="c" class="num">{{ amount(note.position[side].gross[c]) }}</td></tr>
                                            <tr><td>Loss allowance</td><td v-for="c in COLS" :key="c" class="num">{{ amount(-note.position[side].ecl[c]) }}</td></tr>
                                            <tr class="font-bold"><td>Net carrying amount</td><td v-for="c in COLS" :key="c" class="num">{{ amount(note.position[side].net[c]) }}</td></tr>
                                            <tr class="text-gray-500"><td>ECL coverage</td><td v-for="c in COLS" :key="c" class="num">{{ pct(note.position[side].coverage[c]) }}</td></tr>
                                            <tr class="text-gray-500"><td>Number of contracts</td><td v-for="c in COLS" :key="c" class="num">{{ count(note.position[side].contracts[c]) }}</td></tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section>
                            <h3 class="font-semibold text-gray-900">(d) Impairment charge to profit or loss</h3>
                            <div class="maiic-table-wrap mt-2 rounded-lg border border-gray-200">
                                <table class="maiic-table">
                                    <thead><tr><th class="w-[34%]"></th><th class="num">Stage 1</th><th class="num">Stage 2</th><th class="num">Stage 3</th><th class="num">Total</th></tr></thead>
                                    <tbody><tr class="total"><td>ECL charge / (release) for the period</td><td v-for="c in COLS" :key="c" class="num">{{ amount(note.charge[c]) }}</td></tr></tbody>
                                </table>
                            </div>
                        </section>

                        <section>
                            <h3 class="font-semibold text-gray-900">(e) Basis of measurement</h3>
                            <p v-for="(p, k) in note.paragraphs" :key="k" class="mt-2 max-w-5xl text-sm leading-relaxed text-gray-700">{{ p }}</p>
                        </section>
                    </div>

                    <div v-else class="space-y-6 p-4">
                        <section>
                            <h3 class="font-semibold text-gray-900">Checks</h3>
                            <div class="maiic-table-wrap mt-2 rounded-lg border border-gray-200">
                                <table class="maiic-table">
                                    <thead><tr><th>Result</th><th>Check</th><th>Detail</th></tr></thead>
                                    <tbody>
                                        <tr v-for="c in checks" :key="c.key">
                                            <td><span class="maiic-badge" :class="levelBadge[c.level]">{{ c.level.toUpperCase() }}</span></td>
                                            <td class="font-semibold">{{ c.label }}</td>
                                            <td class="text-gray-600">{{ c.detail }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                        <section v-for="(t, key) in { ecl: note.tables.ecl, gross: note.tables.gross }" :key="'r' + key">
                            <template v-if="t.recon">
                                <h3 class="font-semibold text-gray-900">Rounding reconciliation: {{ key === 'ecl' ? 'loss allowance' : 'gross carrying amount' }} ({{ note.unit.label }})</h3>
                                <p class="mb-2 text-xs text-gray-500">Each figure is rounded on its own; the rounding left in a stage column is absorbed in its remeasurement line so the column casts.</p>
                                <div class="maiic-table-wrap rounded-lg border border-gray-200">
                                    <table class="maiic-table">
                                        <thead><tr><th class="w-[34%]"></th><th class="num">Stage 1</th><th class="num">Stage 2</th><th class="num">Stage 3</th><th class="num">Total</th></tr></thead>
                                        <tbody><tr v-for="(r, i) in t.recon" :key="i" :class="{ 'font-semibold': r.rule }"><td>{{ r.label }}</td><td v-for="c in COLS" :key="c" class="num">{{ amount(r.values[c]) }}</td></tr></tbody>
                                    </table>
                                </div>
                            </template>
                        </section>
                        <section>
                            <h3 class="font-semibold text-gray-900">Number of contracts behind each movement</h3>
                            <div class="maiic-table-wrap mt-2 rounded-lg border border-gray-200">
                                <table class="maiic-table">
                                    <thead><tr><th class="w-[34%]"></th><th class="num">Stage 1</th><th class="num">Stage 2</th><th class="num">Stage 3</th><th class="num">Total</th></tr></thead>
                                    <tbody><tr v-for="row in note.tables.ecl.rows" :key="row.key"><td>{{ row.label }}</td><td v-for="c in COLS" :key="c" class="num">{{ count(note.contracts[row.key][c]) }}</td></tr></tbody>
                                </table>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Transfers and remeasurement count the contracts by their closing stage.</p>
                        </section>
                    </div>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
