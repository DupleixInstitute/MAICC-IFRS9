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
                        <button type="button"
                                class="rounded-full bg-white p-1 text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-maiic-500 focus:ring-offset-2">
                            <span class="sr-only">View notifications</span>
                            <BellIcon class="h-6 w-6" aria-hidden="true"/>
                        </button>

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
import FlashMessages from '@/Jetstream/FlashMessages.vue'
import ApplicationMark from '@/Jetstream/ApplicationMark.vue'

export default {
    components: {
        SidebarNav,
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
        }
    },
    mounted() {
        this.initializeChannels();
    },
    methods: {

        logout() {
            this.$inertia.post(route('logout'));
        },
        initializeChannels() {
            if (!this.$page.props.user) return;
            
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
