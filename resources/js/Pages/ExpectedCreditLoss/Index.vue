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
                       <!-- <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6" v-if="summary">
                           <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                               <div class="text-sm text-gray-600">Total Loans</div>
                               <div class="text-2xl font-semibold">{{ summary.total_loans }}</div>
                           </div>
                           <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                               <div class="text-sm text-gray-600">Total Balance</div>
                               <div class="text-2xl font-semibold">{{ formatCurrency(summary.total_balance) }}</div>
                           </div>
                           <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                               <div class="text-sm text-gray-600">Overdue Loans</div>
                               <div class="text-2xl font-semibold">{{ summary.overdue_loans }}</div>
                           </div>
                           <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                               <div class="text-sm text-gray-600">Total Provision</div>
                               <div class="text-2xl font-semibold">{{ formatCurrency(summary.total_provision) }}</div>
                           </div>
                       </div> -->
       
                       <!-- Filters -->
                       <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                           <div class="p-6 bg-white border-b border-gray-200">
                               <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                   <div>
                                       <label class="block text-sm font-medium text-gray-700">Year</label>
                                       <select v-model="filters.year" @change="fetchData"
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                           <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
                                       </select>
                                   </div>
                                   <div>
                                       <label class="block text-sm font-medium text-gray-700">Month</label>
                                       <select v-model="filters.month" @change="fetchData"
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                           <option v-for="(name, index) in months" :key="index" :value="index + 1">{{ name }}</option>
                                       </select>
                                   </div>
                                   <div>
                                       <label class="block text-sm font-medium text-gray-700">Stages</label>
                                       <select v-model="filters.stage" @change="fetchData"
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                           <option value="">All Stages</option>
                                           <option value="0">Stage 1</option>
                                           <option value="1">Stage 2</option>
                                           <option value="2">Stage 3</option>
                                       </select>
                                   </div>
                                   <div>
                                       <label class="block text-sm font-medium text-gray-700">Search</label>
                                       <input type="text" v-model="filters.search" @input="fetchData"
                                              placeholder="Search by Contract ID or Customer..."
                                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                   </div>
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
                                               <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contract ID</th>
                                               <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IFRS Stage</th>
                                               <!-- <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Portfolio</th> -->
                                               <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                                               <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Probability Of Default</th>
                                               <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Loss Given Default</th>
                                               <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expected Credit Loss</th>
                                               <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Updated</th>
                                           </tr>
                                       </thead>
                                       <tbody class="bg-white divide-y divide-gray-200">
                                           <tr v-for="loan in loanBooks.data" :key="loan.id">
                                               <td class="px-3 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ loan.contract_id }}</td>
                                               <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">{{ loan.calculated_ifrs9_stage }}</td>
                                               <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatCurrency(loan.principal_balance) }}</td>
                                               <td class="px-3 py-4 whitespace-nowrap text-sm">{{ loan.pd_value }}</td>
                                               <td class="px-3 py-4 whitespace-nowrap text-sm" >{{ loan.lgd_value }}</td>
                                               <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500"> {{ formatCurrency(loan.ecl_value) }}</td>
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
import { ref, defineProps } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import HelpManual from '../../Components/HelpManual.vue';

        const props = defineProps({
            loanBooks: Object,
            filters: Object,
            portfolios: Array,
        });

        const filters = ref({
            year: new Date().getFullYear(),
            month: new Date().getMonth() + 1,
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
            'external_identity_id', 
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
                window.Inertia.get(route('loan_applications.loan-book'), {
                    search: props.filters.search,
                    year: props.filters.year,
                    month: props.filters.month,
                    overdue: props.filters.overdue
                }, {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true
                });
            } catch (error) {
                console.error('Error fetching data:', error);
            }
        };

        const formatCurrency = (value) => {
            if (!value) return 'E0.00';
            return 'K' + new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
        };

        const formatDate = (date) => {
            return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        };
</script>
