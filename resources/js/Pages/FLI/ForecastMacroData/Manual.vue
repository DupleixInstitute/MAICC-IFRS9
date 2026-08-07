<!-- resources/js/Pages/Forecasting/ManualForecast.vue -->
<template>
  <app-layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Manual Forecast Input
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        
        <!-- Debug Info (Optional - you can remove this later) -->
        <div class="mb-6 bg-amber-50 p-4 rounded-lg" v-if="showDebug">
          <h3 class="text-lg font-medium text-amber-800">Debug Info</h3>
          <p class="text-sm text-amber-700">Page Props: {{ JSON.stringify($page.props) }}</p>
          <p class="text-sm text-amber-700">Has forecastResults: {{ !!$page.props.forecastResults }}</p>
          <p class="text-sm text-amber-700">Processing: {{ processing }}</p>
        </div>

        <!-- Display Errors -->
        <div v-if="$page.props.errors && Object.keys($page.props.errors).length > 0" class="mb-6">
          <div class="bg-red-50 border border-red-200 rounded-md p-4">
            <div class="flex">
              <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
              </div>
              <div class="ml-3">
                <h3 class="text-sm font-medium text-red-800">
                  There were errors with your submission
                </h3>
                <div class="mt-2 text-sm text-red-700">
                  <ul class="list-disc pl-5 space-y-1">
                    <li v-for="(error, field) in $page.props.errors" :key="field">
                      {{ field }}: {{ error }}
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Calculation Form - Collapsible -->
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mb-6">
          <div class="border-b border-gray-200">
            <button
              @click="calculationExpanded = !calculationExpanded"
              class="w-full flex justify-between items-center p-6 text-left focus:outline-none focus:ring-2 focus:ring-maiic-500 focus:ring-inset"
            >
              <h3 class="text-lg font-medium text-gray-900">
                Forecast Calculation Parameters
              </h3>
              <div class="flex items-center">
                <span class="text-sm text-gray-500 mr-3">
                  {{ calculationExpanded ? 'Collapse' : 'Expand' }}
                </span>
                <svg
                  :class="{'rotate-180': calculationExpanded}"
                  class="h-5 w-5 text-gray-500 transition-transform duration-200"
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 20 20"
                  fill="currentColor"
                >
                  <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
              </div>
            </button>
          </div>

          <div v-show="calculationExpanded" class="p-6">
            <!-- Basic Configuration -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
              <div>
                <label for="dependent_variable" class="block text-sm font-medium text-gray-700">
                  What are you forecasting? *
                </label>
                <select
                  v-model="form.dependent_variable"
                  id="dependent_variable"
                  class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-maiic-500 focus:border-maiic-500 sm:text-sm rounded-md"
                  required
                >
                  <option value="">Select...</option>
                  <option value="NPL">Non-Performing Loans (NPL) Ratio</option>
                  <option value="ECL">Expected Credit Loss (ECL)</option>
                  <option value="PD">Probability of Default (PD)</option>
                  <option value="LGD">Loss Given Default (LGD)</option>
                  <option value="other">Other</option>
                </select>
              </div>

              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label for="regression_intercept" class="block text-sm font-medium text-gray-700">
                    Regression Intercept *
                  </label>
                  <input
                    type="number"
                    step="0.0001"
                    v-model="form.regression_intercept"
                    id="regression_intercept"
                    class="mt-1 block w-full border-gray-300 focus:ring-maiic-500 focus:border-maiic-500 sm:text-sm rounded-md"
                    required
                  />
                </div>
                <div>
                  <label for="regression_coefficient" class="block text-sm font-medium text-gray-700">
                    Regression Coefficient *
                  </label>
                  <input
                    type="number"
                    step="0.0001"
                    v-model="form.regression_coefficient"
                    id="regression_coefficient"
                    class="mt-1 block w-full border-gray-300 focus:ring-maiic-500 focus:border-maiic-500 sm:text-sm rounded-md"
                    required
                  />
                </div>
              </div>
            </div>

            <!-- Baseline Value -->
            <div class="mb-8">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                  <label for="baseline_value" class="block text-sm font-medium text-gray-700">
                    Baseline Value (Current) *
                  </label>
                  <input
                    type="number"
                    step="0.0001"
                    v-model="form.baseline_value"
                    id="baseline_value"
                    class="mt-1 block w-full border-gray-300 focus:ring-maiic-500 focus:border-maiic-500 sm:text-sm rounded-md"
                    required
                  />
                  <p class="mt-1 text-sm text-gray-500">Current value for comparison (e.g., current NPL %)</p>
                </div>
                <div class="md:col-span-2">
                  <div class="bg-maiic-50 p-4 rounded-md">
                    <p class="text-sm font-medium text-maiic-800">
                      <strong>Regression Equation:</strong> 
                      {{ equationPreview }}
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <!-- Scenarios Section -->
            <div class="mb-6">
              <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Economic Scenarios</h3>
                <button
                  @click="addScenario"
                  type="button"
                  class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-maiic-600 hover:bg-maiic-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-maiic-500"
                >
                  <svg class="h-4 w-4 mr-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                  </svg>
                  Add Scenario
                </button>
              </div>
              <p class="text-sm text-gray-600 mb-4">
                Enter macroeconomic forecasts for each scenario (e.g., Inflation Rate %)
              </p>

              <!-- Scenarios Container -->
              <div class="space-y-4">
                <div
                  v-for="(scenario, index) in form.scenarios"
                  :key="index"
                  class="border border-gray-200 rounded-lg p-4 bg-gray-50"
                >
                  <div class="grid grid-cols-1 md:grid-cols-12 gap-4 mb-4">
                    <div class="md:col-span-4">
                      <label :for="`scenario_name_${index}`" class="block text-sm font-medium text-gray-700">
                        Scenario Name
                      </label>
                      <input
                        type="text"
                        v-model="scenario.name"
                        :id="`scenario_name_${index}`"
                        class="mt-1 block w-full border-gray-300 focus:ring-maiic-500 focus:border-maiic-500 sm:text-sm rounded-md"
                        required
                      />
                    </div>
                    <div class="md:col-span-3">
                      <label :for="`scenario_probability_${index}`" class="block text-sm font-medium text-gray-700">
                        Probability (%)
                      </label>
                      <input
                        type="number"
                        v-model="scenario.probability"
                        :id="`scenario_probability_${index}`"
                        min="0"
                        max="100"
                        step="1"
                        class="mt-1 block w-full border-gray-300 focus:ring-maiic-500 focus:border-maiic-500 sm:text-sm rounded-md"
                        required
                      />
                    </div>
                    <div class="md:col-span-5 flex items-end space-x-2">
                      <button
                        v-if="form.scenarios.length > 1"
                        @click="removeScenario(index)"
                        type="button"
                        class="inline-flex items-center px-3 py-2 border border-red-300 text-sm leading-4 font-medium rounded-md text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                      >
                        Remove
                      </button>
                    </div>
                  </div>

                  <!-- Macro Forecast Inputs -->
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Macroeconomic Forecast (Years 1-10)
                    </label>
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                      <div v-for="year in 10" :key="year">
                        <label :for="`scenario_${index}_year_${year}`" class="block text-xs text-gray-600 mb-1">
                          Year {{ year }}
                        </label>
                        <input
                          type="number"
                          step="0.01"
                          v-model="scenario.macro_forecast[year - 1]"
                          :id="`scenario_${index}_year_${year}`"
                          class="block w-full border-gray-300 focus:ring-maiic-500 focus:border-maiic-500 sm:text-sm rounded-md"
                          required
                        />
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-between items-center pt-6 border-t border-gray-200">
              <div class="flex items-center space-x-4">
                <input
                  type="checkbox"
                  v-model="form.save_session"
                  id="save_session"
                  class="h-4 w-4 text-maiic-600 focus:ring-maiic-500 border-gray-300 rounded"
                />
                <label for="save_session" class="text-sm text-gray-700">
                  Save this forecast session
                </label>
                <input
                  v-if="form.save_session"
                  v-model="form.session_name"
                  type="text"
                  placeholder="Session name"
                  class="block w-48 border-gray-300 focus:ring-maiic-500 focus:border-maiic-500 sm:text-sm rounded-md"
                  :class="{ 'border-red-300': $page.props.errors?.session_name }"
                />
              </div>

              <button
                @click="generateForecast"
                :disabled="processing"
                class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-maiic-600 hover:bg-maiic-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-maiic-500 disabled:opacity-50"
              >
                <svg
                  v-if="processing"
                  class="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                >
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Generate Forecast
              </button>
            </div>
          </div>
        </div>

        <!-- Results Section - Collapsible -->
        <div v-if="$page.props.forecastResults" class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
          <div class="border-b border-gray-200">
            <button
              @click="resultsExpanded = !resultsExpanded"
              class="w-full flex justify-between items-center p-6 text-left focus:outline-none focus:ring-2 focus:ring-maiic-500 focus:ring-inset"
            >
              <h3 class="text-lg font-medium text-gray-900">
                Forecast Results
                <span class="ml-2 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-maiic-100 text-maiic-800">
                  Generated
                </span>
              </h3>
              <div class="flex items-center">
                <span class="text-sm text-gray-500 mr-3">
                  {{ resultsExpanded ? 'Collapse' : 'Expand' }}
                </span>
                <svg
                  :class="{'rotate-180': resultsExpanded}"
                  class="h-5 w-5 text-gray-500 transition-transform duration-200"
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 20 20"
                  fill="currentColor"
                >
                  <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                </svg>
              </div>
            </button>
          </div>

          <div v-show="resultsExpanded" class="p-6">
            <!-- Regression Equation -->
            <div class="mb-6">
              <div class="bg-maiic-50 p-4 rounded-md">
                <p class="text-sm font-medium text-maiic-800">
                  <strong>Regression Equation Used:</strong> 
                  {{ $page.props.forecastResults.regression_equation }}
                </p>
              </div>
            </div>

            <!-- Weighted Average Forecast -->
            <div class="mb-8">
              <h4 class="text-md font-medium text-gray-700 mb-4">Weighted Average Forecast</h4>
              <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                <table class="min-w-full divide-y divide-gray-300">
                  <thead class="bg-gray-50">
                    <tr>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Year
                      </th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Weighted Macro Driver
                      </th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Forecast Value
                      </th>
                      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Change from Baseline
                      </th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="forecast in $page.props.forecastResults.weighted_forecast" :key="forecast.period">
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ forecast.year }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ forecast.weighted_macro_driver.toFixed(2) }}%
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ forecast.forecast_value.toFixed(2) }}%
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm" :class="forecast.change_from_baseline >= 0 ? 'text-red-600' : 'text-maiic-600'">
                        {{ forecast.change_from_baseline.toFixed(2) }}%
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Individual Scenario Forecasts -->
            <div>
              <h4 class="text-md font-medium text-gray-700 mb-4">Individual Scenario Forecasts</h4>
              <div class="space-y-4">
                <div
                  v-for="scenario in $page.props.forecastResults.scenario_forecasts"
                  :key="scenario.name"
                  class="border border-gray-200 rounded-lg p-4"
                >
                  <h5 class="text-sm font-medium text-gray-900 mb-3">
                    {{ scenario.name }} (Probability: {{ scenario.probability }}%)
                  </h5>
                  <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-300">
                      <thead class="bg-gray-50">
                        <tr>
                          <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Year
                          </th>
                          <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Macro Value
                          </th>
                          <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Forecast Value
                          </th>
                        </tr>
                      </thead>
                      <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="(forecast, index) in scenario.forecasts" :key="index">
                          <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                            {{ forecast.period + 1 }}
                          </td>
                          <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                            {{ forecast.macro_value }}%
                          </td>
                          <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-900">
                            {{ forecast.forecast_value.toFixed(2) }}%
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- No Results Message -->
        <div v-else-if="!processing" class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
          <div class="p-12 text-center">
            <svg class="mx-auto h-12 w-12 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No forecast generated yet</h3>
            <p class="mt-1 text-sm text-gray-500">Fill out the form above and click "Generate Forecast" to see results.</p>
          </div>
        </div>
      </div>
    </div>
  </app-layout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const processing = ref(false)
