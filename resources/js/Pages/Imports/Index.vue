<template>
  <AppLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        General Imports
      </h2>
    </template>

      <HelpManual />

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
          <div class="flex justify-between mb-6">
            <h3 class="text-lg font-medium">Import Templates</h3>
            <Link 
            :href="route('custom_imports.create')" 
                  class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
              Create Template
            </Link>
          </div>

          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead>
                <tr>
                  <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Template Name</th>
                  <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                  <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source Table</th>
                  <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Import Count</th>
                  <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                  <th class="px-6 py-3 bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-for="template in templates" :key="template.id">
                  <td class="px-6 py-4 whitespace-nowrap">{{ template.template_name }}</td>
                  <td class="px-6 py-4">{{ template.template_description }}</td>
                  <td class="px-6 py-4">{{ template.source_table_name }}</td>
                  <td class="px-6 py-4">{{ template.import_count }}</td>
                  <td class="px-6 py-4">
                    <span :class="{
                      'px-2 py-1 text-xs rounded-full': true,
                      'bg-green-100 text-green-800': template.active_status === 1,
                      'bg-red-100 text-red-800': template.active_status === 0,
                      'bg-gray-100 text-gray-800': template.active_status === 2
                    }">
                      {{ getStatusText(template.active_status) }}
                    </span>
                  </td>
                  <td class="px-6 py-4">
                    <div class="flex space-x-2">
                      <!-- <Link :href="route('imports.process', template.id)" 
                            class="text-indigo-600 hover:text-indigo-900">
                        Import
                      </Link> -->
                      <button @click="downloadSample(template.id)" 
                              class="text-green-600 hover:text-green-900">
                        Sample
                      </button>
                      <!-- <Link :href="route('imports.edit', template.id)" 
                            class="text-blue-600 hover:text-blue-900">
                        Edit
                      </Link> -->
                      <button @click="toggleStatus(template)" 
                              class="text-yellow-600 hover:text-yellow-900">
                        {{ template.active_status === 1 ? 'Deactivate' : 'Activate' }}
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpManual from '../../Components/HelpManual.vue';
import { defineProps } from 'vue';

const props = defineProps({
  templates: {
    type: Array,
    required: true
  }
});

const getStatusText = (status) => {
  switch (status) {
    case 1: return 'Active';
    case 0: return 'Inactive';
    case 2: return 'Deleted';
    default: return 'Unknown';
  }
};

const downloadSample = async (templateId) => {
  try {
    const response = await axios.get(route('custom_imports.sample', templateId), {
      responseType: 'blob'
    });
    
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `sample_template_${templateId}.xlsx`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  } catch (error) {
    console.error('Error downloading sample:', error);
  }
};

const toggleStatus = async (template) => {
  try {
    await axios.patch(`/imports/${template.id}/toggle-status`);
    window.location.reload();
  } catch (error) {
    console.error('Error toggling status:', error);
  }
};
</script>
