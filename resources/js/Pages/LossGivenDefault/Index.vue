<template>
    <app-layout>
            <template #header>
             <div class="flex justify-between items-center">
                  <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                      Monthly Loss Given Default
                      <HelpManual />
                  </h2>

                  <div class="flex space-x-2 mt-2">
                      <!-- Calculate -->
                      <Link
                          :href="route('loss-given-default.create')"
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

  <div class="overflow-x-auto mt-6">
    <div class="bg-white shadow-md rounded-lg">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-200">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-small text-gray-500 uppercase tracking-wider">Reporting Period</th>
            <th class="px-6 py-3 text-left text-xs font-small text-gray-500 uppercase tracking-wider">Portfolio</th>
            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">LGD %</th>
            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Cure Rate %</th>
            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Recovery Rate %</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Calculated</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Settled (Recovered Amount)</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">MKW Balance(Start)</th>
            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">MKW Balance(End)</th>
            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Discounting</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created On</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-if="loading">
            <td colspan="15" class="px-6 py-4 text-center text-gray-500">Loading data...</td>
          </tr>
          <tr v-else-if="lossGivenDefaults.length === 0">
            <td colspan="15" class="px-6 py-4 text-center text-gray-500">No Monthly Loss-Given-Default</td>
          </tr>
           <tr v-for="lgd in lossGivenDefaults" :key="lgd.id">
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{formatDate(lgd.start_period)}} - {{formatDate(lgd.reporting_period)}}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{lgd.portfolio_group?.name}}</td>
            <td :class="lgdColorClass(lgd.loss_given_default_percentage)">{{ (lgd.loss_given_default_percentage * 100).toFixed(2) }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-center text-gray-600">{{ (lgd.cure_rate * 100).toFixed(2) }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-center text-gray-600">{{ (lgd.recovery_rate * 100).toFixed(2) }}</td>
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
            <td class="px-3 py-4 whitespace-nowrap text-right text-m text-gray-500">{{formatCurrency(lgd.recovered_amount)}}</td>
            <td class="px-3 py-4 whitespace-nowrap text-right text-m text-gray-500">{{formatCurrency(lgd.start_total_stage3)}}</td>
            <td class="px-3 py-4 whitespace-nowrap text-right text-m text-gray-500">{{formatCurrency(lgd.end_total_stage3)}}</td>
            <td class="px-3 py-4 whitespace-nowrap text-center">
                <span
                    v-if="lgd.is_discounting"
                    class="px-2 py-1 rounded-full text-xs font-semibold bg-maiic-500 text-gray-900"
                >
                    Enabled
                </span>
                <span
                    v-else
                    class="px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800"
                >
                    Not Enabled
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-center text-gray-600">{{lgd.created_by}}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{formatDate(lgd.created_at)}}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600 flex space-x-2"  >
            <button
            v-if="lgd.calculation_source === 'manual'"
            @click="editLGD(lgd.id)"
            class="text-blue-600 hover:text-blue-800 transition-colors"
            aria-label="Edit LGD"
          >
            <i class="fas fa-pencil"></i>
          </button>

          <!-- View Discounted Payments Button -->
          <inertia-link
            v-if="lgd.is_discounting"
            :href="route('loss-given-default.discounted-payments', lgd.id)"
             class="text-purple-600 hover:text-purple-800 transition-colors"
            aria-label="View Discounted Payments"
            title="View Discounted Payments"
          >
            <i class="fas fa-eye"></i>
          </inertia-link>

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
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <div v-if="showModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
        <h2 class="text-lg font-bold mb-4">Update Loan Book Period</h2>
        <p class="mb-4">Select the reporting period to update loan books for <strong>{{ selectedLGD?.portfolio_group?.name }}</strong>.</p>

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
                :disabled="loading === selectedLGD?.id"
            >
                <span v-if="loading === selectedLGD?.id">Update..</span>
                <span v-else>Update</span>

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
import '@fortawesome/fontawesome-free/css/all.css';
import HelpManual from '../../Components/HelpManual.vue';

export default {
    components: {
        AppLayout,
        HelpManual,
    },
    props: {
        lossGivenDefaults: {
            type: Array,
            required: true,
        },
    },
    setup() {
        const loading = ref(null);
        const showModal = ref(false);
        const selectedLGD = ref(null);
        const selectedPeriod = ref('');
        const showReportModal = ref(false);
        const reportPeriod = ref('');
        const reportLoading = ref(false);
        const reportStartPeriod = ref('');
        const reportEndPeriod = ref('');

        const round = (value, decimals = 2) => {
            if (value === null || value === undefined) return '-';
            return Number(Math.round(parseFloat(value + 'e' + decimals)) + 'e-' + decimals).toFixed(decimals);
        };

        // const formatCurrency = (value) => {
        //     if (value === null || value === undefined) return '-';
        //     return new Intl.NumberFormat('en-US', {
        //         style: 'currency',
        //         currency: 'USD',
        //     }).format(value);
        // };

        const formatCurrency = (value) => {
            if (!value) return 'E0.00';
            return new Intl.NumberFormat('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(value).replace(/,/g, ' ');
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
                router.post(route('loss-given-default.lock', id), {}, {
                    preserveScroll: true,
                    onFinish: () => { loading.value = null; },
                    onSuccess: () => { router.reload({ only: ['lossGivenDefaults'] }); },
                    onError: () => { alert('Something went wrong. Please try again.'); },
                });
            }
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

        const openReportModal = () => {
            reportPeriod.value = '';
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

        // Redirect to backend route with both periods
        const params = new URLSearchParams({
            start_period: reportStartPeriod.value,
            end_period: reportEndPeriod.value,
        });

        window.location.href = route('loss-given-default.report-by-period') + '?' + params.toString();

        setTimeout(() => {
            reportLoading.value = false;
            showReportModal.value = false;
        }, 1500);
    };

      const lgdColorClass = (lgd) => {
          if (lgd == null) return 'px-6 py-4 whitespace-nowrap font-bold text-center text-gray-600'; // null or missing LGD
          // Example threshold: LGD >= 0.5 (50%) is high
          return lgd >= 0.5 ? 'px-6 py-4 whitespace-nowrap font-bold text-center text-orange-600' : 'px-6 py-4 whitespace-nowrap font-bold text-center text-green-600';
      };


        return {
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
            showReportModal,
            openReportModal,
            reportPeriod,
            reportLoading,
            reportStartPeriod,
            reportEndPeriod,
            downloadReport,
            round,
            formatCurrency,
            lgdColorClass,
        };
    }
};
</script>
