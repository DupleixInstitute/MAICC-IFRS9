<template>
    <app-layout>
        <template #header>
            <div>
                <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
                    <span>IFRS 9 Model Setup</span><span>/</span><span>Forward-Looking Model</span><span>/</span>
                    <Link :href="route('credit-loss-data.index')" class="hover:text-maiic-700">Credit Loss Data</Link><span>/</span><span class="font-medium text-maiic-700">Import</span>
                </div>
                <h2 class="text-xl font-semibold text-gray-800">Import credit loss data</h2>
                <p class="mt-1 text-sm text-gray-600">Load the historical loss measures of one portfolio from a CSV file, one row per period</p>
            </div>
        </template>
        <template #actions>
            <a :href="route('import-samples.show', 'credit-loss-data')" class="secondary-btn" title="A CSV with the exact column headers this import reads">Download sample CSV</a>
            <Link :href="route('credit-loss-data.index')" class="secondary-btn">Back to credit loss data</Link>
        </template>

        <div class="maiic-panel">
            <form class="grid gap-6 p-5 lg:grid-cols-3" @submit.prevent="submit">
                <div class="space-y-5 lg:col-span-2">
                    <div>
                        <label for="portfolio_id" class="maiic-flabel">Portfolio</label>
                        <select id="portfolio_id" v-model="form.portfolio_id" required class="maiic-select" :disabled="form.processing">
                            <option value="" disabled>Choose the portfolio these figures belong to</option>
                            <option v-for="portfolio in portfolios" :key="portfolio.id" :value="portfolio.id">{{ portfolio.name }}</option>
                        </select>
                        <p v-if="form.errors.portfolio_id" class="mt-1 text-xs text-red-600">{{ form.errors.portfolio_id }}</p>
                    </div>

                    <FileDrop accept=".csv,.txt,.xlsx" placeholder="Choose a CSV file" hint="CSV, TXT or XLSX, up to 2 MB"
                              :error="form.errors.file || fileError" :disabled="form.processing" @file="onFile"/>

                    <!-- What the importer will read from the file's columns -->
                    <div v-if="headers.length" class="space-y-2">
                        <div class="maiic-section-title !mt-0">Columns in your file</div>
                        <div class="maiic-table-wrap rounded-lg border border-gray-200">
                            <table class="maiic-table">
                                <thead>
                                <tr><th>Column</th><th>Read as</th><th v-for="n in previewRows.length" :key="n">Row {{ n }}</th></tr>
                                </thead>
                                <tbody>
                                <tr v-for="(h, i) in headers" :key="i">
                                    <td class="font-mono text-xs">{{ h }}</td>
                                    <td>
                                        <span v-if="readAs(h)" class="maiic-badge maiic-badge-green">{{ readAs(h) }}</span>
                                        <span v-else class="maiic-badge maiic-badge-grey">not read</span>
                                    </td>
                                    <td v-for="(row, r) in previewRows" :key="r" class="max-w-[10rem] truncate text-xs">{{ row[i] }}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                        <p v-if="!hasPeriod" class="text-xs text-red-600">The file has no period column. Every row needs one; rows without it are set aside.</p>
                        <p v-else-if="!metricCount" class="text-xs text-red-600">None of the columns is a credit loss measure the import reads. Rename them as in the sample CSV.</p>
                        <p v-else class="text-xs text-gray-500">{{ dataRowCount.toLocaleString() }} data rows, {{ metricCount }} measure{{ metricCount === 1 ? '' : 's' }} read from each.</p>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-gray-200 pt-4">
                        <Link :href="route('credit-loss-data.index')" class="secondary-btn">Cancel</Link>
                        <button type="submit" class="primary-btn" :disabled="!canSubmit || form.processing">
                            {{ form.processing ? 'Importing...' : 'Start import' }}
                        </button>
                    </div>
                </div>

                <ImportHowTo title="How to import credit loss data" :sample-url="route('import-samples.show', 'credit-loss-data')">
                    <li>Download the sample CSV (top right). It has a period column, one column per measure ({{ measureList }}), and the optional source and notes.</li>
                    <li>Fill one row per period, the date in the period column. Columns are read by their names, so keep the sample's headings; other columns are ignored.</li>
                    <li>PD and LGD are fractions between 0 and 1, and the stage is 1, 2 or 3. A value that breaks this is set aside, the rest of the row still loads.</li>
                    <li>Choose the portfolio and the file, then press Start import. A value already held for the same portfolio, measure and period is replaced.</li>
                    <li>Each upload is logged under its file name on the <Link :href="route('imports.index')" class="font-semibold text-maiic-700 underline">Imports</Link> screen.</li>
                </ImportHowTo>
            </form>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import FileDrop from '@/Components/Data/FileDrop.vue'
