<template>
  <app-layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Train Regression Model
      </h2>
      <p class="mt-1 text-sm text-gray-600">
        Create a new regression model for credit loss forecasting
      </p>
    </template>

    <div class="py-12">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
          <form @submit.prevent="submit" class="p-6">
            <!-- Model Configuration -->
            <div class="space-y-6">
              <!-- Basic Information -->
              <div>
                <h3 class="text-lg font-medium text-gray-900 mb-4">Model Configuration</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Model Name *</label>
                    <input 
                      v-model="form.name" 
                      type="text" 
                      required
                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                      placeholder="e.g., Corporate PD Model Q4 2024"
                    >
                    <p class="mt-1 text-sm text-gray-500">Descriptive name for your model</p>
                  </div>
                  
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Model Type *</label>
                    <select 
                      v-model="form.type" 
                      required
                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                      <option value="">Select Type</option>
                      <option value="pd">Probability of Default (PD)</option>
                      <option value="lgd">Loss Given Default (LGD)</option>
                      <option value="ecl">Expected Credit Loss (ECL)</option>
                      <option value="migration">Credit Migration</option>
                    </select>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700">Portfolio *</label>
                    <select 
                      v-model="form.portfolio_id" 
                      required
                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                      <option value="">Select Portfolio</option>
                      <option v-for="portfolio in portfolios" :key="portfolio.id" :value="portfolio.id">
                        {{ portfolio.name }}
                      </option>
                    </select>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700">Dependent Variable *</label>
                    <select 
                      v-model="form.dep_var_id" 
                      required
                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                      <option value="">Select Variable</option>
                      <option v-for="variable in dependentVariables" :key="variable.id" :value="variable.id">
                        {{ variable.name }} ({{ variable.code }})
                      </option>
                    </select>
                    <p class="mt-1 text-sm text-gray-500">The credit loss metric you want to predict</p>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700">Training Start Date *</label>
                    <input 
                      v-model="form.train_start" 
                      type="month" 
                      required
                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700">Training End Date *</label>
                    <input 
                      v-model="form.train_end" 
                      type="month" 
                      required
                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                  </div>
                </div>
              </div>

              <!-- Independent Variables -->
              <div>
                <h3 class="text-lg font-medium text-gray-900 mb-4">Macroeconomic Factors</h3>
                <p class="text-sm text-gray-600 mb-4">
                  Select the macroeconomic variables that will be used as independent variables in your regression model.
                </p>
                
                <div class="border border-gray-200 rounded-lg overflow-hidden">
                  <div class="bg-gray-50 px-4 py-3 border-b">
                    <div class="flex items-center">
                      <input 
                        type="checkbox" 
                        :checked="allVariablesSelected"
                        @change="toggleAllVariables"
                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                      >
                      <label class="ml-2 text-sm font-medium text-gray-700">Select All Variables</label>
                    </div>
                  </div>
                  
                  <div class="max-h-96 overflow-y-auto">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-0">
                      <div
                        v-for="variable in independentVariables"
                        :key="variable.id"
                        class="flex items-center px-4 py-3 border-b border-gray-100 hover:bg-gray-50"
                      >
                        <input 
                          :id="`var-${variable.id}`"
                          type="checkbox" 
                          :value="variable.id"
                          v-model="form.indep_vars"
                          class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        >
                        <label :for="`var-${variable.id}`" class="ml-3 flex-1">
                          <div class="text-sm font-medium text-gray-900">{{ variable.statistic_name }}</div>
                          <div class="text-sm text-gray-500">{{ variable.statistic_description }}</div>
                          <div class="text-xs text-gray-400 mt-1">
                            Source: {{ variable.data_source }} • Unit: {{ variable.unit }}
                          </div>
                        </label>
                      </div>
                    </div>
                  </div>
                </div>

                <div v-if="form.indep_vars.length > 0" class="mt-4 p-4 bg-blue-50 rounded-lg">
                  <div class="flex items-center">
                    <svg class="w-5 h-5 text-blue-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="text-sm text-blue-700">
                      {{ form.indep_vars.length }} variable(s) selected. 
                      Recommended: 3-8 variables for optimal model performance.
                    </span>
                  </div>
                </div>
              </div>

              <!-- Validation Rules -->
              <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex">
                  <svg class="w-5 h-5 text-yellow-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                  </svg>
                  <div class="text-sm text-yellow-700">
                    <strong>Validation Requirements:</strong> 
                    Model will be automatically validated against these criteria:
                  </div>
                </div>
                <ul class="mt-2 text-sm text-yellow-600 list-disc list-inside">
                  <li>Minimum R² of 0.7 (70%)</li>
                  <li>Minimum adjusted R² of 0.65 (65%)</li>
                  <li>At least 24 periods of historical data</li>
                  <li>Statistically significant coefficients (p-value < 0.05)</li>
                </ul>
              </div>

              <!-- Submit Button -->
              <div class="flex justify-end space-x-3 pt-6 border-t">
                <inertia-link
                  :href="route('regression.index')"
                  class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                  Cancel
                </inertia-link>
                <button
                  type="submit"
                  :disabled="processing || form.indep_vars.length === 0"
                  class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring focus:ring-indigo-200 active:bg-indigo-800 transition disabled:opacity-25"
                >
                  <svg v-if="processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <span v-if="processing">Training Model...</span>
                  <span v-else>Train Regression Model</span>
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'

export default {
  components: { AppLayout },
  props: {
    portfolios: Array,
    dependentVariables: Array,
    independentVariables: Array,
  },
  data() {
    return {
      processing: false,
      form: {
        name: '',
        type: '',
        portfolio_id: '',
        dep_var_id: '',
        indep_vars: [],
        train_start: '',
        train_end: '',
      },
    }
  },
  computed: {
    allVariablesSelected() {
      return this.form.indep_vars.length === this.independentVariables.length
    }
  },
  methods: {
    submit() {
      this.processing = true
      this.$inertia.post(route('regression.store'), this.form, {
        onFinish: () => (this.processing = false),
      })
    },
    toggleAllVariables() {
      if (this.allVariablesSelected) {
        this.form.indep_vars = []
      } else {
        this.form.indep_vars = this.independentVariables.map(v => v.id)
      }
    }
  }
}
</script>