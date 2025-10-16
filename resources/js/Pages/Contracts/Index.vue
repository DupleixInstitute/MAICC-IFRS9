<template>
    <AppLayout title="Contracts">
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Contracts Management
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="sm:flex sm:items-center">
                    <div class="sm:flex-auto">
                        <h1 class="text-xl font-semibold text-gray-900">Contracts</h1>
                        <p class="mt-2 text-sm text-gray-700">
                            A list of all contracts with their original scores and details.
                        </p>
                    </div>
                    <div class="mt-4 sm:mt-0 sm:ml-16 sm:flex-none space-x-4">
                        <a
                            :href="route('loan_applications.contracts.download-sample')"
                            @click.prevent="downloadSample"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            Download Sample
                        </a>
                        <button
                            @click="showImportModal = true"
                            class="inline-flex items-center justify-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:w-auto"
                        >
                            Import Contracts
                        </button>
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="mt-8 flex flex-col sm:flex-row gap-4">
                    <div class="flex-1">
                        <input
                            v-model="form.search"
                            type="text"
                            placeholder="Search contracts..."
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            @input="debouncedSearch"
                        />
                    </div>
                    <div class="sm:w-48">
                        <input
                            v-model="form.period"
                            type="month"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            @change="submit"
                        />
                    </div>
                </div>

                <!-- Table -->
                <div class="mt-8 flex flex-col">
                    <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
                        <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                            <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                                <table class="min-w-full divide-y divide-gray-300">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">Contract ID</th>
                                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Customer</th>
                                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Create Date</th>
                                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Due Date</th>
                                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Opening Score</th>
                                            <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white">
                                        <tr v-for="contract in contracts.data" :key="contract.id">
                                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">
                                                {{ contract.contract_id }}
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                                {{ contract.client?.name || contract.customer_id }}
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                                {{ formatDate(contract.create_date) }}
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                                {{ formatDate(contract.due_date) }}
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500">
                                                {{ formatNumber(contract.opening_score) }}
                                            </td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm">
                                                <span :class="getStatusClass(contract)">
                                                    {{ getStatus(contract) }}
                                                </span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div class="mt-4">
                    <Pagination :links="contracts.links" />
                </div>

                <!-- Import Modal -->
                <Modal :show="showImportModal" @close="showImportModal = false">
                    <div class="p-6">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">Import Contracts</h2>

                        <!-- Instructions Panel -->
                        <div class="bg-gray-50 rounded-lg p-4 mb-6">
                            <h3 class="text-sm font-medium text-gray-900 mb-2">Instructions</h3>
                            <ul class="text-sm text-gray-600 space-y-2 list-disc pl-5">
                                <li>Download the sample file below as a template</li>
                                <li>All dates should be in DD/MM/YYYY format</li>
                                <li>Customer ID must match an existing customer in the system</li>
                                <li>Opening score should be a decimal number (e.g., 75.5)</li>
                                <li>All fields are required</li>
                            </ul>

                            <!-- Download Sample Button -->
                            <div class="mt-4">
                                <a
                                    @click.prevent="downloadSample"
                                    href="#"
                                    class="inline-flex items-center text-sm text-indigo-600 hover:text-indigo-500"
                                >
                                    <svg class="mr-2 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                    Download Sample Template
                                </a>
                            </div>
                        </div>

                        <form @submit.prevent="submitImport" class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Opening Score Period</label>
                                <input
                                    v-model="importForm.opening_score_period"
                                    type="month"
                                    required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">CSV File</label>
                                <div class="mt-1 flex items-center">
                                    <input
                                        type="file"
                                        ref="fileInput"
                                        accept=".csv"
                                        required
                                        class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                                        @change="handleFileChange"
                                    />
                                </div>
                            </div>
                            <div class="mt-6 flex justify-end space-x-3">
                                <button
                                    type="button"
                                    class="rounded-md border border-gray-300 bg-white py-2 px-4 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="showImportModal = false"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    :disabled="importing"
                                    class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <template v-if="!importing">Import</template>
                                    <template v-else>
                                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Importing...
                                    </template>
                                </button>
                            </div>
                        </form>
                    </div>
                </Modal>

                <!-- Error Modal -->
                <Modal :show="showErrorModal" @close="showErrorModal = false" maxWidth="4xl">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-lg font-medium text-gray-900">Import Errors</h2>
                            <button
                                @click="showErrorModal = false"
                                class="text-gray-400 hover:text-gray-500"
                            >
                                <span class="sr-only">Close</span>
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <div v-if="importErrors" class="space-y-4">
                            <!-- Summary -->
                            <div class="bg-gray-50 rounded-lg p-4">
                                <div class="grid grid-cols-3 gap-4 text-sm">
                                    <div class="text-center">
                                        <div class="font-medium text-gray-500">Total Rows</div>
                                        <div class="mt-1 text-2xl font-semibold text-gray-900">{{ importErrors.total_rows }}</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="font-medium text-gray-500">Successful</div>
                                        <div class="mt-1 text-2xl font-semibold text-green-600">{{ importErrors.successful }}</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="font-medium text-gray-500">Failed</div>
                                        <div class="mt-1 text-2xl font-semibold text-red-600">{{ importErrors.failed }}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Detailed Errors -->
                            <div class="mt-4">
                                <h3 class="text-sm font-medium text-gray-900 mb-2">Error Details</h3>
                                <div class="bg-white shadow overflow-hidden sm:rounded-md">
                                    <ul class="divide-y divide-gray-200">
                                        <li v-for="(error, index) in importErrors.row_errors" :key="index" class="px-4 py-4">
                                            <div class="flex items-start">
                                                <div class="flex-shrink-0">
                                                    <svg class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </div>
                                                <div class="ml-3 flex-1">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        Row {{ error.row }}
                                                    </div>
                                                    <div class="mt-1 text-sm text-gray-500">
                                                        {{ error.error }}
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end">
                                <button
                                    type="button"
                                    class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    @click="showErrorModal = false"
                                >
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </Modal>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import { ref, reactive } from 'vue'

