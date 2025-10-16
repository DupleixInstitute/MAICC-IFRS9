<template>
  <div class="relative">
    <div @click="toggle">
      <slot name="trigger" />
    </div>
    <div v-show="open" class="absolute z-20" :class="[placement === 'bottom-start' ? 'left-0' : 'right-0']">
      <slot />
    </div>
    <div v-show="open" class="fixed inset-0 z-10" @click="open = false"></div>
  </div>
</template>

<script>
import { ref } from 'vue'

export default {
  props: {
    placement: {
      type: String,
      default: 'bottom-end',
    },
  },
  setup() {
    const open = ref(false)

    const toggle = () => {
      open.value = !open.value
    }

    return {
      open,
      toggle,
    }
  },
}
</script>
