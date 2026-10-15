<template>
    <!-- An information box shown when a page opens, with an X to close it (UI rule
         of 15 October 2026: every notice can be closed). Closed stays closed in this
         browser until the text changes, so a notice whose figures move comes back.
         tone: info (blue-grey), warning (amber), success (green), danger (red). -->
    <div v-if="!closed" class="flex items-start gap-3 rounded-lg border p-4" :class="tones[tone].box" :role="tone === 'danger' ? 'alert' : 'status'">
        <div class="min-w-0 flex-1 text-sm" :class="tones[tone].text" ref="body"><slot/></div>
        <button type="button" class="flex-none rounded-md p-1 transition" :class="tones[tone].close"
                title="Close this message" aria-label="Close this message" @click="close">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({
    tone: { type: String, default: 'info' },
    // names the notice on its page; the page's component is added, so 'intro' is enough
    id: { type: String, default: 'notice' },
})

const tones = {
    info: { box: 'border-slate-200 bg-slate-50', text: 'text-slate-700', close: 'text-slate-500 hover:bg-slate-200 hover:text-slate-800' },
    warning: { box: 'border-amber-200 bg-amber-50', text: 'text-amber-900', close: 'text-amber-600 hover:bg-amber-100 hover:text-amber-900' },
    success: { box: 'border-maiic-200 bg-maiic-50', text: 'text-maiic-900', close: 'text-maiic-600 hover:bg-maiic-100 hover:text-maiic-900' },
    danger: { box: 'border-red-200 bg-red-50', text: 'text-red-800', close: 'text-red-500 hover:bg-red-100 hover:text-red-800' },
}

const page = usePage()
const closed = ref(false)
const body = ref(null)

// a short fingerprint of the text, so a changed message shows again
function fingerprint(text) {
    let h = 0
    for (let i = 0; i < text.length; i++) h = (h * 31 + text.charCodeAt(i)) | 0
    return String(h)
}
function storageKey() {
    return `maiic.notice.${page.component}.${props.id}`
}
function text() {
    return (body.value && body.value.textContent || '').replace(/\s+/g, ' ').trim()
}

onMounted(() => {
    try {
        closed.value = localStorage.getItem(storageKey()) === fingerprint(text())
    } catch (e) {
        /* storage unavailable: the notice shows */
    }
})

function close() {
    try {
        localStorage.setItem(storageKey(), fingerprint(text()))
    } catch (e) {
        /* storage unavailable: closed for this visit only */
    }
    closed.value = true
}
</script>
