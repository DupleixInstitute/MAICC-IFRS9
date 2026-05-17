<script setup>
import { reactive } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    portfolios: Array,
    selectedPortfolio: [String, Number],
    selectedStartPeriod: String,
    selectedEndPeriod: String,
    selectedMode: String,
});

const form = reactive({
    portfolio_id: props.selectedPortfolio || '',
    start_period: props.selectedStartPeriod || '',
    end_period: props.selectedEndPeriod || '',
    mode: props.selectedMode || 'summary',
});

const downloadReport = () => {
    window.open(route('reports.disbursement-report', { ...form, export: 1 }), '_blank');
};
</script>

<template>
    <Head title="Disbursement Report" />

    <AppLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Disbursement Report</h2>
                <Link :href="route('reports.index')" class="inline-flex items-center rounded-md bg-gray-600 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
                    Back to Reports
                </Link>
            </div>
        </template>

        <div class="p-6 bg-gray-100 min-h-screen">
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Export Parameters</h3>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Portfolio</label>
                        <select v-model="form.portfolio_id" class="mt-1 block w-full border-gray-300 rounded-md">
                            <option value="">All portfolios</option>
                            <option v-for="portfolio in portfolios" :key="portfolio.value" :value="portfolio.value">{{ portfolio.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Start Period</label>
                        <input v-model="form.start_period" type="month" class="mt-1 block w-full border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">End Period</label>
                        <input v-model="form.end_period" type="month" class="mt-1 block w-full border-gray-300 rounded-md">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Mode</label>
                        <select v-model="form.mode" class="mt-1 block w-full border-gray-300 rounded-md">
                            <option value="summary">Summary</option>
                            <option value="detailed">Detailed</option>
                        </select>
                    </div>
                </div>
                <div class="px-6 pb-6 flex justify-end">
                    <button @click="downloadReport" :disabled="!form.portfolio_id || !form.start_period || !form.end_period" class="px-4 py-2 bg-blue-600 text-white rounded-md disabled:opacity-50">Download CSV</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
