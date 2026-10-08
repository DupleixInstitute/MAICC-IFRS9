<template>
    <Head :title="pageTitle"/>
    <div class="min-h-screen bg-gray-100 dark:bg-slate-900">
        <!-- Phone: the panel as a drawer over the page -->
        <TransitionRoot as="template" :show="drawerOpen">
            <Dialog as="div" class="relative z-40 md:hidden" @close="drawerOpen = false">
                <TransitionChild as="template" enter="transition-opacity ease-linear duration-300" enter-from="opacity-0" enter-to="opacity-100"
                                 leave="transition-opacity ease-linear duration-300" leave-from="opacity-100" leave-to="opacity-0">
                    <div class="fixed inset-0 bg-gray-600 bg-opacity-75"/>
                </TransitionChild>
                <div class="fixed inset-0 z-40 flex">
                    <TransitionChild as="template" enter="transition ease-in-out duration-300 transform" enter-from="-translate-x-full" enter-to="translate-x-0"
                                     leave="transition ease-in-out duration-300 transform" leave-from="translate-x-0" leave-to="-translate-x-full">
                        <DialogPanel class="relative flex w-full max-w-xs flex-1 flex-col sidebar-gradient">
                            <div class="absolute top-0 right-0 -mr-12 pt-2">
                                <button type="button" class="ml-1 flex h-10 w-10 items-center justify-center rounded-full focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white" @click="drawerOpen = false">
                                    <span class="sr-only">Close sidebar</span>
                                    <XMarkIcon class="h-6 w-6 text-white" aria-hidden="true"/>
                                </button>
                            </div>
                            <SidebarNav :collapsed="false"/>
                        </DialogPanel>
                    </TransitionChild>
                    <div class="w-14 flex-shrink-0" aria-hidden="true"/>
                </div>
            </Dialog>
        </TransitionRoot>

        <!-- Desktop: the panel, or the 68px icon rail -->
        <div class="hidden md:fixed md:inset-y-0 md:z-20 md:flex md:flex-col sidebar-gradient transition-[width] duration-200"
             :class="collapsed ? 'md:w-[68px]' : 'md:w-72'">
            <SidebarNav :collapsed="collapsed"/>
        </div>

        <div class="flex flex-1 flex-col transition-[padding] duration-200" :class="collapsed ? 'md:pl-[68px]' : 'md:pl-72'">
            <!-- Top bar -->
            <div class="sticky top-0 z-10 flex h-16 flex-shrink-0 bg-white shadow dark:bg-slate-800 dark:shadow-slate-950/40">
                <button type="button" class="border-r border-gray-200 px-4 text-gray-500 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-maiic-500 dark:border-slate-700 dark:text-slate-300 dark:hover:text-white"
                        @click="toggleSidebar" :title="collapsed ? 'Expand the navigation' : 'Fold the navigation to icons'">
                    <span class="sr-only">Toggle sidebar</span>
                    <Bars3BottomLeftIcon class="h-6 w-6" aria-hidden="true"/>
                </button>
                <div class="flex flex-1 items-center justify-between px-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <!-- Period chip: a display, not a control -->
                        <span v-if="$page.props.currentPeriod" class="hidden sm:inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold"
                              :class="$page.props.currentPeriod.closed ? 'border-slate-300 bg-slate-100 text-slate-700 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200' : 'border-maiic-300 bg-maiic-50 text-maiic-800 dark:border-maiic-700 dark:bg-maiic-900/30 dark:text-maiic-200'"
                              :title="'The financial period the system is working in'">
                            <span class="h-2 w-2 rounded-full" :class="$page.props.currentPeriod.closed ? 'bg-slate-400' : 'bg-maiic-500'"/>
                            {{ $page.props.currentPeriod.name }}
                            <span class="uppercase tracking-wider opacity-70">{{ $page.props.currentPeriod.closed ? 'Closed' : 'Open' }}</span>
                        </span>
                    </div>
                    <div class="ml-4 flex items-center gap-1 md:ml-6">
                        <!-- Appearance switch: light, dark, follow the device -->
                        <button type="button" @click="theme.cycle()" :title="'Appearance: ' + theme.label() + ' (click to change)'"
                                class="flex h-9 w-9 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-maiic-500 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white">
                            <span class="sr-only">Appearance</span>
                            <font-awesome-icon :icon="theme.preference.value === 'light' ? 'sun' : (theme.preference.value === 'dark' ? 'moon' : 'adjust')" class="h-4 w-4"/>
                        </button>
                        <NotificationBell/>
                        <Menu as="div" class="relative ml-2">
                            <MenuButton class="flex max-w-xs items-center rounded-full bg-white text-sm focus:outline-none focus:ring-2 focus:ring-maiic-500 focus:ring-offset-2 dark:bg-slate-800 dark:ring-offset-slate-800">
                                <span class="sr-only">Open user menu</span>
                                <img class="h-8 w-8 rounded-full" :src="$page.props.user?.profile_photo_url || '/default-avatar.png'" :alt="$page.props.user?.name || 'User'"/>
                            </MenuButton>
                            <transition enter-active-class="transition ease-out duration-100" enter-from-class="transform opacity-0 scale-95" enter-to-class="transform opacity-100 scale-100"
                                        leave-active-class="transition ease-in duration-75" leave-from-class="transform opacity-100 scale-100" leave-to-class="transform opacity-0 scale-95">
                                <MenuItems class="absolute right-0 z-10 mt-2 w-56 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none dark:bg-slate-800 dark:ring-slate-700">
                                    <div class="px-4 py-2 text-xs text-gray-500 dark:text-slate-400">
                                        <div class="truncate font-semibold text-gray-800 dark:text-slate-100">{{ $page.props.user?.name }}</div>
                                        <div class="truncate">{{ $page.props.user?.email }}</div>
                                    </div>
                                    <div class="border-t border-gray-100 dark:border-slate-700"/>
                                    <MenuItem v-slot="{ active }">
                                        <Link :href="route('profile.show')" :class="[active ? 'bg-gray-100 dark:bg-slate-700' : '', 'block px-4 py-2 text-sm text-gray-700 dark:text-slate-200']">Profile</Link>
                                    </MenuItem>
                                    <MenuItem v-slot="{ active }" v-if="$page.props.jetstream?.hasApiFeatures">
                                        <Link :href="route('api-tokens.index')" :class="[active ? 'bg-gray-100 dark:bg-slate-700' : '', 'block px-4 py-2 text-sm text-gray-700 dark:text-slate-200']">API Tokens</Link>
                                    </MenuItem>
                                    <MenuItem v-slot="{ active }">
                                        <button type="button" @click="theme.cycle()" :class="[active ? 'bg-gray-100 dark:bg-slate-700' : '', 'block w-full px-4 py-2 text-left text-sm text-gray-700 dark:text-slate-200']">
                                            Appearance: {{ theme.label() }}
                                        </button>
                                    </MenuItem>
                                    <div class="border-t border-gray-100 dark:border-slate-700"/>
                                    <MenuItem v-slot="{ active }">
                                        <form @submit.prevent="logout">
                                            <button type="submit" :class="[active ? 'bg-gray-100 dark:bg-slate-700' : '', 'block w-full px-4 py-2 text-left text-sm text-gray-700 dark:text-slate-200']">Log Out</button>
                                        </form>
                                    </MenuItem>
                                </MenuItems>
                            </transition>
                        </Menu>
                    </div>
                </div>
            </div>

            <main>
                <div class="py-6">
                    <div class="mx-auto px-4 sm:px-6 md:px-4">
                        <!-- Page header: tile, breadcrumb, title, description, actions -->
                        <header class="mb-5">
                            <nav v-if="crumbs.length" class="mb-2 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-400 dark:text-slate-500" aria-label="Breadcrumb">
                                <template v-for="(c, i) in crumbs" :key="i">
                                    <span v-if="i > 0" class="opacity-50">/</span>
                                    <span :class="i === crumbs.length - 1 ? 'text-gray-600 dark:text-slate-300' : ''">{{ c.name }}</span>
                                </template>
                            </nav>
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="flex min-w-0 items-start gap-3">
                                    <span class="mt-0.5 flex h-10 w-10 flex-none items-center justify-center rounded-xl text-white shadow-sm" :class="groupAccent.headerTile">
                                        <font-awesome-icon :icon="headerIcon" class="h-4 w-4"/>
                                    </span>
                                    <div class="min-w-0">
                                        <div v-if="$slots.header"><slot name="header"/></div>
                                        <template v-else>
                                            <h1 class="text-xl font-extrabold leading-tight text-gray-900 dark:text-slate-100">{{ pageTitle }}</h1>
                                            <p v-if="pageDescription" class="mt-0.5 text-sm text-gray-500 dark:text-slate-400">{{ pageDescription }}</p>
                                        </template>
                                    </div>
                                </div>
                                <div v-if="$slots.actions" class="flex flex-none items-center gap-2"><slot name="actions"/></div>
                            </div>
                        </header>
                        <FlashMessages/>
                        <slot/>
                    </div>
                </div>
            </main>
        </div>

        <!-- Processing pill -->
        <div v-if="busyPhase !== 'idle'"
             class="pointer-events-none fixed top-20 left-1/2 z-[60] flex -translate-x-1/2 items-center gap-2.5 rounded-full border-2 px-5 py-2.5 text-[13px] font-bold uppercase tracking-[0.14em] shadow-2xl"
             :class="busyPhase === 'busy' ? 'border-amber-300 bg-maiicgold-400 text-gray-900 shadow-amber-500/40' : 'border-maiic-300 bg-maiic-500 text-white shadow-maiic-500/40'"
             role="status" aria-live="polite">
            <template v-if="busyPhase === 'busy'">
                <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity="0.3" stroke-width="3"/>
                    <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                </svg>
                <span>Loading</span>
            </template>
            <template v-else>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span>Done</span>
            </template>
        </div>

        <!-- Fail-loud error dialogue -->
        <div v-if="errorModal" class="fixed inset-0 z-[70] flex items-center justify-center p-4" role="alertdialog" aria-modal="true">
            <div class="absolute inset-0 bg-slate-900/50" @click="errorModal = null"></div>
            <div class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-800">
                <div class="flex items-start gap-4 p-6">
                    <div class="flex h-11 w-11 flex-none items-center justify-center rounded-full bg-red-100 dark:bg-red-900/40">
                        <svg class="h-6 w-6 text-red-600 dark:text-red-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 9v4M12 17h.01"/><path d="M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.7 3.86a2 2 0 0 0-3.4 0Z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-lg font-extrabold text-gray-900 dark:text-slate-100">{{ errorModal.title }}</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-gray-600 dark:text-slate-300">{{ errorModal.message }}</p>
                        <p v-if="errorModal.status" class="mt-2 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500">Error code {{ errorModal.status }}</p>
                    </div>
                </div>
                <div class="flex justify-end gap-2 bg-gray-50 px-6 py-4 dark:bg-slate-900/60">
                    <button @click="errorModal = null" class="rounded-lg bg-maiic-600 px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-maiic-700">OK, got it</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import {Head, Link, router} from '@inertiajs/vue3'
