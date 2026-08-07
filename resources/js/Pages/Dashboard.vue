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
                     loan_portfolios) - nothing hardcoded. -->
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

                <!-- Headline KPI tiles: one standard design (white card, accent
                     bar, metric icon), compact value with the exact amount in
                     small text + tooltip, and direction-aware change chips -->
                <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">
                    <div v-for="k in kpis" :key="k.label"
                         class="maiic-kpi" :style="{ '--accent': k.accent }" :title="k.full || k.value">
                        <div class="flex items-center gap-2">
                            <span class="flex h-7 w-7 flex-none items-center justify-center rounded-lg"
                                  :style="{ backgroundColor: k.accent + '18', color: k.accent }">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path :d="k.icon"/></svg>
                            </span>
                            <p class="maiic-kpi-label !mb-0 truncate">{{ k.label }}</p>
                        </div>
                        <p class="maiic-kpi-value mt-2 break-words text-xl xl:text-2xl">{{ k.value }}</p>
                        <p v-if="k.full" class="mt-0.5 truncate text-[11px] text-gray-400 tabular-nums">{{ k.full }}</p>
                        <p v-if="k.delta" class="mt-1.5">
                            <span :class="['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-bold',
                                           k.deltaGood === null ? 'bg-gray-100 text-gray-600'
                                               : k.deltaGood ? 'bg-maiic-100 text-maiic-800' : 'bg-red-100 text-red-700']">
                                <span v-if="k.deltaUp !== null">{{ k.deltaUp ? '▲' : '▼' }}</span>{{ k.delta }}
                            </span>
                        </p>
                        <p v-else-if="k.sub" class="mt-1.5 text-xs text-gray-400 truncate">{{ k.sub }}</p>
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
                        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                            <div>
                                <h3 class="font-semibold text-gray-900">ECL Coverage Trend</h3>
                                <p class="text-xs text-gray-400">
                                    {{ trendRangeLabel }}<span v-if="selectedPortfolioName"> · {{ selectedPortfolioName }}</span>
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <!-- Trend range (periods from the database) -->
                                <select v-model="filterForm.trend_from" @change="applyFilters"
                                        class="rounded-md border border-maiic-300 bg-maiic-50 py-1 pl-2 pr-7 text-xs font-semibold text-maiic-800 focus:border-maiic-500 focus:ring-maiic-500">
                                    <option :value="null">From: first</option>
                                    <option v-for="p in periods" :key="'f'+p" :value="p">From {{ p }}</option>
                                </select>
                                <select v-model="filterForm.trend_to" @change="applyFilters"
                                        class="rounded-md border border-maiic-300 bg-maiic-50 py-1 pl-2 pr-7 text-xs font-semibold text-maiic-800 focus:border-maiic-500 focus:ring-maiic-500">
                                    <option :value="null">To: latest</option>
                                    <option v-for="p in periods" :key="'t'+p" :value="p">To {{ p }}</option>
                                </select>
                                <div class="flex rounded-lg border border-gray-200 overflow-hidden text-xs">
                                    <button @click="trendView='chart'" :class="trendView==='chart' ? 'bg-maiic-600 text-white' : 'bg-white text-gray-600'" class="px-3 py-1">Chart</button>
                                    <button @click="trendView='table'" :class="trendView==='table' ? 'bg-maiic-600 text-white' : 'bg-white text-gray-600'" class="px-3 py-1">Table</button>
                                </div>
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

                <!-- Summary table: current vs compare-to with traffic lights -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-l-4 border-maiic-600 flex flex-wrap items-baseline justify-between gap-2">
                        <h3 class="font-semibold text-gray-900">Portfolio Summary</h3>
                        <p class="text-xs text-gray-400">Values in {{ currencyCode }}<template v-if="comparePeriod"> · compared to {{ comparePeriod }}</template></p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-maiic-900 text-white">
                                <tr>
                                    <th class="px-6 py-3 text-left font-medium uppercase text-xs tracking-wider">Metric</th>
                                    <th class="px-6 py-3 text-right font-medium uppercase text-xs tracking-wider">Current ({{ selectedPeriod }})</th>
                                    <th v-if="comparePeriod" class="px-6 py-3 text-right font-medium uppercase text-xs tracking-wider">Compare To ({{ comparePeriod }})</th>
                                    <th v-if="comparePeriod" class="px-6 py-3 text-right font-medium uppercase text-xs tracking-wider">Change</th>
                                    <th v-if="comparePeriod" class="px-6 py-3 text-center font-medium uppercase text-xs tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(r, i) in summaryRows" :key="i" :class="i % 2 ? 'bg-maiic-50/40' : 'bg-white'">
                                    <td class="px-6 py-3 text-gray-700" :class="r.bold ? 'font-bold' : ''">{{ r.label }}</td>
                                    <td class="px-6 py-3 num text-gray-800" :class="r.bold ? 'font-bold' : ''">{{ r.value }}</td>
                                    <td v-if="comparePeriod" class="px-6 py-3 num text-gray-500">{{ r.compare ?? '-' }}</td>
                                    <td v-if="comparePeriod" class="px-6 py-3 num font-semibold"
                                        :class="r.status === 'good' ? 'text-maiic-700' : r.status === 'bad' ? 'text-red-600' : r.status === 'watch' ? 'text-amber-600' : 'text-gray-400'">
                                        {{ r.change ?? '-' }}
                                    </td>
                                    <td v-if="comparePeriod" class="px-6 py-3 text-center">
                                        <span v-if="r.status === 'good'" title="Favourable movement"
                                              class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-maiic-100 text-maiic-700">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5m-6 6 6-6 6 6"/></svg>
                                        </span>
                                        <span v-else-if="r.status === 'bad'" title="Adverse movement"
                                              class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-red-100 text-red-600">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01"/></svg>
                                        </span>
                                        <span v-else-if="r.status === 'watch'" title="Watch: moderate movement"
                                              class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-amber-100 text-amber-700">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4"/></svg>
                                        </span>
                                        <span v-else class="text-gray-300">-</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
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
    compareSummary: Object,
    periods: Array,
    portfolios: Array,
    selectedPeriod: String,
    selectedPortfolioId: Number,
    comparePeriod: String,
    trendFrom: String,
    trendTo: String,
    eclTrends: Array,
    error: String,
});

