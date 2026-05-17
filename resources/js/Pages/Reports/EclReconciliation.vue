<template>
    <app-layout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Stage Transition Reconciliation Report
                </h2>
                <a
                    :href="route('reports.index')"
                    class="inline-flex items-center rounded-md bg-gray-600 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
                >
                    Back to Reports
                </a>
            </div>
        </template>

        <div class="p-6 bg-gray-100 min-h-screen">
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Report Parameters</h3>
                    <p class="mt-1 text-sm text-gray-500">Choose whether to reconcile on ECL value or carrying amount, then generate either a summary or detailed report.</p>
                </div>

                <form @submit.prevent="generateReport" class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Portfolio
                            </label>
                            <select
                                v-model="form.portfolio_id"
                                @change="handlePortfolioChange"
                                class="mt-1 block w-full border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md"
                            >
                                <option value="">Select a portfolio</option>
                                <option v-for="portfolio in portfolios" :key="portfolio.value" :value="portfolio.value">
                                    {{ portfolio.label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Start Period
                            </label>
                            <input
                                v-model="form.start_period"
                                type="month"
                                class="mt-1 block w-full border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                End Period
                            </label>
                            <input
                                v-model="form.end_period"
                                type="month"
                                class="mt-1 block w-full border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Movement Type
                            </label>
                            <select
                                v-model="form.movement_type"
                                class="mt-1 block w-full border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md"
                            >
                                <option value="ecl_value">ECL Value</option>
                                <option value="principal_balance">Carrying Amount</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Report Type
                            </label>
                            <select
                                v-model="form.report_type"
                                class="mt-1 block w-full border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md"
                            >
                                <option value="summary">Summary</option>
                                <option value="detailed">Detailed</option>
                            </select>
                        </div>

                        <div v-if="form.report_type === 'detailed'">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Detailed Section
                            </label>
                            <select
                                v-model="form.detail_type"
                                class="mt-1 block w-full border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md"
                            >
                                <option value="">Select a section</option>
                                <option value="new_loans">New Loans</option>
                                <option value="derecognized_loans">Derecognized Loans</option>
                                <option value="stage_transitions">Stage Transitions</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <button
                            v-if="showExportButton"
                            type="button"
                            @click="handleExport"
                            :disabled="isExporting || !isFormValid"
                            class="inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <font-awesome-icon v-if="isExporting" icon="spinner" class="animate-spin -ml-1 mr-2 h-4 w-4"></font-awesome-icon>
                            <font-awesome-icon v-else icon="download" class="-ml-1 mr-2 h-4 w-4"></font-awesome-icon>
                            {{ isExporting ? 'Exporting...' : exportLabel }}
                        </button>

                        <button
                            type="submit"
                            :disabled="isGenerating || !isFormValid"
                            class="inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <font-awesome-icon v-if="isGenerating" icon="spinner" class="animate-spin -ml-1 mr-2 h-4 w-4"></font-awesome-icon>
                            <font-awesome-icon v-else icon="chart-bar" class="-ml-1 mr-2 h-4 w-4"></font-awesome-icon>
                            {{ isGenerating ? 'Generating...' : generateLabel }}
                        </button>
                    </div>
                </form>
            </div>

            <div v-if="report && report.report_type !== 'detailed'" class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Report Results</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ report.start_period }} to {{ report.end_period }} |
                        {{ report.movement_label }}
                        <span v-if="report.report_type === 'detailed'">| {{ report.detail_label }}</span>
                    </p>
                </div>

                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-blue-900">Movement Type</h4>
                            <p class="text-lg font-bold text-blue-600">{{ report.movement_label }}</p>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-green-900">Start Transitions</h4>
                            <p class="text-2xl font-bold text-green-600">{{ report.start_transition_count }}</p>
                        </div>
                        <div class="bg-purple-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-purple-900">New Transitions</h4>
                            <p class="text-2xl font-bold text-purple-600">{{ report.new_transition_count }}</p>
                        </div>
                        <div class="bg-orange-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-orange-900">Total Rows</h4>
                            <p class="text-2xl font-bold text-orange-600">{{ report.row_count }}</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Particulars
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Stage 1
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Stage 2
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Stage 3
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Total
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="(row, index) in report.rows" :key="index" :class="index % 2 === 0 ? 'bg-white' : 'bg-gray-50'">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ row.particulars }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                        {{ formatAmount(row.stage_1) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                        {{ formatAmount(row.stage_2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                        {{ formatAmount(row.stage_3) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900">
                                        {{ formatAmount(row.total) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div v-if="report && report.report_type === 'detailed'" class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Detailed Report Preview</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ report.start_period }} to {{ report.end_period }} |
                        {{ report.movement_label }} |
                        {{ report.detail_label }}
                    </p>
                </div>

                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-blue-900">Movement Type</h4>
                            <p class="text-lg font-bold text-blue-600">{{ report.movement_label }}</p>
                        </div>
                        <div class="bg-purple-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-purple-900">Detail Section</h4>
                            <p class="text-lg font-bold text-purple-600">{{ report.detail_label }}</p>
                        </div>
                        <div class="bg-orange-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-orange-900">Preview Rows</h4>
                            <p class="text-2xl font-bold text-orange-600">
                                {{ report.detail_preview_count }} / {{ report.detail_total_rows }}
                            </p>
                        </div>
                    </div>

                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <font-awesome-icon icon="info-circle" class="h-5 w-5 text-blue-400"></font-awesome-icon>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-blue-700">
                                    Showing a preview of the detailed report on the page. Use export to download the full CSV.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th
                                        v-for="header in report.detail_headers"
                                        :key="header"
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                                    >
                                        {{ formatHeaderLabel(header) }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr
                                    v-for="(row, index) in report.detail_rows"
                                    :key="row.id || index"
                                    :class="index % 2 === 0 ? 'bg-white' : 'bg-gray-50'"
                                >
                                    <td
                                        v-for="header in report.detail_headers"
                                        :key="`${row.id || index}-${header}`"
                                        class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"
                                    >
                                        {{ formatDetailValue(row[header], header) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div v-if="error" class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <font-awesome-icon icon="exclamation-circle" class="h-5 w-5 text-red-400"></font-awesome-icon>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700">{{ error }}</p>
                    </div>
                </div>
            </div>

            <div v-if="success" class="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <font-awesome-icon icon="check-circle" class="h-5 w-5 text-green-400"></font-awesome-icon>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-green-700">{{ success }}</p>
                    </div>
                </div>
            </div>
        </div>

        <teleport to="head">
            <title>{{ pageTitle }}</title>
            <meta property="og:description" :content="pageDescription">
        </teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'

export default {
    components: {
        AppLayout,
    },

    props: {
        report: Object,
        portfolios: Array,
        periods: Array,
        selectedPortfolio: String,
        selectedStartPeriod: String,
        selectedEndPeriod: String,
        selectedMovementType: String,
        selectedReportType: String,
        selectedDetailType: String,
    },

    data() {
        return {
            form: {
                portfolio_id: this.selectedPortfolio || '',
                start_period: this.selectedStartPeriod || '',
                end_period: this.selectedEndPeriod || '',
                movement_type: this.selectedMovementType || 'ecl_value',
                report_type: this.selectedReportType || 'summary',
                detail_type: this.selectedDetailType || '',
            },
            isGenerating: false,
            isExporting: false,
            error: '',
            success: '',
        }
    },

    computed: {
        pageTitle() {
            return "ECL Reconciliation Report";
        },
        pageDescription() {
            return "Generate Expected Credit Loss reconciliation reports";
        },
        isFormValid() {
            const hasBaseFields = this.form.portfolio_id
                && this.form.start_period
                && this.form.end_period
                && this.form.start_period < this.form.end_period;

            if (!hasBaseFields) {
                return false;
            }

            if (this.form.report_type === 'detailed') {
                return !!this.form.detail_type;
            }

            return true;
        },
        exportLabel() {
            return this.form.report_type === 'detailed' ? 'Export Detailed CSV' : 'Export Summary CSV';
        },
        generateLabel() {
            return 'Generate Report';
        },
        showExportButton() {
            return !!this.report;
        }
    },

    methods: {
        handlePortfolioChange() {
            this.form.start_period = '';
            this.form.end_period = '';
            this.error = '';
            this.success = '';

            this.$inertia.get(route('reports.ecl-reconciliation'), {
                portfolio_id: this.form.portfolio_id,
                movement_type: this.form.movement_type,
                report_type: this.form.report_type,
                detail_type: this.form.detail_type,
            });
        },

        generateReport() {
            if (!this.isFormValid) {
                this.error = 'Please complete all required fields and make sure the end period is after the start period';
                return;
            }

            this.isGenerating = true;
            this.error = '';
            this.success = '';

            const reportUrl = route('reports.ecl-reconciliation', {
                ...this.form,
                generate: true,
            });

            window.open(reportUrl, '_blank');
            this.isGenerating = false;
        },

        handleExport() {
            if (!this.isFormValid) {
                this.error = 'Please complete all required fields before exporting';
                return;
            }

            this.isExporting = true;
            this.error = '';

            window.open(route('reports.ecl-reconciliation', {
                ...this.form,
                export: true,
            }), '_blank');

            this.isExporting = false;
        },

        formatAmount(amount) {
            return Number(amount || 0).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        },
        formatHeaderLabel(header) {
            return String(header || '')
                .replace(/_/g, ' ')
                .replace(/\b\w/g, char => char.toUpperCase());
        },
        formatDetailValue(value, header) {
            if (value === null || value === undefined || value === '') {
                return '-';
            }

            if (['principal_balance', 'pd_value', 'lgd_value', 'ecl_value', 'movement_value', 'start_movement_value', 'end_movement_value'].includes(header)) {
                return this.formatAmount(value);
            }

            return value;
        },

    }
}
</script>
