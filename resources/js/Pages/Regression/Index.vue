<template>
  <app-layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Regression Models
      </h2>
      <p class="mt-1 text-sm text-gray-600">
        Manage and analyze regression models for credit loss forecasting
      </p>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
          <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
              <dt class="text-sm font-medium text-gray-500 truncate">
                Total Models
              </dt>
              <dd class="mt-1 text-3xl font-semibold text-gray-900">
                {{ models.total }}
              </dd>
            </div>
          </div>
          <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
              <dt class="text-sm font-medium text-gray-500 truncate">
                Active Models
              </dt>
              <dd class="mt-1 text-lg font-semibold text-maiic-600">
                {{ activeModelsCount }}
              </dd>
            </div>
          </div>
          <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
              <dt class="text-sm font-medium text-gray-500 truncate">
                Avg R²
              </dt>
              <dd class="mt-1 text-2xl font-semibold text-maiic-600">
                {{ averageRSquared }}
              </dd>
            </div>
          </div>
          <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
              <dt class="text-sm font-medium text-gray-500 truncate">
                Approved Models
              </dt>
              <dd class="mt-1 text-2xl font-semibold text-maiic-600">
                {{ approvedModelsCount }}
              </dd>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="mb-6 flex justify-between items-center">
          <div class="flex space-x-3">
            <inertia-link
              :href="route('regression.create')"
              class="inline-flex items-center px-4 py-2 bg-maiic-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-maiic-700 active:bg-maiic-800 focus:outline-none focus:border-maiic-800 focus:ring focus:ring-maiic-300 disabled:opacity-25 transition"
            >
              <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
              </svg>
              Train New Model
            </inertia-link>
          </div>
        </div>

        <!-- Models Table -->
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Model
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Portfolio & Type
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Statistics
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Status
                  </th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="model in models.data" :key="model.id" class="hover:bg-gray-50">
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900">{{ model.name }}</div>
                    <div class="text-sm text-gray-500">Created {{ formatDate(model.created_at) }}</div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm text-gray-900">{{ model.portfolio.name }}</div>
                    <div class="text-sm text-gray-500 capitalize">
                      {{ model.type }} - {{ model.dependent_variable.name }}
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex space-x-4 text-sm">
                      <div>
                        <span class="text-gray-500">R²:</span>
                        <span :class="getRSquaredColor(model.r_squared)" class="font-medium ml-1">
                          {{ (model.r_squared * 100).toFixed(1) }}%
                        </span>
                      </div>
                      <div>
                        <span class="text-gray-500">Adj R²:</span>
                        <span class="font-medium ml-1">
                          {{ (model.adj_r_squared * 100).toFixed(1) }}%
                        </span>
                      </div>
                      <div>
                        <span class="text-gray-500">Periods:</span>
                        <span class="font-medium ml-1">{{ model.train_periods }}</span>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex flex-col space-y-1">
                      <span :class="getStatusBadgeClass(model)" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                        {{ model.is_active ? 'Active' : 'Inactive' }}
                      </span>
                      <span v-if="model.is_approved" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-maiic-100 text-maiic-800">
                        Approved
                      </span>
                      <span v-else class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                        Pending Approval
                      </span>
                    </div>
                  </td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <div class="flex space-x-2">
                      <inertia-link
                        :href="route('regression.view', model.id)"
                        class="text-maiic-600 hover:text-maiic-900"
                      >
                        View
                      </inertia-link>
                      <button
                        @click="toggleActive(model)"
                        :class="model.is_active ? 'text-amber-600 hover:text-amber-900' : 'text-maiic-600 hover:text-maiic-900'"
                        class="transition-colors duration-150"
                      >
                        {{ model.is_active ? 'Deactivate' : 'Activate' }}
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div v-if="models.total > 0" class="px-6 py-4 bg-gray-50 border-t">
            <div class="flex flex-wrap justify-center -mb-1">
              <template v-for="(link, key) in models.links" :key="key">
                <div
                  v-if="!link.url"
                  class="mr-1 mb-1 px-3 py-2 text-sm leading-4 text-gray-400 border rounded"
                  v-html="link.label"
                />
                <inertia-link
                  v-else
                  :href="link.url"
                  class="mr-1 mb-1 px-3 py-2 text-sm leading-4 border rounded hover:bg-white focus:border-maiic-500 focus:text-maiic-500 transition-colors duration-150"
                  :class="{ 'bg-white font-semibold text-maiic-700': link.active }"
                  v-html="link.label"
                />
              </template>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-if="models.total === 0" class="text-center py-12 bg-white rounded-lg shadow">
          <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
          </svg>
          <h3 class="mt-2 text-sm font-medium text-gray-900">No regression models</h3>
          <p class="mt-1 text-sm text-gray-500">Get started by training your first model.</p>
          <div class="mt-6">
            <inertia-link
              :href="route('regression.create')"
              class="inline-flex items-center px-4 py-2 bg-maiic-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-maiic-700 focus:outline-none focus:border-maiic-700 focus:ring focus:ring-maiic-200 active:bg-maiic-800 transition"
            >
              Train New Model
            </inertia-link>
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
    models: Object,
  },
  computed: {
    activeModelsCount() {
      return this.models.data.filter(model => model.is_active).length
    },
    approvedModelsCount() {
      return this.models.data.filter(model => model.is_approved).length
    },
    averageRSquared() {
      if (this.models.data.length === 0) return '0.0%'
      const avg = this.models.data.reduce((sum, model) => sum + model.r_squared, 0) / this.models.data.length
      return (avg * 100).toFixed(1) + '%'
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
    getRSquaredColor(rSquared) {
      if (rSquared >= 0.8) return 'text-maiic-600'
      if (rSquared >= 0.6) return 'text-amber-600'
      return 'text-red-600'
    },
    getStatusBadgeClass(model) {
      return model.is_active 
        ? 'bg-maiic-100 text-maiic-800' 
        : 'bg-gray-100 text-gray-800'
    },
    toggleActive(model) {
      if (confirm(`Are you sure you want to ${model.is_active ? 'deactivate' : 'activate'} this model?`)) {
        this.$inertia.patch(route('regression.toggle-active', model.id))
      }
    }
  }
}
</script>