<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('industry_types.index')">
                    Industry Types
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> Edit
            </h2>
        </template>

        <div class="py-12">
            <div class=" mx-auto">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">
                    <form @submit.prevent="submit">
                        <div class="grid grid-cols-1 gap-2">
                            <div class="">
                                <jet-label for="code" value="Code"/>
                                <jet-input id="code" type="text" class="mt-1 block w-full" v-model="form.code"
                                           required/>
                                <jet-input-error :message="form.errors.code" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="name" value="Name"/>
                                <textarea-input id="name" class="mt-1 block w-full"
                                                v-model="form.name"/>
                                <jet-input-error :message="form.errors.name" class="mt-2"/>
                            </div>
                            <!-- <h4>Liquidity Ratios</h4>
                            <div>
                                <jet-label for="current_ratio" value="Current Ratio(days)"/>
                                <jet-input id="current_ratio" type="text" class="mt-1 block w-full"
                                           v-model="form.current_ratio"/>
                                <jet-input-error :message="form.errors.current_ratio" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="quick_ratio" value="Quick Ratio(%)"/>
                                <jet-input id="quick_ratio" type="text" class="mt-1 block w-full"
                                           v-model="form.quick_ratio"/>
                                <jet-input-error :message="form.errors.quick_ratio" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="debtor_days" value="Debtor Days(days)"/>
                                <jet-input id="debtor_days" type="text" class="mt-1 block w-full"
                                           v-model="form.debtor_days"/>
                                <jet-input-error :message="form.errors.debtor_days" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="creditor_days" value="Creditor Days(days)"/>
                                <jet-input id="creditor_days" type="text" class="mt-1 block w-full"
                                           v-model="form.creditor_days"/>
                                <jet-input-error :message="form.errors.creditor_days" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="working_capital" value="Turnover/Working Capital(%)"/>
                                <jet-input id="working_capital" type="text" class="mt-1 block w-full"
                                           v-model="form.working_capital"/>
                                <jet-input-error :message="form.errors.working_capital" class="mt-2"/>
                            </div>
                            <h4>Profitability Ratios</h4>
                            <div>
                                <jet-label for="turnover_growth" value="Turnover Growth(%)"/>
                                <jet-input id="turnover_growth" type="text" class="mt-1 block w-full"
                                           v-model="form.turnover_growth"/>
                                <jet-input-error :message="form.errors.turnover_growth" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="quick_ratio" value="Quick Ratio(%)"/>
                                <jet-input id="quick_ratio" type="text" class="mt-1 block w-full"
                                           v-model="form.quick_ratio"/>
                                <jet-input-error :message="form.errors.quick_ratio" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="gross_profit" value="Gross Profit(%)"/>
                                <jet-input id="gross_profit" type="text" class="mt-1 block w-full"
                                           v-model="form.gross_profit"/>
                                <jet-input-error :message="form.errors.gross_profit" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="operating_profit_margin" value="Operating Profit Margin(%)"/>
                                <jet-input id="operating_profit_margin" type="text" class="mt-1 block w-full"
                                           v-model="form.operating_profit_margin"/>
                                <jet-input-error :message="form.errors.operating_profit_margin" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="net_profit_margin" value="Net Profit Margin(%)"/>
                                <jet-input id="net_profit_margin" type="text" class="mt-1 block w-full"
                                           v-model="form.net_profit_margin"/>
                                <jet-input-error :message="form.errors.net_profit_margin" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="return_on_equity" value="Return On Equity(ROE)(%)"/>
                                <jet-input id="return_on_equity" type="text" class="mt-1 block w-full"
                                           v-model="form.return_on_equity"/>
                                <jet-input-error :message="form.errors.return_on_equity" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="return_on_assets" value="Return On Assets(ROA)(%)"/>
                                <jet-input id="return_on_assets" type="text" class="mt-1 block w-full"
                                           v-model="form.return_on_assets"/>
                                <jet-input-error :message="form.errors.return_on_assets" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="return_on_investment" value="Return On Investments(ROI)(%)"/>
                                <jet-input id="return_on_investment" type="text" class="mt-1 block w-full"
                                           v-model="form.return_on_investment"/>
                                <jet-input-error :message="form.errors.return_on_investment" class="mt-2"/>
                            </div>
                            <h4>Capital Structure Ratios</h4>
                            <div>
                                <jet-label for="gearing_ratio" value="Gearing Ratio(%)"/>
                                <jet-input id="gearing_ratio" type="text" class="mt-1 block w-full"
                                           v-model="form.gearing_ratio"/>
                                <jet-input-error :message="form.errors.gearing_ratio" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="long_term_debt" value="Long-term Debt/Equity(%)"/>
                                <jet-input id="long_term_debt" type="text" class="mt-1 block w-full"
                                           v-model="form.long_term_debt"/>
                                <jet-input-error :message="form.errors.long_term_debt" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="tangible_net_worth" value="Debt/Tangible Net Worth(%)"/>
                                <jet-input id="tangible_net_worth" type="text" class="mt-1 block w-full"
                                           v-model="form.tangible_net_worth"/>
                                <jet-input-error :message="form.errors.tangible_net_worth" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="total_assets" value="Equity/Total Assets(%)"/>
                                <jet-input id="total_assets" type="text" class="mt-1 block w-full"
                                           v-model="form.total_assets"/>
                                <jet-input-error :message="form.errors.total_assets" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="solvency" value="Solvency(%)"/>
                                <jet-input id="solvency" type="text" class="mt-1 block w-full"
                                           v-model="form.solvency"/>
                                <jet-input-error :message="form.errors.solvency" class="mt-2"/>
                            </div>
                            <h4>Debt Service Ratios</h4>
                            <div>
                                <jet-label for="interest_cover" value="Interest Cover(times)"/>
                                <jet-input id="interest_cover" type="text" class="mt-1 block w-full"
                                           v-model="form.interest_cover"/>
                                <jet-input-error :message="form.errors.interest_cover" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="gross_interest_debts" value="EBITDA/Gross Interest Debts(%)"/>
                                <jet-input id="gross_interest_debts" type="text" class="mt-1 block w-full"
                                           v-model="form.gross_interest_debts"/>
                                <jet-input-error :message="form.errors.gross_interest_debts" class="mt-2"/>
                            </div>
                            <h4>Activity Ratios</h4>
                            <div>
                                <jet-label for="total_assets_turnover" value="Total Assets/Turnover(%)"/>
                                <jet-input id="total_assets_turnover" type="text" class="mt-1 block w-full"
                                           v-model="form.total_assets_turnover"/>
                                <jet-input-error :message="form.errors.total_assets_turnover" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="fixed_assets_turn_over" value="Fixed Assets Turnover(%)"/>
                                <jet-input id="fixed_assets_turn_over" type="text" class="mt-1 block w-full"
                                           v-model="form.fixed_assets_turn_over"/>
                                <jet-input-error :message="form.errors.fixed_assets_turn_over" class="mt-2"/>
                            </div> -->

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
        type: Object
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
                name: this.type.name,
                description: this.type.description,
                code: this.type.code,
            }),
            pageTitle: "Edit Type",
            pageDescription: "Edit Type",
        }

    },
    methods: {
        submit() {
            this.form.put(this.route('industry_types.update', this.type.id), {})

        },

    }
}
</script>
<style scoped>

</style>
