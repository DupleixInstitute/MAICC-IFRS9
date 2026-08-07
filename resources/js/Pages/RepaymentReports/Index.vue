<template>
    <app-layout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Payment Tracking Calculations
                    <HelpManual />
                </h2>

                <div class="flex space-x-2 mt-2">
                    <!-- New Calculation -->
                    <Link
                        :href="route('lgd-calculations.create')"
                        class="inline-flex items-center bg-maiic-600 hover:bg-maiic-700 text-white px-4 py-2 rounded-lg shadow-md transition duration-300"
                    >
                        <i class="fa fa-calculator mr-2" aria-hidden="true"></i>
                        New Calculation
                    </Link>

                    <!-- Filters -->
                    <button
                        @click="showFilters = !showFilters"
                        class="inline-flex items-center bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg shadow-md transition duration-300"
                    >
                        <i class="fas fa-filter mr-2"></i>
                        Filters
                    </button>

                    <!-- Refresh -->
                    <button
                        @click="refreshData"
                        class="inline-flex items-center bg-maiic-600 hover:bg-maiic-700 text-white px-4 py-2 rounded-lg shadow-md transition duration-300"
                    >
                        <i class="fas fa-sync-alt mr-2"></i>
                        Refresh
                    </button>
                </div>
            </div>
        </template>

        <!-- Filters Panel -->
        <div v-if="showFilters" class="bg-white shadow-md rounded-lg p-4 mt-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Portfolio</label>
                    <select v-model="filters.portfolio_id" class="w-full border-gray-300 rounded-md shadow-sm">
                        <option value="">All Portfolios</option>
                        <option v-for="p in portfolios" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select v-model="filters.status" class="w-full border-gray-300 rounded-md shadow-sm">
                        <option value="">All Status</option>
                        <option value="completed">Completed</option>
                        <option value="processing">Processing</option>
                        <option value="pending">Pending</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                    <input type="date" v-model="filters.date_from" class="w-full border-gray-300 rounded-md shadow-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                    <input type="date" v-model="filters.date_to" class="w-full border-gray-300 rounded-md shadow-sm">
                </div>
            </div>
            <div class="flex justify-end mt-4 space-x-2">
                <button @click="clearFilters" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Reset</button>
                <button @click="applyFilters" class="px-4 py-2 bg-maiic-600 text-white rounded hover:bg-maiic-700">Apply</button>
            </div>
        </div>

        <!-- Calculations Table -->
        <div class="overflow-x-auto mt-6">
            <div class="bg-white shadow-md rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Portfolio</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Period</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Duration</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Contracts</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Records</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Payments</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Cured</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Source</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-if="loading">
                            <td colspan="12" class="px-6 py-4 text-center text-gray-500">Loading data...</td>
                        </tr>
                        <tr v-else-if="calculations?.data?.length === 0">
                            <td colspan="12" class="px-6 py-4 text-center text-gray-500">No calculations found</td>
                        </tr>
                        <tr v-for="calc in calculations.data" :key="calc.id" class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">#{{ calc.id }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">{{ calc.portfolio?.name || 'N/A' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                {{ formatDate(calc.start_period) }} - {{ formatDate(calc.end_period) }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                <span class="px-2 py-1 rounded-full text-xs font-semibold" :class="statusClass(calc.status, calc.has_been_recalculated, calc.is_recalculation)">
                                    {{ getStatusLabel(calc.status, calc.has_been_recalculated, calc.is_recalculation) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right text-sm text-gray-600">
                                {{formatDuration(calc.duration_seconds) }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right text-sm text-gray-600">
                                {{ calc.total_contracts_processed.toLocaleString() }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right text-sm text-gray-600">
                                {{ calc.total_records_generated.toLocaleString() }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right text-sm text-gray-600">
                                {{ formatCurrency(calc.total_payments_detected) }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right text-sm text-gray-600">
                                {{ calc.total_cured_contracts }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                <span class="px-2 py-1 rounded-full text-xs" :class="sourceClass(calc.trigger_source)">
                                    {{ calc.trigger_source }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                {{ formatDateTime(calc.created_at) }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                <div class="flex justify-center space-x-2">
                                    <!-- View Details -->
                                    <Link :href="route('lgd-calculations.show', calc.id)"
                                          class="text-maiic-600 hover:text-maiic-800" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </Link>

                                    <!-- Recalculate -->
                                    <button v-if="calc.status === 'completed'"
                                            @click="recalculate(calc.id)"
                                            class="text-maiic-600 hover:text-maiic-800"
                                            title="Recalculate">
                                        <i class="fas fa-redo-alt"></i>
                                    </button>

                                    <!-- Cancel (if pending/processing) -->
                                    <button v-if="['pending', 'processing'].includes(calc.status)"
                                            @click="cancelCalculation(calc.id)"
                                            class="text-amber-600 hover:text-amber-800"
                                            title="Cancel">
                                        <i class="fas fa-times-circle"></i>
                                    </button>

                                    <!-- Export -->
                                    <a v-if="calc.status === 'completed'"
                                       :href="route('lgd-calculations.export', calc.id)"
                                       class="text-maiic-600 hover:text-maiic-800"
                                       title="Export">
                                        <i class="fas fa-download"></i>
                                    </a>

                                    <!-- Delete -->
                                    <button v-if="calc.status !== 'processing'"
                                            @click="deleteCalculation(calc.id)"
                                            class="text-red-600 hover:text-red-800"
                                            title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>

                                    <!-- Compare -->
                                    <!-- <button @click="selectForComparison(calc)"
                                            :class="{'text-maiic-600': !isSelectedForComparison(calc.id), 'text-maiic-600': isSelectedForComparison(calc.id)}"
                                            class="hover:text-maiic-800"
                                            :title="isSelectedForComparison(calc.id) ? 'Selected' : 'Select for comparison'">
                                        <i class="fas fa-balance-scale"></i>
                                    </button> -->
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="px-4 py-3 border-t">
                    <pagination :links="calculations?.links || []" />
                </div>
            </div>
        </div>

        <!-- Comparison Bar (when 2 items selected) -->
        <div v-if="comparisonItems.length === 2" class="fixed bottom-4 right-4 bg-white shadow-lg rounded-lg p-4 border-2 border-maiic-500">
            <p class="text-sm mb-2">Comparing: #{{ comparisonItems[0].id }} and #{{ comparisonItems[1].id }}</p>
            <div class="flex space-x-2">
                <Link :href="route('lgd-calculations.compare', {id1: comparisonItems[0].id, id2: comparisonItems[1].id})"
                      class="px-3 py-1 bg-maiic-600 text-white rounded text-sm hover:bg-maiic-700">
                    Compare Now
                </Link>
                <button @click="clearComparison" class="px-3 py-1 bg-gray-300 rounded text-sm hover:bg-gray-400">
                    Clear
                </button>
            </div>
        </div>

        <!-- Report Modal (for generating reports) -->
        <ReportModal
            :show="showReportModal"
            :portfolios="portfolios"
            @close="showReportModal = false"
            @generate="handleReportGeneration"
        />
    </app-layout>
</template>

<script>
import { ref, reactive } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpManual from '@/Components/HelpManual.vue';
import Pagination from '@/Components/Pagination.vue';
import ReportModal from './Partials/ReportModal.vue';

export default {
    components: {
        AppLayout,
        HelpManual,
        Pagination,
        ReportModal
    },
    props: {
        calculations: {
            type: Object,
            required: true
        },
        portfolios: {
            type: Array,
            default: () => []
        },
        filters: {
            type: Object,
            default: () => ({})
        }
    },
    setup(props) {
        const loading = ref(false);
        const showFilters = ref(false);
        const showReportModal = ref(false);
        const comparisonItems = ref([]);

        // Debug: Log the calculations data to check if recalculated status is present
        console.log('Calculations data:', props.calculations);
        if (props.calculations?.data) {
            props.calculations.data.forEach(calc => {
                console.log(`Calc #${calc.id}:`, {
                    status: calc.status,
                    has_been_recalculated: calc.has_been_recalculated,
                    is_recalculation: calc.is_recalculation,
                    parent_calculation_id: calc.parent_calculation_id
                });
            });
        }

        const filters = reactive({
            portfolio_id: props.filters.portfolio_id || '',
            status: props.filters.status || '',
            date_from: props.filters.date_from || '',
            date_to: props.filters.date_to || ''
        });

        const formatDate = (dateStr) => {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { year: 'numeric', month: '2-digit' });
        };

        const formatDateTime = (dateStr) => {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        };

        const formatCurrency = (value) => {
            if (!value && value !== 0) return 'E0.00';
            return new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(value).replace(/,/g, ' ');
        };

        const statusClass = (status, hasBeenRecalculated = false, isRecalculation = false) => {
            const classes = {
                completed: hasBeenRecalculated ? 'bg-amber-100 text-amber-700' : 'bg-maiic-100 text-maiic-700',
                processing: 'bg-amber-100 text-amber-700',
                pending: 'bg-gray-100 text-gray-700',
                failed: 'bg-red-100 text-red-700'
            };
            return classes[status] || 'bg-gray-100 text-gray-700';
        };

        const getStatusLabel = (status, hasBeenRecalculated = false, isRecalculation = false) => {
            let label = status.charAt(0).toUpperCase() + status.slice(1);

            if (status === 'completed' && hasBeenRecalculated) {
                label = 'Completed (Recalculated)';
            } else if (isRecalculation) {
                label = label + ' (Recalculation)';
            }

            return label;
        };

        const sourceClass = (source) => {
            const classes = {
                manual: 'bg-maiic-100 text-maiic-700',
                scheduled: 'bg-maiic-100 text-maiic-700',
                api: 'bg-amber-100 text-amber-700'
            };
            return classes[source] || 'bg-gray-100 text-gray-700';
        };

        const formatDuration = (seconds) => {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;

            const formattedSecs = String(secs).padStart(2, '0');

            return `${mins} mins ${formattedSecs} secs`;
        };

        const applyFilters = () => {
            router.get(route('lgd-calculations.index'), filters, {
                preserveState: true,
                preserveScroll: true
            });
        };

        const clearFilters = () => {
            filters.portfolio_id = '';
            filters.status = '';
            filters.date_from = '';
            filters.date_to = '';
            applyFilters();
        };

        const refreshData = () => {
            loading.value = true;
            router.get(route('lgd-calculations.index'), filters, {
                preserveState: false,
                preserveScroll: true,
                onFinish: () => {
                    loading.value = false;
                }
            });
        };

        const recalculate = (id) => {
            if (confirm('Create a new recalculation based on this one?')) {
                // Find the calculation data to get required fields
                const calc = props.calculations.data.find(c => c.id === id);
                if (!calc) {
                    alert('Calculation not found');
                    return;
                }

                // Prepare the required form data
                const formData = {
                    portfolio_group: calc.portfolio_group || calc.portfolio?.id,
                    start_period: calc.start_period ? new Date(calc.start_period).toISOString().slice(0, 7) : '',
                    end_period: calc.end_period ? new Date(calc.end_period).toISOString().slice(0, 7) : '',
                    reason: 'User triggered recalculation from list'
                };

                router.post(route('lgd-calculations.recalculate', id), formData, {
                    preserveScroll: true,
                    onSuccess: () => refreshData()
                });
            }
        };

        const cancelCalculation = (id) => {
            if (confirm('Are you sure you want to cancel this calculation?')) {
                router.post(route('lgd-calculations.cancel', id), {}, {
                    preserveScroll: true,
                    onSuccess: () => refreshData()
                });
            }
        };

        const deleteCalculation = (id) => {
            if (confirm('Are you sure you want to delete this calculation and all its records?')) {
                router.delete(route('lgd-calculations.destroy', id), {
                    preserveScroll: true,
                    onSuccess: () => refreshData()
                });
            }
        };

        const selectForComparison = (calc) => {
            const index = comparisonItems.value.findIndex(c => c.id === calc.id);
            if (index >= 0) {
                comparisonItems.value.splice(index, 1);
            } else if (comparisonItems.value.length < 2) {
                comparisonItems.value.push(calc);
            } else {
                alert('You can only compare 2 calculations at a time');
            }
        };

        const isSelectedForComparison = (id) => {
            return comparisonItems.value.some(c => c.id === id);
        };

        const clearComparison = () => {
            comparisonItems.value = [];
        };

        const handleReportGeneration = (data) => {
            showReportModal.value = false;
        };

        return {
            loading,
            showFilters,
            showReportModal,
            comparisonItems,
            filters,
            formatDate,
            formatDuration,
            formatDateTime,
            formatCurrency,
            statusClass,
            getStatusLabel,
            sourceClass,
            applyFilters,
            clearFilters,
            refreshData,
            recalculate,
            cancelCalculation,
            deleteCalculation,
            selectForComparison,
            isSelectedForComparison,
            clearComparison,
            handleReportGeneration
        };
    }
};
</script>
