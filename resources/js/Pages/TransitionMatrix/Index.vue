<template>
    <app-layout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Transition Matrices
                </h2>
                <Link :href="route('transition-matrices.create')" 
                      class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring focus:ring-gray-300 disabled:opacity-25 transition">
                    Create New Matrix
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6">
                        <!-- Search and Filters -->
                        <div class="mb-6 flex justify-between items-center space-x-4">
                            <div class="flex-1 max-w-sm">
                                <jet-input type="text" 
                                          v-model="search" 
                                          class="w-full"
                                          placeholder="Search matrices..." />
                            </div>
                            <div class="flex space-x-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Start Date</label>
                                    <input type="date" 
                                           v-model="startDate"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">End Date</label>
                                    <input type="date" 
                                           v-model="endDate"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" />
                                </div>
                            </div>
                        </div>

                        <!-- Matrices Table -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th class="px-6 py-3">Transition Profile Id</th>
                                        <th class="px-6 py-3">Portfolio Group Id</th>
                                        <th class="px-6 py-3">Calculation Source</th>
                                        <th class="px-6 py-3">Payments Included?</th>
                                        <th class="px-6 py-3">Start Period</th>
                                        <th class="px-6 py-3">End Period</th>
                                        <th class="px-6 py-3">Transition Years</th>
                                        <th class="px-6 py-3">Records Transitioned</th>
                                        <th class="px-6 py-3">Records Updated</th>
                                        <th class="px-6 py-3">Reporting Periods</th>
                                        <th class="px-6 py-3">No of Calc Runs</th>
                                        <th class="px-6 py-3">Transition Balance</th>
                                        <th class="px-6 py-3">Updated Balance</th>
                                        <th class="px-6 py-3">Status</th>
                                        <th class="px-6 py-3">Last Calc Date</th>
                                        <th class="px-6 py-3">Comments</th>
                                        <th class="px-6 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-if="matrices.data.length === 0">
                                        <td colspan="15" class="px-6 py-4 text-center text-gray-500">No transition matrices found.</td>
                                    </tr>
                                    <tr v-for="matrix in matrices.data" :key="matrix.id">
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.transition_profile_id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.portfolio?.name ?? '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ calculationSourceLabels[matrix.calculation_source] }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.pd_start_stage_total_type }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.start_reporting_period }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.end_reporting_period }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.transition_years }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.records_count_transitioned }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.records_count_updated }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.reporting_periods_count }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.run_no }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.transition_balance }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.updated_balance }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                                        <span
                                            class="px-2 py-1 rounded-full text-xs font-semibold"
                                            :class="{
                                            'bg-red-100 text-red-700': matrix.status === 'closed',
                                            'bg-orange-100 text-orange-700': matrix.status === 'draft'
                                            }"
                                        >
                                            {{ matrix.status === 'closed' ? 'Closed' : 'Draft' }}
                                        </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ formatDate(matrix.last_calculation_date) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.comments }}</td>
                                        <!-- <td class="px-6 py-4 text-right text-sm font-medium">
                                            <Link :href="route('transition-matrices.show', matrix.id)" class="text-indigo-600 hover:text-indigo-900 mr-4">View</Link>
                                        </td> -->
                                        <td class="px-6 py-4 text-right text-sm font-medium">
                                            <div class="flex items-center justify-end space-x-4 text-blue-600">
                                                <button
                                                    @click="openModal('view', matrix)"
                                                    class="text-gray-600 hover:text-gray-800" title="View"
                                                >
                                                    <font-awesome-icon :icon="['fas', 'table']" class="w-8 h-8" />
                                                </button>

                                                <button 
                                                    v-if="matrix.status === 'draft'"
                                                    @click="openModal('edit', matrix)"
                                                    class="text-gray-600 hover:text-gray-800" title="Edit"
                                                >
                                                    <font-awesome-icon :icon="['fas', 'pen']" class="w-8 h-8" />
                                                </button>

                                                <button 
                                                    v-if="matrix.status === 'draft'"
                                                    @click="reRunMatrix(matrix.id)"
                                                    class="text-gray-600 hover:text-gray-800" title="Re-run"
                                                >
                                                    <font-awesome-icon :icon="['fas', 'calculator']" class="w-8 h-8" />
                                                </button>

                                                 <!-- LOCKED STATE -->
                                                <button
                                                    v-if="matrix.status === 'closed'"
                                                    @click="lockPD(matrix.id)"
                                                    class="text-gray-600 hover:text-gray-800" title="Unlock PD"
                                                >
                                                    <font-awesome-icon :icon="['fas', 'lock-open']" class="w-8 h-8" />
                                                </button>

                                                <!-- ACTIVE STATE -->

                                                <button
                                                    v-else="matrix.status === 'draft'"
                                                    @click="lockPD(matrix.id)"
                                                    class="text-gray-600 hover:text-gray-800" title="Lock PD"
                                                >
                                                    <font-awesome-icon :icon="['fas', 'lock']" class="w-8 h-8" />
                                                </button>

                                                <button
                                                     v-if="matrix.status === 'closed'"
                                                     @click="openLoanBookModal(matrix)"
                                                    class="text-gray-600 hover:text-gray-800" title="Update Loan Book"
                                                >
                                                    <font-awesome-icon :icon="['fas', 'book']" class="w-8 h-8" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <pagination :links="matrices.links" class="mt-6" />
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
            <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
                <h2 class="text-lg font-bold mb-4">Update Loan Book Period</h2>
                <p class="mb-4">Select the reporting period to update loan books for <strong>{{ selectedTD?.portfolio_group?.name }}</strong>.</p>
                
                <label for="period" class="block mb-2 text-sm font-medium text-gray-700">Reporting Period</label>
                <input 
                    type="month" 
                    v-model="selectedPeriod" 
                    id="period" 
                    class="border-gray-300 rounded-md shadow-sm w-full mb-4"
                >
        
                <div class="flex justify-end space-x-2">
                    <button @click="showModal = false" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
                    <button 
                        @click="submitUpdate" 
                        class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700"
                        :disabled="loading === selectedTD?.id"
                    >
                        <span v-if="loading === selectedTD?.id" class="animate-spin mr-1"></span>
                        Update
                    </button>
                </div>
            </div>
        </div>

        <HelpManual />

        <!-- ✅ INSERT MODAL HERE -->
        <Modal v-if="modalVisible" @close="modalVisible = false">
            <ViewEditMatrix
                :transitionMatrix="selectedMatrix"
                :mode="mode"
                type="normal"
                />
        </Modal>
    </app-layout>
