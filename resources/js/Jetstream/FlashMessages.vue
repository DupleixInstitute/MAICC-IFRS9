<template>
    <!-- Page messages after an action: a box with an icon, a title and the
         message, coloured by type (success green, error red, warning amber,
         info blue-grey). Each can be closed; a new message shows again. -->
    <div v-if="visible.length" class="mb-5 space-y-3" aria-live="polite">
        <div v-for="msg in visible" :key="msg.type"
             class="flex items-start gap-3 rounded-xl border p-4 shadow-sm"
             :class="styles[msg.type].box"
             :role="msg.type === 'error' ? 'alert' : 'status'">
            <span class="flex h-9 w-9 flex-none items-center justify-center rounded-full" :class="styles[msg.type].iconWrap">
                <svg v-if="msg.type === 'success'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7"/></svg>
                <svg v-else-if="msg.type === 'error'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
                <svg v-else-if="msg.type === 'warning'" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.7 3.86a2 2 0 0 0-3.4 0Z"/></svg>
                <svg v-else class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
            </span>
            <div class="min-w-0 flex-1 pt-0.5">
                <p class="text-sm font-bold" :class="styles[msg.type].title">{{ msg.title }}</p>
                <p v-if="msg.text" class="mt-0.5 whitespace-pre-line text-sm" :class="styles[msg.type].text">{{ msg.text }}</p>
                <ul v-if="msg.list && msg.list.length" class="mt-1.5 list-disc space-y-0.5 pl-5 text-sm" :class="styles[msg.type].text">
                    <li v-for="(item, i) in msg.list" :key="i">{{ item }}</li>
                </ul>
            </div>
            <button type="button" class="flex-none rounded-md p-1.5 transition" :class="styles[msg.type].close"
                    :title="'Close this message'" aria-label="Close this message" @click="dismiss(msg.type)">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
    </div>

    <!-- Every error also opens as a modal, so it is seen wherever the page is scrolled or a dialog is open:
         a refusal the system returns (flash error) and the things a form must correct (validation).
         The box above stays as a reminder after the modal is closed. -->
    <teleport to="body">
        <div v-if="errorDialog" class="fixed inset-0 z-[70] flex items-center justify-center p-4" role="alertdialog" aria-modal="true" :aria-label="errorDialog.title">
            <div class="absolute inset-0 bg-slate-900/50" @click="errorDialog = null"></div>
            <div class="relative w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-800">
                <div class="flex items-start gap-4 p-6">
                    <div class="flex h-11 w-11 flex-none items-center justify-center rounded-full bg-red-100 dark:bg-red-900/40">
                        <svg class="h-6 w-6 text-red-600 dark:text-red-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-lg font-extrabold text-gray-900 dark:text-slate-100">{{ errorDialog.title }}</h3>
                        <p v-if="errorDialog.text" class="mt-1.5 whitespace-pre-line text-sm leading-relaxed text-gray-600 dark:text-slate-300">{{ errorDialog.text }}</p>
                        <ul v-if="errorDialog.list && errorDialog.list.length" class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-600 dark:text-slate-300">
                            <li v-for="(item, i) in errorDialog.list" :key="i">{{ item }}</li>
                        </ul>
                        <p class="mt-3 text-xs text-gray-500 dark:text-slate-400">Nothing was changed by the part that failed.</p>
                    </div>
                </div>
                <div class="flex justify-end bg-gray-50 px-6 py-4 dark:bg-slate-900/60">
                    <button type="button" class="rounded-lg bg-maiic-600 px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-maiic-700" @click="errorDialog = null">OK, got it</button>
                </div>
            </div>
        </div>
    </teleport>
</template>

<script>
const STYLES = {
    success: {
        box: 'border-maiic-200 bg-maiic-50',
        iconWrap: 'bg-maiic-100 text-maiic-700',
        title: 'text-maiic-900',
        text: 'text-maiic-800',
        close: 'text-maiic-600 hover:bg-maiic-100 hover:text-maiic-900',
    },
    error: {
        box: 'border-red-200 bg-red-50',
        iconWrap: 'bg-red-100 text-red-600',
        title: 'text-red-900',
        text: 'text-red-800',
        close: 'text-red-500 hover:bg-red-100 hover:text-red-800',
    },
    warning: {
        box: 'border-amber-200 bg-amber-50',
        iconWrap: 'bg-amber-100 text-amber-600',
        title: 'text-amber-900',
        text: 'text-amber-800',
        close: 'text-amber-600 hover:bg-amber-100 hover:text-amber-900',
    },
    info: {
        box: 'border-slate-200 bg-slate-50',
        iconWrap: 'bg-slate-200 text-slate-700',
        title: 'text-slate-900',
        text: 'text-slate-700',
        close: 'text-slate-500 hover:bg-slate-200 hover:text-slate-800',
    },
}

export default {
    data() {
        return {
            styles: STYLES,
            dismissed: {},
            errorDialog: null,
        }
    },
    mounted() {
        // a page reached by a redirect carries its error on first render
        this.openErrorDialog()
        this.onKey = (e) => { if (e.key === 'Escape') this.errorDialog = null }
        document.addEventListener('keydown', this.onKey)
    },
    beforeUnmount() {
        document.removeEventListener('keydown', this.onKey)
    },
    computed: {
        flash() {
            return this.$page.props.flash || {}
        },
        errors() {
            // Named error bags arrive nested ({ bag: { field: message } }).
            const collect = (value) => (value && typeof value === 'object')
                ? Object.values(value).flatMap(collect)
                : [value]
            return collect(this.$page.props.errors || {}).filter(Boolean)
        },
        messages() {
            const out = []
            if (this.flash.success) {
                out.push({ type: 'success', title: 'Done', text: this.flash.success })
            }
            if (this.flash.error) {
                out.push({ type: 'error', title: 'Something went wrong', text: this.flash.error })
            } else if (this.errors.length) {
                out.push({
                    type: 'error',
                    title: this.errors.length === 1 ? 'Please correct one thing before saving' : `Please correct ${this.errors.length} things before saving`,
                    list: this.errors,
                })
            }
            if (this.flash.warning) {
                out.push({ type: 'warning', title: 'Please note', text: this.flash.warning })
            }
            if (this.flash.info) {
                out.push({ type: 'info', title: 'For your information', text: this.flash.info })
            }
            return out
        },
        visible() {
            return this.messages.filter((m) => !this.dismissed[m.type])
        },
    },
    watch: {
        // A new response brings its messages back, even ones closed before.
        '$page.props.flash': {
            handler() {
                this.dismissed = {}
                this.openErrorDialog()
            },
            deep: true,
        },
        '$page.props.errors': {
            handler() {
                this.dismissed = {}
                this.openErrorDialog()
            },
            deep: true,
        },
    },
    methods: {
        /** The error of this response, if any, as a modal (the same words as the box). */
        openErrorDialog() {
            const error = this.messages.find((m) => m.type === 'error')
            this.errorDialog = error ? { title: error.title, text: error.text || null, list: error.list || [] } : null
        },
        dismiss(type) {
            this.dismissed = { ...this.dismissed, [type]: true }
        },
    },
}
</script>
