<template>
  <div class="maiic-panel">
    <div class="border-b border-gray-200 px-5 py-4">
      <h3 class="font-semibold text-gray-900">Transition profiles</h3>
      <p class="text-xs text-gray-500">{{ total }} profile(s). A profile says which table and column hold the grade at the start and end of each period.</p>
    </div>
    <div class="maiic-table-wrap overflow-x-auto">
      <table class="maiic-table">
        <thead>
          <tr>
            <th>Profile code</th>
            <th>Short name</th>
            <th>Start of period</th>
            <th>End of period</th>
            <th>Customer key</th>
            <th>Weighted by</th>
            <th>Created</th>
            <th class="text-right">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="profile in rows" :key="profile.id">
            <td class="font-semibold text-gray-900">{{ profile.profile_code }}</td>
            <td>{{ profile.short_name }}<div v-if="profile.description && profile.description !== profile.short_name" class="text-xs text-gray-500">{{ profile.description }}</div></td>
            <td><span class="font-mono text-xs">{{ profile.start_table }}.{{ profile.start_grading_col }}</span><div class="text-xs text-gray-500">{{ profile.start_value_type }}</div></td>
            <td><span class="font-mono text-xs">{{ profile.end_table }}.{{ profile.end_grading_col }}</span><div class="text-xs text-gray-500">{{ profile.end_value_type }}</div></td>
            <td class="font-mono text-xs">{{ profile.start_client_id_col || '-' }}</td>
            <td>{{ profile.aggregation_criteria || '-' }}</td>
            <td class="whitespace-nowrap">{{ formatDate(profile.created_at) }}</td>
            <td>
              <div class="flex justify-end gap-1.5">
                <button type="button" class="maiic-action maiic-action-edit" title="Edit profile" @click="editProfile(profile.id)"><font-awesome-icon icon="pen" /></button>
                <button type="button" class="maiic-action maiic-action-view" title="Configure the grade mapping" @click="configProfile(profile.id)"><font-awesome-icon icon="cog" /></button>
                <button type="button" class="maiic-action maiic-action-delete" title="Delete profile" @click="deleteProfile(profile)"><font-awesome-icon icon="trash" /></button>
              </div>
            </td>
          </tr>
          <tr v-if="!rows.length">
            <td colspan="8" class="maiic-empty">No transition profiles yet. Use <strong>Create profile</strong> at the top right to add one.</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-if="profiles && profiles.links && profiles.links.length > 3" class="border-t border-gray-100 px-4 pb-4">
      <Pagination :links="profiles.links" />
    </div>
  </div>
</template>

<script>
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { confirmDialog } from '@/Components/confirmDialog';

export default {
  props: {
    // Paginated profiles from the page (15 a page).
    profiles: { type: Object, default: () => ({ data: [], links: [] }) },
  },
  computed: {
    rows() { return (this.profiles && this.profiles.data) || [] },
    total() { return this.profiles?.total ?? this.rows.length },
  },
  methods: {
    formatDate(dateStr) {
      return dateStr ? String(dateStr).slice(0, 10) : '-';
    },
    editProfile(id) {
      router.get(`/transition-profiles/${id}/edit`);
    },
    configProfile(id) {
      router.get(`/transition-profiles/${id}/config`);
    },
    async deleteProfile(profile) {
      if (!(await confirmDialog({
        title: `Delete profile ${profile.profile_code}?`,
        message: 'The transition profile is removed. Matrices already built from it are kept. This cannot be undone.',
        confirmLabel: 'Delete',
        tone: 'danger',
      }))) return;
      // The endpoint answers JSON, so call it directly and reload the list.
      await axios.delete(`/transition-profiles/delete/${profile.id}`);
      router.reload({ only: ['profiles'] });
    },
  },
};
</script>
