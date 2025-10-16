<template>
    <app-layout>
            <template #header>
                 <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Cummulative LGD List
                 <HelpManual />
            </h2>
                    <Link :href="route('lgd-cummulative.create')"
                      class="inline-flex items-center bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg shadow-md transition duration-300 mt-2">
                    Calculate LGD
                    <Icon name="calculator" class="w-4 h-4 mr-2" />
                </Link>
            </div>
        </template>
  <div class="overflow-x-auto mt-6">
    <div class="bg-white shadow-md rounded-lg">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-200">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-small text-gray-500 uppercase tracking-wider">Reporting Period</th>
            <th class="px-6 py-3 text-left text-xs font-small text-gray-500 uppercase tracking-wider">Portfolio</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">LGD %</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cure Rate</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recovery Rate</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Calculated</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance(Start)</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance(End)</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created On</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr v-if="loading">
            <td colspan="15" class="px-6 py-4 text-center text-gray-500">Loading data...</td>
          </tr>
          <tr v-else-if="lgdCummulatives.length === 0">
            <td colspan="15" class="px-6 py-4 text-center text-gray-500">No Cummulative Loss-Given-Default found.</td>
          </tr>
           <tr v-for="lgdC in lgdCummulatives" :key="lgdC.id">
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{formatDate(lgdC.start_period)}} - {{formatDate(lgdC.reporting_period)}}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{lgdC.portfolio_group?.name}}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{lgdC.lgd_cummulative}}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{lgdC.cure_rate_cummulative}}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{lgdC.recovery_rate_cummulative}}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{lgdC.calculation_source}}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                <span
                class="px-2 py-1 rounded-full text-xs font-semibold"
                :class="{
                    'bg-red-100 text-red-700': lgdC.is_active_or_closed === 'closed',
                    'bg-green-100 text-green-700': lgdC.is_active_or_closed === 'active'
                }"
                >
                {{ lgdC.is_active_or_closed === 'closed' ? 'Closed' : 'Active' }}
                </span>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{lgdC.start_total_stage3}}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{lgdC.end_total_stage3}}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{lgdC.created_by}}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{formatDate(lgdC.created_at)}}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600 flex space-x-2">
            <!-- <button
            v-if="lgd.calculation_source === 'manual'"
            @click="editLGD(lgd.id)"
            class="text-blue-600 hover:text-blue-800 transition-colors"
            aria-label="Edit LGD"
          >
            <i class="fas fa-pencil"></i>
          </button> -->
              <button  @click="showPeriods(lgdC.periods_list)"  class="text-brown-600 hover:text-brown-800" title="Show Periods">
                <i class="fas fa-eye"></i>
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
            class="text-green-600 hover:text-green-800"
            title="Lock LGD"
          >
            <i v-if="loading !== lgdC.id" class="fas fa-lock-open"></i>
            <svg v-else class="animate-spin h-5 w-5 text-green-600" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z" />
            </svg>
          </button>

            <button v-if="lgdC.is_active_or_closed === 'closed'"
            @click="openUpdateModal(lgdC)" 
            :disabled="loading === lgdC.id"  
                class="text-blue-600 hover:text-blue-800" 
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
                class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700"
                :disabled="loading === selectedLGD?.id"
            >
                <span v-if="loading === selectedLGD?.id"></span>
                Update
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
        lgdCummulatives: {
            type: Array,
            required: true,
        },
    },
    setup() {
        const loading = ref(null);
        const showModal = ref(false);
        const selectedLGD = ref(null);
        const selectedPeriod = ref('');
        const periodsModalVisible = ref(false)
        const currentPeriods = ref([])

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
            deleteLGD,
            showModal,
            selectedLGD,
            selectedPeriod,
            HelpManual
        };
    }
};
</script>