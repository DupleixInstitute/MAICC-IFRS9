<script setup>
// In-page tabs in the system's tab style (as on EIR Data): green text and
// underline on the active tab, a count chip on each tab (green when active,
// grey otherwise). Sits at the top of a white panel, below the page header.
defineProps({
    tabs: { type: Array, default: () => [] }, // [{ key, label, count? }]
    modelValue: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue'])
const fmt = (n) => Number(n || 0).toLocaleString()
</script>

<template>
    <div class="border-b border-gray-200 px-5 pt-4">
        <nav class="flex gap-6 overflow-x-auto" role="tablist">
            <button v-for="tab in tabs" :key="tab.key" type="button" role="tab"
                    :aria-selected="modelValue === tab.key"
                    class="whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-semibold"
                    :class="modelValue === tab.key ? 'border-maiic-600 text-maiic-700' : 'border-transparent text-gray-500 hover:text-gray-800'"
                    @click="emit('update:modelValue', tab.key)">
                {{ tab.label }}
                <span v-if="tab.count !== undefined && tab.count !== null"
                      class="ml-1 rounded-full px-2 py-0.5 text-xs"
                      :class="modelValue === tab.key ? 'bg-maiic-100 text-maiic-700' : 'bg-gray-100 text-gray-600'">{{ fmt(tab.count) }}</span>
            </button>
        </nav>
    </div>
</template>
