<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                ECL Export
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <jet-label value="Portfolio" />
                            <select v-model="form.portfolio_id" @change="onPortfolioChange"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200">
                                <option value="">Select Portfolio</option>
                                <option v-for="p in portfolios" :key="p.value" :value="p.value">{{ p.label }}</option>
                            </select>
                        </div>
                        <div>
                            <jet-label value="Reporting Period" />
                            <select v-model="form.reporting_period"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200">
                                <option value="">Select Period</option>
                                <option v-for="period in periods" :key="period.value" :value="period.value">{{ period.label }}</option>
                            </select>
                        </div>
                        <div>
                            <jet-label value="Mode" />
                            <select v-model="form.mode"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200">
                                <option value="summary">Summary</option>
                                <option value="totalLoanBook">Total Loan Book</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="form.mode === 'totalLoanBook'" class="mt-6">
                        <jet-label value="Columns (leave empty for all)" />
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-2">
                            <label v-for="(label, key) in availableColumns" :key="key" class="flex items-center text-sm">
                                <input type="checkbox" :value="key" v-model="form.columns" class="rounded border-gray-300 mr-2">
                                {{ label }}
                            </label>
                        </div>
                    </div>

                    <div class="flex justify-end mt-6 gap-3">
                        <a v-if="canRun" :href="exportUrl"
                           class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-500 text-white text-sm rounded-md">
                            Export CSV
                        </a>
                        <span v-else class="text-sm text-gray-500 self-center">Select portfolio and period to export</span>
                        <Link :href="route('reports.index')"
                              class="inline-flex items-center px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 text-sm rounded-md">
                            Back
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </app-layout>
</template>

<script>
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import JetLabel from '@/Jetstream/Label.vue'

export default {
    components: { AppLayout, JetLabel, Link },
    props: {
        portfolios: { type: Array, default: () => [] },
        periods: { type: Array, default: () => [] },
        availableColumns: { type: Object, default: () => ({}) },
        selectedPortfolio: { default: '' },
        selectedPeriod: { default: '' },
        selectedMode: { default: 'summary' },
        selectedColumns: { type: Array, default: () => [] },
    },
    data() {
        return {
            form: {
                portfolio_id: this.selectedPortfolio || '',
                reporting_period: this.selectedPeriod || '',
                mode: this.selectedMode || 'summary',
                columns: this.selectedColumns || [],
            },
        }
    },
    computed: {
        canRun() {
            return this.form.portfolio_id && this.form.reporting_period
        },
        exportUrl() {
            return this.route('reports.ecl-export', { ...this.form, export: 1 })
        },
    },
    methods: {
        onPortfolioChange() {
            router.get(this.route('reports.ecl-export'), { portfolio_id: this.form.portfolio_id }, {
                preserveState: true,
                preserveScroll: true,
            })
        },
    },
}
</script>
