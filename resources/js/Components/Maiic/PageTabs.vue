<template>
    <!-- In-page tabs, the same look as Eir/Data.vue and the layout's section
         tabs: white panel, green active tab with a green underline, and a
         chip with the real number of records (green on the active tab). -->
    <div class="rounded-xl border border-gray-200 bg-white px-5 pt-4 shadow-sm" :class="flush ? 'rounded-b-none border-b-0 shadow-none' : ''">
        <nav class="flex gap-6 overflow-x-auto" aria-label="Page tabs">
            <button v-for="tab in tabs" :key="tab.key" type="button"
                    class="whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-semibold transition-colors duration-150"
                    :class="modelValue === tab.key ? 'border-maiic-600 text-maiic-700' : 'border-transparent text-gray-500 hover:text-gray-800'"
                    :aria-current="modelValue === tab.key ? 'page' : null"
                    @click="$emit('update:modelValue', tab.key)">
                {{ tab.label }}
                <span v-if="tab.count !== null && tab.count !== undefined"
                      class="ml-1 rounded-full px-2 py-0.5 text-xs"
                      :class="modelValue === tab.key ? 'bg-maiic-100 text-maiic-800' : 'bg-gray-100 text-gray-600'">{{ Number(tab.count).toLocaleString() }}</span>
            </button>
        </nav>
    </div>
</template>

<script setup>
defineProps({
    // [{ key, label, count? }]
    tabs: { type: Array, required: true },
    modelValue: { type: String, required: true },
    flush: { type: Boolean, default: false },
});
defineEmits(['update:modelValue']);
</script>
