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
                        <h2 class="font-semibold text-xl text-gray-800 leading-tight">SUMMARY BALANCE SHEET</h2>
                        <div>
                            <inertia-link class="btn btn-blue ml-2" v-if="can('clients.balance_sheet.index')"
                                          :href="route('clients.balance_sheets.index',client.id)">
                                <span>Back </span>
                            </inertia-link>
                        </div>

                    </div>
                    <div class="mt-4 relative overflow-x-auto">
                        <table class="w-full whitespace-no-wrap table-auto">
                            <thead class="bg-gray-50">
                            <tr class="text-left font-bold">
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Summary Balance Sheet as at</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500" v-for="item in sheets">
                                    {{ item.year }}
                                </th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-6 py-4" :colspan="sheets.length+1">
                                    <span class="font-bold">Assets</span>
                                </td>
                            </tr>
                            <tr class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-6 py-4">
                                    <span class="">Current Assets</span>
                                </td>
                                <td class="border-t px-6 py-4" v-for="sheet in sheets">
                                    <span class="">{{ $filters.currency(sheet.total_current_assets) }}</span>
                                </td>
                            </tr>
                            <tr class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-6 py-4">
                                    <span class="">Non-current Assets</span>
                                </td>
                                <td class="border-t px-6 py-4" v-for="sheet in sheets">
                                    <span class="">{{
                                            $filters.currency(sheet.total_other_current_assets + sheet.total_other_assets + sheet.total_fixed_assets)
                                        }}</span>
                                </td>
                            </tr>
                            <tr class="bg-blue-700 text-white">
                                <td class="border-t px-6 py-4">
                                    <span class="">Total Assets</span>
                                </td>
                                <td class="border-t px-6 py-4" v-for="sheet in sheets">
                                    <span class="">{{
                                            $filters.currency(sheet.total_assets)
                                        }}</span>
                                </td>
                            </tr>
                            <tr class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-6 py-4" :colspan="sheets.length+1">
                                    <span class="font-bold">Equity and Liabilities</span>
                                </td>
                            </tr>
                            <tr class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-6 py-4">
                                    <span class="">Equity</span>
                                </td>
                                <td class="border-t px-6 py-4" v-for="sheet in sheets">
                                    <span class="">{{
                                            $filters.currency(sheet.total_equity)
                                        }}</span>
                                </td>
                            </tr>
                            <tr class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-6 py-4">
                                    <span class="">Current Liabilities</span>
                                </td>
                                <td class="border-t px-6 py-4" v-for="sheet in sheets">
                                    <span class="">{{
                                            $filters.currency(sheet.total_current_liabilities)
                                        }}</span>
                                </td>
                            </tr>
                            <tr class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-6 py-4">
                                    <span class="">Non-current Liabilities</span>
                                </td>
                                <td class="border-t px-6 py-4" v-for="sheet in sheets">
                                    <span class="">{{
                                            $filters.currency(sheet.total_long_term_liabilities)
                                        }}</span>
                                </td>
                            </tr>
                            <tr class="bg-blue-700 text-white">
                                <td class="border-t px-6 py-4">
                                    <span class="">Total Equity and Liabilities</span>
                                </td>
                                <td class="border-t px-6 py-4" v-for="sheet in sheets">
                                    <span class="">{{
                                            $filters.currency(sheet.total_liabilities)
                                        }}</span>
                                </td>
                            </tr>
                            <tr class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-6 py-4">
                                    <span class="">Balance Sheet Check</span>
                                </td>
                                <td class="border-t" v-for="sheet in sheets">
                                    <div class=" px-6 py-4 text-white w-full"
                                         :class="sheet.total_assets!==sheet.total_liabilities?'bg-red-800':'bg-green-800'">
                                        {{
                                            $filters.currency(sheet.total_assets - sheet.total_liabilities)
                                        }}
                                    </div>
                                </td>
                            </tr>
                            <tr class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-6 py-4" :colspan="sheets.length+1">
                                    <span class="font-bold">Other Summary Balances</span>
                                </td>
                            </tr>
                            <tr class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-6 py-4">
                                    <span class="">Working Capital</span>
                                </td>
                                <td class="border-t" v-for="sheet in sheets">
                                    <div class=" px-6 py-4 text-white w-full"
                                         :class="sheet.total_working_capital>0?'bg-green-800':'bg-red-800'">
                                        {{
                                            $filters.currency(sheet.total_working_capital)
                                        }}
                                    </div>
                                </td>
                            </tr>
                            <tr class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t px-6 py-4">
                                    <span class="">Tangible Net Worth</span>
                                </td>
                                <td class="border-t px-6 py-4" v-for="sheet in sheets">
                                    <span class="">{{
                                            $filters.currency(sheet.total_tangible_net_worth)
                                        }}</span>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

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
    },
}
</script>

<style scoped>

</style>
