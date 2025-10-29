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
            :href="route('collateral.register.import')"
          >
            Import Register <i class="fa fa-upload" aria-hidden="true"></i>
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
                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contract</th>
                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Basis</th>
                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Discounted Allocated</th>
                <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Coverage</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="item in allocations.data"
                :key="item.id"
                class="bg-white divide-y divide-gray-200"
              >
                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">{{ item.customer_name }}</td>
                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">{{ item.contract_id }}</td>
                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">{{ item.allocation_basis }}</td>
                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">{{ formatCurrency(item.discounted_collateral) }}</td>
                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500"> % {{ (item.coverage_ratio * 100).toFixed(2) }} </td>

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
    return 'E' + new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
};
</script>
