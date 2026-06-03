<template>
  <app-layout>
    <!-- HEADER -->
    <template #header>
      <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800">
          {{ profile.name }} — Internal Grades
        </h2>

        <button
          v-if="!profile.is_active"
          @click="openCreate"
          class="bg-maiic-600 hover:bg-maiic-700 text-white px-4 py-2 rounded-lg shadow"
        >
          + Add Grade
        </button>
      </div>
    </template>

    <div class="py-10 max-w-7xl mx-auto sm:px-6 lg:px-8">

      <!-- INFO -->
      <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
        <div class="text-sm text-blue-800">
         <h3><strong>Profile Name:</strong> {{ profile.name }} </h3>
         <h4>
          <strong>Max Tenor:</strong> {{ maxTenor }} years |
          <strong>Total Grades:</strong> {{ grades.length }}
          </h4>
        </div>
      </div>

      <!-- EMPTY -->
      <div
        v-if="grades.length === 0"
        class="py-16 bg-white rounded-lg shadow text-center text-sm text-gray-600"
      >
        No grades defined
      </div>

      <!-- GRADES -->
      <div v-else class="space-y-4">
        <div
          v-for="grade in grades"
          :key="grade.id"
          class="border rounded-lg shadow-sm bg-white"
        >
          <div class="px-4 py-3 bg-gray-50 flex justify-between items-center">
            <div>
              <h3 class="font-semibold text-m">
                {{ grade.grade_code }} — {{ grade.grade_name }}
              </h3>
              <p class="text-xs text-gray-500">
                Click to view PD curve
              </p>
            </div>

            <div class="flex gap-3 items-center">
              <button
                v-if="!profile.is_active"
                @click.stop="openEdit(grade)"
                class="text-sm text-indigo-600 hover:underline"
                label="Edit Grade"
              >
               <i class="fas fa-edit "></i>
              </button>

              <button @click="toggleGrade(grade.id)">
                <svg
                  class="w-5 h-5 transition-transform"
                  :class="{ 'rotate-180': openGrades[grade.id] }"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 9l-7 7-7-7" />
                </svg>
              </button>
            </div>
          </div>

          <!-- PD CURVE (READ-ONLY) -->
          <div v-if="openGrades[grade.id]" class="p-4 grid grid-cols-2 sm:grid-cols-5 gap-3">
            <div
              v-for="y in maxTenor"
              :key="y"
              class="bg-gray-50 rounded-md p-3 text-center"
            >
              <p class="text-xs text-gray-500">Year {{ y }}</p>
              <p class="font-mono text-sm">
                {{ findPd(grade, y) }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODAL -->
    <AddGradeModal
      :show="showModal"
      :profile="profile"
      :grade="editGrade"
      @close="closeModal"
      @saved="reload"
    />
  </app-layout>
</template>

<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import AddGradeModal from './AddGrade.vue'
import '@fortawesome/fontawesome-free/css/all.min.css'

const props = defineProps({
  profile: Object,
  grades: Array,
})

const showModal = ref(false)
const editGrade = ref(null)
const openGrades = ref({})

const maxTenor = computed(() => props.profile.max_tenor_years)

function toggleGrade(id) {
  openGrades.value[id] = !openGrades.value[id]
}

function openCreate() {
  editGrade.value = null
  showModal.value = true
}

function openEdit(grade) {
  editGrade.value = grade
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  editGrade.value = null
}

function reload() {
  window.location.reload()
}

function findPd(grade, year) {
  return grade.tenor_pds.find(p => p.tenor_years === year)?.pd_probability ?? '—'
}
</script>
