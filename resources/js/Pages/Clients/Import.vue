<template>
    <app-layout title="Import Clients" description="Upload a file of clients (customer ID and name, or any columns you match). It is processed in the background">
        <template #actions>
            <button type="button" class="secondary-btn" title="A CSV with the exact column headers the standard client import reads" @click="downloadSample">Download sample CSV</button>
            <Link :href="route('clients.index')" class="secondary-btn">Back to clients</Link>
        </template>

        <div>
            <InPageTabs v-model="tab" :tabs="tabs" flush/>
            <div class="maiic-panel rounded-t-none">

                <!-- Upload -->
                <form v-if="tab === 'upload'" @submit.prevent="submit" class="grid gap-6 p-5 lg:grid-cols-3">
                    <div class="space-y-5 lg:col-span-2">
                        <div>
                            <span class="maiic-flabel">File layout</span>
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
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
                                <span class="mt-2 text-sm font-semibold text-gray-600">{{ fileName || 'Choose a CSV or Excel file' }}</span>
                                <input type="file" name="names_file" class="hidden" accept=".csv,.txt,.xlsx,.xls" @change="handleFileSelect"/>
                            </label>
                            <p v-if="form.errors.names_file" class="mt-1 text-xs text-red-600">{{ form.errors.names_file }}</p>
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
                                        <option v-for="field in availableFields" :key="field" :value="field">
                                            {{ field }}{{ fieldDescriptions[field] && fieldDescriptions[field].required ? ' (required)' : '' }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <p v-if="form.errors.mapping" class="text-xs text-red-600">{{ form.errors.mapping }}</p>
                        </div>

                        <div class="flex items-center justify-end gap-2 border-t border-gray-200 pt-4">
                            <Link :href="route('clients.index')" class="secondary-btn">Cancel</Link>
                            <button type="submit" class="primary-btn" :disabled="form.processing || !selectedFile">{{ form.processing ? 'Uploading...' : 'Start import' }}</button>
                        </div>
                    </div>

                    <aside class="rounded-lg border border-maiic-100 bg-maiic-50/60 p-4 text-sm text-gray-700">
                        <div class="mb-2 font-semibold text-maiic-800">How to import clients</div>
                        <ol class="list-decimal space-y-1.5 pl-5">
                            <li>For the standard layout, download the sample CSV (top right): two columns, customer_id and name.</li>
                            <li>To use another file as it is, pick Match columns and choose the client field for each column. customer_id must be matched.</li>
                            <li>A client whose customer_id already exists is updated, not duplicated.</li>
                            <li>Rows without a customer_id are set aside in a failed rows file, which you can download from the History tab.</li>
                        </ol>
                    </aside>
                </form>

                <!-- History -->
                <ImportHistoryTable v-else :imports="recentImports"/>
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
                { key: 'legacy', label: 'Standard CSV', help: 'customer_id and name, as in the sample CSV.' },
                { key: 'custom', label: 'Match columns', help: 'Match each column of your file to a client field.' },
            ],
            form: this.$inertia.form({
                names_file: null,
                mapping: {},
                import_type: 'legacy',
            }),
            fileName: '',
            selectedFile: null,
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
        importType() {
            if (this.headers.length) this.setupMapping()
        },
    },

    methods: {
        submit() {
            this.form.transform(() => ({
                names_file: this.selectedFile,
                mapping: this.mapping,
                import_type: this.importType,
            })).post(route('clients.import.store'), { forceFormData: true })
        },

        handleFileSelect(event) {
            const file = event.target.files[0]
            if (file) {
                this.selectedFile = file
                this.fileName = file.name
                this.readFileHeaders(file)
            }
        },

        readFileHeaders(file) {
            if (!/\.(csv|txt)$/i.test(file.name)) {
                this.headers = []
                return
            }
            const reader = new FileReader()
            reader.onload = (e) => {
                const lines = e.target.result.split('\n').filter(line => line.trim() !== '')
                if (lines.length > 0) {
                    this.headers = this.parseCSVLine(lines[0])
                    this.sampleData = lines.slice(0, 4).map(line => this.parseCSVLine(line))
                    this.setupMapping()
                }
            }
            reader.readAsText(file)
        },

        setupMapping() {
            const newMapping = {}
            this.headers.forEach(header => { newMapping[header] = '' })
            if (this.importType === 'custom') this.autoMapFields(newMapping)
            this.mapping = newMapping
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

        autoMapFields(mapping) {
            const commonMappings = {
                'customer_id': 'customer_id', 'customer id': 'customer_id', 'id': 'customer_id',
                'name': 'name', 'customer_name': 'name', 'client_name': 'name', 'full_name': 'name', 'client': 'name',
                'mobile': 'mobile', 'phone': 'mobile', 'telephone': 'mobile', 'phone_number': 'mobile',
                'account_no': 'account_no', 'account_number': 'account_no', 'account': 'account_no',
                'business_unit': 'business_unit', 'email': 'email', 'email_address': 'email',
                'address': 'address', 'physical_address': 'address',
            }
            this.headers.forEach(header => {
                const clean = header.toLowerCase().trim()
                if (commonMappings[clean] && this.availableFields.includes(commonMappings[clean])) {
                    mapping[header] = commonMappings[clean]
                }
            })
        },

        // The standard (legacy) layout of ClientsImport reads exactly these
        // two headings: customer_id and name.
        downloadSample() {
            const blob = new Blob(['customer_id,name\n'], { type: 'text/csv' })
            const url = window.URL.createObjectURL(blob)
            const a = document.createElement('a')
            a.href = url
            a.download = 'clients_sample.csv'
            a.click()
            window.URL.revokeObjectURL(url)
        },
    },
}
</script>
