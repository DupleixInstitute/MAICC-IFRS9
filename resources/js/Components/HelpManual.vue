<template>
  <div>
    <button
      @click="showHelp = true"
      class="fixed bottom-6 right-6 bg-green-600 text-white p-3 rounded-full shadow-lg hover:bg-green-700 transition"
      title="Help"
    >
      <i class="fas fa-question"></i>
    </button>
    <!-- Help Modal -->
    <div v-if="showHelp" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white p-6 rounded-lg shadow-lg w-full max-w-3xl relative max-h-[80vh] overflow-y-auto">
        <h2 class="text-xl font-bold mb-4">{{ manual?.title || 'Help' }}</h2>
        <button
          @click="showHelp = false"
          class="absolute top-3 right-3 text-gray-500 hover:text-gray-700 text-xl"
        >
          <i class="fas fa-times"></i>
        </button>
        <div v-if="manual" class="prose max-w-full" v-html="manual.content"></div>
        <div v-else class="text-gray-500">No help manual found for this page.</div>
      </div>
    </div>
  </div>
</template>


<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { usePage } from '@inertiajs/vue3'
import '@fortawesome/fontawesome-free/css/all.css';

const showHelp = ref(false)
const manual = ref(null)

// Automatically detect current route or menu path
const page = usePage()
const currentRoute = page.props.route_name // Gets example 'expected-credit-loss.index'

// Fetch manual for current route
const fetchManual = async () => {
  try {
    const res = await axios.get(`/api/manuals/route/${encodeURIComponent(currentRoute)}`)
    manual.value = res.data
  } catch (error) {
    console.error('Help manual fetch error:', error)
    manual.value = null
  }
}


onMounted(fetchManual)
</script>

<style scoped>
.prose img {
  max-width: 100%;
  height: auto;
}
</style>
