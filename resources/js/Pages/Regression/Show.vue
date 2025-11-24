<template>
  <app-layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ model.name }}
      </h2>
      <p class="mt-1 text-sm text-gray-600">
        Regression model details and predictions
      </p>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        
        <!-- Model Overview Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
          <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
              <dt class="text-sm font-medium text-gray-500 truncate">R²</dt>
              <dd class="mt-1 text-3xl font-semibold text-gray-900">
                {{ ((Number(model.r_squared || 0) * 100).toFixed(1)) }}%
              </dd>
            </div>
          </div>
          <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
              <dt class="text-sm font-medium text-gray-500 truncate">Adj R²</dt>
              <dd class="mt-1 text-lg font-semibold text-gray-900">
                {{ ((Number(model.adj_r_squared || 0) * 100).toFixed(1)) }}%
              </dd>
            </div>
          </div>
          <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
              <dt class="text-sm font-medium text-gray-500 truncate">Training Periods</dt>
              <dd class="mt-1 text-2xl font-semibold text-green-600">
                {{ model.train_periods }}
              </dd>
            </div>
          </div>
          <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
              <dt class="text-sm font-medium text-gray-500 truncate">Status</dt>
              <dd class="mt-1 text-2xl font-semibold" :class="model.is_active ? 'text-green-600' : 'text-gray-600'">
                {{ model.is_active ? 'Active' : 'Inactive' }}
              </dd>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <!-- Left Column - Model Details -->
          <div class="lg:col-span-2 space-y-6">
            <!-- Model Configuration -->
            <div class="bg-white shadow rounded-lg">
              <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Model Configuration</h3>
              </div>
              <div class="p-6">
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <dt class="text-sm font-bold text-gray-900">Portfolio</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ model.portfolio ? model.portfolio.name : '—' }}</dd>
                  </div>
                  <div>
                    <dt class="text-sm font-bold text-gray-900">Model Type</dt>
                    <dd class="mt-1 text-sm text-gray-900 capitalize">{{ model.type }}</dd>
                  </div>
                  <div>
                    <dt class="text-sm font-bold text-gray-900">Dependent Variable</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                      <span class="px-2 py-1 bg-green-200 rounded text-xs">
                      {{ model.dependent_variable ? model.dependent_variable.name : '—' }}
                      </span>
                    </dd>
                  </div>
                  <div>
                    <dt class="text-sm font-bold text-gray-900">Training Period</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                      {{ model.train_start ? formatDate(model.train_start) : '—' }} to {{ model.train_end ? formatDate(model.train_end) : '—' }}
                    </dd>
                  </div>
                  <div class="md:col-span-2">
                    <dt class="text-sm font-medium text-gray-900">Independent Variables</dt>
                    <dd class="mt-1">
                     <div class="flex flex-wrap gap-2">
                          <span
                            v-for="(variable, index) in independentVariables"
                            :key="index"
                            class="px-2 py-1 bg-blue-200 rounded text-xs"
                          >
                            {{ variable?.statistic_name || variable?.name || '??' }}
                          </span>
                        </div>
                    </dd>
                  </div>
                </dl>
              </div>
            </div>

            <!-- Coefficients Table -->
            <div class="bg-white shadow rounded-lg">
              <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Regression Coefficients</h3>
              </div>
              <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Variable
                      </th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Coefficient
                      </th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        Standard Error
                      </th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        t-statistic
                      </th>
                      <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        p-value
                      </th>
                    </tr>
                  </thead>
                  <tbody class="bg-white divide-y divide-gray-200">
                    <tr v-for="(coef, varName, index) in model.coeffs" :key="varName">
                      <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        {{ varName === 'intercept' 
                            ? 'Intercept' 
                            : ((independentVariables[varName] && (independentVariables[varName].statistic_name || independentVariables[varName].name)) 
                                || String(varName)) }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ Number(coef || 0).toFixed(6) }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ ((model.stats && model.stats.standard_errors && model.stats.standard_errors[index] != null) ? model.stats.standard_errors[index] : 0).toFixed(6) }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        {{ ((model.stats && model.stats.t_statistics && model.stats.t_statistics[index] != null) ? model.stats.t_statistics[index] : 0).toFixed(4) }}
                      </td>
                      <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <span :class="getPValueColor((model.stats && model.stats.p_values && model.stats.p_values[index] != null) ? model.stats.p_values[index] : 1)">
                          {{ ((model.stats && model.stats.p_values && model.stats.p_values[index] != null) ? model.stats.p_values[index] : 1).toFixed(4) }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Right Column - Actions & Status -->
          <div class="space-y-6">
            <!-- Model Status -->
            <div class="bg-white shadow rounded-lg">
              <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Model Status</h3>
              </div>
              <div class="p-6">
                <div class="space-y-4">
                  <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Active Status</span>
                    <span :class="model.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'" 
                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                      {{ model.is_active ? 'Active' : 'Inactive' }}
                    </span>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Approval Status</span>
                    <span :class="model.is_approved ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'" 
                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                      {{ model.is_approved ? 'Approved' : 'Pending' }}
                    </span>
                  </div>
                  <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Validation</span>
                    <span :class="modelMeetsCriteria ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" 
                          class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                      {{ modelMeetsCriteria ? 'Passed' : 'Failed' }}
                    </span>
                  </div>
                </div>

                <div class="mt-6 space-y-3">
                  <button
                    @click="toggleActive"
                    :class="model.is_active ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-green-600 hover:bg-green-700'"
                    class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                  >
                    {{ model.is_active ? 'Deactivate Model' : 'Activate Model' }}
                  </button>
                  
                  <button
                    v-if="!model.is_approved"
                    @click="approveModel"
                    class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                  >
                    Approve Model
                  </button>
                </div>
              </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white shadow rounded-lg">
              <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Quick Actions</h3>
              </div>
              <div class="p-6">
                <div class="space-y-3">
                  <inertia-link
                    :href="route('regression.predict', { model: model.id })"
                    class="w-full inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                  >
                    Make Predictions
                  </inertia-link>
                  
                  <button
                    @click="exportModel"
                    class="w-full inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                  >
                    Export Model Data
                  </button>
                </div>
              </div>
            </div>
          </div>
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
    model: Object,
    independentVariables: Object,
  },
  computed: {
    modelMeetsCriteria() {
      return this.model.r_squared >= 0.7 && 
             this.model.adj_r_squared >= 0.65 &&
             this.model.train_periods >= 24
    }
  },
  methods: {
    formatDate(date) {
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      })
    },
    getPValueColor(pValue) {
      if (pValue < 0.01) return 'text-green-600 font-medium'
      if (pValue < 0.05) return 'text-yellow-600'
      return 'text-red-600'
    },
    toggleActive() {
      if (confirm(`Are you sure you want to ${this.model.is_active ? 'deactivate' : 'activate'} this model?`)) {
       this.$inertia.patch(route('regression.toggle-active', { model: this.model.id }))
      }
    },
    approveModel() {
      if (confirm('Are you sure you want to approve this model for production use?')) {
        this.$inertia.patch(route('regression.approve', { model: this.model.id }))
      }
    },
    exportModel() {
      // Implement export functionality
      alert('Export functionality to be implemented')
    }
  }
}
</script>