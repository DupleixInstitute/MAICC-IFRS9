<template>
    <!-- Client-side twin of Pagination.vue, for lists the page already holds
         in full: 15 rows a page, numbered pages, the current page in MAIIC
         green, Previous / Next at the ends. Hidden on one page. -->
    <nav v-if="pages > 1" class="flex flex-wrap items-center gap-1" aria-label="Pages">
        <button type="button" :disabled="modelValue <= 1" :class="cls(false, modelValue <= 1)" @click="go(modelValue - 1)">&laquo; Previous</button>
        <template v-for="(p, i) in numbers" :key="i">
            <span v-if="p === null" class="px-2 text-sm text-gray-400">...</span>
            <button v-else type="button" :class="cls(p === modelValue, false)" @click="go(p)">{{ p }}</button>
        </template>
        <button type="button" :disabled="modelValue >= pages" :class="cls(false, modelValue >= pages)" @click="go(modelValue + 1)">Next &raquo;</button>
        <span class="ml-2 text-xs text-gray-500">{{ from }} to {{ to }} of {{ total.toLocaleString('en-GB') }}</span>
    </nav>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    total: { type: Number, default: 0 },
    modelValue: { type: Number, default: 1 },
    perPage: { type: Number, default: 15 },
});
const emit = defineEmits(['update:modelValue']);

const pages = computed(() => Math.max(1, Math.ceil(props.total / props.perPage)));
const from = computed(() => (props.total ? (props.modelValue - 1) * props.perPage + 1 : 0));
const to = computed(() => Math.min(props.total, props.modelValue * props.perPage));
const numbers = computed(() => {
    const n = pages.value, c = props.modelValue, out = [];
    for (let p = 1; p <= n; p++) {
        if (p === 1 || p === n || Math.abs(p - c) <= 2) out.push(p);
        else if (out[out.length - 1] !== null) out.push(null);
    }
    return out;
});

function go(p) {
    if (p >= 1 && p <= pages.value) emit('update:modelValue', p);
}
function cls(active, disabled) {
    if (disabled) return 'rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-400 cursor-default';
    return 'rounded-lg border px-3 py-1.5 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-maiic-300 '
        + (active ? 'border-maiic-600 bg-maiic-600 text-white' : 'border-gray-200 bg-white text-gray-700 hover:border-maiic-300 hover:bg-maiic-50 hover:text-maiic-800');
}
</script>
