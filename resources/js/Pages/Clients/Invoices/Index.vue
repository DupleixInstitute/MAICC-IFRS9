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
                    <div class="mt-4 relative overflow-x-auto">
                        <table class="w-full whitespace-no-wrap table-auto">
                            <thead class="bg-gray-50">
                            <tr class="text-left font-bold">
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Account</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">30 Days</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">60 Days</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">90 Days</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">120 Days</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">150 Days</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">180 Days</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Balance</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">Client</span>
                                </td>
                                <td class="border-t">
                                    <span
                                        class="px-6 py-4 flex items-center">{{
                                            $filters.formatNumber(balancesData.thirtyDaysCashAmount)
                                        }}</span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">{{
                                            $filters.formatNumber(balancesData.sixtyDaysCashAmount)
                                        }}</span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">{{
                                            $filters.formatNumber(balancesData.ninetyDaysCashAmount)
                                        }}</span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">{{
                                            $filters.formatNumber(balancesData.oneTwentyDaysCashAmount)
                                        }}</span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">{{
                                            $filters.formatNumber(balancesData.oneFiftyDaysCashAmount)
                                        }}</span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">{{
                                            $filters.formatNumber(balancesData.oneEightyDaysCashAmount)
                                        }}</span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">{{
                                            $filters.formatNumber(balancesData.cashBalance)
                                        }}</span>
                                </td>
                            </tr>
                            <tr class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">CoPayers</span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">{{
                                            $filters.formatNumber(balancesData.thirtyDaysCoPayerAmount)
                                        }}</span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">{{
                                            $filters.formatNumber(balancesData.sixtyDaysCoPayerAmount)
                                        }}</span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">{{
                                            $filters.formatNumber(balancesData.ninetyDaysCoPayerAmount)
                                        }}</span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">{{
                                            $filters.formatNumber(balancesData.oneTwentyDaysCoPayerAmount)
                                        }}</span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">{{
                                            $filters.formatNumber(balancesData.oneFiftyDaysCoPayerAmount)
                                        }}</span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">{{
                                            $filters.formatNumber(balancesData.oneEightyDaysCoPayerAmount)
                                        }}</span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">{{
                                            $filters.formatNumber(balancesData.coPayerBalance)
                                        }}</span>
                                </td>
                            </tr>

                            </tbody>
                            <tfoot>
                            <tr>
                                <th class="text-right border-t" colspan="7">
                                    <span class="px-6 py-4  text-right">Total</span>
                                </th>
                                <th class="border-t">
                                    <span class="px-6 py-4 flex items-center">{{
                                            $filters.formatNumber(balancesData.totalBalance)
                                        }}</span>
                                </th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="flex justify-between mt-2 ">
                        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Invoices</h2>
                        <inertia-link class="btn btn-blue" v-if="can('billing.invoices.create')"
                                      :href="route('clients.invoices.create',client.id)">
                            <span>Create </span>
                            <span class="hidden md:inline">Invoice</span>
                        </inertia-link>
                    </div>
                    <div class="mt-4 relative overflow-x-auto">
                        <table class="w-full whitespace-no-wrap table-auto">
                            <thead class="bg-gray-50">
                            <tr class="text-left font-bold">
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">ID</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Date</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Amount</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Cash</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">CoPayer</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Balance</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Status</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-if="!invoices.data.length">
                                <td colspan="8" class="px-6 py-4 text-center">
                                    No Invoices Yet
                                </td>
                            </tr>
                            <tr v-for="invoice in invoices.data" :key="invoice.id"
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">
                                    {{ invoice.id }}
                                    </span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">
                                    {{ invoice.date }}
                                    </span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">
                                    {{ invoice.amount }}
                                    </span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">
                                    {{ invoice.cash_amount }}
                                    </span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">
                                    {{ invoice.co_payer_amount }}
                                    </span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">
                                    {{ invoice.balance }}
                                    </span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center"
                                          v-if="invoice.status=='partially_paid'">
                                    Partially Paid
                                    </span>
                                    <span class="px-6 py-4 flex items-center"
                                          v-if="invoice.status=='unpaid'">
                                     Unpaid
                                    </span>
                                    <span class="px-6 py-4 flex items-center"
                                          v-if="invoice.status=='paid'">
                                    Paid
                                    </span>
                                </td>
                                <td class="border-t w-px pr-2">
                                    <div class=" flex items-center space-x-2">
                                        <inertia-link v-if="can('billing.invoices.index')"
                                                      :href="route('billing.invoices.show', invoice.id)"
                                                      tabindex="-1" class="text-indigo-600 hover:text-indigo-900">
                                            View
                                        </inertia-link>
                                        <inertia-link v-if="can('billing.invoices.update')"
                                                      :href="route('billing.invoices.edit', invoice.id)"
                                                      tabindex="-1" class="text-indigo-600 hover:text-indigo-900">
                                            Edit
                                        </inertia-link>
                                        <a href="#" v-if="can('billing.invoices.destroy')"
                                           @click="deleteAction(invoice.id)"
                                           class="text-red-600 hover:text-red-900">Delete</a>
                                    </div>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <pagination :links="invoices.links"/>
                </div>
            </div>
        </div>
        <jet-confirmation-modal :show="confirmingDeletion" @close="confirmingDeletion = false">
            <template #title>
                Delete Record
            </template>

            <template #content>
                Are you sure you want to delete your account?
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
        ClientMenu,
    },
    props: {
        client: Object,
        invoices: Object,
        balancesData: Object,

    },
    data() {
        return {
            form: {
                processing: false
            },
            confirmingDeletion: false,
            selectedRecord: null,
            pageTitle: "Clients",
            pageDescription: "Manage Clients",

        }
    },
    methods: {
        deleteAction(id) {
            this.confirmingDeletion = true
            this.selectedRecord = id
        },
        destroy() {

            this.$inertia.delete(this.route('clients.invoices.destroy', this.selectedRecord))
            this.confirmingDeletion = false
        },
    },
}
</script>

<style scoped>

</style>
