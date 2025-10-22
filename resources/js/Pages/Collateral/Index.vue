<template>
  <app-layout>
    <template #header>
      <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Collateral Allocations
        </h2>

        <div class="flex space-x-2">
          <!-- Import Collateral Register -->
          <inertia-link
            class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700"
            :href="route('collateral.register.import')"
          >
            Import Register
          </inertia-link>

          <!-- Auto Allocate Collateral -->
          <inertia-link
            class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
            :href="route('collateral.allocate')"
          >
            + Allocate
          </inertia-link>
        </div>
      </div>
    </template>

    <section class="bg-white p-4 rounded-lg shadow mt-4">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-100">
          <tr>
            <th class="px-4 py-2 text-left">Client</th>
            <th class="px-4 py-2 text-left">Contract</th>
            <th class="px-4 py-2 text-left">Allocated</th>
            <th class="px-4 py-2 text-left">Coverage</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="item in allocations.data"
            :key="item.id"
            class="border-b hover:bg-gray-50"
          >
            <td class="px-4 py-2">{{ item.customer_name }}</td>
            <td class="px-4 py-2">{{ item.contract_id }}</td>
            <td class="px-4 py-2">{{ item.allocated_collateral }}</td>
            <td class="px-4 py-2">{{ item.coverage_ratio }}</td>
          </tr>
        </tbody>
      </table>
    </section>

    <Pagination v-if="allocations.links" :links="allocations.links" class="mt-4" />
  </app-layout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { defineProps } from 'vue'

const props = defineProps({ allocations: Object })
</script>
