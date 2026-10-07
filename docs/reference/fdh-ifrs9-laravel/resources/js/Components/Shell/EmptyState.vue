<script setup>
import Icon from '@/Components/Shell/Icon.vue';

/**
 * Professional empty-state panel shown on every page until real data arrives.
 * Renders no figures of its own - only guidance copy supplied by the page.
 */
defineProps({
    icon:    { type: String, default: 'inbox' },
    title:   { type: String, default: 'No data yet' },
    message: { type: String, default: 'Run bootstrap or import a reporting period to populate this view.' },
    hint:    { type: String, default: '' },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-gray-300 bg-white/60 px-6 py-16 text-center dark:border-slate-700 dark:bg-slate-900/40">
        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 text-gray-400 ring-1 ring-gray-200 dark:bg-slate-800 dark:text-slate-500 dark:ring-slate-700">
            <Icon :name="icon" class="h-7 w-7" />
        </div>
        <h3 class="mt-5 text-base font-semibold text-gray-900 dark:text-slate-100">{{ title }}</h3>
        <p class="mt-1.5 max-w-md text-sm leading-relaxed text-gray-500 dark:text-slate-400">{{ message }}</p>
        <p v-if="hint" class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-500 dark:bg-slate-800 dark:text-slate-400">
            {{ hint }}
        </p>
        <slot />
    </div>
</template>
