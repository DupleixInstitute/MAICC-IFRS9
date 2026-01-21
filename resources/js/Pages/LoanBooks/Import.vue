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
            <!-- Error Alert with Template Hint -->
            <div v-if="$page.props.errors.error && $page.props.show_template_hint" class="mb-6 bg-red-50 border-l-4 border-red-400 p-4 rounded-md">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">
                            E-Banker Format Error
                        </h3>
                        <div class="mt-2 text-sm text-red-700">
                            <p>{{ $page.props.errors.error }}</p>
                            <div class="mt-3">
                                <button @click="downloadEbankerTemplate()" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    Download E-Banker Template
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

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
                                                Use the traditional format with fields in the sample file
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div 
                                    class="border-2 rounded-lg p-4 cursor-pointer transition-all"
                                    :class="importType === 'group' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300 hover:border-gray-400'"
                                    @click="importType = 'group'"
                                >
                                    <div class="flex items-center space-x-3">
                                        <div class="flex-shrink-0">
                                            <input type="radio" v-model="importType" value="group" class="h-4 w-4 text-indigo-600 border-gray-300"/>
                                        </div>
                                        <div class="flex-1">
                                            <h5 class="text-sm font-medium text-gray-900">E-Banker Format</h5>
                                            <p class="text-xs text-gray-500 mt-1">
                                                Use the E-Banker format
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

                        <!-- E-Banker Format Info -->
                        <div v-if="importType === 'group'" class="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-blue-800">E-Banker Format Requirements</h3>
                                    <div class="mt-2 text-sm text-blue-700">
                                        <ul class="list-disc list-inside space-y-1">
                                            <li>File should contain at least 15-20 columns with loan data</li>
                                            <li>Data rows should start with serial numbers (1, 2, 3, etc.)</li>
                                            <li>May include "Loan Type :" context rows</li>
                                            <li>Must include key columns: Contract, Customer, Dates, Balance</li>
                                            <li>Download the template below for the correct format</li>
                                        </ul>
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
                        <!-- <div v-if="importType === 'legacy' && headers.length > 0" class="mt-6 border-t border-gray-200 pt-6">
                            <h4 class="text-sm font-medium text-gray-900 mb-4">Legacy Import Setup</h4>
                            <div class="bg-yellow-50 p-4 rounded-lg mb-4">
                                <h5 class="text-sm font-medium text-yellow-800 mb-2">Legacy Format Requirements:</h5>
                                <ul class="text-xs text-yellow-700 list-disc list-inside space-y-1">
                                    <li>Your CSV must have exactly 2 columns</li>
                                    <li>Column 1: <strong>contract_id</strong></li>
                                    <li>Column 2: <strong>balance</strong></li>
                                </ul>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg">
                                <p class="text-sm text-green-700"><strong>Auto-mapping applied:</strong> customer_id → customer_id, contract_id → contract_id</p>
                            </div>
                        </div> -->

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
                            <jet-button type="button" @click="downloadEbankerTemplate()" class="flex-1 justify-center bg-blue-600 hover:bg-blue-500">
                                Download E-Banker Template
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

    watch: {
    // Sync the local UI state with the form object
    importType(newVal) {
        this.form.import_type = newVal;
        if (newVal === 'group') {
            this.headers = []; // Clear UI mapping for group type
            this.form.mapping = {};
            }
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

            const routeName = this.form.import_type === 'group' 
            ? 'loan_applications.loan-book.import.group'
            : 'loan_applications.loan-book.import.store';

            this.form.post(route(routeName), {
                forceFormData: true,
                onSuccess: () => {
                    // Handle success
                },
            });
        },


        autoMapFields(mapping) {
            const commonMappings = {

                'customer_id': 'customer_id', 'customer id': 'customer_id', 'id': 'customer_id', 'Client ID': 'customer_id', 'Customer ID': 'customer_id',
                'public_name': 'Customer_name', 'Name': 'customer_name', 'borrower_name': 'customer_name', 'Customer Name': 'customer_name','name': 'customer_name',
                'Contract ID': 'contract_id', 'contract id': 'contract_id', 'loan_id': 'contract_id', 'loan id': 'contract_id', 'contract_id': 'contract_id',
                'type': 'loan_type', 'loan_type': 'loan_type',
                'value date': 'create_date', 'Value Date': 'create_date', 'value_date': 'value_date',
                'industry_code':'industry_code', 'Industry Code': 'industry_code', 'IndustryCode': 'industry_code', 'Segmentation Code': 'industry_code', 'Sector Code': 'industry_code',
                'industry_type':'industry_type', 'Industry Type': 'industry_type', 'IndustryType': 'industry_type', 'Segmentation': 'industry_type', 'Sector': 'industry_type', 'Sector Name': 'industry_code',
                'Internal Grade Code':'internal_grade_code', 'Internal Grade': 'internal_grade_code', 'Internal Grade Code': 'internal_grade_code', 'Internal Grade': 'internal_grade_code',
                'internal_grade':'internal_grade_code','InternalGrade':'internal_grade_code','SP Grade':'internal_grade_code','SP Grade Code':'internal_grade_code','SP Rating':'internal_grade_code',
                'disbursed': 'disbursement_date', 'disbursement_date': 'disbursement_date',
                'Value Date': 'create_date', 'value_date': 'create_date','ValueDate': 'create_date', 'Value Period': 'create_date','Create Date': 'create_date','create_date': 'create_date',
                'maturity date': 'due_date', 'maturity_date': 'due_date','Maturity Date': 'due_date', 'Maturity': 'due_date','Due Date': 'due_date',
                'tenor': 'tenor', 'Tenor': 'tenor',
                'moratorium': 'moratorium',
                'interest rate': 'interest_rate', 'rate': 'interest_rate', 'Interest Rate': 'interest_rate','interest_rate': 'interest_rate',

                'principal': 'principal_balance', 'Principal': 'principal_balance', 'amount': 'principal_balance', 'loan amount': 'principal_balance','Loan Amount': 'principal_balance','principal_balance': 'principal_balance',
                'carrying amount': 'carrying_amount', 'Carrying Amount': 'carrying_amount','carrying_amount': 'carrying_amount',
                'approved': 'approved_amount', 
                'disbursed': 'disbursed_amount', 'not yet disbursed': 'pending_amount','Disbursed': 'disbursed_amount', 'Disbursement Amount': 'disbursed_amount',
                'interest': 'interest', 'total': 'total',

                'collateral type': 'collateral_type', 'collateral': 'collateral', 'charge amount': 'charge_amount', 'collateral value': 'collateral_value',

                'repayments': 'repayments',
                '1-30 days': 'aging_1_30', '31-90 days': 'aging_31_90', '91-180 days': 'aging_91_180', '181-270 days': 'aging_181_270',
                'arrears_1_to_30': 'arrears_1_to_30', 'arrears_30_to_90': 'arrears_30_to_90', 'arrears_91_to_180': 'arrears_91_to_180', 'arrears_180_to_270': 'arrears_180_to_270',
                '1-30 Days': 'arrears_1_to_30', '31-90 Days': 'arrears_30_to_90', '91-180 Days': 'arrears_91_to_180', '181-270 Days': 'arrears_180_to_270',
                '< 30 Days': 'aging_1_30', '< 90 Days': 'aging_31_90', '< 180 Days': 'aging_91_180', '< 270 Days': 'aging_181_270',
                
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
        // Legacy IFRS9 loanbook template with dummy data
        data =
            `customer_id,name,contract_id,value_date,maturity_date,tenor,interest_rate,principal,disbursed,carrying_amount,1-30 Days,31-90 Days,91-180 Days,181-270 Days
            1,ABC Hotel,1,01/01/2020,31/12/2025,5,15.00,1000000.00,950000.00,1000000.00,50000.00,20000.00,10000.00,5000.00
            2,XYZ Academy,2,01/03/2021,28/02/2026,5,12.50,2000000.00,1950000.00,2000000.00,100000.00,40000.00,20000.00,10000.00
            3,GreenTech Medical,3,15/06/2022,14/06/2027,5,18.00,1500000.00,1450000.00,1500000.00,75000.00,30000.00,15000.00,7000.00
            4,Sunrise Engineering,4,10/07/2019,09/07/2024,5,17.00,2500000.00,2450000.00,2500000.00,125000.00,50000.00,25000.00,12000.00
            5,Riverside Transport,5,20/09/2020,19/09/2025,5,14.50,1800000.00,1750000.00,1800000.00,90000.00,36000.00,18000.00,9000.00`;
                    
                    filename = 'loanbook_legacy_template_dummy.csv'
                } else {
                    const fields = this.availableFields.join(',')
                    data = `${fields}\nCUST001,John Doe,1000,10%\nCUST002,Jane Smith,1500,12%`
                    filename = 'loanbook_custom_template_dummy.csv'
                }

                const blob = new Blob([data], { type: 'text/csv' })
                const url = window.URL.createObjectURL(blob)
                const a = document.createElement('a')
                a.href = url
                a.download = filename
                a.click()
                window.URL.revokeObjectURL(url)
            },

        downloadEbankerTemplate() {
            // Use Laravel backend to generate e-banker template
            window.location.href = this.route('loan_applications.loan-book.download-ebanker-template')
        }


    }
}
</script>
