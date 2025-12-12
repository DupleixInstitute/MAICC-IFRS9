<template>
    <app-layout>
            <template #header>
                 <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Loss Given Default Periods
                 <HelpManual />
            </h2>
            
                    <Link :href="route('loss-given-default.create')" 
                      class="inline-flex items-center bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg shadow-md transition duration-300 mt-2">
                    Calculate LGD
                    <Icon name="calculator" class="w-4 h-4 mr-2" />
                </Link>
            </div>
        </template>

        
    <form @submit.prevent="applyFilters" class="grid grid-cols-6 gap-4">
        <!-- Search -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Calculation Level</label>
              <select
                  v-model="filters.lgd_calculation_level"
                  class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
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
                  class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
              />
        </div>

        <!-- End Date -->
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
            <input
                v-model="endDate"
                type="month"
                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
            />
        </div>

        <!-- Buttons -->
        <div class="flex items-end space-x-2">
            <button
                type="submit"
                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
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

    <div class="overflow-x-auto mt-6">
    <div class="bg-white shadow-md rounded-lg">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-200">
          <tr>
            <th scope="col" class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Reporting Period</th>
            <th scope="col" class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">LGD Level</th>
            <th scope="col" class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Segmentation</th>
            <th scope="col" class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">LGD %</th>
            <th scope="col" class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Cure Rate %</th>
            <th scope="col" class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Recovery Rate %</th>
            <th scope="col" class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Calculated</th>
            <th scope="col" class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
            <th scope="col" class="px-3 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Balance(Start)</th>
            <th scope="col" class="px-3 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Balance(End)</th>
            <th scope="col" class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Created By</th>
            <th scope="col" class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Created On</th>
            <th scope="col" class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-if="loading">
            <td colspan="15" class="px-6 py-4 text-center text-gray-500">Loading data...</td>
          </tr>
          <tr v-else-if="lossGivenDefaults.data.length === 0">
            <td colspan="15" class="px-6 py-4 text-center text-gray-500">No Monthly Loss-Given-Default</td>
          </tr>
           <tr v-for="lgd in lossGivenDefaults.data" :key="lgd.id">
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{formatDate(lgd.start_period)}} - {{formatDate(lgd.reporting_period)}}</td>
             <td class="px-6 py-4 whitespace-nowrap">
              <span class="px-2 py-1 text-xs font-semibold rounded-full"
                    :class="{
                        'bg-blue-100 text-blue-800': lgd.lgd_calculation_level === 'portfolio',
                        'bg-yellow-100 text-yellow-800': lgd.lgd_calculation_level === 'sector'
                    }">
                  {{ lgd.lgd_calculation_level ? lgd.lgd_calculation_level.toUpperCase() : '-' }}
              </span>
          </td>       

          <!-- Portfolio/Sector Name -->
          <td class="px-6 py-4 whitespace-nowrap">
              <div v-if="lgd.lgd_calculation_level === 'portfolio'">
                  <span v-if="lgd.portfolio_group">
                      {{ lgd.portfolio_group.name }}
                  </span>
                  <span v-else class="text-gray-400">
                      Portfolio ID: {{lgd.lgd_calculation_id }}
                  </span>
              </div>
              <div v-else-if="lgd.lgd_calculation_level === 'sector'">
                  <span v-if="lgd.sector">
                      {{ lgd.sector.code }} - {{ lgd.sector.name }}
                  </span>
                  <span v-else class="text-gray-400">
                      Sector Code: {{ lgd.lgd_calculation_code }}
                  </span>
              </div>
              <span v-else class="text-gray-400">-</span>
          </td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{round((lgd.loss_given_default_percentage * 100),2)}}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{round((lgd.cure_rate * 100),2)}}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{round((lgd.recovery_rate * 100 ),2)}}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{lgd.calculation_source}}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                  <span
                    class="px-2 py-1 rounded-full text-xs font-semibold"
                    :class="{
                      'bg-red-100 text-red-700': lgd.is_active_or_closed === 'closed',
                      'bg-green-100 text-green-700': lgd.is_active_or_closed === 'active'
                    }"
                  >
                    {{ lgd.is_active_or_closed === 'closed' ? 'Closed' : 'Active' }}
                  </span>
                </td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-gray-600">{{lgd.start_total_stage3}}</td>
            <td class="px-6 py-4 whitespace-nowrap text-right text-gray-600">{{lgd.end_total_stage3}}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{lgd.created_by}}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{formatDate(lgd.created_at)}}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600 flex space-x-2"  >
            <div class="flex flex-wrap gap-2 w-20">
            <button 
            v-if="lgd.calculation_source === 'manual'"
            @click="editLGD(lgd.id)" 
            class="text-blue-600 hover:text-blue-800 transition-colors"
            aria-label="Edit LGD"
            title="Edit LGD"
            >
              <i class="fas fa-pencil"></i>
            </button>

              <button 
                v-if="lgd.calculation_source === 'manual'"
                @click="lgd.has_supporting_document ? downloadFile(lgd.id) : downloadFile(lgd.id)"
                :class="[
                  lgd.has_supporting_document 
                    ? 'text-green-600 hover:text-green-800' 
                    : 'text-yellow-600 hover:text-yellow-800'
                ]"
                :title="lgd.has_supporting_document ? 'Download Support Doc' : 'Attach Support Doc First'"
              >
                <i v-if="lgd.has_supporting_document" class="fas fa-check-circle"></i>
                <i v-else class="fas fa-file-download"></i>
              </button>

              <button 
                v-if="lgd.calculation_source === 'manual'"
                @click="openUploadModal(lgd.id)" 
                class="text-gray-700 hover:text-yellow-900 transition-colors"
                aria-label="Attach File"
                title="Attach File"
              >
                <i class="fas fa-paperclip"></i>
              </button>

              <!-- <button @click="" class="text-green-600 hover:text-green-800">
                <i class="fas fa-eye"></i>
              </button> -->
          <!-- Locked State (Closed) -->
          <button
            v-if="lgd.is_active_or_closed === 'closed'"
            @click="lockLGD(lgd.id)"
            :disabled="loading === lgd.id"
            class="text-red-600 hover:text-red-800"
            title="Unlock LGD"
          >
            <i v-if="loading !== lgd.id" class="fas fa-lock"></i>
            <svg v-else class="animate-spin h-5 w-5 text-red-600" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
            </svg>
          </button>

          <!-- Active State -->
          <button
            v-else
            @click="lockLGD(lgd.id)"
            :disabled="loading === lgd.id"
            class="text-green-600 hover:text-green-800"
            title="Lock LGD"
          >
            <i v-if="loading !== lgd.id" class="fas fa-lock-open"></i>
            <svg v-else class="animate-spin h-5 w-5 text-green-600" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
            </svg>
          </button>
              <button v-if="lgd.is_active_or_closed === 'active'"
              @click="deleteLGD(lgd.id)" 
              :disabled="loading === lgd.id"
              class="text-red-600 hover:text-red-800">
                <i v-if="loading !==lgd.id" class="fas fa-trash"></i>
              </button>

            <button v-if="lgd.is_active_or_closed === 'closed'"
            @click="openUpdateModal(lgd)" 
            :disabled="loading === lgd.id"
                class="text-blue-600 hover:text-blue-800" 
                title="Update Loan Book"
            >
                <i v-if="loading !==lgd.id" class="fas fa-book"></i>
            </button>
            </div>
            </td>
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
      <strong>{{ selectedLGD?.portfolio_group?.name }}</strong>.
    </p>

    <!-- Reporting Period -->
    <label for="period" class="block mb-2 text-sm font-medium text-gray-700">Reporting Period</label>
    <input
      type="month"
      v-model="selectedPeriod"
      id="period"
      class="border-gray-300 rounded-md shadow-sm w-full mb-4"
    >

    <!--  Include Customer LGD -->
    <label class="flex items-center mb-4">
      <input
        type="checkbox"
        v-model="includeCustomerLGD"
        class="mr-2 text-green-600 focus:ring-green-500 border-gray-300 rounded"
      >
      <span class="text-sm text-gray-700">Include Customer LGD in Update</span>
    </label>

    <!-- Buttons -->
    <div class="flex justify-end space-x-2">
      <button
        @click="showModal = false"
        class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400"
      >
        Cancel
      </button>

      <button
        @click="submitUpdate"
        class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700"
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




    </app-layout>
