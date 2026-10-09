<template>
    <!-- Shared confirmation box, in place of the browser's confirm().
         Use it in a template:
             <ConfirmDialog :show="asking" title="Delete this template?"
                            message="It cannot be undone." confirm-label="Delete"
                            tone="danger" @confirm="destroy" @cancel="asking = false"/>
         or from code, with the helper in confirmDialog.js:
             if (!(await confirmDialog({ title: 'Delete this template?', tone: 'danger' }))) return -->
    <Teleport to="body">
        <transition enter-active-class="duration-150 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100"
                    leave-active-class="duration-100 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="show" class="fixed inset-0 z-[80] flex items-center justify-center p-4" role="alertdialog" aria-modal="true"
                 :aria-labelledby="titleId" @keydown.esc="cancel">
                <div class="absolute inset-0 bg-slate-900/50" @click="cancel"></div>
                <div class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-start gap-4 p-6">
                        <div class="flex h-11 w-11 flex-none items-center justify-center rounded-full" :class="toneStyle.iconWrap">
                            <svg v-if="tone === 'danger'" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M3 6h18M8 6V4h8v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6"/>
                            </svg>
                            <svg v-else-if="tone === 'warning'" class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 9v4M12 17h.01"/><path d="M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.7 3.86a2 2 0 0 0-3.4 0Z"/>
                            </svg>
                            <svg v-else class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <h3 :id="titleId" class="text-lg font-extrabold text-gray-900">{{ title }}</h3>
                            <p v-if="message" class="mt-1.5 whitespace-pre-line text-sm leading-relaxed text-gray-600">{{ message }}</p>
                            <slot/>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 bg-gray-50 px-6 py-4">
                        <button ref="cancelButton" type="button" @click="cancel"
                                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">
                            {{ cancelLabel }}
                        </button>
                        <button type="button" @click="confirm" :disabled="busy"
                                class="rounded-lg px-5 py-2 text-sm font-bold text-white shadow-sm transition disabled:opacity-50"
                                :class="toneStyle.button">
                            {{ confirmLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

<script>
let uid = 0

const TONES = {
    danger: { iconWrap: 'bg-red-100 text-red-600', button: 'bg-red-600 hover:bg-red-700' },
    warning: { iconWrap: 'bg-amber-100 text-amber-600', button: 'bg-maiic-600 hover:bg-maiic-700' },
    primary: { iconWrap: 'bg-maiic-100 text-maiic-700', button: 'bg-maiic-600 hover:bg-maiic-700' },
}

export default {
    name: 'ConfirmDialog',
    props: {
        show: { type: Boolean, default: false },
        title: { type: String, default: 'Are you sure?' },
        message: { type: String, default: '' },
        confirmLabel: { type: String, default: 'Yes, continue' },
        cancelLabel: { type: String, default: 'Cancel' },
        // danger (deletes), warning (hard to undo), primary (plain confirmation)
        tone: { type: String, default: 'warning' },
        busy: { type: Boolean, default: false },
    },
    emits: ['confirm', 'cancel'],
    data() {
        return { titleId: `confirm-dialog-title-${++uid}` }
    },
    computed: {
        toneStyle() {
            return TONES[this.tone] || TONES.warning
        },
    },
    watch: {
        show: {
            immediate: true,
            handler(value) {
                if (value) {
                    // The safe choice has the focus, so Enter does not delete by accident.
                    this.$nextTick(() => this.$refs.cancelButton && this.$refs.cancelButton.focus())
                }
            },
        },
    },
    methods: {
        confirm() {
            this.$emit('confirm')
        },
        cancel() {
            this.$emit('cancel')
        },
    },
}
</script>
