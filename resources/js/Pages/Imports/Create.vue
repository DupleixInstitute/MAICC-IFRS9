<template>
  <AppLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Create Import Template
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
          <form @submit.prevent="submit">
            <!-- Template Basic Info -->
            <div class="grid grid-cols-1 gap-6 mb-8">
              <div>
                <label class="block text-sm font-medium text-gray-700">Template Name</label>
                <input type="text" v-model="form.template_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Description</label>
                <textarea v-model="form.template_description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
              </div>

              <div>
                <label class="block text-sm font-medium text-gray-700">Source Table</label>
                <select v-model="form.source_table_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                  <option v-for="table in tables" :key="table" :value="table">{{ table }}</option>
                </select>
              </div>
            </div>

            <!-- Configuration Columns -->
            <div class="mb-6">
              <h3 class="text-lg font-medium mb-4">Column Configurations</h3>
              <div class="space-y-4">
                <div v-for="(config, index) in form.configurations" :key="index" class="grid grid-cols-6 gap-4 p-4 border rounded-lg">
                  <div>
                    <label class="block text-sm font-medium text-gray-700">Position</label>
                    <input type="number" v-model="config.position" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <input type="text" v-model="config.description" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700">Data Type</label>
                    <select v-model="config.data_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                      <option value="string">String</option>
                      <option value="integer">Integer</option>
                      <option value="decimal">Decimal</option>
                      <option value="date">Date</option>
                      <option value="datetime">DateTime</option>
                      <option value="boolean">Boolean</option>
                    </select>
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700">Min Value</label>
                    <input type="number" v-model="config.minimum_value" 
                           :disabled="!['integer', 'decimal'].includes(config.data_type)"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                  </div>

                  <div>
                    <label class="block text-sm font-medium text-gray-700">Max Value</label>
                    <input type="number" v-model="config.maximum_value"
                           :disabled="!['integer', 'decimal'].includes(config.data_type)"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                  </div>

                  <div class="space-y-2">
                    <div>
                      <label class="inline-flex items-center">
                        <input type="checkbox" v-model="config.is_reporting_period" class="rounded border-gray-300 text-indigo-600 shadow-sm">
                        <span class="ml-2 text-sm text-gray-600">Is Reporting Period</span>
                      </label>
                    </div>
                    <div>
                      <label class="inline-flex items-center">
                        <input type="checkbox" v-model="config.is_portfolio_group_id" class="rounded border-gray-300 text-indigo-600 shadow-sm">
                        <span class="ml-2 text-sm text-gray-600">Is Portfolio Group</span>
                      </label>
                    </div>
                    <button type="button" @click="removeConfiguration(index)" 
                            class="text-red-600 hover:text-red-900">
                      Remove
                    </button>
                  </div>
                </div>
              </div>

              <button type="button" @click="addConfiguration" 
                      class="mt-4 inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                Add Column
              </button>
            </div>

            <div class="flex justify-end space-x-3">
              <Link :href="route('custom_imports.index')" 
                    class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                Cancel
              </Link>
              <button type="submit" 
                      class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest bg-gray-800 hover:bg-gray-700">
                Create Template
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { defineProps, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  tables: {
    type: Array,
    required: true
  }
});

const form = ref({
  template_name: '',
  template_description: '',
  source_table_name: '',
  configurations: []
});

const addConfiguration = () => {
  form.value.configurations.push({
    position: form.value.configurations.length + 1,
    description: '',
    data_type: 'string',
    minimum_value: null,
    maximum_value: null,
    is_reporting_period: false,
    is_portfolio_group_id: false
  });
};

const removeConfiguration = (index) => {
  form.value.configurations.splice(index, 1);
  // Reorder positions
  form.value.configurations.forEach((config, idx) => {
    config.position = idx + 1;
  });
};

const submit = () => {
  router.post(route('custom_imports.store'), form.value);
};
</script>
