<template>
  <app-layout>
    <template #header>
      <div class="flex justify-between items-center">
       <div>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Collateral Allocations
        </h2>
         <p class="mt-1 text-sm text-gray-600">List of Peformed Allocations based on Customer ID</p>
      </div>

        <div class="flex space-x-2">
          <!-- Import Collateral Register -->
          <inertia-link
            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
            :href="route('collateral.register.index')"
          >
           <i class="fa fa-eye" aria-hidden="true"></i>  View Register 
          </inertia-link>

          <inertia-link
            class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-500 active:bg-gray-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-gray-300 disabled:opacity-25 transition"
            :href="route('collateral.register.import')"
          >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                  </svg>
            Import Register
          </inertia-link>

          <!-- Auto Allocate Collateral -->
          <inertia-link
            class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700"
            :href="route('collateral.allocate')"
          >
            + Allocate
          </inertia-link>
        </div>
      </div>
    </template>

    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6 bg-white border-b border-gray-200">
            <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-100">
              <tr>
                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer ID</th>
                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer Name</th>
                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Basis</th>
                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exposure (MKW)</th>
                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Discounted Allocated (MKW)</th>
                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Collateral Adequecy Ratio (%)</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="item in allocations.data"
                :key="item.id"
                class="bg-white divide-y divide-gray-200"
              >
                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">{{ item.contract_id }}</td>
                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">{{ item.customer_name }}</td>
                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">{{ item.allocation_basis }}</td>
                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500"> {{ formatCurrency(item.total_customer_exposure) }}</td>
                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500"> {{ formatCurrency(item.discounted_collateral) }}</td>
                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">  {{ (item.coverage_ratio * 100).toFixed(2) }} </td>
              </tr>
            </tbody>
          </table>
    </div>
      </div>
    </div>

    <Pagination v-if="allocations.links" :links="allocations.links" class="mt-4" />
  </app-layout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { defineProps } from 'vue'
import '@fortawesome/fontawesome-free/css/all.min.css'

const props = defineProps({ allocations: Object })

const formatCurrency = (value) => {
    if (!value) return 'E0.00';
    return  new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
};
</script>
