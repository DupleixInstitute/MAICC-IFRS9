<template>
  <app-layout>
    <div class="p-6 space-y-6">
      <!-- Header -->
      <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold">
        <Link href="/macro-statistics" class="inline-flex items-center px-4 py-2 text-blue-700 rounded-md">
           <i class="fas fa-arrow-left"></i>
        </Link>{{ statistic.statistic_name }} Values
          <span class="ml-2 text-sm text-gray-500">{{ statistic.unit }}</span>
        </h1>
        <button @click="openForm()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
          + Add Value
        </button>
      </div>

      <!-- Filters -->
      <section class="bg-white p-4 rounded-lg shadow">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block mb-1 font-medium">Scenario</label>
            <select v-model="filters.scenario_id" class="w-full p-2 border rounded">
              <option value="">All Scenarios</option>
              <option v-for="s in scenarios" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div>
            <label class="block mb-1 font-medium">Type</label>
            <select v-model="filters.is_forecast" class="w-full p-2 border rounded">
              <option value="">All</option>
              <option :value="false">Historical</option>
              <option :value="true">Forecast</option>
            </select>
          </div>
          <div>
            <label class="block mb-1 font-medium">Period</label>
            <input type="date" v-model="filters.period" class="w-full p-2 border rounded" />
          </div>
        </div>
      </section>

      <!-- Values Table -->
      <section class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scenario</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="value in filteredValues" :key="value.id">
              <td class="px-6 py-4 whitespace-nowrap">{{ formatPeriod(value.period) }}</td>
              <td class="px-6 py-4 whitespace-nowrap">{{ value.value }}</td>
              <td class="px-6 py-4 whitespace-nowrap">{{ value.scenario?.name || 'Base' }}</td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span :class="{
                  'px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800': !value.is_forecast,
                  'px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800': value.is_forecast
                }">
                  {{ value.is_forecast ? 'Forecast' : 'Historical' }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">{{ value.source || '-' }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-center">
                <button @click="editValue(value)" class="text-blue-600 hover:text-blue-900 mr-3">
                  <i class="fas fa-edit"></i> 
                </button>
                <button @click="deleteValue(value)" class="text-red-600 hover:text-red-900">
                  <i class="fas fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </section>

      <!-- Modal Form -->
      <div v-if="showValueForm" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-gray-500 bg-opacity-75" @click="closeForm"></div>
        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-lg mx-4">
          <ValueForm
            :statistic="statistic"
            :profiles="profiles"
            :value="currentValue"
            @close="closeForm"
            @saved="fetchValues"
          />
        </div>
      </div>
    </div>
  </app-layout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ValueForm from './Components/ValueForm.vue';
import '@fortawesome/fontawesome-free/css/all.css';

const props = defineProps({
  statistic: Object,
  values: Array,
  profiles: Array
});

const showValueForm = ref(false);
const currentValue = ref(null);

const filters = ref({
  scenario_id: '',
  is_forecast: '',
  period: ''
});

function formatPeriod(periodStr) {
  const [year, month] = periodStr.split('-');
  const monthNames = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December'
  ];
  return `${monthNames[parseInt(month, 10) - 1]} ${year}`;
}

function openForm() {
  currentValue.value = null;
  showValueForm.value = true;
}

function editValue(value) {
  currentValue.value = value;
  showValueForm.value = true;
}

function closeForm() {
  currentValue.value = null;
  showValueForm.value = false;
}

function fetchValues() {
  router.reload({ only: ['values'] });
}

function deleteValue(value) {
  if (confirm(`Delete value for ${formatDate(value.period)}?`)) {
    router.delete(route('macro-values.destroy', [props.statistic.id, value.id]), {
      onSuccess: () => fetchValues(),
    });
  }
}

function formatDate(dateStr) {
  return new Date(dateStr).toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
}

const parsedForecast = computed(() => {
  return filters.value.is_forecast === '' ? null : JSON.parse(filters.value.is_forecast);
});

const filteredValues = computed(() => {
  return props.values.filter(v =>
    (!filters.value.scenario_id || v.scenario_id == filters.value.scenario_id) &&
    (parsedForecast.value === null || v.is_forecast === parsedForecast.value) &&
    (!filters.value.period || v.period.startsWith(filters.value.period))
  );
});


</script>