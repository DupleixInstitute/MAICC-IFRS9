<!-- ExpandableNotes.vue -->
<template>
  <td class="border-t px-4 py-4 notes-column">
    <div class="relative">
      <div
        class="text-sm"
        v-if="description"
      >
        <!-- Main description with expand/collapse -->
        <div>
          <div
            :class="{
              'line-clamp-2': !isExpanded,
              'text-gray-800': true,
              'mb-1': true
            }"
          >
            {{ description }}
          </div>

          <!-- Show more/less button -->
          <button
            v-if="needsTruncation"
            @click="isExpanded = !isExpanded"
            class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 transition-colors duration-150 text-sm font-medium"
            :aria-label="isExpanded ? 'Show less' : 'Show more'"
          >
            {{ isExpanded ? 'Show less' : 'Show more' }}
            <svg
              :class="[
                'w-4 h-4 transform transition-transform duration-200',
                isExpanded ? 'rotate-180' : ''
              ]"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M19 9l-7 7-7-7"
              />
            </svg>
          </button>
        </div>

        <!-- Previous description if available -->
        <div
          v-if="previousDescription"
          :class="[
            'mt-2 text-gray-500 transition-all duration-200',
            isExpanded ? 'opacity-100' : 'opacity-0 h-0 overflow-hidden'
          ]"
        >
          <div class="text-xs uppercase font-medium mb-1 text-gray-600">Previous Note:</div>
          <div class="italic">{{ previousDescription }}</div>
        </div>

        <!-- Word count indicator (optional) -->
        <div
          v-if="needsTruncation && !isExpanded"
          class="text-xs text-gray-400 mt-1"
        >

        </div>
      </div>

      <!-- Placeholder when no description -->
      <div v-else class="text-sm text-gray-400">
        No description
      </div>
    </div>
  </td>
</template>

<script>
export default {
  name: 'ExpandableNotes',
  props: {
    description: {
      type: String,
      default: ''
    },
    previousDescription: {
      type: String,
      default: ''
    }
  },
  data() {
    return {
      isExpanded: false
    }
  },
  computed: {
    needsTruncation() {
      return this.description && this.description.length > 10
    },
    wordCount() {
      return this.description ? this.description.trim().split(/\s+/).length : 0
    }
  },
  methods: {
    truncateText(text, length = 100) {
      if (!text || text.length <= length) return text
      return text.slice(0, length) + '...'
    }
  }
}
</script>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Smooth transition for expand/collapse */
.notes-column {
  transition: all 0.2s ease-in-out;
}

/* Ensure the button doesn't wrap awkwardly */
button {
  white-space: nowrap;
}
</style>
