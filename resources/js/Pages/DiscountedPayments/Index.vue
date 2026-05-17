<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('loss-given-default.index')">Monthly Loss Given Default
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span>
                Discounted Payments - {{ lgd.portfolio_group?.name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- LGD Summary Card -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">LGD Summary</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <p class="text-sm text-gray-500">Period</p>
                                <p class="text-lg font-semibold">{{ formatDate(lgd.start_period) }} - {{ formatDate(lgd.reporting_period) }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Discount Rate Source</p>
                                <p class="text-lg font-semibold capitalize">{{ lgd.discount_rate_source }}</p>
                            </div>
                            <div v-if="lgd.discount_rate_source === 'manual'">
                                <p class="text-sm text-gray-500">Interest Rate</p>
                                <p class="text-lg font-semibold">{{ formatPercentage(lgd.interest_rate) }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Average Days to Repayment</p>
                                <p class="text-lg font-semibold">{{ lgd.averageDaysToRepayments }}</p>
                            </div>
                        </div>

                        <!-- Discounting Summary -->
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <h4 class="text-md font-medium text-gray-900 mb-3">Discounting Results</h4>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div class="bg-blue-50 p-4 rounded-lg">
                                    <p class="text-sm text-blue-600">Total Payments</p>
                                    <p class="text-xl font-bold text-blue-900">{{ formatCurrency(lgd.total_payment) }}</p>
                                </div>
                                <div class="bg-green-50 p-4 rounded-lg">
                                    <p class="text-sm text-green-600">Total Discounted</p>
                                    <p class="text-xl font-bold text-green-900">{{ formatCurrency((lgd.discounted_payment_partly + lgd.discounted_payment_full) || (lgd.total_payment - lgd.discount_loss)) }}</p>
                                </div>
                                <div class="bg-red-50 p-4 rounded-lg">
                                    <p class="text-sm text-red-600">Discount Loss</p>
                                    <p class="text-xl font-bold text-red-900">{{ formatCurrency(lgd.discount_loss) }}</p>
                                </div>
                                <div class="bg-purple-50 p-4 rounded-lg">
                                    <p class="text-sm text-purple-600">Loss Rate</p>
                                    <p class="text-xl font-bold text-purple-900">{{ formatPercentage(lgd.discount_loss / lgd.total_payment) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Discounted Payments Table -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-lg font-medium text-gray-900">Discounted Payment Details</h3>
                            <div class="flex space-x-2">
                                <button @click="exportData" class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-md">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    Export CSV
                                </button>
                            </div>
                        </div>

                        <!-- Search and Filters -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Search Contract</label>
                                <input
                                    type="text"
                                    v-model="search.contract_id"
                                    placeholder="Enter contract ID"
                                    class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                            </div>
                            <!-- <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Payment Type</label>
                                <select v-model="search.payment_type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">All Types</option>
                                    <option value="partial">Partial</option>
                                    <option value="full">Full</option>
                                </select>
                            </div> -->
                        </div>

                        <!-- Data Table -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contract ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reporting Period</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Period</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Amount</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Interest Rate</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Days</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Discounted Amount</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Discount Loss</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rate Source</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-for="payment in discountedPayments.data" :key="payment.id" class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            {{ payment.contract_id }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ formatDate(payment.reporting_period) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {{ formatDate(payment.payment_period) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ formatCurrency(payment.payment_amount) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ formatPercentage(payment.interest_rate) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ payment.discounting_days }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 font-medium">
                                            {{ formatCurrency(payment.discounted_amount) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-medium">
                                            {{ formatCurrency(payment.discounted_loss) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full"
                                                  :class="payment.discount_rate_source === 'manual' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'">
                                                {{ payment.discount_rate_source }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div v-if="discountedPayments.data.length > 0" class="mt-6">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-700">
                                    Showing
                                    <span class="font-medium">{{ discountedPayments.from }}</span>
                                    to
                                    <span class="font-medium">{{ discountedPayments.to }}</span>
                                    of
                                    <span class="font-medium">{{ discountedPayments.total }}</span>
                                    results
                                </div>

                                <div class="flex items-center space-x-2">
                                    <!-- Previous Button -->
                                    <button
                                        @click="prevPage"
                                        :disabled="discountedPayments.current_page <= 1"
                                        class="px-3 py-1 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        Previous
                                    </button>

                                    <!-- Page Numbers -->
                                    <div class="flex items-center space-x-1">
                                        <span class="px-3 py-1 text-sm bg-indigo-600 text-white rounded-md">
                                            {{ discountedPayments.current_page }}
                                        </span>
                                        <span class="text-sm text-gray-500">of</span>
                                        <span class="text-sm text-gray-700">{{ discountedPayments.last_page }}</span>
                                    </div>

                                    <!-- Next Button -->
                                    <button
                                        @click="nextPage"
                                        :disabled="discountedPayments.current_page >= discountedPayments.last_page"
                                        class="px-3 py-1 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        Next
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div v-if="discountedPayments.data.length === 0" class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No discounted payments found</h3>
                            <p class="mt-1 text-sm text-gray-500">Try adjusting your search criteria.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </app-layout>
</template>

<script>
import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
    components: {
        AppLayout,
    },
    props: {
        lgd: {
            type: Object,
            required: true,
        },
        discountedPayments: {
            type: Object, // Changed from Array to Object for paginated data
            required: true,
        },
        averageDaysToRepayments: {
            type: Number,
            required: true,
        },

        filters: {
            type: Object,
            default: () => ({}),
        },
    },
    setup(props) {
        // Debug: Log the LGD values for Total Discounted calculation
        console.log('LGD Data:', props.lgd);
        console.log('discounted_payment_partly:', props.lgd.discounted_payment_partly);
        console.log('discounted_payment_full:', props.lgd.discounted_payment_full);
        console.log('total_payment:', props.lgd.total_payment);
        console.log('discount_loss:', props.lgd.discount_loss);
        console.log('Average Days to Repayments:', props.averageDaysToRepayments);

        // Debug: Test the calculation
        const sumDiscounted = parseFloat(props.lgd.discounted_payment_partly || 0) + parseFloat(props.lgd.discounted_payment_full || 0);
        const altCalculation = parseFloat(props.lgd.total_payment || 0) - parseFloat(props.lgd.discount_loss || 0);
        console.log('sumDiscounted:', sumDiscounted);
        console.log('altCalculation:', altCalculation);
        console.log('Final result:', sumDiscounted || altCalculation);

        const search = ref({
            contract_id: props.filters.contract_id || '',
            discount_rate_source: props.filters.discount_rate_source || '',
            payment_type: props.filters.payment_type || '',
        });

        // Watch for search changes and update URL with debounce
        let searchTimeout;
        watch(search, (newSearch) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const params = { ...newSearch };

                // Remove empty values
                Object.keys(params).forEach(key => {
                    if (!params[key]) {
                        delete params[key];
                    }
                });

                // Reset to page 1 when filters change
                params.page = 1;

                    router.get(`/loss-given-default/${props.lgd.id}/discounted-payments`, params, {
                    preserveScroll: true,
                    preserveState: true
                });
            }, 500); // 500ms debounce
        }, { deep: true });

        // Pagination methods
        const prevPage = () => {
            const currentPage = props.discountedPayments.current_page;

            if (currentPage > 1) {
                const params = {
                    page: currentPage - 1
                };

                // Add non-empty search values
                Object.keys(search.value).forEach(key => {
                    if (search.value[key]) {
                        params[key] = search.value[key];
                    }
                });

                router.get(`/loss-given-default/${props.lgd.id}/discounted-payments`, params, { preserveScroll: true });
            } else {
            }
        };

        const nextPage = () => {
            const currentPage = props.discountedPayments.current_page;
            const lastPage = props.discountedPayments.last_page;

            if (currentPage < lastPage) {
                const params = {
                    page: currentPage + 1
                };

                // Add non-empty search values
                Object.keys(search.value).forEach(key => {
                    if (search.value[key]) {
                        params[key] = search.value[key];
                    }
                });

                router.get(`/loss-given-default/${props.lgd.id}/discounted-payments`, params, { preserveScroll: true });
            }
        };

        const formatDate = (dateStr) => {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: '2-digit',
            });
        };

        const formatCurrency = (amount) => {
            if (amount === null || amount === undefined || amount === '') return '0.00';
            // Convert to number to handle string concatenation issues
            const numericAmount = parseFloat(amount);
            if (isNaN(numericAmount)) return '0.00';
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'MWK',
            }).format(numericAmount);
        };

        const formatPercentage = (value) => {
            if (value === null || value === undefined) return '0.00%';
            return `${(value * 100).toFixed(2)}%`;
        };

        const exportData = () => {
            const headers = [
                'Contract ID',
                'Reporting Period',
                'Payment Period',
                'Payment Amount',
                'Interest Rate',
                'Discounting Days',
                'Discounted Amount',
                'Discount Loss',
                'Rate Source',
            ];

            const rows = props.discountedPayments.data.map(payment => [
                payment.contract_id,
                formatDate(payment.reporting_period),
                formatDate(payment.payment_period),
                payment.payment_amount,
                formatPercentage(payment.interest_rate),
                payment.discounting_days,
                payment.discounted_amount,
                payment.discounted_loss,
                payment.discount_rate_source,
            ]);

            const csvContent = [
                headers.join(','),
                ...rows.map(row => row.map(cell => `"${cell}"`).join(','))
            ].join('\n');

            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `discounted_payments_${props.lgd.id}_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        };

        return {
            search,
            formatDate,
            formatCurrency,
            formatPercentage,
            exportData,
            prevPage,
            nextPage,
        };
    },
};
</script>