const page = usePage();
// Organisation reporting currency (Settings > currency) - shared prop.
const currencyCode = computed(() => page.props.currency?.code || '');

const summary = computed(() => props.summary);
const periods = ref(props.periods);
const portfolios = ref(props.portfolios || []);

// Filter state initialised from server-resolved values.
const filterForm = ref({
    period: props.selectedPeriod,
    portfolio_id: props.selectedPortfolioId ?? null,
    compare: props.comparePeriod ?? null,
    trend_from: props.trendFrom ?? null,
    trend_to: props.trendTo ?? null,
});

const comparablePeriods = computed(() =>
    (props.periods || []).filter(p => p !== filterForm.value.period));

const selectedPortfolioName = computed(() => {
    const p = (props.portfolios || []).find(p => p.id === props.selectedPortfolioId);
    return p ? p.name : null;
});

const trendRangeLabel = computed(() => {
    const t = props.eclTrends || [];
    if (!t.length) return 'No periods in range';
    if (props.trendFrom || props.trendTo) return `${t[0].period} to ${t[t.length - 1].period}`;
    return 'All periods';
});

function applyFilters() {
    const query = {};
    if (filterForm.value.period) query.period = filterForm.value.period;
    if (filterForm.value.portfolio_id) query.portfolio_id = filterForm.value.portfolio_id;
    if (filterForm.value.compare) query.compare = filterForm.value.compare;
    if (filterForm.value.trend_from) query.trend_from = filterForm.value.trend_from;
    if (filterForm.value.trend_to) query.trend_to = filterForm.value.trend_to;
    router.get(route('dashboard'), query, { preserveState: false, preserveScroll: true });
}

const pieChart = ref(null);
const trendChart = ref(null);
let pieInstance = null;
let trendInstance = null;

const C = { maiic: '#16a34a', gold: '#f59e0b', rose: '#dc2626' };

const pieView = ref('chart');
const trendView = ref('chart');

function stageShare(i) {
    const e = (summary.value && summary.value.total_eads) || [0, 0, 0];
    const tot = e[0] + e[1] + e[2];
    return tot ? ((e[i] / tot) * 100).toFixed(1) + '%' : '0%';
}

