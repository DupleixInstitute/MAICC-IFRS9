<template>
    <app-layout>
               <template #header>
                   <div class="flex justify-between items-center">
                       <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                           Expected Credit Loss Management
                           
                       </h2>
                       <div class="flex space-x-4">  
                            <Link :href="route('expected-credit-loss.create')" 
                                class="inline-flex items-center bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg shadow-md transition duration-300 mt-2">
                                Calculate ECL
                                <Icon name="calculator" class="w-4 h-4 mr-2" />
                            </Link>
                            <button @click="openReportModal" 
                                class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow-md transition duration-300 mt-2">
                                <Icon name="file-export" class="w-4 h-4 mr-2" />
                                Export Report
                            </button>
                       </div>
                   </div>
               </template>
       
               <div class="py-12">
                   <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                       <!-- Summary Cards -->
                       <div class="mb-6" v-if="summary">
                           <div class="flex items-center justify-between mb-4">
                               <h3 class="text-lg font-medium text-gray-900">ECL Summary</h3>
                               <button @click="toggleSummary" 
                                       class="inline-flex items-center px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded-md transition-colors">
                                   <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1 transform transition-transform" 
                                        :class="{ 'rotate-180': summaryExpanded }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                       <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                   </svg>
                                   {{ summaryExpanded ? 'Hide' : 'Show' }} Summary
                               </button>
                           </div>
                           
                           <transition name="slide-down">
                               <div v-show="summaryExpanded">
                                   <!-- Main Summary Row -->
                                   <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                       <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                                           <div class="text-sm font-semibold">ECL Calculations</div>
                                           <div class="text-2xl text-gray-600">{{ summary.total_calculations || 0 }}</div>
                                       </div>
                                       <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                                           <div class="text-sm font-semibold">Total Exposure</div>
                                           <div class="flex items-center justify-between">
                                               <div class="text-2xl text-gray-600">{{ formatCurrency(summary.total_exposure) }}</div>
                                               <div v-if="summary.previous_period" class="flex items-center" :class="getChangeClass(summary.exposure_change)">
                                                   <span class="text-lg font-semibold">
                                                       {{ summary.exposure_change > 0 ? '↑' : summary.exposure_change < 0 ? '↓' : '→' }}
                                                   </span>
                                               </div>
                                           </div>
                                           <div class="text-xs mt-1" :class="getChangeClass(summary.exposure_change)">
                                               <span v-if="summary.previous_period">
                                                   vs {{ formatPeriod(summary.previous_period) }}: 
                                                   {{ formatChange(summary.exposure_change, summary.exposure_change_percent) }}
                                               </span>
                                               <span v-else class="text-gray-400">No previous period</span>
                                           </div>
                                       </div>
                                       <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                                           <div class="text-sm font-semibold">Total Loss</div>
                                           <div class="flex items-center justify-between">
                                               <div class="text-2xl text-gray-600">{{ formatCurrency(summary.total_loss) }}</div>
                                               <div v-if="summary.previous_period" class="flex items-center" :class="getChangeClass(summary.loss_change)">
                                                   <span class="text-lg font-semibold">
                                                       {{ summary.loss_change > 0 ? '↑' : summary.loss_change < 0 ? '↓' : '→' }}
                                                   </span>
                                               </div>
                                           </div>
                                           <div class="text-xs mt-1" :class="getChangeClass(summary.loss_change)">
                                               <span v-if="summary.previous_period">
                                                   vs {{ formatPeriod(summary.previous_period) }}: 
                                                   {{ formatChange(summary.loss_change, summary.loss_change_percent) }}
                                               </span>
                                               <span v-else class="text-gray-400">No previous period</span>
                                           </div>
                                       </div>
                                       <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                                           <div class="text-sm font-semibold">Period Comparison</div>
                                           <div class="text-sm text-gray-600 mt-1">
                                               <div>Current: {{ formatPeriod(summary.current_period) }}</div>
                                               <div>Previous: {{ summary.previous_period ? formatPeriod(summary.previous_period) : 'N/A' }}</div>
                                           </div>
                                       </div>
                                   </div>
                               </div>
                           </transition>
                       </div>
       
                       <!-- Filters -->
                       <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                           <div class="p-6 bg-white border-b border-gray-200">
                               <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                                   <div>
                                       <label class="block text-sm font-medium text-gray-700">Year</label>
                                       <select v-model="filters.year"
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                           <option value="">All Years</option>
                                           <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
                                       </select>
                                   </div>
                                   <div>
                                       <label class="block text-sm font-medium text-gray-700">Month</label>
                                       <select v-model="filters.month"
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                           <option value="">All Months</option>
                                           <option v-for="(name, index) in months" :key="index" :value="index + 1">{{ name }}</option>
                                       </select>
                                   </div>
                                   <div>
                                       <label class="block text-sm font-medium text-gray-700">Stage</label>
                                       <select v-model="filters.stage"
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                           <option value="">All Stages</option>
                                           <option value="1">Stage 1</option>
                                           <option value="2">Stage 2</option>
                                           <option value="3">Stage 3</option>
                                       </select>
                                   </div>
                                   <div>
                                       <label class="block text-sm font-medium text-gray-700">Portfolio</label>
                                       <select v-model="filters.portfolio"
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                           <option value="">All Portfolios</option>
                                           <option v-for="portfolio in portfolios" :key="portfolio.id" :value="portfolio.id">{{ portfolio.name }}</option>
                                       </select>
                                   </div>
                                   <div>
                                       <label class="block text-sm font-medium text-gray-700">Search</label>
                                       <input type="text" v-model="filters.search"
                                              placeholder="Search by Contract ID or Customer..."
                                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                   </div>
                               </div>
                               <div class="mt-4 flex justify-end space-x-3">
                                   <button @click="applyFilters"
                                           class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                       Apply Filters
                                   </button>
                                   <button @click="resetFilters"
                                           class="inline-flex items-center px-4 py-2 bg-gray-900 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                       Reset Filters
                                   </button>
                               </div>
                           </div>
                       </div>
       
                       <!-- Loan Book Table -->
                       <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                           <div class="p-6 bg-white border-b border-gray-200">
                               <div class="overflow-x-auto">
                                   <table class="min-w-full divide-y divide-gray-200">
                                       <thead class="bg-gray-50">
                                           <tr>
                                               <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reporting Period</th>
                                               <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contract ID</th>
                                               <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer Name</th>
                                               <th scope="col" class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">IFRS Stage</th>
                                               <!-- <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Portfolio</th> -->
                                               <th scope="col" class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance (MKW)</th>
                                               <th scope="col" class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Probability Of Default</th>
                                               <th scope="col" class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Loss Given Default</th>
                                               <th scope="col" class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Expected Credit Loss (MKW)</th>
                                               <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Updated</th>
                                           </tr>
                                       </thead>
                                       <tbody class="bg-white divide-y divide-gray-200">
                                           <tr v-for="loan in loanBooks.data" :key="loan.id">
                                               <td class="px-3 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ formatDate(loan.reporting_period) }}</td>
                                               <td class="px-3 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ loan.contract_id }}</td>
                                               <td class="px-3 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ loan.customer_name }}</td>
                                               <td class="px-3 py-4 whitespace-nowrap text-sm">
                                                        <span :class="getStageClass(loan.ifrs9stage_post_qualitative)" class="px-2 py-1 text-xs font-semibold rounded">
                                                            Stage {{ loan.ifrs9stage_post_qualitative || '-' }}
                                                        </span>
                                               </td>
                                               <td class="px-3 py-4 whitespace-nowrap text-right text-sm text-gray-500">{{ formatCurrency(loan.carrying_amount) }}</td>
                                               <td class="px-3 py-4 whitespace-nowrap text-center text-sm">{{ loan.pd_prefli }}</td>
                                               <td class="px-3 py-4 whitespace-nowrap text-center text-sm" >{{ loan.lgd_value }}</td>
                                               <td class="px-3 py-4 whitespace-nowrap text-right text-sm text-gray-500"> {{ formatCurrency(loan.ecl_value) }}</td>
                                               <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">{{ loan.updated_at ? $filters.time(loan.updated_at) : '' }}</td>
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
                <div v-if="showModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50" @click.self="showModal = false">
                   <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md max-h-[90vh] overflow-y-auto">
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
                                    class="text-xs text-blue-600 hover:underline"
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
                                <label><input type="checkbox" value="customer_name" v-model="selectedColumns"> Customer Name</label>
                                <label><input type="checkbox" value="contract_id" v-model="selectedColumns"> Contract ID</label>
                                <label><input type="checkbox" value="carrying_amount" v-model="selectedColumns"> Balance</label>
                                <label><input type="checkbox" value="pd_value" v-model="selectedColumns"> PD</label>
                                <label><input type="checkbox" value="lgd_value" v-model="selectedColumns"> LGD</label>
                                <label><input type="checkbox" value="ecl_value" v-model="selectedColumns"> ECL</label>
                                <label><input type="checkbox" value="ifrs9stage_pre_qualitative" v-model="selectedColumns"> Stage (Pre-Q)</label>
                                <label><input type="checkbox" value="ifrs9stage_post_qualitative" v-model="selectedColumns"> Stage (Post-Q)</label>
                                <label><input type="checkbox" value="reporting_period" v-model="selectedColumns"> Reporting Period</label>
                                <label><input type="checkbox" value="create_date" v-model="selectedColumns"> Create Date</label>
                                <label><input type="checkbox" value="due_date" v-model="selectedColumns"> Due Date</label>
                                <label><input type="checkbox" value="remaining_tenor" v-model="selectedColumns"> Remaining Tenor</label>
                                <label><input type="checkbox" value="industry_code" v-model="selectedColumns">Industry Code</label>
                                <label><input type="checkbox" value="collection_lgd" v-model="selectedColumns">Collection LGD</label>
                                <label><input type="checkbox" value="allocated_gross_value" v-model="selectedColumns">Allocated Gross Value</label>
                                <label><input type="checkbox" value="allocated_discounted_value" v-model="selectedColumns">Discounted Value</label>
                                <label><input type="checkbox" value="customer_lgd" v-model="selectedColumns">Customer LGD</label>
                                <label><input type="checkbox" value="fli_adj" v-model="selectedColumns">FLI Adjustment</label>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-2">
                            <button @click="showModal = false" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
                            <button 
                                @click="submitUpdate" 
                                class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700"
                                :disabled="loading"
                            >
                                <span v-if="loading">Exporting...</span>
                                <span v-else>Get</span>
                            </button>
                        </div>  
                    </div>
                </div>
