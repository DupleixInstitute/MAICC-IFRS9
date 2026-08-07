<template>
    <div class="relative">
        <button type="button" @click="toggle"
                :class="['relative rounded-full p-2 transition focus:outline-none focus:ring-2 focus:ring-maiic-500 focus:ring-offset-2',
                         unread > 0 ? 'bg-amber-50 text-amber-600 hover:bg-amber-100 hover:text-amber-700'
                                    : 'bg-white text-gray-400 hover:bg-gray-100 hover:text-gray-600']">
            <span class="sr-only">View notifications</span>
            <svg :class="['h-6 w-6', unread > 0 ? 'bell-ring' : '']" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/>
                <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>
            </svg>
            <span v-if="unread > 0"
                  class="absolute -right-0.5 -top-0.5 inline-flex h-4 min-w-[16px] items-center justify-center rounded-full border-2 border-white bg-red-600 px-0.5 text-[10px] font-bold leading-none text-white">
                {{ unread > 9 ? '9+' : unread }}
            </span>
        </button>

        <div v-if="open" class="fixed inset-0 z-40" @click="open = false"></div>
        <div v-if="open"
             class="absolute right-0 z-50 mt-2 w-[340px] max-w-[92vw] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                <span class="text-sm font-bold text-gray-800">Notifications</span>
                <button v-if="unread > 0" @click="markAll" class="text-xs font-semibold text-maiic-700 hover:text-maiic-800">
                    Mark all read
                </button>
            </div>
            <div class="max-h-80 overflow-y-auto">
                <div v-if="loading" class="px-4 py-6 text-center text-sm text-gray-400">Loading notifications...</div>
                <template v-else-if="items.length">
                    <a v-for="n in items" :key="n.id" href="#" @click.prevent="openItem(n)"
                       :class="['flex gap-2.5 border-b border-gray-50 px-4 py-3 transition hover:bg-gray-50',
                                !n.read ? 'bg-maiic-50/60 hover:bg-maiic-50' : '']">
                        <span :class="['mt-1.5 h-2 w-2 flex-none rounded-full', n.read ? 'bg-gray-300' : 'bg-maiic-500']"></span>
                        <span class="min-w-0">
                            <span class="block text-sm leading-snug text-gray-800">{{ n.message }}</span>
                            <span class="mt-0.5 block text-xs text-gray-400" :title="n.created_at_full">{{ n.created_at }}</span>
                        </span>
                    </a>
                </template>
                <div v-else class="px-4 py-8 text-center text-sm text-gray-400">No notifications yet.</div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'NotificationBell',
    data() {
        return {
            open: false,
            loading: false,
            items: [],
            // seed the badge from the count shared on every page load
            unread: (this.$page && this.$page.props && this.$page.props.notifications_unread) || 0,
        }
    },
    watch: {
        // keep the badge in sync when Inertia re-shares props after a visit
        '$page.props.notifications_unread'(v) {
            this.unread = v || 0
        },
    },
    methods: {
        toggle() {
            this.open = !this.open
            if (this.open) this.fetchRecent()
        },
        csrf() {
            const el = document.head.querySelector('meta[name="csrf-token"]')
            return el ? { 'X-CSRF-TOKEN': el.content } : {}
        },
        async fetchRecent() {
            this.loading = true
            try {
                const res = await fetch(this.route('notifications.recent'), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                const data = await res.json()
                this.items = data.items || []
                this.unread = data.unread || 0
            } catch (e) {
                this.items = []
            } finally {
                this.loading = false
            }
        },
        async markAll() {
            try {
                await fetch(this.route('notifications.read_all'), {
                    method: 'POST',
                    headers: { ...this.csrf(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                this.items = this.items.map(n => ({ ...n, read: true }))
                this.unread = 0
            } catch (e) { /* leave state as-is */ }
        },
        async openItem(n) {
            if (!n.read) {
                try {
                    await fetch(this.route('notifications.read', n.id), {
                        method: 'POST',
                        headers: { ...this.csrf(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    })
                } catch (e) { /* non-fatal */ }
                this.unread = Math.max(0, this.unread - 1)
                n.read = true
            }
            this.open = false
            if (n.url) this.$inertia.visit(n.url)
        },
    },
}
</script>

<style scoped>
.bell-ring {
    transform-origin: 50% 0;
    animation: bell-ring 2.2s ease-in-out infinite;
}
@keyframes bell-ring {
    0%, 60%, 100% { transform: rotate(0); }
    5% { transform: rotate(14deg); }
    10% { transform: rotate(-12deg); }
    15% { transform: rotate(9deg); }
    20% { transform: rotate(-6deg); }
    25% { transform: rotate(0); }
}
</style>
