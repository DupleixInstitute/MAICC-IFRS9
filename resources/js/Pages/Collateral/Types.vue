<template>
  <app-layout>
    <template #header>
      <div class="flex justify-between items-center">
        <div>
          <h2 class="font-semibold text-xl text-gray-800 leading-tight">Collateral Types</h2>
          <p class="mt-1 text-sm text-gray-600">Enter the collateral types that are to be used in allocation</p>
        </div>
        <button
          @click="showModal = true"
          class="bg-gradient-to-r from-green-700 to-green-700 hover:from-green-500 hover:to-green-500 text-white px-4 py-2 rounded hover:bg-green-700 flex items-center"
        >
          + Add Type
        </button>
      </div>
    </template>

    <!-- Main Table Section -->
    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
      <div class="p-6 bg-white border-b border-gray-200">
        <div class="overflow-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-100">
              <tr>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Haircut (%)</th>
                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Realisation Period (Months)</th>
                <th class="px-4 py-3 text-left whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="type in types?.data || []"
                :key="type.id"
                class="bg-white divide-y divide-gray-200"
              >
                <td class="px-3 py-4 whitespace-nowrap text-m text-gray-500">{{ type.type_code }}</td>
                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">{{ type.type_name }}</td>
                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">{{ type.standard_haircut }}</td>
                <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">{{ type.realisation_period }}</td>
                <td class="px-4 py-3 space-x-2 whitespace-nowrap">
                  <button
                    @click="editType(type)"
                    class="text-blue-600 hover:text-blue-800 transition-colors"
                    aria-label="Edit Type"
                  >
                    <i class="fas fa-edit"></i>
                  </button>
                  <button
                    @click="deleteType(type.id)"
                    class="text-red-600 hover:text-red-800 transition-colors"
                  >
                    <i class="fas fa-trash-alt"></i>
                  </button>
                </td>
              </tr>
              <tr v-if="!(types?.data?.length > 0)">
                <td colspan="5" class="text-center py-4 text-gray-500">
                  No collateral types available.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="types?.links" class="mt-4">
      <Pagination :links="types.links" />
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center">
      <div class="absolute inset-0 bg-gray-500 bg-opacity-75" @click="closeModal"></div>
      <div class="relative bg-white rounded-lg shadow-xl w-full max-w-lg mx-4 p-4">
        <h3 class="text-lg font-bold mb-4">
          {{ isEdit ? 'Edit Collateral Type' : 'Add Collateral Type' }}
        </h3>
        <form @submit.prevent="submit">
          <label class="block font-medium text-sm text-gray-700 mb-1">Type Code</label>
          <input v-model="form.type_code" placeholder="Type Code" class="w-full border p-2 mb-2 rounded" />
          
          <label class="block font-medium text-sm text-gray-700 mb-1">Type Name</label>
          <input v-model="form.type_name" placeholder="Type Name" class="w-full border p-2 mb-2 rounded" />
          
          <label class="block font-medium text-sm text-gray-700 mb-1">Standard Haircut (%)</label>
          <input v-model="form.standard_haircut" type="number" placeholder="Haircut %" class="w-full border p-2 mb-2 rounded" />
          
          <label class="block font-medium text-sm text-gray-700 mb-1">Realisation Period (Months)</label>
          <input v-model="form.realisation_period" type="number" placeholder="Realisation Period (Months)" class="w-full border p-2 mb-2 rounded" />
          
          <label class="block font-medium text-sm text-gray-700 mb-1">Description</label>
          <textarea v-model="form.description" placeholder="Description" class="w-full border p-2 mb-2 rounded"></textarea>

          <button class="bg-green-600 text-white px-4 py-2 rounded w-full hover:bg-green-700">
            {{ isEdit ? 'Update' : 'Submit' }}
          </button>
        </form>
      </div>
    </div>
  </app-layout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import '@fortawesome/fontawesome-free/css/all.min.css'

const props = defineProps({
  types: {
    type: Object,
    default: () => ({ data: [], links: null })
  }
})

const showModal = ref(false)
const isEdit = ref(false)
const form = ref({
  id: null,
  type_code: '',
  type_name: '',
  description: '',
  standard_haircut: 0,
  realisation_period: 1,
})

function resetForm() {
  form.value = { id: null, type_code: '', type_name: '', description: '', standard_haircut: 0, realisation_period: 1 }
  isEdit.value = false
}

function submit() {
  const url = isEdit.value
    ? route('collateral.types.update', form.value.id)
    : route('collateral.types.store')

  const method = isEdit.value ? 'put' : 'post'

  router[method](url, form.value, {
    preserveScroll: true,
    onSuccess: () => {
      closeModal()
      resetForm()
      router.reload({ only: ['types'] })
    },
  })
}

function editType(type) {
  form.value = { ...type }
  isEdit.value = true
  showModal.value = true
}

function deleteType(id) {
  if (confirm('Are you sure you want to delete this collateral type?')) {
    router.delete(route('collateral.types.delete', id))
  }
}

function closeModal() {
  showModal.value = false
}
</script>
