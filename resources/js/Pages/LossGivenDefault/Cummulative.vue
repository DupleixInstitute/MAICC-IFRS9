<template>
    <app-layout>
            <template #header>
            <div class="flex justify-between items-center">
                  <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                      Cummulative Loss Given Default
                      <HelpManual />
                  </h2>

                  <div class="flex space-x-2 mt-2">
                      <!-- Calculate -->
                      <Link
                          :href="route('lgd-cummulative.create')"
                          class="inline-flex items-center bg-maiic-600 hover:bg-maiic-700 text-white px-4 py-2 rounded-lg shadow-md transition duration-300"
                      >
                         <i class="fa fa-calculator mr-2" aria-hidden="true"></i>
                          Calculate LGD
                  </Link>

                      <!-- Get Report -->
                      <button
                          @click="openReportModal"
                          class="inline-flex items-center bg-maiic-600 hover:bg-maiic-700 text-white px-4 py-2 rounded-lg shadow-md transition duration-300"
                      >
                          <i class="fas fa-file-archive mr-2"></i>
                          Get Report
                      </button>
                  </div>
              </div>
        </template>

          
    <form @submit.prevent="applyFilters" class="grid grid-cols-6 gap-4">
        <!-- Search -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Calculation Level</label>
              <select
                  v-model="filters.lgd_calculation_level"
                  class="w-full border-gray-300 rounded-md shadow-sm focus:ring-maiic-500 focus:border-maiic-500"
              >
                  <option value="">All</option>
                  <option value="portfolio">Portfolio</option>
                  <option value="sector">Sector</option>
              </select>
        </div>

        <!-- Start Date -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
            <input
                  v-model="startDate"
                  type="month"
                  class="w-full border-gray-300 rounded-md shadow-sm focus:ring-maiic-500 focus:border-maiic-500"
              />
        </div>

        <!-- End Date -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
            <input
                v-model="endDate"
                type="month"
                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-maiic-500 focus:border-maiic-500"
            />
        </div>

        <!-- Buttons -->
        <div class="flex items-end space-x-2">
            <button
                type="submit"
                class="bg-maiic-600 text-white px-4 py-2 rounded hover:bg-maiic-700"
            >
                Apply Filters
            </button>
            <button
                type="button"
                @click="resetFilters"
                class="bg-gray-800 text-gray-100 px-4 py-2 rounded hover:bg-gray-500"
            >
                Reset
            </button>
        </div>
    </form>

  <div class="overflow-y-auto mt-6">
    <div class="bg-white shadow-md rounded-lg">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-200">
        <tr>
            <th scope="col" class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Reporting Period</th>
            <th scope="col" class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">LGD Level</th>
            <th scope="col" class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Segmentation</th>
            <th scope="col" class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">LGD %</th>
            <th scope="col" class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Cure Rate %</th>
            <th scope="col" class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Recovery Rate %</th>
            <th scope="col" class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Calculated</th>
            <th scope="col" class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
            <th scope="col" class="px-3 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Balance-Start</th>
            <th scope="col" class="px-3 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Balance-End</th>
            <th scope="col" class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Created By</th>
            <th scope="col" class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Created On</th>
            <th scope="col" class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Actions</th>
        </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-if="loading">
            <td colspan="15" class="px-6 py-4 text-center text-gray-500">Loading data...</td>
          </tr>
          <tr v-else-if="lgdCummulatives.data.length === 0">
            <td colspan="15" class="px-6 py-4 text-center text-gray-500">No Cummulative Loss-Given-Default found.</td>
          </tr>
           <tr v-for="lgdC in lgdCummulatives.data" :key="lgdC.id">
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{formatDate(lgdC.start_period)}} - {{formatDate(lgdC.reporting_period)}}</td>
            <td class="px-6 py-4 whitespace-nowrap">
                <span class="px-2 py-1 text-xs font-semibold rounded-full"
                        :class="{
                            'bg-maiic-100 text-maiic-800': lgdC.lgd_calculation_level === 'portfolio',
                            'bg-amber-100 text-amber-800': lgdC.lgd_calculation_level === 'sector'
                        }">
                    {{ lgdC.lgd_calculation_level ? lgdC.lgd_calculation_level.toUpperCase() : '-' }}
                </span>
            </td>       

            <!-- Portfolio/Sector Name -->
            <td class="px-6 py-4 whitespace-nowrap">
                <div v-if="lgdC.lgd_calculation_level === 'portfolio'">
                    <span v-if="lgdC.portfolio_group">
                        {{ lgdC.portfolio_group.name }}
                    </span>
                    <span v-else class="text-gray-400">
                        Portfolio ID: {{lgdC.lgd_calculation_id }}
                    </span>
                </div>
                <div v-else-if="lgdC.lgd_calculation_level === 'sector'">
                    <span v-if="lgdC.sector">
                        {{ lgdC.sector.code }} - {{ lgdC.sector.name }}
                    </span>
                    <span v-else class="text-gray-400">
                        Sector Code: {{ lgdC.lgd_calculation_code }}
                    </span>
                </div>
                <span v-else class="text-gray-400">-</span>
            </td>
            
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{round((lgdC.lgd_cummulative * 100),2)}}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{round((lgdC.cure_rate_cummulative * 100),2)}}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{round((lgdC.recovery_rate_cummulative * 100),2)}}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{lgdC.calculation_source}}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                 <span
                    class="px-2 py-1 rounded-full text-xs font-semibold"
                    :class="{
                      'bg-red-100 text-red-700': lgdC.is_active_or_closed === 'closed',
                      'bg-maiic-100 text-maiic-700': lgdC.is_active_or_closed === 'active'
                    }"
                  >
                    {{ lgdC.is_active_or_closed === 'closed' ? 'Closed' : 'Active' }}
                  </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-gray-600">{{formatCurrency(lgdC.start_total_stage3)}}</td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-gray-600">{{formatCurrency(lgdC.end_total_stage3)}}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{lgdC.created_by}}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{formatDate(lgdC.created_at)}}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600 flex space-x-2">
            <!-- <button
            v-if="lgd.calculation_source === 'manual'"
            @click="editLGD(lgd.id)"
            class="text-maiic-600 hover:text-maiic-800 transition-colors"
            aria-label="Edit LGD"
          >
            <i class="fas fa-pencil"></i>
          </button> -->
            <button  
            v-if="lgdC.calculation_source === 'system'"
            @click="showPeriods(lgdC.periods_list)"  class="text-brown-600 hover:text-brown-800" title="Show Periods">
            <i class="fas fa-eye"></i>
            </button>

              <button 
                v-if="lgdC.calculation_source === 'manual'"
                @click="openUploadModal(lgdC.id)" 
                class="text-gray-700 hover:text-amber-900 transition-colors"
                aria-label="Attach File"
                title="Attach File"
              >
                <i class="fas fa-paperclip"></i>
              </button>

             <button 
                v-if="lgdC.calculation_source === 'manual'"
                @click="lgdC.has_supporting_document ? downloadFile(lgdC.id) : downloadFile(lgdC.id)"
                :class="[
                  lgdC.has_supporting_document 
                    ? 'text-maiic-600 hover:text-maiic-800' 
                    : 'text-amber-600 hover:text-amber-800'
                ]"
                :title="lgdC.has_supporting_document ? 'Download Support Doc' : 'Attach Support Doc First'"
              >
                <i v-if="lgdC.has_supporting_document" class="fas fa-check-circle"></i>
                <i v-else class="fas fa-file-download"></i>
              </button>

          <!-- Locked State (Closed) -->
          <button
            v-if="lgdC.is_active_or_closed === 'closed'"
            @click="lockLGD(lgdC.id)"
            :disabled="loading === lgdC.id"
            class="text-red-600 hover:text-red-800"
            title="Unlock LGD"
          >
            <i v-if="loading !== lgdC.id" class="fas fa-lock"></i>
            <svg v-else class="animate-spin h-5 w-5 text-red-600" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
            </svg>
          </button>

          <!-- Active State -->
          <button
            v-else
            @click="lockLGD(lgdC.id)"
            :disabled="loading === lgdC.id"
            class="text-maiic-600 hover:text-maiic-800"
            title="Lock LGD"
          >
            <i v-if="loading !== lgdC.id" class="fas fa-lock-open"></i>
            <svg v-else class="animate-spin h-5 w-5 text-maiic-600" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
            </svg>
          </button>

            <button v-if="lgdC.is_active_or_closed === 'closed'"
            @click="openUpdateModal(lgdC)" 
            :disabled="loading === lgdC.id"  
                class="text-maiic-600 hover:text-maiic-800" 
                title="Update Loan Book"
            >
                <i v-if="loading !==lgdC.id" class="fas fa-book"></i>
            </button>

              <button v-if="lgdC.is_active_or_closed === 'active'"
               @click="deleteLGD(lgdC.id)" 
               :disabled="loading === lgdC.id"
               title="Delete LGD"
               class="text-red-600 hover:text-red-800">
                <i class="fas fa-trash"></i>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Pagination -->
  <Pagination :links="lgdCummulatives.links" />

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

    </app-layout>
