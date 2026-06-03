<script setup>
import { reactive, ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    periods: { type: Array, default: () => [] },
    portfolios: { type: Array, default: () => [] },
    scenarios: { type: Array, default: () => [] },
})

const form = reactive({
    period: props.periods[0] || '',
    loan_portfolio_id: '',
    s1_pd_mult: 1, s2_pd_mult: 1, s3_pd_mult: 1,
    s1_lgd_add: 0, s2_lgd_add: 0, s3_lgd_add: 0,
})

// Agri-relevant presets (PD multipliers per stage, LGD add-on in % points).
const presets = {
    Baseline:        { s1_pd_mult: 1,   s2_pd_mult: 1,   s3_pd_mult: 1,   s1_lgd_add: 0,  s2_lgd_add: 0,  s3_lgd_add: 0 },
    'Mild drought':  { s1_pd_mult: 1.3, s2_pd_mult: 1.5, s3_pd_mult: 1,   s1_lgd_add: 3,  s2_lgd_add: 5,  s3_lgd_add: 5 },
    'Severe drought':{ s1_pd_mult: 2,   s2_pd_mult: 2.5, s3_pd_mult: 1,   s1_lgd_add: 8,  s2_lgd_add: 10, s3_lgd_add: 12 },
    'MWK / input shock': { s1_pd_mult: 1.6, s2_pd_mult: 1.8, s3_pd_mult: 1, s1_lgd_add: 5, s2_lgd_add: 7, s3_lgd_add: 8 },
}
function applyPreset(name) {
    Object.assign(form, presets[name])
}

const result = ref(null)
const running = ref(false)
const error = ref('')
const saveName = ref('')
const saveDesc = ref('')
const savedList = ref([...props.scenarios])

const STAGE = { 1: 'Stage 1', 2: 'Stage 2', 3: 'Stage 3' }

function money(v) {
    return 'MWK ' + Number(v || 0).toLocaleString(undefined, { maximumFractionDigits: 0 })
}
function pct(v) {
    return (Number(v || 0) * 100).toFixed(2) + '%'
}

async function run() {
    running.value = true
    error.value = ''
    try {
        const { data } = await axios.post(route('stress-testing.run'), form)
        result.value = data
    } catch (e) {
        error.value = e?.response?.data?.message || 'Run failed. Check the selected period has data.'
    } finally {
        running.value = false
    }
}

async function saveScenario() {
    if (!saveName.value.trim()) { error.value = 'Give the scenario a name first.'; return }
    try {
        const { data } = await axios.post(route('stress-testing.save'), {
            ...form,
            scenario_name: saveName.value,
            description: saveDesc.value,
            result_snapshot: result.value,
        })
        savedList.value.unshift(data.scenario)
        saveName.value = ''
        saveDesc.value = ''
    } catch (e) {
        error.value = 'Could not save scenario.'
    }
}

function loadScenario(s) {
    form.period = s.reporting_period
    form.loan_portfolio_id = s.loan_portfolio_id || ''
    form.s1_pd_mult = s.s1_pd_mult; form.s2_pd_mult = s.s2_pd_mult; form.s3_pd_mult = s.s3_pd_mult
    form.s1_lgd_add = s.s1_lgd_add; form.s2_lgd_add = s.s2_lgd_add; form.s3_lgd_add = s.s3_lgd_add
    run()
}

async function deleteScenario(s) {
    if (!confirm(`Delete scenario "${s.scenario_name}"?`)) return
    await axios.delete(route('stress-testing.destroy', s.id))
    savedList.value = savedList.value.filter(x => x.id !== s.id)
}

const deltaTone = computed(() =>
    !result.value ? 'maiic'
        : result.value.delta > 0 ? 'rose'
        : result.value.delta < 0 ? 'emerald' : 'maiic')

const toneCls = {
    maiic: 'from-maiic-500 to-maiic-600',
    rose: 'from-rose-500 to-rose-600',
    emerald: 'from-emerald-500 to-emerald-600',
    amber: 'from-amber-500 to-amber-600',
}
</script>

