<template>
  <app-layout title="Collateral Types" description="The kinds of collateral the register may hold, each with the haircut and the time to realise it used in the allocation">
    <template #actions>
      <button type="button" @click="openNew" class="primary-btn">New collateral type</button>
    </template>

    <div class="maiic-panel">
      <div class="maiic-table-wrap">
        <table class="maiic-table">
          <thead>
            <tr>
              <th>Code</th>
              <th>Name</th>
              <th>Description</th>
              <th class="num">Haircut %</th>
              <th class="num">Realisation (months)</th>
              <th class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="type in types?.data || []" :key="type.id">
              <td class="font-mono text-xs">{{ type.type_code }}</td>
              <td class="font-semibold text-gray-900">{{ type.type_name }}</td>
              <td>{{ type.description || '-' }}</td>
              <td class="num">{{ Number(type.standard_haircut).toFixed(2) }}</td>
              <td class="num">{{ type.realisation_period }}</td>
              <td class="w-px">
                <div class="flex items-center justify-end gap-1.5">
                  <button type="button" @click="editType(type)" class="maiic-action maiic-action-edit" title="Edit">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                  </button>
                  <button type="button" @click="deleteType(type)" class="maiic-action maiic-action-delete" title="Delete">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M10 11v6M14 11v6"/></svg>
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!(types?.data?.length > 0)">
              <td colspan="6" class="maiic-empty">No collateral types yet. Add one with New collateral type; the register import needs them.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <Pagination v-if="types?.links" :links="types.links"/>

    <!-- Add or edit -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-900/50" @click="closeModal"></div>
      <div class="relative w-full max-w-lg rounded-2xl bg-white shadow-2xl">
        <form @submit.prevent="submit">
          <div class="border-b border-gray-200 px-6 py-4">
            <h3 class="text-lg font-bold text-gray-900">{{ isEdit ? 'Edit collateral type' : 'New collateral type' }}</h3>
          </div>
          <div class="grid grid-cols-2 gap-4 px-6 py-5">
            <div>
              <label class="maiic-flabel" for="t-code">Code</label>
              <input id="t-code" v-model="form.type_code" class="maiic-input" required/>
              <p v-if="errors.type_code" class="mt-1 text-xs text-red-600">{{ errors.type_code }}</p>
            </div>
            <div>
              <label class="maiic-flabel" for="t-name">Name</label>
              <input id="t-name" v-model="form.type_name" class="maiic-input" required/>
              <p v-if="errors.type_name" class="mt-1 text-xs text-red-600">{{ errors.type_name }}</p>
            </div>
            <div>
              <label class="maiic-flabel" for="t-haircut">Haircut %</label>
              <input id="t-haircut" v-model="form.standard_haircut" type="number" step="0.01" min="0" max="100" class="maiic-input" required/>
              <p v-if="errors.standard_haircut" class="mt-1 text-xs text-red-600">{{ errors.standard_haircut }}</p>
            </div>
            <div>
              <label class="maiic-flabel" for="t-months">Realisation (months)</label>
              <input id="t-months" v-model="form.realisation_period" type="number" min="1" class="maiic-input" required/>
              <p v-if="errors.realisation_period" class="mt-1 text-xs text-red-600">{{ errors.realisation_period }}</p>
            </div>
            <div class="col-span-2">
              <label class="maiic-flabel" for="t-desc">Description</label>
              <textarea id="t-desc" v-model="form.description" rows="2" class="maiic-input"></textarea>
              <p v-if="errors.description" class="mt-1 text-xs text-red-600">{{ errors.description }}</p>
            </div>
          </div>
          <div class="flex justify-end gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4">
            <button type="button" @click="closeModal" class="secondary-btn">Cancel</button>
            <button type="submit" class="primary-btn" :disabled="saving">{{ saving ? 'Saving...' : (isEdit ? 'Save changes' : 'Add type') }}</button>
          </div>
        </form>
      </div>
    </div>
  </app-layout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { confirmDialog } from '@/Components/confirmDialog'

defineProps({
  types: {
    type: Object,
    default: () => ({ data: [], links: null }),
  },
})

const showModal = ref(false)
const isEdit = ref(false)
const saving = ref(false)
const errors = ref({})
const blank = () => ({ id: null, type_code: '', type_name: '', description: '', standard_haircut: 0, realisation_period: 1 })
const form = ref(blank())

function openNew() {
  form.value = blank()
  isEdit.value = false
  errors.value = {}
  showModal.value = true
}

function submit() {
  const url = isEdit.value ? route('collateral.types.update', form.value.id) : route('collateral.types.store')
  const method = isEdit.value ? 'put' : 'post'
  saving.value = true
  router[method](url, form.value, {
    preserveScroll: true,
    onSuccess: () => closeModal(),
    onError: (e) => { errors.value = e },
    onFinish: () => { saving.value = false },
  })
}

function editType(type) {
  form.value = { ...type }
  isEdit.value = true
  errors.value = {}
  showModal.value = true
}

async function deleteType(type) {
  if (!(await confirmDialog({
    title: 'Delete the ' + type.type_name + ' collateral type?',
    message: 'The type is removed from the list. This cannot be undone.',
    confirmLabel: 'Delete',
    tone: 'danger',
  }))) return
  router.delete(route('collateral.types.delete', type.id), { preserveScroll: true })
}

function closeModal() {
  showModal.value = false
}
</script>
