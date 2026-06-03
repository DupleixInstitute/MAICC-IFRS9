<template>
  <div>
    <button
      type="button"
      class="px-4 py-2 bg-maiic-600 text-white text-sm font-medium rounded-md hover:bg-maiic-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-maiic-500"
      @click="$refs.file.click()"
    >
      <slot />
    </button>
    <input
      ref="file"
      type="file"
      class="hidden"
      :accept="accept"
      @input="$emit('input', $event.target.files[0])"
    />
  </div>
</template>

<script>
export default {
  props: {
    accept: {
      type: String,
      default: '',
    },
  },
  emits: ['input'],
}
</script>
