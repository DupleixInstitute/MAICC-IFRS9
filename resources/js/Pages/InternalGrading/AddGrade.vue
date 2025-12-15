<template>
  <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6">
      
      <!-- Header -->
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-lg font-medium text-gray-900">Add Internal Grade</h2>
        <button @click="$emit('close')" class="text-gray-500 hover:text-gray-700">&times;</button>
      </div>

      <!-- Form -->
      <div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700">Grade Code *</label>
          <input v-model="form.grade_code" type="text"
            class="mt-1 block w-full border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
        </div>

      <div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700">Grade Name *</label>
          <input v-model="form.grade_name" type="text"
            class="mt-1 block w-full border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
        </div>

        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700">Probabilities per Year</label>
          <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mt-2">
            <div v-for="year in maxTenor" :key="year">
              <label class="block text-xs text-gray-600 mb-1">Year {{ year }}</label>
              <input type="number" step="0.01" min="0" max="1"
                v-model.number="form.probabilities[year-1]"
                class="block w-full border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" />
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end space-x-3 mt-6">
          <button @click="resetForm" type="button"
            class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
            Reset
          </button>
          <button @click="saveGrade" :disabled="processing" type="button"
            class="px-4 py-2 rounded-md bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50">
            <span v-if="processing" class="animate-spin mr-2 h-4 w-4 border-2 border-white border-t-transparent rounded-full"></span>
            Save Grade
          </button>
        </div>
      </div>
    </div>
  </div>
  </div>

</template>

<script setup>
import { ref, computed, watch, reactive } from 'vue'
import { router } from '@inertiajs/vue3'

// Props
const props = defineProps({
  show: Boolean,
  profile: Object
})

// Emits
const emit = defineEmits(['close', 'saved'])

// Form data
const form = reactive({
  grade_code: '',
  grade_name: '',
  probabilities: []
})

const processing = ref(false)

// Maximum tenor from profile
const maxTenor = computed(() => props.profile?.max_tenor_years || 10)

// Initialize probabilities when modal opens or maxTenor changes
const initializeProbabilities = () => {
  form.probabilities = Array(maxTenor.value).fill(0)
}

watch(() => props.show, (val) => {
  if (val) initializeProbabilities()
}, { immediate: true })

// Reset form
function resetForm() {
  form.grade_code = ''
  form.grade_name = ''
  initializeProbabilities()
}

// Save grade using Inertia router post (like Manual Forecast page)
function saveGrade() {
  processing.value = true

  // Map probabilities to tenor_pds
  const payload = {
    grade_code: form.grade_code,
    grade_name: form.grade_name,
    tenor_pds: form.probabilities.map((pd, index) => ({
      tenor_years: index + 1,
      pd_probability: pd
    })),
  }

  router.post(route('internal-grading.grade.store', { profile: props.profile.id }), payload, {
    onSuccess: () => {
      emit('saved')
      emit('close')
      resetForm()
      processing.value = false
    },
    onError: () => {
      processing.value = false
    }
  })
}

</script>
