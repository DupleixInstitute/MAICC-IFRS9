<template>
    <app-layout>
        <template #header>
            <div>
                <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
                    <span>IFRS 9 Model Setup</span><span>/</span><span>LGD Model</span><span>/</span><span class="font-medium text-maiic-700">Cumulative LGD</span>
                </div>
                <h2 class="text-xl font-semibold text-gray-800">Cumulative LGD</h2>
                <p class="mt-1 text-sm text-gray-600">Monthly LGD runs combined over many windows, by portfolio or sector</p>
            </div>
        </template>
        <template #actions>
            <button type="button" class="secondary-btn" @click="openReportModal">Get report</button>
            <Link :href="route('lgd-cummulative.create')" class="primary-btn">Calculate LGD</Link>
        </template>

    <div class="w-full space-y-4">
      <div class="maiic-panel">
        <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <h3 class="font-semibold text-gray-900">Cumulative LGD runs</h3>
            <p class="text-xs text-gray-500">{{ lgdCummulatives.total ?? lgdCummulatives.data.length }} run(s). A closed run is locked for use in the ECL; amounts in MWK.</p>
          </div>
          <form class="flex flex-wrap items-end gap-2" @submit.prevent="applyFilters">
            <label class="block"><span class="maiic-flabel">Level</span>
              <select v-model="filters.lgd_calculation_level" class="maiic-select !w-36">
                <option value="">All</option>
                <option value="portfolio">Portfolio</option>
                <option value="sector">Sector</option>
              </select>
            </label>
            <label class="block"><span class="maiic-flabel">From</span><input v-model="startDate" type="month" class="maiic-input !w-40" /></label>
            <label class="block"><span class="maiic-flabel">To</span><input v-model="endDate" type="month" class="maiic-input !w-40" /></label>
            <button type="submit" class="secondary-btn">Apply</button>
            <button type="button" class="secondary-btn" @click="resetFilters">Reset</button>
          </form>
        </div>
        <div class="maiic-table-wrap overflow-x-auto">
          <table class="maiic-table">
            <thead>
              <tr>
                <th>Window</th>
                <th>Segment</th>
                <th class="num">LGD %</th>
                <th class="num">Cure rate %</th>
                <th class="num">Recovery rate %</th>
                <th class="num">Stage 3 balance, start / end</th>
                <th>Source</th>
                <th>Status</th>
                <th class="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="lgdC in lgdCummulatives.data" :key="lgdC.id">
                <td class="whitespace-nowrap" :title="'Created ' + formatDate(lgdC.created_at) + (lgdC.created_by ? ' by ' + lgdC.created_by : '')">{{ formatDate(lgdC.start_period) }} to {{ formatDate(lgdC.reporting_period) }}</td>
                <td>
                  <span class="maiic-badge maiic-badge-grey">{{ lgdC.lgd_calculation_level ? lgdC.lgd_calculation_level.charAt(0).toUpperCase() + lgdC.lgd_calculation_level.slice(1) : '-' }}</span>
                  <div class="mt-0.5 text-sm">
                    <template v-if="lgdC.lgd_calculation_level === 'portfolio'">{{ lgdC.portfolio_group ? lgdC.portfolio_group.name : 'Portfolio ' + lgdC.lgd_calculation_id }}</template>
                    <template v-else-if="lgdC.lgd_calculation_level === 'sector'">{{ lgdC.sector ? lgdC.sector.code + ' - ' + lgdC.sector.name : 'Sector ' + lgdC.lgd_calculation_code }}</template>
                  </div>
                </td>
                <td class="num font-bold">{{ round(lgdC.lgd_cummulative * 100, 2) }}</td>
                <td class="num">{{ round(lgdC.cure_rate_cummulative * 100, 2) }}</td>
                <td class="num">{{ round(lgdC.recovery_rate_cummulative * 100, 2) }}</td>
                <td class="num whitespace-nowrap">{{ formatCurrency(lgdC.start_total_stage3) }}<div class="text-xs text-gray-500">{{ formatCurrency(lgdC.end_total_stage3) }}</div></td>
                <td class="whitespace-nowrap">{{ lgdC.calculation_source === 'manual' ? 'Manual' : 'System' }}</td>
                <td><span class="maiic-badge" :class="lgdC.is_active_or_closed === 'closed' ? 'maiic-badge-green' : 'maiic-badge-gold'">{{ lgdC.is_active_or_closed === 'closed' ? 'Closed' : 'Active' }}</span></td>
                <td>
                  <div class="flex flex-nowrap justify-end gap-1.5">
                    <button v-if="lgdC.calculation_source === 'system'" type="button" class="maiic-action maiic-action-view" title="Show the periods combined" @click="showPeriods(lgdC.periods_list)"><font-awesome-icon icon="calendar" /></button>
                    <button v-if="lgdC.calculation_source === 'manual'" type="button" class="maiic-action maiic-action-neutral" title="Attach a supporting document" @click="openUploadModal(lgdC.id)"><font-awesome-icon icon="paperclip" /></button>
                    <button v-if="lgdC.calculation_source === 'manual'" type="button" class="maiic-action maiic-action-neutral" :title="lgdC.has_supporting_document ? 'Download the supporting document' : 'No supporting document attached yet'" @click="downloadFile(lgdC.id)"><font-awesome-icon :icon="lgdC.has_supporting_document ? 'check-circle' : 'file-download'" /></button>
                    <button type="button" class="maiic-action" :class="lgdC.is_active_or_closed === 'closed' ? 'maiic-action-edit' : 'maiic-action-view'" :disabled="loading === lgdC.id" :title="lgdC.is_active_or_closed === 'closed' ? 'Unlock this LGD' : 'Lock this LGD'" @click="lockLGD(lgdC.id)"><font-awesome-icon :icon="loading === lgdC.id ? 'spinner' : (lgdC.is_active_or_closed === 'closed' ? 'lock-open' : 'lock')" :spin="loading === lgdC.id" /></button>
                    <button v-if="lgdC.is_active_or_closed === 'closed'" type="button" class="maiic-action maiic-action-neutral" :disabled="loading === lgdC.id" title="Update the loan book" @click="openUpdateModal(lgdC)"><font-awesome-icon icon="book" /></button>
                    <button v-if="lgdC.is_active_or_closed === 'active'" type="button" class="maiic-action maiic-action-delete" :disabled="loading === lgdC.id" title="Delete this LGD run" @click="deleteLGD(lgdC.id)"><font-awesome-icon icon="trash" /></button>
                  </div>
                </td>
              </tr>
              <tr v-if="!lgdCummulatives.data.length">
                <td colspan="9" class="maiic-empty">No cumulative LGD runs yet. Use <strong>Calculate LGD</strong> at the top right to build one from the monthly runs.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-if="lgdCummulatives.links && lgdCummulatives.links.length > 3" class="border-t border-gray-100 px-4 pb-4">
          <Pagination :links="lgdCummulatives.links" />
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
                <p class="mb-4">
                Select the reporting period to update loan books for
                </p>

                <!-- Reporting Period -->
                <label for="period" class="block mb-2 text-sm font-medium text-gray-700">Reporting Period</label>
                <input
                type="month"
                v-model="selectedPeriod"
                id="period"
                class="border-gray-300 rounded-md shadow-sm w-full mb-4"
                >


                <!--  Customer LGD Toggle -->
                <div class="flex items-center mb-6">
                <input
                    id="include_customer_lgd"
                    type="checkbox"
                    v-model="includeCustomerLGD"
                    class="h-4 w-4 text-maiic-600 border-gray-300 rounded focus:ring-maiic-500"
                >
                <label for="include_customer_lgd" class="ml-2 text-sm text-gray-700">
                    Include Customer LGD in Update
                </label>
                </div>
                <div class="flex justify-end space-x-2">
                <button
                    @click="showModal = false"
                    class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400"
                >
                    Cancel
                </button>

                <button
                    @click="submitUpdate"
                    class="px-4 py-2 bg-maiic-600 text-white rounded hover:bg-maiic-700"
                    :disabled="loading === selectedLGD?.id"
                >
                    <span v-if="loading === selectedLGD?.id">Updating...</span>
                    <span v-else>Update</span>
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
      Upload a supporting document for this manual calculation.
      This may include PDF reports, Excel models, or images validating the manual calcultion.
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


