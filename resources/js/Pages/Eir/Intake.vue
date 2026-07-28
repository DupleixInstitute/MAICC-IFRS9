<template>
    <app-layout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight flex items-center">
                        <svg class="w-6 h-6 mr-2 text-indigo-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                        </svg>
                        EIR Schedule Intake
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">Import contractual repayment schedules and origination fees — the EIR solver's inputs</p>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="px-3 py-1 bg-indigo-100 text-indigo-800 text-xs font-medium rounded-full">
                        Coverage: {{ coverage.covered }}/{{ coverage.total }} contracts
                    </div>
                </div>
            </div>
        </template>

        <div class="max-w-6xl mx-auto space-y-6">

            <!-- Step 1: choose type + file -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-1">1 · Upload a file</h3>
                <p class="text-sm text-gray-500 mb-4">
                    Any CSV or Excel shape works — you map its columns once and the template is reused for every future file of the same shape.
                </p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <jet-label class="text-sm font-medium text-gray-900">Import type</jet-label>
                        <select v-model="importType" class="form-input mt-2" :disabled="processing" @change="resetAnalysis">
                            <option value="schedule">Repayment schedule (per contract, once)</option>
                            <option value="fees">Origination fees</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <jet-label class="text-sm font-medium text-gray-900">File (CSV / XLSX)</jet-label>
                        <input
                            type="file"
                            @change="onFile"
                            accept=".csv,.txt,.xlsx,.xls,.ods"
                            class="mt-2 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 file:cursor-pointer border border-gray-300 rounded-md"
                            :disabled="processing"
                        />
                    </div>
                </div>
                <div class="mt-4 flex justify-end">
                    <button
                        @click="analyze"
                        :disabled="processing || !file"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gradient-to-r from-maiic-600 to-maiic-600 hover:from-maiic-700 hover:to-maiic-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-maiic-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                    >
                        {{ processing && stage === 'analyze' ? 'Analyzing…' : 'Analyze headers' }}
                    </button>
                </div>
            </div>

            <!-- Step 2: mapping -->
            <div v-if="analysis" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-1">2 · Map columns</h3>
                <p class="text-sm text-gray-500 mb-4">
                    Connect the file's columns (left) to the system fields (right).
                    Required fields:
                    <span
                        v-for="f in analysis.required_fields" :key="f"
                        class="inline-block px-2 py-0.5 mx-0.5 text-xs rounded-full"
                        :class="mappedTargets.includes(f) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                    >{{ f }}</span>
                </p>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">File column</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sample values</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Maps to</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transform</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="header in analysis.headers" :key="header" class="hover:bg-indigo-50 transition-colors duration-150">
                                <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ header }}</td>
                                <td class="px-4 py-2 text-xs text-gray-500">
                                    {{ previewFor(header) }}
                                </td>
                                <td class="px-4 py-2">
                                    <select v-model="mapping[header]" class="form-input text-sm">
                                        <option :value="undefined">— ignore —</option>
                                        <option v-for="f in allTargetFields" :key="f" :value="f">{{ f }}</option>
                                    </select>
                                </td>
                                <td class="px-4 py-2">
                                    <select v-model="transforms[mapping[header]]" class="form-input text-sm" :disabled="!mapping[header]">
                                        <option :value="undefined">none / text</option>
                                        <option value="number">number</option>
                                        <option value="percent">percent</option>
                                        <option value="date">date (auto)</option>
                                        <option value="date:d/m/Y">date (dd/mm/yyyy)</option>
                                        <option value="date:m/d/Y">date (mm/dd/yyyy)</option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex items-center justify-between">
                    <label class="flex items-center text-sm text-gray-700">
                        <input type="checkbox" v-model="saveTemplate" class="rounded border-gray-300 text-indigo-600 mr-2" />
                        Save this mapping as the template for future {{ importType }} files
                    </label>
                    <button
                        @click="runImport"
                        :disabled="processing || missingRequired.length > 0"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gradient-to-r from-maiic-600 to-teal-600 hover:from-maiic-700 hover:to-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-maiic-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                    >
                        {{ processing && stage === 'import' ? 'Importing…' : 'Run import' }}
                    </button>
                </div>
                <p v-if="missingRequired.length" class="mt-2 text-right text-xs text-red-600">
                    Unmapped required field(s): {{ missingRequired.join(', ') }}
                </p>
            </div>

            <!-- Step 3: result -->
            <div v-if="result" class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">3 · Result</h3>

                <div v-if="importType === 'schedule'" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                    <div class="bg-green-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-green-700">{{ result.loaded_contracts }}</div>
                        <div class="text-xs text-green-800 mt-1">Contracts loaded</div>
                    </div>
                    <div class="bg-green-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-green-700">{{ result.loaded_rows }}</div>
                        <div class="text-xs text-green-800 mt-1">Schedule rows</div>
                    </div>
                    <div class="bg-yellow-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-yellow-700">{{ Object.keys(result.held || {}).length }}</div>
                        <div class="text-xs text-yellow-800 mt-1">Held (not on tape)</div>
                    </div>
                    <div class="bg-red-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-red-700">{{ Object.keys(result.skipped || {}).length }}</div>
                        <div class="text-xs text-red-800 mt-1">Rejected</div>
                    </div>
                </div>

                <div v-else class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                    <div class="bg-green-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-green-700">{{ result.loaded_rows }}</div>
                        <div class="text-xs text-green-800 mt-1">Fee lines loaded</div>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-gray-700">{{ result.skipped_rows }}</div>
                        <div class="text-xs text-gray-800 mt-1">Skipped (blank/zero)</div>
                    </div>
                    <div class="bg-yellow-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-yellow-700">{{ result.negative_lines }}</div>
                        <div class="text-xs text-yellow-800 mt-1">Negative (netting) lines</div>
                    </div>
                    <div class="bg-yellow-50 rounded-lg p-4 text-center">
                        <div class="text-2xl font-bold text-yellow-700">{{ Object.keys(result.unknown_types || {}).length }}</div>
                        <div class="text-xs text-yellow-800 mt-1">Unknown fee types → other</div>
                    </div>
                </div>

                <!-- Fee totals vs GL -->
                <div v-if="importType === 'fees' && result.totals_by_type" class="mb-4">
                    <h4 class="text-sm font-medium text-gray-900 mb-2">Totals by fee type — eyeball these against the GL fee accounts:</h4>
                    <div class="flex flex-wrap gap-2">
                        <span v-for="(total, type) in result.totals_by_type" :key="type" class="px-3 py-1 bg-indigo-50 text-indigo-800 text-xs rounded-full">
                            {{ type }}: {{ Number(total).toLocaleString() }}
                        </span>
                    </div>
                </div>

                <!-- Named reasons -->
                <div v-if="reasonEntries.length" class="border border-gray-200 rounded-lg overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Contract</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr v-for="entry in reasonEntries" :key="entry.contract + entry.status">
                                <td class="px-4 py-2 text-sm font-medium text-gray-900">{{ entry.contract }}</td>
                                <td class="px-4 py-2">
                                    <span class="px-2 py-0.5 text-xs rounded-full" :class="entry.status === 'held' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800'">
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

            <div v-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-800">
                {{ error }}
            </div>
        </div>

        <teleport to="head">
            <title>EIR Schedule Intake</title>
        </teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetLabel from '@/Jetstream/Label.vue'
