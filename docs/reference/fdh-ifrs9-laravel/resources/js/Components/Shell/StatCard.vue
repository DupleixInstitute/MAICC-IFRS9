<script setup>
import Icon from '@/Components/Shell/Icon.vue';

/**
 * A single KPI tile. The `value` is always supplied by the caller from a
 * controller prop; when no run exists the page passes null and this tile
 * renders a neutral em-dash placeholder - never a fabricated number.
 */
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number, null], default: null },
    unit:  { type: String, default: '' },
    icon:  { type: String, default: 'chartpie' },
    hint:  { type: String, default: '' },
});
</script>

<template>
    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-start justify-between">
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-slate-400">{{ label }}</p>
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 text-gray-400 dark:bg-slate-800 dark:text-slate-500">
                <Icon :name="icon" class="h-4 w-4" />
            </span>
        </div>
        <div class="mt-3 flex items-baseline gap-1">
            <span class="text-2xl font-semibold tabular-nums text-gray-900 dark:text-slate-100">
                {{ value === null || value === '' ? '-' : value }}
            </span>
            <span v-if="unit && value !== null && value !== ''" class="text-sm font-medium text-gray-400 dark:text-slate-500">{{ unit }}</span>
        </div>
        <p class="mt-1 text-xs text-gray-400 dark:text-slate-500">{{ hint || 'Awaiting ECL run' }}</p>
    </div>
</template>
