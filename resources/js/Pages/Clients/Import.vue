<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('clients.index')">
                    Clients
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> Import
            </h2>
        </template>

        <div class="mx-auto max-w-7xl">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <!-- Status Alerts -->
                <div v-if="$page.props.flash.error" class="bg-red-50 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-red-700">{{ $page.props.flash.error }}</p>
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <form @submit.prevent="submit" enctype="multipart/form-data">
                        <div class="space-y-8">
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
                                                <input 
                                                    type="radio" 
                                                    v-model="importType" 
                                                    value="legacy" 
                                                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300"
                                                />
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
                                                <input 
                                                    type="radio" 
                                                    v-model="importType" 
                                                    value="custom" 
                                                    class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300"
                                                />
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

                            <!-- File Upload Section -->
                            <div class="mt-6 border-t border-gray-200 pt-6">
                                <h4 class="text-sm font-medium text-gray-900 mb-4">Upload CSV File</h4>

                                <div class="flex items-center justify-center w-full">
                                    <label
                                        class="flex flex-col w-full h-32 border-4 border-dashed hover:bg-gray-100 hover:border-gray-300 rounded-lg cursor-pointer">
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
                                            name="names_file"
                                            class="opacity-0"
                                            accept=".csv,.txt,.xlsx,.xls"
                                            @change="handleFileSelect"
                                            required
                                        />
                                    </label>
                                </div>
                            </div>

                            <!-- Legacy Import Instructions -->
                            <div v-if="importType === 'legacy' && headers.length > 0" class="mt-6 border-t border-gray-200 pt-6">
                                <h4 class="text-sm font-medium text-gray-900 mb-4">Legacy Import Setup</h4>
                                
                                <div class="bg-yellow-50 p-4 rounded-lg mb-4">
                                    <h5 class="text-sm font-medium text-yellow-800 mb-2">Legacy Format Requirements:</h5>
                                    <ul class="text-xs text-yellow-700 list-disc list-inside space-y-1">
                                        <li>Your CSV must have exactly 2 columns</li>
                                        <li>Column 1: <strong>customer_id</strong></li>
                                        <li>Column 2: <strong>public_name</strong> (format: PHONE-NAME)</li>
                                    </ul>
                                </div>

                                <!-- Auto-set mapping for legacy format -->
                                <div class="bg-green-50 p-4 rounded-lg">
                                    <p class="text-sm text-green-700">
                                        <strong>Auto-mapping applied:</strong> customer_id → customer_id, public_name → public_name
                                    </p>
                                </div>
                            </div>

                            <!-- Custom Mapping Section -->
                            <div v-if="importType === 'custom' && headers.length > 0" class="mt-6 border-t border-gray-200 pt-6">
                                <h4 class="text-sm font-medium text-gray-900 mb-4">Map CSV Columns to Database Fields</h4>
                                
                                <!-- File Preview -->
                                <div class="bg-gray-50 p-4 rounded-lg mb-6">
                                    <h5 class="font-medium text-gray-700 mb-2">File Preview (first 3 rows):</h5>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200 text-xs">
                                            <thead class="bg-gray-100">
                                                <tr>
                                                    <th v-for="(header, index) in headers" :key="index" 
                                                        class="px-3 py-2 text-left font-medium text-gray-500 uppercase tracking-wider">
                                                        {{ header }}
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <tr v-for="(row, rowIndex) in sampleData.slice(1, 4)" :key="rowIndex">
                                                    <td v-for="(cell, cellIndex) in row" :key="cellIndex" 
                                                        class="px-3 py-2 text-gray-500 truncate max-w-xs">
                                                        {{ cell }}
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Mapping Controls -->
                                <div class="grid grid-cols-1 gap-4">
                                    <div v-for="(header, index) in headers" :key="index" 
                                        class="flex items-center space-x-4 p-3 bg-gray-50 rounded-lg">
                                        <label class="block text-sm font-medium text-gray-700 w-32 truncate">
                                            {{ header }}
                                        </label>
                                        <select v-model="mapping[header]" 
                                                class="flex-1 py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                            <option value="">-- Ignore this column --</option>
                                            <option v-for="field in availableFields" :key="field" :value="field">
                                                {{ field }}
                                                <template v-if="fieldDescriptions[field] && fieldDescriptions[field].required">
                                                    * (required)
                                                </template>
                                            </option>
                                        </select>
                                        <div class="w-20 text-xs text-gray-500 text-right">
                                            <span v-if="mapping[header]" class="text-green-600">✓ Mapped</span>
                                            <span v-else class="text-gray-400">Not mapped</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Field Descriptions -->
                                <div class="mt-6 p-4 bg-blue-50 rounded-lg">
                                    <h5 class="text-sm font-medium text-blue-800 mb-3">Field Descriptions:</h5>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                                        <div v-for="(desc, fieldName) in fieldDescriptions" :key="fieldName" 
                                             class="flex items-start space-x-2">
                                            <span class="font-medium text-blue-900 min-w-32">{{ fieldName }}</span>
                                            <div>
                                                <p class="text-blue-700">{{ desc.description }}</p>
                                                <p class="text-blue-600 text-xs mt-1">Example: {{ desc.example }}</p>
                                                <span v-if="desc.required" class="inline-block mt-1 px-2 py-1 text-xs bg-red-100 text-red-800 rounded">Required</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Validation Error -->
                                <div v-if="validationError" class="mt-4 p-4 bg-red-50 rounded-lg">
                                    <p class="text-sm text-red-700">{{ validationError }}</p>
                                </div>
                            </div>

                            <!-- Quick Actions -->
                            <div class="mt-6 flex flex-col sm:flex-row gap-4">
                                <jet-button type="button" @click="downloadTemplate('legacy')" class="flex-1 justify-center">
                                    <template #icon>
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </template>
                                    Download Legacy Template
                                </jet-button>
                                <jet-button type="button" @click="downloadTemplate('custom')" class="flex-1 justify-center">
                                    <template #icon>
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </template>
                                    Download Custom Template
                                </jet-button>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex items-center justify-end pt-6 border-t border-gray-200">
                                <Link
                                    :href="route('clients.index')"
                                    class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 active:bg-gray-500 focus:outline-none focus:border-gray-500 focus:ring focus:ring-gray-300 disabled:opacity-25 transition"
                                >
                                    Cancel
                                </Link>
                                <jet-button
                                    v-if="headers.length > 0"
                                    type="submit"
                                    :class="{ 'opacity-25': form.processing }"
                                    :disabled="form.processing"
                                    class="ml-3 bg-green-600 hover:bg-green-500 focus:outline-none focus:border-green-700 focus:ring focus:ring-green-200 active:bg-green-800"
                                >
                                    <template #icon>
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                                        </svg>
                                    </template>
                                    Start Import
                                </jet-button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetButton from "@/Jetstream/Button.vue"
