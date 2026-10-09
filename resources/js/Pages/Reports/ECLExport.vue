<script setup>
import { reactive, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import BackToReports from './Partials/BackToReports.vue'
import DownloadButton from './Partials/DownloadButton.vue'

const props = defineProps({
    portfolios: { type: Array, default: () => [] },
    periods: { type: Array, default: () => [] },
    availableColumns: { type: Object, default: () => ({}) },
    selectedPortfolio: { default: '' },
    selectedPeriod: { default: '' },
    selectedMode: { default: 'summary' },
    selectedColumns: { type: Array, default: () => [] },
})

const form = reactive({
    portfolio_id: props.selectedPortfolio || '',
    reporting_period: props.selectedPeriod || '',
    mode: props.selectedMode || 'summary',
    columns: props.selectedColumns || [],
})

const ready = computed(() => form.portfolio_id && form.reporting_period)
const csvUrl = computed(() => ready.value ? route('reports.ecl-export', { ...form, export: 1 }) : '')

function onPortfolioChange() {
    router.get(route('reports.ecl-export'), { portfolio_id: form.portfolio_id, mode: form.mode }, { preserveState: true, preserveScroll: true })
}
</script>

<template>
    <AppLayout title="ECL export">
        <template #header>
            <BackToReports tab="exports"/>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">ECL export</h2>
            <p class="mt-0.5 text-sm text-gray-500">ECL by stage, or the full loan book with PD, LGD and ECL, for one month, as CSV data.</p>
        </template>
        <template #actions>
            <DownloadButton format="CSV" label="Download CSV" :href="csvUrl" :disabled="!ready" title="Choose the portfolio and month first"/>
        </template>

        <div class="maiic-filterbar grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="maiic-flabel" for="ecl-portfolio">Portfolio</label>
                <select id="ecl-portfolio" v-model="form.portfolio_id" @change="onPortfolioChange" class="maiic-select">
                    <option value="">Select a portfolio</option>
                    <option v-for="p in portfolios" :key="p.value" :value="p.value">{{ p.label }}</option>
                </select>
            </div>
            <div>
                <label class="maiic-flabel" for="ecl-period">Reporting month</label>
                <select id="ecl-period" v-model="form.reporting_period" class="maiic-select" :disabled="!periods.length">
                    <option value="">{{ periods.length ? 'Select a month' : 'Choose a portfolio first' }}</option>
                    <option v-for="period in periods" :key="period.value" :value="period.value">{{ period.label }}</option>
                </select>
            </div>
            <div>
                <label class="maiic-flabel" for="ecl-mode">Content</label>
                <select id="ecl-mode" v-model="form.mode" class="maiic-select">
                    <option value="summary">ECL by stage</option>
                    <option value="totalLoanBook">Full loan book with PD, LGD and ECL</option>
                </select>
            </div>
        </div>

        <div v-if="form.mode === 'totalLoanBook'" class="maiic-panel p-4">
            <h3 class="font-semibold text-gray-900">Columns</h3>
            <p class="mb-3 text-xs text-gray-500">Tick the columns to include; leave all unticked for every column.</p>
            <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                <label v-for="(label, key) in availableColumns" :key="key" class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" :value="key" v-model="form.columns" class="rounded border-gray-300 text-maiic-600">
                    {{ label }}
                </label>
            </div>
        </div>
    </AppLayout>
</template>
