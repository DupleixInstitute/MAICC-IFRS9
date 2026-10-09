<template>
    <app-layout title="Import Loan Book" description="Upload one month-end loan book file for a portfolio. It is processed in the background; follow it on the History tab or in Imports">
        <template #actions>
            <a :href="route('loan_applications.loan-book.sample')" class="secondary-btn" title="A CSV with the exact column headers the standard import reads">Download sample CSV</a>
            <a :href="route('loan_applications.loan-book.download-ebanker-template')" class="secondary-btn" title="A CSV laid out like the E-Banker loan book report">Download E-Banker template</a>
            <Link :href="route('loan_applications.loan-book')" class="secondary-btn">Back to Loan Book</Link>
        </template>

        <div class="space-y-5">
            <div>
                <InPageTabs v-model="tab" :tabs="tabs" flush/>
                <div class="maiic-panel rounded-t-none">

                    <!-- Upload -->
                    <form v-if="tab === 'upload'" @submit.prevent="submit" class="grid gap-6 p-5 lg:grid-cols-3">
                        <div class="space-y-5 lg:col-span-2">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div>
                                    <label class="maiic-flabel" for="loan_portfolio_id">Portfolio</label>
                                    <Multiselect id="loan_portfolio_id" :required="true" :searchable="true" label="name" value-prop="id"
                                                 v-model="form.loan_portfolio_id" :options="portfolios" placeholder="Choose the portfolio"/>
                                    <p v-if="form.errors.loan_portfolio_id" class="mt-1 text-xs text-red-600">{{ form.errors.loan_portfolio_id }}</p>
                                </div>
                                <div>
                                    <label class="maiic-flabel" for="reporting_period">Reporting period</label>
                                    <input id="reporting_period" type="month" v-model="form.reporting_period" required class="maiic-input">
                                    <p v-if="form.errors.reporting_period" class="mt-1 text-xs text-red-600">{{ form.errors.reporting_period }}</p>
                                    <p v-else class="mt-1 text-xs text-gray-500">The month-end the file reports.</p>
                                </div>
                            </div>

                            <div>
                                <span class="maiic-flabel">File layout</span>
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                                    <label v-for="opt in layouts" :key="opt.key"
                                           class="flex cursor-pointer gap-3 rounded-lg border-2 p-3 transition"
                                           :class="importType === opt.key ? 'border-maiic-500 bg-maiic-50' : 'border-gray-200 hover:border-gray-300'">
                                        <input type="radio" v-model="importType" :value="opt.key" class="mt-0.5 h-4 w-4 border-gray-300 text-maiic-600"/>
                                        <span>
                                            <span class="block text-sm font-semibold text-gray-900">{{ opt.label }}</span>
                                            <span class="block text-xs text-gray-500">{{ opt.help }}</span>
                                        </span>
                                    </label>
                                </div>
                            </div>

                            <div>
                                <span class="maiic-flabel">File</span>
                                <label class="flex h-28 w-full cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 transition hover:border-maiic-300 hover:bg-maiic-50">
                                    <svg class="h-8 w-8 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm3 4a1 1 0 000 2h6a1 1 0 100-2H7zm0 4a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>
                                    <span class="mt-2 text-sm font-semibold text-gray-600">{{ fileName || 'Choose a CSV file' }}</span>
                                    <input type="file" class="hidden" accept=".csv,.txt" @change="handleFileSelect"/>
                                </label>
                                <p v-if="form.errors.file" class="mt-1 text-xs text-red-600">{{ form.errors.file }}</p>
                            </div>

                            <!-- Custom mapping -->
                            <div v-if="importType === 'custom' && headers.length > 0" class="space-y-3">
                                <div class="maiic-section-title !mt-0">Match the file's columns</div>
                                <div class="maiic-table-wrap rounded-lg border border-gray-200">
                                    <table class="maiic-table">
                                        <thead><tr><th v-for="(header, index) in headers" :key="index">{{ header }}</th></tr></thead>
                                        <tbody>
                                        <tr v-for="(row, rowIndex) in sampleData.slice(1, 4)" :key="rowIndex">
                                            <td v-for="(cell, cellIndex) in row" :key="cellIndex" class="max-w-xs truncate text-xs">{{ cell }}</td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                                    <div v-for="(header, index) in headers" :key="index" class="flex items-center gap-3 rounded-lg bg-gray-50 px-3 py-2">
                                        <span class="w-40 truncate text-sm font-semibold text-gray-700" :title="header">{{ header }}</span>
                                        <select v-model="mapping[header]" class="maiic-select flex-1">
                                            <option value="">Ignore this column</option>
                                            <option v-for="field in availableFields" :key="field" :value="field">{{ field }}</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-2 border-t border-gray-200 pt-4">
                                <Link :href="route('loan_applications.loan-book')" class="secondary-btn">Cancel</Link>
                                <button type="submit" class="primary-btn" :disabled="form.processing || !form.file">{{ form.processing ? 'Uploading...' : 'Start import' }}</button>
                            </div>
                        </div>

                        <!-- How to -->
                        <aside class="rounded-lg border border-maiic-100 bg-maiic-50/60 p-4 text-sm text-gray-700">
                            <div class="mb-2 font-semibold text-maiic-800">How to import a loan book</div>
                            <ol class="list-decimal space-y-1.5 pl-5">
                                <li>Choose the portfolio and the month-end the file reports.</li>
                                <li>Pick the file layout. For the standard layout, download the sample CSV (top right) and keep its column headers.</li>
                                <li>Dates may be day/month/year or year-month-day. Amounts may carry thousands commas.</li>
                                <li>Each row needs a customer_id, a value_date and a maturity_date; a row without them is set aside in the failed rows file.</li>
                                <li>Start the import, then follow it on the History tab.</li>
                            </ol>
                        </aside>
                    </form>

                    <!-- History -->
                    <ImportHistoryTable v-else :imports="recentImports"/>
                </div>
            </div>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import InPageTabs from '@/Components/Data/InPageTabs.vue'
