<template>
    <!-- the tab title: the page's own, else its name in the menu (the last breadcrumb) -->
    <Head :title="headTitle"/>
    <div>
        <TransitionRoot as="template" :show="sidebarOpen">
            <Dialog as="div" class="relative z-40 md:hidden" @close="sidebarOpen = false">
                <TransitionChild as="template" enter="transition-opacity ease-linear duration-300"
                                 enter-from="opacity-0" enter-to="opacity-100"
                                 leave="transition-opacity ease-linear duration-300" leave-from="opacity-100"
                                 leave-to="opacity-0">
                    <div class="fixed inset-0 bg-gray-600 bg-opacity-75"/>
                </TransitionChild>

                <div class="fixed inset-0 z-40 flex">
                    <TransitionChild as="template" enter="transition ease-in-out duration-300 transform"
                                     enter-from="-translate-x-full" enter-to="translate-x-0"
                                     leave="transition ease-in-out duration-300 transform" leave-from="translate-x-0"
                                     leave-to="-translate-x-full">
                        <DialogPanel class="relative flex w-full max-w-xs flex-1 flex-col"
                                     style="background: linear-gradient(172deg, #0b2b1a 0%, #082013 48%, #051509 100%); border-right: 1px solid rgba(212,160,23,0.14);">
                            <TransitionChild as="template" enter="ease-in-out duration-300" enter-from="opacity-0"
                                             enter-to="opacity-100" leave="ease-in-out duration-300"
                                             leave-from="opacity-100" leave-to="opacity-0">
                                <div class="absolute top-0 right-0 -mr-12 pt-2">
                                    <button type="button"
                                            class="ml-1 flex h-10 w-10 items-center justify-center rounded-full focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white"
                                            @click="sidebarOpen = false">
                                        <span class="sr-only">Close sidebar</span>
                                        <XMarkIcon class="h-6 w-6 text-white" aria-hidden="true"/>
                                    </button>
                                </div>
                            </TransitionChild>
                            <SidebarNav/>
                        </DialogPanel>
                    </TransitionChild>
                    <div class="w-14 flex-shrink-0" aria-hidden="true">
                        <!-- Dummy element to force sidebar to shrink to fit close icon -->
                    </div>
                </div>
            </Dialog>
        </TransitionRoot>

        <!-- Static sidebar for desktop: 296px wide, or the 72px icon rail when
             collapsed (remembered per browser); the page widens with it. -->
        <div class="hidden md:fixed md:inset-y-0 md:z-20 md:flex md:flex-col md:transition-[width] md:duration-200"
             :class="sidebarCollapsed ? 'md:w-[72px]' : 'md:w-[296px]'"
             style="background: linear-gradient(172deg, #0b2b1a 0%, #082013 48%, #051509 100%); border-right: 1px solid rgba(212,160,23,0.14);">
            <SidebarNav :collapsed="sidebarCollapsed" collapsible @toggle="setSidebarCollapsed(!sidebarCollapsed)" @expand="setSidebarCollapsed(false)"/>
        </div>
        <div class="flex min-w-0 flex-1 flex-col md:transition-[padding] md:duration-200" :class="sidebarCollapsed ? 'md:pl-[72px]' : 'md:pl-[296px]'">
            <div class="sticky top-0 z-10 flex h-16 flex-shrink-0 bg-white shadow">
                <button type="button"
                        class="border-r border-gray-200 px-4 text-gray-500 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-maiic-500 md:hidden"
                        @click="sidebarOpen = true">
                    <span class="sr-only">Open sidebar</span>
                    <Bars3BottomLeftIcon class="h-6 w-6" aria-hidden="true"/>
                </button>
                <div class="flex flex-1 justify-between px-4">
                    <div class="flex flex-1">
                        <form class="flex w-full md:ml-0" action="#" method="GET">
                            <label for="search-field" class="sr-only">Search</label>
                            <div class="relative w-full text-gray-400 focus-within:text-gray-600">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center">
                                    <MagnifyingGlassIcon class="h-5 w-5" aria-hidden="true"/>
                                </div>
                                <input id="search-field"
                                       class="block h-full w-full border-transparent py-2 pl-8 pr-3 text-gray-900 placeholder-gray-500 focus:border-transparent focus:placeholder-gray-400 focus:outline-none focus:ring-0 sm:text-sm"
                                       placeholder="Search" type="search" name="search"/>
                            </div>
                        </form>
                    </div>
                    <div class="ml-4 flex items-center md:ml-6">
                        <NotificationBell/>

                        <!-- Profile dropdown -->
                        <Menu as="div" class="relative ml-3">
                            <div>
                                <MenuButton
                                    class="flex max-w-xs items-center rounded-full bg-white text-sm focus:outline-none focus:ring-2 focus:ring-maiic-500 focus:ring-offset-2">
                                    <span class="sr-only">Open user menu</span>
                                    <img class="h-8 w-8 rounded-full"
                                         :src="$page.props.user?.profile_photo_url || '/default-avatar.png'"
                                         :alt="$page.props.user?.name || 'User'"/>
                                </MenuButton>
                            </div>
                            <transition enter-active-class="transition ease-out duration-100"
                                        enter-from-class="transform opacity-0 scale-95"
                                        enter-to-class="transform opacity-100 scale-100"
                                        leave-active-class="transition ease-in duration-75"
                                        leave-from-class="transform opacity-100 scale-100"
                                        leave-to-class="transform opacity-0 scale-95">
                                <MenuItems
                                    class="absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                                    <MenuItem v-slot="{ active }">
                                        <Link :href="route('profile.show')"
                                              :class="[active ? 'bg-gray-100' : '', 'block px-4 py-2 text-sm text-gray-700']">
                                            Profile
                                        </Link>
                                    </MenuItem>
                                    <MenuItem v-slot="{ active }" v-if="$page.props.jetstream.hasApiFeatures">
                                        <Link :href="route('api-tokens.index')"
                                              :class="[active ? 'bg-gray-100' : '', 'block px-4 py-2 text-sm text-gray-700']">
                                            API Tokens
                                        </Link>
                                    </MenuItem>
                                    <div class="border-t border-gray-100"></div>

                                    <!-- Authentication -->
                                    <MenuItem v-slot="{ active }">
                                        <form @submit.prevent="logout">
                                            <button type="submit"
                                                    class="block w-full px-4 py-2 text-sm leading-5 text-gray-700 text-left hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out">
                                                Log Out
                                            </button>
                                        </form>
                                    </MenuItem>

                                </MenuItems>
                            </transition>
                        </Menu>
                    </div>
                </div>
            </div>

            <main ref="main">
                <div class="pt-6 pb-20"><!-- the bottom clears the floating help button -->
                    <div class="mx-auto px-4 sm:px-6 md:px-4">
                        <!-- UAT mode (Staging Module Specification v1, section 8.9, item 5): on every screen while
                             administrators may approve their own proposals; deliberately without a close button -->
                        <div v-if="$page.props.uatMode" role="status" class="mb-4 rounded-md border border-amber-400 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-900 dark:border-amber-400/60 dark:bg-amber-400/10 dark:text-amber-200">
                            UAT mode: maker-checker relaxed for administrators. Every self-approval is recorded on the self-approvals report.
                        </div>
                        <!-- Breadcrumbs: the page's place in the menu, above every page header -->
                        <Breadcrumbs :title="title" :extra="extraCrumbs"/>
                        <!-- Page Heading -->
                        <header class="" v-if="$slots.header || $slots.actions || description">
                            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <slot name="header">
                                        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ title }}</h2>
                                        <p v-if="description" class="mt-0.5 text-sm text-gray-500">{{ description }}</p>
                                    </slot>
                                </div>
                            </div>
                        </header>
                        <!-- Page buttons (#actions) sit in their own row at the far
                             right, directly above the page's table: below the tab
                             names, the KPI cards and the filter bars. The row is one
                             element (actionsRow) that placeActions() moves to just
                             before the first visible table container, or a page's own
                             [data-actions-anchor]; with no table it sits here, below
                             the tab row. Teleport keeps the buttons' handlers. -->
                        <Teleport v-if="$slots.actions" :to="actionsRow"><slot name="actions"/></Teleport>
                        <!-- Section tabs: screens that do one job together share one
                             menu entry; each tab is that screen's own route. Same look
                             as the in-page tabs (Eir/Data.vue): green active tab and a
                             chip with the number of records the tab lists. -->
                        <div v-if="section && section.tabs.length > 1" class="mb-5 rounded-xl border border-gray-200 bg-white px-5 pt-4 shadow-sm">
                            <nav class="flex flex-wrap gap-x-6 gap-y-1" aria-label="Section">
                                <Link v-for="tab in section.tabs" :key="tab.name" :href="route(tab.route, tab.params || {})"
                                      class="whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-semibold transition-colors duration-150"
                                      :class="isActiveTab(tab) ? 'border-maiic-600 text-maiic-700' : 'border-transparent text-gray-500 hover:text-gray-800'"
                                      :aria-current="isActiveTab(tab) ? 'page' : null">
                                    {{ tab.name }}
                                    <span v-if="tabCount(tab) !== null"
                                          class="ml-1 rounded-full px-2 py-0.5 text-xs"
                                          :class="isActiveTab(tab) ? 'bg-maiic-100 text-maiic-800' : 'bg-gray-100 text-gray-600'">{{ tabCount(tab).toLocaleString() }}</span>
                                </Link>
                            </nav>
                        </div>
                        <!-- home of the actions row when the page has no table -->
                        <div ref="actionsHome"></div>
                        <FlashMessages/>

                        <div ref="pageBody"><slot/></div>
                    </div>
                </div>
            </main>
        </div>

        <!-- Navigation feedback pill: "Loading" while a visit is in flight
             (after a short delay so instant pages don't flash), then a green
             "Done" flash on completion. -->
        <div v-if="busyPhase !== 'idle'"
             class="pointer-events-none fixed top-20 left-1/2 z-[60] flex -translate-x-1/2 items-center gap-2.5 rounded-full border-2 px-5 py-2.5 text-[13px] font-bold uppercase tracking-[0.14em] shadow-2xl"
             :class="busyPhase === 'busy'
                 ? 'border-amber-300 bg-maiicgold-400 text-gray-900 shadow-amber-500/40'
                 : 'border-maiic-300 bg-maiic-500 text-white shadow-maiic-500/40'"
             role="status" aria-live="polite">
            <template v-if="busyPhase === 'busy'">
                <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity="0.3" stroke-width="3"/>
                    <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                </svg>
                <span>Loading</span>
            </template>
            <template v-else>
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M5 12.5l4.5 4.5L19 7" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <span>Done</span>
            </template>
        </div>

        <!-- Fail-loud error modal: rights, session, server and connection
             problems are explained politely instead of a raw error page. -->
        <div v-if="errorModal" class="fixed inset-0 z-[70] flex items-center justify-center p-4"
             role="alertdialog" aria-modal="true">
            <div class="absolute inset-0 bg-slate-900/50" @click="errorModal = null"></div>
            <div class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-800">
                <div class="flex items-start gap-4 p-6">
                    <div class="flex h-11 w-11 flex-none items-center justify-center rounded-full bg-red-100 dark:bg-red-900/40">
                        <svg class="h-6 w-6 text-red-600 dark:text-red-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 9v4M12 17h.01"/>
                            <path d="M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.7 3.86a2 2 0 0 0-3.4 0Z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-lg font-extrabold text-gray-900 dark:text-slate-100">{{ errorModal.title }}</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-gray-600 dark:text-slate-300">{{ errorModal.message }}</p>
                        <p v-if="errorModal.status" class="mt-2 text-xs font-semibold uppercase tracking-wider text-gray-400">
                            Error code {{ errorModal.status }}
                        </p>
                    </div>
                </div>
                <div class="flex justify-end gap-2 bg-gray-50 px-6 py-4 dark:bg-slate-900/60">
                    <button @click="errorModal = null"
                            class="rounded-lg bg-maiic-600 px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-maiic-700">
                        OK, got it
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import {Head, Link} from '@inertiajs/vue3'
import {
    Dialog,
    DialogPanel,
    Menu,
    MenuButton,
    MenuItem,
    MenuItems,
    TransitionChild,
    TransitionRoot,
} from '@headlessui/vue'
import {
    Bars3BottomLeftIcon,
    BellIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline'
import {MagnifyingGlassIcon} from '@heroicons/vue/20/solid'
import SidebarNav from "@/Jetstream/SidebarNav.vue"
import { currentSection, activeTab } from "@/Jetstream/menuMatch"
import NotificationBell from "@/Jetstream/NotificationBell.vue"
import FlashMessages from '@/Jetstream/FlashMessages.vue'
import Breadcrumbs from '@/Components/Maiic/Breadcrumbs.vue'
import ApplicationMark from '@/Jetstream/ApplicationMark.vue'
import { router } from '@inertiajs/vue3'
import { markRaw } from 'vue'

// The desktop sidebar is full width or a 72px icon rail; the choice is kept
// per browser (spec v4 section 11, decision D24).
const SIDEBAR_KEY = 'maiic.sidebar.collapsed'
function readSidebarCollapsed() {
    try {
        return localStorage.getItem(SIDEBAR_KEY) === '1'
    } catch (e) {
        return false
    }
}

// The page's action buttons sit in their own row at the far right, directly
// above the page's first visible table (UI request of 13 October 2026).
function makeActionsRow() {
    if (typeof document === 'undefined') return null;
    const el = document.createElement('div');
    el.className = 'mb-3 flex flex-wrap items-center justify-end gap-2';
    el.setAttribute('data-actions-row', '');
    return el;
}
function visible(el) {
    return !!el && el.getClientRects().length > 0;
}
// Where the actions row goes: before the page's own [data-actions-anchor],
// else before the container of the first visible table (its panel, else its
// .maiic-table-wrap, else the table). A container that is a cell of a grid or
// a row of flex items is lifted to the grid or row, so the actions row never
// becomes a cell beside it. Null when the page shows no table.
function actionsTarget(body, row) {
    const usable = el => visible(el) && !row.contains(el) && !el.closest('[role="dialog"], [aria-modal="true"], .fixed');
    const mark = [...body.querySelectorAll('[data-actions-anchor]')].find(usable);
    if (mark) return mark;
    const wrap = [...body.querySelectorAll('.maiic-table-wrap')].find(el => usable(el) && el.querySelector('table'));
    const table = wrap ? wrap.querySelector('table') : [...body.querySelectorAll('table')].find(usable);
    if (!table) return null;
    let node = table.closest('.maiic-panel') || wrap || table.closest('.overflow-x-auto') || table;
    if (!body.contains(node) || node === body) node = table;
    while (node.parentElement && node.parentElement !== body) {
        const cs = getComputedStyle(node.parentElement);
        const side = cs.display.includes('grid') || (cs.display.includes('flex') && !cs.flexDirection.startsWith('column'));
        if (!side) break;
        node = node.parentElement;
    }
    return node;
}

export default {
    components: {
        SidebarNav,
        NotificationBell,
        FlashMessages,
        Breadcrumbs,
        ApplicationMark,
        MagnifyingGlassIcon,
        Dialog,
        DialogPanel,
        Menu,
        Head,
        Link,
        MenuButton,
        MenuItem,
        MenuItems,
        TransitionChild,
        TransitionRoot,
        Bars3BottomLeftIcon,
        BellIcon,
        XMarkIcon,
    },
    props: {
        title: String,
        description: String,
        menu: [Object, Array],
        // a detail page's own crumbs after its menu trail, e.g.
        // [{ label: 'S&P Emerging Markets', href: null }]; the last is the page
        extraCrumbs: { type: Array, default: () => [] },
    },
    computed: {
        headTitle() {
            if (this.title) return this.title
            const crumbs = (this.$page.props.breadcrumbs && this.$page.props.breadcrumbs.crumbs) || []
            return crumbs.length ? crumbs[crumbs.length - 1].label : ''
        },
        section() {
            return currentSection(this.$page.props.menu)
        },
        activeTab() {
            return this.section ? activeTab(this.section) : null
        },
    },
    data() {
        return {
            sidebarOpen: false,
            sidebarCollapsed: readSidebarCollapsed(),
            // the row the page's #actions are teleported into; a plain element
            // that placeActions() moves, never rendered by Vue itself
            actionsRow: markRaw(makeActionsRow()),
            actionsObserver: null,
            actionsFrame: null,
            showingNavigationDropdown: false,
            nurseConsultationChannel: null,
            doctorConsultationChannel: null,
            receptionistConsultationChannel: null,
            busyPhase: 'idle',
            busyShowAfter: null,
            busyClearAfter: null,
            offRouterStart: null,
            offRouterFinish: null,
            errorModal: null,
            offRouterInvalid: null,
            offRouterException: null,
        }
    },
    mounted() {
        this.initializeChannels();
        this.initNavigationFeedback();
        this.initActionsRow();
    },
    // a page's #actions can appear or change after the first paint
    updated() {
        this.schedulePlaceActions();
    },
    unmounted() {
        if (this.actionsRow) this.actionsRow.remove();
    },
    beforeUnmount() {
        if (this.actionsObserver) this.actionsObserver.disconnect();
        if (this.actionsFrame) cancelAnimationFrame(this.actionsFrame);
        if (this.busyShowAfter) clearTimeout(this.busyShowAfter);
        if (this.busyClearAfter) clearTimeout(this.busyClearAfter);
        if (this.offRouterStart) this.offRouterStart();
        if (this.offRouterFinish) this.offRouterFinish();
        if (this.offRouterInvalid) this.offRouterInvalid();
        if (this.offRouterException) this.offRouterException();
    },
    methods: {
        // A page may send its own counts for the views inside it, keyed by the
        // tab param (props.tabCounts = { blockers: 4, portfolios: 2 }); else the
        // server's record count for the tab's screen.
        tabCount(tab) {
            const own = this.$page.props.tabCounts
            const key = tab.params && tab.params.tab
            if (own && key && own[key] !== undefined && own[key] !== null) return Number(own[key])
            return tab.count === null || tab.count === undefined ? null : Number(tab.count)
        },
        isActiveTab(tab) {
            return !!this.activeTab && this.activeTab === tab
        },
        // ---- the page's action buttons, in a row above its table ----------
        initActionsRow() {
            this.placeActions();
            // client-side tabs, filters and v-if panels change which table is
            // first: place the row again after any change under <main>
            if (typeof MutationObserver === 'undefined' || !this.$refs.main) return;
            this.actionsObserver = new MutationObserver((records) => {
                const row = this.actionsRow;
                if (records.every(r => row.contains(r.target))) return;
                this.schedulePlaceActions();
            });
            this.actionsObserver.observe(this.$refs.main, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'class', 'hidden'] });
        },
        schedulePlaceActions() {
            if (this.actionsFrame || typeof requestAnimationFrame === 'undefined') return;
            this.actionsFrame = requestAnimationFrame(() => {
                this.actionsFrame = null;
                this.placeActions();
            });
        },
        placeActions() {
            const row = this.actionsRow;
            const body = this.$refs.pageBody;
            const home = this.$refs.actionsHome;
            if (!row || !body || !home) return;
            // a page whose #actions render nothing (or not yet) leaves no gap
            row.hidden = row.children.length === 0;
            const target = actionsTarget(body, row);
            if (target) {
                if (row.parentNode !== target.parentNode || row.nextSibling !== target) target.parentNode.insertBefore(row, target);
            } else if (row.parentNode !== home) {
                home.appendChild(row);
            }
        },
        // the desktop sidebar's icon rail, remembered in this browser
        setSidebarCollapsed(value) {
            this.sidebarCollapsed = !!value
            try {
                localStorage.setItem(SIDEBAR_KEY, this.sidebarCollapsed ? '1' : '0')
            } catch (e) {
                /* storage unavailable: the choice holds for this page only */
            }
        },

        logout() {
            this.$inertia.post(route('logout'));
        },
        // "Loading" pill after 120ms (so instant pages don't flash), then a
        // short green "Done" flash when the visit completes.
        initNavigationFeedback() {
            this.offRouterStart = router.on('start', () => {
                if (this.busyShowAfter) clearTimeout(this.busyShowAfter);
                if (this.busyClearAfter) clearTimeout(this.busyClearAfter);
                this.busyShowAfter = setTimeout(() => { this.busyPhase = 'busy'; }, 120);
            });
            this.offRouterFinish = router.on('finish', (event) => {
                if (this.busyShowAfter) { clearTimeout(this.busyShowAfter); this.busyShowAfter = null; }
                if (event.detail.visit.completed) {
                    this.busyPhase = 'done';
                    this.busyClearAfter = setTimeout(() => { this.busyPhase = 'idle'; }, 650);
                } else {
                    this.busyPhase = 'idle';
                }
            });

            // Fail-loud: every failed action is explained in a modal instead of
            // a raw error page or silence.
            this.offRouterInvalid = router.on('invalid', (event) => {
                event.preventDefault();
                const status = event.detail.response?.status;
                let title = 'Action could not be completed';
                let message = 'The server could not complete this action. Nothing was changed.';
                if (status === 403) { title = 'No access rights'; message = 'You do not have the rights to view this page or perform this action. Contact your administrator if you believe you should have access.'; }
                else if (status === 419) { title = 'Session expired'; message = 'Your session has expired. Refresh the page and sign in again, then retry.'; }
                else if (status === 404) { title = 'Not found'; message = 'The item you tried to open no longer exists. Refresh and try again.'; }
                else if (status === 413) { message = 'The file or request is too large to process.'; }
                else if (status === 429) { message = 'Too many requests in a short time. Wait a moment and try again.'; }
                else if (status === 503) { message = 'The system is temporarily unavailable. Please try again shortly.'; }
                else if (status >= 500) { message = 'The server hit an error while processing this action. It has been logged.'; }
                this.errorModal = { title, message, status: status ?? null };
                this.busyPhase = 'idle';
            });
            this.offRouterException = router.on('exception', (event) => {
                event.preventDefault();
                this.errorModal = {
                    title: 'Connection problem',
                    message: 'Could not reach the server. Check your connection and try again. Nothing was saved.',
                    status: null,
                };
                this.busyPhase = 'idle';
            });
        },
        initializeChannels() {
            if (!this.$page.props.user) return;
            // Broadcasting is not configured; Echo is not bundled.
            if (typeof Echo === 'undefined') return;
            
            if (this.$page.props.user.current_role === 'nurse') {
                this.nurseConsultationChannel = Echo.private(`consultation-nurse.${this.$page.props.user.id}`)
                    .listen('ConsultationCreated', (e) => {
                        const audio = new Audio("/sounds/message-pop-alert.mp3");
                        audio.play();
                        let msg = `A new consultation pushed to your queue:` + e.consultation.patient.name;
                        this.$toast.info(msg, {
                            duration: 10000,
                            onClick: () => {
                                window.location = this.route('patients.consultations.vitals.index', e.consultation.id)
                            }
                        });
                        //refresh current page if consultations
                        if (this.route().current('consultations.index')) {
                            this.$inertia.reload();
                        }
                    });
            }
            if (this.$page.props.user.current_role === 'doctor') {
                this.doctorConsultationChannel = Echo.private(`consultation-doctor.${this.$page.props.user.id}`)
                    .listen('ConsultationNurseCompleted', (e) => {
                        const audio = new Audio("/sounds/message-pop-alert.mp3");
                        audio.play();
                        let msg = `A new consultation pushed to your queue:` + e.consultation.patient.name;
                        this.$toast.info(msg, {
                            duration: 10000,
                            onClick: () => {
                                window.location = this.route('patients.consultations.vitals.index', e.consultation.id)
                            }
                        });
                        //refresh current page if consultations
                        if (this.route().current('consultations.index')) {
                            this.$inertia.reload();
                        }
                    });
            }
            if (this.$page.props.user.current_role === 'receptionist') {
                this.receptionistConsultationChannel = Echo.private(`consultation-receptionist.${this.$page.props.user.id}`)
                    .listen('ConsultationCreated', (e) => {
                    });
            }

        }
    }
}
</script>