import { Link } from '@inertiajs/vue3'

export default {
    components: {
        AppLayout,
        JetButton,
        Link,
    },

    props: {
        availableFields: {
            type: Array,
            required: true,
            default: () => []
        },
        fieldDescriptions: {
            type: Object,
            default: () => ({})
        },
        error: {
            type: String,
            default: null
        }
    },

    data() {
        return {
            importType: 'custom',
            form: this.$inertia.form({
                names_file: null,
                mapping: {},
                import_type: 'custom',
            }),
            fileName: '',
            selectedFile: null,
            headers: [],
            sampleData: [],
            mapping: {},
            validationError: ''
        }
    },

    computed: {
        // isMappingValid() {
        //     if (this.importType === 'legacy') {
        //         const hasRequired = this.headers.includes('customer_id') && this.headers.includes('public_name');
        //         if (!hasRequired) {
        //             this.validationError = 'Legacy import requires customer_id and public_name columns';
        //         }
        //         return hasRequired;
        //     }
            
        //     const hasCustomerId = Object.values(this.mapping).includes('customer_id');
        //     const hasMappings = Object.values(this.mapping).some(value => value !== '');
            
        //     if (!hasCustomerId) {
        //         this.validationError = 'The customer_id field must be mapped as it is required.';
        //     } else if (!hasMappings) {
        //         this.validationError = 'At least one field must be mapped.';
        //     } else {
        //         this.validationError = '';
        //     }
            
        //     return hasCustomerId && hasMappings;
        // }
    },

    mounted() {
        console.log('Available fields:', this.availableFields);
        console.log('Field descriptions:', this.fieldDescriptions);
        
        if (this.error) {
            console.warn('Field loading error:', this.error);
        }
    },

    methods: {
        submit() {
            // if (!this.isMappingValid) {
            //     return;
            // }

            const formData = new FormData();
            formData.append('names_file', this.selectedFile);
            formData.append('mapping', JSON.stringify(this.mapping));
            formData.append('import_type', this.importType);

            this.form.transform(() => ({
                names_file: this.selectedFile,
                mapping: this.mapping,
                import_type: this.importType,
            })).post(route('clients.import.store'), {
                forceFormData: true,
                onSuccess: () => console.log('Import started successfully'),
                onError: (errors) => {
                    console.error('Import errors:', errors);
                    this.validationError = errors.message || 'An error occurred during import';
                },
            });
        },

        handleFileSelect(event) {
            const file = event.target.files[0];
            if (file) {
                this.selectedFile = file;
                this.fileName = file.name;
                this.readFileHeaders(file);
            }
        },

        readFileHeaders(file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                const content = e.target.result;
                const lines = content.split('\n').filter(line => line.trim() !== '');
                
                if (lines.length > 0) {
                    this.headers = this.parseCSVLine(lines[0]);
                    this.sampleData = lines.slice(0, 4).map(line => this.parseCSVLine(line));
                    this.setupMapping();
                }
            };
            reader.readAsText(file);
        },

        setupMapping() {
            const newMapping = {};
            
            if (this.importType === 'legacy') {
                // Auto-map for legacy format
                this.headers.forEach(header => {
                    if (header === 'Customer ID') {
                        newMapping[header] = 'customer_id';
                    } else if (header === 'Name') {
                        newMapping[header] = 'name';
                    } else {
                        newMapping[header] = '';
                    }
                });
            } else {
                // Set up empty mappings for custom import
                this.headers.forEach(header => {
                    newMapping[header] = '';
                });
                this.autoMapFields(newMapping);
            }
            
            this.mapping = newMapping;
        },

        parseCSVLine(line) {
            const result = [];
            let current = '';
            let inQuotes = false;
            
            for (let i = 0; i < line.length; i++) {
                const char = line[i];
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

        autoMapFields(mapping) {
            const commonMappings = {
                'customer_id': 'customer_id', 'customer id': 'customer_id', 'id': 'customer_id', 'Customer Id': 'customer_id',
                'customer_name': 'name', 'Name': 'name', 'client_name': 'name', 'full_name': 'name', 'client': 'name',
                'mobile': 'mobile', 'phone': 'mobile', 'telephone': 'mobile', 'phone_number': 'mobile',
                'account_no': 'account_no', 'account_number': 'account_no', 'account': 'account_no',
                'business_unit': 'business_unit', 'unit': 'business_unit', 'department': 'business_unit',
                'email': 'email', 'email_address': 'email',
                'address': 'address', 'physical_address': 'address',
            };

            this.headers.forEach(header => {
                const cleanHeader = header.toLowerCase().trim();
                if (commonMappings[cleanHeader] && this.availableFields.includes(commonMappings[cleanHeader])) {
                    mapping[header] = commonMappings[cleanHeader];
                }
            });
        },

        downloadTemplate(type) {
            let data, filename;
            
            if (type === 'legacy') {
                data = 'customer_id,name\n1001,0774892762-John Doe\n1002,0774892763-Jane Smith';
                filename = 'clients_legacy_template.csv';
            } else {
                const fields = this.availableFields.length > 0 ? 
                    this.availableFields.join(',') : 
                    'customer_id,customer_name,mobile,account_no,business_unit,email,address';
                data = `${fields}\nCUST001,John Doe,0712345678,ACC001,Retail,john@example.com,"123 Main St"\nCUST002,Jane Smith,0798765432,ACC002,Corporate,jane@example.com,"456 Oak Ave"`;
                filename = 'clients_custom_template.csv';
            }
            
            this.downloadFile(data, filename);
        },

        downloadFile(data, filename) {
            const blob = new Blob([data], {type: 'text/csv'});
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            a.click();
            window.URL.revokeObjectURL(url);
        },
    },
}
</script>