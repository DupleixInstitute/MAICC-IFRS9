<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Chart of Accounts
            </h2>
        </template>
        <div class=" mx-auto mb-4 flex justify-between items-center">
            <filter-search v-model="form.search" class="w-full max-w-md mr-4" @reset="reset">
                <div class="w-80 mt-2 px-4 py-6 shadow-xl bg-white rounded">
                    <div class="mb-2">
                        <jet-label for="stage" value="Account Type"/>
                        <select
                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                            name="account_type" v-model="form.account_type" id="account_type">
                            <optgroup label="Asset">
                                <option value="fixed_asset">Fixed Asset</option>
                                <option value="current_asset">Current Asset</option>
                                <option value="other_current_asset">Other Current
                                    Asset
                                </option>
                                <option value="other_asset">Other Asset</option>
                                <option value="cash">Cash</option>
                                <option value="bank">Bank</option>
                                <option value="stock">Stock</option>
                            </optgroup>
                            <optgroup label="Liability">
                                <option value="other_current_liability">Other Current Liability</option>
                                <option value="credit_card">Credit Card</option>
                                <option value="long_term_liability">Long Term Liability</option>
                                <option value="other_liability">Other Liability</option>
                                <option value="income_tax">Income Tax</option>
                            </optgroup>
                            <optgroup label="Expense">
                                <option value="expense">Expense</option>
                                <option value="cost_of_goods_sold">Cost of Goods Sold</option>
                                <option value="other_expense">Other Expense</option>
                            </optgroup>
                            <optgroup label="Equity">
                                <option value="equity">Equity</option>
                            </optgroup>
                        </select>
                    </div>
                </div>
            </filter-search>
            <inertia-link v-if="can('chart_of_accounts.create')" class="btn btn-blue"
                          :href="route('chart_of_accounts.create')">
                <span>Create </span>
                <span class="hidden md:inline">Chart of Account</span>
            </inertia-link>
        </div>
        <div class=" mx-auto">
            <div class="bg-white rounded shadow overflow-x-auto">
                <table class="w-full whitespace-no-wrap">
                    <thead class="bg-gray-50">
                    <tr class="text-left font-bold">
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Name</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">GL Code</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Type</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Balance</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Active</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="chartOfAccount in chartOfAccounts.data" :key="chartOfAccount.id"
                        class="hover:bg-gray-100 focus-within:bg-gray-100">
                        <td class="border-t">
                            <span class="px-6 py-4 flex items-center">
                                {{ chartOfAccount.name }}
                            </span>
                        </td>
                        <td class="border-t">
                             <span class="px-6 py-4 flex items-center">
                                {{ chartOfAccount.gl_code }}
                            </span>
                        </td>
                        <td class="border-t">
                             <span class="px-6 py-4 flex items-center"
                                   v-if="chartOfAccount.account_type==='fixed_asset'">
                                Fixed Asset
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='current_asset'">
                                Current Asset
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='other_current_asset'">
                                Other Current Asset
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='other_asset'">
                                Other Asset
                            </span>
                            <span class="px-6 py-4 flex items-center" v-if="chartOfAccount.account_type==='cash'">
                                Cash
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='bank'">
                                Bank
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='stock'">
                                Stock
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='other_current_liability'">
                                Other Current Liability
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='credit_card'">
                                Credit Card
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='long_term_liability'">
                                Long Term Liability
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='other_liability'">
                                Other Liability
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='income_tax'">
                                Income Tax
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='income'">
                                Income
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='other_income'">
                               Other Income
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='expense'">
                                Expense
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='cost_of_goods_sold'">
                                Cost of Goods Sold
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='other_expense'">
                                Other Expense
                            </span>
                            <span class="px-6 py-4 flex items-center" v-if="chartOfAccount.account_type==='equity'">
                                Equity
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='accounts_receivable'">
                                Accounts Receivable
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='accounts_payable'">
                                Accounts Payable
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='property_plant_equipment'">
                               Asset- Property, plant and equipment
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='investment_property'">
                                Asset- Investment property
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='right_of_use_asset'">
                                Asset-Right of use assets
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='intangible_asset'">
                                Asset-Intangible assets
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='other_long_term_liability'">
                                Other Long Term Liability
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='finance_lease_liability'">
                                Finance lease liability
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='deferred_income_tax'">
                                Deferred Income Tax
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='cost_of_goods_sold_depreciation'">
                                Expense-Cost of Goods Sold-Depreciation
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='depreciation_property_plant_equipment'">
                                Expense- Depreciation- property, plant and equipment
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='depreciation_right_of_use_assets'">
                               Expense - Depreciation - right of use assets
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='depreciation_investment_property'">
                               Expense - Depreciation - investment property
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='amortisation_intangible_assets'">
                                Expense - Amortisation - intangible assets
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='short_term_leases'">
                                Expense - Short-term leases
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='income_tax_expense'">
                               Income Tax Expense
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='net_finance_costs_banks'">
                                Expense - Net Finance Costs-banks
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='net_finance_costs_finance_leases'">
                                Expense - Net Finance Costs-finance leases
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='retained_earning'">
                                Equity - Retained earnings or Accumulated profits
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='capital_contribution'">
                                 Equity - Capital/owner's contribution
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='reserve'">
                                 Equity - Reserves
                            </span>
                            <span class="px-6 py-4 flex items-center"
                                  v-if="chartOfAccount.account_type==='current_liability'">
                                 Current Liability
                            </span>
                        </td>
                        <td class="border-t">
                             <span class="px-6 py-4 flex items-center">
                                {{ $filters.formatNumber(chartOfAccount.balance) }}
                            </span>
                        </td>
                        <td class="border-t">
                            <span class="px-6 py-4 flex items-center">
                                <span v-if="chartOfAccount.active">Yes</span>
                                <span v-if="!chartOfAccount.active">No</span>
                            </span>
                        </td>
                        <td class="border-t w-px pr-2">
                            <div class=" flex items-center space-x-2">
                                <inertia-link v-if="can('chart_of_accounts.update')"
                                              :href="route('chart_of_accounts.edit', chartOfAccount.id)"
                                              tabindex="-1" class="text-indigo-600 hover:text-indigo-900">
                                    Edit
                                </inertia-link>
                                <a href="#" v-if="can('chart_of_accounts.destroy')"
                                   @click="deleteAction(chartOfAccount.id)" class="text-red-600 hover:text-red-900">Delete</a>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="chartOfAccounts.data.length === 0">
                        <td class="border-t px-6 py-4" colspan="6">No Chart of Accounts found.</td>
                    </tr>
                    </tbody>
                </table>

            </div>
            <pagination :links="chartOfAccounts.links"/>
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
        chartOfAccounts: Object,
        filters: Object,

    },
    data() {
        return {
            form: {
                search: this.filters.search,
                account_type: this.filters.account_type,
                processing: false
            },
            confirmingDeletion: false,
            selectedRecord: null,
            pageTitle: "Chart of Accounts",
            pageDescription: "Manage Chart of Accounts",

        }
    },
    watch: {
        form: {
            handler: _.debounce(function () {
                let query = pickBy(this.form)
                this.$inertia.get(this.route('chart_of_accounts.index', Object.keys(query).length ? query : {}))
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

            this.$inertia.delete(this.route('chart_of_accounts.destroy', this.selectedRecord))
            this.confirmingDeletion = false
        },
    },
}
</script>

<style scoped>

</style>
