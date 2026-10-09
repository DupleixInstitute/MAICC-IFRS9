<template>
    <AppLayout title="Loan Book" description="Each loan's exposure, stage, arrears, locked EIR and ECL. Open a row to see how its ECL was built.">
        <template #actions>
            <button @click="openExportModal" class="secondary-btn" title="Balances by stage for a range of periods">Export summary</button>
            <button @click="openDisbursementModal" class="secondary-btn" title="Loans disbursed within a range of periods">Disbursements</button>
            <Link :href="route('loan_applications.loan-book.import.create')" class="primary-btn">Import loan book</Link>
        </template>

        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6" v-if="summary">
                <div class="maiic-kpi !py-3" style="--accent: #15803d"><div class="maiic-kpi-label">Loans</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ Number(summary.total_loans || 0).toLocaleString() }}</div></div>
                <div class="maiic-kpi !py-3" style="--accent: #0e7490" :title="formatMoney(summary.total_carrying)"><div class="maiic-kpi-label">Carrying (MWK)</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ compact(summary.total_carrying) }}</div></div>
                <div class="maiic-kpi !py-3" style="--accent: #15803d" :title="formatMoney(summary.total_ead) + ' = carrying amount plus undrawn commitments times utilisation'"><div class="maiic-kpi-label">EAD (MWK)</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ compact(summary.total_ead) }}</div></div>
                <div class="maiic-kpi !py-3" style="--accent: #dc2626" :title="formatMoney(summary.stage_3_exposure) + ', ' + Number(summary.stage_3_count || 0).toLocaleString() + ' loans in stage 3'"><div class="maiic-kpi-label">Stage 3 EAD (MWK)</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ compact(summary.stage_3_exposure) }}</div></div>
                <div class="maiic-kpi !py-3" style="--accent: #dc2626" :title="formatMoney(summary.total_provision)"><div class="maiic-kpi-label">ECL (MWK)</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ compact(summary.total_provision) }}</div></div>
                <div class="maiic-kpi !py-3" style="--accent: #d97706"><div class="maiic-kpi-label">Coverage (ECL / EAD)</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ Number(summary.ecl_coverage || 0).toFixed(2) }}%</div></div>
            </div>

            <div class="maiic-filterbar !mb-0 flex flex-wrap items-center gap-3 !py-2.5">
                <select v-model="period" @change="fetchData" class="maiic-select w-36" aria-label="Reporting period" title="Reporting period">
                    <option v-for="p in periods" :key="p" :value="p">{{ periodLabel(p) }}</option>
                </select>
                <select v-model="filters.stage" @change="fetchData" class="maiic-select w-32" aria-label="Stage" title="Stage">
                    <option value="">All stages</option>
                    <option value="1">Stage 1</option>
                    <option value="2">Stage 2</option>
                    <option value="3">Stage 3</option>
                </select>
                <select v-model="filters.product_group" @change="fetchData" class="maiic-select w-52" aria-label="Product group" title="Product group">
                    <option value="">All product groups</option>
                    <option v-for="g in productGroups" :key="g" :value="g">{{ g }}</option>
                </select>
                <select v-model="filters.sector" @change="fetchData" class="maiic-select w-56" aria-label="Sector" title="Sector">
                    <option value="">All sectors</option>
                    <option v-for="s in sectors" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <input type="text" v-model="filters.search" @input="fetchDataDebounced" placeholder="Search contract or customer" class="maiic-input w-56" aria-label="Search"/>
                <span class="ml-auto text-xs text-gray-500">EAD = carrying amount + undrawn x utilisation. Coverage = ECL / EAD.</span>
            </div>

            <div class="maiic-panel">
                <div class="maiic-table-wrap">
                    <table class="maiic-table text-[13px] [&_td]:!px-2.5 [&_th]:!px-2.5">
                        <thead>
                            <tr>
                                <th class="w-8"><span class="sr-only">Open</span></th>
                                <th>Contract</th>
                                <th>Product group / sector</th>
                                <th title="IFRS 9 stage, and the Reserve Bank of Malawi class by days past due and term">Stage / RBM</th>
                                <th class="num" title="Days past due">DPD</th>
                                <th class="num" title="Carrying amount, with any undrawn commitment below it">Carrying (MWK)</th>
                                <th class="num">EAD (MWK)</th>
                                <th class="num" title="The locked original effective interest rate (effective annual)">EIR</th>
                                <th class="num">ECL (MWK)</th>
                                <th class="num">Coverage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="loan in loanBooks.data" :key="loan.id">
                                <tr class="cursor-pointer" @click="toggle(loan.id)">
                                    <td class="!pr-0">
                                        <button type="button" class="maiic-action maiic-action-neutral !h-6 !w-6" :title="open[loan.id] ? 'Hide how the ECL was built' : 'Show how the ECL was built'" @click.stop="toggle(loan.id)">
                                            <font-awesome-icon :icon="open[loan.id] ? 'chevron-down' : 'chevron-right'" class="text-[10px]"/>
                                        </button>
                                    </td>
                                    <td class="whitespace-nowrap">
                                        <div class="font-mono text-xs font-semibold text-gray-900">{{ loan.contract_id }}</div>
                                        <div class="max-w-[11rem] truncate text-xs text-gray-500" :title="customerOf(loan)">{{ customerOf(loan) }}</div>
                                    </td>
                                    <td class="max-w-[11rem]">
                                        <div class="truncate text-xs text-gray-800" :title="loan.product_group">{{ loan.product_group || '-' }}</div>
                                        <div class="truncate text-xs text-gray-500" :title="loan.sector">{{ loan.sector }}</div>
                                    </td>
                                    <td class="whitespace-nowrap">
                                        <span :class="['maiic-badge', stageBadge(stageOf(loan))]">Stage {{ stageOf(loan) }}</span>
                                        <div class="mt-0.5 text-[11px] font-semibold" :class="rbmText(loan.lineage.rbm_class)">{{ loan.lineage.rbm_class }}</div>
                                    </td>
                                    <td class="num"><span :class="getOverdueClass(loan.overdue_days)">{{ Number(loan.overdue_days || 0).toLocaleString() }}</span></td>
                                    <td class="num">
                                        <div>{{ formatMoney(loan.lineage.carrying) }}</div>
                                        <div v-if="loan.lineage.undrawn" class="text-[11px] text-gray-500" title="Undrawn commitment">+ {{ formatMoney(loan.lineage.undrawn) }}</div>
                                    </td>
                                    <td class="num font-semibold">{{ formatMoney(loan.lineage.ead) }}</td>
                                    <td class="num">
                                        <span v-if="loan.eir.locked" :title="'Locked ' + (loan.eir.rate_type === 'FLOATING' ? '(floating)' : '(fixed)')">{{ formatRate(loan.eir.rate) }}</span>
                                        <span v-else class="maiic-badge maiic-badge-grey" :title="'No locked EIR yet: ' + loan.eir.status">{{ loan.eir.status }}</span>
                                    </td>
                                    <td class="num font-semibold">{{ loan.ecl_value === null ? '-' : formatMoney(loan.ecl_value) }}</td>
                                    <td class="num">{{ loan.lineage.coverage === null ? '-' : loan.lineage.coverage.toFixed(2) + '%' }}</td>
                                </tr>
                                <tr v-if="open[loan.id]" class="!bg-maiic-50/40">
                                    <td colspan="10" class="!px-4 !py-3">
                                        <div class="w-0 min-w-full"><LoanLineage :loan="loan"/></div>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="!loanBooks.data.length">
                                <td colspan="10" class="maiic-empty">{{ periods.length ? 'No loan matches these filters.' : 'No loan book has been imported yet. Use Import loan book (top right).' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <Pagination v-if="loanBooks.links" :links="loanBooks.links"/>
        </div>
        <HelpManual/>
    </AppLayout>

    <!-- Export summary -->
    <div v-if="showExportModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="showExportModal = false"></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl">
            <div class="border-b border-gray-200 px-6 py-4">
                <h3 class="text-lg font-bold text-gray-900">Export the loan book summary</h3>
                <p class="text-sm text-gray-500">Balances by IFRS 9 stage for each period in the range.</p>
            </div>
            <div class="space-y-4 px-6 py-5">
                <div>
                    <label for="exportPortfolio" class="maiic-flabel">Portfolio</label>
                    <select v-model="exportForm.portfolio_id" id="exportPortfolio" class="maiic-select">
                        <option value="">All portfolios</option>
                        <option v-for="portfolio in portfolios" :key="portfolio.id" :value="portfolio.id">{{ portfolio.name }}</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label for="startPeriod" class="maiic-flabel">From</label><input type="month" v-model="exportForm.start_period" id="startPeriod" class="maiic-input" required></div>
                    <div><label for="endPeriod" class="maiic-flabel">To</label><input type="month" v-model="exportForm.end_period" id="endPeriod" class="maiic-input" required></div>
                </div>
                <p v-if="exportError" class="text-xs text-red-600">{{ exportError }}</p>
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4">
                <button @click="showExportModal = false" class="secondary-btn">Cancel</button>
                <button @click="submitExport" class="primary-btn" :disabled="exporting">{{ exporting ? 'Exporting...' : 'Export' }}</button>
            </div>
        </div>
    </div>

    <!-- Disbursement report -->
    <div v-if="showDisbursementModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="showDisbursementModal = false"></div>
        <div class="relative w-full max-w-md rounded-2xl bg-white shadow-2xl">
            <div class="border-b border-gray-200 px-6 py-4">
                <h3 class="text-lg font-bold text-gray-900">Export the disbursement report</h3>
                <p class="text-sm text-gray-500">The amount disbursed and the number of loans originated in the range.</p>
            </div>
            <div class="space-y-4 px-6 py-5">
                <div>
                    <label for="disbursementPortfolio" class="maiic-flabel">Portfolio</label>
                    <select v-model="disbursementForm.portfolio_id" id="disbursementPortfolio" class="maiic-select">
                        <option value="">All portfolios</option>
                        <option v-for="portfolio in portfolios" :key="portfolio.id" :value="portfolio.id">{{ portfolio.name }}</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label for="dStart" class="maiic-flabel">From</label><input type="month" v-model="disbursementForm.start_period" id="dStart" class="maiic-input" required></div>
                    <div><label for="dEnd" class="maiic-flabel">To</label><input type="month" v-model="disbursementForm.end_period" id="dEnd" class="maiic-input" required></div>
                </div>
                <div>
                    <span class="maiic-flabel">Content</span>
                    <label class="flex items-center gap-2 text-sm text-gray-700"><input type="radio" v-model="disbursementForm.mode" value="summary" class="text-maiic-600"> Summary only</label>
                    <label class="mt-1 flex items-center gap-2 text-sm text-gray-700"><input type="radio" v-model="disbursementForm.mode" value="detailed" class="text-maiic-600"> Summary and each contract</label>
                </div>
                <p v-if="disbursementError" class="text-xs text-red-600">{{ disbursementError }}</p>
            </div>
            <div class="flex justify-end gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4">
                <button @click="showDisbursementModal = false" class="secondary-btn">Cancel</button>
                <button @click="submitDisbursementExport" class="primary-btn" :disabled="disbursementExporting">{{ disbursementExporting ? 'Exporting...' : 'Export' }}</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import HelpManual from '@/Components/HelpManual.vue';
import LoanLineage from '@/Components/Ifrs9/LoanLineage.vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    loanBooks: Object,
    filters: Object,
    portfolios: Array,
    summary: Object,
    periods: { type: Array, default: () => [] },
    productGroups: { type: Array, default: () => [] },
    sectors: { type: Array, default: () => [] },
});

