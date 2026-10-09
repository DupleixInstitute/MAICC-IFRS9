<template>
    <app-layout title="ECL Calculation" description="Loan-level expected credit loss (PD x LGD x exposure) for every period that has been calculated">
        <template #actions>
            <Link :href="route('expected-credit-loss.create')" :class="btn.primary">
                <font-awesome-icon icon="calculator"/> Run ECL calculation
            </Link>
            <button type="button" :class="btn.gold" @click="openReconciliationModal">
                <font-awesome-icon icon="balance-scale"/> ECL reconciliation
            </button>
            <button type="button" :class="btn.ghost" @click="openReportModal">
                <font-awesome-icon icon="file-export"/> Export report
            </button>
            <Link :href="route('expected-credit-loss.projections')" :class="btn.ghost">
                <font-awesome-icon icon="chart-line"/> Projection audit
            </Link>
        </template>

        <div class="space-y-5">
            <!-- KPI row: the filtered population in view -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                <div class="maiic-kpi" style="--accent:#15803d">
                    <div class="maiic-kpi-label">Loans in view</div>
                    <div class="maiic-kpi-value text-xl">{{ formatNumber(summary?.total_loans) }}</div>
                </div>
                <div class="maiic-kpi" style="--accent:#0e7490">
                    <div class="maiic-kpi-label">Carrying amount (MWK)</div>
                    <div class="maiic-kpi-value text-xl" :title="formatMoney(summary?.total_exposure)">{{ compact(summary?.total_exposure) }}</div>
                </div>
                <div class="maiic-kpi" style="--accent:#d97706">
                    <div class="maiic-kpi-label">Undiscounted ECL (MWK)</div>
                    <div class="maiic-kpi-value text-xl" :title="formatMoney(summary?.total_loss)">{{ summary?.calculated_loans ? compact(summary.total_loss) : '-' }}</div>
                </div>
                <div class="maiic-kpi" style="--accent:#b45309">
                    <div class="maiic-kpi-label">Discounted ECL (MWK)</div>
                    <div class="maiic-kpi-value text-xl" :title="formatMoney(summary?.total_discounted_loss)">{{ summary?.discounted_loans ? compact(summary.total_discounted_loss) : '-' }}</div>
                </div>
                <div class="maiic-kpi" style="--accent:#dc2626">
                    <div class="maiic-kpi-label">Coverage (ECL / carrying)</div>
                    <div class="maiic-kpi-value text-xl">{{ summary?.calculated_loans && summary?.total_exposure ? formatPercent(coverageRate) : '-' }}</div>
                </div>
            </div>

            <!-- Tabs: loan results by basis, and the saved run by stage -->
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-5 pt-4">
                    <nav class="flex gap-6 overflow-x-auto">
                        <button v-for="t in tabs" :key="t.key" type="button"
                                class="whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-semibold"
                                :class="activeTab === t.key ? 'border-maiic-600 text-maiic-700' : 'border-transparent text-gray-500 hover:text-gray-800'"
                                @click="activeTab = t.key">
                            {{ t.label }}
                            <span class="ml-1 rounded-full px-2 py-0.5 text-xs" :class="activeTab === t.key ? 'bg-maiic-100 text-maiic-700' : 'bg-gray-100 text-gray-600'">{{ formatNumber(t.count) }}</span>
                        </button>
                    </nav>
                </div>

                <!-- One slim filter row and one muted line: what the view shows and what the saved run recorded -->
                <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 px-4 py-3">
                    <select v-model="filters.year" aria-label="Year" title="Year" class="maiic-select w-24 py-1.5" @change="fetchData">
                        <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
                    </select>
                    <select v-model="filters.month" aria-label="Month" title="Month" class="maiic-select w-36 py-1.5" @change="fetchData">
                        <option v-for="(name, index) in months" :key="index" :value="index + 1">{{ name }}</option>
                    </select>
                    <select v-model="filters.portfolio" aria-label="Portfolio" title="Portfolio" class="maiic-select w-44 py-1.5" @change="fetchData">
                        <option value="">All portfolios</option>
                        <option v-for="p in portfolios" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                    <select v-model="filters.stage" aria-label="Stage" title="Stage" class="maiic-select w-32 py-1.5" @change="fetchData">
                        <option value="">All stages</option>
                        <option value="1">Stage 1</option>
                        <option value="2">Stage 2</option>
                        <option value="3">Stage 3</option>
                    </select>
                    <input v-model="filters.search" type="search" aria-label="Search" class="maiic-input w-56 py-1.5"
                           placeholder="Search contract or customer" @keyup.enter="fetchData">
                    <button type="button" class="maiic-action maiic-action-view" title="Search" @click="fetchData"><font-awesome-icon icon="search"/></button>
                    <button type="button" class="text-xs font-bold text-gray-500 hover:text-maiic-700" @click="resetFilters">Clear</button>
                </div>
                <p class="flex flex-wrap items-center gap-x-2 gap-y-1 border-b border-gray-200 px-4 py-2 text-xs text-gray-500">
                    <span>{{ currentTab.description }}</span>
                    <template v-if="runInfo?.period">
                        <span class="text-gray-300">|</span>
                        <template v-if="runScopes.length">
                            <span>{{ periodLabel(runInfo.period) }} run:</span>
                            <template v-for="s in runScopes" :key="s.key">
                                <span class="maiic-badge maiic-badge-green">{{ s.level === 'sector' ? 'Sector' : 'Portfolio' }}: {{ s.scope || 'not named' }}</span>
                                <span class="maiic-badge" :class="s.discounted ? 'maiic-badge-solid-green' : 'maiic-badge-grey'">{{ s.discounted ? 'Discounted at EIR' : 'Undiscounted' }}</span>
                                <span>{{ s.run_at }}</span>
                            </template>
                        </template>
                        <span v-else class="maiic-badge maiic-badge-gold">No ECL run saved for {{ periodLabel(runInfo.period) }}</span>
                    </template>
                    <template v-if="summary?.discount_unresolved_loans > 0">
                        <span class="text-gray-300">|</span>
                        <span class="font-semibold text-amber-700"><font-awesome-icon icon="exclamation-triangle"/> {{ formatNumber(summary.discount_unresolved_loans) }} loan(s) could not be discounted: check their approved EIR and remaining term.</span>
                    </template>
                </p>

                <!-- Loan results -->
                <div v-if="activeTab !== 'run'">
                    <div class="maiic-table-wrap">
                        <table class="maiic-table">
                            <thead>
                                <tr>
                                    <th>Contract</th>
                                    <th>Customer</th>
                                    <th>Period</th>
                                    <th>Stage</th>
                                    <th class="num">EAD (MWK)</th>
                                    <th class="num">PD before FLI</th>
                                    <th class="num">PD after FLI</th>
                                    <th class="num">LGD</th>
                                    <th class="num">Undiscounted ECL (MWK)</th>
                                    <template v-if="activeTab === 'undiscounted'">
                                        <th class="num">Coverage</th>
                                    </template>
                                    <template v-else>
                                        <th class="num">EIR</th>
                                        <th class="num">Horizon</th>
                                        <th class="num">Discounted ECL (MWK)</th>
                                        <th class="num">Discounting effect (MWK)</th>
                                        <th>Discount status</th>
                                    </template>
                                    <th>Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="loan in loanBooks.data" :key="loan.id">
                                    <td class="whitespace-nowrap font-semibold text-gray-900">{{ loan.contract_id }}</td>
                                    <td class="max-w-[16rem] truncate" :title="loan.client?.name || loan.customer_name">{{ loan.client?.name || loan.customer_name || '-' }}</td>
                                    <td class="whitespace-nowrap">{{ loan.reporting_period }}</td>
                                    <td><span class="maiic-badge" :class="stageBadge(stageOf(loan))">Stage {{ stageOf(loan) }}</span></td>
                                    <td class="num">{{ formatMoney(eadValue(loan)) }}</td>
                                    <td class="num">{{ formatRate(loan.pd_prefli) }}</td>
                                    <td class="num">{{ formatRate(loan.pd_post_fli) }}</td>
                                    <td class="num">{{ formatRate(loan.lgd_value) }}</td>
                                    <td class="num font-semibold">{{ formatMoney(loan.ecl_value, true) }}</td>
                                    <template v-if="activeTab === 'undiscounted'">
                                        <td class="num">{{ formatPercent(loanCoverage(loan)) }}</td>
                                    </template>
                                    <template v-else>
                                        <td class="num" :title="eirBasis(loan)">{{ eirRate(loan) }}</td>
                                        <td class="num">{{ formatHorizon(loan.ecl_discount_horizon_years) }}</td>
                                        <td class="num font-semibold">{{ formatMoney(loan.ecl_value_discounted, true) }}</td>
                                        <td class="num">{{ formatMoney(loan.ecl_discounting_effect, true) }}</td>
                                        <td><span class="maiic-badge" :class="discountStatusClass(loan.ecl_discount_status)">{{ discountStatusLabel(loan.ecl_discount_status) }}</span></td>
                                    </template>
                                    <td class="whitespace-nowrap text-xs text-gray-500">{{ loan.updated_at ? $filters.time(loan.updated_at) : '' }}</td>
                                </tr>
                                <tr v-if="!loanBooks.data || loanBooks.data.length === 0">
                                    <td :colspan="activeTab === 'discounted' ? 15 : 11" class="maiic-empty">
                                        No ECL results for this period and these filters. Pick another month, clear the filters, or run the ECL calculation.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="loanBooks.links" class="border-t border-gray-100 px-4 pb-4">
                        <Pagination :links="loanBooks.links"/>
                    </div>
                </div>

                <!-- Saved run by stage -->
                <div v-else class="maiic-table-wrap">
                    <table class="maiic-table">
                        <thead>
                            <tr>
                                <th>Calculated for</th>
                                <th>Stage</th>
                                <th class="num">Loans</th>
                                <th class="num">EAD (MWK)</th>
                                <th class="num">Average PD</th>
                                <th class="num">Average LGD</th>
                                <th class="num">Undiscounted ECL (MWK)</th>
                                <th class="num">Discounted ECL (MWK)</th>
                                <th class="num">Coverage</th>
                                <th>Basis</th>
                                <th>Run at</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(r, i) in runInfo.rows" :key="i">
                                <td>{{ r.level === 'sector' ? 'Sector' : 'Portfolio' }}: {{ r.scope || '-' }}</td>
                                <td><span class="maiic-badge" :class="stageBadge(Number(r.stage))">Stage {{ r.stage }}</span></td>
                                <td class="num">{{ formatNumber(r.loans) }}</td>
                                <td class="num">{{ formatMoney(r.ead, true) }}</td>
                                <td class="num">{{ formatRate(r.pd_used) }}</td>
                                <td class="num">{{ formatRate(r.lgd_used) }}</td>
                                <td class="num font-semibold">{{ formatMoney(r.ecl, true) }}</td>
                                <td class="num">{{ formatMoney(r.ecl_discounted, true) }}</td>
                                <td class="num">{{ r.ead ? formatPercent((r.ecl || 0) / r.ead * 100) : '-' }}</td>
                                <td><span class="maiic-badge" :class="r.discount_status === 'NOT_REQUESTED' ? 'maiic-badge-grey' : 'maiic-badge-green'">{{ r.discount_status === 'NOT_REQUESTED' ? 'Undiscounted' : (r.discount_status === 'PARTIAL' ? 'Discounted, some unresolved' : 'Discounted') }}</span></td>
                                <td class="whitespace-nowrap text-xs text-gray-500">{{ r.run_at || '-' }}</td>
                            </tr>
                            <tr v-if="runInfo.rows.length" class="total">
                                <td colspan="2">Total</td>
                                <td class="num">{{ formatNumber(runTotals.loans) }}</td>
                                <td class="num">{{ formatMoney(runTotals.ead) }}</td>
                                <td class="num"></td>
                                <td class="num"></td>
                                <td class="num">{{ formatMoney(runTotals.ecl) }}</td>
                                <td class="num">{{ runTotals.hasDiscounted ? formatMoney(runTotals.ecl_discounted) : '-' }}</td>
                                <td class="num">{{ runTotals.ead ? formatPercent(runTotals.ecl / runTotals.ead * 100) : '-' }}</td>
                                <td colspan="2"></td>
                            </tr>
                            <tr v-if="!runInfo.rows.length">
                                <td colspan="11" class="maiic-empty">No ECL run has been saved for {{ runInfo.period ? periodLabel(runInfo.period) : 'this period' }}. Use Run ECL calculation to create one.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Export modal -->
        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
            <div class="maiic-panel w-full max-w-md">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                    <h2 class="text-base font-bold text-gray-900">Export ECL report</h2>
                    <button type="button" class="maiic-action maiic-action-neutral" title="Close" @click="showModal = false">&times;</button>
                </div>
                <div class="space-y-4 p-5">
                    <div>
                        <label for="portfolio" class="maiic-flabel">Portfolio</label>
                        <select id="portfolio" v-model="selectedPortfolio" class="maiic-select">
                            <option value="">All portfolios</option>
                            <option v-for="portfolio in portfolios" :key="portfolio.id" :value="portfolio.id">{{ portfolio.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="period" class="maiic-flabel">Reporting period</label>
                        <input id="period" v-model="selectedPeriod" type="month" class="maiic-input">
                        <p v-if="exportError" class="mt-1 text-xs font-semibold text-red-600">{{ exportError }}</p>
                    </div>
                    <div>
                        <label for="export-mode" class="maiic-flabel">Mode</label>
                        <select id="export-mode" v-model="selectedMode" class="maiic-select">
                            <option value="summary">Summary by stage</option>
                            <option value="totalLoanBook">Total loan book (loan level)</option>
                        </select>
                    </div>
                    <div v-if="selectedMode === 'totalLoanBook'">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="maiic-flabel mb-0">Columns</span>
                            <div class="flex gap-3 text-xs font-bold">
                                <button type="button" class="text-maiic-700 hover:underline" @click="selectedColumns = allColumns.slice()">Select all</button>
                                <button type="button" class="text-red-600 hover:underline" @click="selectedColumns = []">Clear all</button>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2 text-sm text-gray-700">
                            <label v-for="col in columnOptions" :key="col.value" class="flex items-center gap-2">
                                <input v-model="selectedColumns" type="checkbox" :value="col.value" class="rounded border-gray-300 text-maiic-600 focus:ring-maiic-500"> {{ col.label }}
                            </label>
                        </div>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-200 px-5 py-4">
                    <button type="button" :class="btn.ghost" @click="showModal = false">Cancel</button>
                    <button type="button" :class="btn.primary" :disabled="loading" @click="submitUpdate">
                        <font-awesome-icon icon="file-export"/> {{ loading ? 'Exporting...' : 'Export' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Reconciliation modal -->
        <div v-if="showReconciliationModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
            <div class="maiic-panel w-full max-w-md">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                    <h2 class="text-base font-bold text-gray-900">ECL reconciliation</h2>
                    <button type="button" class="maiic-action maiic-action-neutral" title="Close" @click="showReconciliationModal = false">&times;</button>
                </div>
                <div class="space-y-4 p-5">
                    <div>
                        <label for="recon-portfolio" class="maiic-flabel">Portfolio</label>
                        <select id="recon-portfolio" v-model="reconciliationForm.portfolio_id" class="maiic-select">
                            <option value="">Select portfolio</option>
                            <option v-for="portfolio in portfolios" :key="portfolio.id" :value="portfolio.id">{{ portfolio.name }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="recon-start" class="maiic-flabel">Start period</label>
                            <input id="recon-start" v-model="reconciliationForm.start_period" type="month" class="maiic-input">
                        </div>
                        <div>
                            <label for="recon-end" class="maiic-flabel">End period</label>
                            <input id="recon-end" v-model="reconciliationForm.end_period" type="month" class="maiic-input">
                        </div>
                    </div>
                    <div>
                        <label for="recon-movement" class="maiic-flabel">Movement of</label>
                        <select id="recon-movement" v-model="reconciliationForm.movement_type" class="maiic-select">
                            <option value="ecl_value">ECL</option>
                            <option value="principal_balance">Carrying amount</option>
                        </select>
                    </div>
                    <div>
                        <label for="recon-report-type" class="maiic-flabel">Report type</label>
                        <select id="recon-report-type" v-model="reconciliationForm.report_type" class="maiic-select">
                            <option value="summary">Summary</option>
                            <option value="detailed">Detailed</option>
                        </select>
                    </div>
                    <div v-if="reconciliationForm.report_type === 'detailed'">
                        <label for="recon-detail-type" class="maiic-flabel">Detailed section</label>
                        <select id="recon-detail-type" v-model="reconciliationForm.detail_type" class="maiic-select">
                            <option value="">Select a section</option>
                            <option value="new_loans">New loans</option>
                            <option value="derecognized_loans">Derecognised loans</option>
                            <option value="stage_transitions">Stage transitions</option>
                        </select>
                    </div>
                    <p v-if="reconciliationError" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">{{ reconciliationError }}</p>
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-200 px-5 py-4">
                    <button type="button" :class="btn.ghost" @click="showReconciliationModal = false">Cancel</button>
                    <button type="button" :class="btn.gold" :disabled="reconciliationLoading" @click="submitReconciliation">
                        <font-awesome-icon icon="balance-scale"/> {{ reconciliationLoading ? 'Preparing...' : 'Generate report' }}
                    </button>
                </div>
            </div>
        </div>
        <HelpManual/>
    </app-layout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import HelpManual from '@/Components/HelpManual.vue';

const props = defineProps({
    loanBooks: Object,
    filters: Object,
    portfolios: Array,
    sectors: Array,
    summary: Object,
    latestPeriod: String,
    runInfo: { type: Object, default: () => ({ period: null, rows: [] }) },
});

const btn = {
    primary: 'inline-flex items-center gap-2 rounded-lg bg-maiic-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-maiic-700 disabled:opacity-50',
    gold: 'inline-flex items-center gap-2 rounded-lg bg-maiicgold-400 px-4 py-2 text-sm font-bold text-gray-900 shadow-sm transition hover:bg-maiicgold-500 disabled:opacity-50',
    ghost: 'inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50',
};

// Open on the basis the population was actually measured on.
const activeTab = ref(props.summary?.discounted_loans > 0 ? 'discounted' : 'undiscounted');
const latestPeriodParts = (props.latestPeriod || '').split('-');
const defaultYear = Number(latestPeriodParts[0]) || new Date().getFullYear();
const defaultMonth = Number(latestPeriodParts[1]) || new Date().getMonth() + 1;

const coverageRate = computed(() => {
    const exposure = Number(props.summary?.total_exposure || 0);
    return exposure > 0 ? (Number(props.summary?.total_loss || 0) / exposure) * 100 : 0;
});

const tabs = computed(() => [
    { key: 'undiscounted', label: 'Undiscounted ECL', count: props.summary?.calculated_loans || 0,
      description: 'PD x LGD x exposure for each loan, no present-value adjustment.' },
    { key: 'discounted', label: 'Discounted ECL', count: props.summary?.discounted_loans || 0,
      description: 'The same loss discounted at the locked EIR; blank where the run was undiscounted.' },
    { key: 'run', label: 'Run summary by stage', count: props.runInfo?.rows?.length || 0,
      description: 'Stage totals the saved run recorded, with the average PD and LGD it used.' },
]);
const currentTab = computed(() => tabs.value.find(t => t.key === activeTab.value));

// One line per scope the period was run for (portfolio or sector), from the saved run.
const runScopes = computed(() => {
    const seen = {};
    (props.runInfo?.rows || []).forEach(r => {
        const key = r.level + '|' + (r.scope || '');
        if (!seen[key]) seen[key] = { key, level: r.level, scope: r.scope, discounted: r.discount_status !== 'NOT_REQUESTED', run_at: r.run_at };
    });
    return Object.values(seen);
});
const runTotals = computed(() => {
    const t = { loans: 0, ead: 0, ecl: 0, ecl_discounted: 0, hasDiscounted: false };
    (props.runInfo?.rows || []).forEach(r => {
        t.loans += Number(r.loans || 0); t.ead += Number(r.ead || 0); t.ecl += Number(r.ecl || 0);
        if (r.ecl_discounted !== null) { t.hasDiscounted = true; t.ecl_discounted += Number(r.ecl_discounted); }
    });
    return t;
});

const filters = ref({
    year: defaultYear,
    month: defaultMonth,
    stage: '',
    portfolio: '',
    search: '',
    ...props.filters,
});

const years = [2022, 2023, 2024, 2025, 2026, 2027, 2028, 2029, 2030];
const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const columnOptions = [
    { value: 'external_identity_id', label: 'External ID' },
    { value: 'contract_id', label: 'Contract ID' },
    { value: 'principal_balance', label: 'Principal balance' },
    { value: 'pd_value', label: 'PD' },
    { value: 'lgd_value', label: 'LGD' },
    { value: 'ecl_value', label: 'ECL' },
    { value: 'calculated_ifrs9_stage', label: 'Stage' },
    { value: 'reporting_period', label: 'Reporting period' },
    { value: 'create_date', label: 'Create date' },
    { value: 'due_date', label: 'Due date' },
    { value: 'contract_status', label: 'Contract status' },
    { value: 'overdue_days', label: 'Overdue days' },
];
const allColumns = columnOptions.map(c => c.value);

const selectedPortfolio = ref('');
const selectedPeriod = ref('');
const selectedMode = ref('summary');
const selectedColumns = ref([]);
const loading = ref(false);
const showModal = ref(false);
const exportError = ref('');
const showReconciliationModal = ref(false);
const reconciliationLoading = ref(false);
const reconciliationError = ref('');
const reconciliationForm = ref({ portfolio_id: '', start_period: '', end_period: '' });

const openReportModal = () => {
    selectedPeriod.value = props.runInfo?.period || '';
    selectedPortfolio.value = '';
    selectedMode.value = 'summary';
    selectedColumns.value = [];
    exportError.value = '';
    showModal.value = true;
};

const openReconciliationModal = () => {
    reconciliationForm.value = {
        portfolio_id: '', start_period: '', end_period: '',
        movement_type: 'ecl_value', report_type: 'summary', detail_type: '',
    };
    reconciliationError.value = '';
    showReconciliationModal.value = true;
};

const submitUpdate = () => {
    if (!selectedPeriod.value) {
        exportError.value = 'Choose the reporting period to export.';
        return;
    }
    loading.value = true;
    try {
        const url = route('expected-credit-loss.reports', {
            reporting_period: selectedPeriod.value,
            portfolios: selectedPortfolio.value,
            mode: selectedMode.value,
            columns: selectedMode.value === 'totalLoanBook' ? selectedColumns.value : [],
        });
        window.open(url, '_blank');
        showModal.value = false;
    } finally {
        loading.value = false;
    }
};

const submitReconciliation = () => {
    const f = reconciliationForm.value;
    if (!f.portfolio_id || !f.start_period || !f.end_period) {
        reconciliationError.value = 'Choose a portfolio, a start period and an end period.';
        return;
    }
    if (f.start_period >= f.end_period) {
        reconciliationError.value = 'The end period must be after the start period.';
        return;
    }
    if (f.report_type === 'detailed' && !f.detail_type) {
        reconciliationError.value = 'Choose which detailed section to produce.';
        return;
    }
    reconciliationLoading.value = true;
    try {
        const url = route('reports.ecl-reconciliation', {
            portfolio_id: f.portfolio_id,
            start_period: f.start_period,
            end_period: f.end_period,
            movement_type: f.movement_type,
            report_type: f.report_type,
            detail_type: f.report_type === 'detailed' ? f.detail_type : '',
            generate: true,
        });
        window.open(url, '_blank');
        showReconciliationModal.value = false;
    } finally {
        reconciliationLoading.value = false;
    }
};

const fetchData = () => {
    router.get(route('expected-credit-loss.index'), {
        search: filters.value.search,
        year: filters.value.year,
        month: filters.value.month,
        stage: filters.value.stage,
        portfolio: filters.value.portfolio,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const resetFilters = () => {
    filters.value = { year: defaultYear, month: defaultMonth, stage: '', portfolio: '', search: '' };
    router.get(route('expected-credit-loss.index'), {}, { preserveScroll: true, replace: true });
};

const formatMoney = (value, blankWhenMissing = false) => {
    if ((value === null || value === undefined || value === '') && blankWhenMissing) return '-';
    return new Intl.NumberFormat('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value || 0));
};
// Large amounts on the KPI tiles in millions / billions; the full figure is the tooltip.
const compact = (value) => {
    const v = Number(value || 0), a = Math.abs(v);
    if (a >= 1e9) return (v / 1e9).toFixed(2) + ' bn';
    if (a >= 1e6) return (v / 1e6).toFixed(2) + ' m';
    return formatMoney(v);
};
const formatNumber = (value) => new Intl.NumberFormat('en-GB').format(Number(value || 0));
const formatPercent = (value) => Number(value || 0).toFixed(2) + '%';
const formatRate = (value) => (value === null || value === undefined || value === '') ? '-' : (Number(value) * 100).toFixed(2) + '%';
const formatHorizon = (value) => (value === null || value === undefined || value === '') ? '-' : Math.round(Number(value) * 12) + ' months';
function periodLabel(p) {
    const [y, m] = String(p || '').split('-');
    return m ? months[Number(m) - 1] + ' ' + y : p;
}

const eadValue = (loan) => {
    const utilisation = loan.facility_utilisation_rate === null || loan.facility_utilisation_rate === undefined
        ? 1 : Number(loan.facility_utilisation_rate);
    return Number(loan.carrying_amount || 0) + (Number(loan.commitments || 0) * utilisation);
};
const loanCoverage = (loan) => {
    const ead = eadValue(loan);
    return ead > 0 ? (Number(loan.ecl_value || 0) / ead) * 100 : 0;
};
// Same final-stage precedence and badge convention as Loan Books.
const stageOf = (loan) => Number(loan.ifrs9stage_post_qualitative ?? loan.calculated_ifrs9_stage ?? loan.ifrs9_stage ?? 1);
const stageBadge = (s) => s === 3 ? 'maiic-badge-red' : s === 2 ? 'maiic-badge-gold' : 'maiic-badge-green';

const discountStatusLabel = (status) => ({
    CALCULATED_HORIZON_PROXY: 'Calculated',
    FLOATING_RATE_PROXY: 'Floating-rate proxy',
    EIR_UNAVAILABLE: 'EIR unavailable',
    HORIZON_UNAVAILABLE: 'Horizon unavailable',
    NOT_REQUESTED: 'Not requested',
    NOT_CALCULATED: 'Not calculated',
}[status] || status || 'Not calculated');
const discountStatusClass = (status) => {
    if (status === 'CALCULATED_HORIZON_PROXY') return 'maiic-badge-green';
    if (status === 'FLOATING_RATE_PROXY') return 'maiic-badge-gold';
    if (status === 'EIR_UNAVAILABLE' || status === 'HORIZON_UNAVAILABLE') return 'maiic-badge-red';
    return 'maiic-badge-grey';
};

// The solved effective rate, shown only where one actually exists.
const eirRate = (loan) => {
    const eir = loan.contract_eir;
    if (!eir || eir.eir_effective_annual === null || eir.eir_effective_annual === undefined) return '-';
    return (Number(eir.eir_effective_annual) * 100).toFixed(2) + '%';
};
// Where the rate came from and how far it has been approved.
const eirBasis = (loan) => {
    const eir = loan.contract_eir;
    if (!eir) return 'No EIR';
    if (eir.eir_effective_annual === null || eir.eir_effective_annual === undefined) return 'Not calculated';
    if (!eir.locked_at) return 'Calculated, not approved';
    return eir.rate_type === 'FLOATING' ? 'Floating, original rate used as proxy' : 'Fixed, original rate';
};
</script>
