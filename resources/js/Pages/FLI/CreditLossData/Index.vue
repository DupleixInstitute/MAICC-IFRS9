<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <!-- <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('loan-applications.loan-book')">
                    Loan Portfolios
                </inertia-link> -->
                <span class="text-indigo-400 font-medium"></span>
               Credit Loss Data
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <dt class="text-sm font-medium text-gray-500 truncate">
                                Total Records
                            </dt>
                            <dd class="mt-1 text-3xl font-semibold text-gray-900">
                                {{ creditLossData.length }}
                            </dd>
                        </div>
                    </div>
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <dt class="text-sm font-medium text-gray-500 truncate">
                                Date Range
                            </dt>
                            <dd class="mt-1 text-lg font-semibold text-gray-900">
                                {{ dateRange }}
                            </dd>
                        </div>
                    </div>
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <dt class="text-sm font-medium text-gray-500 truncate">
                                Latest ECL
                            </dt>
                            <dd class="mt-1 text-2xl font-semibold text-green-600">
                                {{ formatCurrency(latestEcl) }}
                            </dd>
                        </div>
                    </div>
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <dt class="text-sm font-medium text-gray-500 truncate">
                                Latest NPL
                            </dt>
                            <dd class="mt-1 text-2xl font-semibold text-red-600">
                                {{ formatCurrency(latestNpl) }}
                            </dd>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mb-6 flex justify-between items-center">
                    <div class="flex space-x-3">
                        <inertia-link
                            :href="route('credit-loss-data.create')"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-800 focus:outline-none focus:border-indigo-800 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add New Record
                        </inertia-link>

                        <inertia-link
                            :href="route('credit-loss-data.importView')"
                            class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 active:bg-green-800 focus:outline-none focus:border-green-800 focus:ring focus:ring-green-300 disabled:opacity-25 transition"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                            </svg>
                            Import CSV
                        </inertia-link>
                    </div>

                    <div class="flex items-center space-x-4">
                        <!-- Period Filter -->
                        <select 
                            v-model="filters.period" 
                            @change="applyFilters"
                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">All Periods</option>
                            <option v-for="period in uniquePeriods" :key="period" :value="period">
                                {{ formatPeriod(period) }}
                            </option>
                        </select>

                        <!-- Stage Filter -->
                        <select 
                            v-model="filters.stage" 
                            @change="applyFilters"
                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">All Stages</option>
                            <option value="1">Stage 1</option>
                            <option value="2">Stage 2</option>
                            <option value="3">Stage 3</option>
                        </select>

                        <!-- Search -->
                        <input
                            type="text"
                            v-model="filters.search"
                            @input="applyFilters"
                            placeholder="Search..."
                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                    </div>
                </div>

                <!-- Data Table -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="flex flex-col">
                        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                                <div class="overflow-hidden">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('period')">
                                                    Period
                                                    <span v-if="sortField === 'period'" class="ml-1">
                                                        {{ sortDirection === 'asc' ? '↑' : '↓' }}
                                                    </span>
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('ecl_value')">
                                                    ECL
                                                    <span v-if="sortField === 'ecl_value'" class="ml-1">
                                                        {{ sortDirection === 'asc' ? '↑' : '↓' }}
                                                    </span>
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('npl_value')">
                                                    NPL
                                                    <span v-if="sortField === 'npl_value'" class="ml-1">
                                                        {{ sortDirection === 'asc' ? '↑' : '↓' }}
                                                    </span>
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    PD/LGD/EAD
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer" @click="sortBy('stage')">
                                                    Stage
                                                    <span v-if="sortField === 'stage'" class="ml-1">
                                                        {{ sortDirection === 'asc' ? '↑' : '↓' }}
                                                    </span>
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Credit Rating
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Source
                                                </th>
                                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                    Actions
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <tr v-for="record in paginatedData" :key="record.id" class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {{ formatPeriod(record.period) }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    {{ formatCurrency(record.ecl_value) }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    {{ formatCurrency(record.npl_value) }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <div class="space-y-1">
                                                        <div class="flex items-center">
                                                            <span class="text-xs text-gray-500 w-8">PD:</span>
                                                            <span class="text-sm">{{ formatPercentage(record.pd_value) }}</span>
                                                        </div>
                                                        <div class="flex items-center">
                                                            <span class="text-xs text-gray-500 w-8">LGD:</span>
                                                            <span class="text-sm">{{ formatPercentage(record.lgd_value) }}</span>
                                                        </div>
                                                        <div class="flex items-center">
                                                            <span class="text-xs text-gray-500 w-8">EAD:</span>
                                                            <span class="text-sm">{{ formatCurrency(record.ead_value) }}</span>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span :class="getStageBadgeClass(record.stage)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                                        {{ getStageText(record.stage) }}
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <span :class="getRatingClass(record.credit_rating)" class="px-2 py-1 rounded text-xs font-medium">
                                                        {{ record.credit_rating || 'N/A' }}
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <div class="flex items-center">
                                                        <svg v-if="record.source === 'CSV Import'" class="w-4 h-4 mr-1 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                        </svg>
                                                        {{ record.source || 'Manual' }}
                                                    </div>
                                                    <div class="text-xs text-gray-400 mt-1">
                                                        by {{ record.creator?.name || 'System' }}
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <div class="flex space-x-2">
                                                        <button
                                                            @click="editRecord(record)"
                                                            class="text-indigo-600 hover:text-indigo-900"
                                                        >
                                                            Edit
                                                        </button>
                                                        <button
                                                            @click="deleteRecord(record)"
                                                            class="text-red-600 hover:text-red-900"
                                                        >
                                                            Delete
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>

                                    <!-- Empty State -->
                                    <div v-if="filteredData.length === 0" class="text-center py-12">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <h3 class="mt-2 text-sm font-medium text-gray-900">No credit loss data</h3>
                                        <p class="mt-1 text-sm text-gray-500">Get started by adding a new record or importing CSV data.</p>
                                        <div class="mt-6">
                                            <inertia-link
                                                :href="route('credit-loss-data.create')"
                                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring focus:ring-indigo-200 active:bg-indigo-800 transition"
                                            >
                                                Add New Record
                                            </inertia-link>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pagination -->
                                <div v-if="filteredData.length > 0" class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
                                    <div class="flex-1 flex justify-between items-center">
                                        <div>
                                            <p class="text-sm text-gray-700">
                                                Showing
                                                <span class="font-medium">{{ (currentPage - 1) * perPage + 1 }}</span>
                                                to
                                                <span class="font-medium">{{ Math.min(currentPage * perPage, filteredData.length) }}</span>
                                                of
                                                <span class="font-medium">{{ filteredData.length }}</span>
                                                results
                                            </p>
                                        </div>
                                        <div class="flex space-x-2">
                                            <button
                                                @click="prevPage"
                                                :disabled="currentPage === 1"
                                                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                            >
                                                Previous
                                            </button>
                                            <button
                                                @click="nextPage"
                                                :disabled="currentPage >= totalPages"
                                                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                            >
                                                Next
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Modal -->
                <div v-if="editingRecord" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                    <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
                        <div class="mt-3">
                            <div class="flex justify-between items-center pb-3 border-b">
                                <h3 class="text-lg font-medium text-gray-900">
                                    Edit Credit Loss Data
                                </h3>
                                <button @click="editingRecord = null" class="text-gray-400 hover:text-gray-500">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>

                            <form @submit.prevent="updateRecord" class="mt-4 space-y-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Period</label>
                                        <input
                                            type="month"
                                            v-model="editForm.period"
                                            required
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Stage</label>
                                        <select
                                            v-model="editForm.stage"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="">Select Stage</option>
                                            <option value="1">Stage 1</option>
                                            <option value="2">Stage 2</option>
                                            <option value="3">Stage 3</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">ECL Value</label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            v-model="editForm.ecl_value"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">NPL Value</label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            v-model="editForm.npl_value"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                    </div>
                                </div>

                                <div class="grid grid-cols-3 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">PD Value</label>
                                        <input
                                            type="number"
                                            step="0.0001"
                                            min="0"
                                            max="1"
                                            v-model="editForm.pd_value"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">LGD Value</label>
                                        <input
                                            type="number"
                                            step="0.0001"
                                            min="0"
                                            max="1"
                                            v-model="editForm.lgd_value"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">EAD Value</label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            v-model="editForm.ead_value"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Notes</label>
                                    <textarea
                                        v-model="editForm.notes"
                                        rows="3"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    ></textarea>
                                </div>

                                <div class="flex justify-end space-x-3 pt-4">
                                    <button
                                        type="button"
                                        @click="editingRecord = null"
                                        class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 focus:outline-none focus:border-gray-400 focus:ring focus:ring-gray-200 active:bg-gray-500 transition"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        :disabled="updating"
                                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring focus:ring-indigo-200 active:bg-indigo-800 disabled:opacity-25 transition"
                                    >
                                        {{ updating ? 'Updating...' : 'Update' }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Inertia } from '@inertiajs/inertia'
import { Link } from '@inertiajs/vue3'

export default {
    components: {
        AppLayout,
        Link,
    },
    props: {
        portfolio: Object,
        creditLossData: Array,
        profiles: Array,
    },
    data() {
        return {
            filters: {
                period: '',
                stage: '',
                search: ''
            },
            sortField: 'period',
            sortDirection: 'desc',
            currentPage: 1,
            perPage: 10,
            editingRecord: null,
            editForm: {
                period: '',
                ecl_value: null,
                npl_value: null,
                pd_value: null,
                lgd_value: null,
                ead_value: null,
                stage: '',
                credit_rating: '',
                notes: ''
            },
            updating: false
        }
    },
    computed: {
        sortedData() {
            return [...this.filteredData].sort((a, b) => {
                let aVal = a[this.sortField];
                let bVal = b[this.sortField];

                if (this.sortField === 'period') {
                    aVal = new Date(aVal + '-01');
                    bVal = new Date(bVal + '-01');
                }

                if (aVal < bVal) return this.sortDirection === 'asc' ? -1 : 1;
                if (aVal > bVal) return this.sortDirection === 'asc' ? 1 : -1;
                return 0;
            });
        },
        filteredData() {
            return this.creditLossData.filter(record => {
                const matchesPeriod = !this.filters.period || record.period === this.filters.period;
                const matchesStage = !this.filters.stage || record.stage == this.filters.stage;
                const matchesSearch = !this.filters.search || 
                    Object.values(record).some(value => 
                        value && value.toString().toLowerCase().includes(this.filters.search.toLowerCase())
                    );
                
                return matchesPeriod && matchesStage && matchesSearch;
            });
        },
        paginatedData() {
            const start = (this.currentPage - 1) * this.perPage;
            const end = start + this.perPage;
            return this.sortedData.slice(start, end);
        },
        totalPages() {
            return Math.ceil(this.filteredData.length / this.perPage);
        },
        uniquePeriods() {
            return [...new Set(this.creditLossData.map(item => item.period))].sort().reverse();
        },
        dateRange() {
            if (this.creditLossData.length === 0) return 'N/A';
            const periods = this.creditLossData.map(item => item.period).sort();
            return `${this.formatPeriod(periods[0])} - ${this.formatPeriod(periods[periods.length - 1])}`;
        },
        latestEcl() {
            if (this.creditLossData.length === 0) return 0;
            const latest = this.creditLossData.sort((a, b) => b.period.localeCompare(a.period))[0];
            return latest.ecl_value || 0;
        },
        latestNpl() {
            if (this.creditLossData.length === 0) return 0;
            const latest = this.creditLossData.sort((a, b) => b.period.localeCompare(a.period))[0];
            return latest.npl_value || 0;
        }
    },
    methods: {
        sortBy(field) {
            if (this.sortField === field) {
                this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                this.sortField = field;
                this.sortDirection = 'asc';
            }
        },
        applyFilters() {
            this.currentPage = 1;
        },
        nextPage() {
            if (this.currentPage < this.totalPages) {
                this.currentPage++;
            }
        },
        prevPage() {
            if (this.currentPage > 1) {
                this.currentPage--;
            }
        },
        formatPeriod(period) {
            if (!period) return 'N/A';
            const [year, month] = period.split('-');
            return new Date(year, month - 1).toLocaleDateString('en-US', { year: 'numeric', month: 'long' });
        },
        formatCurrency(value) {
            if (!value) return '-';
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(value);
        },
        formatPercentage(value) {
            if (!value) return '-';
            return new Intl.NumberFormat('en-US', {
                style: 'percent',
                minimumFractionDigits: 2,
                maximumFractionDigits: 4
            }).format(value);
        },
        getStageBadgeClass(stage) {
            const classes = {
                '1': 'bg-green-100 text-green-800',
                '2': 'bg-yellow-100 text-yellow-800',
                '3': 'bg-red-100 text-red-800'
            };
            return classes[stage] || 'bg-gray-100 text-gray-800';
        },
        getStageText(stage) {
            const stages = {
                '1': 'Stage 1',
                '2': 'Stage 2',
                '3': 'Stage 3'
            };
            return stages[stage] || 'Unknown';
        },
        getRatingClass(rating) {
            if (!rating) return 'bg-gray-100 text-gray-800';
            if (rating.includes('A') || rating.includes('1')) return 'bg-green-100 text-green-800';
            if (rating.includes('B') || rating.includes('2')) return 'bg-yellow-100 text-yellow-800';
            if (rating.includes('C') || rating.includes('3')) return 'bg-red-100 text-red-800';
            return 'bg-gray-100 text-gray-800';
        },
        editRecord(record) {
            this.editingRecord = record;
            this.editForm = { ...record };
        },
        updateRecord() {
            this.updating = true;
            Inertia.put(route('credit-loss-data.update', this.editingRecord.id), this.editForm, {
                onSuccess: () => {
                    this.editingRecord = null;
                    this.updating = false;
                },
                onError: () => {
                    this.updating = false;
                }
            });
        },
        deleteRecord(record) {
            if (confirm('Are you sure you want to delete this record?')) {
                Inertia.delete(route('credit-loss-data.destroy', record.id), {
                    onSuccess: () => {
                        // Record will be removed automatically due to Inertia update
                    }
                });
            }
        }
    }
}
</script>