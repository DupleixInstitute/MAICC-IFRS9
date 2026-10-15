<template>
    <!-- In-page tabs, the same look as Eir/Data.vue and the layout's section
         tabs: white panel, green active tab with a green underline, and a
         chip with the real number of records (green on the active tab).
         One row per page (navigation review of 15 October 2026): a page under
         a section's tab row has no second row; a page with several views is
         its own menu entry and shows them here. With `query`, the open tab is
         kept in the address (?tab=...), so a reload or a shared link opens it. -->
    <div class="rounded-xl border border-gray-200 bg-white px-5 pt-4 shadow-sm" :class="flush ? 'rounded-b-none border-b-0 shadow-none' : ''">
        <nav class="flex flex-wrap gap-x-6 gap-y-1" aria-label="Page tabs">
            <button v-for="tab in tabs" :key="tab.key" type="button"
                    class="whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-semibold transition-colors duration-150"
                    :class="modelValue === tab.key ? 'border-maiic-600 text-maiic-700' : 'border-transparent text-gray-500 hover:text-gray-800'"
                    :aria-current="modelValue === tab.key ? 'page' : null"
                    @click="choose(tab.key)">
                {{ tab.label }}
                <span v-if="tab.count !== null && tab.count !== undefined"
                      class="ml-1 rounded-full px-2 py-0.5 text-xs"
                      :class="modelValue === tab.key ? 'bg-maiic-100 text-maiic-800' : 'bg-gray-100 text-gray-600'">{{ Number(tab.count).toLocaleString() }}</span>
            </button>
        </nav>
    </div>
</template>

<script setup>
import { onMounted } from 'vue'

const props = defineProps({
    // [{ key, label, count? }]
    tabs: { type: Array, required: true },
    modelValue: { type: String, required: true },
    flush: { type: Boolean, default: false },
    // the query-string key that keeps the open tab, e.g. 'tab'; none keeps it in the page only
    query: { type: String, default: null },
});
const emit = defineEmits(['update:modelValue']);

function choose(key) {
    emit('update:modelValue', key);
    if (!props.query || typeof window === 'undefined') return;
    const url = new URL(window.location.href);
    if (key === props.tabs[0]?.key) url.searchParams.delete(props.query);
    else url.searchParams.set(props.query, key);
    // keep Inertia's page state; only the address changes
    window.history.replaceState(window.history.state, '', url.toString());
}

onMounted(() => {
    if (!props.query || typeof window === 'undefined') return;
    const wanted = new URL(window.location.href).searchParams.get(props.query);
    if (wanted && wanted !== props.modelValue && props.tabs.some(t => t.key === wanted)) emit('update:modelValue', wanted);
});
</script>
