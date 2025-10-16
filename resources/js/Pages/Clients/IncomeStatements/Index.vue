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
                        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Income Statements</h2>
                        <inertia-link class="btn btn-blue" v-if="can('clients.income_statement.create')"
                                      :href="route('clients.income_statements.create',client.id)">
                            <span>Create </span>
                            <span class="hidden md:inline">Statement</span>
                        </inertia-link>
                    </div>
                    <div class="mt-4 relative overflow-x-auto">
                        <table class="w-full whitespace-no-wrap table-auto">
                            <thead class="bg-gray-50">
                            <tr class="text-left font-bold">
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Year</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Reporting Month</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">No of Months In Year</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Audit Status</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Annual Inflation Rate - Real</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Annual Inflation Rate - Nominal
                                </th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Sales</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Costs Of Good Sold</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Gross Profit</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Operating Expenses</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Operating Income</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Other Expenses</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Other Income</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Income Before Tax</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Income Tax</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Net Profit</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Action</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr v-if="!statements.data.length">
                                <td colspan="11" class="px-6 py-4 text-center">
                                    No Income Statements Yet
                                </td>
                            </tr>
                            <tr v-for="statement in statements.data" :key="statement.id"
                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">
                                    {{ statement.year }}
                                    </span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">
                                    {{ statement.reporting_month }}
                                    </span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">
                                    {{ statement.months_in_year }}
                                    </span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">
                                    {{ statement.audit_status }}
                                    </span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">
                                    {{ statement.real_annual_inflation_rate }}
                                    </span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">
                                    {{ statement.nominal_annual_inflation_rate }}
                                    </span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">
                                    {{ numberFormat(statement.total_sales) }}
                                    </span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">
                                    {{ numberFormat(statement.total_cost_of_goods_sold) }}
                                    </span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">
                                    {{ numberFormat(statement.total_gross_margin) }}
                                    </span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">
                                    {{ numberFormat(statement.total_operating_expenses) }}
                                    </span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">
                                    {{ numberFormat(statement.total_operating_profit) }}
                                    </span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">
                                    {{ numberFormat(statement.total_other_expenses) }}
                                    </span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">
                                    {{ numberFormat(statement.total_other_income) }}
                                    </span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">
                                    {{ numberFormat(statement.total_income_before_tax) }}
                                    </span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">
                                    {{ numberFormat(statement.total_income_tax) }}
                                    </span>
                                </td>
                                <td class="border-t">
                                    <span class="px-6 py-4 flex items-center">
                                    {{ numberFormat(statement.net_profit) }}
                                    </span>
                                </td>
                                <td class="border-t w-px pr-2">
                                    <div class=" flex items-center space-x-2">
                                        <inertia-link v-if="can('clients.income_statement.update')"
                                                      :href="route('clients.income_statements.edit', statement.id)"
                                                      tabindex="-1" class="text-indigo-600 hover:text-indigo-900">
                                            Edit
                                        </inertia-link>
                                        <a href="#" v-if="can('clients.income_statement.destroy')"
                                           @click="deleteAction(statement.id)"
                                           class="text-red-600 hover:text-red-900">Delete</a>
                                    </div>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                    <pagination v-if="statements.data.length" :links="statements.links"/>
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
        statements: Object,

    },
    data() {
        return {
            form: {
                processing: false
            },
            confirmingDeletion: false,
            selectedRecord: null,
            pageTitle: "Client Income Statements",
            pageDescription: "Client Income Statements",

        }
    },
    methods: {
        deleteAction(id) {
            this.confirmingDeletion = true
            this.selectedRecord = id
        },
        destroy() {

            this.$inertia.delete(this.route('clients.income_statements.destroy', this.selectedRecord))
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
