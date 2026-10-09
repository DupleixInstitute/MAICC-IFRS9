<script setup>
import { reactive, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import BackToReports from './Partials/BackToReports.vue';
import DownloadButton from './Partials/DownloadButton.vue';

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

const ready = computed(() => form.start_period && form.end_period && form.start_period <= form.end_period);
const csvUrl = computed(() => ready.value ? route('reports.loan-book-export', { ...form, export: 1 }) : '');
</script>

<template>
    <AppLayout title="Loan book export">
        <template #header>
            <BackToReports tab="exports"/>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Loan book export</h2>
            <p class="mt-0.5 text-sm text-gray-500">The loan book between two months, as a summary by month or loan by loan, as CSV data.</p>
        </template>
        <template #actions>
            <DownloadButton format="CSV" label="Download CSV" :href="csvUrl" :disabled="!ready" title="Choose the start and end months first"/>
        </template>

        <div class="maiic-filterbar grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="maiic-flabel" for="lbe-portfolio">Portfolio</label>
                <select id="lbe-portfolio" v-model="form.portfolio_id" class="maiic-select">
                    <option value="">All portfolios</option>
                    <option v-for="portfolio in portfolios" :key="portfolio.value" :value="portfolio.value">{{ portfolio.label }}</option>
                </select>
            </div>
            <div>
                <label class="maiic-flabel" for="lbe-start">Start month</label>
                <input id="lbe-start" v-model="form.start_period" type="month" class="maiic-input">
            </div>
            <div>
                <label class="maiic-flabel" for="lbe-end">End month</label>
                <input id="lbe-end" v-model="form.end_period" type="month" class="maiic-input">
                <p v-if="form.start_period && form.end_period && form.start_period > form.end_period" class="mt-1 text-xs text-red-600">The end month must not be before the start month.</p>
            </div>
            <div>
                <label class="maiic-flabel" for="lbe-mode">Detail</label>
                <select id="lbe-mode" v-model="form.mode" class="maiic-select">
                    <option value="summary">Summary by month</option>
                    <option value="detailed">Loan by loan</option>
                </select>
            </div>
        </div>
        <p class="text-xs text-gray-500">Choose the months, then Download CSV at the top right. The file opens in Excel.</p>
    </AppLayout>
</template>
