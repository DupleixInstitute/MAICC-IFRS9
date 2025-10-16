<template>
    <app-layout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Transition Matrices Cummulative
                </h2>
                <Link :href="route('transition-matrix-cummulative.create')" 
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
                                        <th class="px-6 py-3">Start Period</th>
                                        <th class="px-6 py-3">End Period</th>
                                        <th class="px-6 py-3">Transition Periods</th>
                                        <th class="px-6 py-3">Records Transitioned</th>
                                        <!-- <th class="px-6 py-3">Records Updated</th> -->
                                        <th class="px-6 py-3">Periods Cummulated</th>
                                        <th class="px-6 py-3">Transition Balance</th>
                                        <th class="px-6 py-3">Calc Runs</th>
                                        <th class="px-6 py-3">Last Calc Date</th>
                                          <th class="px-6 py-3">Status</th>
                                        <th class="px-6 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-if="cumMatrix.data.length === 0">
                                        <td colspan="15" class="px-6 py-4 text-center text-gray-500">No transition matrices found.</td>
                                    </tr>
                                    <tr v-for="matrix in cumMatrix.data" :key="matrix.id">
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.id }}</td>
                                       <td class="px-6 py-4 whitespace-nowrap">{{ matrix.transition_profile_id}}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.portfolio?.name ?? '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ calculationSourceLabels[matrix.calculation_source] }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ formatDate(matrix.start_period) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ formatDate(matrix.end_period) }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.periods_count }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.records_counted }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <button 
                                            @click="showPeriods(matrix.periods_list)" 
                                            class="text-gray-600 hover:text-brown-800" title="Show Periods"
                                        >
                                            <font-awesome-icon :icon="['fas', 'eye']" class="w-8 h-8" />
                                        </button>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.transition_balance_cummulated }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.run_no }}</td> 
                                        <td class="px-6 py-4 whitespace-nowrap">{{ formatDate(matrix.last_reporting_period) }}</td>
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
                        <pagination :links="cumMatrix.links" class="mt-6" />
                    </div>
                </div>
            </div>
        </div>

        <div 
            v-if="periodsModalVisible" 
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            >
            <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-md">
                <h2 class="text-lg font-bold mb-4">Periods List</h2> 
                <button 
                @click="periodsModalVisible = false" 
                class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700"
                >
                Close
                </button>
                    <table class="min-w-full text-sm mb-4">
                    <thead>
                        <tr>
                        <th class="px-2 py-1 text-left">Start</th>
                        <th class="px-2 py-1 text-left">End</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(period, index) in currentPeriods" :key="index">
                        <td class="px-2 py-1">{{ period.start }}</td>
                        <td class="px-2 py-1">{{ period.end }}</td>
                        </tr>
                    </tbody>
                    </table>
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
            type="cumulative" 
            />
        </Modal>
    </app-layout>
</template>

<script>
import { ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import debounce from 'lodash/debounce'
import AppLayout from '@/Layouts/AppLayout.vue'
import JetInput from '@/Jetstream/Input.vue'
import Pagination from '@/Shared/Pagination.vue'
import Modal from './Modal.vue' // Your modal component
import ViewEditMatrix from './ViewEditMatrix.vue' // Your matrix table view
import HelpManual from '../../Components/HelpManual.vue';

export default {
    components: {
        AppLayout,
        Link,
        JetInput,
        Pagination,
        Modal,
        ViewEditMatrix,
        HelpManual,
    },

    props: {
        cumMatrix: {
            type: Object,
            required: true
        },
        filters: {
            type: Object,
            default: () => ({})
        }
    },

    data() {
        return {
            calculationSourceLabels: {
                manual: 'Manual',
                system: 'System'
            }
        };
    },

    setup(props) {
        const search = ref(props.filters.search || '')
        const startDate = ref(props.filters.start_date || '')
        const endDate = ref(props.filters.end_date || '')
        const showModal = ref(false)
        const selectedTD = ref(null)
        const selectedPeriod = ref('')
        const loading = ref(null)
        const periodsModalVisible = ref(false)
        const currentPeriods = ref([])


        const showPeriods = (periods) => {
        console.log('Periods list data:', periods)

        // Parse JSON string if needed
        let parsedPeriods = periods

        if (typeof periods === 'string') {
            try {
            parsedPeriods = JSON.parse(periods)
            } catch (e) {
            alert('Could not parse periods JSON.')
            return
            }
        }

        // Check for null or not array
        if (!Array.isArray(parsedPeriods)) {
            alert('Periods data is not an array.')
            return
        }

        //Periods parsed is an array
        const monthNames = ["January", "February", "March", "April", "May", "June", 
                   "July", "August", "September", "October", "November", "December"];

        currentPeriods.value = parsedPeriods.map(p => {
            const [startYear, startMonth] = p.start.split('-')
            const [endYear, endMonth] = p.end.split('-')
            return {
                start: `${monthNames[parseInt(startMonth) - 1]} ${startYear}`,
                end: `${monthNames[parseInt(endMonth) - 1]} ${endYear}`
            }
})
        periodsModalVisible.value = true
        }


        const updateSearch = debounce(() => {
            router.get(
                route('transition-matrix-cummulative.index'),
                { 
                    search: search.value,
                    start_date: startDate.value,
                    end_date: endDate.value
                },
                { 
                    preserveState: true,
                    replace: true,
                    preserveScroll: true
                }
            )
        }, 300)

        watch([search, startDate, endDate], () => {
            updateSearch()
        })

        const modalVisible = ref(false)
        const selectedMatrix = ref(null)
        const mode = ref('view') // or 'edit'

        function openModal(type, matrix) {
            selectedMatrix.value = matrix
            mode.value = type
            modalVisible.value = true
        }

        function openLoanBookModal(matrix) {
            selectedTD.value = matrix
            showModal.value = true
        }


        const formatDate = (date) => {
            return new Date(date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            })
        }

        async function submitUpdate() {
            if (!selectedPeriod.value) {
                    alert("Please select a reporting period.");
                    return;
                }

                loading.value = selectedTD.value?.id

                try {
                    await router.post(route('transition-matrix-cummulative.update-loan-book', selectedTD.value.id), {
                        reporting_period: selectedPeriod.value + '-01', // convert month to full date
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
                    alert('Error updating loan book: ' + (error.response?.data?.message || error.message));
                    loading.value = null
                }
            }


        const reRunMatrix = async (matrix) => {
            try {
                const confirmed = confirm('Are you sure you want to re-run this calculation?');
                if (!confirmed) return;

                await axios.post(`/transition-matrix-cummulative/${matrix}/rerun`);

                alert('Matrix re-run completed successfully.');
            } catch (error) {
                alert('Error while re-running matrix: ' + (error.response?.data?.message || error.message));
            }
        };

        const lockPD = (matrixId) => {
            if (confirm('Are you sure you want to change the lock status?')) {
                loading.value = matrixId;
                router.post(
                    route('transition-matrix-cumulative.lock', { matrix: matrixId }), 
                    {},
                    {
                        preserveScroll: true,
                        onFinish: () => { loading.value = null },
                        onSuccess: () => { router.reload({ only: ['matrices'] }) },
                    }
                );
            }
        };
        

                // In your component's methods or mounted()

     return {
            showPeriods,
            periodsModalVisible,
            currentPeriods,
            search,
            startDate,
            endDate,
            modalVisible,
            selectedMatrix,
            mode,
            openModal,
            formatDate,
            reRunMatrix,
            lockPD,
            showModal,
            selectedTD,
            selectedPeriod,
            loading,
            openLoanBookModal,
            submitUpdate
        }
    }
}

</script>
