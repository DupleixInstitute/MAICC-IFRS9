<template>
  <app-layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Transition Profiles
      </h2>
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