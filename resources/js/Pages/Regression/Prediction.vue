<template>
  <AppLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Generate Predictions — {{ props.model.name }}
      </h2>
    </template>

    <div class="py-6 max-w-5xl mx-auto">
      <div class="bg-white shadow rounded-xl p-6">

        <!-- Success/Error Messages -->
        <div v-if="$page.props.flash.success" class="mb-4 p-3 bg-green-100 text-green-700 rounded">
          {{ $page.props.flash.success }}
        </div>
        <div v-if="$page.props.flash.error" class="mb-4 p-3 bg-red-100 text-red-700 rounded">
          {{ $page.props.flash.error }}
        </div>

        <!-- Scenario Selection -->
        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700">Scenario</label>
          <select 
            v-model="form.scenario_id" 
            @change="fetchMacroData"
            class="mt-1 block w-full border rounded p-2"
          >
            <option disabled value="">Select Scenario</option>
            <option v-for="s in props.scenarios" :key="s.id" :value="s.id">
              {{ s.profile_code }} — {{ s.name }}
            </option>
          </select>
        </div>

        <!-- Period Selection -->
        <div class="mb-6">
          <label class="block text-sm font-medium text-gray-700">Forecast Periods</label>
          <div class="mt-2 space-y-2">
            <label v-for="period in props.availablePeriods" :key="period" class="inline-flex items-center mr-4">
              <input 
                type="checkbox" 
                :value="period" 
                v-model="form.periods"
                @change="fetchMacroData"
                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
              >
              <span class="ml-2">{{ period }}</span>
            </label>
          </div>
          <p class="mt-1 text-sm text-gray-500">
            These periods have forecast macro data available
          </p>
        </div>

        <!-- Macro Variables Display (Read-only from forecast data) -->
        <div class="mb-6" v-if="macroData && Object.keys(macroData).length > 0">
          <h3 class="text-lg font-medium text-gray-900 mb-4">Forecast Macro Data</h3>
          <p class="text-sm text-gray-600 mb-4">
            Using forecast macro data for selected scenario and periods
          </p>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div v-for="v in props.macroVariables" :key="v.id" class="border rounded-lg p-4 bg-gray-50">
              <label class="block text-sm font-medium text-gray-700 mb-2">
                {{ v.statistic_code }} — {{ v.statistic_name }}
              </label>
              <div class="space-y-2">
                <div v-for="period in form.periods" :key="period" class="flex justify-between items-center">
                  <span class="text-sm text-gray-600">{{ period }}:</span>
                  <span class="font-mono text-sm">
                    {{ getMacroValue(v.statistic_code, period) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <div v-else-if="form.scenario_id && form.periods.length > 0" class="mb-6 text-center py-4 text-gray-500">
          Loading forecast macro data...
        </div>

        <div class="mt-6">
          <button 
            @click="submitPrediction" 
            :disabled="form.processing || !form.scenario_id || form.periods.length === 0 || !macroData"
            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 disabled:bg-gray-400 disabled:cursor-not-allowed"
          >
            <span v-if="form.processing">Generating Predictions...</span>
            <span v-else>Generate Predictions</span>
          </button>
        </div>

        <div v-if="Object.keys(form.errors).length" class="mt-4 p-3 bg-red-100 text-red-700 rounded">
          <div v-for="(e, i) in form.errors" :key="i">{{ e }}</div>
        </div>

      </div>

      <!-- Predictions History -->
      <div class="bg-white shadow rounded-xl p-6 mt-6">
        <h3 class="text-lg font-semibold mb-4">Predictions History</h3>
        
        <div v-if="props.model.predictions && props.model.predictions.length > 0" class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scenario</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Predicted Value</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="prediction in props.model.predictions" :key="prediction.id">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ getScenarioName(prediction.scenario_id) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ prediction.period }}</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ formatValue(prediction.predicted_value) }}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                  {{ new Date(prediction.created_at).toLocaleDateString() }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="text-center py-4 text-gray-500">
          No predictions generated yet.
        </div>
      </div>

    </div>
  </AppLayout>
</template>
<script setup>
import { ref, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  model: Object,
  scenarios: Array,
  macroVariables: Array,
  availablePeriods: Array,
  macroData: Object, // already coming as prop
})

const form = useForm({
  scenario_id: '',
  periods: [],
})

// No need for separate macroData ref, we'll rely on props
const macroData = ref(props.macroData || {})

// Watch scenario_id or periods and reload the page with filtered macro data
watch([() => form.scenario_id, () => form.periods], ([scenarioId, periods]) => {
  if (scenarioId && periods.length) {
    router.get(route('regression.predict', props.model.id), {
      scenario_id: scenarioId,
      periods: periods
    }, { preserveState: true, preserveScroll: true });
  }
});

// Get value for a statistic for a given period
function getMacroValue(statisticCode, period) {
  const raw = macroData.value?.[period]?.[statisticCode]

  if (raw === undefined || raw === null || raw === '') return 'N/A'

  const num = Number(raw)
  return isNaN(num) ? raw : num.toFixed(4)
}

// Submit predictions
function submitPrediction() {
  if (!macroData.value || Object.keys(macroData.value).length === 0) {
    alert('No macro data available. Please select a scenario and periods first.')
    return
  }

  form.transform((data) => ({
    ...data,
    macro_data: macroData.value
  })).post(route('regression.predict.store', props.model.id), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset()
      alert('Predictions generated successfully!')
    },
    onError: (errors) => {
      console.log('Errors:', errors)
    }
  })
}

function getScenarioName(scenarioId) {
  const scenario = props.scenarios.find(s => s.id === scenarioId)
  return scenario ? `${scenario.profile_code} - ${scenario.name}` : 'Unknown'
}

function formatValue(value) {
  return typeof value === 'number' ? value.toFixed(6) : value
}
</script>
