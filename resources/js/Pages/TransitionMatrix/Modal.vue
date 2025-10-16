<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click.self="$emit('close')">
    <div class="bg-white rounded p-6 w-full max-w-3xl">
      <slot></slot>
    </div>
  </div>
</template>
