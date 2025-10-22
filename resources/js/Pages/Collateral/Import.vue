<template>
  <app-layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">Import Collateral Register</h2>
    </template>

    <section class="bg-white p-4 rounded-lg shadow mt-4">
      <form @submit.prevent="submit" enctype="multipart/form-data">
        <input type="file" @change="handleFile" class="border p-2 mb-4 w-full rounded" />
        <button class="bg-green-600 text-white px-4 py-2 rounded">Upload</button>
      </form>
    </section>
  </app-layout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const file = ref(null)

function handleFile(e) {
  file.value = e.target.files[0]
}

function submit() {
  const formData = new FormData()
  formData.append('file', file.value)
  router.post('/collateral/import-register', formData)
}
</script>
