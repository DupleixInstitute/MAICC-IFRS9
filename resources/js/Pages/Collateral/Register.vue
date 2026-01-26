<template>
  <app-layout>
    <template #header>
      <div class="flex justify-between items-center">
        <div>
          <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('collateral.allocations.index')">
          Collateral
        </inertia-link>
            <span>/</span>Register
          </h2>
          <p class="mt-1 text-sm text-gray-600">List of Collateral Register by Date</p>
        </div>
        <div class="flex space-x-2">     
            <inertia-link
              class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500 active:bg-gray-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-gray-300 disabled:opacity-25 transition"
              :href="route('collateral.register.import')"
            >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
              Import Register
            </inertia-link>
          </div>
      </div>
    </template>

    <!-- Filters Section -->
    <div class="bg-white shadow-sm sm:rounded-lg mb-6">
      <div class="p-6 border-b border-gray-200">
        <form @submit.prevent="applyFilters" class="grid grid-cols-6 gap-4">
          <!-- Date From -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
            <input
              v-model="filters.registration_date_from"
              type="month"
              class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
            />
          </div>

          <!-- Date To -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
            <input
              v-model="filters.registration_date_to"
              type="month"
              class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
            />
          </div>

          <!-- Type Code -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Collateral Type</label>
            <input
              v-model="filters.type_code"
              type="text"
              placeholder="e.g. Code: 103"
              class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
            />
          </div>

          <!-- Min Sum -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Customer ID</label>
            <input
              v-model="filters.customer_id"
              type="text"
              placeholder="ID"
              class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
            />
          </div>

          <!-- Max Sum -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Customer Name</label>
            <input
              v-model="filters.customer_name"
              type="text"
              placeholder="Name"
              class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
            />
          </div>

          <!-- Button -->
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
      </div>
    </div>

    <!-- Table Section -->
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
      <div class="p-6 bg-white border-b border-gray-200">
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-100">
              <tr>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer ID</th>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer Name</th>
                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Type Code</th>
                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Reporting Period</th>
                <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">Registration Date</th>
                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Nominal Value</th>
                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Market Value</th>
                <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Execution Value</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="item in collateralRegisters.data"
                :key="item.id"
                class="bg-white divide-y divide-gray-200 hover:bg-gray-50"
              >
                <td class="px-3 py-4 text-sm text-gray-500">{{ item.customer_id }}</td>
                <td class="px-3 py-4 text-sm text-gray-500">{{ item.customer_name }}</td>
                <td class="px-3 py-4 text-center text-sm text-gray-500">{{ item.collateral_type }}</td>  
                <td class="px-3 py-4 text-center text-sm text-gray-500">{{ item.registration_date }}</td>
                <td class="px-3 py-4 text-center text-sm text-gray-500">N/A</td>
                <td class="px-3 py-4 text-right text-sm text-gray-500">{{ formatCurrency(item.nominal_value) }}</td>
                <td class="px-3 py-4 text-right text-sm text-gray-500">{{ formatCurrency(item.market_value) }}</td>
                <td class="px-3 py-4 text-right text-sm text-gray-500">{{ formatCurrency(item.execution_value) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <Pagination v-if="collateralRegisters.links" :links="collateralRegisters.links" class="mt-4" />
  </app-layout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'

const props = defineProps({
  collateralRegisters: Object,
  filters: Object
})

const filters = reactive({
  registration_date_from: props.filters?.registration_date_from || '',
  registration_date_to: props.filters?.registration_date_to || '',
  type_code: props.filters?.type_code || '',
  customer_id: props.filters?.customer_id || '',
  customer_name: props.filters?.customer_name || ''
})

function applyFilters() {
  router.get(route('collateral.register.index'), filters, { preserveState: true })
}

function resetFilters() {
  filters.registration_date_from = ''
  filters.registration_date_to = ''
  filters.type_code = ''
  filters.customer_id = ''
  filters.customer_name = ''

  router.get(route('collateral.register.index'), {}, { preserveState: false })
}
    

const formatCurrency = (value) => {
  if (!value) return 'E0.00'
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(value)
}
</script>
