<template>
    <div class="p-6">
      <h2 class="text-lg font-medium text-gray-900 mb-4"> Weighted Calculation </h2>

      <form @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Start Period</label>
              <input 
                type="month"
                v-model="form.start_period" 
                class="w-full border rounded p-2"
                required
                :max="new Date().toLocaleDateString('en-CA', { year: 'numeric', month: '2-digit' }).slice(0, 7)"
              >
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">End Period</label>
              <input 
                type="month"
                v-model="form.end_period" 
                class="w-full border rounded p-2"
                required
                :max="new Date().toLocaleDateString('en-CA', { year: 'numeric', month: '2-digit' }).slice(0, 7)"
              >
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Scenario</label>
            <select v-model="form.scenario_profile_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                <option value="">Select Profile</option>
                    <option v-for="profile in profiles" :key="profile.id" :value="profile.id">
                    {{ profile.profile_code }} - {{ profile.name }} 
                    </option>
            </select>
          </div>
          </div>

        <div class="mt-6 flex justify-end space-x-3">
          <button 
            type="button" 
            @click="close"
            class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300"
          >
            Cancel
          </button>
     <button 
          type="submit" 
          class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
        >
          Process
        </button>

        </div>
      </form>
    </div>
</template>

<script setup>
import { useForm, router } from '@inertiajs/vue3'

const props = defineProps({
  profiles: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['close', 'saved'])

const form = useForm({
  start_period: '',
  end_period: '',
  scenario_profile_id: ''
})

function submit() {
  router.post('/macro-forecast-weighted/calculate', form, {
    onSuccess: () => {
      emit('saved')
      emit('close')
    }
  })
}
</script>
