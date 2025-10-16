<template>
  <app-layout>
    <template #header>
      <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          {{ manual ? 'Edit Manual' : 'Create Manual' }}
        </h2>

        <Link href="/manuals/list" class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-400 text-black-700 rounded-md">
            Back
        </Link>
      </div>
    </template>

    <div class="bg-white shadow-md rounded-lg p-6 mt-6 max-w-2xl mx-auto">
      <form @submit.prevent="submitForm" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700">Title</label>
          <input v-model="form.title" type="text" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required />
        </div>

        <div>
          <select v-model="form.route" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" required>
            <option disabled value="">-- Select Route --</option>
            <option v-for="option in routeOptions" :key="option.route" :value="option.route">
              {{ option.title }} ({{ option.route }})
            </option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700">Content</label>
          <RichTextEditor v-model="form.content" />
        </div>

        <div class="flex justify-end gap-4">
          <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
            {{ manual ? 'Update Manual' : 'Create Manual' }}
          </button>
          <button type="button" @click="emit('cancel')" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">
            Cancel
          </button>
        </div>
      </form>
    </div>
  </app-layout>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import RichTextEditor from '@/Components/RichTextEditor.vue'
import AppLayout from '@/Layouts/AppLayout.vue';
import JetButton from '@/Jetstream/Button.vue';
import JetLabel from '@/Jetstream/Label.vue';

// Props and emits
const props = defineProps({
  manual: {
    type: Object,
    default: null
  }
})
const emit = defineEmits(['submitted', 'cancel'])

// Reactive state
const routeOptions = ref([])
const form = reactive({
  title: '',
  route: '',
  content: ''
})

// Prefill if editing
onMounted(async () => {
  if (props.manual) {
    form.title = props.manual.title
    form.route = props.manual.route
    form.content = props.manual.content
  }

  try {
    const response = await axios.get('/api/manuals/routes')
    routeOptions.value = response.data
  } catch (error) {
    console.error('Error fetching routes:', error)
  }
})

// Form submit
const submitForm = () => {
  if (props.manual) {
    router.put(route('manuals.update', props.manual.id), form, {
      onSuccess: () => route('manuals.index'),
      onError: (errors) => console.error(errors)
      
    })
  } else {
    router.post(route('manuals.store'), form, {
      onSuccess: () => route('manuals.index'),
      onError: (errors) => console.error(errors)
    })
  }
}
</script>
