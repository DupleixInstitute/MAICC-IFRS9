<template>
  <div class="p-6">
    <h2 class="text-xl font-bold mb-4">{{ scenario ? 'Edit' : 'Add' }} Scenario</h2>

    <form @submit.prevent="submit" class="space-y-4">
      <div>
          <label class="block font-medium">Profile</label>
          <select v-model="form.profile_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
              <option value="">Select Profile</option>
                <option v-for="profile in profiles" :key="profile.id" :value="profile.id">
                 {{ profile.profile_code }} - {{ profile.name }} 
                </option>
          </select>
      </div>
      <div>
        <label class="block font-medium">Name</label>
        <input v-model="form.name" type="text" class="w-full border p-2 rounded" required />
      </div>

      <div>
        <label class="block font-medium">Description</label>
        <textarea v-model="form.description" class="w-full border p-2 rounded"></textarea>
      </div>

      <div>
        <label class="block font-medium">Probability (%)</label>
        <input v-model="form.probability" type="number" class="w-full border p-2 rounded" min="0" max="100" required />
      </div>

      <div class="flex items-center">
        <input type="checkbox" v-model="form.is_base_case" id="base-case" />
        <label for="base-case" class="ml-2">Is Base Case</label>
      </div>

      <div>
        <label class="block font-medium">Tags (comma-separated)</label>
        <input type="text" v-model="tagInput" @blur="parseTags" class="w-full border p-2 rounded" />
      </div>

      <div class="flex justify-end space-x-2 mt-6">
        <button type="button" @click="$emit('close')" class="px-4 py-2 border rounded">Cancel</button>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Save</button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { reactive, watch, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  scenario: Object,
  profiles: Array
})


const emit = defineEmits(['close', 'saved'])

const tagInput = ref('')
const form = useForm({
  profile_id: '',
  name: '',
  description: '',
  probability: '',
  is_base_case: false,
  tags: []
})

watch(() => props.scenario, (val) => {
  if (val) {
    form.profile_id = val.profile_id
    form.name = val.name
    form.description = val.description
    form.probability = val.probability
    form.is_base_case = val.is_base_case
    form.tags = val.tags || []
    tagInput.value = (val.tags || []).join(', ')
  } else {
    form.reset()
    tagInput.value = ''
  }
}, { immediate: true })

function parseTags() {
  form.tags = tagInput.value.split(',').map(t => t.trim()).filter(Boolean)
}

function submit() {
  parseTags()

  const routeName = props.scenario ? 'scenarios.update' : 'scenarios.store'
  const routeParams = props.scenario ? [props.scenario.id] : []

  form.submit(props.scenario ? 'put' : 'post', route(routeName, routeParams), {
    onSuccess: () => {
      emit('close')
      emit('saved')
    }
  })
}
</script>
