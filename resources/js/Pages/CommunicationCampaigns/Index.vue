<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Campaigns
            </h2>
        </template>
        <div class=" mx-auto mb-4 flex justify-between items-center">
            <filter-search v-model="form.search" class="w-full max-w-md mr-4" @reset="reset">
                <div class="w-80 mt-2 px-4 py-6 shadow-xl bg-white rounded">
                    <div class="mb-2">
                        <jet-label for="trigger_type" value="Trigger Type"/>
                        <select v-model="form.trigger_type" id="trigger_type"
                                class="mt-1 w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                            <option :value="null"/>
                            <option value="direct">Direct</option>
                            <option value="schedule">Scheduled</option>
                            <option value="triggered">Triggered</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <jet-label for="campaign_type" value="Campaign Type"/>
                        <select v-model="form.campaign_type" id="campaign_type"
                                class="mt-1 w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                            <option :value="null"/>
                            <option value="sms">SMS</option>
                            <option value="email">Email</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <jet-label for="status" value="Status"/>
                        <select v-model="form.status" id="status"
                                class="mt-1 w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                            <option :value="null"/>
                            <option value="pending">Pending</option>
                            <option value="active">Active</option>
                            <option value="inactive">In-Active</option>
                            <option value="done">Done</option>
                        </select>
                    </div>
                </div>
            </filter-search>
            <inertia-link v-if="can('communication.campaigns.create')" class="btn btn-blue"
                          :href="route('communication.campaigns.create')">
                <span>Create </span>
                <span class="hidden md:inline">Campaign</span>
            </inertia-link>
        </div>
        <div class=" mx-auto">
            <div class="bg-white rounded shadow overflow-x-auto">
                <table class="w-full whitespace-no-wrap">
                    <thead class="bg-gray-50">
                    <tr class="text-left font-bold">
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Name</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Type</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Campaign Type</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Status</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="communicationCampaign in communicationCampaigns.data" :key="communicationCampaign.id"
                        class="hover:bg-gray-100 focus-within:bg-gray-100">
                        <td class="border-t">
                            <span class="px-6 py-4 flex items-center">
                                {{ communicationCampaign.name }}
                            </span>
                        </td>
                        <td class="border-t">
                             <span class="px-6 py-4 flex items-center"
                                   v-if="communicationCampaign.campaign_type==='sms'">
                                SMS
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="communicationCampaign.campaign_type==='email'">
                                Email
                            </span>
                        </td>
                        <td class="border-t">
                             <span class="px-6 py-4 flex items-center"
                                   v-if="communicationCampaign.trigger_type==='direct'">
                                Direct
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="communicationCampaign.trigger_type==='schedule'">
                                Schedule
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="communicationCampaign.trigger_type==='triggered'">
                                Triggered
                            </span>
                        </td>
                        <td class="border-t">
                             <span class="px-6 py-4 flex items-center">
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
                            </span>
                        </td>
                        <td class="border-t w-px pr-2">
                            <div class=" flex items-center space-x-2">
                                <inertia-link v-if="can('communication.campaigns.index')"
                                              :href="route('communication.campaigns.show', communicationCampaign.id)"
                                              tabindex="-1" class="text-indigo-600 hover:text-indigo-900">
                                    View
                                </inertia-link>
                                <inertia-link
                                    v-if="can('communication.campaigns.update') && communicationCampaign.trigger_type!=='direct'"
                                    :href="route('communication.campaigns.edit', communicationCampaign.id)"
                                    tabindex="-1" class="text-indigo-600 hover:text-indigo-900">
                                    Edit
                                </inertia-link>
                                <a href="#" v-if="can('communication.campaigns.destroy')"
                                   @click="deleteAction(communicationCampaign.id)"
                                   class="text-red-600 hover:text-red-900">Delete</a>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="communicationCampaigns.data.length === 0">
                        <td class="border-t px-6 py-4" colspan="5">No Campaigns found.</td>
                    </tr>
                    </tbody>
                </table>

            </div>
            <pagination :links="communicationCampaigns.links"/>
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

                <jet-danger-button class="ml-2" @click.native="destroy" :class="{ 'opacity-25': form.processing }"
                                   :disabled="form.processing">
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
import Icon from '@/Jetstream/Icon.vue'
import Pagination from '@/Jetstream/Pagination.vue'
import SearchFilter from '@/Jetstream/SearchFilter.vue'
import FilterSearch from '@/Jetstream/FilterSearch.vue'
import mapValues from 'lodash/mapValues'
import pickBy from 'lodash/pickBy'
import throttle from 'lodash/throttle'
import JetLabel from '@/Jetstream/Label.vue'
import SelectInput from '@/Jetstream/SelectInput.vue'
import JetConfirmationModal from '@/Jetstream/ConfirmationModal.vue'
import JetDangerButton from '@/Jetstream/DangerButton.vue'
import JetSecondaryButton from '@/Jetstream/SecondaryButton.vue'

export default {
    components: {
        AppLayout,
        Icon,
        Pagination,
        SearchFilter,
        FilterSearch,
        JetLabel,
        SelectInput,
        JetConfirmationModal,
        JetDangerButton,
        JetSecondaryButton,
    },
    props: {
        communicationCampaigns: Object,
        filters: Object,

    },
    data() {
        return {
            form: {
                search: this.filters.search,
                trigger_type: this.filters.trigger_type,
                campaign_type: this.filters.campaign_type,
                status: this.filters.status,
                communication_campaign_business_rule_id: this.filters.communication_campaign_business_rule_id,
                processing: false
            },
            confirmingDeletion: false,
            selectedRecord: null,
            pageTitle: "Campaigns",
            pageDescription: "Manage Campaigns",

        }
    },
    watch: {
        form: {
            handler: _.debounce(function () {
                let query = pickBy(this.form)
                this.$inertia.get(this.route('communication.campaigns.index', Object.keys(query).length ? query : {}))
            }, 500),
            deep: true,
        },
    },
    methods: {
        reset() {
            this.form = mapValues(this.form, () => null)
        },
        deleteAction(id) {
            this.confirmingDeletion = true
            this.selectedRecord = id
        },
        destroy() {

            this.$inertia.delete(this.route('communication.campaigns.destroy', this.selectedRecord))
            this.confirmingDeletion = false
        },
    },
}
</script>

<style scoped>

</style>
