<template>
  <div class="overflow-x-auto mt-6">
    <div class="bg-white shadow-md rounded-lg">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-200">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Id</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Profile Code</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Short Name</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Table</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Column</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Start Col Type</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Table</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Column</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">End Col Type</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created On</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <tr
            v-for="profile in profiles"
            :key="profile.id"
            class="hover:bg-gray-50 transition duration-200"
          >
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{ profile.id }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{ profile.profile_code }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{ profile.short_name }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{ profile.start_table }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{ profile.start_grading_col }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{ profile.start_value_type }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{ profile.end_table }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{ profile.end_grading_col }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{ profile.end_value_type }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{ formatDate(profile.created_at) }}</td>
            <td class="px-6 py-4 whitespace-nowrap text-gray-600 flex space-x-2">
              <button @click="editProfile(profile.id)" class="text-blue-600 hover:text-blue-800">
                <i class="fas fa-pencil"></i>
              </button>
              <button @click="configProfile(profile.id)" class="text-green-600 hover:text-green-800">
                <i class="fas fa-cog"></i>
              </button>
              <button @click="deleteProfile(profile.id)" class="text-red-600 hover:text-red-800">
                <i class="fas fa-trash"></i>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import '@fortawesome/fontawesome-free/css/all.css';


export default {
  setup() {
    const profiles = ref([]);

    const fetchProfiles = async () => {
      try {
        const response = await axios.get('/api/transition-profiles');
        profiles.value = response.data.data || response.data; // adjust depending on your API
      } catch (error) {
        console.error('Failed to fetch profiles:', error);
      }
    };

    const formatDate = (dateStr) => {
      const date = new Date(dateStr);
      return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
      });
    };

    const editProfile = (id) => {
      router.get(`/transition-profiles/${id}/edit`);
    };

    const configProfile = (id) => {
      router.get(`/transition-profiles/${id}/config`);
    };

    const deleteProfile = (id) => {
      if (confirm('Are you sure you want to delete this profile?')) {
        router.delete(`/transition-profiles/delete/${id}`);
      }
    };

    onMounted(fetchProfiles);

    return {
      profiles,
      formatDate,
      editProfile,
      configProfile,
      deleteProfile,
    };
  },
};
</script>