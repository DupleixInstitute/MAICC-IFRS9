<template>
    <app-layout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Calculation Details #{{ calculation.id }}
                    <HelpManual />
                </h2>

                <div class="flex space-x-2 mt-2">
                    <Link
                        :href="route('lgd-calculations.index')"
                        class="inline-flex items-center bg-gray-900 hover:bg-gray-700 text-white px-3 py-1 rounded-lg shadow-md transition duration-300"
                    >
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back
                    </Link>

                    <button
                        v-if="calculation.status === 'completed'"
                        @click="generateReport"
                        class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow-md transition duration-300"
                    >
                       <i class="fas fa-file-archive mr-2"></i>
                        Export Report
                    </button>
                </div>
            </div>
        </template>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-500">Status</div>
                <div class="mt-2">
                    <span class="px-3 py-1 rounded-full text-sm font-semibold" :class="statusClass(calculation.status)">
                        {{ calculation.status }}
                    </span>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-500">Duration</div>
                <div class="text-lg font-semibold mt-2">{{ formatDuration(calculation.duration_seconds) }}</div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-500">Period</div>
                <div class="text-lg font-semibold mt-2">
                    {{ formatDate(calculation.start_period) }} - {{ formatDate(calculation.end_period) }}
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-500">Portfolio</div>
                <div class="text-lg font-semibold mt-2">{{ calculation.portfolio?.name || 'N/A' }}</div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-500">Contracts Processed</div>
                <div class="text-2xl font-bold text-blue-600 mt-2">{{ calculation.total_contracts_processed.toLocaleString() }}</div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-500">Records Generated</div>
                <div class="text-2xl font-bold text-green-600 mt-2">{{ calculation.total_records_generated.toLocaleString() }}</div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-500">Total Payments</div>
                <div class="text-2xl font-bold text-purple-600 mt-2">{{ formatCurrency(calculation.total_payments_detected) }}</div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-500">Cured Contracts</div>
                <div class="text-2xl font-bold text-orange-600 mt-2">{{ calculation.total_cured_contracts }}</div>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-500">Defaulted Amount</div>
                <div class="text-xl font-semibold mt-2">{{ formatCurrency(calculation.total_defaulted_amount) }}</div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-700">Payment-Based Cure Rate (Recommended)</div>
                <span class="text-xs text-gray-700">Contracts with actual payments</span>
                <div class="text-xl font-semibold mt-2">{{ recommendedCureRate }}</div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-700">Balance-Based Cure Rate</div>
                <span class="text-xs text-gray-700">Contracts with reduced balances</span>
                <div class="text-xl font-semibold mt-2">{{ balanceBasedCureRate }}</div>
            </div>
        </div>

        <!-- Recovery Rate -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-500">Recovered Amount Rate</div>
                 <span class="text-xs text-gray-700">Stage: 1, 2 & 3</span>
                <div class="text-xl font-semibold mt-2">{{ summary.recovery_rate }}</div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-sm text-gray-700">Stage-Based Cure Rate (Contract Count)</div>
                <span class="text-xs text-gray-700">Stage: 3 <i class="fas fa-arrow-right"></i> Stage: 1 & 2 </span>
                <div class="text-xl font-semibold mt-2">{{ summary.cure_rate }}</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mt-6 border-b border-gray-200">
            <nav class="flex space-x-4">
                <button @click="activeTab = 'payments'"
                        :class="{'border-b-2 border-blue-500 text-blue-600': activeTab === 'payments'}"
                        class="px-3 py-2 text-sm font-medium">
                    Recent Payments
                </button>
                <button @click="activeTab = 'contracts'"
                        :class="{'border-b-2 border-blue-500 text-blue-600': activeTab === 'contracts'}"
                        class="px-3 py-2 text-sm font-medium">
                    Contracts
                </button>
                <button @click="activeTab = 'metadata'"
                        :class="{'border-b-2 border-blue-500 text-blue-600': activeTab === 'metadata'}"
                        class="px-3 py-2 text-sm font-medium">
                    Metadata
                </button>
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="mt-4">
            <!-- Payments Tab -->
            <div v-if="activeTab === 'payments'" class="overflow-x-auto">
                <div class="bg-white shadow-md rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contract</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reporting Period</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment Period</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Previous Balance</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Current Balance</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Payment</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Stage</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Cured</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Months</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="record in recentRecords" :key="record.id">
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-mono">{{ record.contract_id }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">{{formatPeriod(record.reporting_period)}}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">{{formatPeriod(record.payment_period)}}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-right">{{ formatCurrency(record.starting_balance) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-right">{{ formatCurrency(record.ending_balance) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-bold" :class="paymentClass(record.payment_amount)">
                                    {{ formatCurrency(record.payment_amount) }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <span class="px-2 py-1 rounded-full text-xs" :class="paymentTypeClass(record.payment_type)">
                                        {{ record.payment_type }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <span class="px-2 py-1 rounded-full text-xs" :class="stageClass(record.ifrs9_stage)">
                                        Stage {{ record.ifrs9_stage }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <span v-if="record.is_cured" class="text-green-600">
                                        <i class="fas fa-check-circle"></i>
                                    </span>
                                    <span v-else class="text-gray-400">
                                        <i class="fas fa-times-circle"></i>
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-right">{{ record.months_since_default }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Contracts Tab -->
            <div v-if="activeTab === 'contracts'" class="bg-white shadow-md rounded-lg p-4">
                <!-- Summary Stats -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-blue-50 rounded-lg p-4">
                        <div class="text-sm text-blue-600 font-medium">Total Contracts</div>
                        <div class="text-2xl font-bold text-blue-800">{{ stats.unique_contracts.toLocaleString() }}</div>
                    </div>

                    <div class="bg-green-50 rounded-lg p-4">
                        <div class="text-sm text-green-600 font-medium">Cured Contracts</div>
                        <div class="text-2xl font-bold text-green-800">{{ stats.cured_contracts.toLocaleString() }}</div>
                    </div>

                    <div class="bg-yellow-50 rounded-lg p-4">
                        <div class="text-sm text-yellow-600 font-medium">Defaulted Contracts</div>
                        <div class="text-2xl font-bold text-yellow-800">{{ (stats.unique_contracts - stats.cured_contracts).toLocaleString() }}</div>
                    </div>

                    <div class="bg-red-50 rounded-lg p-4">
                        <div class="text-sm text-red-600 font-medium">Cure Rate</div>
                        <div class="text-2xl font-bold text-red-800">{{ summary.cure_rate }}</div>
                    </div>
                </div>

                <!-- Search and Filters -->
                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Search Contract</label>
                            <input type="text" v-model="contractSearch" placeholder="Enter Contract ID..."
                                   class="w-full border-gray-300 rounded-md shadow-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Filter by Stage</label>
                            <select v-model="stageFilter" class="w-full border-gray-300 rounded-md shadow-sm">
                                <option value="">All Stages</option>
                                <option value="1">Stage 1</option>
                                <option value="2">Stage 2</option>
                                <option value="3">Stage 3</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Filter by Status</label>
                            <select v-model="statusFilter" class="w-full border-gray-300 rounded-md shadow-sm">
                                <option value="">All Status</option>
                                <option value="cured">Cured</option>
                                <option value="not_cured">Not Cured</option>
                                <option value="with_payments">With Payments</option>
                                <option value="no_payments">No Payments</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Contracts Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contract ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Current Stage</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Starting Balance</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Current Balance</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Payments</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Balance Reduction</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Months in Default</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="contract in filteredContracts" :key="contract.contract_id"
                                class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3 whitespace-nowrap text-sm font-mono">{{ contract.contract_id }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm">
                                    <span class="px-2 py-1 rounded-full text-xs" :class="stageClass(contract.current_stage)">
                                        Stage {{ contract.current_stage }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-right">{{ formatCurrency(contract.starting_balance) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-right">{{ formatCurrency(contract.current_balance) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-right font-bold text-green-600">
                                    {{ formatCurrency(contract.total_payments) }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-right">
                                    <span :class="contract.balance_reduction > 0 ? 'text-green-600' : 'text-gray-500'">
                                        {{ formatCurrency(contract.balance_reduction) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-center">
                                    <span v-if="contract.is_cured" class="text-green-600 font-medium">
                                        <i class="fas fa-check-circle mr-1"></i>Cured
                                    </span>
                                    <span v-else class="text-red-600 font-medium">
                                        <i class="fas fa-times-circle mr-1"></i>Not Cured
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-center text-sm">{{ contract.months_in_default }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Empty State -->
                    <div v-if="filteredContracts.length === 0" class="text-center py-8">
                        <div class="text-gray-500">
                            <i class="fas fa-search text-4xl mb-4"></i>
                            <p class="text-lg font-medium">No contracts found</p>
                            <p class="text-sm">Try adjusting your search or filter criteria</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Metadata Tab -->
            <div v-if="activeTab === 'metadata'" class="bg-white shadow-md rounded-lg p-6">
                <dl class="grid grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm text-gray-500">Calculation ID</dt>
                        <dd class="text-sm font-medium">{{ calculation.id }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Parent Calculation</dt>
                        <dd class="text-sm font-medium">
                            <Link v-if="calculation.parent_calculation_id"
                                  :href="route('lgd-calculations.show', calculation.parent_calculation_id)"
                                  class="text-blue-600 hover:underline">
                                #{{ calculation.parent_calculation_id }}
                            </Link>
                            <span v-else>-</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Triggered By</dt>
                        <dd class="text-sm font-medium">{{ calculation.triggered_by?.name || 'System' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Trigger Source</dt>
                        <dd class="text-sm font-medium">{{ calculation.trigger_source }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">Start Time</dt>
                        <dd class="text-sm font-medium">{{ formatDateTime(calculation.start_time) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-gray-500">End Time</dt>
                        <dd class="text-sm font-medium">{{ formatDateTime(calculation.end_time) }}</dd>
                    </div>
                    <div v-if="calculation.recalculation_reason">
                        <dt class="text-sm text-gray-500">Recalculation Reason</dt>
                        <dd class="text-sm font-medium">{{ calculation.recalculation_reason }}</dd>
                    </div>
                    <div v-if="calculation.error_message">
                        <dt class="text-sm text-red-500">Error Message</dt>
                        <dd class="text-sm text-red-600">{{ calculation.error_message }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Report Modal -->
        <ReportModal
            v-if="showReportModal"
            :show="showReportModal"
            :portfolios="[calculation.portfolio]"
            :default-portfolio="calculation.portfolio_group"
            :default-start="calculation.start_period"
            :default-end="calculation.end_period"
            :calculation-id="calculation.id"
            @close="showReportModal = false"
            @generate="handleReportGeneration"
        />
    </app-layout>
</template>

<script>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpManual from '@/Components/HelpManual.vue';
import ReportModal from './Partials/ReportModal.vue';

export default {
    components: {
        AppLayout,
        HelpManual,
        ReportModal
    },
    props: {
        calculation: {
            type: Object,
            required: true
        },
        stats: {
            type: Object,
            required: true
        },
        recentRecords: {
            type: Array,
            default: () => []
        },
        summary: {
            type: Object,
            required: true
        },
        recommendedCureRate: {
            type: String,
            default: null
        },
        balanceBasedCureRate: {
            type: String,
            default: null
        }
    },
    setup(props) {
        console.log('=== Props Debug ===');
        console.log('Calculation:', props.calculation);
        console.log('Recent Records:', props.recentRecords);
        console.log('Recent Records Count:', props.recentRecords?.length || 0);
        console.log('===================');

        const activeTab = ref('payments');
        const showReportModal = ref(false);
        const contractSearch = ref('');
        const stageFilter = ref('');
        const statusFilter = ref('');

        const formatDate = (dateStr) => {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { year: 'numeric', month: '2-digit' });
        };

        const formatDateTime = (dateStr) => {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            return date.toLocaleString('en-US', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        };

        const formatPeriod = (dateString) => {
            if (!dateString) return '';
            const [year, month] = dateString.split('-');

            const date = new Date(year, month - 1); // avoids timezone issues

            return `${year}-${month}`;
        };

        const formatDuration = (seconds) => {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;

            const formattedSecs = String(secs).padStart(2, '0');

            return `${mins} mins ${formattedSecs} secs`;
        };

        const formatCurrency = (value) => {
            if (!value && value !== 0) return 'E0.00';
            return new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(value).replace(/,/g, ' ');
        };

        const statusClass = (status) => {
            const classes = {
                completed: 'bg-green-100 text-green-700',
                processing: 'bg-yellow-100 text-yellow-700',
                pending: 'bg-gray-100 text-gray-700',
                failed: 'bg-red-100 text-red-700'
            };
            return classes[status] || 'bg-gray-100 text-gray-700';
        };

        const paymentClass = (amount) => {
            return amount > 0 ? 'text-green-600' : 'text-gray-500';
        };

        const paymentTypeClass = (type) => {
            const classes = {
                full: 'bg-green-100 text-green-700',
                partial: 'bg-yellow-100 text-yellow-700',
                none: 'bg-gray-100 text-gray-700'
            };
            return classes[type] || 'bg-gray-100 text-gray-700';
        };

        const stageClass = (stage) => {
            const classes = {
                '1': 'bg-green-100 text-green-700',
                '2': 'bg-yellow-100 text-yellow-700',
                '3': 'bg-red-100 text-red-700'
            };
            return classes[stage] || 'bg-gray-100 text-gray-700';
        };

        const generateReport = () => {
            showReportModal.value = true;
        };

        const handleReportGeneration = (data) => {
            showReportModal.value = false;
        };

        // Computed property for filtered contracts
        const filteredContracts = computed(() => {
            // For now, return empty array since we don't have contracts data
            // This would need to be implemented with actual data fetching
            return [];
        });

        return {
            activeTab,
            showReportModal,
            contractSearch,
            stageFilter,
            statusFilter,
            filteredContracts,
            formatDate,
            formatDateTime,
            formatPeriod,
            formatDuration,
            formatCurrency,
            statusClass,
            paymentClass,
            paymentTypeClass,
            stageClass,
            generateReport,
            handleReportGeneration
        };
    }
}
</script>
