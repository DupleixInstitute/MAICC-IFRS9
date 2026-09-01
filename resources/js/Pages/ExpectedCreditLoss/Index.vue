<template>
    <app-layout>
               <template #header>
                   <div class="flex justify-between items-center">
                       <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                           Expected Credit Loss Management

                       </h2>
                       <div class="flex space-x-4">
                            <Link :href="route('expected-credit-loss.projections')"
                                class="inline-flex items-center border border-maiic-600 text-maiic-700 hover:bg-maiic-50 px-4 py-2 rounded-lg shadow-sm transition duration-300 mt-2">
                                Projection Audit
                            </Link>
                            <Link :href="route('expected-credit-loss.create')"
                                class="inline-flex items-center bg-maiic-600 hover:bg-maiic-700 text-white px-4 py-2 rounded-lg shadow-md transition duration-300 mt-2">
                                Calculate ECL
                                <Icon name="calculator" class="w-4 h-4 mr-2" />
                            </Link>
                            <button @click="openReconciliationModal"
                                class="inline-flex items-center bg-black hover:bg-gray-800 text-white px-4 py-2 rounded-lg shadow-md transition duration-300 mt-2">
                                <Icon name="arrows-right-left" class="w-4 h-4 mr-2" />
                                ECL Reconciliation
                            </button>
                            <button @click="openReportModal"
                                class="inline-flex items-center bg-maiic-600 hover:bg-maiic-700 text-white px-4 py-2 rounded-lg shadow-md transition duration-300 mt-2">
                                <Icon name="file-export" class="w-4 h-4 mr-2" />
                                Export Report
                            </button>
                       </div>
                   </div>
               </template>

               <div class="py-12">
                   <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                       <!-- Measurement basis tabs and summary cards -->
                       <div class="mb-6" v-if="summary">
                           <div class="inline-flex rounded-lg border border-gray-200 bg-white p-1 shadow-sm" role="tablist" aria-label="ECL measurement basis">
                               <button type="button" role="tab" :aria-selected="activeBasis === 'discounted'"
                                       @click="activeBasis = 'discounted'"
                                       :class="basisTabClass('discounted')">
                                   Discounted ECL (EIR present value)
                               </button>
                               <button type="button" role="tab" :aria-selected="activeBasis === 'undiscounted'"
                                       @click="activeBasis = 'undiscounted'"
                                       :class="basisTabClass('undiscounted')">
                                   Undiscounted ECL
                               </button>
                           </div>

                           <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                               <div class="rounded-lg bg-white p-5 shadow-sm border border-gray-100">
                                   <p class="text-sm text-gray-500">{{ activeBasis === 'discounted' ? 'Records discounted' : 'Records calculated' }}</p>
                                   <p class="mt-1 text-2xl font-semibold text-gray-900">{{ formatNumber(activeBasis === 'discounted' ? summary.discounted_loans : summary.calculated_loans) }}</p>
                                   <p class="mt-1 text-xs text-gray-500">of {{ formatNumber(summary.total_loans) }} records in the filtered population</p>
                               </div>
                               <div class="rounded-lg bg-white p-5 shadow-sm border border-gray-100">
                                   <p class="text-sm text-gray-500">{{ activeBasis === 'discounted' ? 'Discounted ECL' : 'Undiscounted ECL' }}</p>
                                   <p class="mt-1 text-2xl font-semibold text-gray-900">{{ formatCurrency(activeBasis === 'discounted' ? summary.total_discounted_loss : summary.total_loss) }}</p>
                                   <p class="mt-1 text-xs text-gray-500">For the selected reporting population</p>
                               </div>
                               <div class="rounded-lg bg-white p-5 shadow-sm border border-gray-100">
                                   <p class="text-sm text-gray-500">{{ activeBasis === 'discounted' ? 'Discounting effect' : 'ECL coverage of exposure' }}</p>
                                   <p class="mt-1 text-2xl font-semibold text-gray-900">
                                       {{ activeBasis === 'discounted' ? formatCurrency(summary.total_discounting_effect) : formatPercent(coverageRate) }}
                                   </p>
                                   <p class="mt-1 text-xs text-gray-500">{{ activeBasis === 'discounted' ? 'Reduction from applying EIR present value' : 'Undiscounted ECL divided by carrying exposure' }}</p>
                               </div>
                           </div>

                           <div v-if="activeBasis === 'discounted' && summary.discount_unresolved_loans > 0"
                                class="mt-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                               {{ formatNumber(summary.discount_unresolved_loans) }} record(s) could not be discounted. Review their discount status, approved EIR and remaining horizon.
                           </div>
                       </div>

                       <!-- Filters -->
                       <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                           <button type="button" @click="showFilters = !showFilters"
                                   class="flex w-full items-center justify-between px-6 py-4 text-left hover:bg-gray-50"
                                   :aria-expanded="showFilters">
                               <span>
                                   <span class="block text-sm font-semibold text-gray-900">Filters</span>
                                   <span class="block text-xs text-gray-500">Year, month, stage and contract or customer search</span>
                               </span>
                               <span class="text-sm font-medium text-maiic-700">
                                   {{ showFilters ? 'Hide filters' : 'Show filters' }}
                                   <span class="ml-1 inline-block transition-transform" :class="{ 'rotate-180': showFilters }">&#9660;</span>
                               </span>
                           </button>
                           <div v-show="showFilters" class="p-6 bg-white border-t border-gray-200">
                               <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                   <div>
                                       <label class="block text-sm font-medium text-gray-700">Year</label>
                                       <select v-model="filters.year" @change="fetchData"
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-maiic-500 focus:ring-maiic-500">
                                           <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
                                       </select>
                                   </div>
                                   <div>
                                       <label class="block text-sm font-medium text-gray-700">Month</label>
                                       <select v-model="filters.month" @change="fetchData"
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-maiic-500 focus:ring-maiic-500">
                                           <option v-for="(name, index) in months" :key="index" :value="index + 1">{{ name }}</option>
                                       </select>
                                   </div>
                                   <div>
                                       <label class="block text-sm font-medium text-gray-700">Stages</label>
                                       <select v-model="filters.stage" @change="fetchData"
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-maiic-500 focus:ring-maiic-500">
                                           <option value="">All Stages</option>
                                           <option value="1">Stage 1</option>
                                           <option value="2">Stage 2</option>
                                           <option value="3">Stage 3</option>
                                       </select>
                                   </div>
                                   <div>
                                       <label class="block text-sm font-medium text-gray-700">Search</label>
                                       <input type="text" v-model="filters.search" @input="fetchData"
                                              placeholder="Search by Contract ID or Customer..."
                                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-maiic-500 focus:ring-maiic-500">
                                   </div>
                               </div>
                               <div class="col-span-4 mt-4 flex justify-end">
                                    <button @click="applyFilters"
                                            class="inline-flex mr-4 items-center bg-maiic-600 hover:bg-maiic-700 text-white px-4 py-2 rounded-lg shadow-md transition duration-300">
                                        Apply Filters
                                    </button>
                                    <button @click="resetFilters"
                                            class="inline-flex items-center bg-gray-300 hover:bg-gray-400 text-black px-4 py-2 rounded-lg shadow-md transition duration-300">
                                        Reset
                                    </button>
                               </div>
                           </div>
                       </div>

                       <!-- Loan Book Table -->
                       <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                           <div class="p-6 bg-white border-b border-gray-200">
                               <div class="overflow-x-auto">
                                   <table class="maiic-table">
                                       <thead>
                                           <tr>
                                               <th>Contract ID</th>
                                               <th>Reporting Period</th>
                                               <th class="text-center">IFRS Stage</th>
                                               <th class="text-right">EAD (MWK)</th>
                                               <template v-if="activeBasis === 'undiscounted'">
                                                   <th class="text-center">PD</th>
                                                   <th class="text-center">LGD</th>
                                                   <th class="text-right">Undiscounted ECL (MWK)</th>
                                                   <th class="text-center">ECL Coverage</th>
                                               </template>
                                               <template v-else>
                                                   <th class="text-center">PD</th>
                                                   <th class="text-center">LGD</th>
                                                   <th class="text-right">Undiscounted ECL (MWK)</th>
                                                   <th class="text-center">Original EIR</th>
                                                   <th class="text-center">Discount Horizon (Months)</th>
                                                   <th class="text-right">Discounted ECL (MWK)</th>
                                                   <th class="text-right">Discounting Effect (MWK)</th>
                                                   <th class="text-center">Discount Status</th>
                                               </template>
                                               <th>Updated</th>
                                           </tr>
                                       </thead>
                                       <tbody>
                                           <tr v-for="loan in loanBooks.data" :key="loan.id">
                                               <td class="px-3 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ loan.contract_id }}</td>
                                               <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">{{ loan.reporting_period }}</td>
                                               <td class="px-3 py-4 whitespace-nowrap text-center text-sm">
                                                   <span :class="['maiic-badge', stageOf(loan) === 3 ? 'maiic-badge-red' : stageOf(loan) === 2 ? 'maiic-badge-gold' : 'maiic-badge-green']">
                                                       Stage {{ stageOf(loan) }}
                                                   </span>
                                               </td>
                                               <td class="px-3 py-4 whitespace-nowrap text-right text-sm tabular-nums text-gray-500">{{ formatCurrency(eadValue(loan)) }}</td>
                                               <template v-if="activeBasis === 'undiscounted'">
                                                   <td class="px-3 py-4 whitespace-nowrap text-center text-sm">{{ formatRate(loan.pd_value) }}</td>
                                                   <td class="px-3 py-4 whitespace-nowrap text-center text-sm">{{ formatRate(loan.lgd_value) }}</td>
                                                   <td class="px-3 py-4 whitespace-nowrap text-right text-sm tabular-nums text-gray-500">{{ formatCurrency(loan.ecl_value) }}</td>
                                                   <td class="px-3 py-4 whitespace-nowrap text-center text-sm text-gray-500">{{ formatPercent(loanCoverage(loan)) }}</td>
                                               </template>
                                               <template v-else>
                                                   <td class="px-3 py-4 whitespace-nowrap text-center text-sm">{{ formatRate(eclPd(loan)) }}</td>
                                                   <td class="px-3 py-4 whitespace-nowrap text-center text-sm">{{ formatRate(loan.lgd_value) }}</td>
                                                   <td class="px-3 py-4 whitespace-nowrap text-right text-sm tabular-nums text-gray-500">{{ formatCurrency(loan.ecl_value) }}</td>
                                                   <td class="px-3 py-4 whitespace-nowrap text-center text-sm text-gray-500" :title="eirBasis(loan)">{{ eirRate(loan) }}</td>
                                                   <td class="px-3 py-4 whitespace-nowrap text-center text-sm text-gray-500">{{ formatHorizon(loan.ecl_discount_horizon_years) }}</td>
                                                   <td class="px-3 py-4 whitespace-nowrap text-right text-sm tabular-nums text-gray-500">{{ formatCurrency(loan.ecl_value_discounted, true) }}</td>
                                                   <td class="px-3 py-4 whitespace-nowrap text-right text-sm tabular-nums text-gray-500">{{ formatCurrency(loan.ecl_discounting_effect, true) }}</td>
                                                   <td class="px-3 py-4 whitespace-nowrap text-center text-sm">
                                                       <span :class="discountStatusClass(loan.ecl_discount_status)">{{ discountStatusLabel(loan.ecl_discount_status) }}</span>
                                                   </td>
                                               </template>
                                               <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">{{ loan.updated_at ? $filters.time(loan.updated_at) : '' }}</td>
                                           </tr>
                                           <tr v-if="!loanBooks.data || loanBooks.data.length === 0">
                                               <td :colspan="activeBasis === 'discounted' ? 13 : 9" class="px-4 py-10 text-center text-sm text-gray-500">
                                                   No ECL records match the selected reporting period and filters.
                                               </td>
                                           </tr>
                                       </tbody>
                                   </table>
                               </div>
                               <div class="mt-4" v-if="loanBooks.links">
                                   <Pagination :links="loanBooks.links" />
                               </div>
                           </div>
                       </div>
                   </div>
               </div>
                <div v-if="showModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
                    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
                        <h2 class="text-lg font-bold mb-4">Export Loan Book Report</h2>
                          <label for="portfolio" class="block mb-2 text-sm font-medium text-gray-700">Select Portfolio</label>
                            <select v-model="selectedPortfolio" id="portfolio" class="border-gray-300 rounded-md shadow-sm w-full mb-4">
                                <option value="">Select Portfolio</option>
                                <option v-for="portfolio in portfolios" :key="portfolio.id" :value="portfolio.id">
                                    {{ portfolio.name }}
                                </option>
                            </select>

                         <!-- Mode Selection -->
                        <label for="period" class="block mb-2 text-sm font-medium text-gray-700">Reporting Period</label>
                        <input type="month" v-model="selectedPeriod" id="period" class="border-gray-300 rounded-md shadow-sm w-full mb-4">

                        <!-- Mode Selection -->
                        <label class="block mb-2 text-sm font-medium text-gray-700">Mode</label>
                        <select v-model="selectedMode" class="border-gray-300 rounded-md shadow-sm w-full mb-4">
                            <option value="summary">Summary</option>
                            <option value="totalLoanBook">Total Loan Book</option>
                        </select>

                        <!-- Conditional Columns Selection -->
                        <div v-if="selectedMode === 'totalLoanBook'" class="mb-4">
                            <label class="block mb-2 text-sm font-medium text-gray-700">Select Columns</label>
                            <div class="flex space-x-2 mb-2">
                                 <button
                                    type="button"
                                    class="text-xs text-maiic-600 hover:underline"
                                    @click="selectedColumns = allColumns.slice()"
                                >
                                    Select All
                                </button>
                                <button
                                    type="button"
                                    class="text-xs text-red-600 hover:underline"
                                    @click="selectedColumns = []"
                                >
                                    Clear All
                                </button>
                                </div>
                            <div class="grid grid-cols-2 gap-2">
                                <label><input type="checkbox" value="external_identity_id" v-model="selectedColumns"> External ID</label>
                                <label><input type="checkbox" value="contract_id" v-model="selectedColumns"> Contract ID</label>
                                <label><input type="checkbox" value="principal_balance" v-model="selectedColumns"> Principal Balance</label>
                                <label><input type="checkbox" value="pd_value" v-model="selectedColumns"> PD</label>
                                <label><input type="checkbox" value="lgd_value" v-model="selectedColumns"> LGD</label>
                                <label><input type="checkbox" value="ecl_value" v-model="selectedColumns"> ECL</label>
                                <label><input type="checkbox" value="calculated_ifrs9_stage" v-model="selectedColumns"> Stage</label>
                                <label><input type="checkbox" value="reporting_period" v-model="selectedColumns"> Reporting Period</label>
                                <label><input type="checkbox" value="create_date" v-model="selectedColumns"> Create Date</label>
                                <label><input type="checkbox" value="due_date" v-model="selectedColumns"> Due Date</label>
                                <label><input type="checkbox" value="contract_status" v-model="selectedColumns"> Contract Status</label>
                                <label><input type="checkbox" value="contract_status" v-model="selectedColumns">Overdue Days</label>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-2">
                            <button @click="showModal = false" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
                            <button
                                @click="submitUpdate"
                                class="px-4 py-2 bg-maiic-600 text-white rounded hover:bg-maiic-700"
                                :disabled="loading"
                            >
                                <span v-if="loading">Exporting...</span>
                                <span v-else>Get</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div v-if="showReconciliationModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
                    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
                        <h2 class="text-lg font-bold mb-4">Export ECL Reconciliation</h2>

                        <label for="recon-portfolio" class="block mb-2 text-sm font-medium text-gray-700">Select Portfolio</label>
                        <select v-model="reconciliationForm.portfolio_id" id="recon-portfolio" class="border-gray-300 rounded-md shadow-sm w-full mb-4">
                            <option value="">Select Portfolio</option>
                            <option v-for="portfolio in portfolios" :key="portfolio.id" :value="portfolio.id">
                                {{ portfolio.name }}
                            </option>
                        </select>

                        <label for="recon-start" class="block mb-2 text-sm font-medium text-gray-700">Start Period</label>
                        <input type="month" v-model="reconciliationForm.start_period" id="recon-start" class="border-gray-300 rounded-md shadow-sm w-full mb-4">

                        <label for="recon-end" class="block mb-2 text-sm font-medium text-gray-700">End Period</label>
                        <input type="month" v-model="reconciliationForm.end_period" id="recon-end" class="border-gray-300 rounded-md shadow-sm w-full mb-4">

                        <label for="recon-movement" class="block mb-2 text-sm font-medium text-gray-700">Movement Type</label>
                        <select v-model="reconciliationForm.movement_type" id="recon-movement" class="border-gray-300 rounded-md shadow-sm w-full mb-4">
                            <option value="ecl_value">ECL Value</option>
                            <option value="principal_balance">Carrying Amount</option>
                        </select>

                        <label for="recon-report-type" class="block mb-2 text-sm font-medium text-gray-700">Report Type</label>
                        <select v-model="reconciliationForm.report_type" id="recon-report-type" class="border-gray-300 rounded-md shadow-sm w-full mb-4">
                            <option value="summary">Summary</option>
                            <option value="detailed">Detailed</option>
                        </select>

                        <div v-if="reconciliationForm.report_type === 'detailed'">
                            <label for="recon-detail-type" class="block mb-2 text-sm font-medium text-gray-700">Detailed Section</label>
                            <select v-model="reconciliationForm.detail_type" id="recon-detail-type" class="border-gray-300 rounded-md shadow-sm w-full mb-4">
                                <option value="">Select Detailed Section</option>
                                <option value="new_loans">New Loans</option>
                                <option value="derecognized_loans">Derecognized Loans</option>
                                <option value="stage_transitions">Stage Transitions</option>
                            </select>
                        </div>

                        <div class="flex justify-end space-x-2">
                            <button @click="showReconciliationModal = false" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
                            <button
                                @click="submitReconciliation"
                                class="px-4 py-2 bg-amber-600 text-white rounded hover:bg-amber-700"
                                :disabled="reconciliationLoading"
                            >
                                <span v-if="reconciliationLoading">Preparing...</span>
                                <span v-else>Generate Report</span>
                            </button>
                        </div>
                    </div>
                </div>
