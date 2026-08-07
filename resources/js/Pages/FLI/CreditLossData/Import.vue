<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-maiic-500 hover:text-maiic-600" :href="route('credit-loss-data.index')">Credit Loss Data
                </inertia-link>
                <span class="text-maiic-500 font-medium">/</span> Import
            </h2>
        </template>
        <div class="mx-auto">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">
                <form @submit.prevent="submit">
                    <div class="space-y-6">
                        <!-- Portfolio Selection -->
                        <div class="mt-4">
                            <jet-label for="portfolio_id" value="Portfolio *" />
                            <select
                                id="portfolio_id"
                                v-model="form.portfolio_id"
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-maiic-500 focus:ring-maiic-500"
                                :class="{ 'border-red-300 focus:border-red-500 focus:ring-red-500': form.errors.portfolio_id }"
                            >
                                <option value="" disabled>Select a portfolio</option>
                                <option
                                    v-for="portfolio in portfolios"
                                    :key="portfolio.id"
                                    :value="portfolio.id"
                                >
                                    {{ portfolio.name }}
                                </option>
                            </select>
                            <jet-input-error :message="form.errors.portfolio_id" class="mt-2" />
                        </div>

                        <!-- Period Selection -->
                        <!-- <div>
                            <jet-label for="period" value="Reporting Period *" />
                            <input 
                                type="month" 
                                v-model="form.period" 
                                required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-maiic-500 focus:ring-maiic-500"
                                :class="{ 'border-red-300 focus:border-red-500 focus:ring-red-500': form.errors.period }"
                            >
                            <p class="mt-1 text-xs text-gray-500">Select the month and year for this credit loss data</p>
                            <jet-input-error :message="form.errors.period" class="mt-2" />
                        </div> -->

                        <!-- File Upload Section -->
                        <div class="mt-6 border-t border-gray-200 pt-6">
                            <h4 class="text-sm font-medium text-gray-900 mb-4">Upload CSV File</h4>

                            <div class="flex items-center justify-center w-full">
                                <label
                                    class="flex flex-col w-full h-32 border-4 border-dashed hover:bg-gray-100 hover:border-gray-300">
                                    <div class="relative flex flex-col items-center justify-center pt-7">
                                        <svg xmlns="http://www.w3.org/2000/svg"
                                            class="w-12 h-12 text-gray-400 group-hover:text-gray-600"
                                            viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd"
                                                d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z"
                                                clip-rule="evenodd"/>
                                        </svg>
                                        <p class="pt-1 text-sm tracking-wider text-gray-400 group-hover:text-gray-600">
                                            {{ fileName || 'Select a CSV file' }}
                                        </p>
                                    </div>
                                    <input
                                        type="file"
                                        class="opacity-0"
                                        accept=".csv,.txt"
                                        @change="handleFileSelect"
                                        :disabled="isProcessing"
                                    />
                                </label>
                            </div>
                            
                            <p class="mt-2 text-xs text-gray-500">
                                Supported formats: CSV, TXT. Maximum file size: 2MB
                            </p>
                        </div>

                        <!-- Header Mapping Section -->
                        <div v-if="showPreview" class="mt-6 border-t border-gray-200 pt-6">
                            <h4 class="text-sm font-medium text-gray-900 mb-4">Map CSV Headers to Credit Loss Metrics</h4>
                            
                            <!-- Validation Summary -->
                            <div class="mb-4 p-4 bg-maiic-50 rounded-lg">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-maiic-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                    </svg>
                                    <span class="text-sm text-maiic-700">
                                        Metrics mapped: {{ mappedMetricsCount }} / {{ definitions.length }} available
                                    </span>
                                </div>
                                <div v-if="mappedMetricsCount === 0" class="mt-2 text-sm text-red-600">
                                    <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                    </svg>
                                    Please map at least one metric column to continue
                                </div>
                            </div>

                            <!-- Instructions -->
                            <div class="mb-4 p-3 bg-amber-50 rounded-lg">
                                <p class="text-sm text-amber-700">
                                    <strong>How it works:</strong> Map your CSV columns to credit loss metrics. Each row will create individual metric records.
                                    Multiple columns can map to the same metric.
                                </p>
                            </div>

                            <!-- Header Mapping Table -->
                            <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 rounded-lg">
                                <table class="min-w-full divide-y divide-gray-300">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">CSV Header</th>
                                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Credit Loss Metric</th>
                                            <th class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white">
                                        <tr v-for="(header, index) in previewData.headers" :key="index">
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-900">
                                                <code class="text-xs bg-gray-100 px-2 py-1 rounded">{{ header }}</code>
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-900">
                                                <select 
                                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-maiic-500 focus:border-maiic-500 sm:text-sm rounded-md"
                                                    :value="getMappedDefinition(header)"
                                                    @change="updateDefinitionMapping(header, $event.target.value)"
                                                    :disabled="isProcessing"
                                                >
                                                    <optgroup label="Special Fields">
                                                        <option value="period">Period</option>
                                                    </optgroup>
                                                    <option value="">-- Select Metric --</option>
                                                    <optgroup label="Credit Loss Metrics">
                                                        <option 
                                                            v-for="definition in definitions" 
                                                            :key="definition.id"
                                                            :value="definition.id"
                                                        >
                                                            {{ definition.name }} ({{ definition.code }})
                                                        </option>
                                                    </optgroup>
                                                    <optgroup label="Additional Fields">
                                                        <option value="source">Data Source</option>
                                                        <option value="notes">Notes</option>
                                                    </optgroup>
                                                </select>
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-900">
                                                <span 
                                                    v-if="getMappedDefinition(header)" 
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-maiic-100 text-maiic-800"
                                                >
                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                    </svg>
                                                    {{ getMappedDefinitionName(header) }}
                                                </span>
                                                <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                                    <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                    </svg>
                                                    Unmapped
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Data Preview -->
                            <div class="mt-6">
                                <h5 class="text-sm font-medium text-gray-900 mb-3">Data Preview (First 3 rows)</h5>
                                <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 rounded-lg">
                                    <table class="min-w-full divide-y divide-gray-300">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th 
                                                    v-for="(header, index) in previewData.headers" 
                                                    :key="index"
                                                    class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900"
                                                    :class="{ 'bg-maiic-50': getMappedDefinition(header) }"
                                                >
                                                    <div class="text-xs">{{ header }}</div>
                                                    <div class="text-xs text-gray-500 font-normal">
                                                        {{ getMappedDefinitionName(header) || 'Unmapped' }}
                                                    </div>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 bg-white">
                                            <tr v-for="(row, rowIndex) in previewData.rows.slice(0, 3)" :key="rowIndex">
                                                <td 
                                                    v-for="(cell, cellIndex) in row" 
                                                    :key="cellIndex"
                                                    class="whitespace-nowrap px-3 py-2 text-sm text-gray-900"
                                                >
                                                    {{ cell }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Expected Output Preview -->
                            <div class="mt-6">
                                <h5 class="text-sm font-medium text-gray-900 mb-3">Expected Import Result</h5>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <div class="text-sm text-gray-600 mb-2">
                                        Your CSV data will be imported as individual metric records:
                                    </div>
                                    <div class="space-y-2 text-xs">
                                        <div v-for="metric in mappedMetrics" :key="metric.id" class="flex items-center justify-between bg-white p-2 rounded border">
                                            <div>
                                                <span class="font-medium">{{ metric.name }}</span>
                                                <span class="text-gray-500 ml-2">({{ metric.code }})</span>
                                            </div>
                                            <span class="text-gray-500">{{ metric.columnCount }} column(s)</span>
                                        </div>
                                        <div v-if="mappedMetrics.length === 0" class="text-center text-gray-500 py-2">
                                            No metrics mapped yet
                                        </div>
                                    </div>
                                    <div class="mt-3 text-xs text-gray-500">
                                        Total records to create: {{ previewData.rows.length }} rows × {{ mappedMetrics.length }} metrics = {{ previewData.rows.length * mappedMetrics.length }} records
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="flex items-center justify-end mt-6">
                        <Link
                            :href="route('credit-loss-data.index')"
                            class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 active:bg-gray-500 focus:outline-none focus:border-gray-500 focus:ring focus:ring-gray-300 disabled:opacity-25 transition mr-2"
                        >
                            Cancel
                        </Link>
                        <jet-button
                            :class="{ 'opacity-25': !canSubmit || isProcessing }"
                            :disabled="!canSubmit || isProcessing"
                        >
                            {{ isProcessing ? 'Importing...' : 'Import Data' }}
                        </jet-button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Processing Modal -->
        <div v-if="isProcessing"
            class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center">
            <div class="bg-white p-8 rounded-lg shadow-xl max-w-lg w-full">
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <svg class="animate-spin h-5 w-5 text-maiic-600"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-gray-700 font-medium">Importing credit loss data...</span>
                        </div>
                        <div class="text-sm text-gray-500">{{ uploadProgress }}%</div>
                    </div>

                    <div class="relative pt-1">
                        <div class="overflow-hidden h-2 text-xs flex rounded bg-gray-200">
                            <div
                                :style="{ width: uploadProgress + '%' }"
                                class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-maiic-500 transition-all duration-300"
                            ></div>
                        </div>
                    </div>

                    <div class="text-sm text-gray-600 text-center">
                        Processing {{ processedRows }}/{{ totalRows }} records...
                        <div class="text-xs text-gray-500 mt-1">
                            Creating {{ mappedMetrics.length }} metric types
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetButton from "@/Jetstream/Button.vue"
import JetInput from "@/Jetstream/Input.vue"
import JetInputError from "@/Jetstream/InputError.vue"
import JetLabel from "@/Jetstream/Label.vue"
import {Link} from '@inertiajs/vue3'

export default {
    components: {
        AppLayout,
        JetButton,
        JetInput,
        JetLabel,
        JetInputError,
        Link,
    },
    props: {
        portfolios: Object,
        definitions: Array
    },
    data() {
        return {
            form: this.$inertia.form({
                file: null,
                portfolio_id: '',
                // period: '',
                field_mapping: {},
            }),
            uploadProgress: 0,
            fileName: '',
            isProcessing: false,
            processedRows: 0,
            totalRows: 0,
            previewData: {
                headers: [],
                rows: []
            },
            definitionMapping: {}, // Maps CSV headers to definition IDs
        }
    },

    computed: {
        showPreview() {
            return this.previewData.headers.length > 0 && this.previewData.rows.length > 0;
        },
        mappedMetricsCount() {
            // Count unique definition IDs that are mapped (excluding source/notes)
            const definitionIds = Object.values(this.definitionMapping).filter(value => 
                value && !isNaN(parseInt(value))
            );
            return new Set(definitionIds).size;
        },
        mappedMetrics() {
            const metrics = [];
            const definitionIds = [...new Set(Object.values(this.definitionMapping).filter(value => 
                value && !isNaN(parseInt(value))
            ))];
            
            definitionIds.forEach(id => {
                const definition = this.definitions.find(d => d.id == id);
                if (definition) {
                    const columnCount = Object.values(this.definitionMapping).filter(value => value == id).length;
                    metrics.push({
                        ...definition,
                        columnCount: columnCount
                    });
                }
            });
            
            return metrics;
        },
        canSubmit() {
            return this.form.file && 
                   this.form.portfolio_id && 
                //    this.form.period && 
                   this.mappedMetricsCount > 0;
        }
    },

    methods: {
        submit() {
            // Add definition mapping to form data
            this.form.field_mapping = this.definitionMapping;
            
            this.isProcessing = true;
            this.form.post(route('credit-loss-data.import'), {
                onProgress: (progress) => {
                    if (progress.detail) {
                        this.uploadProgress = progress.detail.progress || 0;
                        this.processedRows = progress.detail.processed || 0;
                        this.totalRows = progress.detail.total || 0;
                    } else if (progress.total) {
                        this.uploadProgress = Math.round((progress.loaded / progress.total) * 100);
                    }
                },
                onSuccess: (page) => {
                    this.uploadProgress = 100;
                    this.isProcessing = false;
                },
                onError: () => {
                    this.uploadProgress = 0;
                    this.isProcessing = false;
                },
            });
        },

        handleFileSelect(event) {
            const file = event.target.files[0];
            if (file) {
                // Validate file
                if (!this.validateFile(file)) {
                    return;
                }

                this.form.file = file;
                this.fileName = file.name;
                this.parseCSVFile(file);
            }
        },

        validateFile(file) {
            const validTypes = ['text/csv', 'text/plain', 'application/vnd.ms-excel'];
            const maxSize = 2 * 1024 * 1024; // 2MB

            if (!validTypes.includes(file.type) && !file.name.toLowerCase().endsWith('.csv')) {
                alert('Please select a valid CSV file.');
                return false;
            }

            if (file.size > maxSize) {
                alert('File size must be less than 2MB.');
                return false;
            }

            return true;
        },

        parseCSVFile(file) {
            const reader = new FileReader();
            
            reader.onload = (e) => {
                try {
                    const csvText = e.target.result;
                    const rows = csvText.split('\n').filter(row => row.trim());
                    
                    if (rows.length < 2) {
                        alert('CSV file must contain at least a header row and one data row.');
                        return;
                    }

                    // Parse headers
                    const headers = this.parseCSVRow(rows[0]);
                    
                    // Parse data rows
                    const dataRows = rows.slice(1).map(row => this.parseCSVRow(row)).filter(row => row.some(cell => cell.trim() !== ''));

                    this.previewData = {
                        headers: headers,
                        rows: dataRows
                    };

                    // Auto-detect definition mappings
                    this.autoDetectMappings(headers);

                } catch (error) {
                    console.error('Error parsing CSV:', error);
                    alert('Error parsing CSV file. Please check the format.');
                }
            };

            reader.onerror = () => {
                alert('Error reading file.');
            };

            reader.readAsText(file);
        },

        parseCSVRow(row) {
            const result = [];
            let current = '';
            let inQuotes = false;

            for (let i = 0; i < row.length; i++) {
                const char = row[i];
                
                if (char === '"') {
                    inQuotes = !inQuotes;
                } else if (char === ',' && !inQuotes) {
                    result.push(current.trim());
                    current = '';
                } else {
                    current += char;
                }
            }
            
            result.push(current.trim());
            return result;
        },

        autoDetectMappings(headers) {
            const mappings = {};

            headers.forEach(header => {
                const normalizedHeader = header.toLowerCase().trim();
                
                // Try to find matching definition by code or name
                const matchedDefinition = this.definitions.find(definition => {
                    const definitionCode = definition.code.toLowerCase();
                    const definitionName = definition.name.toLowerCase();
                    
                    return normalizedHeader.includes(definitionCode) ||
                           definitionCode.includes(normalizedHeader) ||
                           normalizedHeader.includes(definitionName) ||
                           this.commonAliases[definitionCode]?.some(alias => 
                               normalizedHeader.includes(alias.toLowerCase())
                           );
                });

                if (matchedDefinition) {
                    mappings[header] = matchedDefinition.id.toString();
                }
            });

            this.definitionMapping = mappings;
        },

        getMappedDefinition(header) {
            return this.definitionMapping[header] || '';
        },

        getMappedDefinitionName(header) {
            const definitionId = this.definitionMapping[header];
            if (!definitionId) return '';
            
            if (definitionId === 'period') return 'Period';
            if (definitionId === 'source') return 'Data Source';
            if (definitionId === 'notes') return 'Notes';
            
            const definition = this.definitions.find(d => d.id == definitionId);
            return definition ? definition.name : '';
        },

        updateDefinitionMapping(header, definitionId) {
            if (definitionId === '') {
                // Remove mapping
                const { [header]: removed, ...rest } = this.definitionMapping;
                this.definitionMapping = rest;
            } else {
                // Update mapping
                this.definitionMapping = {
                    ...this.definitionMapping,
                    [header]: definitionId
                };
            }
        },

        // Common aliases for auto-detection
        commonAliases: {
            'ECL': ['ecl', 'expected credit loss', 'expected_credit_loss', 'credit_loss'],
            'PD': ['pd', 'probability of default', 'probability_of_default', 'default_probability'],
            'LGD': ['lgd', 'loss given default', 'loss_given_default', 'loss_rate'],
            'EAD': ['ead', 'exposure at default', 'exposure_at_default', 'exposure'],
            'NPL': ['npl', 'non performing loans', 'non_performing_loans', 'npl_amount'],
            'STAGE': ['stage', 'ifrs stage', 'ifrs_stage', 'credit_stage'],
            'CREDIT_RATING': ['rating', 'credit_rating', 'internal_rating', 'risk_rating'],
        }
    }
}
</script>