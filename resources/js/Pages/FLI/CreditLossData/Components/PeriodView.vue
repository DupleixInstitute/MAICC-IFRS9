<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('credit-loss-data.index')">
                    Credit Loss Data
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> {{ formatPeriod(period) }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Period Summary Cards -->
                <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <dt class="text-sm font-medium text-gray-500 truncate">
                                Selected Period
                            </dt>
                            <dd class="mt-1 text-lg font-semibold text-gray-900">
                                {{ formatPeriod(period) }}
                            </dd>
                        </div>
                    </div>
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <dt class="text-sm font-medium text-gray-500 truncate">
                                Total Records
                            </dt>
                            <dd class="mt-1 text-3xl font-semibold text-gray-900">
                                {{ totalRecords }}
                            </dd>
                        </div>
                    </div>
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <dt class="text-sm font-medium text-gray-500 truncate">
                                Portfolios
                            </dt>
                            <dd class="mt-1 text-2xl font-semibold text-blue-600">
                                {{ Object.keys(groupedData).length }}
                            </dd>
                        </div>
                    </div>
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <dt class="text-sm font-medium text-gray-500 truncate">
                                Total ECL
                            </dt>
                            <dd class="mt-1 text-2xl font-semibold text-green-600">
                                {{ formatCurrency(totalEcl) }}
                            </dd>
                        </div>
                    </div>
                    <div class="bg-white overflow-hidden shadow rounded-lg">
                        <div class="px-4 py-5 sm:p-6">
                            <dt class="text-sm font-medium text-gray-500 truncate">
                                Total NPL
                            </dt>
                            <dd class="mt-1 text-2xl font-semibold text-red-600">
                                {{ formatCurrency(totalNpl) }}
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
                            Add Record
                        </inertia-link>

                        <!-- Period Navigation -->
                        <div class="flex space-x-2">
                            <button
                                @click="navigatePeriod('prev')"
                                class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-none focus:border-gray-700 focus:ring focus:ring-gray-300 transition"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                </svg>
                                Previous
                            </button>
                            <button
                                @click="navigatePeriod('next')"
                                class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:outline-none focus:border-gray-700 focus:ring focus:ring-gray-300 transition"
                            >
                                Next
                                <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center space-x-4">
                        <!-- Portfolio Filter -->
                        <select 
                            v-model="filters.portfolio_id" 
                            @change="applyFilters"
                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">All Portfolios</option>
                            <option v-for="portfolio in portfolios" :key="portfolio.id" :value="portfolio.id">
                                {{ portfolio.name }}
                            </option>
                        </select>

                        <!-- Metric Filter -->
                        <select 
                            v-model="filters.definition_id" 
                            @change="applyFilters"
                            class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">All Metrics</option>
                            <option v-for="definition in definitions" :key="definition.id" :value="definition.id">
                                {{ definition.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Data Display by Portfolio -->
                <div class="space-y-6">
                    <div v-for="(metrics, portfolioId) in filteredData" :key="portfolioId" class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                        <div class="px-6 py-4 bg-gray-50 border-b flex justify-between items-center">
                            <h3 class="text-lg font-medium text-gray-900">
                                {{ getPortfolioName(portfolioId) }}
                                <span class="text-sm text-gray-500 ml-2">
                                    ({{ metrics.length }} metrics)
                                </span>
                            </h3>
                            <div class="text-sm text-gray-500">
                                Portfolio Summary
                            </div>
                        </div>
                        
                        <div class="overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Metric
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Value
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Description
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Source
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="record in metrics" :key="record.id" class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <span :class="getMetricBadgeClass(record.definition?.code)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                                {{ record.definition?.name || 'N/A' }}
                                            </span>
                                            <div class="text-xs text-gray-500 mt-1">
                                                {{ record.definition?.code }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium" :class="getValueColor(record.definition?.code, record.value)">
                                            {{ formatValue(record.definition?.code, record.value) }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            {{ record.definition?.description || 'No description' }}
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
                        </div>

                        <!-- Portfolio Summary -->
                        <div class="bg-gray-50 px-6 py-4 border-t">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-500">ECL:</span>
                                    <span class="ml-2 font-medium text-green-600">
                                        {{ formatCurrency(getPortfolioMetricValue(portfolioId, 'ECL')) }}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-gray-500">NPL:</span>
                                    <span class="ml-2 font-medium text-red-600">
                                        {{ formatCurrency(getPortfolioMetricValue(portfolioId, 'NPL')) }}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-gray-500">PD:</span>
                                    <span class="ml-2 font-medium text-blue-600">
                                        {{ formatPercentage(getPortfolioMetricValue(portfolioId, 'PD')) }}
                                    </span>
                                </div>
                                <div>
                                    <span class="text-gray-500">LGD:</span>
                                    <span class="ml-2 font-medium text-purple-600">
                                        {{ formatPercentage(getPortfolioMetricValue(portfolioId, 'LGD')) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="Object.keys(filteredData).length === 0" class="text-center py-12 bg-white rounded-lg shadow">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No data for {{ formatPeriod(period) }}</h3>
                    <p class="mt-1 text-sm text-gray-500">No credit loss data found for the selected period.</p>
                    <div class="mt-6">
                        <inertia-link
                            :href="route('credit-loss-data.create')"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring focus:ring-indigo-200 active:bg-indigo-800 transition"
                        >
                            Add New Record
                        </inertia-link>
                    </div>
                </div>

                <!-- Edit Modal -->
                <div v-if="editingRecord" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                    <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
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
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Value</label>
                                    <input
                                        type="number"
                                        step="0.0001"
                                        v-model="editForm.value"
                                        :class="getInputClass(editingRecord.definition?.code)"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                    <p class="mt-1 text-xs text-gray-500" v-if="editingRecord.definition">
                                        {{ getInputDescription(editingRecord.definition.code) }}
                                    </p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Source</label>
                                    <select
                                        v-model="editForm.source"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="Manual Entry">Manual Entry</option>
                                        <option value="CSV Import">CSV Import</option>
                                        <option value="System Generated">System Generated</option>
                                        <option value="External Data">External Data</option>
                                        <option value="Regulatory Report">Regulatory Report</option>
                                        <option value="Internal Model">Internal Model</option>
                                    </select>
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
        period: String, // The selected period (YYYY-MM)
        creditLossData: Array, // Data for the selected period
        definitions: Array,
        portfolios: Array,
        allPeriods: Array, // All available periods for navigation
    },
    data() {
        return {
            filters: {
                portfolio_id: '',
                definition_id: ''
            },
            editingRecord: null,
            editForm: {
                value: null,
                source: '',
                notes: ''
            },
            updating: false
        }
    },
    computed: {
        // Group data by portfolio
        groupedData() {
            const grouped = {};
            
            this.creditLossData.forEach(record => {
                const portfolioId = record.portfolio_id;
                
                if (!grouped[portfolioId]) {
                    grouped[portfolioId] = [];
                }
                
                grouped[portfolioId].push(record);
            });
            
            return grouped;
        },
        
        filteredData() {
            let filtered = { ...this.groupedData };
            
            // Filter by portfolio
            if (this.filters.portfolio_id) {
                Object.keys(filtered).forEach(portfolioId => {
                    if (portfolioId != this.filters.portfolio_id) {
                        delete filtered[portfolioId];
                    }
                });
            }
            
            // Filter by definition
            Object.keys(filtered).forEach(portfolioId => {
                if (this.filters.definition_id) {
                    filtered[portfolioId] = filtered[portfolioId].filter(record => 
                        record.definition_id == this.filters.definition_id
                    );
                }
                
                // Remove empty portfolios
                if (filtered[portfolioId].length === 0) {
                    delete filtered[portfolioId];
                }
            });
            
            return filtered;
        },
        
        totalRecords() {
            return this.creditLossData.length;
        },
        
        totalEcl() {
            return this.getTotalMetricValue('ECL');
        },
        
        totalNpl() {
            return this.getTotalMetricValue('NPL');
        }
    },
    methods: {
        getPortfolioName(portfolioId) {
            const portfolio = this.portfolios.find(p => p.id == portfolioId);
            return portfolio ? portfolio.name : `Portfolio ${portfolioId}`;
        },
        
        getPortfolioMetricValue(portfolioId, metricCode) {
            const definition = this.definitions.find(d => d.code === metricCode);
            if (!definition) return 0;
            
            const record = this.groupedData[portfolioId]?.find(r => r.definition_id === definition.id);
            return record?.value || 0;
        },
        
        getTotalMetricValue(metricCode) {
            const definition = this.definitions.find(d => d.code === metricCode);
            if (!definition) return 0;
            
            const records = this.creditLossData.filter(r => r.definition_id === definition.id);
            return records.reduce((sum, record) => sum + (record.value || 0), 0);
        },
        
        navigatePeriod(direction) {
            const currentIndex = this.allPeriods.indexOf(this.period);
            let newIndex;
            
            if (direction === 'prev' && currentIndex > 0) {
                newIndex = currentIndex - 1;
            } else if (direction === 'next' && currentIndex < this.allPeriods.length - 1) {
                newIndex = currentIndex + 1;
            } else {
                return; // No navigation possible
            }
            
            const newPeriod = this.allPeriods[newIndex];
            Inertia.get(route('credit-loss-data.period', { period: newPeriod }));
        },
        
        applyFilters() {
            // Filters are applied reactively in computed property
        },
        
        formatPeriod(period) {
            if (!period) return 'N/A';
            const [year, month] = period.split('-');
            return new Date(year, month - 1).toLocaleDateString('en-US', { year: 'numeric', month: 'long' });
        },
        
        formatCurrency(value) {
            if (!value) return 'E0.00';
            return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
        },
        
        formatPercentage(value) {
            if (!value) return '-';
            return new Intl.NumberFormat('en-US', {
                style: 'percent',
                minimumFractionDigits: 2,
                maximumFractionDigits: 4
            }).format(value);
        },
        
        formatValue(metricCode, value) {
            if (value === null || value === undefined) return '-';
            
            const percentageMetrics = ['PD', 'LGD'];
            const currencyMetrics = ['ECL', 'NPL', 'EAD'];
            
            if (percentageMetrics.includes(metricCode)) {
                return this.formatPercentage(value);
            } else if (currencyMetrics.includes(metricCode)) {
                return this.formatCurrency(value);
            } else {
                return value;
            }
        },
        
        getMetricBadgeClass(metricCode) {
            const classes = {
                'ECL': 'bg-blue-100 text-blue-800',
                'PD': 'bg-green-100 text-green-800',
                'LGD': 'bg-yellow-100 text-yellow-800',
                'EAD': 'bg-purple-100 text-purple-800',
                'NPL': 'bg-red-100 text-red-800',
                'STAGE': 'bg-gray-100 text-gray-800',
                'CREDIT_RATING': 'bg-indigo-100 text-indigo-800'
            };
            return classes[metricCode] || 'bg-gray-100 text-gray-800';
        },
        
        getValueColor(metricCode, value) {
            if (value === null || value === undefined) return 'text-gray-500';
            
            if (['PD', 'LGD', 'NPL'].includes(metricCode)) {
                if (value > 0.1) return 'text-red-600';
                if (value > 0.05) return 'text-yellow-600';
                return 'text-green-600';
            }
            return 'text-gray-900';
        },
        
        getInputClass(metricCode) {
            const percentageMetrics = ['PD', 'LGD'];
            if (percentageMetrics.includes(metricCode)) {
                return 'pr-10';
            }
            return '';
        },
        
        getInputDescription(metricCode) {
            const descriptions = {
                'PD': 'Probability of Default (0-1, e.g., 0.05 for 5%)',
                'LGD': 'Loss Given Default (0-1, e.g., 0.45 for 45%)',
                'ECL': 'Expected Credit Loss in currency',
                'NPL': 'Non-Performing Loans in currency',
                'EAD': 'Exposure at Default in currency',
                'STAGE': 'IFRS 9 Stage (1, 2, or 3)',
                'CREDIT_RATING': 'Credit rating string'
            };
            return descriptions[metricCode] || 'Enter value';
        },
        
        editRecord(record) {
            this.editingRecord = record;
            this.editForm = { 
                value: record.value,
                source: record.source,
                notes: record.notes
            };
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
                Inertia.delete(route('credit-loss-data.destroy', record.id));
            }
        }
    }
}
</script> 