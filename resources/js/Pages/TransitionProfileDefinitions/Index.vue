<template>
  <app-layout>
    <template #header>
            <div class="flex justify-between items-center">
              <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Transition Profile
                </h2>
                <p class="mt-1 text-sm text-gray-600">List of Profiles </p>
                
                </div>
                
        <Link :href="route('transition-profiles.create')" 
                class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring focus:ring-gray-300 disabled:opacity-25 transition">
              Create Profile
          </Link>
          </div>
    </template>

    <div>
      <p v-if="message" class="bg-green-500 text-white p-4 rounded">
        {{ message }}
      </p>
    </div>

    <div class="mt-6 mx-auto max-w-7xl">
      <transition-profile-data-table />
    </div>
    
    <HelpManual />
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import TransitionProfileDataTable from './Components/TransitionProfileDataTable.vue';
import HelpManual from '../../Components/HelpManual.vue';

export default {
  components: {
    AppLayout,
    TransitionProfileDataTable,
    HelpManual,
  },
  props: {
    message: String,
  },
  methods: {
    navigateToConfig(id) {
      this.$inertia.get(`/transition-profiles/${id}/config`);
    },
    handleDelete(id) {
      // Your delete logic here
    },
  },
};
</script>