import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import Modal from '@/Components/Modal.vue'
import Pagination from '@/Components/Pagination.vue'
import debounce from 'lodash/debounce'

export default {
    components: {
        Link,
        Modal,
        Pagination,
        AppLayout,
    },

    props: {
        contracts: Object,
        filters: Object,
    },

    setup(props) {
        const showImportModal = ref(false)
        const showErrorModal = ref(false)
        const importing = ref(false)
        const fileInput = ref(null)
        const importErrors = ref(null)

        const form = reactive({
            search: props.filters.search || '',
            period: props.filters.period || '',
        })

        const importForm = reactive({
            opening_score_period: '',
            file: null,
        })

        const submit = () => {
            Inertia.get(route('contracts.index'), form, {
                preserveState: true,
                preserveScroll: true,
            })
        }

        const debouncedSearch = debounce(() => {
            submit()
        }, 300)

        const handleFileChange = (event) => {
            importForm.file = event.target.files[0]
        }

        const submitImport = async () => {
            if (!importForm.file || !importForm.opening_score_period) return

            importing.value = true
            const formData = new FormData()
            formData.append('file', importForm.file)
            formData.append('opening_score_period', importForm.opening_score_period)

            try {
                const response = await axios.post(route('loan_applications.contracts.save-contract'), formData)
                showImportModal.value = false
                Inertia.reload()
            } catch (error) {
                const errorDetails = error.response?.data?.details
                if (errorDetails) {
                    importErrors.value = errorDetails
                    showImportModal.value = false
                    showErrorModal.value = true
                } else {
                    alert(error.response?.data?.error || 'An error occurred during import')
                }
            } finally {
                importing.value = false
            }
        }

        const downloadSample = async () => {
            try {
                const response = await fetch(route('loan_applications.contracts.download-sample'));
                if (!response.ok) {
                    const data = await response.json();
                    throw new Error(data.error || 'Download failed');
                }

                // Get the filename from the Content-Disposition header
                const filename = 'contracts_sample.csv';

                // Create a blob from the response
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);

                // Create a temporary link and trigger the download
                const a = document.createElement('a');
                a.href = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();

                // Cleanup
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            } catch (error) {
                alert(error.message || 'Failed to download sample file. Please try again.');
            }
        }

        return {
            form,
            importForm,
            showImportModal,
            showErrorModal,
            importing,
            importErrors,
            fileInput,
            submit,
            debouncedSearch,
            handleFileChange,
            submitImport,
            downloadSample,
        }
    },

    methods: {
        formatDate(date) {
            return new Date(date).toLocaleDateString()
        },

        formatNumber(number) {
            return new Intl.NumberFormat().format(number)
        },

        getStatus(contract) {
            if (contract.write_off_date) return 'Written Off'
            if (contract.closed_date) return 'Closed'
            return 'Active'
        },

        getStatusClass(contract) {
            const baseClasses = 'px-2 inline-flex text-xs leading-5 font-semibold rounded-full '
            if (contract.write_off_date) return baseClasses + 'bg-red-100 text-red-800'
            if (contract.closed_date) return baseClasses + 'bg-green-100 text-green-800'
            return baseClasses + 'bg-blue-100 text-blue-800'
        },
    },
}
</script>
