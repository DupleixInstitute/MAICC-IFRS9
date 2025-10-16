<template>
  <div class="p-6">
    <h2 class="text-xl font-bold mb-4">{{ profile ? 'Edit' : 'Add' }} Profile</h2>

    <form @submit.prevent="submit" class="space-y-4">
      <div>
        <label class="block font-medium">Profile Code</label>
        <input v-model="form.profile_code" type="text" class="w-full border p-2 rounded" required />
      </div>
      
      <div>
        <label class="block font-medium">Name</label>
        <input v-model="form.name" type="text" class="w-full border p-2 rounded" required />
      </div>

      <div>
        <label class="block font-medium">Description</label>
        <textarea v-model="form.description" class="w-full border p-2 rounded"></textarea>
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
  profile: Object
})

const emit = defineEmits(['close', 'saved'])

const tagInput = ref('')
const form = useForm({
  profile_code: '',
  name: '',
  description: '',
})

watch(() => props.profile, (val) => {
  if (val) {
    form.name = val.name
    form.description = val.description
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

  if (props.profile && props.profile.id) {
    form.submit('put', route('scenarios.profiles.update', { profile: props.profile.id }), {
      onSuccess: () => {
        emit('close')
        emit('saved')
      }
    })
  } else {
    form.submit('post', route('scenarios.profiles.store'), {
      onSuccess: () => {
        emit('close')
        emit('saved')
      }
    })
  }
}

</script>