</template>

<script>
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import JetInput from '@/Jetstream/Input.vue'
import Pagination from '@/Shared/Pagination.vue'
import '@fortawesome/fontawesome-free/css/all.css';
import HelpManual from '../../Components/HelpManual.vue';

export default {
    components: {
        AppLayout,
        HelpManual,
    },
    props: {
        lossGivenDefaults: {
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
        const search = ref('');
        const startDate = ref('');
        const endDate = ref('');
        const filters = ref({
              lgd_calculation_level: props.filters.lgd_calculation_level || '',
              start_date: props.filters.start_date || '',
              end_date: props.filters.end_date || '',
          });

        const showUploadModal = ref(false);
        const uploadTargetId = ref(null);
        const uploadFile = ref(null);
        const uploadLoading = ref(false);

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

        const applyFilters = () => {
              router.get(route('loss-given-default.index'), {
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

        const lockLGD = (id) => {
            if (confirm('Are you sure you want to change the lock status of this LGD?')) {
                loading.value = id;
                router.post(route('loss-given-default.lock', id), {}, {
                    preserveScroll: true,
                    onFinish: () => { loading.value = null; },
                    onSuccess: () => { router.reload({ only: ['lossGivenDefaults'] }); },
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

            router.post(route('loss-given-default.attach-file', uploadTargetId.value), formData, {
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
                window.location.href = `/loss-given-default/${id}/download-file`;
            };

            
        const openUpdateModal = (lgd) => {
            selectedLGD.value = lgd;
            selectedPeriod.value = '';
            showModal.value = true;
        };

        const submitUpdate = () => {
            if (!selectedPeriod.value) {
                alert('Please select a period.');
                return;
            }

            loading.value = selectedLGD.value.id;

            router.post(route('loss-given-default.update-loan-book', selectedLGD.value.id), {
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
                    router.reload({ only: ['lossGivenDefaults'] });
                },
                onError: () => {
                    alert('Something went wrong. Please try again.');
                },
            });
        };


        const editLGD = (id) => {
            router.get(`/loss-given-default/${id}/edit`);
        };

        const deleteLGD = (id) => {
            if (confirm('Are you sure?')) {
                router.delete(`/loss-given-default/delete/${id}`);
            }
        };

        return {
            openUploadModal,
            handleModalFileChange,
            submitUpload,
            uploadTargetId,
            uploadFile,
            uploadLoading,
            showUploadModal,
            filters,
            formatDate,
            loading,
            lockLGD,
            openUpdateModal,
            submitUpdate,
            editLGD,
            deleteLGD,
            showModal,
            selectedLGD,
            selectedPeriod,
            search,
            round,
            startDate,
            endDate,
            applyFilters,
            resetFilters,
            includeCustomerLGD,
            downloadFile,
        };
    }
};
</script>