<template>
  <AppLayout>
    <!-- Use header slot like in Scenario Profiles -->
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Macro Element Definitions
      </h2>

      <div class="mb-4 flex justify-end">
        <button
          @click="openForm()"
          class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
        >
          + Add Variable
        </button>
      </div>
    </template>

    <!-- Table -->
    <div class="overflow-x-auto mt-6">
      <div class="bg-white shadow-md rounded-lg">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-200">
            <tr>
              <th class="py-2 px-4 text-gray-600 text-center">Name</th>
              <th class="py-2 px-4 text-gray-600 text-center">Code</th>
              <th class="py-2 px-4 text-gray-600 text-center">Frequency</th>
              <th class="py-2 px-4 text-gray-600 text-center">Data Source</th>
              <th class="py-2 px-4 text-gray-600 text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="stat in statistics" :key="stat.id" class="border-b hover:bg-gray-50">
              <td class="py-2 px-4 text-center">{{ stat.statistic_name }}</td>
              <td class="py-2 px-4 text-center">{{ stat.statistic_code }}</td>
              <td class="py-2 px-4 text-center">
                {{ stat.frequency ?? stat.periodic_interval }}
              </td>
              <td class="py-2 px-4 text-center">{{ stat.data_source }}</td>
              <td class="py-2 px-4 text-center space-x-3">
                <button
                  @click="openForm(stat)"
                  class="px-2 py-1 text-green-400 hover:text-green-600 transition-colors"
                  title="Edit Variable"
                >
                  <i class="fas fa-pencil"></i>
                </button>
                <button
                  @click="viewValues(stat.id)"
                  class="px-2 py-1 text-indigo-400 hover:text-indigo-600 transition-colors"
                  title="Go to Values"
                >
                  <i class="fas fa-tasks"></i>
                </button>
                <button
                  @click="deleteStat(stat.id)"
                  class="px-2 py-1 text-red-400 hover:text-red-600 transition-colors"
                  title="Delete Variable"
                >
                  <i class="fas fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal -->
   <Create
      :show="showForm"
      :form="form"
      @close="closeForm"
      @saved="reload"
   />
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import '@fortawesome/fontawesome-free/css/all.css';
import AppLayout from '@/Layouts/AppLayout.vue'
import Create from './Create.vue'

const props = defineProps({
  statistics: Array,
})

const showForm = ref(false)
const form = ref({})

function openForm(stat = null) {
  form.value = stat ? { ...stat } : {}
  showForm.value = true
}

function closeForm() {
  form.value = {}
  showForm.value = false
}

function reload() {
  router.route('macro-statistics.index')
}

function deleteStat(id) {
  if (confirm('Are you sure you want to delete this?')) {
    router.delete(route('macro-statistics.destroy', id)).then(() => reload())
  }
}

function viewValues(statId) {
  router.get(route('macro-values.index', statId))
}
</script>
