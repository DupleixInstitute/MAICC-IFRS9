<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4" @click.self="$emit('close')">
    <div class="bg-white rounded p-6 w-full max-w-7xl max-h-[95vh] overflow-y-auto">
      <slot></slot>
    </div>
  </div>
</template>
