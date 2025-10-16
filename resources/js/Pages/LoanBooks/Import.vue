<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('loan_applications.loan-book')">Loan Books
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> Import
            </h2>
        </template>
        <div class="mx-auto">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">
                <form @submit.prevent="submit">
                    <div class="space-y-6">
                        <div class="mt-4">
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
                        <div class="text-sm text-gray-600">
                            <p class="mb-4">The File should be a CSV file</p>

                        </div>

                        <div class="mt-6 border-t border-gray-200 pt-6">
                            <h4 class="text-sm font-medium text-gray-900 mb-4">Upload File</h4>

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
                                            {{ fileName || 'Select a file' }}
                                        </p>
                                    </div>
                                    <input
                                        type="file"
                                        class="opacity-0"
                                        accept=".csv,.txt"
                                        @change="handleFileSelect"
                                    />
                                </label>
                            </div>
                        </div>

                    </div>
                    <div class="flex items-center justify-end mt-6">
                        <Link
                            :href="route('loan_applications.loan-book')"
                            class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 active:bg-gray-500 focus:outline-none focus:border-gray-500 focus:ring focus:ring-gray-300 disabled:opacity-25 transition mr-2"
                        >
                            Cancel
                        </Link>
                        <jet-button
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                        >
                            Save
                        </jet-button>
                    </div>
                </form>
            </div>
        </div>
    </app-layout>
    <!-- Processing Modal with Progress Bar -->
    <div v-if="form.processing || uploadProgress > 0"
         class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center">
        <div class="bg-white p-8 rounded-lg shadow-xl max-w-lg w-full">
            <div class="space-y-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <svg v-if="form.processing" class="animate-spin h-5 w-5 text-indigo-600"
                             xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-gray-700 font-medium">{{
                                form.processing ? 'Processing file...' : 'Uploading file...'
                            }}</span>
                    </div>
                    <div class="text-sm text-gray-500">{{ uploadProgress }}%</div>
                </div>

                <div class="relative pt-1">
                    <div class="overflow-hidden h-2 text-xs flex rounded bg-gray-200">
                        <div
                            :style="{ width: uploadProgress + '%' }"
                            class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-indigo-500 transition-all duration-300"
                        ></div>
                    </div>
                </div>

                <div class="text-sm text-gray-600 text-center">
                    {{
                        form.processing ? 'Please wait while we process your file. This may take a few minutes for large files.' : 'Uploading your file...'
                    }}
                </div>
            </div>
        </div>
    </div>

</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetButton from "@/Jetstream/Button.vue"
import JetInput from "@/Jetstream/Input.vue"
import JetInputError from "@/Jetstream/InputError.vue"
import JetLabel from "@/Jetstream/Label.vue"
import {Link} from '@inertiajs/vue3'
import ImportModal from "@/Components/ImportModal.vue";

export default {
    components: {
        ImportModal,
        AppLayout,
        JetButton,
        JetInput,
        JetLabel,
        JetInputError,
        Link,
    },
    props: {
        portfolios: Object
    },
    data() {
        return {
            form: this.$inertia.form({
                file: null,
                loan_portfolio_id: '',
                reporting_period: '',
            }),
            uploadProgress: 0,
            fileName: '',
        }
    },

    methods: {
        submit() {
            this.form.post(route('loan_applications.loan-book.import.store'), {
                onProgress: (progress) => {
                    if (progress.total) {
                        this.uploadProgress = Math.round((progress.loaded / progress.total) * 100)
                    }
                },
                onSuccess: (page) => {
                    this.uploadProgress = 100

                },
                onError: () => {
                    this.uploadProgress = 0
                },
            })
        },
        downloadSample() {
            const sampleData = 'Customer ID,Public Name\n1001,0774892762-John Doe\n1002,0774892763-Jane Smith'
            const blob = new Blob([sampleData], {type: 'text/csv'})
            const url = window.URL.createObjectURL(blob)
            const a = document.createElement('a')
            a.href = url
            a.download = 'sample_names_file.csv'
            a.click()
            window.URL.revokeObjectURL(url)
        },
        handleFileSelect(event) {
            const file = event.target.files[0]
            if (file) {
                this.form.file = file
                this.fileName = file.name
            }
        }
    },
}
</script>
