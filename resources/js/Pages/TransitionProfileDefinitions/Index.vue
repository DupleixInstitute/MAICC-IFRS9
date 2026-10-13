<template>
  <app-layout>
    <template #header>
      <div>
        <h2 class="text-xl font-semibold text-gray-800">Transition Profiles</h2>
        <p class="mt-1 text-sm text-gray-600">Where the grades come from when the monthly transition matrices are built</p>
      </div>
    </template>
    <template #actions>
      <Link :href="route('transition-profiles.create')" class="primary-btn">Create profile</Link>
    </template>

    <div class="w-full space-y-4">
      <transition-profile-data-table :profiles="profiles" />
    </div>

    <HelpManual />
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import TransitionProfileDataTable from './Components/TransitionProfileDataTable.vue';
import HelpManual from '../../Components/HelpManual.vue';

export default {
  components: {
    AppLayout,
    Link,
    TransitionProfileDataTable,
    HelpManual,
  },
  props: {
    profiles: { type: Object, default: () => ({ data: [], links: [] }) },
  },
};
</script>
