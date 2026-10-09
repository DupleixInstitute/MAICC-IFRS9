<script setup>
import { computed } from 'vue'

// The system pager's look (numbered pages, current page in MAIIC green) for
// rows already on the page: 15 a page. v-model is the 1-based page.
const props = defineProps({
    total: { type: Number, default: 0 },
    perPage: { type: Number, default: 15 },
    modelValue: { type: Number, default: 1 },
})
const emit = defineEmits(['update:modelValue'])

const pages = computed(() => Math.max(1, Math.ceil(props.total / props.perPage)))
const numbers = computed(() => {
    const out = []
    for (let p = 1; p <= pages.value; p++) {
        if (p === 1 || p === pages.value || Math.abs(p - props.modelValue) <= 2) out.push(p)
        else if (out[out.length - 1] !== '...') out.push('...')
    }
    return out
})
const go = (p) => emit('update:modelValue', Math.min(Math.max(1, p), pages.value))
const from = computed(() => (props.modelValue - 1) * props.perPage + 1)
const to = computed(() => Math.min(props.modelValue * props.perPage, props.total))
</script>

<template>
    <div v-if="pages > 1" class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-4 py-3">
        <p class="text-xs text-gray-500">Showing {{ from }} to {{ to }} of {{ total.toLocaleString() }} rows</p>
        <nav class="flex flex-wrap items-center gap-1" aria-label="Pages">
            <button type="button" :disabled="modelValue === 1" @click="go(modelValue - 1)"
                    class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 hover:border-maiic-300 hover:bg-maiic-50 disabled:cursor-not-allowed disabled:text-gray-400 disabled:hover:bg-white">Previous</button>
            <template v-for="(p, i) in numbers" :key="i">
                <span v-if="p === '...'" class="px-2 text-sm text-gray-400">...</span>
                <button v-else type="button" @click="go(p)"
                        class="rounded-lg border px-3 py-1.5 text-sm font-semibold transition"
                        :class="p === modelValue ? 'border-maiic-600 bg-maiic-600 text-white' : 'border-gray-200 bg-white text-gray-700 hover:border-maiic-300 hover:bg-maiic-50 hover:text-maiic-800'">{{ p }}</button>
            </template>
            <button type="button" :disabled="modelValue === pages" @click="go(modelValue + 1)"
                    class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 hover:border-maiic-300 hover:bg-maiic-50 disabled:cursor-not-allowed disabled:text-gray-400 disabled:hover:bg-white">Next</button>
        </nav>
    </div>
</template>