<div
    v-if="showReportModal"
    class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50"
>
    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
        <h2 class="text-lg font-bold mb-4">Download LGD Monthly Report</h2>

        <label class="block mb-2 text-sm font-medium text-gray-700">Start Period</label>
        <input
            type="month"
            v-model="reportStartPeriod"
            class="border-gray-300 rounded-md shadow-sm w-full mb-4"
        />

        <label class="block mb-2 text-sm font-medium text-gray-700">End Period</label>
        <input
            type="month"
            v-model="reportEndPeriod"
            class="border-gray-300 rounded-md shadow-sm w-full mb-4"
        />

        <label class="block mb-2 text-sm font-medium text-gray-700">Calculation Level (Optional)</label>
        <select
            v-model="reportCalculationLevel"
            class="border-gray-300 rounded-md shadow-sm w-full mb-4"
        >
            <option value="">All Levels</option>
            <option value="portfolio">Portfolio</option>
            <option value="sector">Sector</option>
            <option value="customer">Customer</option>
        </select>

        <div class="flex justify-end space-x-2">
            <button
                @click="showReportModal = false"
                class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400"
            >
                Cancel
            </button>

            <button
                @click="downloadReport"
                :disabled="reportLoading"
                class="px-4 py-2 bg-maiic-600 text-white rounded hover:bg-maiic-700"
            >
                <span v-if="reportLoading">Preparing…</span>
                <span v-else>Download</span>
            </button>
        </div>
    </div>
