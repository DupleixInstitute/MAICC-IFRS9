<template>
    <Head :title="title"/>
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

        <!-- Static sidebar for desktop -->
        <div class="hidden md:fixed md:inset-y-0 md:z-20 md:flex md:w-72 md:flex-col"
             style="background: linear-gradient(172deg, #0b2b1a 0%, #082013 48%, #051509 100%); border-right: 1px solid rgba(212,160,23,0.14);">
            <SidebarNav/>
        </div>
        <div class="flex flex-1 flex-col md:pl-72">
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

            <main>
                <div class="py-6">
                    <div class="mx-auto px-4 sm:px-6 md:px-4">
                        <!-- Page Heading -->
                        <header class="" v-if="$slots.header">
                            <div class="mb-4">
                                <slot name="header"></slot>
                            </div>
                        </header>
                        <FlashMessages/>

                        <slot/>
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
            <div class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl">
                <div class="flex items-start gap-4 p-6">
                    <div class="flex h-11 w-11 flex-none items-center justify-center rounded-full bg-red-100">
                        <svg class="h-6 w-6 text-red-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 9v4M12 17h.01"/>
                            <path d="M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.7 3.86a2 2 0 0 0-3.4 0Z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-lg font-extrabold text-gray-900">{{ errorModal.title }}</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-gray-600">{{ errorModal.message }}</p>
                        <p v-if="errorModal.status" class="mt-2 text-xs font-semibold uppercase tracking-wider text-gray-400">
                            Error code {{ errorModal.status }}
                        </p>
                    </div>
                </div>
                <div class="flex justify-end gap-2 bg-gray-50 px-6 py-4">
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
import NotificationBell from "@/Jetstream/NotificationBell.vue"
import FlashMessages from '@/Jetstream/FlashMessages.vue'
import ApplicationMark from '@/Jetstream/ApplicationMark.vue'
import { router } from '@inertiajs/vue3'

export default {
    components: {
        SidebarNav,
        NotificationBell,
        FlashMessages,
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
        menu: [Object, Array]
    },
    data() {
        return {
            sidebarOpen: false,
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
    },
    beforeUnmount() {
        if (this.busyShowAfter) clearTimeout(this.busyShowAfter);
        if (this.busyClearAfter) clearTimeout(this.busyClearAfter);
        if (this.offRouterStart) this.offRouterStart();
        if (this.offRouterFinish) this.offRouterFinish();
        if (this.offRouterInvalid) this.offRouterInvalid();
        if (this.offRouterException) this.offRouterException();
    },
    methods: {

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