import {Dialog, DialogPanel, Menu, MenuButton, MenuItem, MenuItems, TransitionChild, TransitionRoot} from '@headlessui/vue'
import {Bars3BottomLeftIcon, XMarkIcon} from '@heroicons/vue/24/outline'
import SidebarNav from '@/Jetstream/SidebarNav.vue'
import NotificationBell from '@/Jetstream/NotificationBell.vue'
import FlashMessages from '@/Jetstream/FlashMessages.vue'
import {useTheme} from '@/composables/useTheme'
import {accent, breadcrumb} from '@/navAccents'

const COLLAPSE_KEY = 'maiic.sidebar.collapsed'

export default {
    components: {SidebarNav, NotificationBell, FlashMessages, Dialog, DialogPanel, Menu, Head, Link, MenuButton, MenuItem, MenuItems, TransitionChild, TransitionRoot, Bars3BottomLeftIcon, XMarkIcon},
    props: {
        title: String,
        description: String,
        menu: [Object, Array],
    },
    setup() {
        return {theme: useTheme()}
    },
    data() {
        let collapsed = false
        try { collapsed = localStorage.getItem(COLLAPSE_KEY) === '1' } catch (e) { /* storage unavailable */ }
        return {
            drawerOpen: false, collapsed,
            busyPhase: 'idle', busyShowAfter: null, busyClearAfter: null,
            offRouterStart: null, offRouterFinish: null, offRouterInvalid: null, offRouterException: null,
            errorModal: null,
        }
    },
    computed: {
        crumbs() {
            return breadcrumb(this.$page.props.menu, this.$page.props.route_name)
        },
        activeEntry() {
            return this.crumbs.length ? this.crumbs[this.crumbs.length - 1] : null
        },
        pageTitle() {
            return this.title || (this.activeEntry ? this.activeEntry.name : 'MAIIC IFRS 9')
        },
        pageDescription() {
            return this.description || (this.activeEntry && this.activeEntry.description) || ''
        },
        groupAccent() {
            return accent(this.crumbs.length ? this.crumbs[0].accent : 'slate')
        },
        headerIcon() {
            const leaf = this.activeEntry
            if (leaf && leaf.icon && leaf.icon !== 'circle') return leaf.icon
            return this.crumbs.length && this.crumbs[0].icon ? this.crumbs[0].icon : 'home'
        },
    },
    mounted() {
        this.initNavigationFeedback()
        if (this.$page.props.user && this.$page.props.user.theme_preference) this.theme.adoptFromServer(this.$page.props.user.theme_preference)
    },
    beforeUnmount() {
        if (this.busyShowAfter) clearTimeout(this.busyShowAfter)
        if (this.busyClearAfter) clearTimeout(this.busyClearAfter)
        if (this.offRouterStart) this.offRouterStart()
        if (this.offRouterFinish) this.offRouterFinish()
        if (this.offRouterInvalid) this.offRouterInvalid()
        if (this.offRouterException) this.offRouterException()
    },
    methods: {
        logout() {
            this.$inertia.post(route('logout'))
        },
        toggleSidebar() {
            if (window.innerWidth < 768) { this.drawerOpen = true; return }
            this.collapsed = !this.collapsed
            try { localStorage.setItem(COLLAPSE_KEY, this.collapsed ? '1' : '0') } catch (e) { /* storage unavailable */ }
        },
        initNavigationFeedback() {
            this.offRouterStart = router.on('start', () => {
                if (this.busyShowAfter) clearTimeout(this.busyShowAfter)
                if (this.busyClearAfter) clearTimeout(this.busyClearAfter)
                this.busyShowAfter = setTimeout(() => { this.busyPhase = 'busy' }, 120)
            })
            this.offRouterFinish = router.on('finish', (event) => {
                if (this.busyShowAfter) { clearTimeout(this.busyShowAfter); this.busyShowAfter = null }
                if (event.detail.visit.completed) {
                    this.busyPhase = 'done'
                    this.busyClearAfter = setTimeout(() => { this.busyPhase = 'idle' }, 650)
                } else {
                    this.busyPhase = 'idle'
                }
            })
            this.offRouterInvalid = router.on('invalid', (event) => {
                event.preventDefault()
                const status = event.detail.response?.status
                let title = 'Action could not be completed'
                let message = 'The server could not complete this action. Nothing was changed.'
                if (status === 403) { title = 'No access rights'; message = 'You do not have the rights to view this page or perform this action. Contact your administrator if you believe you should have access.' }
                else if (status === 419) { title = 'Session expired'; message = 'Your session has expired. Refresh the page and sign in again, then retry.' }
                else if (status === 404) { title = 'Not found'; message = 'The item you tried to open no longer exists. Refresh and try again.' }
                else if (status === 413) { message = 'The file or request is too large to process.' }
                else if (status === 429) { message = 'Too many requests in a short time. Wait a moment and try again.' }
                else if (status === 503) { message = 'The system is temporarily unavailable. Please try again shortly.' }
                else if (status >= 500) { message = 'The server hit an error while processing this action. It has been logged.' }
                this.errorModal = {title, message, status: status ?? null}
                this.busyPhase = 'idle'
            })
            this.offRouterException = router.on('exception', (event) => {
                event.preventDefault()
                this.errorModal = {title: 'Connection problem', message: 'Could not reach the server. Check your connection and try again. Nothing was saved.', status: null}
                this.busyPhase = 'idle'
            })
        },
    },
}
</script>

<style>
.sidebar-gradient {
    background: linear-gradient(172deg, #0b2b1a 0%, #082013 48%, #051509 100%);
    border-right: 1px solid rgba(212, 160, 23, 0.14);
}
</style>