const calculationExpanded = ref(true) // Start with calculation expanded
const resultsExpanded = ref(true) // Start with results expanded
const showDebug = ref(false) // Set to true to see debug info

const form = reactive({
  dependent_variable: 'NPL',
  regression_intercept: 0.02,
  regression_coefficient: 0.30,
  baseline_value: 11.55,
  save_session: false,
  session_name: '',
  scenarios: [
    {
      name: 'Base Case',
      probability: 40,
      macro_forecast: [31.50, 28.50, 20.00, 16.00, 16.00]
    },
    {
      name: 'Upside',
      probability: 25,
      macro_forecast: [29.00, 26.00, 17.50, 13.50, 13.50]
    }
  ]
})

const equationPreview = computed(() => {
  return `Forecast = ${form.regression_intercept} + (${form.regression_coefficient} × Macro Driver)`
})

function addScenario() {
  form.scenarios.push({
    name: 'New Scenario',
    probability: 10,
    macro_forecast: [0, 0, 0, 0, 0]
  })
}

function removeScenario(index) {
  form.scenarios.splice(index, 1)
}

function generateForecast() {
  console.log('Form data being sent:', JSON.stringify(form, null, 2))
  processing.value = true
  
  router.post(route('forecasting.manual.process'), form, {
    onStart: () => {
      console.log('Request started')
    },
    onSuccess: (page) => {
      console.log('Request successful - Page props:', page.props)
      console.log('Forecast results received:', page.props.forecastResults)
      processing.value = false
      // Auto-expand results when they're generated
      resultsExpanded.value = true
    },
    onError: (errors) => {
      console.log('Request errors:', errors)
      processing.value = false
    },
    onFinish: () => {
      console.log('Request finished')
      processing.value = false
    },
    preserveScroll: true,
    preserveState: true,
  })
}
</script>