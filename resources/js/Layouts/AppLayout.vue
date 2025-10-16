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
                        <DialogPanel class="relative flex w-full max-w-xs flex-1 flex-col bg-indigo-700 pt-5 pb-4">
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
                            <div class="flex flex-shrink-0 items-center px-4">
                                <ApplicationMark class="block h-8 w-auto"/>
                            </div>
                            <div class="mt-5 h-0 flex-1 overflow-y-auto">
                                <nav class="space-y-1 px-2">
                                    <div v-for="item in $page.props.menu" :key="item.name">
                                        <Link v-if="!item.dropdown" :href="route(item.route)"
                                              :class="[(route().current(item.route)||(item.route_check && route().current(item.route_check))) ? 'bg-indigo-800 text-white' : 'text-indigo-100 hover:bg-indigo-600', 'group flex items-center px-2 py-2 text-sm font-medium rounded-md']">
                                            <font-awesome-icon class="mr-3 h-6 w-6 flex-shrink-0 text-indigo-300"
                                                               aria-hidden="true" v-if="item.icon" :icon="item.icon"/>
                                            {{ item.name }}
                                        </Link>
                                        <DropdownMenu v-else :item="item"/>
                                    </div>
                                </nav>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                    <div class="w-14 flex-shrink-0" aria-hidden="true">
                        <!-- Dummy element to force sidebar to shrink to fit close icon -->
                    </div>
                </div>
            </Dialog>
        </TransitionRoot>

        <!-- Static sidebar for desktop -->
        <div class="hidden md:fixed md:inset-y-0 md:flex md:w-64 md:flex-col">
            <!-- Sidebar component, swap this element with another sidebar if you like -->
            <div class="flex flex-grow flex-col overflow-y-auto bg-indigo-700 pt-5">
                <div class="flex flex-shrink-0 items-center px-4">
                    <ApplicationMark class="block  w-auto"/>
                </div>
                <div class="mt-5 flex flex-1 flex-col">
                    <nav class="flex-1 space-y-1 px-2 pb-4">
                        <div v-for="item in $page.props.menu" :key="item.name">
                            <Link v-if="!item.dropdown" :href="route(item.route)"
                                  :class="[(route().current(item.route)||(item.route_check && route().current(item.route_check))) ? 'bg-indigo-800 text-white' : 'text-indigo-100 hover:bg-indigo-600', 'group flex items-center px-2 py-2 text-sm font-medium rounded-md']">
                                <font-awesome-icon class="mr-3 h-6 w-6 flex-shrink-0 text-indigo-300" aria-hidden="true"
                                                   v-if="item.icon" :icon="item.icon"/>
                                {{ item.name }}
                            </Link>
                            <DropdownMenu v-else :item="item"/>
                        </div>
                    </nav>
                </div>
            </div>
        </div>
        <div class="flex flex-1 flex-col md:pl-64">
            <div class="sticky top-0 z-10 flex h-16 flex-shrink-0 bg-white shadow">
                <button type="button"
                        class="border-r border-gray-200 px-4 text-gray-500 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500 md:hidden"
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
                                class="rounded-full bg-white p-1 text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            <span class="sr-only">View notifications</span>
                            <BellIcon class="h-6 w-6" aria-hidden="true"/>
                        </button>

                        <!-- Profile dropdown -->
                        <Menu as="div" class="relative ml-3">
                            <div>
                                <MenuButton
                                    class="flex max-w-xs items-center rounded-full bg-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
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
import DropdownMenu from "@/Jetstream/DropdownMenu.vue"
import FlashMessages from '@/Jetstream/FlashMessages.vue'
import ApplicationMark from '@/Jetstream/ApplicationMark.vue'

export default {
    components: {
        DropdownMenu,
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
