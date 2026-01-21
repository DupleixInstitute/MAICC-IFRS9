<template>
    <AppLayout title="Loan Book">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Loan Book Management
                </h2>
                <div class="flex space-x-4">
                    <button @click="showImportModal = true"
                            class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 hidden">
                        Import Loan Book
                    </button>
                    <Link
                        :href="route('loan_applications.loan-book.import.create')"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        Import Loan Book
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Summary Section -->
                <div class="mb-6" v-if="summary">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Summary</h3>
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
                        <div v-show="summaryExpanded" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                                <div class="text-sm font-semibold ">Total Loans</div>
                                <div class="text-2xl text-gray-600">{{ summary.total_loans || 0 }}</div>
                            </div>
                            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                                <div class="text-sm ">Total Balance</div>
                                <div class="text-2xl text-gray-600">{{ formatCurrency(summary.total_balance) }}</div>
                            </div>
                            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                                <div class="text-sm font-semibold">Classification Count</div>
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium text-gray-700">Stage 1:</span>
                                        <span :class="getStageClass(1)" class="px-2 py-1 text-xs font-semibold rounded">
                                            {{ summary.stage_1_count || 0 }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium text-gray-700">Stage 2:</span>
                                        <span :class="getStageClass(2)" class="px-2 py-1 text-xs font-semibold rounded">
                                            {{ summary.stage_2_count || 0 }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm font-medium text-gray-700">Stage 3:</span>
                                        <span :class="getStageClass(3)" class="px-2 py-1 text-xs font-semibold rounded">
                                            {{ summary.stage_3_count || 0 }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                                <div class="text-sm font-semibold">Total Loss</div>
                                <div class="text-2xl text-gray-600">{{ formatCurrency(summary.total_ecl) }}</div>
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
                                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Portfolio</th>
                                        <th scope="col" class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance (MKW)</th>
                                        <th scope="col" class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Stage</th>
                                        <th scope="col" class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">PD Pre-FLI</th>
                                        <th scope="col" class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">FLI Adj</th>
                                        <th scope="col" class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">PD Post-FLI</th>
                                        <th scope="col" class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">LGD</th>
                                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due Date</th>
                                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <!-- <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Updated</th> -->
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="loan in loanBooks.data" :key="loan.id">
                                        <td class="px-3 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ formatPeriod(loan.reporting_period) }}</td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ loan.contract_id }}</td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <div class="font-medium text-gray-900">{{ loan.customer_name }}</div>
                                            <div class="text-xs text-gray-400">ID: {{ loan.customer_id }}</div>
                                        </td>
                                        <td class="px-3 py-4 whitespace-nowrap text-left text-sm text-gray-500">{{ loan.portfolio?.name || loan.loan_portfolio_id }} </td>
                                        <td class="px-3 py-4 whitespace-nowrap  text-right text-sm text-gray-500">{{ formatCurrency(loan.carrying_amount) }}</td>
                                        <td class="px-3 py-4 whitespace-nowrap text-center text-sm">
                                            <span :class="getStageClass(loan.ifrs9stage_post_qualitative)" class="px-2 py-1 text-xs font-semibold rounded">
                                                Stage {{ loan.ifrs9stage_post_qualitative || '-' }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-4 whitespace-nowrap text-center text-sm text-gray-500">{{ formatPercent(loan.pd_prefli) }}</td>
                                        <td class="px-3 py-4 whitespace-nowrap text-center text-sm font-medium" :class="getFliAdjClass(loan.fli_adj)">
                                            {{ formatPercent(loan.fli_adj) }}
                                        </td>
                                        <td class="px-3 py-4 whitespace-nowrap text-center text-sm text-gray-900 font-medium">{{ formatPercent(loan.pd_post_fli_adj) }}</td>
                                        <td class="px-3 py-4 whitespace-nowrap text-center text-sm text-gray-500">{{ formatPercent(loan.lgd_value) }}</td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatDate(loan.due_date) }}</td>
                                        <td class="px-3 py-4 whitespace-nowrap text-sm">
                                            <span :class="getStatusClass(loan.overdue_status)" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                                {{ loan.overdue_status }}
                                            </span>
                                        </td>
                                        <!-- <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">{{ loan.updated_at ? $filters.time(loan.updated_at) : '' }}</td> -->
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
        <HelpManual />
    </AppLayout>

    <!-- Import Modal -->
    <Modal :show="showImportModal" @close="showImportModal = false">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">Import Loan Book</h2>

            <div class="mt-4">
                <!-- Import Instructions -->
                <div class="mb-6 bg-gray-50 rounded-lg p-4">
                    <h3 class="text-sm font-medium text-gray-900 mb-2">Import Instructions</h3>
                    <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                        <li>Download and use the template below for your data</li>
                        <li>Required columns: loan_id, customer ID, Issue Date(Create date), due_date, principal_balance, overdue_days</li>
                        <li>Dates should be in dd/mm/yyyy format (e.g., 31/12/2024)</li>
                        <li>Principal balance must be a positive number</li>
                        <li>Overdue days must be a non-negative number</li>
                        <li>Maximum file size: 10MB</li>
                    </ul>

                    <div class="mt-3">
                        <a href="#" @click.prevent="downloadTemplate"
                           class="inline-flex items-center text-sm text-blue-600 hover:text-blue-800">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10" />
                            </svg>
                            Download Template
                        </a>
                    </div>
                </div>

                <form @submit.prevent="submitImport" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Reporting Period</label>
                        <input type="month" v-model="importForm.reporting_period" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <p class="mt-1 text-xs text-gray-500">Select the month and year for this loan book data</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Portfolio</label>
                        <select v-model="importForm.portfolio" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Select Portfolio</option>
                            <option v-for="portfolio in portfolios" :key="portfolio.id" :value="portfolio.id">
                                {{ portfolio.name }}
                            </option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Choose the portfolio type for this loan book</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">CSV File</label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md">
                            <div class="space-y-1 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                                          stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="flex text-sm text-gray-600">
                                    <label class="relative cursor-pointer rounded-md font-medium text-indigo-600 hover:text-indigo-500">
                                        <input type="file" @change="handleFileUpload" accept=".csv" class="sr-only" required>
                                        <span>Upload a file</span>
                                    </label>
                                    <p class="pl-1">or drag and drop</p>
                                </div>
                                <p class="text-xs text-gray-500">CSV file up to 10MB</p>
                            </div>
                        </div>
                        <div v-if="importForm.file" class="mt-2 text-sm text-gray-600">
                            Selected file: {{ importForm.file.name }}
                        </div>
                    </div>

                    <div v-if="importProgress" class="mt-4">
                        <div class="relative pt-1">
                            <div class="flex mb-2 items-center justify-between">
                                <div>
                                    <span class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded-full"
                                          :class="{'text-green-600': importProgress === 100}">
                                        Progress
                                    </span>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs font-semibold inline-block">
                                        {{ importProgress }}%
                                    </span>
                                </div>
                            </div>
                            <div class="overflow-hidden h-2 mb-4 text-xs flex rounded bg-gray-200">
                                <div :style="{ width: importProgress + '%' }"
                                     class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-green-500">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="importError" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-md">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Import Error</h3>
                                <div class="mt-2 text-sm text-red-700">
                                    {{ importError }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end space-x-3">
                        <button type="button" @click="showImportModal = false"
                                class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit"
                                :disabled="importing"
                                class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50">
                            {{ importing ? 'Importing...' : 'Import' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
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

const summary = computed(() => props.summary);

const years = [2022, 2023, 2024, 2025, 2026, 2027, 2028, 2029, 2030];
const months = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
];

const showImportModal = ref(false);
const importing = ref(false);
const importProgress = ref(0);
const importError = ref('');
const summaryExpanded = ref(true); // Start expanded for better UX
const importForm = ref({
    reporting_period: '',
    portfolio: '',
    file: null
});

const toggleSummary = () => {
    summaryExpanded.value = !summaryExpanded.value;
};

const handleFileUpload = (event) => {
    importForm.value.file = event.target.files[0];
    importError.value = ''; // Clear any previous errors
};

const submitImport = async () => {
    if (!importForm.value.file) {
        importError.value = 'Please select a file';
        return;
    }

    importing.value = true;
    importProgress.value = 0;
    importError.value = ''; // Clear any previous errors

    const formData = new FormData();
    formData.append('file', importForm.value.file);
    formData.append('reporting_period', importForm.value.reporting_period);
    formData.append('portfolio', importForm.value.portfolio);

    try {
        const response = await fetch(route('loan_applications.loan-book.save-loan-book'), {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.error || 'Import failed');
        }

        alert('Import successful!');
        showImportModal.value = false;
        fetchData(); // Refresh the data
    } catch (error) {
        importError.value = error.message;
    } finally {
        importing.value = false;
        importProgress.value = 0;
        // Only reset form on success
        if (!importError.value) {
            importForm.value = {
                reporting_period: '',
                portfolio: '',
                file: null
            };
        }
    }
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

const applyFilters = () => {
    fetchData();
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
        
        // Remove undefined params but keep year/month independently
        Object.keys(params).forEach(key => params[key] === undefined && delete params[key]);
        
        router.get(route('loan_applications.loan-book'), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onSuccess: (page) => {
                // Ensure the summary is updated from the page props
                if (page.props.summary) {
                    // Summary will be automatically updated through props
                }
            }
        });
    } catch (error) {
        console.error('Error fetching data:', error);
    }
};

const formatCurrency = (value) => {
    if (!value) return '0.00';
    return  new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
};

const formatDate = (date) => {
    return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
};
const formatPeriod = (date) => {
    return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short'});
};

const getOverdueClass = (days) => {
    if (days === 0) return 'text-green-600';
    if (days <= 30) return 'text-yellow-600';
    return 'text-red-600';
};

const getStatusClass = (status) => {
    const classes = {
        'Current': 'bg-green-100 text-green-800',
        'Watch': 'bg-yellow-100 text-yellow-800',
        'Substandard': 'bg-orange-100 text-orange-800',
        'Doubtful': 'bg-red-100 text-red-800',
        'Loss': 'bg-red-200 text-red-900'
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};

const getStageClass = (stage) => {
    const classes = {
        1: 'bg-green-100 text-green-800',
        2: 'bg-yellow-100 text-yellow-800',
        3: 'bg-red-100 text-red-800'
    };
    return classes[stage] || 'bg-gray-100 text-gray-800';
};

const getFliAdjClass = (value) => {
    if (!value) return 'text-gray-400';
    if (value > 0) return 'text-red-600';
    if (value < 0) return 'text-green-600';
    return 'text-gray-600';
};

const formatPercent = (value) => {
    if (value === null || value === undefined) return '-';
    return (parseFloat(value) * 100).toFixed(2) + '%';
};

const downloadTemplate = () => {
    const template = `loan_id,customer_id,issue_date,due_date,principal_balance,overdue_days
L001,CUS001,01/01/2024,31/12/2024,50000.00,0
L002,CUS002,15/01/2024,31/12/2024,75000.00,30`;

    const blob = new Blob([template], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'loan_book_template.csv';
    document.body.appendChild(a);
    a.click();
    window.URL.revokeObjectURL(url);
    document.body.removeChild(a);
};

onMounted(() => {
    //fetchSummary();
});
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