</template>
<script>
import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import debounce from 'lodash/debounce'
import AppLayout from '@/Layouts/AppLayout.vue'
import JetInput from '@/Jetstream/Input.vue'
import Pagination from '@/Shared/Pagination.vue'
import Modal from './Modal.vue'
import ViewEditMatrix from './ViewEditMatrix.vue'
import HelpManual from '../../Components/HelpManual.vue';

export default {
    components: {
        AppLayout,
        JetInput,
        Pagination,
        Modal,
        ViewEditMatrix,
        HelpManual,
    },

    props: {
        matrices: {
            type: Object,
            required: true,
        },
        filters: {
            type: Object,
            default: () => ({}),
        },
    },

    setup(props) {
        const search = ref(props.filters.search || '')
        const startDate = ref(props.filters.start_date || '')
        const endDate = ref(props.filters.end_date || '')
        const modalVisible = ref(false)
        const selectedMatrix = ref(null)
        const mode = ref('view')
        const selectedType = ref('normal')

        const showModal = ref(false)
        const selectedTD = ref(null)
        const selectedPeriod = ref('')
        const loading = ref(null)

        const calculationSourceLabels = {
            manual: 'Manual',
            system: 'System'
        }

        const updateSearch = debounce(() => {
            router.get(
                route('transition-matrices.index'),
                {
                    search: search.value,
                    start_date: startDate.value,
                    end_date: endDate.value,
                },
                {
                    preserveState: true,
                    replace: true,
                    preserveScroll: true,
                }
            )
        }, 300)

        watch([search, startDate, endDate], updateSearch)

        function openModal(matrixMode, matrix, type = 'normal') {
            selectedMatrix.value = matrix
            mode.value = matrixMode
            selectedType.value = type
            modalVisible.value = true
        }

        function openLoanBookModal(matrix) {
            selectedTD.value = matrix
            showModal.value = true
        }

        async function submitUpdate() {
            if (!selectedPeriod.value) {
                alert("Please select a reporting period.")
                return
            }

            loading.value = selectedTD.value?.id

            try {
                await router.post(route('transition-matrices.matrix.loanbook-update', selectedTD.value.id), {
                    reporting_period: selectedPeriod.value + '-01',
                }, {
                    preserveState: true,
                    preserveScroll: true,
                    onFinish: () => {
                        loading.value = null
                        showModal.value = false
                        selectedPeriod.value = ''
                    }
                })
            } catch (error) {
                alert('Error updating loan book: ' + (error.response?.data?.message || error.message))
                loading.value = null
            }
        }

        async function reRunMatrix(matrixId) {
            try {
                const confirmed = confirm('Are you sure you want to re-run this calculation?')
                if (!confirmed) return

                await axios.post(`/transition-matrix/${matrixId}/rerun`)

                alert('Matrix re-run completed successfully.')
            } catch (error) {
                alert('Error while re-running matrix: ' + (error.response?.data?.message || error.message))
            }
        }

        function lockPD(id) {
            if (confirm('Are you sure you want to change the lock status of this Probability Of Default?')) {
                loading.value = id
                router.post(route('transition-matrices.lock', id), {}, {
                    preserveScroll: true,
                    onFinish: () => { loading.value = null },
                    onSuccess: () => { router.reload({ only: ['matrices'] }) },
                    onError: () => { alert('Something went wrong. Please try again.') },
                })
            }
        }

        const formatDate = (date) => {
            return new Date(date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
            })
        }

        return {
            search,
            startDate,
            endDate,
            modalVisible,
            selectedMatrix,
            mode,
            selectedType,
            openModal,
            showModal,
            selectedTD,
            selectedPeriod,
            loading,
            openLoanBookModal,
            submitUpdate,
            reRunMatrix,
            lockPD,
            formatDate,
            calculationSourceLabels, // ✅ Now returned
        }
    },
}
</script>
