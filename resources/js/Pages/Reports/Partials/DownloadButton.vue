<script setup>
import { computed } from 'vue'

// A download link for a report: PDF (the primary, MAIIC green), Excel, CSV
// or ZIP. A plain <a>, so the browser downloads the file instead of Inertia
// trying to read it. Disabled until the report has what it needs.
const props = defineProps({
    href: { type: String, default: '' },
    format: { type: String, default: 'PDF' },
    label: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    title: { type: String, default: '' },
})

const text = computed(() => props.label || ({ PDF: 'PDF', Excel: 'Excel', CSV: 'CSV', ZIP: 'ZIP' }[props.format] || props.format))
const primary = computed(() => props.format === 'PDF')
const tone = computed(() => ({ PDF: 'text-red-600', Excel: 'text-maiic-600', CSV: 'text-gray-500', ZIP: 'text-amber-600' }[props.format] || 'text-gray-500'))
</script>

<template>
    <span v-if="disabled || !href"
          :title="title || 'Choose the report settings first'"
          class="inline-flex cursor-not-allowed items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-400">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
        {{ text }}
    </span>
    <a v-else :href="href" :title="title || ('Download as ' + format)"
       :class="primary
           ? 'bg-maiic-600 text-white hover:bg-maiic-700 border-maiic-600'
           : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300'"
       class="inline-flex items-center gap-1.5 rounded-lg border px-3 py-2 text-sm font-semibold shadow-sm transition">
        <svg class="h-4 w-4" :class="primary ? 'text-white' : tone" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
        {{ text }}
    </a>
</template>
