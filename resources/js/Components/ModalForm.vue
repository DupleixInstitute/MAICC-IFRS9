<template>
  <div v-if="show" class="fixed inset-0 z-50 bg-black bg-opacity-30 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg">
      <div class="p-4 border-b flex justify-between items-center">
        <h3 class="text-lg font-bold">{{ title }}</h3>
        <button @click="$emit('close')" class="text-gray-500 hover:text-black">✖</button>
      </div>
      <div class="p-4">
        <slot />
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  show: Boolean,
  title: String
})
</script>
