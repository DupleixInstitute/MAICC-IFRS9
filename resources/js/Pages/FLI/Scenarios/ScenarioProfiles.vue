<template>
  <AppLayout>
    
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Scenario Profiles
      </h2>

    <div class="mb-4 flex justify-end space-x-4">
          <button @click="openProfileForm()"
          class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
            + Create New Profile
          </button>
          
            <button @click="openScenarioForm()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
          + Add Scenario
        </button>
      </div>
    </template>

    <div class="overflow-x-auto mt-6">
      <div class="bg-white shadow-md rounded-lg">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-200">
          <tr>
            <th class="py-2 px-4 border-b text-gray-600 text-center align-middle">Scenario Code</th>
            <th class="py-2 px-4 border-b text-gray-600 text-center align-middle">Scenario Name</th>
            <th class="py-2 px-4 border-b text-gray-600 text-center align-middle">Created By</th>
            <th class="py-2 px-4 border-b text-gray-600 text-center align-middle">Status</th>
            <th class="py-2 px-4 border-b text-gray-600 text-center align-middle">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="profile in profiles" :key="profile.id">
            <td class="py-2 px-4 border-b text-center align-middle">{{ profile.profile_code }}</td>
            <td class="py-2 px-4 border-b text-center align-middle">{{ profile.name }}</td>
            <td class="py-2 px-4 border-b text-center align-middle">{{ profile.created_by }}</td>
            <td class="py-2 px-4 border-b text-center align-middle">
              <span v-if="profile.is_complete" class="text-green-600 font-bold">✔</span>
              <span v-else class="text-red-600 font-bold">✘</span>
            </td>
            <td class="py-2 px-4 border-b text-center align-middle space-x-3">
              <button @click="viewProfile(profile)"
               class="text-green-600 hover:text-green-800">
                <i class="fas fa-eye"></i> 
              </button>
              <button class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-edit"></i>
              </button>
              <button class="text-red-600 hover:text-red-800">
                <i class="fas fa-trash"></i>
              </button>
            </td>
          </tr>
        </tbody>
      </table>

      </div>
    </div>

     <div v-if="showProfileForm" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-gray-500 bg-opacity-75" @click="closeForm"></div>
        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-lg mx-4">
          <ProfileForm
            :profile="currentProfile"
            @close="closeForm"
            @saved="reload"
          />
        </div>
      </div>

      <div v-if="showScenarioForm" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-gray-500 bg-opacity-75" @click="closeForm"></div>
        <div class="relative bg-white rounded-lg shadow-xl w-full max-w-lg mx-4">
          <ScenarioForm
            :scenario="currentScenario"
            :profiles="profiles" 
            @close="closeForm"
            @saved="reload"
          />
        </div>
      </div>

  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ProfileForm from './Components/ProfileForm.vue'
import ScenarioForm from './ScenarioForm.vue'

const props = defineProps({
  profiles: Array
})

const showProfileForm = ref(false)
const currentProfile = ref(null)

const showScenarioForm = ref(false)
const currentScenario = ref(null)

function openProfileForm(profile = null) {
  currentProfile.value = profile
  showProfileForm.value = true
}

function openScenarioForm() {
  currentScenario.value = null
  showScenarioForm.value = true
}


function reload() {
  // Logic to reload the profiles list
  Inertia.reload({ only: ['profiles'] })
}

function closeForm() {
  currentProfile.value = null
  showProfileForm.value = false
  currentScenario.value = null
  showScenarioForm.value = false
}

function editScenarioProfile(scenario) {
  currentScenario.value = scenario
  showScenarioForm.value = true
}

function viewProfile(profile) {
  router.get(route('scenarios.index', profile.id));
}


</script>