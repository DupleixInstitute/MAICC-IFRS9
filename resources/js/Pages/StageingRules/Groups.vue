<template>
    <app-layout>
        <template #header>
            <div>
                <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
                    <span>IFRS 9 Model Setup</span><span>/</span><span>Staging &amp; SICR Rules</span><span>/</span><span class="font-medium text-maiic-700">SICR Groups</span>
                </div>
                <h2 class="text-xl font-semibold text-gray-800">SICR Groups</h2>
                <p class="mt-1 text-sm text-gray-600">Groups that hold the alert items used to judge a significant increase in credit risk</p>
            </div>
        </template>
        <template #actions>
            <button type="button" class="secondary-btn" @click="openImportModal">Import CSV</button>
            <button type="button" class="primary-btn" @click="openModal">Add group</button>
        </template>

        <div class="w-full space-y-4">
            <!-- Groups Table -->
            <div class="maiic-panel">
                <div class="border-b border-gray-200 px-5 py-4">
                    <h3 class="font-semibold text-gray-900">SICR groups</h3>
                    <p class="text-xs text-gray-500">{{ groups.total ?? groups.data.length }} group(s)</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="maiic-table">
                        <thead>
                            <tr>
                                <th>Group name</th>
                                <th>
                                    Description
                                </th>
                                <th class="num">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(group, index) in groups.data" :key="group.id"
                                :class="index % 2 === 0 ? 'bg-white' : 'bg-gray-50'"
                                class="hover:bg-maiic-50 transition-colors duration-150"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-8 w-8">
                                            <div class="h-8 w-8 rounded-full bg-maiic-100 flex items-center justify-center">
                                                <span class="text-sm font-medium text-maiic-600">{{ group.name.charAt(0).toUpperCase() }}</span>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">{{ group.name }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900">{{ group.description }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-1.5">
                                        <button type="button" class="maiic-action maiic-action-edit" title="Edit group" @click="edit(group)"><font-awesome-icon icon="pen" /></button>
                                        <button type="button" class="maiic-action maiic-action-delete" title="Delete group" @click="destroy(group.id)"><font-awesome-icon icon="trash" /></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="groups.data.length === 0">
                                <td colspan="3" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center">
                                        <svg class="w-12 h-12 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 009.586 13H7" />
                                        </svg>
                                        <h3 class="text-sm font-medium text-gray-900 mb-1">No SICR groups found</h3>
                                        <p class="text-sm text-gray-500">Use <strong>Add group</strong> at the top right to create the first one.</p>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="groups.links" class="bg-white px-6 py-3 border-t border-gray-200">
                    <pagination :links="groups.links"/>
                </div>
            </div>
        </div>

        <!-- Add/Edit Group Modal -->
        <jet-modal :show="showModal" @close="closeModal" max-width="2xl">
            <div class="bg-white rounded-lg overflow-hidden">
                <div class="bg-gradient-to-r from-maiic-600 to-maiic-600 px-6 py-4">
                    <h3 class="text-lg font-semibold text-white flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path v-if="!editingId" fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"></path>
                            <path v-else d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
                        </svg>
                        {{ editingId ? 'Edit SICR Group' : 'Add New SICR Group' }}
                    </h3>
                    <p class="mt-1 text-maiic-100 text-sm">{{ editingId ? 'Update the group information' : 'Define logical groupings for credit risk factors' }}</p>
                </div>

                <form @submit.prevent="save" class="p-6">
                    <div class="space-y-6">
                        <div>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <jet-label class="text-sm font-medium text-gray-900">
                                    Group Name *
                                </jet-label>
                                <p class="text-xs text-gray-500 mt-1">Short identifier for the group</p>
                                <div class="mt-3 relative">
                                    <input
                                        v-model="form.name"
                                        class="form-input"
                                        type="text"
                                        placeholder="e.g., Financial Ratios"
                                        required
                                        :disabled="processing"
                                    />
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <svg class="h-4 w-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <jet-label class="text-sm font-medium text-gray-900">
                                    Description *
                                </jet-label>
                                <p class="text-xs text-gray-500 mt-1">Detailed explanation of this group's purpose</p>
                                <div class="mt-3">
                                    <textarea
                                        v-model="form.description"
                                        class="form-input"
                                        rows="4"
                                        placeholder="Describe what types of risk factors this group contains..."
                                        required
                                        :disabled="processing"
                                    ></textarea>
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
                            :disabled="processing || !form.name || !form.description"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gradient-to-r from-maiic-600 to-maiic-600 hover:from-maiic-700 hover:to-maiic-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-maiic-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                        >
                            <svg v-if="processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="-ml-1 mr-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            {{ processing ? 'Saving...' : (editingId ? 'Update Group' : 'Create Group') }}
                        </button>
                    </div>
                </form>
            </div>
        </jet-modal>

        <!-- Bulk import: how to, sample CSV, upload -->
        <jet-modal :show="showImportModal" @close="closeImportModal" max-width="2xl">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900">Import SICR groups from a CSV file</h3>
                <p class="mt-1 text-sm text-gray-500">Add many groups at once. A group that already exists, by name, is left as it is.</p>

                <div class="mt-5 grid gap-5 md:grid-cols-5">
                    <div class="md:col-span-3">
                        <FileDrop :key="importKey" :error="importError" :disabled="processing" @file="onFile"/>
                    </div>
                    <ImportHowTo class="md:col-span-2" title="How to import" :sample-url="route('import-samples.show', 'sicr-groups')">
                        <li>Download the sample CSV: two columns, name and description, headings in lower case.</li>
                        <li>One row per group. Rows without a name are skipped.</li>
                        <li>A name that already exists is not added again and its description is not changed.</li>
                        <li>Choose the file and press Start import. The message at the top says how many rows were read.</li>
                    </ImportHowTo>
                </div>

                <div class="mt-6 flex justify-end gap-2 border-t border-gray-200 pt-4">
                    <button type="button" class="secondary-btn" :disabled="processing" @click="closeImportModal">Cancel</button>
                    <button type="button" class="primary-btn" :disabled="processing || !csvFile" @click="uploadCsv">
                        {{ processing ? 'Importing...' : 'Start import' }}
                    </button>
                </div>
            </div>
        </jet-modal>

        <teleport to="head">
            <title>SICR Groups - IFRS 9 Staging Rules</title>
        </teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetButton from '@/Jetstream/Button.vue'
import JetLabel from '@/Jetstream/Label.vue'
import JetModal from '@/Jetstream/Modal.vue'
import FileDrop from '@/Components/Data/FileDrop.vue'
import ImportHowTo from '@/Components/Data/ImportHowTo.vue'
import Pagination from '@/Jetstream/Pagination.vue'
import { confirmDialog } from '@/Components/confirmDialog'

export default {
    props: { groups: Object },
    components: { FileDrop, ImportHowTo, AppLayout, JetButton, JetLabel, JetModal, Pagination },
    data(){
        return {
            form: { name: '', description: '' },
            processing: false,
            editingId: null,
            csvFile: null,
            showModal: false,
            showImportModal: false,
            importError: '',
            importKey: 0
        }
    },
    methods:{
        // Modal Controls
        openModal() {
            this.showModal = true
            this.editingId = null
            this.form = { name: '', description: '' }
        },
        closeModal() {
            this.showModal = false
            this.editingId = null
            this.form = { name: '', description: '' }
        },
        openImportModal() {
            this.showImportModal = true
            this.csvFile = null
            this.importError = ''
            this.importKey++
        },
        closeImportModal() {
            this.showImportModal = false
            this.csvFile = null
        },

        // CRUD Operations
        save(){
            if (!this.form.name || !this.form.description) return
            this.processing = true
            const routeName = this.editingId ? this.route('sicr-groups.update', this.editingId) : this.route('sicr-groups.store')
            const method = this.editingId ? 'put' : 'post'
            this.$inertia[method](routeName, this.form, {
                onFinish: () => { this.processing = false },
                onSuccess: () => {
                    this.closeModal()
                    this.$toast?.success(this.editingId ? 'Group updated successfully!' : 'Group created successfully!')
                },
                onError: (errors) => {
                    console.error('Validation errors:', errors)
                    this.$toast?.error('Please check your input and try again.')
                }
            })
        },
        edit(group) {
            this.editingId = group.id
            this.form = { name: group.name, description: group.description }
            this.showModal = true
        },
        async destroy(id) {
            if (await confirmDialog({ title: 'Delete this group?', message: 'The SICR group is removed. This cannot be undone.', confirmLabel: 'Delete', tone: 'danger' })) {
                this.$inertia.delete(this.route('sicr-groups.destroy', id), {
                    onSuccess: () => {
                        this.$toast?.success('Group deleted successfully!')
                    }
                })
            }
        },

        // File Upload
        onFile(file) {
            this.csvFile = file
            this.importError = ''
        },
        uploadCsv() {
            if (!this.csvFile) return
            this.processing = true
            const data = new FormData()
            data.append('file', this.csvFile)
            this.$inertia.post(this.route('sicr-groups.import'), data, {
                onFinish: () => { this.processing = false },
                onSuccess: () => {
                    this.closeImportModal()
                },
                onError: (errors) => {
                    this.importError = Object.values(errors || {})[0] || 'The file could not be imported. Check it against the sample CSV.'
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

.bg-gradient-to-r {
    background-image: linear-gradient(to right, var(--tw-gradient-stops));
}
</style>
