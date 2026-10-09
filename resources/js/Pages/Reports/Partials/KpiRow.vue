<script setup>
import { computed } from 'vue'

// One row of small summary cards that fits on one line at desktop width:
// as many columns as cards. items: [{ label, value, sub?, tone? }] where tone
// is maiic / emerald (green), amber (gold), rose (red) or grey.
const props = defineProps({ items: { type: Array, default: () => [] } })

const COLS = {
    1: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
    2: 'grid-cols-2',
    3: 'grid-cols-1 sm:grid-cols-3',
    4: 'grid-cols-2 lg:grid-cols-4',
    5: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5',
    6: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-6',
}
const cols = computed(() => COLS[Math.min(props.items.length, 6)] || COLS[6])
const ACCENT = { maiic: '#15803d', emerald: '#15803d', amber: '#d97706', rose: '#dc2626', grey: '#9ca3af' }
</script>

<template>
    <div v-if="items.length" class="grid gap-3" :class="cols">
        <div v-for="(k, i) in items" :key="i" class="maiic-kpi min-w-0 px-4 py-3" :style="{ '--accent': ACCENT[k.tone] || ACCENT.maiic }">
            <div class="maiic-kpi-label truncate" :title="k.label">{{ k.label }}</div>
            <div class="truncate text-xl font-extrabold leading-tight text-gray-900 tabular-nums" :title="String(k.value)">{{ k.value }}</div>
            <div v-if="k.sub" class="mt-0.5 truncate text-xs text-gray-500">{{ k.sub }}</div>
        </div>
    </div>
</template>
