<script setup>
import { reactive, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import BackToReports from './Partials/BackToReports.vue';
import DownloadButton from './Partials/DownloadButton.vue';
import KpiRow from './Partials/KpiRow.vue';
import ReportIcon from './Partials/ReportIcon.vue';

const props = defineProps({
    report: { type: Object, default: null },
    error: { type: String, default: null },
    portfolios: { type: Array, default: () => [] },
    periods: { type: Array, default: () => [] },
    selectedPortfolio: { default: '' },
    selectedStartPeriod: { default: '' },
    selectedEndPeriod: { default: '' },
});

const form = reactive({
    portfolio_id: props.selectedPortfolio || (props.portfolios.length === 1 ? props.portfolios[0].value : ''),
    start_period: props.selectedStartPeriod || '',
    end_period: props.selectedEndPeriod || '',
});

const canRun = computed(() => form.portfolio_id && form.start_period && form.end_period && form.start_period < form.end_period);

const reload = (extra = {}) => router.get(route('reports.loan-book-reconciliation'), { ...form, ...extra }, { preserveScroll: true });
const onPortfolioChange = () => { form.start_period = ''; form.end_period = ''; reload(); };

const downloadUrl = (kind) => props.report
    ? route('reports.loan-book-reconciliation', { portfolio_id: props.selectedPortfolio, start_period: props.selectedStartPeriod, end_period: props.selectedEndPeriod, download: kind })
    : '';

const money = (v) => {
    const n = Number(v || 0);
    const t = Math.abs(n).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return n < 0 ? '(' + t + ')' : t;
};
const line = (key) => (props.report?.lines || []).find(l => l.key === key) || { amount: 0, loans: 0 };
const figures = computed(() => props.report ? [
    { label: 'Opening loan book', value: money(line('opening').amount), sub: line('opening').loans + ' loans' },
    { label: 'New loans', value: money(line('new').amount), sub: line('new').loans + ' loans' },
    { label: 'Closing loan book', value: money(line('closing').amount), sub: line('closing').loans + ' loans' },
    { label: 'Difference', value: money(line('variance').amount), tone: props.report.ties ? 'maiic' : 'rose' },
] : []);
</script>

<template>
    <AppLayout title="Loan book reconciliation">
        <template #header>
            <BackToReports tab="annual"/>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Loan book reconciliation</h2>
            <p class="mt-0.5 text-sm text-gray-500">Opening book plus new loans, less loans that left, plus the change on continuing loans, against the closing book.</p>
        </template>

        <template #actions>
            <select v-model="form.portfolio_id" @change="onPortfolioChange" class="maiic-select w-40" aria-label="Portfolio">
                <option value="">Portfolio</option>
                <option v-for="p in portfolios" :key="p.value" :value="p.value">{{ p.label }}</option>
            </select>
            <select v-model="form.start_period" class="maiic-select w-36" aria-label="Start period" :disabled="!periods.length">
                <option value="">From</option>
                <option v-for="p in periods" :key="'s' + p.value" :value="p.value">From {{ p.label }}</option>
            </select>
            <select v-model="form.end_period" class="maiic-select w-36" aria-label="End period" :disabled="!periods.length">
                <option value="">To</option>
                <option v-for="p in periods" :key="'e' + p.value" :value="p.value">To {{ p.label }}</option>
            </select>
            <button type="button" @click="reload({ generate: 1 })" :disabled="!canRun"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-maiic-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-maiic-700 disabled:opacity-50">
                <ReportIcon name="play" class="h-4 w-4"/> Run
            </button>
            <DownloadButton format="CSV" :href="downloadUrl('csv')" :disabled="!report"/>
            <DownloadButton format="Excel" :href="downloadUrl('xlsx')" :disabled="!report"/>
            <DownloadButton format="PDF" label="Download PDF" :href="downloadUrl('pdf')" :disabled="!report"/>
        </template>

        <div class="w-full space-y-4">
            <div v-if="error" class="flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-700">
                <ReportIcon name="alert" class="h-5 w-5"/> {{ error }}
            </div>

            <template v-if="report">
                <KpiRow :items="figures"/>

                <div class="maiic-panel">
                    <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                        <h3 class="font-semibold text-gray-900">{{ report.portfolio }}, {{ report.start_period }} to {{ report.end_period }}</h3>
                        <span class="maiic-badge" :class="report.ties ? 'maiic-badge-green' : 'maiic-badge-red'">{{ report.ties ? 'Ties' : 'Does not tie' }}</span>
                    </div>
                    <div class="maiic-table-wrap">
                        <table class="maiic-table">
                            <thead><tr><th></th><th class="num">Loans</th><th class="num">Carrying amount</th></tr></thead>
                            <tbody>
                                <tr v-for="l in report.lines" :key="l.key" :class="{ total: ['expected', 'closing'].includes(l.key), 'font-semibold': l.key === 'opening' }">
                                    <td>{{ l.label }}</td>
                                    <td class="num">{{ Number(l.loans).toLocaleString() }}</td>
                                    <td class="num" :class="l.key === 'variance' && !report.ties ? 'text-red-700 font-bold' : ''">{{ money(l.amount) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="border-t border-gray-100 px-4 py-2 text-xs text-gray-500">
                        Write-offs: {{ report.write_offs.loans ? report.write_offs.loans + ' loans in the closing book carry a write-off status (' + money(report.write_offs.amount) + ').' : 'no loan carries a write-off status, so loans that leave the book are shown as repaid or derecognised.' }}
                    </p>
                </div>
            </template>
            <div v-else-if="!error" class="maiic-panel maiic-empty">Choose a portfolio and two periods at the top right, then Run.</div>
        </div>
    </AppLayout>
</template>
