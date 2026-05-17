<script setup>
import { reactive, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    report: { type: Object, default: null },
    portfolios: { type: Array, default: () => [] },
    periods: { type: Array, default: () => [] },
    selectedPortfolio: { default: '' },
    selectedStartPeriod: { default: '' },
    selectedEndPeriod: { default: '' },
});

const form = reactive({
    portfolio_id: props.selectedPortfolio || '',
    start_period: props.selectedStartPeriod || '',
    end_period: props.selectedEndPeriod || '',
});

const canRun = computed(() => form.portfolio_id && form.start_period && form.end_period);

const onPortfolioChange = () => {
    router.get(route('reports.loan-book-reconciliation'), { portfolio_id: form.portfolio_id }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const generate = () => {
    router.get(route('reports.loan-book-reconciliation'), { ...form, generate: 1 }, {
        preserveState: true,
        preserveScroll: true,
    });
};

const downloadReport = () => {
    window.open(route('reports.loan-book-reconciliation', { ...form, export: 1 }), '_blank');
};

const money = (v) => Number(v || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});
</script>

<template>
    <Head title="Loan Book Reconciliation" />

    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Loan Book Reconciliation Report</h2>
                <Link :href="route('reports.index')" class="inline-flex items-center rounded-md bg-gray-600 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
                    Back to Reports
                </Link>
            </div>
        </template>

        <div class="p-6 bg-gray-100 min-h-screen">
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Reconciliation Parameters</h3>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Portfolio</label>
                        <select v-model="form.portfolio_id" @change="onPortfolioChange" class="mt-1 block w-full border-gray-300 rounded-md">
                            <option value="">Select a portfolio</option>
                            <option v-for="p in portfolios" :key="p.value" :value="p.value">{{ p.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Start Period</label>
                        <select v-model="form.start_period" class="mt-1 block w-full border-gray-300 rounded-md">
                            <option value="">Select period</option>
                            <option v-for="period in periods" :key="'s' + period.value" :value="period.value">{{ period.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">End Period</label>
                        <select v-model="form.end_period" class="mt-1 block w-full border-gray-300 rounded-md">
                            <option value="">Select period</option>
                            <option v-for="period in periods" :key="'e' + period.value" :value="period.value">{{ period.label }}</option>
                        </select>
                    </div>
                </div>

                <div class="px-6 pb-6 flex justify-end gap-3">
                    <button @click="generate" :disabled="!canRun" class="px-4 py-2 bg-blue-600 text-white rounded-md disabled:opacity-50">Generate</button>
                    <button @click="downloadReport" :disabled="!canRun" class="px-4 py-2 bg-green-600 text-white rounded-md disabled:opacity-50">Download CSV</button>
                </div>
            </div>

            <div v-if="report" class="bg-white rounded-lg shadow mt-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">
                        Reconciliation &mdash; {{ report.start_period }} to {{ report.end_period }}
                    </h3>
                </div>
                <div class="p-6 overflow-x-auto">
                    <table class="min-w-full text-sm border">
                        <tbody>
                            <tr>
                                <td class="px-4 py-2 border">Opening Balance</td>
                                <td class="px-4 py-2 border text-right">{{ money(report.opening_balance) }}</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2 border">Add: New Disbursements</td>
                                <td class="px-4 py-2 border text-right">{{ money(report.new_disbursements) }}</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2 border">Less: Repayments</td>
                                <td class="px-4 py-2 border text-right">{{ money(report.repayments) }}</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-2 border">Less: Write-offs</td>
                                <td class="px-4 py-2 border text-right">{{ money(report.write_offs) }}</td>
                            </tr>
                            <tr class="bg-gray-50 font-medium">
                                <td class="px-4 py-2 border">Expected Closing Balance</td>
                                <td class="px-4 py-2 border text-right">{{ money(report.reconciliation.expected_closing_balance) }}</td>
                            </tr>
                            <tr class="bg-gray-50 font-medium">
                                <td class="px-4 py-2 border">Actual Closing Balance</td>
                                <td class="px-4 py-2 border text-right">{{ money(report.reconciliation.actual_closing_balance) }}</td>
                            </tr>
                            <tr class="font-semibold" :class="report.reconciliation.variance == 0 ? 'text-green-700' : 'text-red-700'">
                                <td class="px-4 py-2 border">Variance</td>
                                <td class="px-4 py-2 border text-right">{{ money(report.reconciliation.variance) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
