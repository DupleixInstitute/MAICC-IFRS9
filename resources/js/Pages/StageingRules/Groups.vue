<template>
    <app-layout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight flex items-center">
                        <svg class="w-6 h-6 mr-2 text-indigo-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z"></path>
                        </svg>
                        SICR Groups
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">Manage Significant Increase in Credit Risk groupings</p>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="px-3 py-1 bg-indigo-100 text-indigo-800 text-xs font-medium rounded-full">
                        {{ groups?.data?.length || 0 }} Groups
                    </div>
                </div>
            </div>
        </template>
        
        <div class="max-w-6xl mx-auto space-y-6">
            <!-- Action Buttons -->
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-medium text-gray-900">SICR Group Management</h3>
                    <p class="mt-1 text-sm text-gray-500">Manage significant increase in credit risk groupings</p>
                </div>
                <div class="flex space-x-3">
                    <button
                        @click="openImportModal"
                        class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                    >
                        <svg class="-ml-1 mr-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        Import CSV
                    </button>
                    <button
                        @click="openModal"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                    >
                        <svg class="-ml-1 mr-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"></path>
                        </svg>
                        Add New Group
                    </button>
                </div>
            </div>
            
            <!-- Groups Table -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-gray-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2v1a1 1 0 102 0V3h4v1a1 1 0 102 0V3a2 2 0 012 2v6a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm2.5 7a1.5 1.5 0 100-3 1.5 1.5 0 000 3zm2.45-1.5a2.5 2.5 0 114.9 0 .5.5 0 00-.9 0H8.95zM12 12.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3z" clip-rule="evenodd"></path>
                        </svg>
                        Existing SICR Groups
                    </h3>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    <div class="flex items-center space-x-1">
                                        <span>Group Name</span>
                                        <svg class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Description
                                </th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="(group, index) in groups.data" :key="group.id" 
                                :class="index % 2 === 0 ? 'bg-white' : 'bg-gray-50'"
                                class="hover:bg-indigo-50 transition-colors duration-150"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-8 w-8">
                                            <div class="h-8 w-8 rounded-full bg-indigo-100 flex items-center justify-center">
                                                <span class="text-sm font-medium text-indigo-600">{{ group.name.charAt(0).toUpperCase() }}</span>
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
                                    <div class="flex justify-end space-x-2">
                                        <button
                                            @click="edit(group)"
                                            class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                                        >
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
                                            </svg>
                                            Edit
                                        </button>
                                        <button
                                            @click="destroy(group.id)"
                                            class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded text-red-700 bg-red-100 hover:bg-red-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200"
                                        >
                                            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                            </svg>
                                            Delete
                                        </button>
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
                                        <p class="text-sm text-gray-500">Get started by creating your first group above.</p>
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
                <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4">
                    <h3 class="text-lg font-semibold text-white flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path v-if="!editingId" fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"></path>
                            <path v-else d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path>
                        </svg>
                        {{ editingId ? 'Edit SICR Group' : 'Add New SICR Group' }}
                    </h3>
                    <p class="mt-1 text-indigo-100 text-sm">{{ editingId ? 'Update the group information' : 'Define logical groupings for credit risk factors' }}</p>
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
                            class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="processing || !form.name || !form.description"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
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
        
        <!-- Bulk Import Modal -->
        <jet-modal :show="showImportModal" @close="closeImportModal" max-width="lg">
            <div class="bg-white rounded-lg overflow-hidden">
                <div class="bg-gradient-to-r from-green-600 to-teal-600 px-6 py-4">
                    <h3 class="text-lg font-semibold text-white flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        Bulk Import SICR Groups
                    </h3>
                    <p class="mt-1 text-green-100 text-sm">Import multiple groups from a CSV file</p>
                </div>
                
                <div class="p-6">
                    <div class="mb-4">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-blue-800">CSV Format Requirements</h3>
                                    <div class="mt-2 text-sm text-blue-700">
                                        <p>Your CSV file should contain the following columns:</p>
                                        <ul class="list-disc list-inside mt-2">
                                            <li><strong>name</strong> - Group name (required)</li>
                                            <li><strong>description</strong> - Group description (required)</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 rounded-lg p-4">
                            <jet-label class="text-sm font-medium text-gray-900">
                                Select CSV File *
                            </jet-label>
                            <p class="text-xs text-gray-500 mt-1">Choose a CSV file containing group data</p>
                            <div class="mt-3">
                                <input 
                                    type="file" 
                                    @change="onFile" 
                                    accept=".csv,text/csv" 
                                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 file:cursor-pointer border border-gray-300 rounded-md"
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
                            class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="uploadCsv"
                            :disabled="processing || !csvFile"
                            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-gradient-to-r from-green-600 to-teal-600 hover:from-green-700 hover:to-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                        >
                            <svg v-if="processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
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
            <title>SICR Groups - IFRS 9 Staging Rules</title>
        </teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetButton from '@/Jetstream/Button.vue'
import JetLabel from '@/Jetstream/Label.vue'
import JetModal from '@/Jetstream/Modal.vue'
import Pagination from '@/Jetstream/Pagination.vue'

export default {
    props: { groups: Object },
    components: { AppLayout, JetButton, JetLabel, JetModal, Pagination },
    data(){
        return { 
            form: { name: '', description: '' }, 
            processing: false, 
            editingId: null, 
            csvFile: null,
            showModal: false,
            showImportModal: false
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
        destroy(id) { 
            if (confirm('Are you sure you want to delete this group?')) {
                this.$inertia.delete(this.route('sicr-groups.destroy', id), {
                    onSuccess: () => {
                        this.$toast?.success('Group deleted successfully!')
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
            this.$inertia.post(this.route('sicr-groups.import'), data, { 
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
    @apply block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-200;
}

.form-input:focus {
    @apply border-indigo-300 bg-indigo-50;
}

.bg-gradient-to-r {
    background-image: linear-gradient(to right, var(--tw-gradient-stops));
}
</style>
