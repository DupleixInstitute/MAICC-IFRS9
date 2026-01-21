<template>
  <app-layout>
    <template #header>
      <div class="flex justify-between items-center">
      <div>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('collateral.allocations.index')">
          Collateral
        </inertia-link>
        <span class="text-indigo-400 font-medium">/</span> Import
      </h2>
       <p class="mt-1 text-sm text-gray-600">Select the file with collaterals (registry of all the collateral)</p>
    </div>

      <div class="flex space-x-4">
          <button
              @click="downloadSample()"
             class="inline-flex items-center px-4 py-2 border border-gray-800 rounded-md shadow-sm text-sm font-medium text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 transition-all duration-200"
          >
              <svg class="-ml-1 mr-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
              </svg>
              Download Sample File
          </button>
      </div>
      </div>
    </template>

    <div class="mx-auto">
      <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">
        <form @submit.prevent="submit" enctype="multipart/form-data">
          <div class="space-y-6">
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
                                                Use traditional format with fields in sample file
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

               <div>
                  <label class="block text-sm font-medium text-gray-700">Period</label>
                  <input type="month" v-model="form.period" required
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                  <p class="mt-1 text-xs text-gray-500">Select the month and year for the collateral register</p>
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
                            <button type="button" @click="downloadTemplate('legacy')" class="flex-1 justify-center inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Download Legacy Template
                            </button>
                        </div>

          <!-- Submit Buttons -->
          <div class="flex items-center justify-end pt-6 border-t border-gray-200">
            <Link :href="route('collateral.allocations.index')" class="inline-flex items-center px-4 py-2 bg-gray-300 text-gray-700 rounded-md mr-3">
                Cancel
            </Link>
            <button type="submit" :disabled="form.processing" class="ml-3 bg-green-600 hover:bg-green-500 text-white px-4 py-2 rounded-md">
                Start Import
            </button>
          </div>
        </div>
        </form>
      </div>
    </div>

    <!-- Processing Modal -->
    <div v-if="form.processing || uploadProgress > 0" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center">
      <div class="bg-white p-8 rounded-lg shadow-xl max-w-lg w-full">
        <div class="space-y-6">
          <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
              <svg v-if="form.processing" class="animate-spin h-5 w-5 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span class="text-gray-700 font-medium">{{ form.processing ? 'Processing file...' : 'Uploading file...' }}</span>
            </div>
            <div class="text-sm text-gray-500">{{ uploadProgress }}%</div>
          </div>

          <div class="relative pt-1">
            <div class="overflow-hidden h-2 text-xs flex rounded bg-gray-200">
              <div :style="{ width: uploadProgress + '%' }" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-indigo-500 transition-all duration-300"></div>
            </div>
          </div>

          <div class="text-sm text-gray-600 text-center">
            {{ form.processing ? 'Please wait while we process your file. This may take a few minutes for large files.' : 'Uploading your file...' }}
          </div>
        </div>
      </div>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