function formatAmount(amount) {
    const n = Number(amount || 0);
    const abs = Math.abs(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return n < 0 ? '(' + abs + ')' : abs;
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

// Signed change vs the compare-to period. Money values compare as a
// percentage change; ratio values compare in percentage points.
// goodWhenUp says whether an increase is favourable (book growth) or
// adverse (risk metrics), which drives the chip colour.
function deltaInfo(key, kind, goodWhenUp) {
    const s = summary.value || {};
    const c = props.compareSummary;
    if (!c || !props.comparePeriod) return { delta: null, deltaUp: null, deltaGood: null };
    const now = Number(s[key] || 0);
    const then = Number(c[key] || 0);
    let text, d;
    if (kind === 'money') {
        if (then === 0) return { delta: null, deltaUp: null, deltaGood: null };
        d = ((now - then) / Math.abs(then)) * 100;
        text = `${d > 0 ? '+' : ''}${d.toFixed(1)}% vs ${props.comparePeriod}`;
    } else {
        d = now - then;
        text = `${d > 0 ? '+' : ''}${d.toFixed(2)}pts vs ${props.comparePeriod}`;
    }
    if (Math.abs(d) < 0.005) return { delta: text, deltaUp: null, deltaGood: null };
    return { delta: text, deltaUp: d > 0, deltaGood: goodWhenUp ? d > 0 : d < 0 };
}

function fullMoney(v) {
    return currencyCode.value + ' ' + formatAmount(v);
}

const kpis = computed(() => {
    const s = summary.value || {};
    const A = { green: '#15803d', gold: '#d97706', red: '#dc2626' };
    // Lucide-style single-path icons per metric.
    const I = {
        bank: 'M3 21h18M4 18h16M6 18V9m4 9V9m4 9V9m4 9V9M2 9l10-6 10 6H2Z',
        alert: 'M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0ZM12 9v4m0 4h.01',
        shield: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Zm-3-10 2 2 4-4',
        pie: 'M21.2 15.9A10 10 0 1 1 8 2.8M22 12A10 10 0 0 0 12 2v10Z',
        trend: 'M3 3v18h18M7 14l4-4 3 3 5-6',
        scale: 'M12 3v18M8 21h8M7 7l-4 6a4 4 0 0 0 8 0L7 7Zm10 0-4 6a4 4 0 0 0 8 0l-4-6ZM4 7h16',
    };
    return [
        { label: 'Total Exposure (EAD)', value: money(s.carrying_amount), full: fullMoney(s.carrying_amount), icon: I.bank, accent: A.green, ...deltaInfo('carrying_amount', 'money', true) },
        { label: 'Total ECL', value: money(s.total_ecl), full: fullMoney(s.total_ecl), icon: I.alert, accent: A.red, sub: formatPct(s.ecl_percentage) + ' coverage', ...deltaInfo('total_ecl', 'money', false) },
        { label: 'ECL Coverage', value: formatPct(s.ecl_percentage), full: null, icon: I.shield, accent: A.gold, ...deltaInfo('ecl_percentage', 'pts', false) },
        { label: 'Stage 3 Exposure', value: money(s.stage_3_amount), full: fullMoney(s.stage_3_amount), icon: I.pie, accent: A.red, sub: formatPct(s.stage_3_percentage) + ' of book', ...deltaInfo('stage_3_amount', 'money', false) },
        { label: 'Weighted PD', value: formatPct(s.weighted_pd), full: null, icon: I.trend, accent: A.green, ...deltaInfo('weighted_pd', 'pts', false) },
        { label: 'Weighted LGD', value: formatPct(s.weighted_lgd), full: null, icon: I.scale, accent: A.green, ...deltaInfo('weighted_lgd', 'pts', false) },
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
        { tag: 'Non-performing', badge: 'bg-red-100 text-red-800' },
    ];
    return [0, 1, 2].map(i => ({ ead: ead[i], ecl: ecl[i], pd: pd[i], ...meta[i] }));
});

// 5-column compare table: current vs compare-to with metric-aware
// traffic lights (a rise in exposure is growth; a rise in ECL/PD/LGD is risk).
const summaryRows = computed(() => {
    const s = summary.value || {};
    const c = props.compareSummary || null;

    const row = (label, key, kind, goodWhenUp, bold = false) => {
        const now = Number(s[key] || 0);
        const then = c ? Number(c[key] || 0) : null;
        let change = null, status = null;
        if (c && then !== null) {
            if (kind === 'money') {
                change = then !== 0 ? (((now - then) / Math.abs(then)) * 100) : null;
                if (change !== null) {
                    const good = goodWhenUp ? change > 0 : change < 0;
                    status = Math.abs(change) < 1 ? 'neutral' : (good ? 'good' : (Math.abs(change) < 10 ? 'watch' : 'bad'));
                    change = `${change > 0 ? '+' : ''}${change.toFixed(1)}%`;
                }
            } else {
                const d = now - then;
                const good = goodWhenUp ? d > 0 : d < 0;
                status = Math.abs(d) < 0.05 ? 'neutral' : (good ? 'good' : (Math.abs(d) < 1 ? 'watch' : 'bad'));
                change = `${d > 0 ? '+' : ''}${d.toFixed(2)}pts`;
            }
        }
        return {
            label, bold,
            value: kind === 'money' ? formatAmount(now) : formatPct(now),
            compare: c ? (kind === 'money' ? formatAmount(then) : formatPct(then)) : null,
            change, status,
        };
    };

    return [
        row('Total EAD', 'carrying_amount', 'money', true, true),
        row('Weighted PD', 'weighted_pd', 'pts', false),
        row('Weighted LGD', 'weighted_lgd', 'pts', false),
        row('Net Carrying Amount', 'paid_amount', 'money', true),
        row('Total ECL', 'total_ecl', 'money', false, true),
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
                datasets: [{
                    data: [e[0], e[1], e[2]],
                    backgroundColor: [C.maiic, C.gold, C.red],
                    borderWidth: 3,
                    borderColor: '#ffffff',
                    hoverOffset: 10,
                }],
            },
            options: {
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { usePointStyle: true, pointStyle: 'circle', padding: 16, color: '#475569', font: { size: 12 } },
                    },
                    tooltip: {
                        backgroundColor: '#0b2b1a',
                        titleColor: '#fbbf24',
                        bodyColor: '#ffffff',
                        padding: 12,
                        cornerRadius: 10,
                        callbacks: {
                            label: (item) => {
                                const total = e[0] + e[1] + e[2];
                                const share = total ? ((item.parsed / total) * 100).toFixed(1) : 0;
                                return ' ' + formatAmount(item.parsed) + ' (' + share + '%)';
                            },
                        },
                    },
                },
                animation: { duration: 800 },
            },
        });
    }

    if (trendChart.value) {
        // Auto-scale the y-axis to the data (a fixed 0-100 axis flattened a
        // 3-6% coverage line into invisibility).
        const values = (props.eclTrends || []).map(i => Number(i.ecl_percentage || 0));
        const peak = values.length ? Math.max(...values) : 0;
        const ctx = trendChart.value.getContext('2d');
        // Soft brand-green area gradient under the line.
        const fillGrad = ctx.createLinearGradient(0, 0, 0, trendChart.value.clientHeight || 288);
        fillGrad.addColorStop(0, 'rgba(22, 163, 74, 0.28)');
        fillGrad.addColorStop(0.6, 'rgba(22, 163, 74, 0.10)');
        fillGrad.addColorStop(1, 'rgba(22, 163, 74, 0.01)');
        trendInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: (props.eclTrends || []).map(i => i.period),
                datasets: [{
                    label: 'ECL Coverage %',
                    data: values,
                    borderColor: C.maiic,
                    borderWidth: 2.5,
                    backgroundColor: fillGrad,
                    fill: true,
                    tension: 0.4,
                    pointRadius: (props.eclTrends || []).map(i => i.period === props.selectedPeriod ? 6 : 3.5),
                    pointHoverRadius: 7,
                    pointBackgroundColor: (props.eclTrends || []).map(i => i.period === props.selectedPeriod ? C.gold : '#ffffff'),
                    pointBorderColor: (props.eclTrends || []).map(i => i.period === props.selectedPeriod ? C.gold : C.maiic),
                    pointBorderWidth: 2,
                    pointHoverBackgroundColor: C.gold,
                    pointHoverBorderColor: '#ffffff',
                }],
            },
            options: {
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: {
                        beginAtZero: true,
                        suggestedMax: peak > 0 ? Math.ceil(peak * 1.25) : 10,
                        border: { display: false },
                        grid: { color: 'rgba(226, 232, 240, 0.8)' },
                        ticks: {
                            color: '#64748b',
                            font: { size: 11 },
                            callback: (v) => v + '%',
                        },
                        title: { display: false },
                    },
                    x: {
                        border: { display: false },
                        grid: { display: false },
                        ticks: { color: '#64748b', font: { size: 11 }, maxRotation: 40 },
                        title: { display: false },
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#0b2b1a',
                        titleColor: '#fbbf24',
                        bodyColor: '#ffffff',
                        padding: 12,
                        cornerRadius: 10,
                        displayColors: false,
                        callbacks: {
                            label: (item) => 'ECL coverage: ' + Number(item.parsed.y).toFixed(2) + '%',
                        },
                    },
                },
            },
        });
    }
}

onMounted(renderCharts);
watch(summary, renderCharts);
</script>