// Rows opened to show how their ECL was built.
const open = reactive({});
const toggle = (id) => { open[id] = !open[id]; };

// The tiles come with the page (the server's summary for the same filters).
const summary = ref(props.summary || null);
const filters = ref({
    stage: '',
    search: '',
    product_group: '',
    sector: '',
    ...props.filters
});
// The month shown: the one asked for, else the latest the server opened on.
const pad = (m) => String(m).padStart(2, '0');
const period = ref(props.filters?.year && props.filters?.month
    ? props.filters.year + '-' + pad(props.filters.month)
    : (props.periods[0] || ''));
const periodLabel = (p) => {
    const [y, m] = String(p).split('-');
    return new Date(Number(y), Number(m) - 1, 1).toLocaleDateString('en-GB', { month: 'short', year: 'numeric' });
};

const showExportModal = ref(false);
const showDisbursementModal = ref(false);
const exporting = ref(false);
const disbursementExporting = ref(false);
const exportError = ref('');
const disbursementError = ref('');

const exportForm = ref({
    portfolio_id: '',
    start_period: '',
    end_period: '',
    mode: 'summary'
});

const disbursementForm = ref({
    portfolio_id: '',
    start_period: '',
    end_period: '',
    mode: 'summary'
});

const openExportModal = () => {
    exportForm.value = {
        portfolio_id: '',
        start_period: '',
        end_period: '',
        mode: 'summary'
    };
    exportError.value = '';
    showExportModal.value = true;
};

