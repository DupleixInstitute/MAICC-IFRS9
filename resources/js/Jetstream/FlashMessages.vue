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
        }
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
            },
            deep: true,
        },
        '$page.props.errors': {
            handler() {
                this.dismissed = {}
            },
            deep: true,
        },
    },
    methods: {
        dismiss(type) {
            this.dismissed = { ...this.dismissed, [type]: true }
        },
    },
}
</script>
