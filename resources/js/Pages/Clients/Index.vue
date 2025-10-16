<template>
  <app-layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Clients
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="flex items-center justify-between mb-6">
          <search-filter v-model="form.search" class="w-full max-w-md mr-4" @reset="reset">
            <label class="block text-sm font-medium text-gray-700">Status</label>
            <select v-model="form.trashed" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
              <option :value="null" />
              <option value="with">With Trashed</option>
              <option value="only">Only Trashed</option>
            </select>
          </search-filter>

          <div class="flex items-center space-x-4">
            <jet-button
              @click="showImportModal = true"
              class="flex items-center hidden"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
              </svg>
              Import Names File
            </jet-button>

            <Link
              v-if="can.create"
              :href="route('clients.import.create')"
              class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
                Import Names File
            </Link>
              <Link
              v-if="can.create"
              :href="route('clients.create')"
              class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
              </svg>
              <span>Create Client</span>
            </Link>
          </div>
        </div>

        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
          <table class="min-w-full divide-y divide-gray-200 bg-white shadow-sm rounded-lg overflow-hidden">
            <thead>
              <tr class="bg-gray-50">
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                  Customer ID
                </th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                  Name
                </th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                  Phone
                </th>
                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                  Updated
                </th>
                <th scope="col" class="px-6 py-4 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
              <tr v-for="client in displayedClients"
                  :key="client.id"
                  class="hover:bg-gray-50 transition-colors duration-200 ease-in-out">
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm font-medium text-gray-900">{{ client.external_id }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-900">{{ client.name }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-600">{{ client.mobile }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <div class="text-sm text-gray-600">{{ client.updated_at }}</div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                  <inertia-link
                    :href="route('clients.edit', client.id)"
                    class="inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-150 ease-in-out"
                  >
                    <svg class="h-4 w-4 mr-1.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Edit
                  </inertia-link>
                </td>
              </tr>
              <tr v-if="displayedClients.length === 0">
                <td colspan="4" class="px-6 py-8 text-center">
                  <div class="text-gray-500 text-sm">
                    <svg class="mx-auto h-12 w-12 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <p class="mt-2 font-medium">No clients found</p>
                    <p class="mt-1">Get started by creating a new client.</p>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <pagination
          v-if="clients?.links"
          class="mt-6"
          :links="clients.links"
        />
      </div>
    </div>

    <import-modal
      :show="showImportModal"
      @close="closeImportModal"
      @file-selected="importNamesFile"
    />

    <!-- Processing Modal with Progress Bar -->
    <div v-if="processing || uploadProgress > 0" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center">
      <div class="bg-white p-8 rounded-lg shadow-xl max-w-lg w-full">
        <div class="space-y-6">
          <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
              <svg v-if="processing" class="animate-spin h-5 w-5 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <span class="text-gray-700 font-medium">{{ processing ? 'Processing file...' : 'Uploading file...' }}</span>
            </div>
            <div class="text-sm text-gray-500">{{ uploadProgress }}%</div>
          </div>

          <div class="relative pt-1">
            <div class="overflow-hidden h-2 text-xs flex rounded bg-gray-200">
              <div
                :style="{ width: uploadProgress + '%' }"
                class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-indigo-500 transition-all duration-300"
              ></div>
            </div>
          </div>

          <div class="text-sm text-gray-600 text-center">
            {{ processing ? 'Please wait while we process your file. This may take a few minutes for large files.' : 'Uploading your file...' }}
          </div>
        </div>
      </div>
    </div>

    <!-- Import Results Modal -->
    <div v-if="showImportResults" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center">
      <div class="bg-white p-8 rounded-lg shadow-xl max-w-2xl w-full">
        <div class="space-y-4">
          <div class="flex justify-between items-center">
            <h3 class="text-lg font-medium text-gray-900">Import Results</h3>
            <button @click="closeImportResults" class="text-gray-400 hover:text-gray-500">
              <span class="sr-only">Close</span>
              <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="grid grid-cols-3 gap-4 text-center">
            <div class="bg-gray-50 p-4 rounded">
              <div class="text-2xl font-bold text-gray-900">{{ importStats.total }}</div>
              <div class="text-sm text-gray-500">Total Records</div>
            </div>
            <div class="bg-green-50 p-4 rounded">
              <div class="text-2xl font-bold text-green-600">{{ importStats.processed }}</div>
              <div class="text-sm text-gray-500">Successful</div>
            </div>
            <div class="bg-red-50 p-4 rounded">
              <div class="text-2xl font-bold text-red-600">{{ importStats.failed }}</div>
              <div class="text-sm text-gray-500">Failed</div>
            </div>
          </div>

          <div v-if="importStats.failed > 0" class="mt-4">
            <h4 class="text-sm font-medium text-gray-900 mb-2">Errors</h4>
            <div class="bg-red-50 p-4 rounded text-sm text-red-600 max-h-40 overflow-y-auto">
              <div v-for="(error, index) in importErrors" :key="index">
                {{ error }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <HelpManual />
  </app-layout>
</template>

<script>
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import HelpManual from '../../Components/HelpManual.vue';
import SearchFilter from '@/Shared/SearchFilter.vue'
import Pagination from '@/Shared/Pagination.vue'
import ImportModal from '@/Components/ImportModal.vue'
import JetButton from '@/Jetstream/Button.vue'
import { router } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3'

export default {
  components: {
    AppLayout,
    SearchFilter,
    Pagination,
    ImportModal,
    JetButton,
    Link,
    HelpManual
  },

  props: {
    clients: {
      type: Object,
      default: () => ({}),
    },
    filters: Object,
    can: Object,
    importResults: Object,
  },

  setup(props) {
    const form = ref({
      search: props.filters.search,
      trashed: props.filters.trashed,
    })
    const showImportModal = ref(false)
    const showImportResults = ref(false)
    const processing = ref(false)
    const uploadProgress = ref(0)
    const importStats = ref({
      total: 0,
      processed: 0,
      failed: 0
    })
    const importErrors = ref([])

    const displayedClients = computed(() => {
      const existingClients = props.clients?.data || [];

      if (props.importResults?.records?.length) {
        // Filter out any existing clients that match the newly imported ones
        const newRecordIds = new Set(props.importResults.records.map(r => r.external_id));
        const filteredExisting = existingClients.filter(c => !newRecordIds.has(c.external_id));

        return [...props.importResults.records, ...filteredExisting];
      }
      return existingClients;
    });

    console.log('displayedClients', displayedClients.value);

    function reset() {
      form.value = {
        search: '',
        trashed: null,
      }
    }

    function closeImportModal() {
      showImportModal.value = false
      uploadProgress.value = 0
      processing.value = false
    }

    function closeImportResults() {
      showImportResults.value = false
      uploadProgress.value = 0
      processing.value = false
    }

    function importNamesFile(file) {
      if (!file) return

      processing.value = false // Start with upload progress first
      uploadProgress.value = 0
      showImportModal.value = false // Close the file selection modal immediately

      const formData = new FormData()
      formData.append('names_file', file)

      router.post(route('clients.import'), formData, {
        onProgress: (progress) => {
          if (progress.total) {
            uploadProgress.value = Math.round((progress.loaded / progress.total) * 100)
          }
        },
        onSuccess: (page) => {
          uploadProgress.value = 100
          processing.value = false

          if (page.props.importResults) {
            importStats.value = {
              total: page.props.importResults.total,
              processed: page.props.importResults.processed,
              failed: page.props.importResults.failed
            }
            importErrors.value = page.props.flash.error ? page.props.flash.error.split('\n') : []
            showImportResults.value = true
          }
        },
        onError: () => {
          processing.value = false
          uploadProgress.value = 0
        },
        preserveScroll: true,
      })
    }

    return {
      form,
      reset,
      importNamesFile,
      closeImportModal,
      closeImportResults,
      can: props.can,
      showImportModal,
      showImportResults,
      processing,
      uploadProgress,
      importStats,
      importErrors,
      displayedClients,
    }
  },
}
</script>

<style>
.btn-indigo {
  @apply px-6 py-3 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition;
}
</style>
