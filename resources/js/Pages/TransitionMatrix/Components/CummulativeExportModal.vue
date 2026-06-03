<template>
    <Modal v-if="show" @close="$emit('close')">
        <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Export Cumulative Transition Matrix Report</h3>

            <form @submit.prevent="submitExport" class="space-y-4">
                <!-- Start Period -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Period</label>
                    <input
                        type="month"
                        v-model="form.start_period"
                        required
                        class="w-full border-gray-300 rounded-md shadow-sm focus:border-maiic-500 focus:ring-maiic-500"
                    />
                </div>

                <!-- End Period -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">End Period</label>
                    <input
                        type="month"
                        v-model="form.end_period"
                        required
                        class="w-full border-gray-300 rounded-md shadow-sm focus:border-maiic-500 focus:ring-maiic-500"
                    />
                </div>

                <!-- Export Format Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Export Format</label>
                    <select
                        v-model="form.format"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:border-maiic-500 focus:ring-maiic-500"
                    >
                        <option value="csv">CSV</option>
                        <option value="excel">Excel</option>
                    </select>
                </div>

                <!-- Export Type Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Export Type</label>
                    <select
                        v-model="form.export_type"
                        class="w-full border-gray-300 rounded-md shadow-sm focus:border-maiic-500 focus:ring-maiic-500"
                    >
                        <option value="summary">Summary (Current)</option>
                        <option value="matrix">Matrix Format</option>
                    </select>
                </div>

                <!-- Export Options -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Export Options</label>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input
                                type="checkbox"
                                v-model="form.include_headers"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-maiic-500"
                            />
                            <span class="ml-2 text-sm text-gray-700">Include Headers</span>
                        </label>
                        <label class="flex items-center">
                            <input
                                type="checkbox"
                                v-model="form.compress_file"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-maiic-500"
                            />
                            <span class="ml-2 text-sm text-gray-700">Compress as ZIP</span>
                        </label>
                    </div>
                </div>

                <!-- Error Message -->
                <div v-if="error" class="text-red-600 text-sm mt-2">
                    {{ error }}
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-3 mt-6">
                    <button
                        type="button"
                        @click="closeModal"
                        class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-maiic-500"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="processing"
                        class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-maiic-600 hover:bg-maiic-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-maiic-500 disabled:opacity-50"
                    >
                        <span v-if="processing" class="animate-spin mr-2">⏳</span>
                        {{ processing ? 'Exporting...' : 'Export' }}
                    </button>
                </div>
            </form>
        </div>
    </Modal>
</template>

<script>
import Modal from '../Modal.vue'

export default {
    components: {
        Modal
    },
    props: {
        show: {
            type: Boolean,
            default: false
        }
    },
    emits: ['close', 'success'],
    data() {
        return {
            processing: false,
            error: '',
            form: {
                start_period: '',
                end_period: '',
                format: 'csv',
                export_type: 'summary',
                include_headers: true,
                compress_file: true
            }
        }
    },
    watch: {
        show(newVal) {
            // Reset form when modal opens
            if (newVal) {
                this.resetForm()
            }
        }
    },
    methods: {
        resetForm() {
            this.form = {
                start_period: '',
                end_period: '',
                format: 'csv',
                export_type: 'summary',
                include_headers: true,
                compress_file: true
            }
            this.error = ''
        },
        closeModal() {
            this.$emit('close')
        },
        async submitExport() {
            // Validation
            if (!this.form.start_period || !this.form.end_period) {
                this.error = 'Please select both start and end periods'
                return
            }

            if (this.form.start_period > this.form.end_period) {
                this.error = 'Start period cannot be later than end period'
                return
            }

            this.error = ''
            this.processing = true

            try {
                // Build query parameters for GET request
                const params = new URLSearchParams({
                    start_period: this.form.start_period,
                    end_period: this.form.end_period,
                    format: this.form.format,
                    export_type: this.form.export_type,
                    include_headers: this.form.include_headers ? '1' : '0',
                    compress_file: this.form.compress_file ? '1' : '0'
                })

                const response = await fetch(route('transition-matrix-cummulative.report-by-period') + '?' + params.toString(), {
                    method: 'GET',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': this.form.format === 'excel' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv'
                    }
                })

                if (!response.ok) {
                    console.error('Response not OK:', response.status, response.statusText)
                    throw new Error('Export failed')
                }

                // Check if response is HTML (error page) instead of file data
                const contentType = response.headers.get('Content-Type')
                if (contentType && contentType.includes('text/html')) {
                    // This is an error page, not a file
                    const text = await response.text()
                    console.error('Server returned HTML error page:', text)

                    // Try to extract error message from HTML
                    const errorMatch = text.match(/No locked periods found for the selected date range/)
                    if (errorMatch) {
                        this.error = 'No locked periods found for the selected date range'
                    } else {
                        this.error = 'Export failed: Server returned an error page'
                    }
                    return
                }

                // Create blob from response
                console.log('Creating blob from response...')
                const blob = await response.blob()
                console.log('Blob created:', blob.size, 'bytes')

                // Check if blob is actually empty or too small (indicates error)
                if (blob.size === 0) {
                    this.error = 'Export failed: No data received from server'
                    return
                }

                // Get filename from Content-Disposition header if available
                const contentDisposition = response.headers.get('Content-Disposition')
                let filename = `Transition_Matrix_Cumulative_Report_${this.form.start_period}_to_${this.form.end_period}.${this.form.compress_file ? 'zip' : this.form.format}`

                if (contentDisposition) {
                    const filenameMatch = contentDisposition.match(/filename="(.+)"/)
                    if (filenameMatch) {
                        filename = filenameMatch[1]
                    }
                }

                // Create download link
                const url = window.URL.createObjectURL(blob)
                const a = document.createElement('a')
                a.style.display = 'none'
                a.href = url
                a.download = filename

                // Add to DOM, trigger click, and cleanup
                document.body.appendChild(a)
                console.log('Triggering download:', filename)

                // Use a timeout to ensure the click event is processed
                setTimeout(() => {
                    a.click()
                    document.body.removeChild(a)
                    window.URL.revokeObject(url)
                    console.log('Download triggered successfully')
                }, 100)

                this.closeModal()
                this.$emit('success', 'Cumulative report exported successfully')

            } catch (error) {
                this.error = 'Error exporting report: ' + (error.message || 'Unknown error')
            } finally {
                this.processing = false
            }
        }
    }
}
</script>
