<template>
  <div v-if="show" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50">
    <div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-2xl">
      <h2 class="text-2xl font-semibold text-gray-800 mb-6">
        {{ form.id ? 'Edit' : 'Add' }} Macro Variable
      </h2>

      <form @submit.prevent="submitForm" class="space-y-6">
        <!-- fields -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block font-medium">Name</label>
            <input v-model="form.statistic_name" class="w-full border p-2 rounded" required />
          </div>
          <div>
            <label class="block font-medium">Code</label>
            <input v-model="form.statistic_code" class="w-full border p-2 rounded" required />
          </div>
          <div class="col-span-2">
            <label class="block font-medium">Description</label>
            <textarea v-model="form.statistic_description" class="w-full border p-2 rounded"></textarea>
          </div>
          <div>
            <label class="block font-medium">Unit</label>
            <input v-model="form.unit" class="w-full border p-2 rounded" />
          </div>
          <div>
            <label class="block font-medium">Frequency</label>
            <select v-model="form.frequency" class="w-full border p-2 rounded">
              <option value="">--</option>
              <option value="monthly">Monthly</option>
              <option value="quarterly">Quarterly</option>
              <option value="yearly">Yearly</option>
            </select>
          </div>
          <div>
            <label class="block font-medium">Data Source</label>
            <input v-model="form.data_source" class="w-full border p-2 rounded" />
          </div>
        </div>

        <div class="flex justify-end gap-3">
          <button type="button" @click="$emit('close')" class="bg-gray-200 px-4 py-2 rounded">
            Cancel
          </button>
          <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded">
            {{ form.id ? 'Update' : 'Save' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3'

const props = defineProps({
  show: Boolean,
  form: Object
})

const emit = defineEmits(['close'])

function submitForm() {
  const isUpdate = !!props.form.id

  const request = isUpdate
    ? router.put(route('macro-statistics.update', props.form.id), props.form)
    : router.post(route('macro-statistics.store'), props.form)

  request.then(() => {
    if (isUpdate) {
      // just close the modal and refresh the list
      router.reload({ only: ['statistics'] })
      emit('close')
    } else {
      // new record → go back to index
      router.visit(route('macro-statistics.index'))
    }
  })
}

</script>
