<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('loan_applications.loan-book')">
                    Loan Books
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> Import
            </h2>
        </template>

        <div class="mx-auto max-w-7xl">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                <form @submit.prevent="submit">
                    <div class="space-y-8">

                        <!-- Portfolio & Reporting Period -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <jet-label for="loan_portfolio_id" value="Portfolio Group"/>
                                <Multiselect
                                    id="loan_portfolio_id"
                                    :required="true"
                                    :searchable="true"
                                    label="name"
                                    value-prop="id"
                                    v-model="form.loan_portfolio_id"
                                    :options="portfolios"
                                />
                                <jet-input-error :message="form.errors.loan_portfolio_id" class="mt-2"/>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Reporting Period</label>
                                <input type="month" v-model="form.reporting_period" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <p class="mt-1 text-xs text-gray-500">Select the month and year for this loan book data</p>
                            </div>
                        </div>

                        <!-- Import Type Selection -->
                        <div class="mt-6">
                            <h4 class="text-sm font-medium text-gray-900 mb-4">Select Import Type</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div 
                                    class="border-2 rounded-lg p-4 cursor-pointer transition-all"
                                    :class="importType === 'legacy' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300 hover:border-gray-400'"
                                    @click="importType = 'legacy'"
                                >
                                    <div class="flex items-center space-x-3">
                                        <div class="flex-shrink-0">
                                            <input type="radio" v-model="importType" value="legacy" class="h-4 w-4 text-indigo-600 border-gray-300"/>
                                        </div>
                                        <div class="flex-1">
                                            <h5 class="text-sm font-medium text-gray-900">Legacy Format</h5>
                                            <p class="text-xs text-gray-500 mt-1">
                                                Use the traditional format with customer_id and public_name fields
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div 
                                    class="border-2 rounded-lg p-4 cursor-pointer transition-all"
                                    :class="importType === 'custom' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300 hover:border-gray-400'"
                                    @click="importType = 'custom'"
                                >
                                    <div class="flex items-center space-x-3">
                                        <div class="flex-shrink-0">
                                            <input type="radio" v-model="importType" value="custom" class="h-4 w-4 text-indigo-600 border-gray-300"/>
                                        </div>
                                        <div class="flex-1">
                                            <h5 class="text-sm font-medium text-gray-900">Custom Mapping</h5>
                                            <p class="text-xs text-gray-500 mt-1">
                                                Map your CSV columns to any database fields
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- File Upload -->
                        <div class="mt-6 border-t border-gray-200 pt-6">
                            <h4 class="text-sm font-medium text-gray-900 mb-4">Upload File</h4>
                            <div class="flex items-center justify-center w-full">
                                <label
                                    class="flex flex-col w-full h-32 border-4 border-dashed hover:bg-gray-100 hover:border-gray-300 rounded-lg cursor-pointer">
                                    <div class="relative flex flex-col items-center justify-center pt-7">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-gray-400 group-hover:text-gray-600" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/>
                                        </svg>
                                        <p class="pt-1 text-sm tracking-wider text-gray-400 group-hover:text-gray-600">
                                            {{ fileName || 'Select a CSV file' }}
                                        </p>
                                    </div>
                                    <input type="file" class="opacity-0" accept=".csv,.txt" @change="handleFileSelect"/>
                                </label>
                            </div>
                        </div>

                        <!-- Legacy Auto Mapping -->
                        <div v-if="importType === 'legacy' && headers.length > 0" class="mt-6 border-t border-gray-200 pt-6">
                            <h4 class="text-sm font-medium text-gray-900 mb-4">Legacy Import Setup</h4>
                            <div class="bg-yellow-50 p-4 rounded-lg mb-4">
                                <h5 class="text-sm font-medium text-yellow-800 mb-2">Legacy Format Requirements:</h5>
                                <ul class="text-xs text-yellow-700 list-disc list-inside space-y-1">
                                    <li>Your CSV must have exactly 2 columns</li>
                                    <li>Column 1: <strong>customer_id</strong></li>
                                    <li>Column 2: <strong>public_name</strong></li>
                                </ul>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg">
                                <p class="text-sm text-green-700"><strong>Auto-mapping applied:</strong> customer_id → customer_id, public_name → public_name</p>
                            </div>
                        </div>

                        <!-- Custom Mapping -->
                        <div v-if="importType === 'custom' && headers.length > 0" class="mt-6 border-t border-gray-200 pt-6">
                            <h4 class="text-sm font-medium text-gray-900 mb-4">Map CSV Columns</h4>
                            <div class="bg-gray-50 p-4 rounded-lg mb-6">
                                <h5 class="font-medium text-gray-700 mb-2">File Preview (first 3 rows):</h5>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                                        <thead class="bg-gray-100">
                                            <tr>
                                                <th v-for="(header, index) in headers" :key="index" class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">
                                                    {{ header }}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <tr v-for="(row, rowIndex) in sampleData.slice(1, 4)" :key="rowIndex">
                                                <td v-for="(cell, cellIndex) in row" :key="cellIndex" class="px-3 py-2 text-gray-500 truncate max-w-xs">
                                                    {{ cell }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4">
                                <div v-for="(header, index) in headers" :key="index" class="flex items-center space-x-4 p-3 bg-gray-50 rounded-lg">
                                    <label class="block text-sm font-medium text-gray-700 w-32 truncate">{{ header }}</label>
                                    <select v-model="mapping[header]" class="flex-1 py-2 px-3 border border-gray-300 rounded-md shadow-sm text-sm">
                                        <option value="">-- Ignore this column --</option>
                                        <option v-for="field in availableFields" :key="field" :value="field">{{ field }}</option>
                                    </select>
                                    <div class="w-20 text-xs text-gray-500 text-right">
                                        <span v-if="mapping[header]" class="text-green-600">✓ Mapped</span>
                                        <span v-else class="text-gray-400">Not mapped</span>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <!-- Quick Actions -->
                        <div class="mt-6 flex flex-col sm:flex-row gap-4">
                            <jet-button type="button" @click="downloadTemplate('legacy')" class="flex-1 justify-center">
                                Download Legacy Template
                            </jet-button>
                            <jet-button type="button" @click="downloadTemplate('custom')" class="flex-1 justify-center">
                                Download Custom Template
                            </jet-button>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="flex items-center justify-end pt-6 border-t border-gray-200">
                            <Link :href="route('loan_applications.loan-book')" class="inline-flex items-center px-4 py-2 bg-gray-300 text-gray-700 rounded-md mr-3">
                                Cancel
                            </Link>
                            <jet-button :disabled="form.processing" class="ml-3 bg-green-600 hover:bg-green-500">
                                Start Import
                            </jet-button>
                        </div>

                    </div>
                </form>
            </div>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetButton from "@/Jetstream/Button.vue"
import JetLabel from "@/Jetstream/Label.vue"
import JetInputError from "@/Jetstream/InputError.vue"
import { Link } from '@inertiajs/vue3'

export default {
    components: { AppLayout, JetButton, JetLabel, JetInputError, Link },

    props: {
        portfolios: Object,
        availableFields: { type: Array, default: () => ['customer_id', 'public_name', 'loan_amount', 'interest_rate'] },
        fieldDescriptions: { type: Object, default: () => ({}) },
    },

    data() {
        return {
            importType: 'custom',
            form: this.$inertia.form({
                file: null,
                loan_portfolio_id: '',
                reporting_period: '',
                mapping: {},
                import_type: 'custom',
            }),
            fileName: '',
            selectedFile: null,
            headers: [],
            sampleData: [],
            mapping: {},
        }
    },

    methods: {
        submit() {
            const formData = new FormData()
            formData.append('file', this.selectedFile)
            formData.append('loan_portfolio_id', this.form.loan_portfolio_id)
            formData.append('reporting_period', this.form.reporting_period)
            formData.append('import_type', this.importType)
            formData.append('mapping', JSON.stringify(this.mapping))

            this.form.transform(() => ({
                file: this.selectedFile,
                loan_portfolio_id: this.form.loan_portfolio_id,
                reporting_period: this.form.reporting_period,
                import_type: this.importType,
                mapping: this.mapping,
            })).post(route('loan_applications.loan-book.import.store'))
        },


        autoMapFields(mapping) {
            const commonMappings = {

                'customer_id': 'customer_id', 'customer id': 'customer_id', 'id': 'customer_id', 'Client ID': 'customer_id', 'Customer ID': 'customer_id',
                'public_name': 'name', 'Name': 'name', 'borrower_name': 'name', 'Customer Name': 'name',

                'type': 'loan_type', 'loan_type': 'loan_type',
                'value date': 'disbursement_date', 'disbursed': 'disbursement_date', 'disbursement_date': 'disbursement_date',
                'maturity date': 'maturity_date', 'maturity_date': 'maturity_date','Maturity Date': 'maturity_date',
                'tenor': 'tenor', 'Tenor': 'tenor',
                'moratorium': 'moratorium', 'Moratorium Period': 'moratorium',
                'interest rate': 'interest_rate', 'rate': 'interest_rate', 'Interest Rate': 'interest_rate',

                'principal': 'loan_amount', 'loan_amount': 'loan_amount', 'amount': 'loan_amount',
                'carrying amount': 'carrying_amount', 'Carrying Amount': 'carrying_amount',
                'approved': 'approved_amount', 
                'disbursed': 'disbursed_amount', 'not yet disbursed': 'pending_amount','Disbursed': 'disbursed_amount', 'Disbursement Amount': 'disbursed_amount',
                'interest': 'interest', 'total': 'total',

                'collateral type': 'collateral_type', 'collateral': 'collateral', 'charge amount': 'charge_amount', 'collateral value': 'collateral_value',

                'repayments': 'repayments',
                '1-30 days': 'aging_1_30', '31-90 days': 'aging_31_90', '91-180 days': 'aging_91_180', '181-270 days': 'aging_181_270'
            };


            this.headers.forEach(header => {
                const cleanHeader = header.toLowerCase().trim();
                if (commonMappings[cleanHeader] && this.availableFields.includes(commonMappings[cleanHeader])) {
                    mapping[header] = commonMappings[cleanHeader];
                }
            });
        },


        handleFileSelect(event) {
            const file = event.target.files[0]
            if (!file) return
            this.selectedFile = file
            this.fileName = file.name
            this.readFileHeaders(file)
        },

        readFileHeaders(file) {
            const reader = new FileReader()
            reader.onload = (e) => {
                const lines = e.target.result.split('\n').filter(line => line.trim() !== '')
                if (lines.length) {
                    this.headers = this.parseCSVLine(lines[0])
                    this.sampleData = lines.slice(0, 4).map(line => this.parseCSVLine(line))
                    this.setupMapping()
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

        setupMapping() {
            const newMapping = {};

            if (this.importType === 'legacy') {
                this.headers.forEach(header => {
                    if (header.toLowerCase().includes('id')) newMapping[header] = 'customer_id';
                    else if (header.toLowerCase().includes('name')) newMapping[header] = 'public_name';
                    else newMapping[header] = '';
                });
            } else {
                // Custom mapping
                this.headers.forEach(header => newMapping[header] = '');
                this.autoMapFields(newMapping);
            }

            this.mapping = newMapping;
        },


        downloadTemplate(type) {
            let data = '', filename = ''
            if (type === 'legacy') {
                data = 'customer_id,public_name\n1001,0774892762-John Doe\n1002,0774892763-Jane Smith'
                filename = 'loanbook_legacy_template.csv'
            } else {
                const fields = this.availableFields.join(',')
                data = `${fields}\nCUST001,John Doe,1000,10%\nCUST002,Jane Smith,1500,12%`
                filename = 'loanbook_custom_template.csv'
            }
            const blob = new Blob([data], { type: 'text/csv' })
            const url = window.URL.createObjectURL(blob)
            const a = document.createElement('a')
            a.href = url
            a.download = filename
            a.click()
            window.URL.revokeObjectURL(url)
        }
    }
}
</script>
