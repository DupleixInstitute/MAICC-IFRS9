<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600"
                              :href="route('chart_of_accounts.index')">
                    Chart of Accounts
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> Create
            </h2>
        </template>


        <div class=" mx-auto">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">
                <form @submit.prevent="submit">
                    <div class="grid grid-cols-1 gap-2">
                        <div>
                            <jet-label for="parent_id" value="Parent"/>
                            <Multiselect
                                v-model="form.parent_id"
                                mode="single"
                                :options="$page.props.chartOfAccounts"
                            />
                            <jet-input-error :message="form.errors.parent_id" class="mt-2"/>
                        </div>
                        <div class="">
                            <jet-label for="name" value="Name"/>
                            <jet-input id="name" type="text" class="mt-1 block w-full" v-model="form.name"
                                       required autofocus/>
                            <jet-input-error :message="form.errors.name" class="mt-2" required/>
                        </div>
                        <div class="">
                            <jet-label for="gl_code" value="GL Code"/>
                            <jet-input id="gl_code" type="text" class="mt-1 block w-full" v-model="form.gl_code"/>
                            <jet-input-error :message="form.errors.gl_code" class="mt-2" required/>
                        </div>
                        <div>
                            <jet-label for="account_type" value="Account Type"/>
                            <select
                                class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                                name="account_type" v-model="form.account_type" id="account_type">
                                <optgroup label="Asset">
                                    <option value="fixed_asset">Fixed Asset</option>
                                    <option value="current_asset">Current Asset</option>
                                    <option value="accounts_receivable">Accounts Receivable</option>
                                    <option value="other_current_asset">Other Current
                                        Asset
                                    </option>
                                    <option value="other_asset">Other Asset</option>
                                    <option value="cash">Cash</option>
                                    <option value="bank">Bank</option>
                                    <option value="stock">Stock</option>
                                    <option value="property_plant_equipment">Property, plant and equipment</option>
                                    <option value="investment_property">Investment property</option>
                                    <option value="right_of_use_asset">Right of use assets</option>
                                    <option value="intangible_asset">Intangible assets</option>
                                </optgroup>
                                <optgroup label="Liability">
                                    <option value="current_liability">Current Liability</option>
                                    <option value="accounts_payable">Accounts Payable</option>
                                    <option value="credit_card">Credit Card</option>
                                    <option value="long_term_liability">Long Term Liability</option>
                                    <option value="other_long_term_liability">Other Long Term Liability</option>
                                    <option value="finance_lease_liability">Finance lease liability</option>
                                    <option value="other_liability">Other Liability</option>
                                    <option value="income_tax">Income Tax</option>
                                    <option value="deferred_income_tax">Deferred Income Tax</option>
                                </optgroup>
                                <optgroup label="Expense">
                                    <option value="expense">Expense</option>
                                    <option value="cost_of_goods_sold">Cost of Goods Sold</option>
                                    <option value="cost_of_goods_sold_depreciation">Cost of Goods Sold-Depreciation
                                    </option>
                                    <option value="depreciation_property_plant_equipment">Depreciation- property, plant and equipment
                                    </option>
                                    <option value="depreciation_right_of_use_assets">Depreciation - right of use assets
                                    </option>
                                    <option value="depreciation_investment_property">Depreciation - investment property
                                    </option>
                                    <option value="amortisation_intangible_assets">Amortisation - intangible assets
                                    </option>
                                    <option value="short_term_leases">Short-term leases
                                    </option>
                                    <option value="other_expense">Other Expense</option>
                                    <option value="income_tax_expense">Income Tax Expense</option>
                                    <option value="net_finance_costs_banks">Net Finance Costs-banks</option>
                                    <option value="net_finance_costs_finance_leases">Net Finance Costs-finance leases
                                    </option>
                                </optgroup>
                                <optgroup label="Income">
                                    <option value="income">income</option>
                                    <option value="other_income">Other Income</option>
                                </optgroup>
                                <optgroup label="Equity">
                                    <option value="equity">Equity</option>
                                    <option value="retained_earning">Retained earnings or Accumulated profits</option>
                                    <option value="capital_contribution">Capital/owner's contribution</option>
                                    <option value="reserve">Reserves</option>
                                </optgroup>
                            </select>
                            <jet-input-error :message="form.errors.account_type" class="mt-2"/>
                        </div>
                        <div class="">
                            <jet-label for="external_id" value="External ID"/>
                            <jet-input id="external_id" type="text" class="mt-1 block w-full"
                                       v-model="form.external_id"/>
                            <jet-input-error :message="form.errors.external_id" class="mt-2"/>
                        </div>
                        <div>
                            <jet-label for="allow_manual">
                                <div class="flex items-center">
                                    <jet-checkbox name="enable_reconciliation" id="allow_manual"
                                                  v-model:checked="form.allow_manual"/>
                                    <div class="ml-2">
                                        Allow Manual Entries
                                    </div>
                                </div>
                            </jet-label>
                        </div>
                        <div>
                            <jet-label for="enable_reconciliation">
                                <div class="flex items-center">
                                    <jet-checkbox name="enable_reconciliation" id="enable_reconciliation"
                                                  v-model:checked="form.enable_reconciliation"/>
                                    <div class="ml-2">
                                        Enable Reconciliation
                                    </div>
                                </div>
                            </jet-label>
                        </div>
                        <div>
                            <jet-label for="active">
                                <div class="flex items-center">
                                    <jet-checkbox name="active" id="active" v-model:checked="form.active"/>
                                    <div class="ml-2">
                                        Active
                                    </div>
                                </div>
                            </jet-label>
                        </div>
                        <div>
                            <jet-label for="description" value="Description"/>
                            <textarea-input id="description" class="mt-1 block w-full"
                                            v-model="form.description"/>
                            <jet-input-error :message="form.errors.description" class="mt-2"/>

                        </div>
                    </div>
                    <div class="flex items-center justify-end mt-4">

                        <jet-button class="ml-4" :class="{ 'opacity-25': form.processing }"
                                    :disabled="form.processing">
                            Save
                        </jet-button>
                    </div>
                </form>
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
import JetButton from "@/Jetstream/Button.vue";
import JetInput from "@/Jetstream/Input.vue";
import JetInputError from "@/Jetstream/InputError.vue";
import JetCheckbox from "@/Jetstream/Checkbox.vue";
import JetLabel from "@/Jetstream/Label.vue";
import Select from "@/Jetstream/Select.vue";
import FileInput from "@/Jetstream/FileInput.vue";
import TextareaInput from "@/Jetstream/TextareaInput.vue";


export default {
    props: {
        chartOfAccounts: Object,
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

    },
    data() {
        return {
            form: this.$inertia.form({
                parent_id: null,
                name: null,
                gl_code: null,
                external_id: null,
                account_type: null,
                description: null,
                enable_reconciliation: false,
                allow_manual: false,
                active: true,
            }),
            pageTitle: "Create Chart of Account",
            pageDescription: "Create Chart of Account",
        }

    },
    methods: {
        submit() {
            this.form.post(this.route('chart_of_accounts.store'), {})

        },

    }
}
</script>
<style scoped>

</style>
