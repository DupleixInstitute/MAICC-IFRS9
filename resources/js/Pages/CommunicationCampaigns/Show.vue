<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600"
                              :href="route('communication.campaigns.index')">
                    Campaigns
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> Campaign #{{ communicationCampaign.id }}
            </h2>
        </template>

        <div class=" mx-auto">
            <div class="md:flex md:items-start">
                <div class="relative mb-4  w-full md:w-3/12">
                    <div class="intro-y bg-white mt-5 lg:mt-0">
                        <div class="relative flex items-center p-5">
                            <div class="ml-4 mr-auto">
                                <div class="font-medium text-base">{{ communicationCampaign.name }}</div>
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
                                        <jet-dropdown-link
                                            :href="route('communication.campaigns.edit',communicationCampaign.id)">
                                            <font-awesome-icon icon="edit"/>
                                            Edit
                                        </jet-dropdown-link>
                                        <a href="#"
                                           class="block px-4 py-2 text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out"
                                           @click="deleteAction(communicationCampaign.id)">
                                            <font-awesome-icon icon="trash"
                                                               class="text-red-600 hover:text-red-900"/>
                                            Delete
                                        </a>
                                    </div>
                                </template>
                            </jet-dropdown>
                        </div>
                        <div class="p-5 border-t border-gray-200 dark:border-dark-5">
                            <div class="flex justify-between">
                                <span class="font-medium">Type</span>
                                <span
                                    v-if="communicationCampaign.campaign_type==='sms'">
                                        SMS
                                    </span>
                                <span
                                    v-if="communicationCampaign.campaign_type==='email'">
                                        Email
                                    </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Campaign Type</span>
                                <span
                                    v-if="communicationCampaign.trigger_type==='direct'">
                                        Direct
                                    </span>
                                <span
                                    v-if="communicationCampaign.trigger_type==='schedule'">
                                        Schedule
                                    </span>
                                <span
                                    v-if="communicationCampaign.trigger_type==='triggered'">
                                        Triggered
                                    </span>
                            </div>
                            <div v-if="communicationCampaign.trigger_type==='schedule'">
                                <div class="flex justify-between">
                                    <span class="font-medium">Scheduled Date</span>
                                    <span>
                                        {{
                                            communicationCampaign.scheduled_date
                                        }} {{ communicationCampaign.scheduled_time }}
                                        </span>
                                </div>
                            </div>
                            <div v-if="communicationCampaign.recurring">
                                <div class="flex justify-between">
                                    <span class="font-medium">Recur Frequency</span>
                                    <span>
                                        Every {{
                                            communicationCampaign.recur_frequency
                                        }} {{ communicationCampaign.recur_type }}
                                        </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="font-medium">Recur Start Date</span>
                                    <span>
                                        {{ communicationCampaign.recur_start_date }}
                                        </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="font-medium">Recur Next Run Date</span>
                                    <span>
                                        {{ communicationCampaign.recur_next_date }}
                                        </span>
                                </div>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Business Rule</span>
                                <span v-if="communicationCampaign.communication_campaign_business_rule">
                                        {{ communicationCampaign.communication_campaign_business_rule.name }}
                                    </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">From Days</span>
                                <span>
                                        {{ communicationCampaign.from_x }}
                                    </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">To Days</span>
                                <span>
                                        {{ communicationCampaign.to_y }}
                                    </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="font-medium">Status</span>
                                <span v-if="communicationCampaign.status==='pending'"
                                      class="px-2 rounded-full bg-yellow-100 text-yellow-800">
                                        pending
                                    </span>
                                <span v-if="communicationCampaign.status==='active'"
                                      class="px-2 rounded-full bg-blue-100 text-blue-800">
                                        active
                                    </span>
                                <span v-if="communicationCampaign.status==='closed'"
                                      class="px-2 rounded-full bg-green-100 text-green-800">
                                        closed
                                    </span>
                                <span v-if="communicationCampaign.status==='done'"
                                      class="px-2 rounded-full bg-green-100 text-green-800">
                                        done
                                    </span>
                                <span v-if="communicationCampaign.status==='inactive'"
                                      class="px-2 rounded-full bg-red-100 text-red-800">
                                        in-active
                                    </span>
                            </div>

                        </div>
                    </div>
                </div>
                <div class="w-full md:w-9/12  md:ml-4">
                    <div class="bg-white p-5">
                        <div class="flex items-center">
                            <div class="font-medium text-lg">Message</div>
                        </div>

                        <div class="mt-4" v-html="communicationCampaign.description"></div>
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
        communicationCampaign: Object,
        printInvoicePayment: Boolean,
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
            confirmingDeletion: false,
            selectedRecord: null,
            processing: false,
            pageTitle: "Campaign Details",
            pageDescription: "Campaign Details",
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
            this.$inertia.delete(this.route('communication.campaigns.destroy', this.communicationCampaign.id))
            this.confirmingDeletion = false
            window.location = route('communication.campaigns.index')
        },
    }
}
</script>
<style scoped>

</style>