</template>

<script>
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

        const lockLGD = (id) => {
            if (confirm('Are you sure you want to change the lock status of this LGD?')) {
                loading.value = id;
                router.post(`/loss-given-default/cummulative/${id}/lock`, {}, {
                    preserveScroll: true,
                    onFinish: () => { loading.value = null; },
                    onSuccess: () => { router.reload({ only: ['lgdCummulatives'] }); },
                    onError: () => { alert('Something went wrong. Please try again.'); },
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
                alert('Please select a file first.');
                return;
            }

            const formData = new FormData();
            formData.append('file', uploadFile.value);

            uploadLoading.value = true;

            router.post(route('lgd-cummulative.attach-file', uploadTargetId.value), formData, {
                forceFormData: true,
                preserveScroll: true,

                onSuccess: () => {
                    alert(' File attached successfully');
                    showUploadModal.value = false;
                    router.reload({ only: ['lossGivenDefaults'] });
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
                window.location.href = `/loss-given-default/cummulative/${id}/download-file`;
            };


        const openUpdateModal = (lgd_cummulatives) => {
            selectedLGD.value = lgd_cummulatives;
            selectedPeriod.value = '';
            showModal.value = true;
        };

        const submitUpdate = () => {
            if (!selectedPeriod.value) {
                alert('Please select a period.');
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
                    alert('Something went wrong. Please try again.');
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
            alert('Could not parse periods JSON.')
            return
            }
        }

        // Check for null or not array
        if (!Array.isArray(parsedPeriods)) {
            alert('Periods data is not an array.')
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

        const deleteLGD = (id) => {
            if (confirm('Are you sure?')) {
                router.delete(`/loss-given-default/cummulative/${id}/delete`, {
                    preserveScroll: true,
                    onSuccess: () => {
                        router.reload({ only: ['lgdCummulatives'] });
                    },
                    onError: () => {
                        alert('Something went wrong. Please try again.');
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
            alert('Please select both start and end periods.');
            return;
        }

        // Optional: check that start <= end
        if (reportStartPeriod.value > reportEndPeriod.value) {
            alert('Start period cannot be after end period.');
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