<template>
  <div class="p-6">
    <h2 class="text-lg font-medium text-gray-900 mb-4">
      {{ props.value ? 'Edit' : 'Add' }} Value for {{ props.statistic.statistic_name }}
    </h2>

    <form @submit.prevent="submit">
      <div class="grid grid-cols-1 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Period</label>
          <input 
            type="month"
            v-model="form.period" 
            class="w-full border rounded p-2"
            required
            :max="new Date().toISOString().slice(0, 7)"
          >
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Value</label>
          <input 
            type="number" 
            step="0.0001" 
            v-model="form.value" 
            class="w-full border rounded p-2"
            required
          >
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Profile</label>
          <select v-model="form.scenario_profile_id" class="w-full border rounded p-2">
            <option :value="null">-- Select Profile --</option>
            <option v-for="p in props.profiles" :key="p.id" :value="p.id">
              {{ p.name }}
            </option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Scenario</label>
          <select v-model="form.scenario_id" class="w-full border rounded p-2">
            <option :value="null">Select Scenario</option>
            <option v-for="s in availableScenarios" :key="s.id" :value="s.id">
              {{ s.name }}
            </option>
          </select>
        </div>

        <div>
          <label class="flex items-center">
            <input type="checkbox" v-model="form.is_forecast" class="rounded">
            <span class="ml-2 text-sm text-gray-700">Is Forecast</span>
          </label>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Source</label>
          <input 
            type="text" 
            v-model="form.source" 
            class="w-full border rounded p-2"
            placeholder="e.g. Central Bank, IMF"
          >
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
          <textarea 
            v-model="form.notes" 
            class="w-full border rounded p-2"
            rows="3"
          ></textarea>
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
          {{ props.value ? 'Update' : 'Save' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  show: Boolean,
  statistic: Object,
  profiles: Array,
  value: Object
});

const emit = defineEmits(['close', 'saved']);

const form = ref({
  period: '',
  value: '',
  scenario_profile_id: null,
  scenario_id: null,
  is_forecast: false,
  source: '',
  notes: ''
});

// Watch for changes to props.value and populate form
watch(() => props.value, (newValue) => {
  if (newValue) {
    Object.assign(form.value, {
      period: newValue.period?.substring(0, 7) || '',
      value: newValue.value ?? '',
      scenario_profile_id: newValue.scenario?.profile_id ?? null,
      scenario_id: newValue.scenario_id ?? null,
      is_forecast: Boolean(newValue.is_forecast),
      source: newValue.source ?? '',
      notes: newValue.notes ?? ''
    });
  } else {
    resetForm();
  }
}, { immediate: true });

const availableScenarios = computed(() => {
  const profile = props.profiles.find(p => p.id === form.value.scenario_profile_id);
  return profile?.scenarios ?? [];
});

function submit() {
  const url = props.value 
    ? `/macro-statistics/values/${props.value.id}`  // update
    : `/macro-statistics/${props.statistic.id}/values`; // create

  const method = props.value ? 'put' : 'post';

  router[method](url, {
    ...form.value,
    statistic_id: props.statistic.id,
  }, {
    onSuccess: () => {
      emit('saved');
      close();
    },
    onError: (errors) => {
      console.error('Submission failed:', errors);
    }
  });
}

function close() {
  emit('close');
}

function resetForm() {
  form.value = {
    period: '',
    value: '',
    scenario_profile_id: null,
    scenario_id: null,
    is_forecast: false,
    source: '',
    notes: ''
  };
}
</script>
