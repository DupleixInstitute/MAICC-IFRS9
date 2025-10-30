<template>
  <app-layout>
    <template #header>
      <div class="flex justify-between items-center">
      <div>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('collateral.allocations.index')">
          Collateral
        </inertia-link>
        <span class="text-indigo-400 font-medium">/</span> Import
      </h2>
       <p class="mt-1 text-sm text-gray-600">Select the file with collaterals (registry of all the collateral)</p>
    </div>

      <div class="flex space-x-4">
          <button
              @click="downloadSample()"
             class="inline-flex items-center px-4 py-2 border border-gray-800 rounded-md shadow-sm text-sm font-medium text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-900 transition-all duration-200"
          >
              <svg class="-ml-1 mr-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
              </svg>
              Download Sample File
          </button>
      </div>
      </div>
    </template>

    <div class="mx-auto">
      <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">
        <form @submit.prevent="submit" enctype="multipart/form-data">
          <div class="space-y-6">
               <div>
                  <label class="block text-sm font-medium text-gray-700">Period</label>
                  <input type="month" v-model="form.registration_date" required
                          class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                  <p class="mt-1 text-xs text-gray-500">Select the month and year for the collateral register</p>
              </div>
            <!-- File Upload -->
            <div class="mt-6 border-t border-gray-200 pt-6">
              <h4 class="text-lg font-medium text-gray-900 mb-4 text-align-center justify-center">Upload File</h4>
              <div class="flex items-center justify-center w-full">
                <label class="flex flex-col w-full h-32 border-4 border-dashed hover:bg-gray-100 hover:border-gray-300">
                  <div class="relative flex flex-col items-center justify-center pt-7">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-gray-400 group-hover:text-gray-600" viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"/>
                    </svg>
                    <p class="pt-1 text-sm tracking-wider text-gray-400 group-hoAver:text-gray-600">
                          {{ fileName || 'Select a CSV file to upload' }}
                    </p>
                  </div>
                  <input type="file" class="opacity-0" accept=".csv,.txt" @change="handleFileSelect" />
                </label>
              </div>
            </div>
          </div>

          <!-- Buttons -->
          <div class="flex items-center justify-end mt-6">
            <Link :href="route('collateral.allocations.index')" class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 active:bg-gray-500 focus:outline-none focus:border-gray-500 focus:ring focus:ring-gray-300 disabled:opacity-25 transition mr-2">
              Cancel
            </Link>
              <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:border-indigo-700 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition"
              >
                Import
              </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Processing Modal -->
    <div v-if="form.processing || uploadProgress > 0" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center">
      <div class="bg-white p-8 rounded-lg shadow-xl max-w-lg w-full">
        <div class="space-y-6">
          <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
              <svg v-if="form.processing" class="animate-spin h-5 w-5 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span class="text-gray-700 font-medium">{{ form.processing ? 'Processing file...' : 'Uploading file...' }}</span>
            </div>
            <div class="text-sm text-gray-500">{{ uploadProgress }}%</div>
          </div>

          <div class="relative pt-1">
            <div class="overflow-hidden h-2 text-xs flex rounded bg-gray-200">
              <div :style="{ width: uploadProgress + '%' }" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-indigo-500 transition-all duration-300"></div>
            </div>
          </div>

          <div class="text-sm text-gray-600 text-center">
            {{ form.processing ? 'Please wait while we process your file. This may take a few minutes for large files.' : 'Uploading your file...' }}
          </div>
        </div>
      </div>
    </div>
  </app-layout>
</template>


<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import '@fortawesome/fontawesome-free/css/all.min.css'

const fileName = ref('')

const file = ref(null)
const uploadProgress = ref(0)
const form = ref({
  registration_date: '',
  processing: false,
})


function handleFileSelect(e) {
  const selectedFile = e.target.files[0]
  file.value = selectedFile || null
  fileName.value = selectedFile?.name || ''
}

function downloadSample() {
  window.location.href = route('collateral.register.sample')
}
function submit() {
  if (!file.value) {
    alert('Please select a file before submitting.')
    return
  }

  if (!form.value.registration_date) {
    alert('Please select a registration date before submitting.')
    return
  }

  form.value.processing = true
  const formData = new FormData()
  formData.append('file', file.value)
  formData.append('registration_date', form.value.registration_date) // ✅ Add this line

  router.post('/collateral/register/import', formData, {
    onProgress: (progress) => {
      uploadProgress.value = Math.round((progress.loaded / progress.total) * 100)
    },
    onFinish: () => {
      form.value.processing = false
      uploadProgress.value = 0
    },
    onError: () => {
      form.value.processing = false
    }
  })
}
</script>
