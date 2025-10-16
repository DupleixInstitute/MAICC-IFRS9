<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('clients.index')">Clients
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> {{ client.name }}
            </h2>
        </template>
        <div class="mx-auto">
            <div class="md:flex md:items-start">
                <div class="bg-white relative shadow-xl mb-4 mt-20 w-full md:w-3/12">
                    <div class="col-span-12 lg:col-span-4 xxl:col-span-3 flex lg:block flex-col-reverse">
                        <div class="intro-y box mt-5 lg:mt-0">
                            <client-menu :client="client"></client-menu>
                        </div>
                    </div>

                </div>
                <div class="w-full md:w-9/12 p-4 md:ml-4 bg-white sm:mt-4">
                    <div class="flex justify-between ">
                        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Balance Sheets</h2>
                        <div>
                            <inertia-link class="btn btn-blue" v-if="can('clients.balance_sheet.index')"
                                          :href="route('clients.balance_sheets.summary',client.id)">
                                <span>View Summary </span>
                            </inertia-link>
                            <inertia-link class="btn btn-blue ml-2" v-if="can('clients.balance_sheet.create')"
                                          :href="route('clients.balance_sheets.create',client.id)">
                                <span>Create </span>
                                <span class="hidden md:inline">Sheet</span>
                            </inertia-link>
                        </div>

                    </div>
                    <div class="mt-4 relative overflow-x-auto">
                        <table class="w-full whitespace-no-wrap table-auto">
                            <thead class="bg-gray-50">
                            <tr class="text-left font-bold">
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Year</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Total Assets</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Total Liabilities</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Working Capital</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-if="!sheets.data.length">
                                <td colspan="11" class="px-6 py-4 text-center">
                                    No Balance Sheets Yet
                                </td>
                            </tr>
                            <tr v-for="sheet in sheets.data" :key="sheet.id"
                                class="hover:bg-gray-100 focus-within:bg-gray-100">

                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">
                                    {{ sheet.year }}
                                        <span v-if="sheet.total_assets!==sheet.total_equity_liabilities"
                                              class="ml-2 text-red-600" title="Not balanced"><font-awesome-icon
                                            icon="times-circle"></font-awesome-icon></span>
                                        <span v-if="sheet.total_assets===sheet.total_equity_liabilities"
                                              class="ml-2 text-green-600" title="Balanced"><font-awesome-icon
                                            icon="check-circle"></font-awesome-icon></span>
                                    </span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">
                                        {{ numberFormat(sheet.total_assets) }}

                                    </span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">
                                    {{ numberFormat(sheet.total_liabilities) }}
                                    </span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">
                                    {{ numberFormat(sheet.total_working_capital) }}
                                    </span>
                                </td>
                                <td class="border-t w-px pr-2">
                                    <div class=" flex items-center space-x-2">
                                        <inertia-link v-if="can('clients.balance_sheet.update')"
                                                      :href="route('clients.balance_sheets.edit', sheet.id)"
                                                      tabindex="-1" class="text-indigo-600 hover:text-indigo-900">
                                            Edit
                                        </inertia-link>
                                        <a href="#" v-if="can('clients.balance_sheet.destroy')"
                                           @click="deleteAction(sheet.id)"
                                           class="text-red-600 hover:text-red-900">Delete</a>
                                    </div>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <pagination v-if="sheets.data.length" :links="sheets.links"/>
                </div>
            </div>
        </div>
        <jet-confirmation-modal :show="confirmingDeletion" @close="confirmingDeletion = false">
            <template #title>
                Delete Record
            </template>

            <template #content>
                Are you sure you want to delete this record?
            </template>

            <template #footer>
                <jet-secondary-button @click.native="confirmingDeletion = false">
                    Nevermind
                </jet-secondary-button>

                <jet-danger-button class="ml-2" @click.native="destroy" :class="{ 'opacity-25': form.processing }"
                                   :disabled="form.processing">
                    Delete
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
import ClientMenu from '@/Pages/Clients/ClientMenu.vue'
import {FontAwesomeIcon} from "@fortawesome/vue-fontawesome";

export default {
    components: {
        FontAwesomeIcon,
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
        ClientMenu,
    },
    props: {
        client: Object,
        sheets: Object,

    },
    data() {
        return {
            form: {
                processing: false
            },
            confirmingDeletion: false,
            selectedRecord: null,
            pageTitle: "Client Balance Sheets",
            pageDescription: "Client Balance Sheets",

        }
    },
    methods: {
        deleteAction(id) {
            this.confirmingDeletion = true
            this.selectedRecord = id
        },
        destroy() {

            this.$inertia.delete(this.route('clients.balance_sheets.destroy', this.selectedRecord))
            this.confirmingDeletion = false
        },
        numberFormat(value) {
            return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
        },

    },
}
</script>

<style scoped>

</style>