const submitExport = async () => {
    exportError.value = '';
    if (!exportForm.value.start_period || !exportForm.value.end_period) {
        exportError.value = 'Choose both the first and the last period.';
        return;
    }
    if (exportForm.value.start_period > exportForm.value.end_period) {
        exportError.value = 'The first period cannot be after the last.';
        return;
    }

    exporting.value = true;

    try {
        // Consolidated onto the Reports module (ReportsController@loanBookExport + LoanBookExportService).
        const url = route('reports.loan-book-export', {
            start_period: exportForm.value.start_period,
            end_period: exportForm.value.end_period,
            portfolio_id: exportForm.value.portfolio_id || null,
            mode: 'summary',
            export: 1
        });

        // Create a temporary link to download the file
        const link = document.createElement('a');
        link.href = url;
        link.target = '_blank';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        showExportModal.value = false;
    } catch (error) {
        console.error('Export failed:', error);
        exportError.value = 'The export could not start. Please try again.';
    } finally {
        exporting.value = false;
    }
};

const openDisbursementModal = () => {
    disbursementForm.value = {
        portfolio_id: '',
        start_period: '',
        end_period: '',
        mode: 'summary'
    };
    disbursementError.value = '';
    showDisbursementModal.value = true;
};

const submitDisbursementExport = async () => {
    disbursementError.value = '';
    if (!disbursementForm.value.start_period || !disbursementForm.value.end_period) {
        disbursementError.value = 'Choose both the first and the last period.';
        return;
    }
    if (disbursementForm.value.start_period > disbursementForm.value.end_period) {
        disbursementError.value = 'The first period cannot be after the last.';
        return;
    }

    disbursementExporting.value = true;

    try {
        // Consolidated onto the Reports module (ReportsController@disbursementReport + DisbursementReportService).
        const url = route('reports.disbursement-report', {
            start_period: disbursementForm.value.start_period,
            end_period: disbursementForm.value.end_period,
            portfolio_id: disbursementForm.value.portfolio_id || null,
            mode: disbursementForm.value.mode,
            export: 1
        });

        // Create a temporary link to download the file
        const link = document.createElement('a');
        link.href = url;
        link.target = '_blank';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        showDisbursementModal.value = false;
    } catch (error) {
        console.error('Disbursement export failed:', error);
        disbursementError.value = 'The export could not start. Please try again.';
    } finally {
        disbursementExporting.value = false;
    }
};