<HelpManual />
    </app-layout>
</template>

<script setup>
import { computed, ref, defineProps } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import HelpManual from '../../Components/HelpManual.vue';
import { Inertia } from '@inertiajs/inertia';

        const props = defineProps({
            loanBooks: Object,
            filters: Object,
            portfolios: Array,
            summary: Object,
            latestPeriod: String,
        });

        const activeBasis = ref('discounted');
        const showFilters = ref(false);
        const latestPeriodParts = (props.latestPeriod || '').split('-');
        const defaultYear = Number(latestPeriodParts[0]) || new Date().getFullYear();
        const defaultMonth = Number(latestPeriodParts[1]) || new Date().getMonth() + 1;
        const coverageRate = computed(() => {
            const exposure = Number(props.summary?.total_exposure || 0);
            return exposure > 0 ? (Number(props.summary?.total_loss || 0) / exposure) * 100 : 0;
        });

        const filters = ref({
            year: defaultYear,
            month: defaultMonth,
            overdue: '',
            search: '',
            ...props.filters
        });

        const years = [2022, 2023, 2024, 2025, 2026, 2027, 2028, 2029, 2030];
        const months = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];
        const allColumns = [
            'contract_id',
            'principal_balance',
            'pd_value',
            'lgd_value',
            'ecl_value',
            'calculated_ifrs9_stage',
            'reporting_period',
            'external_identity_id' ,
            'create_date',
            'due_date',
            'contract_status',
            'overdue_days',
            ];


        const selectedPortfolio = ref('');
        const selectedPeriod = ref('');
        const selectedMode = ref('summary');
        const selectedColumns = ref([]);
        const loading = ref(false);
        const showModal = ref(false);
        const showReconciliationModal = ref(false);
        const reconciliationLoading = ref(false);
        const reconciliationForm = ref({
            portfolio_id: '',
            start_period: '',
            end_period: '',
        });


        const openReportModal = () => {
            selectedPeriod.value = '';
            selectedPortfolio.value = '';
            selectedMode.value = 'summary';
            selectedColumns.value = [];
            showModal.value = true;
        };

        const openReconciliationModal = () => {
            reconciliationForm.value = {
                portfolio_id: '',
                start_period: '',
                end_period: '',
                movement_type: 'ecl_value',
                report_type: 'summary',
                detail_type: '',
            };
            showReconciliationModal.value = true;
        };

        const submitUpdate = async () => {
            if (!selectedPeriod.value) {
                alert('Please select a period');
                return;
            }

            loading.value = true;

            try {
                const url = route('expected-credit-loss.reports', {
                    reporting_period: selectedPeriod.value,
                    portfolios: selectedPortfolio.value,
                    mode: selectedMode.value,
                    columns: selectedMode.value === 'totalLoanBook' ? selectedColumns.value : []
                });

                window.open(url, '_blank');
                showModal.value = false;
            } catch (error) {
                console.error('Export failed:', error);
            } finally {
                loading.value = false;
            }
        };

        const submitReconciliation = async () => {
            if (!reconciliationForm.value.portfolio_id || !reconciliationForm.value.start_period || !reconciliationForm.value.end_period) {
                alert('Please select a portfolio, start period, and end period');
                return;
            }

            if (reconciliationForm.value.start_period >= reconciliationForm.value.end_period) {
                alert('End period must be after start period');
                return;
            }

            if (reconciliationForm.value.report_type === 'detailed' && !reconciliationForm.value.detail_type) {
                alert('Please select a detailed section');
                return;
            }

            reconciliationLoading.value = true;

            try {
                const url = route('reports.ecl-reconciliation', {
                    portfolio_id: reconciliationForm.value.portfolio_id,
                    start_period: reconciliationForm.value.start_period,
                    end_period: reconciliationForm.value.end_period,
                    movement_type: reconciliationForm.value.movement_type,
                    report_type: reconciliationForm.value.report_type,
                    detail_type: reconciliationForm.value.report_type === 'detailed'
                        ? reconciliationForm.value.detail_type
                        : '',
                    generate: true,
                });

                window.open(url, '_blank');
                showReconciliationModal.value = false;
            } catch (error) {
                console.error('Reconciliation export failed:', error);
            } finally {
                reconciliationLoading.value = false;
            }
        };

        const fetchData = async () => {
            try {
                router.get(route('expected-credit-loss.index'), {
                    search: filters.value.search,
                    year: filters.value.year,
                    month: filters.value.month,
                    stage: filters.value.stage,
                    overdue: filters.value.overdue
                }, {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true
                });
            } catch (error) {
                console.error('Error fetching data:', error);
            }
        };

        const applyFilters = () => {
            fetchData();
        };

        const resetFilters = () => {
            filters.value = {
                year: defaultYear,
                month: defaultMonth,
                overdue: '',
                search: '',
                ...props.filters
            };
            fetchData();
        };

        const formatCurrency = (value, blankWhenMissing = false) => {
            if ((value === null || value === undefined || value === '') && blankWhenMissing) return '—';
            if (!value) return ' 0.00';
            return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
        };

        const formatNumber = (value) => new Intl.NumberFormat('en-US').format(Number(value || 0));
        const formatPercent = (value) => Number(value || 0).toFixed(2) + '%';
        const formatRate = (value) => value === null || value === undefined || value === ''
            ? '—'
            : (Number(value) * 100).toFixed(2) + '%';
        const formatHorizon = (value) => value === null || value === undefined || value === ''
            ? '—'
            : Math.round(Number(value) * 12) + ' months';

        const eadValue = (loan) => {
            const utilisation = loan.facility_utilisation_rate === null || loan.facility_utilisation_rate === undefined
                ? 1
                : Number(loan.facility_utilisation_rate);
            return Number(loan.carrying_amount || 0) + (Number(loan.commitments || 0) * utilisation);
        };
        const loanCoverage = (loan) => {
            const ead = eadValue(loan);
            return ead > 0 ? (Number(loan.ecl_value || 0) / ead) * 100 : 0;
        };
        const eclPd = (loan) => loan.pd_post_fli ?? loan.pd_prefli ?? loan.pd_value;
        // Same final-stage precedence and badge convention as Loan Books.
        const stageOf = (loan) => Number(
            loan.ifrs9stage_post_qualitative
            ?? loan.calculated_ifrs9_stage
            ?? loan.ifrs9_stage
            ?? 1
        );

        const basisTabClass = (basis) => [
            'rounded-md px-4 py-2 text-sm font-medium transition',
            activeBasis.value === basis
                ? 'bg-maiic-600 text-white shadow-sm'
                : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900',
        ];

        const discountStatusLabel = (status) => ({
            CALCULATED_HORIZON_PROXY: 'Calculated',
            FLOATING_RATE_PROXY: 'Floating-rate proxy',
            EIR_UNAVAILABLE: 'EIR unavailable',
            HORIZON_UNAVAILABLE: 'Horizon unavailable',
            NOT_REQUESTED: 'Not requested',
            NOT_CALCULATED: 'Not calculated',
        }[status] || status || 'Not calculated');

        const discountStatusClass = (status) => {
            const base = 'inline-flex rounded-full px-2 py-0.5 text-xs font-medium ';
            if (status === 'CALCULATED_HORIZON_PROXY') return base + 'bg-emerald-100 text-emerald-800';
            if (status === 'FLOATING_RATE_PROXY') return base + 'bg-amber-100 text-amber-800';
            if (status === 'EIR_UNAVAILABLE' || status === 'HORIZON_UNAVAILABLE') return base + 'bg-red-100 text-red-800';
            return base + 'bg-gray-100 text-gray-600';
        };

        // The solved effective rate, shown only where one actually exists.
        // A blank is meaningful: it says this facility's ECL was measured with
        // no solved effective rate behind it.
        const eirRate = (loan) => {
            const eir = loan.contract_eir;
            if (!eir || eir.eir_effective_annual === null || eir.eir_effective_annual === undefined) return '—';
            return (Number(eir.eir_effective_annual) * 100).toFixed(2) + '%';
        };

        // The rate alone would imply the ECL was discounted at it. It is not:
        // ecl_value is PD x LGD x carrying amount, undiscounted, pending the
        // ECL time-conventions decision. This column says where the rate came
        // from and how far it has been approved, so the two are never confused.
        const eirBasis = (loan) => {
            const eir = loan.contract_eir;
            if (!eir) return 'No EIR';
            if (eir.eir_effective_annual === null || eir.eir_effective_annual === undefined) return 'Not calculated';
            if (!eir.locked_at) return 'Calculated — not approved';
            // IFRS 9 wants a floating facility discounted at its current
            // effective rate; no reset history is recorded, so the original
            // stands in and must be labelled as a proxy rather than presented
            // as the current rate.
            return eir.rate_type === 'FLOATING' ? 'Floating — original as proxy' : 'Fixed — original';
        };

        const eirBasisClass = (loan) => {
            const basis = eirBasis(loan);
            const base = 'inline-flex rounded-full px-2 py-0.5 text-xs font-medium ';
            if (basis === 'Fixed — original') return base + 'bg-emerald-100 text-emerald-800';
            if (basis === 'Floating — original as proxy') return base + 'bg-amber-100 text-amber-800';
            if (basis === 'Calculated — not approved') return base + 'bg-sky-100 text-sky-800';
            return base + 'bg-gray-100 text-gray-600';
        };

        const formatDate = (date) => {
            return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        };
</script>
