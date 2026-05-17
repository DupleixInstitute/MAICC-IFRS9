<template>
    <app-layout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Transition Matrix Monthly Probability
                </h2>
                <div class="flex items-center space-x-2">
                    <button @click="showReportModal = true"
                          class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:outline-none focus:ring focus:ring-blue-300 transition">
                        <i class="fas fa-file-archive mr-2"></i>
                        Get Report
                    </button>
                    <Link :href="route('transition-matrices.create')"
                          class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring focus:ring-gray-300 disabled:opacity-25 transition">
                        Create New Matrix
                    </Link>
                </div>
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
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transition Profile Id</th>
                                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">PD Level</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Segmentation </th>
                                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Calculation Source</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payments Included?</th>
                                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Start Period</th>
                                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">End Period</th>
                                        <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Transition Years</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Records Transitioned</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Records Updated</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reporting Periods</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">No of Calc Runs</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Transition Balance</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Updated Balance</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Calc Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Comments</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr v-if="matrices.data.length === 0">
                                        <td colspan="15" class="px-6 py-4 text-center text-gray-500">No transition matrices found.</td>
                                    </tr>
                                    <tr v-for="matrix in matrices.data" :key="matrix.id">
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.transition_profile_id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 py-1 text-center text-xs font-semibold rounded-full"
                                                  :class="{
                                                      'bg-blue-100 text-blue-800': matrix.pd_calculation_level === 'portfolio',
                                                      'bg-green-100 text-green-800': matrix.pd_calculation_level === 'sector'
                                                  }">
                                                {{ matrix.pd_calculation_level ? matrix.pd_calculation_level.toUpperCase() : '-' }}
                                            </span>
                                        </td>       

                                        <!-- Portfolio/Sector Name -->
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div v-if="matrix.pd_calculation_level === 'portfolio'">
                                                <span v-if="matrix.portfolio">
                                                    {{ matrix.portfolio.name }}
                                                </span>
                                                <span v-else class="text-gray-400">
                                                    Portfolio ID: {{ matrix.pd_calculation_id }}
                                                </span>
                                            </div>
                                            <div v-else-if="matrix.pd_calculation_level === 'sector'">
                                                <span v-if="matrix.sector">
                                                    {{ matrix.sector.code }} - {{ matrix.sector.name }}
                                                </span>
                                                <span v-else class="text-gray-400">
                                                    Sector Code: {{ matrix.pd_calculation_code }}
                                                </span>
                                            </div>
                                            <span v-else class="text-gray-400">-</span>
                                        </td>
                                        <td class="px-6 py-4 text-center whitespace-nowrap">{{ calculationSourceLabels[matrix.calculation_source] }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.pd_start_stage_total_type }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.start_reporting_period }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.end_reporting_period }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.transition_years }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.records_count_transitioned }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.records_count_updated }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.reporting_periods_count }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ matrix.run_no }}</td>
                                        <td class="px-6 py-4 text-right whitespace-nowrap">{{ formatCurrency(matrix.transition_balance )}}</td>
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
                                            <div class="flex  flex-wrap gap-2 w-20">
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

                                                 <button 
                                                        v-if="matrix.calculation_source === 'manual'"
                                                        @click="matrix.has_supporting_document ? downloadFile(matrix.id) : downloadFile(matrix.id)"
                                                        :class="[
                                                        matrix.has_supporting_document 
                                                            ? 'text-gray-600 hover:text-gray-800' 
                                                            : 'text-gray-600 hover:text-gray-800'
                                                        ]"
                                                        :title="matrix.has_supporting_document ? 'Download Support Doc' : 'Attach Support Doc First'"
                                                    >
                                                        <i v-if="matrix.has_supporting_document"> 
                                                            <font-awesome-icon :icon="['fas', 'check-circle']" class="w-8 h-8" />
                                                        </i>
                                                        <i v-else>
                                                             <font-awesome-icon :icon="['fas', 'file-download']" class="w-8 h-8" />
                                                        </i>
                                                 </button>

                                                    <button 
                                                        v-if="matrix.calculation_source === 'manual'"
                                                        @click="openUploadModal(matrix.id)" 
                                                        class="text-gray-600 hover:text-gray-800 transition-colors"
                                                        aria-label="Attach File"
                                                        title="Attach File"
                                                    >
                                                        <font-awesome-icon :icon="['fas', 'paperclip']" class="w-8 h-8" />
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

        
        <div
            v-if="showUploadModal"
            class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50"
            >
            <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-lg animate-fadeIn">
                <h2 class="text-xl font-bold mb-3 text-gray-800">
                Attach Supporting Document
                </h2>

                <p class="text-sm text-gray-600 mb-4 leading-relaxed">
                Upload a supporting document for this  calculation.  
                This may include PDF reports, Excel models, or images validating the manual calculation.
                </p>
                <!-- File Upload Box -->
                <label
                class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer hover:border-blue-400 transition"
                >
                <div class="flex flex-col items-center pt-4">
                    <i class="fas fa-cloud-upload-alt text-3xl text-gray-500"></i>
                    <span class="mt-2 text-sm text-gray-600">Click to choose a file</span>
                </div>

                <input
                    type="file"
                    class="hidden"
                    @change="handleModalFileChange"
                    accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.png"
                />
                </label>

                <!-- File Info -->
                <div
                v-if="uploadFile"
                class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-800"
                >
                <strong>Selected File:</strong> {{ uploadFile.name }}  
                <div class="text-xs mt-1 text-blue-600">
                    Size: {{ Math.round(uploadFile.size / 1024) }} KB
                </div>
                </div>

                <!-- Max Size & Accepted Formats Note -->
                <div class="mt-3 text-xs text-gray-500">
                <strong>Allowed Formats:</strong> PDF, DOC, DOCX, XLS, XLSX, JPG, PNG  
                <br />
                <strong>Max Size:</strong> 5 MB
                </div>

                <!-- Buttons -->
                <div class="flex justify-end space-x-3 mt-6">
                <button
                    @click="showUploadModal = false"
                    class="px-4 py-2 bg-gray-300 rounded-lg hover:bg-gray-400 transition"
                >
                    Cancel
                </button>

                <button
                    @click="submitUpload"
                    :disabled="uploadLoading"
                    class="px-5 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition disabled:opacity-50"
                >
                    <span v-if="uploadLoading">Uploading...</span>
                    <span v-else>Upload</span>
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

        <ExportModal :show="showReportModal" @close="showReportModal = false" />
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
import ExportModal from './Components/ExportModal.vue'
import HelpManual from '../../Components/HelpManual.vue';

