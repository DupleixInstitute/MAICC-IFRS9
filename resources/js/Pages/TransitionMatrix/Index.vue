<template>
    <app-layout>
        <template #header>
            <div>
                <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
                    <span>IFRS 9 Model Setup</span><span>/</span><span>PD Model</span><span>/</span><span class="font-medium text-maiic-700">Monthly Probability</span>
                </div>
                <h2 class="text-xl font-semibold text-gray-800">Monthly Probability</h2>
                <p class="mt-1 text-sm text-gray-600">Monthly transition matrices: how loans move between stages, and the probability of default they give</p>
            </div>
        </template>
        <template #actions>
            <button type="button" class="secondary-btn" @click="showReportModal = true">Get report</button>
            <Link :href="route('transition-matrices.create')" class="primary-btn">Create matrix</Link>
        </template>

        <div class="w-full space-y-4">
            <div class="maiic-panel">
                <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h3 class="font-semibold text-gray-900">Transition matrices</h3>
                        <p class="text-xs text-gray-500">{{ matrices.total ?? matrices.data.length }} matrix run(s), newest first. A closed matrix is locked for use in the ECL.</p>
                    </div>
                    <div class="flex flex-wrap items-end gap-2">
                        <label class="block"><span class="maiic-flabel">Search</span><input v-model="search" type="text" class="maiic-input !w-56" placeholder="Segment, period or comment" /></label>
                        <label class="block"><span class="maiic-flabel">From</span><input v-model="startDate" type="date" class="maiic-input !w-40" /></label>
                        <label class="block"><span class="maiic-flabel">To</span><input v-model="endDate" type="date" class="maiic-input !w-40" /></label>
                    </div>
                </div>

                <div class="maiic-table-wrap overflow-x-auto">
                    <table class="maiic-table">
                        <thead>
                            <tr>
                                <th>Matrix</th>
                                <th>Segment</th>
                                <th>Source</th>
                                <th>Periods</th>
                                <th class="num">Years</th>
                                <th class="num">Records</th>
                                <th class="num">Transition balance</th>
                                <th>Status</th>
                                <th>Last calculated</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="matrix in matrices.data" :key="matrix.id">
                                <td class="whitespace-nowrap">
                                    <div class="font-semibold text-gray-900">#{{ matrix.id }}</div>
                                    <div class="text-xs text-gray-500">Profile {{ matrix.transition_profile_id }}, run {{ matrix.run_no ?? '-' }}</div>
                                </td>
                                <td>
                                    <span class="maiic-badge maiic-badge-grey">{{ matrix.pd_calculation_level ? matrix.pd_calculation_level.charAt(0).toUpperCase() + matrix.pd_calculation_level.slice(1) : '-' }}</span>
                                    <div class="mt-0.5 text-sm">
                                        <template v-if="matrix.pd_calculation_level === 'portfolio'">{{ matrix.portfolio ? matrix.portfolio.name : 'Portfolio ' + matrix.pd_calculation_id }}</template>
                                        <template v-else-if="matrix.pd_calculation_level === 'sector'">{{ matrix.sector ? matrix.sector.code + ' - ' + matrix.sector.name : 'Sector ' + matrix.pd_calculation_code }}</template>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">{{ calculationSourceLabels[matrix.calculation_source] || matrix.calculation_source }}<div v-if="matrix.pd_start_stage_total_type" class="text-xs text-gray-500">Payments: {{ matrix.pd_start_stage_total_type }}</div></td>
                                <td class="whitespace-nowrap">{{ matrix.start_reporting_period }} to {{ matrix.end_reporting_period }}<div class="text-xs text-gray-500">{{ matrix.reporting_periods_count ?? '-' }} reporting period(s)</div></td>
                                <td class="num">{{ matrix.transition_years }}</td>
                                <td class="num whitespace-nowrap">{{ matrix.records_count_transitioned ?? '-' }}<div class="text-xs text-gray-500">{{ matrix.records_count_updated ?? 0 }} updated</div></td>
                                <td class="num whitespace-nowrap">{{ formatCurrency(matrix.transition_balance) }}<div v-if="matrix.updated_balance" class="text-xs text-gray-500">{{ formatCurrency(matrix.updated_balance) }} updated</div></td>
                                <td>
                                    <span class="maiic-badge" :class="matrix.status === 'closed' ? 'maiic-badge-green' : 'maiic-badge-gold'" :title="matrix.comments || null">{{ matrix.status === 'closed' ? 'Closed' : 'Draft' }}</span>
                                </td>
                                <td class="whitespace-nowrap">{{ formatDate(matrix.last_calculation_date) }}</td>
                                <td>
                                    <div class="flex flex-nowrap justify-end gap-1.5">
                                        <button type="button" class="maiic-action maiic-action-view" title="View the matrix" @click="openModal('view', matrix)"><font-awesome-icon icon="table" /></button>
                                        <button v-if="matrix.status === 'draft'" type="button" class="maiic-action maiic-action-edit" title="Edit the matrix" @click="openModal('edit', matrix)"><font-awesome-icon icon="pen" /></button>
                                        <button v-if="matrix.status === 'draft'" type="button" class="maiic-action maiic-action-neutral" title="Re-run the calculation" @click="reRunMatrix(matrix.id)"><font-awesome-icon icon="calculator" /></button>
                                        <button v-if="matrix.calculation_source === 'manual'" type="button" class="maiic-action maiic-action-neutral" :title="matrix.has_supporting_document ? 'Download the supporting document' : 'No supporting document attached yet'" @click="downloadFile(matrix.id)"><font-awesome-icon :icon="matrix.has_supporting_document ? 'check-circle' : 'file-download'" /></button>
                                        <button v-if="matrix.calculation_source === 'manual'" type="button" class="maiic-action maiic-action-neutral" title="Attach a supporting document" @click="openUploadModal(matrix.id)"><font-awesome-icon icon="paperclip" /></button>
                                        <button type="button" class="maiic-action" :class="matrix.status === 'closed' ? 'maiic-action-edit' : 'maiic-action-view'" :title="matrix.status === 'closed' ? 'Unlock this PD' : 'Lock this PD'" @click="lockPD(matrix.id)"><font-awesome-icon :icon="matrix.status === 'closed' ? 'lock-open' : 'lock'" /></button>
                                        <button v-if="matrix.status === 'closed'" type="button" class="maiic-action maiic-action-neutral" title="Update the loan book" @click="openLoanBookModal(matrix)"><font-awesome-icon icon="book" /></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="matrices.data.length === 0">
                                <td colspan="10" class="maiic-empty">No transition matrices yet. Use <strong>Create matrix</strong> at the top right to build the first one.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="matrices.links && matrices.links.length > 3" class="border-t border-gray-100 px-4 pb-4">
                    <pagination :links="matrices.links" />
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
                        class="px-4 py-2 bg-maiic-600 text-white rounded hover:bg-maiic-700"
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
                class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer hover:border-maiic-400 transition"
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
                class="mt-4 p-3 bg-maiic-50 border border-maiic-200 rounded-lg text-sm text-maiic-800"
                >
                <strong>Selected File:</strong> {{ uploadFile.name }}
                <div class="text-xs mt-1 text-maiic-600">
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
                    class="px-5 py-2 bg-maiic-600 text-white rounded-lg hover:bg-maiic-700 transition disabled:opacity-50"
                >
                    <span v-if="uploadLoading">Uploading...</span>
                    <span v-else>Upload</span>
                </button>
                </div>
            </div>
            </div>

        <HelpManual />

        
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
import { router, Link } from '@inertiajs/vue3'
import { confirmDialog } from '@/Components/confirmDialog'
import { notice } from '@/Components/Maiic/notice'
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
        Link,
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
                await notice('Choose a reporting period', 'Pick the month whose loan book should be updated.', 'warning')
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
                notice('The loan book was not updated', error.response?.data?.message || error.message, 'danger')
                loading.value = null
            }
        }

        async function reRunMatrix(matrixId) {
            try {
                if (!(await confirmDialog({ title: 'Re-run this calculation?', message: 'The draft matrix is recalculated from the loan books.', confirmLabel: 'Re-run' }))) return

                await axios.post(`/transition-matrix/${matrixId}/rerun`)

                router.reload({ only: ['matrices'] })
                notice('Matrix re-run', 'The calculation finished.')
            } catch (error) {
                notice('The re-run failed', error.response?.data?.message || error.message, 'danger')
            }
        }

        async function lockPD(id) {
            if (await confirmDialog({ title: 'Change the lock on this PD?', message: 'A closed (locked) matrix is the one the ECL uses; a draft can still be edited.', confirmLabel: 'Change lock' })) {
                loading.value = id
                router.post(route('transition-matrices.lock', id), {}, {
                    preserveScroll: true,
                    onFinish: () => { loading.value = null },
                    onSuccess: () => { router.reload({ only: ['matrices'] }) },
                    onError: () => { notice('The lock was not changed', 'Please try again.', 'danger') },
                })
            }
        }

        const formatDate = (date) => {
            if (!date) return '-'
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
                notice('Choose a file first', 'Pick the supporting document to attach.', 'warning');
                return;
            }

            const formData = new FormData();
            formData.append('file', uploadFile.value);

            uploadLoading.value = true;

            router.post(route('transition-matrices.attach-file', uploadTargetId.value), formData, {
                forceFormData: true,
                preserveScroll: true,

                onSuccess: () => {

                    showUploadModal.value = false;
                    router.reload({ only: ['matrices'] });
                },

                onError: (errors) => {
                    console.error(errors);
                    notice('Upload failed', Object.values(errors || {})[0] || 'The file could not be attached.', 'danger');
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
            if (!value) return '0.00';
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
