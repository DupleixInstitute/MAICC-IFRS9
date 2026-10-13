<template>
    <!-- The segment a PD or LGD run was calculated for: its name on one line, the level under it.
         Shared by the monthly and cumulative PD and LGD lists. -->
    <div class="flex items-center gap-2.5">
        <span class="flex h-8 w-8 flex-none items-center justify-center rounded-lg" :class="look.chip">
            <font-awesome-icon :icon="look.icon" class="text-sm"/>
        </span>
        <span class="min-w-0">
            <span class="block font-semibold leading-tight text-gray-900 dark:text-slate-100">{{ label }}</span>
            <span class="block text-xs leading-tight text-gray-500 dark:text-slate-400">{{ caption }}</span>
        </span>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    level: { type: String, default: '' },      // book | portfolio | sector
    name: { type: String, default: '' },       // the portfolio or sector name, when known
    code: { type: [String, Number], default: '' },
})

const lvl = computed(() => String(props.level || '').toLowerCase())

const look = computed(() => ({
    book: { icon: 'database', chip: 'bg-maiic-50 text-maiic-700 dark:bg-maiic-900/40 dark:text-maiic-300' },
    portfolio: { icon: 'folder', chip: 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' },
    sector: { icon: 'building', chip: 'bg-sky-50 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300' },
}[lvl.value] || { icon: 'folder', chip: 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-300' }))

const label = computed(() => {
    if (lvl.value === 'book') return 'Whole loan book'
    if (props.name) return props.name
    if (lvl.value === 'portfolio') return 'Portfolio ' + (props.code || '')
    if (lvl.value === 'sector') return 'Sector ' + (props.code || '')
    return props.code ? String(props.code) : '-'
})

const caption = computed(() => {
    if (lvl.value === 'book') return 'All portfolios pooled'
    if (lvl.value === 'portfolio') return 'Portfolio'
    if (lvl.value === 'sector') return props.code ? 'Sector ' + props.code : 'Sector'
    return lvl.value ? lvl.value.charAt(0).toUpperCase() + lvl.value.slice(1) : ''
})
</script>
