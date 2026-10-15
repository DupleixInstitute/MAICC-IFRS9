<template>
  <app-layout>
    <div class="p-6 space-y-6">
  <!-- Header -->
  <div class="flex justify-between items-center">
    <!-- Title & Profile Info -->
    <div>
      <h1 class="text-2xl font-bold">Scenarios</h1>
      <p class="text-gray-700">
        <strong class="text-gray-900">Profile Code:</strong> {{ profile.profile_code }}
      </p>
      <p class="text-gray-700">
        <strong class="text-gray-900">Profile Name:</strong> {{ profile.name }}
      </p>
    </div>
    <!-- Button -->
    <div>
        <Link href="/scenario-profiles" class="text-sm font-medium text-maiic-700 hover:underline">&larr; Back to scenario profiles</Link>
    </div>
  </div>
      <!-- Scenario List -->
      <section class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Probability</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tags</th>
              <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr v-for="s in pagedScenarios" :key="s.id">
              <td class="px-6 py-4 whitespace-nowrap font-medium">{{ s.name }}</td>
              <td class="px-6 py-4 whitespace-nowrap">{{ s.probability }}%</td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  class="px-2 py-1 rounded-full text-xs font-semibold"
                  :class="s.is_base_case ? 'bg-maiic-200 text-maiic-800' : 'bg-gray-100 text-gray-800'"
                >
                  {{ s.is_base_case ? 'Base Case' : 'Alternative' }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span v-if="s.tags" class="text-sm text-gray-600">
                  {{ s.tags.join(', ') }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right space-x-2">
                <button @click="editScenario(s)" class="text-maiic-600 hover:text-maiic-800"><i class="fas fa-edit"></i></button>
                <button @click="editScenario(s)" class="text-maiic-600 hover:text-maiic-800"><i class="fas fa-eye"></i></button>
                <button @click="deleteScenario(s.id)" class="text-red-600 hover:text-red-800"><i class="fas fa-trash"></i></button>
              </td>
            </tr>
          </tbody>
        </table>
        <RowPager v-model="page" :total="(scenarios || []).length" class="border-t border-gray-100" />
      </section>

      <!-- Modal Form -->
      <div v-if="showScenarioForm" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-gray-500 bg-opacity-75" @click="closeForm"></div>
        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-lg mx-4">
          <ScenarioForm
            :scenario="currentScenario"
            :profile="profile"
            :profiles="profiles"
            @close="closeForm"
            @saved="reload"
          />
        </div>
      </div>
    </div>
  </app-layout>
</template>

<script setup>
import { confirmDialog } from '@/Components/confirmDialog'
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ScenarioForm from './ScenarioForm.vue'
import RowPager from '@/Components/Maiic/RowPager.vue'
import { Inertia } from '@inertiajs/inertia'
import '@fortawesome/fontawesome-free/css/all.min.css'

const props = defineProps({
  scenarios: Array,
  profile: Object,
  profiles: Array
})

// 15 rows a page
const page = ref(1)
const pagedScenarios = computed(() => (props.scenarios || []).slice((page.value - 1) * 15, page.value * 15))

const showScenarioForm = ref(false)
const currentScenario = ref(null)

function openForm() {
  currentScenario.value = null
  showScenarioForm.value = true
}

function editScenario(scenario) {
  currentScenario.value = scenario
  showScenarioForm.value = true
}

function closeForm() {
  currentScenario.value = null
  showScenarioForm.value = false
}

function reload() {
  if (currentProfile.value) {
    router.get(route('scenarios.index', currentProfile.value.id), {
      preserveScroll: true,
      preserveState: true,
    })
  }
}


async function deleteScenario(id) {
  if (await confirmDialog({ title: 'Deleting this will remove macro data related to this scenario. Are you sure you want to delete this scenario?', confirmLabel: 'Delete', tone: 'danger' })) {
    router.delete(route('scenarios.destroy', id), {
      onSuccess: reload
    })
  }
}
</script>
