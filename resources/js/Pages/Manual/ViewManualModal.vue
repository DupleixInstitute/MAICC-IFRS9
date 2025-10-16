<template>
  <div
    v-if="manual"
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
  >
    <div
      class="bg-white p-6 rounded-lg shadow-lg max-w-3xl w-full relative max-h-[80vh] overflow-y-auto"
    >
      <button
        @click="$emit('close')"
        class="absolute top-3 right-3 text-gray-500 hover:text-gray-700 text-xl"
        title="Close"
      >
        &times;
      </button>

      <h2 class="text-2xl font-bold mb-2">{{ manual.title }}</h2>
      <p class="text-sm text-gray-500 mb-4">
        Route: <code>{{ manual.route_title }}</code>
      </p>
      <div class="prose max-w-none" v-html="manual.content"></div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  manual: {
    type: Object,
    required: false,
    default: null,
  },
})

defineEmits(['close'])
</script>

<style scoped>
.prose img {
  max-width: 100%;
  height: auto;
}
</style>
