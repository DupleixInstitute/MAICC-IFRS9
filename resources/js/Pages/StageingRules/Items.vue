<template>
    <app-layout>
        <template #header>
            <div>
                <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
                    <span>IFRS 9 Model Setup</span><span>/</span><span>Staging &amp; SICR Rules</span><span>/</span><span class="font-medium text-maiic-700">SICR Alert Items</span>
                </div>
                <h2 class="text-xl font-semibold text-gray-800">SICR Alert Items</h2>
                <p class="mt-1 text-sm text-gray-600">The individual risk signals inside each SICR group</p>
            </div>
        </template>
        <template #actions>
            <select v-model="filters.group_id" class="maiic-select !w-48" title="Filter by group" aria-label="Filter by group" @change="applyFilter">
                <option :value="null">All groups</option>
                <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
            </select>
            <button type="button" class="secondary-btn" @click="openImportModal">Import CSV</button>
            <button type="button" class="primary-btn" @click="openModal">Add item</button>
        </template>

        <div class="w-full space-y-4">
            <!-- Items Table -->
            <div class="maiic-panel">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                    <div>
                        <h3 class="font-semibold text-gray-900">SICR alert items<span v-if="filters.group_id" class="font-normal text-gray-500"> in {{ groups.find(g => g.id === filters.group_id)?.name }}</span></h3>
                        <p class="text-xs text-gray-500">{{ items.total ?? items.data.length }} item(s). Click a status to switch it on or off.</p>
                    </div>
                    <button v-if="filters.group_id" type="button" class="secondary-btn" @click="clearFilters">Clear filter</button>
                </div>

                <div class="overflow-x-auto">
                    <table class="maiic-table">
                        <thead>
                            <tr>
                                <th>Group</th>
                                <th>Item name</th>
                                <th>
                                    Status
                                </th>
                                <th class="num">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(item, index) in items.data" :key="item.id"
                                :class="index % 2 === 0 ? 'bg-white' : 'bg-gray-50'"
                                class="hover:bg-maiic-50 transition-colors duration-150"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-8 w-8">
                                            <div class="h-8 w-8 rounded bg-maiic-100 flex items-center justify-center">
                                                <span class="text-xs font-medium text-maiic-600">{{ item.group?.name?.substring(0, 2).toUpperCase() }}</span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">{{ item.group?.name }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900">{{ item.name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <button
                                        @click="toggle(item.id)"
                                        :class="item.active ? 'bg-maiic-100 text-maiic-800' : 'bg-red-100 text-red-800'"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium transition-colors duration-200 hover:opacity-75"
                                    >
                                        <svg :class="item.active ? 'text-maiic-400' : 'text-red-400'" class="-ml-0.5 mr-1.5 h-3 w-3" fill="currentColor" viewBox="0 0 8 8">
                                            <circle cx="4" cy="4" r="3"/>
                                        </svg>
                                        {{ item.active ? 'Active' : 'Inactive' }}
                                    </button>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-1.5">
                                        <button type="button" class="maiic-action maiic-action-edit" title="Edit item" @click="edit(item)"><font-awesome-icon icon="pen" /></button>
                                        <button type="button" class="maiic-action maiic-action-delete" title="Delete item" @click="destroy(item.id)"><font-awesome-icon icon="trash" /></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="items.data.length === 0">
                                <td colspan="4" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                        </svg>
                                        <h3 class="text-sm font-medium text-gray-900 mb-1">No SICR items found</h3>
                                        <p class="text-sm text-gray-500 mb-4">{{ filters.group_id ? 'No items in this group yet.' : 'Use Add item at the top right to create the first one.' }}</p>
                                        <button
                                            @click="openModal"
                                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-maiic-700 bg-maiic-100 hover:bg-maiic-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-maiic-500"
                                        >
                                            <svg class="-ml-1 mr-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"></path>
                                            </svg>
                                            Add First Item
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="items.links" class="bg-white px-6 py-3 border-t border-gray-200">
                    <pagination :links="items.links"/>
                </div>
            </div>
        </div>

        <!-- Add/Edit Item Modal -->
        <jet-modal :show="showModal" @close="closeModal" max-width="2xl">
            <div class="bg-white rounded-lg overflow-hidden">
                <div class="bg-gradient-to-r from-maiic-600 to-maiic-600 px-6 py-4">
                    <h3 class="text-lg font-semibold text-white flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path v-if="!editingId" fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"></path>
                            <path v-else d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
                        </svg>
                        {{ editingId ? 'Edit SICR Item' : 'Add New SICR Item' }}
                    </h3>
                    <p class="mt-1 text-maiic-100 text-sm">{{ editingId ? 'Update the item information' : 'Create a new risk factor item within a group' }}</p>
                </div>

                <form @submit.prevent="save" class="p-6">
                    <div class="space-y-6">
                        <div>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <jet-label class="text-sm font-medium text-gray-900">
                                    SICR Group *
                                </jet-label>
                                <p class="text-xs text-gray-500 mt-1">Select the group this item belongs to</p>
                                <div class="mt-3 relative">
                                    <select
                                        v-model.number="form.group_id"
                                        class="form-input"
                                        required
                                        :disabled="processing"
                                    >
                                        <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                                    </select>
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <svg class="h-4 w-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <jet-label class="text-sm font-medium text-gray-900">
                                    Item Name *
                                </jet-label>
                                <p class="text-xs text-gray-500 mt-1">Descriptive name for this risk factor</p>
                                <div class="mt-3 relative">
                                    <input
                                        v-model="form.name"
                                        class="form-input"
                                        type="text"
                                        placeholder="e.g., Debt-to-Equity Ratio, Current Ratio"
                                        required
                                        :disabled="processing"
                                    />
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <svg class="h-4 w-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-maiic-50 border border-maiic-200 rounded-lg p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-maiic-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-maiic-800">Item Status</h3>
                                    <div class="mt-2 text-sm text-maiic-700">
                                        <p>New items are created as <strong>Active</strong> by default. You can toggle the status later using the status button in the table.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-6 border-t border-gray-200 mt-6 space-x-3">
                        <button
                            type="button"
                            @click="closeModal"
                            :disabled="processing"
                            class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-maiic-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="processing || !form.name || !form.group_id"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gradient-to-r from-maiic-600 to-maiic-600 hover:from-maiic-700 hover:to-maiic-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-maiic-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                        >
                            <svg v-if="processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="-ml-1 mr-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            {{ processing ? 'Saving...' : (editingId ? 'Update Item' : 'Create Item') }}
                        </button>
                    </div>
                </form>
            </div>
        </jet-modal>

        <!-- Bulk Import Modal -->
        <jet-modal :show="showImportModal" @close="closeImportModal" max-width="lg">
            <div class="bg-white rounded-lg overflow-hidden">
                <div class="bg-gradient-to-r from-maiic-600 to-maiic-600 px-6 py-4">
                    <h3 class="text-lg font-semibold text-white flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        Bulk Import SICR Items
                    </h3>
                    <p class="mt-1 text-maiic-100 text-sm">Import multiple items from a CSV file</p>
                </div>

                <div class="p-6">
                    <div class="mb-4">
                        <div class="bg-maiic-50 border border-maiic-200 rounded-lg p-4 mb-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-maiic-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-maiic-800">CSV Format Requirements</h3>
                                    <div class="mt-2 text-sm text-maiic-700">
                                        <p>Your CSV file should contain the following columns:</p>
                                        <ul class="list-disc list-inside mt-2">
                                            <li><strong>group</strong> - Group name (must exist)</li>
                                            <li><strong>name</strong> - Item name (required)</li>
                                            <li><strong>active</strong> - Status (1 for active, 0 for inactive)</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-4">
                            <jet-label class="text-sm font-medium text-gray-900">
                                Select CSV File *
                            </jet-label>
                            <p class="text-xs text-gray-500 mt-1">Choose a CSV file containing item data</p>
                            <div class="mt-3">
                                <input
                                    type="file"
                                    @change="onFile"
                                    accept=".csv,text/csv"
                                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-maiic-50 file:text-maiic-700 hover:file:bg-maiic-100 file:cursor-pointer border border-gray-300 rounded-md"
                                    :disabled="processing"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-gray-200 space-x-3">
                        <button
                            type="button"
                            @click="closeImportModal"
                            :disabled="processing"
                            class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-maiic-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="uploadCsv"
                            :disabled="processing || !csvFile"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gradient-to-r from-maiic-600 to-maiic-600 hover:from-maiic-700 hover:to-maiic-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-maiic-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                        >
                            <svg v-if="processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 818-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="-ml-1 mr-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            {{ processing ? 'Uploading...' : 'Upload CSV' }}
                        </button>
                    </div>
                </div>
            </div>
        </jet-modal>

        <teleport to="head">
            <title>SICR Items - IFRS 9 Staging Rules</title>
        </teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetButton from '@/Jetstream/Button.vue'
import JetLabel from '@/Jetstream/Label.vue'
import JetModal from '@/Jetstream/Modal.vue'
import Pagination from '@/Jetstream/Pagination.vue'
import { confirmDialog } from '@/Components/confirmDialog'

export default {
    props: { groups: Array, items: Object, filters: Object },
    components: { AppLayout, JetButton, JetLabel, JetModal, Pagination },
    data(){
        return {
            form: { group_id: this.filters.group_id || (this.groups[0]?.id||null), name: '' },
            processing: false,
            editingId: null,
            csvFile: null,
            showModal: false,
            showImportModal: false
        }
    },
    computed: {
        activeFilter() {
            if (this.filters.group_id) {
                const group = this.groups.find(g => g.id === this.filters.group_id)
                return group ? group.name : null
            }
            return null
        }
    },
    methods:{
        // Modal Controls
        openModal() {
            this.showModal = true
            this.editingId = null
            this.form = { group_id: this.filters.group_id || (this.groups[0]?.id||null), name: '' }
        },
        closeModal() {
            this.showModal = false
            this.editingId = null
            this.form = { group_id: this.filters.group_id || (this.groups[0]?.id||null), name: '' }
        },
        openImportModal() {
            this.showImportModal = true
            this.csvFile = null
        },
        closeImportModal() {
            this.showImportModal = false
            this.csvFile = null
        },

        // Filter Management
        applyFilter(){
            this.$inertia.get(this.route('sicr-items.index'), this.filters, { preserveState: true })
        },
        clearFilters() {
            this.filters.group_id = null
            this.applyFilter()
        },

        // CRUD Operations
        save(){
            if (!this.form.name || !this.form.group_id) return
            this.processing = true
            const routeName = this.editingId ? this.route('sicr-items.update', this.editingId) : this.route('sicr-items.store')
            const method = this.editingId ? 'put' : 'post'
            this.$inertia[method](routeName, this.form, {
                onFinish: () => { this.processing = false },
                onSuccess: () => {
                    this.closeModal()
                    this.$toast?.success(this.editingId ? 'Item updated successfully!' : 'Item created successfully!')
                },
                onError: (errors) => {
                    console.error('Validation errors:', errors)
                    this.$toast?.error('Please check your input and try again.')
                }
            })
        },
        edit(item) {
            this.editingId = item.id
            this.form = { group_id: item.group_id, name: item.name }
            this.showModal = true
        },
        toggle(id) {
            this.$inertia.post(this.route('sicr-items.toggle', id), {}, {
                onSuccess: () => {
                    this.$toast?.success('Item status updated successfully!')
                }
            })
        },
        async destroy(id) {
            if (await confirmDialog({ title: 'Delete this item?', message: 'The SICR alert item is removed. This cannot be undone.', confirmLabel: 'Delete', tone: 'danger' })) {
                this.$inertia.delete(this.route('sicr-items.destroy', id), {
                    onSuccess: () => {
                        this.$toast?.success('Item deleted successfully!')
                    }
                })
            }
        },

        // File Upload
        onFile(e) {
            this.csvFile = e.target.files[0]
        },
        uploadCsv() {
            if (!this.csvFile) return
            this.processing = true
            const data = new FormData()
            data.append('file', this.csvFile)
            this.$inertia.post(this.route('sicr-items.import'), data, {
                onFinish: () => { this.processing = false },
                onSuccess: () => {
                    this.closeImportModal()
                    this.$toast?.success('CSV imported successfully!')
                },
                onError: (errors) => {
                    console.error('Import errors:', errors)
                    this.$toast?.error('Failed to import CSV. Please check the file format.')
                }
            })
        }
    }
}
</script>

<style scoped>
.form-input {
    @apply block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-maiic-500 focus:border-maiic-500 transition-all duration-200;
}

.form-input:focus {
    @apply border-maiic-300 bg-maiic-50;
}

.filter-select {
    @apply block w-full pl-3 pr-10 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-maiic-500 focus:border-maiic-500 transition-all duration-200 sm:text-sm;
}

.bg-gradient-to-r {
    background-image: linear-gradient(to right, var(--tw-gradient-stops));
}
</style>