import ImportHistoryTable from '@/Components/Data/ImportHistoryTable.vue'
import { Link } from '@inertiajs/vue3'

export default {
    components: { AppLayout, InPageTabs, ImportHistoryTable, Link },

    props: {
        portfolios: [Array, Object],
        availableFields: { type: Array, default: () => [] },
        fieldDescriptions: { type: Object, default: () => ({}) },
        recentImports: { type: Array, default: () => [] },
        importCount: { type: Number, default: 0 },
    },

    data() {
        return {
            tab: 'upload',
            importType: 'legacy',
            layouts: [
                { key: 'legacy', label: 'Standard CSV', help: 'The columns of the sample CSV.' },
                { key: 'group', label: 'E-Banker report', help: 'The E-Banker loan book report as exported.' },
                { key: 'custom', label: 'Match columns', help: 'Match each column of your file to a loan book field.' },
            ],
            form: this.$inertia.form({
                file: null,
                loan_portfolio_id: '',
                reporting_period: '',
                mapping: {},
                import_type: 'legacy',
            }),
            fileName: '',
            headers: [],
            sampleData: [],
            mapping: {},
        }
    },

    computed: {
        tabs() {
            return [
                { key: 'upload', label: 'Upload' },
                { key: 'history', label: 'History', count: this.importCount },
            ]
        },
    },

    watch: {
        importType(newVal) {
            this.form.import_type = newVal
            if (newVal === 'group') {
                this.headers = []
                this.form.mapping = {}
            } else if (this.form.file) {
                this.readFileHeaders(this.form.file)
            }
        },
    },

    methods: {
        submit() {
            const routeName = this.form.import_type === 'group'
                ? 'loan_applications.loan-book.import.group'
                : 'loan_applications.loan-book.import.store'
            if (this.form.import_type === 'custom') {
                this.form.mapping = { ...this.mapping }
            }
            this.form.post(route(routeName), { forceFormData: true })
        },

        autoMapFields(mapping) {
            const commonMappings = {
                'customer_id': 'customer_id', 'customer id': 'customer_id', 'id': 'customer_id', 'client id': 'customer_id',
                'name': 'customer_name', 'customer name': 'customer_name', 'borrower_name': 'customer_name',
                'contract id': 'contract_id', 'loan_id': 'contract_id', 'loan id': 'contract_id', 'contract_id': 'contract_id',
                'type': 'loan_type', 'loan_type': 'loan_type',
                'industry_code': 'industry_code', 'industry code': 'industry_code', 'sector code': 'industry_code',
                'industry_type': 'industry_type', 'industry type': 'industry_type', 'sector': 'industry_type',
                'internal grade code': 'internal_grade_code', 'internal grade': 'internal_grade_code', 'internal_grade': 'internal_grade_code',
                'value date': 'create_date', 'value_date': 'create_date', 'create date': 'create_date', 'create_date': 'create_date',
                'maturity date': 'due_date', 'maturity_date': 'due_date', 'maturity': 'due_date', 'due date': 'due_date',
                'tenor': 'tenor', 'interest rate': 'interest_rate', 'rate': 'interest_rate', 'interest_rate': 'interest_rate',
                'principal': 'principal_balance', 'amount': 'principal_balance', 'loan amount': 'principal_balance', 'principal_balance': 'principal_balance',
                'carrying amount': 'carrying_amount', 'carrying_amount': 'carrying_amount',
                'approved': 'approved_amount', 'disbursed': 'disbursed_amount',
                'repayments': 'repayments',
                '1-30 days': 'arrears_1_to_30', '31-90 days': 'arrears_30_to_90', '91-180 days': 'arrears_91_to_180', '181-270 days': 'arrears_180_to_270',
            }
            this.headers.forEach(header => {
                const clean = header.toLowerCase().trim()
                if (commonMappings[clean] && this.availableFields.includes(commonMappings[clean])) {
                    mapping[header] = commonMappings[clean]
                }
            })
        },

        handleFileSelect(event) {
            const file = event.target.files[0]
            if (!file) return
            this.form.file = file
            this.fileName = file.name
            if (this.importType !== 'group') this.readFileHeaders(file)
        },

        readFileHeaders(file) {
            const reader = new FileReader()
            reader.onload = (e) => {
                const lines = e.target.result.split('\n').filter(line => line.trim() !== '')
                if (lines.length) {
                    this.headers = this.parseCSVLine(lines[0])
                    this.sampleData = lines.slice(0, 4).map(line => this.parseCSVLine(line))
                    const newMapping = {}
                    this.headers.forEach(header => { newMapping[header] = '' })
                    this.autoMapFields(newMapping)
                    this.mapping = newMapping
                }
            }
            reader.readAsText(file)
        },

        parseCSVLine(line) {
            const result = []
            let current = '', inQuotes = false
            for (let i = 0; i < line.length; i++) {
                const char = line[i]
                if (char === '"') inQuotes = !inQuotes
                else if (char === ',' && !inQuotes) { result.push(current.trim()); current = '' }
                else current += char
            }
            result.push(current.trim())
            return result
        },
    },
}
</script>