import ImportHowTo from '@/Components/Data/ImportHowTo.vue'
import { Link } from '@inertiajs/vue3'

export default {
    components: { AppLayout, FileDrop, ImportHowTo, Link },

    props: {
        portfolios: { type: [Array, Object], default: () => [] },
        definitions: { type: Array, default: () => [] },
        // definition code => the column names CreditLossDataImport accepts
        aliases: { type: Object, default: () => ({}) },
    },

    data() {
        return {
            form: this.$inertia.form({ file: null, portfolio_id: '' }),
            fileError: '',
            headers: [],
            previewRows: [],
            dataRowCount: 0,
            isCsv: false,
        }
    },

    computed: {
        measureList() {
            return this.definitions.map(d => d.code).join(', ')
        },
        /** Slugged column name => what the importer reads it as. */
        recognised() {
            const map = { period: 'Period', source: 'Data source', notes: 'Notes' }
            for (const d of this.definitions) {
                for (const a of (this.aliases[d.code] || [])) {
                    const key = String(a).toLowerCase()
                    if (!(key in map)) map[key] = d.name
                }
            }
            return map
        },
        hasPeriod() {
            return this.headers.some(h => this.slug(h) === 'period')
        },
        metricCount() {
            const names = new Set(this.definitions.map(d => d.name))
            return new Set(this.headers.map(h => this.readAs(h)).filter(r => names.has(r))).size
        },
        canSubmit() {
            if (!this.form.file || !this.form.portfolio_id) return false
            // An Excel file cannot be read in the browser; the importer checks it.
            return !this.isCsv || (this.hasPeriod && this.metricCount > 0)
        },
    },

    methods: {
        submit() {
            this.form.post(route('credit-loss-data.import'), { forceFormData: true })
        },

        onFile(file) {
            this.form.file = null
            this.fileError = ''
            this.headers = []
            this.previewRows = []
            this.dataRowCount = 0
            this.isCsv = false
            if (!file) return
            if (!/\.(csv|txt|xlsx)$/i.test(file.name)) {
                this.fileError = 'Choose a CSV, TXT or XLSX file.'
                return
            }
            if (file.size > 2 * 1024 * 1024) {
                this.fileError = 'The file is larger than 2 MB. Split it into smaller files.'
                return
            }
            this.form.file = file
            if (/\.(csv|txt)$/i.test(file.name)) {
                this.isCsv = true
                this.readHeaders(file)
            }
        },

        readHeaders(file) {
            const reader = new FileReader()
            reader.onload = (e) => {
                const lines = String(e.target.result).split(/\r?\n/).filter(l => l.trim() !== '')
                if (!lines.length) {
                    this.fileError = 'The file is empty.'
                    return
                }
                this.headers = this.parseRow(lines[0])
                this.previewRows = lines.slice(1, 4).map(l => this.parseRow(l))
                this.dataRowCount = lines.length - 1
            }
            reader.onerror = () => { this.fileError = 'The file could not be read.' }
            reader.readAsText(file)
        },

        parseRow(line) {
            const out = []
            let cur = '', quoted = false
            for (const ch of line) {
                if (ch === '"') quoted = !quoted
                else if (ch === ',' && !quoted) { out.push(cur.trim()); cur = '' }
                else cur += ch
            }
            out.push(cur.trim())
            return out
        },

        /** The heading as the importer sees it (Laravel Excel slugs headings with "_"). */
        slug(h) {
            return String(h).toLowerCase().replace(/-+/g, '_').replace(/@/g, '_at_')
                .replace(/[^a-z0-9_\s]/g, '').replace(/[_\s]+/g, '_').replace(/^_+|_+$/g, '')
        },

        readAs(h) {
            return this.recognised[this.slug(h)] || ''
        },
    },
}
</script>
