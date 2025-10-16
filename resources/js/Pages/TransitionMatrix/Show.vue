<template>
    <app-layout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Transition Matrix Details
                </h2>
                <Link :href="route('transition-matrices.index')" 
                      class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring focus:ring-gray-300 disabled:opacity-25 transition">
                    Back to List
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <!-- Matrix Details -->
                    <div class="p-6 border-b border-gray-200">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">Matrix Information</h3>
                                <dl class="mt-2 text-sm text-gray-600">
                                    <div class="mt-1">
                                        <dt class="font-medium">Profile</dt>
                                        <dd>{{ matrix.transition_profile.name }}</dd>
                                    </div>
                                    <div class="mt-1">
                                        <dt class="font-medium">Reporting Period</dt>
                                        <dd>{{ formatDate(matrix.start_reporting_period) }} - {{ formatDate(matrix.end_reporting_period) }}</dd>
                                    </div>
                                    <div class="mt-1">
                                        <dt class="font-medium">Description</dt>
                                        <dd>{{ matrix.description || 'No description provided' }}</dd>
                                    </div>
                                    <div class="mt-1">
                                        <dt class="font-medium">Status</dt>
                                        <dd>
                                            <span :class="{
                                                'px-2 inline-flex text-xs leading-5 font-semibold rounded-full': true,
                                                'bg-green-100 text-green-800': matrix.status === 'active',
                                                'bg-yellow-100 text-yellow-800': matrix.status === 'draft',
                                                'bg-gray-100 text-gray-800': matrix.status === 'archived' || !matrix.status
                                            }">
                                                {{ matrix.status ? (matrix.status.charAt(0).toUpperCase() + matrix.status.slice(1)) : 'Draft' }}
                                            </span>
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                            <div>
                                <h3 class="text-lg font-medium text-gray-900">Actions</h3>
                                <div class="mt-2 space-y-2">
                                    <button @click="showUpdateLoanBookModal = true"
                                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition">
                                        Update Loan Book
                                    </button>
                                    <button v-if="matrix.external_file_path"
                                            @click="downloadMatrix"
                                            class="ml-2 inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring focus:ring-gray-300 disabled:opacity-25 transition">
                                        Download Matrix Work Paper
                                    </button>
                                    <button
                                        disabled
                                        title="Coming soon"
                                        class="ml-2 inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-500 active:bg-green-900 focus:outline-none focus:border-green-900 focus:ring focus:ring-green-300 disabled:opacity-25 disabled:cursor-not-allowed transition">
                                        Download Entries (Excel)
                                    </button>
                                    <button
                                        disabled
                                        title="Coming soon"
                                        class="ml-2 inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring focus:ring-blue-300 disabled:opacity-25 disabled:cursor-not-allowed transition">
                                        Import Entries
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Matrix Entries -->
                    <div class="p-6">
                        <transition-matrix-table 
                            :matrix="matrix"
                            :profile="matrix.transition_profile"
                            :portfolio-groups="portfolioGroups"
                            :states="states"
                            :entries="matrix.entries || []"
                            @update-entries="updateEntries" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Update Loan Book Modal -->
        <update-loan-book-modal 
            v-if="showUpdateLoanBookModal"
            :show="showUpdateLoanBookModal"
            :matrix="matrix"
            @close="showUpdateLoanBookModal = false"
            @update="updateLoanBook" />
    </app-layout>
</template>

<script>
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import TransitionMatrixTable from './Components/TransitionMatrixTable.vue'
import UpdateLoanBookModal from './Components/UpdateLoanBookModal.vue'

export default {
    components: {
        AppLayout,
        Link,
        TransitionMatrixTable,
        UpdateLoanBookModal
    },

    props: {
        matrix: {
            type: Object,
            required: true
        },
        portfolioGroups: {
            type: Array,
            required: true
        },
        states: {
            type: Object,
            required: true
        }
    },

    setup(props) {
        const showUpdateLoanBookModal = ref(false)

        const downloadMatrix = () => {
            // Create a temporary link and trigger download
            const link = document.createElement('a')
            link.href = `/storage/${props.matrix.external_file_path}`
            link.download = `matrix-${props.matrix.id}.pdf` // Assuming it's a PDF, adjust if needed
            document.body.appendChild(link)
            link.click()
            document.body.removeChild(link)
        }

        const formatDate = (date) => {
            return new Date(date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            })
        }

        const updateEntries = (entries) => {
            useForm({
                entries: entries
            }).post(route('transition-matrices.entries.update', props.matrix.id))
        }

        const updateLoanBook = (data) => {
            useForm(data).post(route('transition-matrices.update-loan-book', props.matrix.id))
        }

        return {
            showUpdateLoanBookModal,
            downloadMatrix,
            formatDate,
            updateEntries,
            updateLoanBook
        }
    }
}
</script>
