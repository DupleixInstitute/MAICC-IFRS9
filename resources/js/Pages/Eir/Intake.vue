<template>
    <app-layout>
        <template #header>
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
                        <Link :href="route('eir-data.index')" class="hover:text-maiic-700">EIR Data</Link><span>/</span><span class="font-medium text-maiic-700">Data Intake</span>
                    </div>
                    <h2 class="text-xl font-semibold text-gray-800">EIR Data Intake</h2>
                    <p class="mt-1 text-sm text-gray-600">Load contract terms, transactions, fees, drawdowns, reference rates and the interest the ledger posted</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="maiic-badge maiic-badge-green" title="Contracts on the latest loan tape that have a repayment schedule">Schedules: {{ coverage.covered }}/{{ coverage.total }} contracts</span>
                    <a :href="sampleUrl" class="secondary-btn" :title="'The ' + typeLabel + ' columns with worked example rows'">Download sample CSV</a>
                </div>
            </div>
        </template>

        <div class="w-full space-y-6">

            <!-- Import: Upload / History -->
            <div>
                <InPageTabs v-model="tab" :tabs="tabs" flush/>
                <div class="maiic-panel rounded-t-none">

                    <div v-if="tab === 'upload'" class="grid gap-6 p-5 lg:grid-cols-3">
                        <div class="space-y-5 lg:col-span-2">
                            <div v-if="autoDetectedType" class="flex items-start gap-2 rounded-lg border border-maiic-200 bg-maiic-50 px-4 py-3 text-sm text-maiic-800">
                                <svg class="mt-0.5 h-4 w-4 flex-none" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                                <span>{{ autoDetectedType }} was recognised from the file's headers and selected for you.</span>
                            </div>

                            <div>
                                <label class="maiic-flabel" for="eir-import-type">Import type</label>
                                <select id="eir-import-type" v-model="importType" class="maiic-select" :disabled="processing" @change="resetAnalysis">
                                    <option v-for="t in importTypes" :key="t.key" :value="t.key">{{ t.label }}</option>
                                </select>
                            </div>

                            <FileDrop label="File" accept=".csv,.txt,.xlsx,.xls,.ods" placeholder="Choose a CSV or Excel file"
                                      hint="CSV, XLSX, XLS or ODS, up to 20 MB" :disabled="processing" @file="onFile"/>

                            <div class="flex items-center justify-end gap-2 border-t border-gray-200 pt-4">
                                <button type="button" class="primary-btn" :disabled="processing || !file" @click="analyze(true)">
                                    {{ processing ? 'Importing...' : 'Import file' }}
                                </button>
                            </div>
                        </div>

                        <ImportHowTo :title="'How to import ' + typeShort" :sample-url="sampleUrl" :sample-label="'Download ' + sampleFileName"
                                     sample-title="This type's columns with worked example rows. Its headers map automatically.">
                            <li>Pick the import type, then download its sample CSV. A file with the same headers needs no column matching.</li>
                            <li v-for="(step, i) in typeSteps" :key="i">{{ step }}</li>
                            <li>Choose the file and press Import file. It runs in the background and this page shows the progress.</li>
                            <li>Contracts that cannot be loaded are listed with a reason and can be downloaded as an exception report. Every upload is kept on the History tab.</li>
                        </ImportHowTo>
                    </div>

                    <div v-else>
                        <ImportHistoryTable :imports="pagedHistory"
                                            :caption="historyCaption"
                                            :empty-text="'No ' + typeShort + ' file has been imported yet. Use the Upload tab to import the first one.'"/>
                        <LocalPager v-model="historyPage" :total="typeHistory.length" class="border-t border-gray-100"/>
                    </div>
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

                <div v-if="importType === 'disbursements'" class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-4">
                    <div class="bg-maiic-50 rounded-lg p-4 text-center"><div class="text-2xl font-bold text-maiic-700">{{ result.loaded_rows }}</div><div class="text-xs text-maiic-800 mt-1">Drawdowns loaded</div></div>
                    <div class="bg-gray-50 rounded-lg p-4 text-center"><div class="text-2xl font-bold text-gray-700">{{ result.already_stored }}</div><div class="text-xs text-gray-800 mt-1">Already stored</div></div>
                    <div class="bg-gray-50 rounded-lg p-4 text-center"><div class="text-2xl font-bold text-gray-700">{{ result.duplicate_source_rows }}</div><div class="text-xs text-gray-800 mt-1">Repeated rows in the file</div></div>
                    <div class="bg-maiic-50 rounded-lg p-4 text-center"><div class="text-2xl font-bold text-maiic-700">{{ result.contracts }}</div><div class="text-xs text-maiic-800 mt-1">Facilities in the file</div></div>
                    <div class="bg-maiic-50 rounded-lg p-4 text-center"><div class="text-2xl font-bold text-maiic-700">{{ Number(result.total_amount || 0).toLocaleString() }}</div><div class="text-xs text-maiic-800 mt-1">Total drawn in the file</div></div>
                </div>

                <div v-if="importType === 'disbursements' && result.loaded_rows" class="mb-4 text-sm text-gray-600">
                    Drawdowns in the file run from {{ result.first_date || '-' }} to {{ result.last_date || '-' }}.
                    <Link :href="route('eir-drawdowns.index')" class="text-maiic-700 underline font-medium">Open Drawdowns</Link>
                    to see the undrawn commitment per facility.
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

                <div v-else-if="importType === 'reference_rates'" class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-4">
                    <div class="bg-maiic-50 rounded-lg p-4 text-center"><div class="text-2xl font-bold text-maiic-700">{{ result.loaded_rows }}</div><div class="text-xs text-maiic-800 mt-1">Rates loaded</div></div>
                    <div class="bg-gray-50 rounded-lg p-4 text-center"><div class="text-2xl font-bold text-gray-700">{{ result.unchanged }}</div><div class="text-xs text-gray-800 mt-1">Already stored</div></div>
                    <div class="bg-maiic-50 rounded-lg p-4 text-center"><div class="text-2xl font-bold text-maiic-700">{{ result.rate_changes }}</div><div class="text-xs text-maiic-800 mt-1">Rate changes in the file</div></div>
                    <div class="bg-gray-50 rounded-lg p-4 text-center"><div class="text-2xl font-bold text-gray-700">{{ result.repeated_rate_rows }}</div><div class="text-xs text-gray-800 mt-1">Rows repeating the previous rate</div></div>
                    <div class="bg-maiic-50 rounded-lg p-4 text-center"><div class="text-2xl font-bold text-maiic-700">{{ result.series && result.series.current_rate !== null ? Number(result.series.current_rate).toFixed(2) + '%' : '-' }}</div><div class="text-xs text-maiic-800 mt-1">{{ result.index_code }} rate now in force</div></div>
                </div>

                <div v-if="importType === 'reference_rates' && result.series" class="mb-4 text-sm text-gray-600">
                    The stored {{ result.series.index_code }} series now holds
                    <span class="font-semibold text-gray-900">{{ result.series.rows }}</span> rows and
                    <span class="font-semibold text-gray-900">{{ result.series.changes }}</span> rate changes from
                    {{ result.series.first_date }} to {{ result.series.last_date }}.
                    <Link :href="route('eir-reference-rates.index')" class="text-maiic-700 underline font-medium">Open Reference Rates</Link>
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

            <div v-if="error" class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <svg class="mt-0.5 h-5 w-5 flex-none text-red-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                <div>
                    <div class="font-semibold">The file was not imported</div>
                    <p class="mt-1">{{ error }}</p>
                </div>
            </div>
        </div>

        <teleport to="head">
            <title>EIR Schedule & Fee Intake</title>
        </teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import InPageTabs from '@/Components/Data/InPageTabs.vue'
