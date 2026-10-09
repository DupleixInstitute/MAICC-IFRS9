<template>
    <!-- Client-side pager for lists the server sends whole: 15 rows a page,
         numbered pages styled like Components/Pagination.vue. -->
    <nav v-if="pages > 1" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3" aria-label="Pages">
        <span class="text-xs text-gray-500">Showing {{ from }} to {{ to }} of {{ total.toLocaleString() }}</span>
        <div class="flex flex-wrap items-center gap-1">
            <button type="button" :disabled="modelValue <= 1" @click="go(modelValue - 1)" :class="cls(false, modelValue <= 1)">&laquo; Previous</button>
            <template v-for="(p, i) in numbers" :key="i">
                <span v-if="p === '...'" class="px-2 text-sm text-gray-400">...</span>
                <button v-else type="button" @click="go(p)" :class="cls(p === modelValue, false)">{{ p }}</button>
            </template>
            <button type="button" :disabled="modelValue >= pages" @click="go(modelValue + 1)" :class="cls(false, modelValue >= pages)">Next &raquo;</button>
        </div>
    </nav>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    total: { type: Number, required: true },
    modelValue: { type: Number, default: 1 },
    perPage: { type: Number, default: 15 },
});
const emit = defineEmits(['update:modelValue']);

const pages = computed(() => Math.max(1, Math.ceil(props.total / props.perPage)));
const from = computed(() => props.total ? (props.modelValue - 1) * props.perPage + 1 : 0);
const to = computed(() => Math.min(props.total, props.modelValue * props.perPage));
const numbers = computed(() => {
    const n = pages.value, c = props.modelValue, out = [];
    for (let p = 1; p <= n; p++) {
        if (p === 1 || p === n || Math.abs(p - c) <= 2) out.push(p);
        else if (out[out.length - 1] !== '...') out.push('...');
    }
    return out;
});
function go(p) { if (p >= 1 && p <= pages.value) emit('update:modelValue', p); }
function cls(active, disabled) {
    if (disabled) return 'rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-400 cursor-not-allowed';
    return 'rounded-lg border px-3 py-1.5 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-maiic-300 ' +
        (active ? 'border-maiic-600 bg-maiic-600 text-white' : 'border-gray-200 bg-white text-gray-700 hover:border-maiic-300 hover:bg-maiic-50 hover:text-maiic-800');
}
</script>
