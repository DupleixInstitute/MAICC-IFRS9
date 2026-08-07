<template>
    <app-layout title="Dashboard">
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight flex items-center gap-2">
                    IFRS 9 ECL Dashboard
                    <HelpManual />
                </h2>

                <!-- Global filter bar: everything on this page is scoped by these.
                     All options come from the database (reporting_periods /
                     loan_portfolios) — nothing hardcoded. -->
                <div class="flex flex-wrap items-end gap-3 bg-maiic-600 rounded-xl px-4 py-2.5 shadow-md">
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-widest text-white/80 mb-0.5">
                            Reporting Period
                        </label>
                        <select v-model="filterForm.period" @change="applyFilters"
                                class="rounded-lg border-0 bg-white text-maiic-800 text-sm font-bold py-1.5 px-3 pr-8 shadow focus:ring-2 focus:ring-white cursor-pointer">
                            <option v-for="period in periods" :key="period" :value="period">{{ period }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-widest text-white/80 mb-0.5">
                            Portfolio
                        </label>
                        <select v-model="filterForm.portfolio_id" @change="applyFilters"
                                class="rounded-lg border-0 bg-white text-maiic-800 text-sm font-bold py-1.5 px-3 pr-8 shadow focus:ring-2 focus:ring-white cursor-pointer">
                            <option :value="null">All portfolios</option>
                            <option v-for="p in portfolios" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-widest text-white/80 mb-0.5">
                            Compare To
                        </label>
                        <select v-model="filterForm.compare" @change="applyFilters"
                                class="rounded-lg border-0 bg-white text-maiic-800 text-sm font-bold py-1.5 px-3 pr-8 shadow focus:ring-2 focus:ring-white cursor-pointer">
                            <option :value="null">Previous period</option>
                            <option v-for="period in comparablePeriods" :key="period" :value="period">{{ period }}</option>
                        </select>
                    </div>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="w-full px-4 sm:px-6 lg:px-10">

                <!-- filter context line -->
                <p class="mb-4 text-sm text-gray-500">
                    Showing <b class="text-gray-700">{{ selectedPeriod }}</b>
                    <template v-if="selectedPortfolioName"> · portfolio <b class="text-gray-700">{{ selectedPortfolioName }}</b></template>
                    <template v-if="comparePeriod"> · compared to <b class="text-gray-700">{{ comparePeriod }}</b></template>
                </p>

                <!-- Headline KPI cards -->
                <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
                    <div v-for="k in kpis" :key="k.label"
                         :class="['rounded-2xl p-5 text-white bg-gradient-to-br shadow', k.cls]">
                        <p class="text-xs uppercase tracking-wider opacity-85 truncate">{{ k.label }}</p>
                        <p class="text-xl xl:text-2xl font-bold mt-2 leading-tight break-words">{{ k.value }}</p>
                        <p v-if="k.sub" class="text-xs mt-1 opacity-80 truncate">{{ k.sub }}</p>
                    </div>
                </div>

                <!-- Stage breakdown -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div v-for="(s, i) in stages" :key="i"
                         class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold text-gray-900">Stage {{ i + 1 }}</h3>
                            <span :class="['text-xs px-2 py-1 rounded-full', s.badge]">{{ s.tag }}</span>
                        </div>
                        <p class="text-xs text-gray-500 mt-3">Exposure (EAD)</p>
                        <p class="text-lg font-bold text-gray-900">{{ currencyCode }} {{ formatAmount(s.ead) }}</p>
                        <div class="flex justify-between mt-3 text-sm">
                            <span class="text-gray-500">ECL</span>
                            <span class="font-semibold text-gray-800">{{ currencyCode }} {{ formatAmount(s.ecl) }}</span>
                        </div>
                        <div class="flex justify-between mt-1 text-sm">
                            <span class="text-gray-500">PD</span>
                            <span class="font-semibold text-gray-800">{{ formatPct(s.pd) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Charts (with chart / table toggle) -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="font-semibold text-gray-900">Portfolio Composition by Stage</h3>
                                <p class="text-xs text-gray-400">{{ selectedPeriod }}<span v-if="selectedPortfolioName"> · {{ selectedPortfolioName }}</span></p>
                            </div>
                            <div class="flex rounded-lg border border-gray-200 overflow-hidden text-xs">
                                <button @click="pieView='chart'" :class="pieView==='chart' ? 'bg-maiic-600 text-white' : 'bg-white text-gray-600'" class="px-3 py-1">Chart</button>
                                <button @click="pieView='table'" :class="pieView==='table' ? 'bg-maiic-600 text-white' : 'bg-white text-gray-600'" class="px-3 py-1">Table</button>
                            </div>
                        </div>
                        <div v-show="pieView==='chart'" class="h-72 flex items-center justify-center">
                            <canvas ref="pieChart"></canvas>
                        </div>
                        <table v-if="pieView==='table'" class="min-w-full text-sm">
                            <thead class="bg-maiic-900 text-white">
                                <tr><th class="px-4 py-2 text-left text-xs uppercase">Stage</th>
                                    <th class="px-4 py-2 text-right text-xs uppercase">EAD</th>
                                    <th class="px-4 py-2 text-right text-xs uppercase">Share</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(s,i) in stages" :key="i" :class="i%2?'bg-maiic-50/40':'bg-white'">
                                    <td class="px-4 py-2">Stage {{ i+1 }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ formatAmount(s.ead) }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ stageShare(i) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="font-semibold text-gray-900">ECL Coverage Trend</h3>
                                <p class="text-xs text-gray-400">All periods<span v-if="selectedPortfolioName"> · {{ selectedPortfolioName }}</span></p>
                            </div>
                            <div class="flex rounded-lg border border-gray-200 overflow-hidden text-xs">
                                <button @click="trendView='chart'" :class="trendView==='chart' ? 'bg-maiic-600 text-white' : 'bg-white text-gray-600'" class="px-3 py-1">Chart</button>
                                <button @click="trendView='table'" :class="trendView==='table' ? 'bg-maiic-600 text-white' : 'bg-white text-gray-600'" class="px-3 py-1">Table</button>
                            </div>
                        </div>
                        <div v-show="trendView==='chart'" class="h-72">
                            <canvas ref="trendChart"></canvas>
                        </div>
                        <table v-if="trendView==='table'" class="min-w-full text-sm">
                            <thead class="bg-maiic-900 text-white">
                                <tr><th class="px-4 py-2 text-left text-xs uppercase">Period</th>
                                    <th class="px-4 py-2 text-right text-xs uppercase">ECL %</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(t,i) in (eclTrends||[])" :key="i" :class="i%2?'bg-maiic-50/40':'bg-white'">
                                    <td class="px-4 py-2">{{ t.period }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ Number(t.ecl_percentage||0).toFixed(2) }}%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Summary table -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-l-4 border-maiic-600">
                        <h3 class="font-semibold text-gray-900">Portfolio Summary</h3>
                    </div>
                    <table class="min-w-full text-sm">
                        <thead class="bg-maiic-900 text-white">
                            <tr>
                                <th class="px-6 py-3 text-left font-medium uppercase text-xs tracking-wider">Metric</th>
                                <th class="px-6 py-3 text-right font-medium uppercase text-xs tracking-wider">Value ({{ currencyCode }})</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(r, i) in summaryRows" :key="i" :class="i % 2 ? 'bg-maiic-50/40' : 'bg-white'">
                                <td class="px-6 py-3 text-gray-700" :class="r.bold ? 'font-bold' : ''">{{ r.label }}</td>
                                <td class="px-6 py-3 text-right tabular-nums text-gray-800" :class="r.bold ? 'font-bold' : ''">{{ r.value }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-if="props.error" class="text-amber-600 text-sm mt-4">{{ props.error }}</p>
            </div>
        </div>
    </app-layout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, onMounted, watch, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Chart, registerables } from 'chart.js';
import HelpManual from '../Components/HelpManual.vue';
Chart.register(...registerables);

const props = defineProps({
    summary: Object,
    periods: Array,
    portfolios: Array,
    selectedPeriod: String,
    selectedPortfolioId: Number,
    comparePeriod: String,
    eclTrends: Array,
    error: String,
});

const page = usePage();
// Organisation reporting currency (Settings > currency) — shared prop.
const currencyCode = computed(() => page.props.currency?.code || '');

const summary = computed(() => props.summary);
const periods = ref(props.periods);
const portfolios = ref(props.portfolios || []);

// Filter state initialised from server-resolved values.
const filterForm = ref({
    period: props.selectedPeriod,
    portfolio_id: props.selectedPortfolioId ?? null,
    compare: props.comparePeriod ?? null,
});

const comparablePeriods = computed(() =>
    (props.periods || []).filter(p => p !== filterForm.value.period));

const selectedPortfolioName = computed(() => {
    const p = (props.portfolios || []).find(p => p.id === props.selectedPortfolioId);
    return p ? p.name : null;
});

function applyFilters() {
    const query = {};
    if (filterForm.value.period) query.period = filterForm.value.period;
    if (filterForm.value.portfolio_id) query.portfolio_id = filterForm.value.portfolio_id;
    if (filterForm.value.compare) query.compare = filterForm.value.compare;
    router.get(route('dashboard'), query, { preserveState: false, preserveScroll: true });
}

const pieChart = ref(null);
const trendChart = ref(null);
let pieInstance = null;
let trendInstance = null;

const C = { maiic: '#16a34a', gold: '#f59e0b', rose: '#e11d48' };

const pieView = ref('chart');
const trendView = ref('chart');

function stageShare(i) {
    const e = (summary.value && summary.value.total_eads) || [0, 0, 0];
    const tot = e[0] + e[1] + e[2];
    return tot ? ((e[i] / tot) * 100).toFixed(1) + '%' : '0%';
}

function formatAmount(amount) {
    return Number(amount || 0).toLocaleString();
}
function formatPct(v) {
    return (Number(v || 0)).toFixed(2) + '%';
}
// Compact currency so KPI cards never overflow: 66.40B / 8.62M / 950.0K.
function money(v) {
    const n = Number(v || 0);
    const a = Math.abs(n);
    const c = currencyCode.value ? currencyCode.value + ' ' : '';
    if (a >= 1e12) return c + (n / 1e12).toFixed(2) + 'T';
    if (a >= 1e9)  return c + (n / 1e9).toFixed(2) + 'B';
    if (a >= 1e6)  return c + (n / 1e6).toFixed(2) + 'M';
    if (a >= 1e3)  return c + (n / 1e3).toFixed(1) + 'K';
    return c + n.toLocaleString();
}

// Signed delta vs the compare-to period, e.g. "+0.42pts vs 2025-10".
function coverageDelta() {
    const s = summary.value || {};
    if (!props.comparePeriod) return null;
    const d = Number(s.ecl_percentage || 0) - Number(s.last_ecl_percentage || 0);
    const sign = d > 0 ? '+' : '';
    return `${sign}${d.toFixed(2)}pts vs ${props.comparePeriod}`;
}

const kpis = computed(() => {
    const s = summary.value || {};
    return [
        { label: 'Total Exposure (EAD)', value: money(s.carrying_amount), cls: 'from-maiic-500 to-maiic-600' },
        { label: 'Total ECL', value: money(s.total_ecl), sub: formatPct(s.ecl_percentage) + ' coverage', cls: 'from-rose-500 to-rose-600' },
        { label: 'ECL Coverage', value: formatPct(s.ecl_percentage), sub: coverageDelta(), cls: 'from-amber-500 to-amber-600' },
        { label: 'Stage 3 Exposure', value: money(s.stage_3_amount), sub: formatPct(s.stage_3_percentage) + ' of book', cls: 'from-rose-500 to-rose-600' },
        { label: 'Weighted PD', value: formatPct(s.weighted_pd), cls: 'from-maiic-500 to-maiic-600' },
        { label: 'Weighted LGD', value: formatPct(s.weighted_lgd), cls: 'from-maiic-600 to-maiic-700' },
    ];
});

const stages = computed(() => {
    const s = summary.value || {};
    const ead = s.total_eads || [0, 0, 0];
    const ecl = s.ecl_totals || [0, 0, 0];
    const pd = s.pd_percentages || [0, 0, 0];
    const meta = [
        { tag: 'Performing', badge: 'bg-maiic-100 text-maiic-800' },
        { tag: 'Underperforming', badge: 'bg-amber-100 text-amber-800' },
        { tag: 'Non-performing', badge: 'bg-rose-100 text-rose-800' },
    ];
    return [0, 1, 2].map(i => ({ ead: ead[i], ecl: ecl[i], pd: pd[i], ...meta[i] }));
});

const summaryRows = computed(() => {
    const s = summary.value || {};
    return [
        { label: 'Total EAD', value: formatAmount(s.carrying_amount), bold: true },
        { label: 'Weighted PD', value: formatPct(s.weighted_pd) },
        { label: 'Weighted LGD', value: formatPct(s.weighted_lgd) },
        { label: 'Net Carrying Amount', value: formatAmount(s.paid_amount) },
        { label: 'Total ECL', value: formatAmount(s.total_ecl), bold: true },
    ];
});

function renderCharts() {
    if (pieInstance) { pieInstance.destroy(); pieInstance = null; }
    if (trendInstance) { trendInstance.destroy(); trendInstance = null; }

    if (pieChart.value) {
        const e = summary.value?.total_eads || [0, 0, 0];
        pieInstance = new Chart(pieChart.value.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Stage 1', 'Stage 2', 'Stage 3'],
                datasets: [{ data: [e[0], e[1], e[2]], backgroundColor: [C.maiic, C.gold, C.rose], borderWidth: 0 }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                animation: { duration: 800 },
            },
        });
    }

    if (trendChart.value) {
        // Auto-scale the y-axis to the data (a fixed 0-100 axis flattened a
        // 3-6% coverage line into invisibility).
        const values = (props.eclTrends || []).map(i => Number(i.ecl_percentage || 0));
        const peak = values.length ? Math.max(...values) : 0;
        trendInstance = new Chart(trendChart.value.getContext('2d'), {
            type: 'line',
            data: {
                labels: (props.eclTrends || []).map(i => i.period),
                datasets: [{
                    label: 'ECL %',
                    data: values,
                    borderColor: C.maiic,
                    backgroundColor: 'rgba(22, 163, 74, 0.15)',
                    fill: true,
                    tension: 0.3,
                    pointBackgroundColor: C.maiic,
                }],
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        suggestedMax: peak > 0 ? Math.ceil(peak * 1.25) : 10,
                        title: { display: true, text: 'ECL %' },
                    },
                    x: { title: { display: true, text: 'Reporting Period' } },
                },
                plugins: { legend: { display: true } },
            },
        });
    }
}

onMounted(renderCharts);
watch(summary, renderCharts);
</script>
