<template>
    <app-layout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
                        <Link :href="route('eir-data.index')" class="hover:text-maiic-700">EIR Data</Link><span>/</span><span class="font-medium text-maiic-700">Data Intake</span>
                    </div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight flex items-center">
                        <svg class="w-6 h-6 mr-2 text-maiic-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                        </svg>
                        EIR Data Intake
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">Load contract terms, transactions, fees and loan-level interest evidence</p>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="px-3 py-1 bg-maiic-100 text-maiic-800 text-xs font-medium rounded-full">
                        Coverage: {{ coverage.covered }}/{{ coverage.total }} contracts
                    </div>
                </div>
            </div>
        </template>

        <div class="max-w-6xl mx-auto space-y-6">

            <!-- Import card -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-1">Import EIR data</h3>
                <div v-if="autoDetectedType" class="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ autoDetectedType }} was detected from the file headers and selected automatically.
                </div>
                <p class="text-sm text-gray-500 mb-4">Choose the source type and upload the corresponding CSV or Excel file. The system detects and validates the expected columns automatically.</p>
                <div v-if="importType === 'contract_transactions'" class="mb-4 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                    <div class="font-semibold">Extract B remaining-schedule evidence</div>
                    <p class="mt-1">
                        Rows marked <strong>Scheduled</strong> are staged as the remaining schedule for comparison. They never replace the original version-1 schedule used by EIR.
                    </p>
                    <p class="mt-1">
                        Rows marked <strong>Actual</strong> are retained as transaction evidence and do not appear in the contractual Cash Flows tab. Fee component is optional; a blank fee does not block principal and interest.
                    </p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <jet-label class="text-sm font-medium text-gray-900">Import type</jet-label>
                        <select v-model="importType" class="form-input mt-2" :disabled="processing" @change="resetAnalysis">
                            <option value="contract_master">Contract master (Extract A) — facility terms, monthly</option>
                            <option value="schedule">Repayment schedule (per contract, once)</option>
                            <option value="fees">Fees and transaction costs</option>
                            <option value="contract_transactions">Contract transactions (Extract B) — scheduled and actual cash flows</option>
                            <option value="gl_interest">GL interest postings (Extract C) — what the ledger posted</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <jet-label class="text-sm font-medium text-gray-900">File (CSV / XLSX)</jet-label>
                        <input
                            type="file"
                            @change="onFile"
                            accept=".csv,.txt,.xlsx,.xls,.ods"
                            class="mt-2 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-maiic-50 file:text-maiic-700 hover:file:bg-maiic-100 file:cursor-pointer border border-gray-300 rounded-md"
                            :disabled="processing"
                        />
                    </div>
                </div>
                <div class="mt-4 flex justify-end">
                    <button
                        @click="analyze(true)"
                        :disabled="processing || !file"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gradient-to-r from-maiic-600 to-maiic-600 hover:from-maiic-700 hover:to-maiic-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-maiic-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                    >
                        {{ processing ? 'Importing…' : 'Import file' }}
                    </button>
                </div>
            </div>

            <!-- Import result -->
            <div v-if="result" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Import result</h3>

                <div
                    v-if="importType === 'contract_transactions' && result.scheduled_rows_routed > 0 && result.loaded_rows === 0"
                    class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                >
                    <div class="font-semibold">Scheduled rows were found, but none were staged.</div>
                    <p class="mt-1">Review the contract-level reasons below. The original Cash Flows tab is unchanged.</p>
                    <a v-if="exceptionDownloadUrl" :href="exceptionDownloadUrl" class="mt-2 inline-block font-semibold underline">Download exception report</a>
                </div>

                <div v-if="importType === 'schedule'" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                    <div class="bg-maiic-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-maiic-700">{{ result.loaded_contracts }}</div>
                        <div class="text-xs text-maiic-800 mt-1">Contracts loaded</div>
                    </div>
                    <div class="bg-maiic-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-maiic-700">{{ result.loaded_rows }}</div>
                        <div class="text-xs text-maiic-800 mt-1">Schedule rows</div>
                    </div>
                    <div class="bg-amber-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-amber-700">{{ Object.keys(result.held || {}).length }}</div>
                        <div class="text-xs text-amber-800 mt-1">Held (not on tape)</div>
                    </div>
                    <div class="bg-red-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-red-700">{{ Object.keys(result.skipped || {}).length }}</div>
                        <div class="text-xs text-red-800 mt-1">Rejected</div>
                    </div>
                </div>

                <div v-if="importType === 'contract_transactions'" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                    <div class="bg-maiic-50 rounded-lg p-4 text-center"><div class="text-2xl font-bold text-maiic-700">{{ result.scheduled_rows_routed }}</div><div class="text-xs text-maiic-800 mt-1">Scheduled rows routed</div></div>
                    <div class="bg-green-50 rounded-lg p-4 text-center"><div class="text-2xl font-bold text-green-700">{{ result.loaded_rows }}</div><div class="text-xs text-green-800 mt-1">Remaining rows staged</div></div>
                    <div class="bg-red-50 rounded-lg p-4 text-center"><div class="text-2xl font-bold text-red-700">{{ scheduleRowsNotLoaded }}</div><div class="text-xs text-red-800 mt-1">Rows not staged</div></div>
                    <div class="bg-maiic-50 rounded-lg p-4 text-center"><div class="text-2xl font-bold text-maiic-700">{{ result.loaded_contracts }}</div><div class="text-xs text-maiic-800 mt-1">Contracts staged</div></div>
                </div>

                <div v-if="importType === 'contract_transactions'" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                    <div class="bg-maiic-50 rounded-lg p-4 text-center"><div class="text-2xl font-bold text-maiic-700">{{ result.actual_rows_loaded }}</div><div class="text-xs text-maiic-800 mt-1">New actual rows retained</div></div>
                    <div class="bg-maiic-50 rounded-lg p-4 text-center"><div class="text-2xl font-bold text-maiic-700">{{ result.fee_rows_routed }}</div><div class="text-xs text-maiic-800 mt-1">Fee rows routed</div></div>
                    <div class="bg-amber-50 rounded-lg p-4 text-center"><div class="text-2xl font-bold text-amber-700">{{ Object.keys(result.held || {}).length }}</div><div class="text-xs text-amber-800 mt-1">Contracts held</div></div>
                    <div class="bg-red-50 rounded-lg p-4 text-center"><div class="text-2xl font-bold text-red-700">{{ Object.keys(result.skipped || {}).length }}</div><div class="text-xs text-red-800 mt-1">Contracts rejected</div></div>
                </div>

                <div v-else-if="importType === 'contract_master'" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                    <div class="bg-maiic-50 rounded-lg p-4 text-center"><div class="text-2xl font-bold text-maiic-700">{{ result.created }}</div><div class="text-xs text-maiic-800 mt-1">Contracts created</div></div>
                    <div class="bg-maiic-50 rounded-lg p-4 text-center"><div class="text-2xl font-bold text-maiic-700">{{ result.updated }}</div><div class="text-xs text-maiic-800 mt-1">Terms updated</div></div>
                    <div class="bg-gray-50 rounded-lg p-4 text-center"><div class="text-2xl font-bold text-gray-700">{{ result.unchanged }}</div><div class="text-xs text-gray-800 mt-1">Unchanged</div></div>
                    <div class="bg-maiic-50 rounded-lg p-4 text-center"><div class="text-2xl font-bold text-maiic-700">{{ result.fee_rows_routed }}</div><div class="text-xs text-maiic-800 mt-1">Origination fees → pending</div></div>
                </div>

                <div v-else-if="importType === 'gl_interest'" class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-4">
                    <div class="bg-maiic-50 rounded-lg p-4 text-center"><div class="text-2xl font-bold text-maiic-700">{{ result.loaded_rows }}</div><div class="text-xs text-maiic-800 mt-1">Postings loaded</div></div>
                    <div class="bg-gray-50 rounded-lg p-4 text-center"><div class="text-2xl font-bold text-gray-700">{{ result.annual_summary_rows || 0 }}</div><div class="text-xs text-gray-800 mt-1">Annual totals excluded</div></div>
                    <div class="bg-amber-50 rounded-lg p-4 text-center"><div class="text-2xl font-bold text-amber-700">{{ result.restated_rows }}</div><div class="text-xs text-amber-800 mt-1">GL restatements applied</div></div>
                    <div class="bg-amber-50 rounded-lg p-4 text-center"><div class="text-2xl font-bold text-amber-700">{{ result.negative_rows }}</div><div class="text-xs text-amber-800 mt-1">Negative rows (check sign)</div></div>
                    <div class="bg-gray-50 rounded-lg p-4 text-center"><div class="text-2xl font-bold text-gray-700">{{ Number(result.total_posted).toLocaleString() }}</div><div class="text-xs text-gray-800 mt-1">Total interest posted</div></div>
                </div>

                <!-- Posted interest by period - eyeball against the GL before reconciling -->
                <div v-if="importType === 'gl_interest' && result.periods" class="mb-4">
                    <h4 class="text-sm font-medium text-gray-900 mb-2">Interest posted by period - check these against the GL before the reconciliation is run:</h4>
                    <div class="flex flex-wrap gap-2">
                        <span v-for="(total, period) in result.periods" :key="period" class="px-3 py-1 bg-maiic-50 text-maiic-800 text-xs rounded-full">
                            {{ period }}: {{ Number(total).toLocaleString() }}
                        </span>
                    </div>
                </div>

                <div v-else-if="importType === 'fees'" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                    <div class="bg-maiic-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-maiic-700">{{ result.loaded_rows }}</div>
                        <div class="text-xs text-maiic-800 mt-1">Lines loaded as pending</div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-gray-700">{{ result.skipped_rows }}</div>
                        <div class="text-xs text-gray-800 mt-1">Skipped (blank/zero)</div>
                    </div>
                    <div class="bg-amber-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-amber-700">{{ result.negative_lines }}</div>
                        <div class="text-xs text-amber-800 mt-1">Negative (netting) lines</div>
                    </div>
                    <div class="bg-amber-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-amber-700">{{ Object.keys(result.unknown_types || {}).length }}</div>
                        <div class="text-xs text-amber-800 mt-1">Unknown fee types → other</div>
                    </div>
                </div>

                <!-- Fee totals vs GL -->
                <div v-if="importType === 'fees' && result.totals_by_type" class="mb-4">
                    <h4 class="text-sm font-medium text-gray-900 mb-2">Totals by fee type - eyeball these against the GL fee accounts:</h4>
                    <div class="flex flex-wrap gap-2">
                        <span v-for="(total, type) in result.totals_by_type" :key="type" class="px-3 py-1 bg-maiic-50 text-maiic-800 text-xs rounded-full">
                            {{ type }}: {{ Number(total).toLocaleString() }}
                        </span>
                    </div>
                </div>

                <!-- Named reasons -->
                <div v-if="reasonEntries.length" class="border border-gray-200 rounded-lg overflow-hidden">
                    <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3">
                        <span class="text-sm font-medium text-gray-700">{{ reasonEntries.length }} contract-level exception(s)</span>
                        <a v-if="exceptionDownloadUrl" :href="exceptionDownloadUrl" class="text-sm font-semibold text-maiic-700 underline">Download CSV</a>
                    </div>
                    <table class="maiic-table">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Contract</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="entry in reasonEntries" :key="entry.contract + entry.status">
                                <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ entry.contract }}</td>
                                <td class="px-4 py-2">
                                    <span class="px-2 py-0.5 text-xs rounded-full" :class="entry.status === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800'">
                                        {{ entry.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-600">{{ entry.reason }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="result.coverage" class="mt-4 text-sm text-gray-600">
                    Schedule coverage is now
                    <span class="font-semibold text-gray-900">{{ result.coverage.covered }}/{{ result.coverage.total }}</span>
                    contracts on the latest loan tape.
                </div>
            </div>

            <div v-if="queuedImport" class="bg-maiic-50 border border-maiic-200 rounded-lg p-5 text-sm text-maiic-900">
                <div class="flex items-start gap-3">
                    <svg v-if="!isImportTerminal" class="mt-0.5 h-5 w-5 animate-spin text-maiic-700" viewBox="0 0 24 24" fill="none">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                    </svg>
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold">{{ importStatusHeading }}</div>
                        <p class="mt-1">{{ queuedImport.name }}</p>
                        <p v-if="!isImportTerminal" class="mt-1 text-maiic-700">This page checks progress automatically; you do not need to refresh it.</p>
                        <p v-if="pollError" class="mt-2 text-amber-700">{{ pollError }}</p>
                        <div v-if="isImportTerminal" class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-xs">
                            <span>Rows processed: <strong>{{ queuedImport.rows_processed || 0 }}</strong></span>
                            <span>Rows inserted: <strong>{{ queuedImport.records || 0 }}</strong></span>
                            <span>Contract exceptions: <strong>{{ queuedImport.failed_records || 0 }}</strong></span>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-4">
                            <a :href="importHistoryUrl" class="text-maiic-700 underline font-medium">Open Import History</a>
                            <a v-if="exceptionDownloadUrl" :href="exceptionDownloadUrl" class="text-maiic-700 underline font-medium">Download exception report</a>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-800">
                {{ error }}
            </div>
        </div>

        <teleport to="head">
            <title>EIR Schedule & Fee Intake</title>
        </teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetLabel from '@/Jetstream/Label.vue'
import { Link } from '@inertiajs/vue3'
import { markRaw } from 'vue'
import axios from 'axios'

export default {
    props: {
        coverage: Object,
        templates: Object,
        fieldSpec: Object,
    },
    components: { AppLayout, JetLabel, Link },
    data() {
        return {
            importType: 'contract_master',
            file: null,
            analysis: null,
            mapping: {},      // header -> target field
            transforms: {},   // target field -> transform
            saveTemplate: false,
            processing: false,
            stage: null,
            result: null,
            queuedImport: null,
            importHistoryUrl: null,
            importStatusUrl: null,
            exceptionDownloadUrl: null,
            pollTimer: null,
            pollError: null,
            terminalResultRetries: 0,
            error: null,
            autoDetectedType: null,
        }
    },
    computed: {
        allTargetFields() {
            const spec = this.fieldSpec[this.importType]
            return [...spec.required, ...spec.optional]
        },
        mappedTargets() {
            return Object.values(this.mapping).filter(Boolean)
        },
        missingRequired() {
            return this.fieldSpec[this.importType].required.filter(f => !this.mappedTargets.includes(f))
        },
        reasonEntries() {
            if (!this.result) return []
            const entries = []
            for (const [contract, reason] of Object.entries(this.result.held || {})) {
                entries.push({ contract, status: 'held', reason })
            }
            for (const [contract, reason] of Object.entries(this.result.skipped || {})) {
                entries.push({ contract, status: 'rejected', reason })
            }
            // Loaded, but a reviewer still has to look: a contract created
            // without the terms the solver needs, or a GL figure restated
            // against a period that may already have been reconciled.
            for (const [contract, reason] of Object.entries(this.result.incomplete || {})) {
                entries.push({ contract, status: 'incomplete', reason })
            }
            for (const [contract, reason] of Object.entries(this.result.restatements || {})) {
                entries.push({ contract, status: 'restated', reason })
            }
            return entries
        },
        scheduleRowsNotLoaded() {
            if (!this.result) return 0
            return Math.max(0, Number(this.result.scheduled_rows_routed || 0) - Number(this.result.loaded_rows || 0))
        },
        isImportTerminal() {
            return ['completed', 'failed'].includes(this.queuedImport?.status)
        },
        importStatusHeading() {
            if (this.queuedImport?.status === 'completed') return 'Import completed'
            if (this.queuedImport?.status === 'failed') return 'Import failed'
            if (this.queuedImport?.status === 'processing') return 'Import processing'
            return 'Import queued successfully'
        },
    },
    beforeUnmount() {
        this.stopImportPolling()
    },
    methods: {
        onFile(e) {
            this.file = e.target.files[0]
            this.resetAnalysis()
        },
        resetAnalysis() {
            this.stopImportPolling()
            this.analysis = null
            this.mapping = {}
            this.transforms = {}
            this.result = null
            this.queuedImport = null
            this.importHistoryUrl = null
            this.importStatusUrl = null
            this.exceptionDownloadUrl = null
            this.pollError = null
            this.terminalResultRetries = 0
            this.error = null
            this.autoDetectedType = null
        },
        profileFor(header) {
            return this.analysis?.profile?.[header] || null
        },
        typeClass(header) {
            return {
                number: 'bg-blue-50 text-blue-700',
                date: 'bg-purple-50 text-purple-700',
                text: 'bg-gray-100 text-gray-500',
                empty: 'bg-amber-50 text-amber-700',
            }[this.profileFor(header)?.type] || 'bg-gray-100 text-gray-500'
        },
        /**
         * Apply the transform inferred from the column's values. Only ever
         * fills a blank choice - once an operator picks a transform it is
         * theirs, and re-analysing the same file must not overwrite it.
         */
        applySuggestedTransform(header) {
            const target = this.mapping[header]
            if (!target || this.transforms[target]) return
            const t = this.profileFor(header)?.suggested_transform || this.defaultTransformFor(target)
            if (t) this.transforms[target] = t
        },
        /** Distinct values beyond the dozen shown, so a key column reads as one. */
        moreValues(header) {
            const p = this.profileFor(header)
            if (!p) return 0
            return Math.max(0, p.distinct - p.values.length)
        },
        previewFor(header) {
            if (!this.analysis?.preview) return ''
            return this.analysis.preview
                .map(row => row[header])
                .filter(v => v !== null && v !== undefined && String(v).trim() !== '')
                .slice(0, 3)
                .join(' · ')
        },
        defaultTransformFor(field) {
            if (['due_date', 'transaction_date'].includes(field)) return 'date'
            if (['principal_due', 'interest_due', 'fee_due', 'amount', 'principal_component', 'interest_component', 'fee_component', 'total_amount', 'balance_after_transaction'].includes(field)) return 'number'
            return undefined
        },
        async requestAnalysis(importType) {
            const data = new FormData()
            data.append('file', this.file)
            data.append('import_type', importType)
            const response = await axios.post(this.route('eir-intake.analyze'), data)
            return response.data
        },
        async analyze(autoImport = false) {
            if (!this.file) return
            this.processing = true
            this.stage = 'analyze'
            this.error = null
            this.autoDetectedType = null
            try {
                const selectedType = this.importType
                const analysis = await this.requestAnalysis(selectedType)
                if (analysis.import_type && analysis.import_type !== selectedType) {
                    this.importType = analysis.import_type
                    this.autoDetectedType = 'Contract transactions (Extract B)'
                }
                // Analysis data is display-only. Avoid recursively proxying
                // every profile/preview cell on the browser's main thread.
                this.analysis = markRaw(analysis)
                // Pre-fill from the saved template, then transforms. The type
                // read from the column's own values leads - it is the only
                // thing that knows a date column arrived as Excel serials -
                // and the field-name defaults fill in where the values were
                // inconclusive, such as an amount column of small integers.
                this.mapping = { ...analysis.mapping }
                for (const [header, field] of Object.entries(this.mapping)) {
                    const t = analysis.profile?.[header]?.suggested_transform || this.defaultTransformFor(field)
                    if (t) this.transforms[field] = t
                }
                if (autoImport) {
                    if (this.missingRequired.length) {
                        this.error = `The file is missing required columns: ${this.missingRequired.join(', ')}. Check that the correct source type and file were selected.`
                        return
                    }
                    await this.runImport()
                }
            } catch (e) {
                this.error = e.response?.data?.error || e.response?.data?.message || 'Failed to analyze file.'
            } finally {
                this.processing = false
                this.stage = null
            }
        },
        async runImport() {
            if (!this.file || this.missingRequired.length) return
            this.processing = true
            this.stage = 'import'
            this.error = null
            try {
                // Apply default transforms for any mapped field without one.
                for (const field of this.mappedTargets) {
                    if (!this.transforms[field]) {
                        const t = this.defaultTransformFor(field)
                        if (t) this.transforms[field] = t
                    }
                }

                if (this.saveTemplate) {
                    await axios.post(this.route('eir-intake.save-template'), {
                        import_type: this.importType,
                        mappings: Object.entries(this.mapping)
                            .filter(([, field]) => field)
                            .map(([header, field]) => ({
                                source_header: header,
                                target_field: field,
                                transform: this.transforms[field] || null,
                            })),
                    })
                }

                const data = new FormData()
                data.append('file', this.file)
                data.append('import_type', this.importType)
                data.append('mapping', JSON.stringify(
                    Object.fromEntries(Object.entries(this.mapping).filter(([, f]) => f))
                ))
                data.append('transforms', JSON.stringify(this.transforms))
                const { data: response } = await axios.post(this.route('eir-intake.import'), data)
                if (response.queued) {
                    this.queuedImport = response.import
                    this.importHistoryUrl = response.history_url
                    this.importStatusUrl = response.status_url
                    this.exceptionDownloadUrl = null
                    this.pollError = null
                    this.terminalResultRetries = 0
                    this.result = null
                    this.pollImportStatus()
                } else {
                    this.result = response.result
                }
            } catch (e) {
                this.error = e.response?.data?.error || e.response?.data?.message || 'Import failed.'
            } finally {
                this.processing = false
                this.stage = null
            }
        },
        stopImportPolling() {
            if (this.pollTimer) window.clearTimeout(this.pollTimer)
            this.pollTimer = null
        },
        scheduleImportPoll(delay = 1500) {
            this.stopImportPolling()
            this.pollTimer = window.setTimeout(() => this.pollImportStatus(), delay)
        },
        async pollImportStatus() {
            if (!this.importStatusUrl || !this.queuedImport) return

            try {
                const { data } = await axios.get(this.importStatusUrl)
                this.queuedImport = data.import
                this.exceptionDownloadUrl = data.exception_url
                this.pollError = null

                if (data.import_type) this.importType = data.import_type
                if (data.result) {
                    this.result = markRaw(data.result)
                    this.terminalResultRetries = 0
                }

                // The job stores its terminal status immediately before its
                // detailed audit result. Allow that very small race to settle.
                if (data.terminal && data.import.status === 'completed' && !data.result && this.terminalResultRetries < 4) {
                    this.terminalResultRetries++
                    this.scheduleImportPoll(500)
                    return
                }

                if (!data.terminal) this.scheduleImportPoll()
            } catch (e) {
                this.pollError = 'Could not refresh the status just now. Retrying automatically.'
                this.scheduleImportPoll(3000)
            }
        },
    },
}
</script>

<style scoped>
.form-input {
    @apply block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-maiic-500 focus:border-maiic-500 transition-all duration-200;
}
</style>
