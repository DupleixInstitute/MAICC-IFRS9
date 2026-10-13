<template>
    <!-- The "how to" panel of an import screen: short numbered steps and the
         Download sample CSV button, beside the upload form. -->
    <aside class="rounded-lg border border-maiic-100 bg-maiic-50/60 p-4 text-sm text-gray-700">
        <div class="mb-2 font-semibold text-maiic-800">{{ title }}</div>
        <ol class="list-decimal space-y-1.5 pl-5">
            <slot/>
        </ol>
        <slot name="after"/>
        <a v-if="sampleUrl" :href="sampleUrl" class="secondary-btn mt-4 inline-flex items-center gap-1.5" :title="sampleTitle">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L6.295 8.235a.75.75 0 10-1.09 1.03l4.25 4.5a.75.75 0 001.09 0l4.25-4.5a.75.75 0 00-1.09-1.03l-2.955 3.129V2.75z"/><path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z"/></svg>
            {{ sampleLabel }}
        </a>
    </aside>
</template>

<script setup>
defineProps({
    title: { type: String, default: 'How to import' },
    sampleUrl: { type: String, default: '' },
    sampleLabel: { type: String, default: 'Download sample CSV' },
    sampleTitle: { type: String, default: 'A CSV with the exact column headers this import reads' },
});
</script>
