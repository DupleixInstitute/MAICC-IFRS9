<template>
  <app-layout title="Page help text" description="Short help shown by the ? button on a particular screen. The User and Administrator manuals are under System Documentation; a screen without its own help text opens the manual article for it.">
        <template #actions>
            <Link :href="route('manuals.create')" class="primary-btn"><i class="fas fa-plus"></i> Add page help</Link>
        </template>

    <div class="maiic-panel">
      <div class="maiic-table-wrap">
        <table class="maiic-table">
          <thead>
            <tr>
              <th>Title</th>
              <th>Route</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading">
              <td colspan="3" class="px-6 py-4 text-center text-gray-500">Loading manuals...</td>
            </tr>
            <tr v-else-if="manuals.length === 0">
              <td colspan="3" class="px-6 py-4 text-center text-gray-500">No screen has its own help text yet: the ? button on every screen opens the matching manual article.</td>
            </tr>
            <tr v-for="manual in manualRows" :key="manual.id">
              <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{ manual.title }}</td>
              <td class="px-6 py-4 whitespace-nowrap text-gray-600">{{ manual.route_title }}</td>
              <td class="px-6 py-4 whitespace-nowrap flex space-x-2">

                 <button
                  @click="viewManual(manual)"
                  class="text-maiic-600 hover:text-maiic-800"
                  title="View Manual"
                >
                  <font-awesome-icon :icon="['fas', 'eye']" class="w-8 h-8" />
                </button>
                <Link
                  :href="route('manuals.edit', manual.id)"
                  class="text-maiic-600 hover:text-maiic-800"
                  title="Edit Manual"
                >
                  <font-awesome-icon :icon="['fas', 'pen']" class="w-8 h-8" />
                </Link>
                <button @click="deleteManual(manual)" class="text-red-600 hover:text-red-800" title="Delete Manual">
                  <font-awesome-icon :icon="['fas', 'trash']" class="w-8 h-8" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <RowPager v-model="manualPage" :total="manuals.length" class="border-t border-gray-100" />
    </div>

      <ViewManualModal
        v-if="selectedManual"
        :manual="selectedManual"
        @close="closeModal"
      />
  </app-layout>
</template>

<script setup>
import { reactive, ref, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue'
import '@fortawesome/fontawesome-free/css/all.css';
import ViewManualModal from './ViewManualModal.vue'
import { confirmDialog } from '@/Components/confirmDialog'
import RowPager from '@/Components/Maiic/RowPager.vue'


const props = defineProps({
  manuals: Array
})

const loading = ref(false) // no need to load via axios now

// Use props.manuals directly instead of fetching
const manuals = ref(props.manuals)

// 15 page help texts a page; a shorter list never leaves the page past the end
const manualPage = ref(1)
const manualRows = computed(() => (manuals.value || []).slice((manualPage.value - 1) * 15, manualPage.value * 15))
watch(() => (manuals.value || []).length, (n) => {
  const last = Math.max(1, Math.ceil(n / 15))
  if (manualPage.value > last) manualPage.value = last
})

const selectedManual = ref(null)

const viewManual = (manual) => {
  selectedManual.value = manual
}

const closeModal = () => {
  selectedManual.value = null
}

const deleteManual = async (manual) => {
  if (await confirmDialog({ title: 'Delete this manual?', message: 'The manual is removed for everyone. This cannot be undone.', confirmLabel: 'Delete', tone: 'danger' })) {
    router.delete(route('manuals.delete', manual));
  }
}

</script>

