<template>
  <jet-dialog-modal :show="show" @close="closeModal">
    <template #title>
      Import Names File Instructions
    </template>

    <template #content>
      <div class="space-y-6">
        <div class="text-sm text-gray-600">
          <p class="mb-4">The Names File should be a CSV file with the following format:</p>
          <div class="bg-gray-50 p-4 rounded-md">
            <p class="font-mono text-xs">Column 1: Customer ID</p>
            <p class="font-mono text-xs">Column 2: Public Name (format: PHONE-NAME)</p>
          </div>
        </div>

        <div class="mt-4">
          <h4 class="text-sm font-medium text-gray-900">Example:</h4>
          <div class="mt-2 bg-gray-50 p-4 rounded-md">
            <pre class="text-xs font-mono">
1001,0774892762-John Doe
1002,0774892763-Jane Smith</pre>
          </div>
        </div>

        <div class="mt-4">
          <h4 class="text-sm font-medium text-gray-900">Important Notes:</h4>
          <ul class="mt-2 text-sm text-gray-600 list-disc list-inside">
            <li>The file must be in CSV format</li>
            <li>Public Name column should have phone number first, followed by a dash (-), then the name</li>
            <li>Phone numbers should be in the format 07XXXXXXXX</li>
          </ul>
        </div>

        <div class="mt-6 border-t border-gray-200 pt-6">
          <h4 class="text-sm font-medium text-gray-900 mb-4">Upload Names File</h4>

          <div class="flex items-center justify-center w-full">
            <label class="flex flex-col w-full h-32 border-4 border-dashed hover:bg-gray-100 hover:border-gray-300">
              <div class="relative flex flex-col items-center justify-center pt-7">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 text-gray-400 group-hover:text-gray-600" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
                </svg>
                <p class="pt-1 text-sm tracking-wider text-gray-400 group-hover:text-gray-600">
                  {{ fileName || 'Select a file' }}
                </p>
              </div>
              <input
                type="file"
                class="opacity-0"
                accept=".csv,.txt"
                @change="handleFileSelect"
              />
            </label>
          </div>
        </div>

        <div class="mt-4">
          <jet-button @click="downloadSample" class="w-full justify-center">
            Download Sample File
          </jet-button>
        </div>
      </div>
    </template>

    <template #footer>
      <div class="flex justify-between w-full">
        <jet-secondary-button @click="closeModal">
          Cancel
        </jet-secondary-button>
        <jet-button
          @click="importFile"
          :disabled="!selectedFile"
          :class="{ 'opacity-25': !selectedFile }"
        >
          Import File
        </jet-button>
      </div>
    </template>
  </jet-dialog-modal>
</template>

<script>
import { defineComponent, ref } from 'vue'
import JetDialogModal from '@/Jetstream/DialogModal.vue'
import JetSecondaryButton from '@/Jetstream/SecondaryButton.vue'
import JetButton from '@/Jetstream/Button.vue'

export default defineComponent({
  components: {
    JetDialogModal,
    JetSecondaryButton,
    JetButton,
  },

  props: {
    show: {
      type: Boolean,
      default: false,
    },
  },

  emits: ['close', 'file-selected'],

  setup(props, { emit }) {
    const selectedFile = ref(null)
    const fileName = ref('')

    const closeModal = () => {
      selectedFile.value = null
      fileName.value = ''
      emit('close')
    }

    const handleFileSelect = (event) => {
      const file = event.target.files[0]
      if (file) {
        selectedFile.value = file
        fileName.value = file.name
      }
    }

    const importFile = () => {
      if (selectedFile.value) {
        emit('file-selected', selectedFile.value)
      }
    }

    const downloadSample = () => {
      const sampleData = 'Customer ID,Public Name\n1001,0774892762-John Doe\n1002,0774892763-Jane Smith'
      const blob = new Blob([sampleData], { type: 'text/csv' })
      const url = window.URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = 'sample_names_file.csv'
      a.click()
      window.URL.revokeObjectURL(url)
    }

    return {
      closeModal,
      downloadSample,
      handleFileSelect,
      importFile,
      selectedFile,
      fileName,
    }
  },
})
</script>