const fetchData = async () => {
    try {
        await fetchSummary();
        const [year, month] = String(period.value || '').split('-');
        router.get(route('loan_applications.loan-book'), {
            search: filters.value.search || undefined,
            year: year || undefined,
            month: month ? Number(month) : undefined,
            stage: filters.value.stage || undefined,
            product_group: filters.value.product_group || undefined,
            sector: filters.value.sector || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true
        });
    } catch (error) {
        console.error('Error fetching data:', error);
    }
};

const fetchDataDebounced = debounce(() => fetchData(), 400);

const fetchSummary = async () => {
    try {
        const [year, month] = String(period.value || '').split('-');
        const response = await fetch(route('loan_applications.loan-book.summary', {
            search: filters.value.search || undefined,
            year: year || undefined,
            month: month ? Number(month) : undefined,
            stage: filters.value.stage || undefined,
            product_group: filters.value.product_group || undefined,
            sector: filters.value.sector || undefined,
        }));
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        summary.value = await response.json();
    } catch (error) {
        console.error('Error fetching summary:', error);
    }
};

// Accounting format: thousands separators, 2dp, negatives in parentheses.
const formatMoney = (value) => {
    const n = Number(value ?? 0);
    if (!isFinite(n)) return '0.00';
    const abs = Math.abs(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return n < 0 ? '(' + abs + ')' : abs;
};

// PD / LGD are stored as fractions (0..1); show as percentages.
const formatRate = (value) => {
    const n = Number(value ?? 0);
    if (!isFinite(n)) return '0.00%';
    return (n * 100).toFixed(2) + '%';
};

// Final IFRS 9 stage: post-qualitative wins, then calculated, then imported.
const stageOf = (loan) => {
    return Number(loan.ifrs9stage_post_qualitative ?? loan.calculated_ifrs9_stage ?? loan.ifrs9_stage ?? 1);
};
const stageBadge = (s) => s === 3 ? 'maiic-badge-red' : s === 2 ? 'maiic-badge-gold' : 'maiic-badge-green';
// RBM classes: performing green, special mention gold, non-performing red.
const rbmText = (c) => c === 'Pass' ? 'text-maiic-700' : c === 'Special mention' ? 'text-amber-700' : 'text-red-600';
const customerOf = (loan) => loan.client?.name || loan.customer_name || loan.external_identity_id || '-';

// Large amounts on the figure strip in millions / billions; the full figure is the tooltip.
const compact = (value) => {
    const v = Number(value || 0), a = Math.abs(v);
    if (a >= 1e9) return (v / 1e9).toFixed(2) + ' bn';
    if (a >= 1e6) return (v / 1e6).toFixed(2) + ' m';
    return formatMoney(v);
};

const getOverdueClass = (days) => {
    if (!days) return 'text-maiic-600';
    if (days <= 30) return 'text-amber-600';
    return 'text-red-600 font-semibold';
};

</script>