<HelpManual />
    </app-layout>
</template>

<script setup>
import { ref, defineProps, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Shared/Pagination.vue';
import HelpManual from '../../Components/HelpManual.vue';

        const props = defineProps({
            loanBooks: Object,
            filters: Object,
            portfolios: Array,
            summary: Object,
        });

        const filters = ref({
            year: '',
            month: '',
            stage: '',
            portfolio: '',
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
            'carrying_amount',
            'pd_value',
            'lgd_value',
            'ecl_value',
            'ifrs9stage_post_qualitative',
            'ifrs9stage_pre_qualitative',
            'reporting_period',
            'customer_name', 
            'collateral_id',
            'create_date',
            'due_date',
            'remaining_tenor',
            'industry_code',
            'allocated_gross_value',
            'allocated_discounted_value',
            'collection_lgd',
            'customer_lgd',
            'pd_prefli',    
            'pd_post_fli',
            'fli_adj',
            ];


        const selectedPortfolio = ref('');
        const selectedPeriod = ref(''); 
        const selectedMode = ref('summary');
        const selectedColumns = ref([]);
        const loading = ref(false);
        const showModal = ref(false);


        const openReportModal = () => {
            selectedPeriod.value = '';
            selectedPortfolio.value = '';
            selectedMode.value = 'summary'; 
            selectedColumns.value = []; 
            showModal.value = true;
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

                window.location.href = url;
                showModal.value = false;
            } catch (error) {
                console.error('Export failed:', error);
            } finally {
                loading.value = false;
            }
        };

        const fetchData = async () => {
            try {
                const params = {
                    search: filters.value.search || undefined,
                    year: filters.value.year || undefined,
                    month: filters.value.month || undefined,
                    stage: filters.value.stage || undefined,
                    portfolio: filters.value.portfolio || undefined
                };
                
                // Remove undefined params
                Object.keys(params).forEach(key => params[key] === undefined && delete params[key]);
                
                router.get(route('expected-credit-loss.index'), params, {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                    onSuccess: () => {
                        // Summary will be automatically updated through props
                    },
                    onError: (errors) => {
                        console.error('Filter application failed:', errors);
                    }
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
                year: '',
                month: '',
                stage: '',
                portfolio: '',
                search: ''
            };
            fetchData();
        };

        // Computed property for formatted summary data
        const formattedSummary = computed(() => {
            if (!props.summary) return null;
            
            return {
                ...props.summary,
                total_exposure_formatted: formatCurrency(props.summary.total_exposure),
                total_loss_formatted: formatCurrency(props.summary.total_loss),
                loss_percentage: props.summary.total_exposure > 0 
                    ? ((props.summary.total_loss / props.summary.total_exposure) * 100).toFixed(2) + '%'
                    : '0%'
            };
        });

        const getStageClass = (stage) => {
            const classes = {
                1: 'bg-green-100 text-green-800',
                2: 'bg-yellow-100 text-yellow-800',
                3: 'bg-red-100 text-red-800'
            };
            return classes[stage] || 'bg-gray-100 text-gray-800';
        };

        const formatCurrency = (value) => {
            if (!value) return '0.00';
            return  new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
        };

        const summaryExpanded = ref(true); // Start expanded for better UX

        const toggleSummary = () => {
            summaryExpanded.value = !summaryExpanded.value;
        };

        const formatPeriod = (date) => {
            return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short'});
        };

        const formatDate = (date) => {
            return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        };

        const getChangeClass = (change) => {
            if (change > 0) return 'text-red-600'; // Increase is bad for loss/exposure
            if (change < 0) return 'text-green-600'; // Decrease is good
            return 'text-gray-500'; // No change
        };

        const formatChange = (change, percent) => {
            const sign = change > 0 ? '+' : '';
            const arrow = change > 0 ? '↑' : change < 0 ? '↓' : '→';
            return `${arrow} ${sign}${formatCurrency(Math.abs(change))} (${sign}${percent.toFixed(1)}%)`;
        };
</script>

<style scoped>
.slide-down-enter-active,
.slide-down-leave-active {
    transition: all 0.3s ease;
    overflow: hidden;
}

.slide-down-enter-from,
.slide-down-leave-to {
    max-height: 0;
    opacity: 0;
    transform: translateY(-10px);
}

.slide-down-enter-to,
.slide-down-leave-from {
    max-height: 500px;
    opacity: 1;
    transform: translateY(0);
}
</style>