export default {
    components: { AppLayout, Link },

    data() {
        return {
            importType: 'legacy',
            form: this.$inertia.form({
                file: null,
                period: '',
                mapping: {},
                import_type: 'legacy',
            }),
            fileName: '',
            selectedFile: null,
            headers: [],
            sampleData: [],
            mapping: {},
            availableFields: [
                'customer_id', 'customer_name', 'collateral_type', 'property_use', 
                'description', 'location', 'registration_date', 'expiry_date', 
                'valuation_date', 'nominal_value', 'market_value', 'execution_value', 'status'
            ],
        }
    },

    watch: {
        // Sync local UI state with form object
        importType(newVal) {
            this.form.import_type = newVal;
            if (newVal === 'legacy') {
                this.headers = []; // Clear UI mapping for legacy type
                this.form.mapping = {};
            }
        },
    },

    methods: {
        submit() {
            const formData = new FormData()
            formData.append('file', this.selectedFile)
            formData.append('period', this.form.period)
            formData.append('import_type', this.importType)
            formData.append('mapping', JSON.stringify(this.mapping))

           this.form.transform(() => ({
                file: this.selectedFile,
                period: this.form.period,
                import_type: this.importType,
                mapping: this.mapping,
            })).post('/collateral/register/import', {
                forceFormData: true,
                onSuccess: () => {
                    // optional success handling
                },
            });

        },

        autoMapFields(mapping) {
            const commonMappings = {
                'customer_id': 'customer_id', 'customer id': 'customer_id', 'id': 'customer_id', 'Client ID': 'customer_id', 'Customer ID': 'customer_id',
                'customer_name': 'customer_name', 'Customer Name': 'customer_name', 'client_name': 'customer_name', 'ClientName': 'customer_name', 'name': 'customer_name',
                'collateral_type': 'collateral_type', 'Collateral Type': 'collateral_type', 'type': 'collateral_type', 'collateral': 'collateral_type',
                'property_use': 'property_use', 'Property Use': 'property_use', 'use': 'property_use', 'purpose': 'property_use',
                'description': 'description', 'Description': 'description', 'details': 'description', 'remarks': 'description',
                'location': 'location', 'Location': 'location', 'address': 'location', 'property_address': 'location',
                'registration_date': 'registration_date', 'Registration Date': 'registration_date', 'reg_date': 'registration_date', 'date_registered': 'registration_date',
                'expiry_date': 'expiry_date', 'Expiry Date': 'expiry_date', 'exp_date': 'expiry_date', 'expiration_date': 'expiry_date',
                'valuation_date': 'valuation_date', 'Valuation Date': 'valuation_date', 'val_date': 'valuation_date', 'date_valued': 'valuation_date',
                'nominal_value': 'nominal_value', 'Nominal Value': 'nominal_value', 'nominal': 'nominal_value', 'book_value': 'nominal_value',
                'market_value': 'market_value', 'Market Value': 'market_value', 'market': 'market_value', 'fair_value': 'market_value',
                'execution_value': 'execution_value', 'Execution Value': 'execution_value', 'execution': 'execution_value', 'forced_sale_value': 'execution_value',
                'status': 'status', 'Status': 'status', 'collateral_status': 'status',
            };

            this.headers.forEach(header => {
                const cleanHeader = header.toLowerCase().trim();
                if (commonMappings[cleanHeader] && this.availableFields.includes(commonMappings[cleanHeader])) {
                    mapping[header] = commonMappings[cleanHeader];
                }
            });
        },

        handleFileSelect(event) {
            const file = event.target.files[0];
            if (!file) return;
            this.selectedFile = file;
            this.fileName = file.name;
            this.readFileHeaders(file);
        },

        readFileHeaders(file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                const lines = e.target.result.split('\n').filter(line => line.trim() !== '');
                if (lines.length) {
                    this.headers = this.parseCSVLine(lines[0]);
                    this.sampleData = lines.slice(0, 4).map(line => this.parseCSVLine(line));
                    this.setupMapping();
                }
            };
            reader.readAsText(file);
        },

        parseCSVLine(line) {
            const result = [];
            let current = '', inQuotes = false;
            for (let i = 0; i < line.length; i++) {
                const char = line[i];
                if (char === '"') inQuotes = !inQuotes;
                else if (char === ',' && !inQuotes) { result.push(current.trim()); current = ''; }
                else current += char;
            }
            result.push(current.trim());
            return result;
        },

        setupMapping() {
            const newMapping = {};

            if (this.importType === 'legacy') {
                this.headers.forEach(header => {
                    if (header.toLowerCase().includes('id')) newMapping[header] = 'customer_id';
                    else if (header.toLowerCase().includes('name')) newMapping[header] = 'customer_name';
                    else if (header.toLowerCase().includes('type')) newMapping[header] = 'collateral_type';
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
                // Legacy collateral template with dummy data
                data = `customer_id,customer_name,collateral_type,property_use,description,location,registration_date,expiry_date,valuation_date,nominal_value,market_value,execution_value,status
CUST001,John Doe,Property,Residential,Residential House,123 Main St,01/01/2023,31/12/2033,01/01/2023,500000.00,550000.00,450000.00,ACTIVE
CUST002,Jane Smith,Vehicle,Personal,Company Car,456 Oak Ave,15/02/2023,14/02/2028,15/02/2023,25000.00,28000.00,22000.00,ACTIVE
CUST003,Bob Johnson,Equipment,Business,Office Equipment,789 Pine Rd,10/03/2023,09/03/2028,10/03/2023,75000.00,80000.00,65000.00,ACTIVE`;
                        
                filename = 'collateral_legacy_template_dummy.csv';
            } else {
                const fields = this.availableFields.join(',');
                data = `${fields}\nCUST001,John Doe,Property,Residential,Residential House,123 Main St,01/01/2023,31/12/2033,01/01/2023,500000.00,550000.00,450000.00,ACTIVE`;
                filename = 'collateral_custom_template_dummy.csv';
            }

            const blob = new Blob([data], { type: 'text/csv' })
            const url = window.URL.createObjectURL(blob)
            const a = document.createElement('a')
            a.href = url
            a.download = filename
            a.click()
            window.URL.revokeObjectURL(url)
        },

        downloadSample() {
            window.location.href = this.route('collateral.register.sample')
        }
    }
}
</script>
