<template>
    <app-layout>
      <template #header>
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Macro Forecast Weighted</h2>

    <!-- Select Period -->
    <div class="mb-4 flex justify-end space-x-4">
      <button @click="showForecastForm = true"
              class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
        Calculate Forecast
      </button>
    </div>
  </template>

        <!-- Filters -->
      <section class="bg-white p-4 rounded-lg shadow">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block mb-1 font-medium">Macro Variable</label>
            <select v-model="filters.macro_stat_id" class="w-full p-2 border rounded">
              <option value="">All Variables</option>
              <option v-for="stat in macroVariable" :key="stat.id" :value="stat.id">
                {{ stat.statistic_name }}
              </option>
            </select>
          </div>
            <div>
              <label class="block mb-1 font-medium">Start Period</label>
              <input type="month" v-model="filters.start_period" class="w-full p-2 border rounded" />
            </div>
            <div>
              <label class="block mb-1 font-medium">End Period</label>
              <input type="month" v-model="filters.end_period" class="w-full p-2 border rounded" />
            </div>
        </div>
      </section>

  <!-- Results Table -->
    <div class="overflow-x-auto bg-white shadow rounded">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-100">
          <tr>
            <th class="px-4 py-2 text-left">Period</th>
            <th class="px-4 py-2 text-left">Scenario</th>
            <th class="px-4 py-2 text-left">Macro Variable</th>
            <th class="px-4 py-2 text-left">Weighted Value</th>
            <th class="px-4 py-2 text-left">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="forecast in filteredForecasts" :key="forecast.id" class="border-b">
             <td class="px-4 py-2">
              {{ new Date(forecast.reporting_period?.period).toLocaleDateString('en-US', { month: 'long', year: 'numeric' }) }}
            </td>
            <td class="px-4 py-2">{{ forecast.scenario_profile?.name }}</td>
            <td class="px-4 py-2">{{ forecast.macro_statistic?.statistic_name }}</td>
            <td class="px-4 py-2">{{ forecast.weighted_value }}</td>
            <td class="px-4 py-2">
              <!-- <button @click="editForecast(forecast)" class="text-blue-600 hover:underline">Edit</button> -->
              <button @click="rerunForecast(forecast)" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-calculator"></i>
              </button>
              <button @click="deleteForecast(forecast.id)" class="text-red-600 hover:text-red-800">
                <i class="fas fa-trash-alt"></i>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="mt-4" v-if="forecasts.links">
          <Pagination :links="forecasts.links" />
      </div>
    
    

    <div v-if="showForecastForm" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-gray-500 bg-opacity-75" @click="closeForm"></div>
          <div class="relative bg-white rounded-lg shadow-xl w-full max-w-lg mx-4">
          <CreateForecast 
            :profiles="profiles"
            @close="closeForm"
          />
          </div>
    </div>
    </app-layout>
</template>


<script setup>
import { router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue';
import CreateForecast from './CreateForecast.vue'
import '@fortawesome/fontawesome-free/css/all.min.css';

const props = defineProps({
  forecasts: Array,
  profiles: Array,
  macroVariable: Array
})

const filters = ref({
  macro_stat_id: '',
  start_period: '',
  end_period: ''
})

const filteredForecasts = computed(() => {
  return props.forecasts.data.filter(forecast => {
    const statMatch = !filters.value.macro_stat_id || forecast.macro_statistic?.id === filters.value.macro_stat_id;

    const periodDate = new Date(forecast.reporting_period?.period);
    const startDate = filters.value.start_period ? new Date(filters.value.start_period) : null;
    const endDate = filters.value.end_period ? new Date(filters.value.end_period) : null;

    const periodMatch =
      (!startDate || periodDate >= startDate) &&
      (!endDate || periodDate <= endDate);

    return statMatch && periodMatch;
  });
});

const period = ref(null)

const showForecastForm = ref(false)

function showForm() {
  showForecastForm.value = true
}

function closeForm() {
  showForecastForm.value = false
}

// const reload = () => {
//   router.reload('macro.forecast.weighted.index')
// }

function editForecast(forecast) {
  // Implement edit logic here
  alert(`Edit forecast ID: ${forecast.id}`)
}

function rerunForecast(id){
  if(confirm('Are you sure you want to rerun this forecast?')){
    // Implement rerun logic here
    router.post(route('macro-forecast-weighted.rerun', id))
  }
}

function deleteForecast(id) {
  if (confirm('Are you sure you want to delete this forecast?')) {
    router.delete(route('macro-forecast-weighted.destroy', id))
  }
}
</script>