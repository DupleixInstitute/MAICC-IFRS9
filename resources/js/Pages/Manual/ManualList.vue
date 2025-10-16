<template>
  <app-layout>
    <template #header>
      <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Manuals Management</h2>
        <Link :href="route('manuals.create')"
          class="inline-flex items-center bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg shadow-md transition duration-300 mt-2">
          Add New Manual
          <i class="fas fa-plus ml-2"></i>  
        </Link>
      </div>
    </template>

    <div class="overflow-x-auto mt-6">
      <div class="bg-white shadow-md rounded-lg">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-200">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Route</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-if="loading">
              <td colspan="3" class="px-6 py-4 text-center text-gray-500">Loading manuals...</td>
            </tr>
            <tr v-else-if="manuals.length === 0">
              <td colspan="3" class="px-6 py-4 text-center text-gray-500">No manuals found.</td>
            </tr>
            <tr v-for="manual in manuals" :key="manual.id">
              <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{ manual.title }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{ manual.route_title }}</td>
              <td class="px-6 py-4 whitespace-nowrap flex space-x-2">

                 <button
                  @click="viewManual(manual)"
                  class="text-green-600 hover:text-green-800"
                  title="View Manual"
                >
                  <font-awesome-icon :icon="['fas', 'eye']" class="w-8 h-8" />
                </button>
                <Link
                  :href="route('manuals.edit', manual.id)"
                  class="text-blue-600 hover:text-blue-800"
                  title="Edit Manual"
                >
                  <font-awesome-icon :icon="['fas', 'pen']" class="w-8 h-8" />
                </Link>
                <button @click="deleteManual(manual)" class="text-red-600 hover:text-red-800" title="Delete Manual">
                  <font-awesome-icon :icon="['fas', 'trash']" class="w-8 h-8" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

      <ViewManualModal
        v-if="selectedManual"
        :manual="selectedManual"
        @close="closeModal"
      />
  </app-layout>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue'
import '@fortawesome/fontawesome-free/css/all.css';
import ViewManualModal from './ViewManualModal.vue'


const props = defineProps({
  manuals: Array
})

const loading = ref(false) // no need to load via axios now

// Use props.manuals directly instead of fetching
const manuals = ref(props.manuals)

const selectedManual = ref(null)

const viewManual = (manual) => {
  selectedManual.value = manual
}

const closeModal = () => {
  selectedManual.value = null
}

const deleteManual = (manual) => {
  if (confirm('Are you sure you want to delete this manual?')) {
    router.delete(route('manuals.delete', manual));
  }
}

</script>

