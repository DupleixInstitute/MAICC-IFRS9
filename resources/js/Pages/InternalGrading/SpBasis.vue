<template>
    <app-layout title="S&P basis" :extra-crumbs="[{ label: 'S&P basis' }]">
        <template #header>
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-slate-100">S&amp;P basis: the December 2025 model's method</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-slate-400">An alternative ECL beside the system's own, for comparison only. It changes no booked figure, no report and no engine result.</p>
            </div>
        </template>
        <template #actions>
            <div class="flex gap-2">
                <Link :href="route('internal-grading.profiles')" class="secondary-btn whitespace-nowrap">Internal Grades</Link>
                <button v-if="canRun" type="button" class="primary-btn whitespace-nowrap" :disabled="!selected || !!settingsError || running" @click="runNow">
                    {{ running ? 'Running...' : 'Run for ' + label(selected) }}
                </button>
            </div>
        </template>

        <div class="w-full space-y-6">
            <div class="maiic-filterbar">
                <div class="grid grid-cols-1 items-end gap-3 md:grid-cols-4">
                    <label class="block">
                        <span class="maiic-flabel">Month</span>
                        <select v-model="selected" class="maiic-select" @change="go">
                            <option v-for="p in periods" :key="p" :value="p">{{ label(p) }}</option>
                        </select>
                    </label>
                    <div class="md:col-span-3 text-xs text-gray-600 dark:text-slate-400">
                        Profile in use: <b>{{ activeProfile || 'none active' }}</b>.
                        <template v-if="settings">Stage: {{ settings.staging }}. Grade: {{ settings.grades }}. Forward-looking factor: {{ settings.macro }}. Recovery: {{ settings.recovery }}.</template>
                        <Link :href="route('eir-governance.index')" class="ml-1 font-semibold text-maiic-700 underline dark:text-maiic-300">Change in the Governance Centre</Link>
                    </div>
                </div>
                <p v-if="settingsError" class="mt-2 text-sm text-red-700 dark:text-red-300">{{ settingsError }}</p>
            </div>

            <template v-if="run">
                <KpiRow :cards="cards" />

                <div class="maiic-panel p-6">
                    <h3 class="maiic-section-title">By stage, {{ label(run.reporting_period) }}</h3>
                    <p class="mb-3 text-xs text-gray-500 dark:text-slate-400">Each side by its own stage. The system's ECL is undiscounted here, as the model's is; the booked allowance is discounted at the EIR where an EIR is locked.</p>
                    <div class="maiic-table-wrap">
                        <table class="maiic-table">
                            <thead>
                                <tr><th>Stage</th><th class="text-right">S&amp;P basis loans</th><th class="text-right">EAD</th><th class="text-right">ECL</th><th class="text-right">System loans</th><th class="text-right">EAD</th><th class="text-right">ECL</th></tr>
                            </thead>
                            <tbody>
                                <tr v-for="st in [1, 2, 3]" :key="st">
                                    <td>Stage {{ st }}</td>
                                    <td class="num">{{ count(run.by_stage.sp_basis[st]?.loans) }}</td>
                                    <td class="num">{{ money(run.by_stage.sp_basis[st]?.ead) }}</td>
                                    <td class="num">{{ money(run.by_stage.sp_basis[st]?.ecl) }}</td>
                                    <td class="num">{{ count(run.by_stage.system[st]?.loans) }}</td>
                                    <td class="num">{{ money(run.by_stage.system[st]?.ead) }}</td>
                                    <td class="num">{{ money(run.by_stage.system[st]?.ecl) }}</td>
                                </tr>
                                <tr class="font-semibold">
                                    <td>Total</td><td class="num">{{ count(run.loans) }}</td><td class="num">{{ money(run.ead) }}</td><td class="num">{{ money(run.ecl) }}</td>
                                    <td class="num">{{ count(run.loans) }}</td><td class="num">{{ money(run.ead) }}</td><td class="num">{{ money(run.system_ecl) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-for="n in run.notes" :key="n" class="mt-2 text-[11px] text-gray-500 dark:text-slate-400">{{ n }}</p>
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-slate-400">Run {{ run.id }} on {{ run.created_at }} with the profile "{{ run.profile_name }}". Settings used: {{ run.settings.staging }}; {{ run.settings.macro }}; recovery: {{ run.settings.recovery }}.</p>
                </div>

                <div class="maiic-panel p-6">
                    <h3 class="maiic-section-title">Loan by loan</h3>
                    <div class="mb-3 flex flex-wrap items-center gap-3">
                        <input v-model="search" class="maiic-input max-w-xs" placeholder="Loan or customer">
                        <span class="text-xs text-gray-500 dark:text-slate-400">{{ filtered.length }} of {{ results.length }} loans, largest S&amp;P basis ECL first</span>
                    </div>
                    <div class="maiic-table-wrap">
                        <table class="maiic-table text-xs">
                            <thead>
                                <tr>
                                    <th>Loan</th><th class="text-right">Days past due</th><th>S&amp;P stage</th><th>Grade</th><th class="text-right">Years</th>
                                    <th class="text-right">S&amp;P PD</th><th class="text-right">PD applied</th><th class="text-right">LGD</th><th class="text-right">EAD</th>
                                    <th class="text-right">Recovery deducted</th><th class="text-right">S&amp;P basis ECL</th>
                                    <th>System stage</th><th class="text-right">System PD (12m)</th><th class="text-right">System ECL</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in shown" :key="r.id">
                                    <td><div class="font-medium">{{ r.contract_id }}</div><div class="text-gray-500 dark:text-slate-400">{{ r.customer_name }}</div></td>
                                    <td class="num">{{ r.days_past_due }}</td>
                                    <td>{{ r.stage }}</td>
                                    <td>{{ r.grade_code }} ({{ r.sp_rating }})</td>
                                    <td class="num">{{ r.horizon_years || '-' }}</td>
                                    <td class="num">{{ pct(r.sp_pd) }}</td>
                                    <td class="num">{{ pct(r.pd) }}</td>
                                    <td class="num">{{ pct(r.lgd) }}</td>
                                    <td class="num">{{ money(r.ead) }}</td>
                                    <td class="num">{{ money(r.recovery_pv) }}</td>
                                    <td class="num font-semibold">{{ money(r.ecl) }}</td>
                                    <td>{{ r.system_stage ?? '-' }}</td>
                                    <td class="num">{{ r.system_pd === null ? '-' : pct(r.system_pd) }}</td>
                                    <td class="num">{{ r.system_ecl === null ? '-' : money(r.system_ecl) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <RowPager v-model="page" :total="filtered.length" class="border-t border-gray-100" />
                </div>
            </template>
            <p v-else class="maiic-empty">No S&amp;P basis has been run for {{ selected ? label(selected) : 'any month' }}.<template v-if="canRun"> Use "Run for {{ label(selected) }}" above.</template></p>
        </div>
        <teleport to="head"><title>S&amp;P basis</title></teleport>
    </app-layout>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import KpiRow from '@/Components/Maiic/KpiRow.vue'
import RowPager from '@/Components/Maiic/RowPager.vue'

const props = defineProps({
    periods: { type: Array, default: () => [] },
    period: { type: String, default: null },
    run: { type: Object, default: null },
    results: { type: Array, default: () => [] },
    settings: { type: Object, default: null },
    settingsError: { type: String, default: null },
    activeProfile: { type: String, default: null },
    canRun: { type: Boolean, default: false },
})

const selected = ref(props.period)
const running = ref(false)
const search = ref('')
// 15 loans a page; a new search starts again at page 1
const page = ref(1)
watch(search, () => { page.value = 1 })
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

function label(p) {
    if (!p) return ''
    const [y, m] = p.split('-')
    return MONTHS[Number(m) - 1] + ' ' + y
}
function money(v) {
    return v === null || v === undefined ? '-' : Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
function count(v) {
    return Number(v || 0).toLocaleString()
}
function pct(v) {
    return (Number(v) * 100).toFixed(2) + '%'
}
function go() {
    router.get(route('internal-grading.sp-basis'), { period: selected.value }, { preserveState: false })
}
function runNow() {
    running.value = true
    router.post(route('internal-grading.sp-basis.run'), { period: selected.value }, { onFinish: () => { running.value = false } })
}

const cards = computed(() => {
    const r = props.run
    const diff = r.ecl - r.system_ecl
    return [
        { label: 'Loans', value: r.loans, sub: 'MAIIC and FInES; Mega Farm outside' },
        { label: 'S&P basis ECL', value: money(r.ecl), raw: true, sub: 'The December 2025 model\'s method', accent: '#b45309' },
        { label: 'System ECL (undiscounted)', value: money(r.system_ecl), raw: true, sub: 'The booked engines, before discounting', accent: '#0f766e' },
        { label: 'S&P basis less system', value: money(diff), raw: true, sub: r.system_ecl ? (diff / r.system_ecl * 100).toFixed(1) + '% of the system figure' : '', accent: '#475569' },
    ]
})
const filtered = computed(() => {
    const q = search.value.trim().toLowerCase()
    return q ? props.results.filter(r => String(r.contract_id).includes(q) || String(r.customer_name || '').toLowerCase().includes(q)) : props.results
})
const shown = computed(() => filtered.value.slice((page.value - 1) * 15, page.value * 15))
</script>
