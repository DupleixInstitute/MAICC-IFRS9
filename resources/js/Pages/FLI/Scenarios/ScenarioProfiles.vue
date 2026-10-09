<template>
  <AppLayout>

    <template #header>
      <div>
        <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
          <span>Administration</span><span>/</span><span>Legacy FLI tools</span><span>/</span><span class="font-medium text-maiic-700">Scenario Profiles</span>
        </div>
        <h2 class="text-xl font-semibold text-gray-800">Scenario Profiles</h2>
        <p class="mt-1 text-sm text-gray-600">Named groups of economic scenarios (for example base, upside and downside) used by the weighted forecast</p>
      </div>
    </template>
    <template #actions>
      <button type="button" class="secondary-btn" @click="openScenarioForm()">Add scenario</button>
      <button type="button" class="primary-btn" @click="openProfileForm()">Create profile</button>
    </template>

    <LegacyNotice class="mb-4" />
    <div class="w-full space-y-4">
      <div class="maiic-panel">
        <div class="border-b border-gray-200 px-5 py-4">
          <h3 class="font-semibold text-gray-900">Profiles</h3>
          <p class="text-xs text-gray-500">{{ profiles.length }} profile(s). A profile is complete when its scenarios and weights are all in place.</p>
        </div>
        <div class="maiic-table-wrap overflow-x-auto">
          <table class="maiic-table">
            <thead>
              <tr><th>Code</th><th>Name</th><th>Created by</th><th>Status</th><th class="text-right">Actions</th></tr>
            </thead>
            <tbody>
              <tr v-for="profile in pagedProfiles" :key="profile.id">
                <td class="font-semibold text-gray-900">{{ profile.profile_code }}</td>
                <td>{{ profile.name }}</td>
                <td>{{ profile.created_by || '-' }}</td>
                <td><span class="maiic-badge" :class="profile.is_complete ? 'maiic-badge-green' : 'maiic-badge-gold'">{{ profile.is_complete ? 'Complete' : 'Incomplete' }}</span></td>
                <td>
                  <div class="flex justify-end gap-1.5">
                    <button type="button" class="maiic-action maiic-action-view" title="Open the profile's scenarios" @click="viewProfile(profile)"><font-awesome-icon icon="eye" /></button>
                  </div>
                </td>
              </tr>
              <tr v-if="!profiles.length">
                <td colspan="5" class="maiic-empty">No scenario profiles yet. Use <strong>Create profile</strong> at the top right, then add its scenarios.</td>
              </tr>
            </tbody>
          </table>
        </div>
        <RowPager v-model="page" :total="profiles.length" class="border-t border-gray-100" />
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
import { ref, computed } from 'vue'
import RowPager from '@/Components/Maiic/RowPager.vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import LegacyNotice from '@/Components/Maiic/LegacyNotice.vue'
import ProfileForm from './Components/ProfileForm.vue'
import ScenarioForm from './ScenarioForm.vue'

const props = defineProps({
  profiles: Array
})

const page = ref(1)
const pagedProfiles = computed(() => (props.profiles || []).slice((page.value - 1) * 15, page.value * 15))

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
  router.reload({ only: ['profiles'] })
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