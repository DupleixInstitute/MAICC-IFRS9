<template>
    <AppLayout title="Loan Book" description="Every loan in a month-end loan book, with its stage, exposure, PD, LGD and ECL">
        <template #actions>
            <button @click="openExportModal" class="secondary-btn" title="Balances by stage for a range of periods">Export summary</button>
            <button @click="openDisbursementModal" class="secondary-btn" title="Loans disbursed within a range of periods">Disbursements</button>
            <Link :href="route('loan_applications.loan-book.import.create')" class="primary-btn">Import loan book</Link>
        </template>

        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5" v-if="summary">
                <div class="maiic-kpi" style="--accent: #15803d"><div class="maiic-kpi-label">Loans</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ Number(summary.total_loans || 0).toLocaleString() }}</div></div>
                <div class="maiic-kpi" style="--accent: #15803d"><div class="maiic-kpi-label">Total EAD (MWK)</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ formatMoney(summary.total_balance) }}</div></div>
                <div class="maiic-kpi" style="--accent: #dc2626" :title="Number(summary.stage_3_count || 0).toLocaleString() + ' loans in stage 3'"><div class="maiic-kpi-label">Stage 3 exposure (MWK)</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ formatMoney(summary.stage_3_exposure) }}</div></div>
                <div class="maiic-kpi" style="--accent: #d97706"><div class="maiic-kpi-label">ECL coverage</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ Number(summary.ecl_coverage || 0).toFixed(2) }}%</div></div>
                <div class="maiic-kpi" style="--accent: #dc2626"><div class="maiic-kpi-label">Total ECL (MWK)</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ formatMoney(summary.total_provision) }}</div></div>
            </div>

            <div class="maiic-filterbar !mb-0 flex flex-wrap items-center gap-3 !py-2.5">
                <select v-model="period" @change="fetchData" class="maiic-select w-40" aria-label="Reporting period">
                    <option v-for="p in periods" :key="p" :value="p">{{ periodLabel(p) }}</option>
                </select>
                <select v-model="filters.stage" @change="fetchData" class="maiic-select w-32" aria-label="Stage">
                    <option value="">All stages</option>
                    <option value="1">Stage 1</option>
                    <option value="2">Stage 2</option>
                    <option value="3">Stage 3</option>
                </select>
                <input type="text" v-model="filters.search" @input="fetchDataDebounced" placeholder="Search contract or customer" class="maiic-input w-56" aria-label="Search"/>
            </div>

            <div class="maiic-panel">
                <div class="maiic-table-wrap">
                    <table class="maiic-table">
                        <thead>
                            <tr>
                                <th>Contract ID</th>
                                <th>Customer</th>
                                <th>Stage</th>
                                <th class="num">EAD (MWK)</th>
                                <th class="num">PD</th>
                                <th class="num">LGD</th>
                                <th class="num">ECL (MWK)</th>
                                <th class="num">Coverage</th>
                                <th class="num">Days overdue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="loan in loanBooks.data" :key="loan.id">
                                <td class="whitespace-nowrap font-mono text-xs">{{ loan.contract_id }}</td>
                                <td class="font-semibold text-gray-900">{{ loan.client?.name || loan.customer_name || loan.external_identity_id }}</td>
                                <td class="whitespace-nowrap">
                                    <span :class="['maiic-badge', stageOf(loan) === 3 ? 'maiic-badge-red' : stageOf(loan) === 2 ? 'maiic-badge-gold' : 'maiic-badge-green']">Stage {{ stageOf(loan) }}</span>
                                </td>
                                <td class="num">{{ formatMoney(loan.ead ?? loan.carrying_amount) }}</td>
                                <td class="num">{{ formatRate(loan.pd_post_fli ?? loan.pd_value) }}</td>
                                <td class="num">{{ formatRate(loan.lgd_value) }}</td>
                                <td class="num">{{ formatMoney(loan.ecl_value) }}</td>
                                <td class="num">{{ coverageOf(loan) }}</td>
                                <td class="num"><span :class="getOverdueClass(loan.overdue_days)">{{ loan.overdue_days }}</span></td>
                            </tr>
                            <tr v-if="!loanBooks.data.length">
                                <td colspan="9" class="maiic-empty">{{ periods.length ? 'No loan matches these filters.' : 'No loan book has been imported yet. Use Import loan book (top right).' }}</td>
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
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import HelpManual from '@/Components/HelpManual.vue';
import debounce from 'lodash/debounce';

const props = defineProps({
    loanBooks: Object,
    filters: Object,
    portfolios: Array,
    summary: Object,
    periods: { type: Array, default: () => [] },
});

// The tiles come with the page (the server's summary for the same filters).
const summary = ref(props.summary || null);
const filters = ref({
    stage: '',
    search: '',
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
            stage: filters.value.stage || undefined
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
            stage: filters.value.stage || undefined
        }));
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        summary.value = await response.json();
    } catch (error) {
        console.error('Error fetching summary:', error);
    }
};

const formatCurrency = (value) => {
    return formatMoney(value);
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

// Per-loan ECL coverage: ECL over carrying amount.
const coverageOf = (loan) => {
    const ead = Number(loan.carrying_amount ?? 0);
    const ecl = Number(loan.ecl_value ?? 0);
    if (!ead) return '0.00%';
    return ((ecl / ead) * 100).toFixed(2) + '%';
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};

const getOverdueClass = (days) => {
    if (days === 0) return 'text-maiic-600';
    if (days <= 30) return 'text-amber-600';
    return 'text-red-600';
};

const getStatusClass = (status) => {
    const classes = {
        'Current': 'bg-maiic-100 text-maiic-800',
        'Watch': 'bg-amber-100 text-amber-800',
        'Substandard': 'bg-amber-100 text-amber-800',
        'Doubtful': 'bg-red-100 text-red-800',
        'Loss': 'bg-red-200 text-red-900'
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};

</script>