<template>
    <AppLayout title="Stress Testing">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Stress Testing</h2>
                <Link :href="route('ifrs9-reports.index')" class="text-sm text-maiic-600 hover:underline">
                    IFRS 9 Reports &rarr;
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- Scenario builder -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900">ECL Stress Scenario</h1>
                            <p class="text-gray-500 mt-1 text-sm">
                                Apply per-stage PD multipliers and LGD add-ons (percentage points). ECL is
                                recomputed loan-by-loan (EAD &times; PD &times; LGD, capped at 100%).
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="(_, name) in presets" :key="name" @click="applyPreset(name)"
                                    class="px-3 py-1.5 text-xs font-medium rounded-lg border border-maiic-300 text-maiic-700 bg-maiic-50 hover:bg-maiic-100">
                                {{ name }}
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-5">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Reporting Period</label>
                            <select v-model="form.period"
                                    class="w-full rounded-lg border-gray-300 text-sm py-2 px-3 shadow-sm focus:ring-maiic-500 focus:border-maiic-500">
                                <option v-for="p in periods" :key="p" :value="p">{{ p }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Portfolio</label>
                            <select v-model="form.loan_portfolio_id"
                                    class="w-full rounded-lg border-gray-300 text-sm py-2 px-3 shadow-sm focus:ring-maiic-500 focus:border-maiic-500">
                                <option value="">All portfolios</option>
                                <option v-for="p in portfolios" :key="p.value" :value="p.value">{{ p.label }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="overflow-x-auto mt-5">
                        <table class="min-w-full text-sm border border-gray-100 rounded-lg">
                            <thead class="bg-maiic-900 text-white">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs uppercase tracking-wider">Stage</th>
                                    <th class="px-4 py-2 text-left text-xs uppercase tracking-wider">PD multiplier (&times;)</th>
                                    <th class="px-4 py-2 text-left text-xs uppercase tracking-wider">LGD add-on (% pts)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="s in [1,2,3]" :key="s" :class="s % 2 ? 'bg-maiic-50/40' : 'bg-white'">
                                    <td class="px-4 py-2 font-medium text-gray-700 whitespace-nowrap">{{ STAGE[s] }}</td>
                                    <td class="px-4 py-2">
                                        <input type="number" step="0.1" min="0.01"
                                               v-model.number="form[`s${s}_pd_mult`]"
                                               class="w-28 rounded-md border-gray-300 text-sm py-1.5 px-2 focus:ring-maiic-500 focus:border-maiic-500"/>
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="number" step="0.5" min="0"
                                               v-model.number="form[`s${s}_lgd_add`]"
                                               class="w-28 rounded-md border-gray-300 text-sm py-1.5 px-2 focus:ring-maiic-500 focus:border-maiic-500"/>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-5 flex items-center gap-3">
                        <button @click="run" :disabled="running || !form.period"
                                class="inline-flex items-center px-5 py-2 bg-maiic-600 hover:bg-maiic-700 text-white text-sm font-medium rounded-lg shadow disabled:opacity-50">
                            {{ running ? 'Running…' : 'Run Stress Test' }}
                        </button>
                        <span v-if="error" class="text-rose-600 text-sm">{{ error }}</span>
                    </div>
                </div>

                <!-- Results -->
                <template v-if="result">
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                        <div :class="['rounded-2xl p-5 text-white bg-gradient-to-br shadow', toneCls.maiic]">
                            <p class="text-xs uppercase tracking-wider opacity-85">Base ECL</p>
                            <p class="text-2xl font-bold mt-2">{{ money(result.total_base_ecl) }}</p>
                        </div>
                        <div :class="['rounded-2xl p-5 text-white bg-gradient-to-br shadow', toneCls.amber]">
                            <p class="text-xs uppercase tracking-wider opacity-85">Stressed ECL</p>
                            <p class="text-2xl font-bold mt-2">{{ money(result.total_stress_ecl) }}</p>
                        </div>
                        <div :class="['rounded-2xl p-5 text-white bg-gradient-to-br shadow', toneCls[deltaTone]]">
                            <p class="text-xs uppercase tracking-wider opacity-85">&Delta; ECL</p>
                            <p class="text-2xl font-bold mt-2">{{ money(result.delta) }}</p>
                        </div>
                        <div :class="['rounded-2xl p-5 text-white bg-gradient-to-br shadow', toneCls[deltaTone]]">
                            <p class="text-xs uppercase tracking-wider opacity-85">&Delta; %</p>
                            <p class="text-2xl font-bold mt-2">{{ pct(result.delta_pct) }}</p>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 border-l-4 border-maiic-600">
                            <h3 class="font-semibold text-gray-900">By IFRS 9 Stage</h3>
                        </div>
                        <table class="min-w-full text-sm">
                            <thead class="bg-maiic-900 text-white">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs uppercase tracking-wider whitespace-nowrap">Stage</th>
                                    <th class="px-5 py-3 text-right text-xs uppercase tracking-wider">Accounts</th>
                                    <th class="px-5 py-3 text-right text-xs uppercase tracking-wider">Exposure</th>
                                    <th class="px-5 py-3 text-right text-xs uppercase tracking-wider">Base ECL</th>
                                    <th class="px-5 py-3 text-right text-xs uppercase tracking-wider">Stressed ECL</th>
                                    <th class="px-5 py-3 text-right text-xs uppercase tracking-wider">&Delta;</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(r, i) in result.by_stage" :key="i" :class="i % 2 ? 'bg-maiic-50/40' : 'bg-white'">
                                    <td class="px-5 py-3 whitespace-nowrap text-gray-700">{{ STAGE[r.stage] || ('Stage ' + r.stage) }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums">{{ Number(r.accounts).toLocaleString() }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums">{{ money(r.exposure) }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums">{{ money(r.base_ecl) }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums">{{ money(r.stress_ecl) }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums"
                                        :class="r.stress_ecl - r.base_ecl > 0 ? 'text-rose-600' : 'text-emerald-600'">
                                        {{ money(r.stress_ecl - r.base_ecl) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="px-6 py-4 border-l-4 border-maiic-600">
                            <h3 class="font-semibold text-gray-900">By Portfolio</h3>
                        </div>
                        <table class="min-w-full text-sm">
                            <thead class="bg-maiic-900 text-white">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs uppercase tracking-wider whitespace-nowrap">Portfolio</th>
                                    <th class="px-5 py-3 text-right text-xs uppercase tracking-wider">Accounts</th>
                                    <th class="px-5 py-3 text-right text-xs uppercase tracking-wider">Exposure</th>
                                    <th class="px-5 py-3 text-right text-xs uppercase tracking-wider">Base ECL</th>
                                    <th class="px-5 py-3 text-right text-xs uppercase tracking-wider">Stressed ECL</th>
                                    <th class="px-5 py-3 text-right text-xs uppercase tracking-wider">&Delta; %</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(r, i) in result.by_portfolio" :key="i" :class="i % 2 ? 'bg-maiic-50/40' : 'bg-white'">
                                    <td class="px-5 py-3 whitespace-nowrap text-gray-700">{{ r.portfolio }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums">{{ Number(r.accounts).toLocaleString() }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums">{{ money(r.exposure) }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums">{{ money(r.base_ecl) }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums">{{ money(r.stress_ecl) }}</td>
                                    <td class="px-5 py-3 text-right tabular-nums"
                                        :class="r.stress_ecl - r.base_ecl > 0 ? 'text-rose-600' : 'text-emerald-600'">
                                        {{ r.base_ecl > 0 ? ((r.stress_ecl - r.base_ecl) / r.base_ecl * 100).toFixed(1) + '%' : '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Save scenario -->
                    <div class="bg-white rounded-2xl shadow-sm border border-maiic-200 p-6">
                        <h3 class="font-semibold text-gray-900 mb-3">Save this scenario</h3>
                        <div class="flex flex-wrap items-end gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Name</label>
                                <input v-model="saveName" type="text" placeholder="e.g. 2025 severe drought"
                                       class="rounded-lg border-gray-300 text-sm py-2 px-3 w-56 focus:ring-maiic-500 focus:border-maiic-500"/>
                            </div>
                            <div class="flex-1 min-w-[12rem]">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Description</label>
                                <input v-model="saveDesc" type="text" placeholder="Optional notes"
                                       class="w-full rounded-lg border-gray-300 text-sm py-2 px-3 focus:ring-maiic-500 focus:border-maiic-500"/>
                            </div>
                            <button @click="saveScenario"
                                    class="px-5 py-2 bg-maiic-600 hover:bg-maiic-700 text-white text-sm font-medium rounded-lg shadow">
                                Save
                            </button>
                        </div>
                    </div>
                </template>

                <!-- Saved scenarios -->
                <div v-if="savedList.length" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-l-4 border-maiic-600">
                        <h3 class="font-semibold text-gray-900">Saved Scenarios</h3>
                    </div>
                    <table class="min-w-full text-sm">
                        <thead class="bg-maiic-900 text-white">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs uppercase tracking-wider">Name</th>
                                <th class="px-5 py-3 text-left text-xs uppercase tracking-wider whitespace-nowrap">Period</th>
                                <th class="px-5 py-3 text-left text-xs uppercase tracking-wider">PD ×(S1/S2/S3)</th>
                                <th class="px-5 py-3 text-left text-xs uppercase tracking-wider">LGD +(S1/S2/S3)</th>
                                <th class="px-5 py-3 text-left text-xs uppercase tracking-wider">Saved by</th>
                                <th class="px-5 py-3 text-right text-xs uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(s, i) in savedList" :key="s.id" :class="i % 2 ? 'bg-maiic-50/40' : 'bg-white'">
                                <td class="px-5 py-3 text-gray-800 font-medium">{{ s.scenario_name }}</td>
                                <td class="px-5 py-3 whitespace-nowrap">{{ s.reporting_period }}</td>
                                <td class="px-5 py-3 whitespace-nowrap">{{ s.s1_pd_mult }}/{{ s.s2_pd_mult }}/{{ s.s3_pd_mult }}</td>
                                <td class="px-5 py-3 whitespace-nowrap">{{ s.s1_lgd_add }}/{{ s.s2_lgd_add }}/{{ s.s3_lgd_add }}</td>
                                <td class="px-5 py-3">{{ s.saved_by }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <button @click="loadScenario(s)" class="text-maiic-600 hover:underline mr-3">Load</button>
                                    <button @click="deleteScenario(s)" class="text-rose-600 hover:underline">Delete</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