</div>

    <HelpManual />
    </app-layout>
</template>

<script>
import { confirmDialog } from '@/Components/confirmDialog'
import { notice } from '@/Components/Maiic/notice'
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Shared/Pagination.vue'
import '@fortawesome/fontawesome-free/css/all.css';
import HelpManual from '../../Components/HelpManual.vue';

export default {
    components: {
        AppLayout,
        HelpManual,
        Pagination,
    },
    props: {
        lgdCummulatives: {
            type: Object,
            required: true,
        },
        filters: {
            type: Object,
            required: true,
        },
    },
    setup(props) {
        const loading = ref(null);
        const showModal = ref(false);
        const includeCustomerLGD = ref(false)
        const selectedLGD = ref(null);
        const selectedPeriod = ref('');
        const periodsModalVisible = ref(false)
        const currentPeriods = ref([])
        const updateScope = ref([])
        const filters = ref({
            lgd_calculation_level: props.filters.lgd_calculation_level || '',
            start_date: props.filters.start_date || '',
            end_date: props.filters.end_date || '',
        });
        const showUploadModal = ref(false);
        const uploadTargetId = ref(null);
        const uploadFile = ref(null);
        const uploadLoading = ref(false);
        const showReportModal = ref(false);
        const reportPeriod = ref('');
        const reportLoading = ref(false);
        const reportStartPeriod = ref('');
        const reportEndPeriod = ref('');
        const reportCalculationLevel = ref('');

        const applyFilters = () => {
              router.get(route('lgd-cummulative.index'), {
                  lgd_calculation_level: filters.value.lgd_calculation_level,
                  start_date: filters.value.start_date,
                  end_date: filters.value.end_date
              }, { preserveState: true, replace: true });
          };

        const resetFilters = () => {
                filters.value.lgd_calculation_level = '';
                filters.value.start_date = '';
                filters.value.end_date = '';
                applyFilters();
            };

        const round = (value, decimals = 2) => {
            if (value === null || value === undefined) return '-';
            return Number(Math.round(parseFloat(value + 'e' + decimals)) + 'e-' + decimals).toFixed(decimals);
        };

        const formatDate = (dateStr) => {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: '2-digit',
            });
        };

        const lockLGD = async (id) => {
            if (await confirmDialog({ title: 'Change the lock on this LGD?', message: 'A closed (locked) LGD is the one the ECL uses; an active run can still change.', confirmLabel: 'Change lock' })) {
                loading.value = id;
                router.post(`/loss-given-default/cummulative/${id}/lock`, {}, {
                    preserveScroll: true,
                    onFinish: () => { loading.value = null; },
                    onSuccess: () => { router.reload({ only: ['lgdCummulatives'] }); },
                    onError: () => { notice('Something went wrong. Please try again.'); },
                });
            }
        };

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
                notice('Please select a file first.');
                return;
            }

            const formData = new FormData();
            formData.append('file', uploadFile.value);

            uploadLoading.value = true;

            router.post(route('lgd-cummulative.attach-file', uploadTargetId.value), formData, {
                forceFormData: true,
                preserveScroll: true,

                onSuccess: () => {
                    showUploadModal.value = false;
                    router.reload({ only: ['lossGivenDefaults'] });
                },

                onError: (errors) => {
                    console.error(errors);
                    notice('Upload failed', 'The file could not be attached.', 'danger');
                },

                onFinish: () => {
                    uploadLoading.value = false;
                },
            });
        };

            const downloadFile = (id) => {
                window.location.href = `/loss-given-default/cummulative/${id}/download-file`;
            };


        const openUpdateModal = (lgd_cummulatives) => {
            selectedLGD.value = lgd_cummulatives;
            selectedPeriod.value = '';
            showModal.value = true;
        };

        const submitUpdate = () => {
            if (!selectedPeriod.value) {
                notice('Please select a period.');
                return;
            }

            loading.value = selectedLGD.value.id;

            router.post(route('lgd-cummulative.update-loanbook', selectedLGD.value.id), {
                reporting_period: selectedPeriod.value,
                lgd_id: selectedLGD.value.id,
                include_customer_lgd: includeCustomerLGD.value,
            }, {
                preserveScroll: true,
                onFinish: () => {
                    loading.value = null;
                    showModal.value = false;
                },
                onSuccess: () => {
                    router.reload({ only: ['lgdCummulatives'] });
                },
                onError: () => {
                    notice('Something went wrong. Please try again.');
                },
            });
        };



        const showPeriods = (periods) => {
        console.log('Periods list data:', JSON.parse(JSON.stringify(periods)));

        // Parse JSON string if needed
        let parsedPeriods = periods

        if (typeof periods === 'string') {
            try {
            parsedPeriods = JSON.parse(periods)
            } catch (e) {
            notice('Could not parse periods JSON.')
            return
            }
        }

        // Check for null or not array
        if (!Array.isArray(parsedPeriods)) {
            notice('Periods data is not an array.')
            return
        }

        // parsedPeriods is an array
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



        // const editLGD = (id) => {
        //     router.get(`/loss-given-default/${id}/edit`);
        // };

        const deleteLGD = async (id) => {
            if (await confirmDialog({ title: 'Delete this LGD run?', message: 'The run and its figures are removed. This cannot be undone.', confirmLabel: 'Delete', tone: 'danger' })) {
                router.delete(`/loss-given-default/cummulative/${id}/delete`, {
                    preserveScroll: true,
                    onSuccess: () => {
                        router.reload({ only: ['lgdCummulatives'] });
                    },
                    onError: () => {
                        notice('Something went wrong. Please try again.');
                    },
                });
            }
        };

        const formatCurrency = (value) => {
              if (!value) return '0.00';
              return  new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
          };

           const openReportModal = () => {
            reportPeriod.value = '';
            reportCalculationLevel.value = '';
            showReportModal.value = true;
        };

      const downloadReport = () => {
        if (!reportStartPeriod.value || !reportEndPeriod.value) {
            notice('Please select both start and end periods.');
            return;
        }

        // Optional: check that start <= end
        if (reportStartPeriod.value > reportEndPeriod.value) {
            notice('Start period cannot be after end period.');
            return;
        }

        reportLoading.value = true;

        // Redirect to backend route with all parameters
        const params = new URLSearchParams({
            start_period: reportStartPeriod.value,
            end_period: reportEndPeriod.value,
        });

        // Add calculation level if selected
        if (reportCalculationLevel.value) {
            params.append('lgd_calculation_level', reportCalculationLevel.value);
        }

        window.location.href = route('loss-given-default.report-by-period') + '?' + params.toString();

        setTimeout(() => {
            reportLoading.value = false;
            showReportModal.value = false;
        }, 1500);
    };


        return {
            periodsModalVisible,
            currentPeriods,
            formatDate,
            loading,
            lockLGD,
            showPeriods,
            openUpdateModal,
            submitUpdate,
           // editLGD,
            round,
            deleteLGD,
            showModal,
            selectedLGD,
            selectedPeriod,
            HelpManual,
            includeCustomerLGD,
            updateScope,
            filters,
            applyFilters,
            resetFilters,

            //function for file attachment
            openUploadModal,
            handleModalFileChange,
            submitUpload,
            uploadTargetId,
            uploadFile,
            uploadLoading,
            showUploadModal,
            downloadFile,
            openReportModal,
            reportPeriod,
            reportLoading,
            reportStartPeriod,
            reportEndPeriod,
            reportCalculationLevel,
            downloadReport,
            showReportModal,
            formatCurrency,
        };
    }
};
</script>