import axios from 'axios'

export default {
    props: {
        coverage: Object,
        templates: Object,
        fieldSpec: Object,
    },
    components: { AppLayout, JetLabel },
    data() {
        return {
            importType: 'schedule',
            file: null,
            analysis: null,
            mapping: {},      // header -> target field
            transforms: {},   // target field -> transform
            saveTemplate: true,
            processing: false,
            stage: null,
            result: null,
            error: null,
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
            return entries
        },
    },
    methods: {
        onFile(e) {
            this.file = e.target.files[0]
            this.resetAnalysis()
        },
        resetAnalysis() {
            this.analysis = null
            this.mapping = {}
            this.transforms = {}
            this.result = null
            this.error = null
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
            if (field === 'due_date') return 'date'
            if (['principal_due', 'interest_due', 'fee_due', 'amount'].includes(field)) return 'number'
            return undefined
        },
        async analyze() {
            if (!this.file) return
            this.processing = true
            this.stage = 'analyze'
            this.error = null
            try {
                const data = new FormData()
                data.append('file', this.file)
                data.append('import_type', this.importType)
                const { data: analysis } = await axios.post(this.route('eir-intake.analyze'), data)
                this.analysis = analysis
                // Pre-fill from the saved template, then default transforms.
                this.mapping = { ...analysis.mapping }
                for (const field of Object.values(this.mapping)) {
                    const t = this.defaultTransformFor(field)
                    if (t) this.transforms[field] = t
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
                this.result = response.result
            } catch (e) {
                this.error = e.response?.data?.error || e.response?.data?.message || 'Import failed.'
            } finally {
                this.processing = false
                this.stage = null
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