export default {
    components: {
        AppLayout,
        JetInput,
        Pagination,
        Modal,
        ViewEditMatrix,
        ExportModal,
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

        const showUploadModal = ref(false);
        const uploadTargetId = ref(null);
        const uploadFile = ref(null);
        const uploadLoading = ref(false);
        const showReportModal = ref(false);


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

        
           const openUploadModal = (id) => {
                uploadTargetId.value = id;
                uploadFile.value = null;
                showUploadModal.value = true;
            };

            const handleModalFileChange = (e) => {
                uploadFile.value = e.target.files[0];
            };

        const submitUpload = () => {
            if (!uploadFile.value) {
                alert('Please select a file first.');
                return;
            }

            const formData = new FormData();
            formData.append('file', uploadFile.value);

            uploadLoading.value = true;

            router.post(route('transition-matrices.attach-file', uploadTargetId.value), formData, {
                forceFormData: true,
                preserveScroll: true,

                onSuccess: () => {
                    alert(' File attached successfully');
                    showUploadModal.value = false;
                    router.reload({ only: ['matrices'] });
                },

                onError: (errors) => {
                    console.error(errors);
                    alert(' Upload failed');
                },

                onFinish: () => {
                    uploadLoading.value = false;
                },
            });
        };

            const downloadFile = (id) => {
                window.location.href = `/transition-matrix/${id}/download-file`;
            };

        
        const formatCurrency = (value) => {
            if (!value) return 'E0.00';
            return  new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
        };
                    

        return {
            formatCurrency,
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
            calculationSourceLabels,
            showUploadModal,
            downloadFile,
            submitUpload,
            handleModalFileChange,
            openUploadModal,
            showReportModal,
        }
    },
}
</script>
