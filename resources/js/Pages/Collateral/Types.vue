<template>
  <app-layout>
    <template #header>
      <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Collateral Types</h2>
        <button
          @click="showModal = true"
          class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"
        >
          + Add Type
        </button>
      </div>
    </template>

    <!-- Main Table Section -->
    <section class="bg-white p-4 rounded-lg shadow mt-4 overflow-x-auto">
      <table class="min-w-full border border-gray-200 rounded-lg text-sm">
        <thead class="bg-gray-100 text-gray-700 uppercase text-xs">
          <tr>
            <th class="px-4 py-3 text-left whitespace-nowrap">Code</th>
            <th class="px-4 py-3 text-left whitespace-nowrap">Name</th>
            <th class="px-4 py-3 text-left whitespace-nowrap">Haircut (%)</th>
            <th class="px-4 py-3 text-left whitespace-nowrap">Description</th>
            <th class="px-4 py-3 text-left whitespace-nowrap">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          <tr
            v-for="type in types"
            :key="type.id"
            class="hover:bg-gray-50 transition"
          >
            <td class="px-4 py-3 text-gray-700 font-medium">{{ type.type_code }}</td>
            <td class="px-4 py-3 text-gray-700">{{ type.type_name }}</td>
            <td class="px-4 py-3 text-gray-700">{{ type.standard_haircut }}</td>
            <td class="px-4 py-3 text-gray-600">{{ type.description }}</td>
            <td class="px-4 py-3 space-x-2 whitespace-nowrap">
              <button
                @click="editType(type)"
                class="text-blue-600 hover:text-blue-800 font-medium"
              >
                Edit
              </button>
              <button
                @click="deleteType(type.id)"
                class="text-red-600 hover:text-red-800 font-medium"
              >
                Delete
              </button>
            </td>
          </tr>
          <tr v-if="types.data && types.data.length === 0">
            <td colspan="5" class="text-center py-4 text-gray-500">
              No collateral types available.
            </td>
          </tr>
        </tbody>
      </table>
    </section>


    <!-- Pagination (Optional) -->
    <div v-if="types.links" class="mt-4">
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
          <input
            v-model="form.type_code"
            placeholder="Type Code"
            class="w-full border p-2 mb-2 rounded"
          />
          <input
            v-model="form.type_name"
            placeholder="Type Name"
            class="w-full border p-2 mb-2 rounded"
          />
          <input
            v-model="form.standard_haircut"
            type="number"
            placeholder="Haircut %"
            class="w-full border p-2 mb-2 rounded"
          />
          <textarea
            v-model="form.description"
            placeholder="Description"
            class="w-full border p-2 mb-2 rounded"
          ></textarea>

          <button
            class="bg-green-600 text-white px-4 py-2 rounded w-full hover:bg-green-700"
          >
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

const props = defineProps({
  types: Object, 
})

const showModal = ref(false)
const isEdit = ref(false)

const form = ref({
  id: null,
  type_code: '',
  type_name: '',
  description: '',
  standard_haircut: 0,
})

function resetForm() {
  form.value = { id: null, type_code: '', type_name: '', description: '', standard_haircut: 0 }
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
    router.delete(route('collateral-types.destroy', id))
  }
}

function closeModal() {
  showModal.value = false
}
</script>
