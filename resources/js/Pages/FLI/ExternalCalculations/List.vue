<template>
    <AppLayout title="FLI Calculation History">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                FLI Calculation History
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-full mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6">
                        <!-- Header with Action Button -->
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-lg font-semibold text-gray-900">
                                External Calculation Records
                            </h3>
                            <a
                                :href="route('fli.external.index')"
                                class="inline-flex items-center px-4 py-2 bg-maiic-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-maiic-700 focus:outline-none focus:ring-2 focus:ring-maiic-500 focus:ring-offset-2 transition ease-in-out duration-150"
                            >
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                New Calculation
                            </a>
                        </div>

                        <!-- Summary Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div class="bg-gradient-to-br from-maiic-50 to-maiic-100 rounded-lg p-4 border border-maiic-200">
                                <div class="text-sm font-medium text-maiic-600">Total Calculations</div>
                                <div class="text-2xl font-bold text-maiic-900">{{ parameters.length }}</div>
                            </div>
                            <div class="bg-gradient-to-br from-maiic-50 to-maiic-100 rounded-lg p-4 border border-maiic-200">
                                <div class="text-sm font-medium text-maiic-600">Reporting Periods</div>
                                <div class="text-2xl font-bold text-maiic-900">{{ uniqueReportingPeriods }}</div>
                            </div>
                            <div class="bg-gradient-to-br from-maiic-50 to-maiic-100 rounded-lg p-4 border border-maiic-200">
                                <div class="text-sm font-medium text-maiic-600">Total Adjustments</div>
                                <div class="text-2xl font-bold text-maiic-900">{{ totalAdjustments }}</div>
                            </div>
                        </div>

                        <!-- Filters -->
                        <div class="mb-4 flex flex-wrap gap-4">
                            <div class="flex-1 min-w-[200px]">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Search by period, statistic, creator..."
                                    class="w-full border-gray-300 focus:border-maiic-500 focus:ring-maiic-500 rounded-md shadow-sm"
                                />
                            </div>
                            <div class="min-w-[200px]">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Filter by Statistic</label>
                                <select
                                    v-model="filterStatistic"
                                    class="w-full border-gray-300 focus:border-maiic-500 focus:ring-maiic-500 rounded-md shadow-sm"
                                >
                                    <option value="">All Statistics</option>
                                    <option value="inflation">Inflation</option>
                                    <option value="exchange_rates">Exchange Rates</option>
                                    <option value="credit_index">Credit Index</option>
                                    <option value="unemployment_rate">Unemployment Rate</option>
                                    <option value="interest_rates">Interest Rates</option>
                                </select>
                            </div>
                        </div>

                        <!-- Calculations Table -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Reporting Period
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Scenario Set
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Economic Statistic
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            PD Proxy
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Forecasting
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Regression
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Created By
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Created At
                                        </th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <template v-for="param in filteredParameters" :key="param.id">
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ param.reporting_period }}
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    {{ getAdjustmentCount(param.reporting_period) }} adjustments
                                                </div>
                                            </td>
                                            <td class="px-4 py-4">
                                                <div class="text-sm text-gray-900">{{ param.scenario_set_name }}</div>
                                                <div class="text-xs text-gray-500 mt-1">
                                                    <div v-for="scenario in param.scenarios" :key="scenario.name" class="flex justify-between gap-2">
                                                        <span>{{ scenario.name }}:</span>
                                                        <span class="font-medium">{{ scenario.probability }}%</span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4">
                                                <div class="text-sm text-gray-900">
                                                    {{ formatStatistic(param.economic_data_statistic) }}
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    Base: {{ param.base_macro_data_value }}
                                                </div>
                                            </td>
                                            <td class="px-4 py-4">
                                                <div class="text-sm text-gray-900">
                                                    {{ formatStatistic(param.pd_proxy_statistic) }}
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    Base: {{ param.base_pd_proxy_value }}%
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    {{ param.number_of_forecasting_periods }} periods
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    {{ param.forecasting_period_length_months }} months each
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    Base: {{ param.base_forecast_period }}
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="text-xs text-gray-600">
                                                    <div>Slope: <span class="font-medium">{{ param.regression_slope }}</span></div>
                                                    <div>Intercept: <span class="font-medium">{{ param.regression_intercept }}</span></div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">{{ param.created_by_name }}</div>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">{{ formatDate(param.created_at) }}</div>
                                            </td>
                                            <td class="px-4 py-4 whitespace-nowrap text-sm">
                                                <button
                                                    @click="toggleDetails(param.id)"
                                                    class="text-maiic-600 hover:text-maiic-900 font-medium"
                                                >
                                                    {{ expandedRows.includes(param.id) ? 'Hide' : 'Details' }}
                                                </button>
                                            </td>
                                        </tr>
                                        <!-- Expanded Row Details -->
                                        <tr v-if="expandedRows.includes(param.id)">
                                            <td colspan="9" class="px-4 py-4 bg-gray-50">
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    <!-- Loan Book Statistics -->
                                                    <div class="bg-white p-4 rounded-lg border border-gray-200">
                                                        <h4 class="font-semibold text-gray-900 mb-3">Loan Book Impact</h4>
                                                        <template v-if="getLoanBookStats(param.reporting_period)">
                                                            <div class="space-y-2 text-sm">
                                                                <div class="flex justify-between">
                                                                    <span class="text-gray-600">Total Loans Updated:</span>
                                                                    <span class="font-medium">{{ getLoanBookStats(param.reporting_period).total_loans }}</span>
                                                                </div>
                                                                <div class="flex justify-between">
                                                                    <span class="text-gray-600">Average FLI Adjustment:</span>
                                                                    <span class="font-medium" :class="getFliAdjClass(getLoanBookStats(param.reporting_period).avg_fli_adj)">
                                                                        {{ formatPercent(getLoanBookStats(param.reporting_period).avg_fli_adj) }}
                                                                    </span>
                                                                </div>
                                                                <div class="flex justify-between">
                                                                    <span class="text-gray-600">Min FLI Adjustment:</span>
                                                                    <span class="font-medium" :class="getFliAdjClass(getLoanBookStats(param.reporting_period).min_fli_adj)">
                                                                        {{ formatPercent(getLoanBookStats(param.reporting_period).min_fli_adj) }}
                                                                    </span>
                                                                </div>
                                                                <div class="flex justify-between">
                                                                    <span class="text-gray-600">Max FLI Adjustment:</span>
                                                                    <span class="font-medium" :class="getFliAdjClass(getLoanBookStats(param.reporting_period).max_fli_adj)">
                                                                        {{ formatPercent(getLoanBookStats(param.reporting_period).max_fli_adj) }}
                                                                    </span>
                                                                </div>
                                                            </div>
                                                        </template>
                                                        <div v-else class="text-sm text-gray-500 italic">
                                                            No loan book updates found
                                                        </div>
                                                    </div>

                                                    <!-- Parameter Summary -->
                                                    <div class="bg-white p-4 rounded-lg border border-gray-200">
                                                        <h4 class="font-semibold text-gray-900 mb-3">Parameter Summary</h4>
                                                        <div class="space-y-2 text-sm">
                                                            <div>
                                                                <span class="text-gray-600">Calculation ID:</span>
                                                                <span class="font-medium ml-2">{{ param.id }}</span>
                                                            </div>
                                                            <div>
                                                                <span class="text-gray-600">Total Forecast Months:</span>
                                                                <span class="font-medium ml-2">
                                                                    {{ param.number_of_forecasting_periods * param.forecasting_period_length_months }} months
                                                                </span>
                                                            </div>
                                                            <div>
                                                                <span class="text-gray-600">Regression Formula:</span>
                                                                <div class="mt-1 p-2 bg-gray-100 rounded font-mono text-xs">
                                                                    PD = {{ param.regression_slope }} × Economic_Data + {{ param.regression_intercept }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>

                            <!-- Empty State -->
                            <div v-if="filteredParameters.length === 0" class="text-center py-12">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No calculations found</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    {{ searchQuery || filterStatistic ? 'Try adjusting your filters' : 'Get started by creating a new calculation' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    parameters: Array,
    adjustmentCounts: Object,
    loanBookStats: Object,
});

const searchQuery = ref('');
const filterStatistic = ref('');
const expandedRows = ref([]);

// Computed properties
const uniqueReportingPeriods = computed(() => {
    return new Set(props.parameters.map(p => p.reporting_period)).size;
});

const totalAdjustments = computed(() => {
    return Object.values(props.adjustmentCounts || {}).reduce((sum, count) => sum + count, 0);
});

const filteredParameters = computed(() => {
    let filtered = props.parameters;

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(param =>
            param.reporting_period?.toLowerCase().includes(query) ||
            param.scenario_set_name?.toLowerCase().includes(query) ||
            param.economic_data_statistic?.toLowerCase().includes(query) ||
            param.pd_proxy_statistic?.toLowerCase().includes(query) ||
            param.created_by_name?.toLowerCase().includes(query)
        );
    }

    if (filterStatistic.value) {
        filtered = filtered.filter(param =>
            param.economic_data_statistic === filterStatistic.value
        );
    }

    return filtered;
});

// Methods
const toggleDetails = (id) => {
    const index = expandedRows.value.indexOf(id);
    if (index > -1) {
        expandedRows.value.splice(index, 1);
    } else {
        expandedRows.value.push(id);
    }
};

const formatStatistic = (stat) => {
    if (!stat) return 'N/A';
    return stat.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const formatDate = (dateStr) => {
    if (!dateStr) return 'N/A';
    const date = new Date(dateStr);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
};

const formatPercent = (value) => {
    if (value === null || value === undefined) return 'N/A';
    return `${parseFloat(value).toFixed(4)}%`;
};

const getAdjustmentCount = (reportingPeriod) => {
    return props.adjustmentCounts?.[reportingPeriod] || 0;
};

const getLoanBookStats = (reportingPeriod) => {
    return props.loanBookStats?.[reportingPeriod];
};

const getFliAdjClass = (value) => {
    if (value === null || value === undefined) return '';
    const numValue = parseFloat(value);
    if (numValue > 0) return 'text-red-600';
    if (numValue < 0) return 'text-maiic-600';
    return 'text-gray-600';
};
</script>
