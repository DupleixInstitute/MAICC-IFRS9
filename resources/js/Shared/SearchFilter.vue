<template>
  <div class="flex items-center">
    <div class="flex w-full bg-white shadow rounded">
      <dropdown class="px-4 md:px-6 rounded-l border-r hover:bg-gray-100 focus:border-white focus:ring focus:z-10" placement="bottom-start">
        <template #trigger>
          <div class="flex items-center">
            <span class="text-gray-700 hidden md:inline">Filter</span>
            <icon class="w-4 h-4 ml-2 text-gray-700" name="cheveron-down" />
          </div>
        </template>
        <div class="mt-2 px-4 py-6 w-screen shadow-xl bg-white rounded-lg md:w-72">
          <slot />
        </div>
      </dropdown>
      <input
        v-model="query"
        class="relative w-full px-6 py-3 rounded-r focus:ring-2 focus:ring-indigo-400"
        autocomplete="off"
        type="text"
        name="search"
        placeholder="Search..."
      />
    </div>
    <button v-if="query" class="ml-3 text-sm text-gray-500 hover:text-gray-700 focus:text-indigo-500" type="button" @click="reset">
      Reset
    </button>
  </div>
</template>

<script>
import { ref, watch } from 'vue'
import Dropdown from '@/Shared/Dropdown.vue'
import Icon from '@/Shared/Icon.vue'

export default {
  components: {
    Dropdown,
    Icon,
  },
  props: {
    modelValue: String,
  },
  emits: ['update:modelValue', 'reset'],
  setup(props, { emit }) {
    const query = ref(props.modelValue)

    watch(query, value => {
      emit('update:modelValue', value)
    })

    const reset = () => {
      query.value = ''
      emit('reset')
    }

    return { query, reset }
  },
}
</script>
