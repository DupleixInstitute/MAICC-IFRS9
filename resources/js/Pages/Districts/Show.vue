<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('locations.districts.index')">
                    Districts
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> District #{{ district.id }}
            </h2>
        </template>

        <div class=" mx-auto">
            <div class="md:flex md:items-start">
                <div class="relative mb-4  w-full md:w-3/12">
                    <div class="intro-y bg-white mt-5 lg:mt-0">
                        <div class="relative flex items-center p-5">
                            <div class="ml-4 mr-auto">
                                <div class="font-medium text-base">District #{{ district.id }}</div>
                            </div>
                            <jet-dropdown align="right" width="60">
                                <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button type="button"
                                                    class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:bg-gray-50 hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                     viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                     stroke-width="1.5"
                                                     stroke-linecap="round" stroke-linejoin="round"
                                                     class="feather feather-more-horizontal w-5 h-5 text-gray-600 dark:text-gray-300">
                                            <circle cx="12" cy="12" r="1"></circle>
                                            <circle cx="19" cy="12" r="1"></circle>
                                            <circle cx="5" cy="12" r="1"></circle>
                                        </svg>
                                            </button>
                                        </span>
                                </template>

                                <template #content>
                                    <div class="w-60 shadow-xl bg-white rounded">
                                        <jet-dropdown-link :href="route('locations.districts.edit',district.id)">
                                            <font-awesome-icon icon="edit"/>
                                            Edit
                                        </jet-dropdown-link>
                                        <a href="#"
                                           class="block px-4 py-2 text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out"
                                           @click="deleteAction(district.id)">
                                            <font-awesome-icon icon="trash"
                                                               class="text-red-600 hover:text-red-900"/>
                                            Delete
                                        </a>
                                    </div>
                                </template>
                            </jet-dropdown>
                        </div>
                        <div class="p-5 border-t border-gray-200 dark:border-dark-5">
                            <div class="">
                                <span>{{ district.name }}</span>
                            </div>

                        </div>

                    </div>
                </div>
                <div class="w-full md:w-9/12  md:ml-4">
                    <div class="bg-white p-5">
                        <div class="flex justify-between">
                            <h3>Wards</h3>
                            <inertia-link class="btn btn-blue" v-if="can('locations.create')" :href="route('locations.wards.create',{district_id:district.id})">
                                <span>Create </span>
                                <span class="hidden md:inline">Ward</span>
                            </inertia-link>
                        </div>
                        <div class=" overflow-x-auto">
                            <table class="w-full whitespace-no-wrap mt-4">
                                <thead class="bg-gray-50">
                                <tr class="text-left font-bold">
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Name</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Action</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="ward in district.wards" :key="ward.id"
                                    class="hover:bg-gray-100 focus-within:bg-gray-100">
                                    <td class="border-t">
                                      <span class="px-6 py-4 flex items-center">
                                          {{ ward.name }}
                                      </span>
                                    </td>

                                    <td class="border-t w-px pr-2">
                                        <div class=" flex items-center space-x-2">
                                            <inertia-link v-if="can('locations.index')"
                                                          :href="route('locations.wards.show', ward.id)"
                                                          tabindex="-1" class="text-indigo-600 hover:text-indigo-900">
                                                View
                                            </inertia-link>
                                            <inertia-link v-if="can('locations.update')"
                                                          :href="route('locations.wards.edit', ward.id)"
                                                          tabindex="-1" class="text-indigo-600 hover:text-indigo-900">
                                                Edit
                                            </inertia-link>
                                            <a href="#" v-if="can('locations.destroy')"
                                               @click="deleteItemAction(ward.id)"
                                               class="text-red-600 hover:text-red-900">Delete</a>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="district.wards.length === 0">
                                    <td class="border-t px-6 py-4 text-center" colspan="2">No wards found.</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>


        <jet-confirmation-modal :show="confirmingDeletion" @close="confirmingDeletion = false">
            <template #title>
                Delete Record
            </template>

            <template #content>
                Are you sure you want to delete record?
            </template>

            <template #footer>
                <jet-secondary-button @click.native="confirmingDeletion = false">
                    Nevermind
                </jet-secondary-button>

                <jet-danger-button class="ml-2" @click.native="destroy" :class="{ 'opacity-25': processing }"
                                   :disabled="processing">
                    Delete Record
                </jet-danger-button>
            </template>
        </jet-confirmation-modal>
        <jet-confirmation-modal :show="confirmItemDeletion" @close="confirmItemDeletion = false">
            <template #title>
                Delete Record
            </template>

            <template #content>
                Are you sure you want to delete record?
            </template>

            <template #footer>
                <jet-secondary-button @click.native="confirmItemDeletion = false">
                    Nevermind
                </jet-secondary-button>

                <jet-danger-button class="ml-2" @click.native="destroyItem"
                                   :class="{ 'opacity-25': processing }"
                                   :disabled="processing">
                    Delete Record
                </jet-danger-button>
            </template>
        </jet-confirmation-modal>

        <teleport to="head">
            <title>{{ pageTitle }}</title>
            <meta property="og:description" :content="pageDescription">
        </teleport>
    </app-layout>
</template>
<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetButton from "@/Jetstream/Button.vue";
import JetInput from "@/Jetstream/Input.vue";
import JetInputError from "@/Jetstream/InputError.vue";
import JetCheckbox from "@/Jetstream/Checkbox.vue";
import JetLabel from "@/Jetstream/Label.vue";
import Select from "@/Jetstream/Select.vue";
import FileInput from "@/Jetstream/FileInput.vue";
import TextareaInput from "@/Jetstream/TextareaInput.vue";
import JetDropdown from '@/Jetstream/Dropdown.vue'
import JetDropdownLink from '@/Jetstream/DropdownLink.vue'
import JetDialogModal from '@/Jetstream/DialogModal.vue'
import JetSecondaryButton from '@/Jetstream/SecondaryButton.vue'
import JetConfirmationModal from '@/Jetstream/ConfirmationModal.vue'
import JetDangerButton from '@/Jetstream/DangerButton.vue'
import print from 'print-js'

export default {
    props: {
        district: Object,
    },
    components: {
        Select,
        AppLayout,
        JetButton,
        JetInput,
        JetCheckbox,
        JetLabel,
        JetInputError,
        FileInput,
        TextareaInput,
        JetDropdown,
        JetDropdownLink,
        JetDialogModal,
        JetSecondaryButton,
        JetConfirmationModal,
        JetDangerButton,

    },
    data() {
        return {
            confirmClaim: false,
            confirmItemDeletion: false,
            confirmingDeletion: false,
            selectedItemRecord: null,
            selectedRecord: null,
            processing: false,
            pageTitle: "District Details",
            pageDescription: "District Details",
        }

    },
    mounted() {

    },
    methods: {

        deleteAction(id) {
            this.confirmingDeletion = true
            this.selectedRecord = id
        },
        destroy() {
            this.$inertia.delete(this.route('locations.districts.destroy', this.selectedRecord))
            this.confirmingDeletion = false
            window.location = route('locations.districts.index')
        },
        deleteItemAction(id) {
            this.confirmItemDeletion = true
            this.selectedItemRecord = id
        },
        destroyItem() {
            this.$inertia.delete(this.route('locations.eards.destroy', this.selectedItemRecord))
            this.confirmItemDeletion = false
        },
    }
}
</script>
<style scoped>

</style>