import ImportHistoryTable from '@/Components/Data/ImportHistoryTable.vue'
import ImportHowTo from '@/Components/Data/ImportHowTo.vue'
import FileDrop from '@/Components/Data/FileDrop.vue'
import LocalPager from '@/Components/Data/LocalPager.vue'
import { Link } from '@inertiajs/vue3'
import { markRaw } from 'vue'
import axios from 'axios'

export default {
    props: {
        coverage: Object,
        templates: Object,
        fieldSpec: Object,
        initialType: { type: String, default: 'contract_master' },
        recentImports: { type: Array, default: () => [] },
    },
    components: { AppLayout, Link, InPageTabs, ImportHistoryTable, ImportHowTo, FileDrop, LocalPager },
    data() {
        return {
            importType: this.initialType || 'contract_master',
            tab: 'upload',
            historyPage: 1,
            importTypes: [
                { key: 'contract_master', short: 'contract master', label: 'Contract master (Extract A), facility terms, monthly' },
                { key: 'schedule', short: 'repayment schedules', label: 'Repayment schedule (per contract, once)' },
                { key: 'fees', short: 'fees', label: 'Fees and transaction costs' },
                { key: 'contract_transactions', short: 'contract transactions', label: 'Contract transactions (Extract B), scheduled and actual cash flows' },
                { key: 'gl_interest', short: 'GL interest postings', label: 'GL interest postings (Extract C), what the ledger posted' },
                { key: 'reference_rates', short: 'reference rates', label: 'Reference rates (File C), the prime lending rate by effective date' },
                { key: 'disbursements', short: 'drawdowns', label: 'Drawdowns, one row per tranche paid out' },
            ],
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
    watch: {
        importType() { this.historyPage = 1 },
    },
    computed: {
        typeLabel() {
            return (this.importTypes.find(t => t.key === this.importType) || {}).label || this.importType
        },
        typeShort() {
            return (this.importTypes.find(t => t.key === this.importType) || {}).short || this.importType
        },
        /** What is particular to the chosen type, from the importer's own rules. */
        typeSteps() {
            return {
                contract_transactions: [
                    'Rows marked Scheduled are kept as the remaining schedule for comparison. They never replace the original schedule the EIR uses.',
                    'Rows marked Actual are kept as transaction evidence and do not appear in Cash Flows. The fee column is optional.',
                ],
                reference_rates: [
                    'Write dates year first (yyyy-mm-dd). A file with any other date shape is refused as a whole, naming the column and the first bad row.',
                    'Dates must rise down the file. Rates are percentages, such as 25.30. A row that repeats the previous rate is loaded but is not a rate change.',
                ],
                disbursements: [
                    'One row per tranche, dates written year first (yyyy-mm-dd). Any other date shape refuses the whole file.',
                    'Every amount must be above zero. Loading the same file twice adds nothing: rows are matched on facility, date, amount, tranche and reference.',
                ],
            }[this.importType] || []
        },
        tabs() {
            return [
                { key: 'upload', label: 'Upload' },
                { key: 'history', label: 'History', count: this.typeHistory.length },
            ]
        },
        /** The imports log rows of the chosen type, named "EIR <type>: <file>". */
        typeHistory() {
            const prefix = 'EIR ' + this.importType + ':'
            return this.recentImports.filter(r => String(r.name || '').startsWith(prefix))
        },
        pagedHistory() {
            return this.typeHistory.slice((this.historyPage - 1) * 15, this.historyPage * 15)
        },
        historyCaption() {
            return this.typeHistory.length.toLocaleString() + ' uploads of ' + this.typeShort + '. The Imports screen logs every kind.'
        },
        /** Named for the type so a folder of downloads stays readable. */
        sampleFileName() {
            return `${this.importType}_sample.csv`
        },
        sampleUrl() {
            return this.route('eir-intake.sample', { type: this.importType })
        },
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
            // Notes are keyed by contract (Extract A) or are a plain list
            // (the reference-rate series, where a note is about a row).
            for (const [key, reason] of Object.entries(this.result.notes || {})) {
                entries.push({ contract: Number.isNaN(Number(key)) ? key : 'file', status: 'note', reason })
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
        onFile(file) {
            this.file = file
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
                    this.autoDetectedType = analysis.import_type === 'reference_rates'
                        ? 'Reference rates (File C)'
                        : 'Contract transactions (Extract B)'
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
