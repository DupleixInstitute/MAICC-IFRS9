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
                        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Income Statement</h2>
                        <inertia-link class="btn btn-blue" :href="route('clients.income_statements.index',client.id)">
                            <span>Back </span>
                        </inertia-link>
                    </div>
                    <div class="mt-4">
                        <form @submit.prevent="submit" enctype="multipart/form-data">
                            <div>
                                <jet-label for="year" value="Year(4 digits)"/>
                                <jet-input id="year" type="number" required class="block w-full" v-model="form.year"/>
                                <jet-input-error :message="form.errors.year" class="mt-2"/>
                            </div>
                            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-2">
                                <div>
                                    <jet-label for="audit_status" value="Reporting Month (1-12)"/>
                                    <select
                                        class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                                        name="reporting_month" v-model="form.reporting_month" id="reporting_month">
                                        <option v-for="n in 12" :value="n">{{ n }}</option>
                                    </select>
                                    <jet-input-error :message="form.errors.reporting_month" class="mt-2"/>
                                </div>
                                <div>
                                    <jet-label for="months_in_year" value="No of Months In Year (1-15)"/>
                                    <select
                                        class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                                        name="months_in_year" v-model="form.months_in_year" id="months_in_year">
                                        <option v-for="n in 15" :value="n">{{ n }}</option>
                                    </select>
                                    <jet-input-error :message="form.errors.months_in_year" class="mt-2"/>
                                </div>
                                <div>
                                    <jet-label for="audit_status" value="Audit Status"/>
                                    <select
                                        class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                                        name="audit_status" v-model="form.audit_status" id="audit_status">
                                        <option value="Audited">Audited</option>
                                        <option value="Registered Accountant">Registered Accountant</option>
                                        <option value="Management Accounts">Management Accounts</option>
                                    </select>
                                    <jet-input-error :message="form.errors.audit_status" class="mt-2"/>
                                </div>
                            </div>
                            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-2">
                                <div>
                                    <jet-label for="real_annual_inflation_rate" value="Annual Inflation Rate - Real"/>
                                    <jet-input id="real_annual_inflation_rate" type="text" class="block w-full"
                                               v-model="form.real_annual_inflation_rate"/>
                                    <jet-input-error :message="form.errors.real_annual_inflation_rate" class="mt-2"/>
                                </div>
                                <div>
                                    <jet-label for="nominal_annual_inflation_rate"
                                               value="Annual Inflation Rate - Norminal"/>
                                    <jet-input id="nominal_annual_inflation_rate" type="text" class="block w-full"
                                               v-model="form.nominal_annual_inflation_rate"/>
                                    <jet-input-error :message="form.errors.nominal_annual_inflation_rate" class="mt-2"/>
                                </div>
                            </div>
                            <table class="mt-4 w-full whitespace-no-wrap table-auto">
                                <tbody>
                                <tr
                                    class="hover:bg-gray-100 focus-within:bg-gray-100">
                                    <td class="border-t px-6 py-4">
                                    <span class="font-bold">
                                   Net Sales
                                    </span>
                                    </td>
                                    <td class="border-t px-6 py-4 text-right">
                                        <span class="font-bold">{{ $filters.currency(form.total_sales) }}</span>
                                    </td>
                                </tr>
                                <tr v-for="(item,index) in form.charts.sales" :key="index"
                                    class="hover:bg-gray-100 focus-within:bg-gray-100">
                                    <td class="border-t px-6 py-4">
                                    <span class=" flex items-center">
                                    {{ item.name }}
                                    </span>
                                    </td>
                                    <td class="border-t px-6 py-4">
                                        <jet-input id="name" @keyup="updateTotal" type="number"
                                                   class="mt-1 block w-full"
                                                   v-model="item.amount"/>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4">
                                        <h1 class="font-bold">Cost of sales</h1>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <span class="font-bold">{{
                                                $filters.currency(form.total_cost_of_goods_sold)
                                            }}</span>
                                    </td>
                                </tr>
                                <tr v-for="(item,index) in form.charts.cost_of_goods_sold" :key="index"
                                    class="hover:bg-gray-100 focus-within:bg-gray-100">
                                    <td class="border-t px-8 py-4">
                                    <span class=" flex items-center">
                                    {{ item.name }}
                                    </span>
                                    </td>
                                    <td class="border-t px-6 py-4">
                                        <jet-input id="name" @keyup="updateTotal" type="number"
                                                   class="mt-1 block w-full"
                                                   v-model="item.amount"/>
                                    </td>
                                </tr>
                                <tr
                                    class="hover:bg-gray-100 focus-within:bg-gray-100">
                                    <td class="border-t px-6 py-4">
                                    <span class="font-bold">
                                   Gross Profit
                                    </span>
                                    </td>
                                    <td class="border-t px-6 py-4 text-right">
                                        <span class="font-bold">{{ $filters.currency(form.total_gross_margin) }}</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td colspan="2" class="px-6 py-4">
                                        <h1 class="font-bold">Other income</h1>
                                    </td>
                                </tr>
                                <tr v-for="(item,index) in form.charts.other_income" :key="index"
                                    class="hover:bg-gray-100 focus-within:bg-gray-100">
                                    <td class="border-t px-8 py-4">
                                    <span class=" flex items-center">
                                    {{ item.name }}
                                    </span>
                                    </td>
                                    <td class="border-t px-6 py-4">
                                        <jet-input id="name" @keyup="updateTotal" type="number"
                                                   class="mt-1 block w-full"
                                                   v-model="item.amount"/>
                                    </td>
                                </tr>

                                <tr>
                                    <td class="px-6 py-4">
                                        <h1 class="font-bold">Total Operating expenses</h1>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <span class="font-bold">({{
                                                $filters.currency(form.total_operating_expenses)
                                            }})</span>
                                    </td>
                                </tr>
                                <tr v-for="(item,index) in form.charts.operating_expenses" :key="index"
                                    class="hover:bg-gray-100 focus-within:bg-gray-100">
                                    <td class="border-t px-8 py-4">
                                    <span class=" flex items-center">
                                    {{ item.name }}
                                    </span>
                                    </td>
                                    <td class="border-t px-6 py-4">
                                        <jet-input id="name" @keyup="updateTotal" type="number"
                                                   class="mt-1 block w-full"
                                                   v-model="item.amount"/>
                                    </td>
                                </tr>

                                <tr>
                                    <td colspan="2" class="px-6 py-4">
                                        <h1 class="font-bold">Other non recurring expenses (unusual) </h1>
                                    </td>
                                </tr>
                                <tr v-for="(item,index) in form.charts.other_expenses" :key="index"
                                    class="hover:bg-gray-100 focus-within:bg-gray-100">
                                    <td class="border-t px-8 py-4">
                                    <span class=" flex items-center">
                                    {{ item.name }}
                                    </span>
                                    </td>
                                    <td class="border-t px-6 py-4">
                                        <jet-input id="name" @keyup="updateTotal" type="number"
                                                   class="mt-1 block w-full"
                                                   v-model="item.amount"/>
                                    </td>
                                </tr>
                                <tr
                                    class="hover:bg-gray-100 focus-within:bg-gray-100">
                                    <td class="border-t px-6 py-4">
                                    <span class="font-bold">
                                   Net operating profit
                                    </span>
                                    </td>
                                    <td class="border-t px-6 py-4 text-right">
                                        <span class="font-bold">{{
                                                $filters.currency(form.total_operating_profit)
                                            }}</span>
                                    </td>
                                </tr>
                                <tr v-for="(item,index) in form.charts.net_finance_costs" :key="index"
                                    class="hover:bg-gray-100 focus-within:bg-gray-100">
                                    <td class="border-t px-8 py-4">
                                    <span class=" flex items-center">
                                    {{ item.name }}
                                    </span>
                                    </td>
                                    <td class="border-t px-6 py-4">
                                        <jet-input id="name" @keyup="updateTotal" type="number"
                                                   class="mt-1 block w-full"
                                                   v-model="item.amount"/>
                                    </td>
                                </tr>
                                <tr
                                    class="hover:bg-gray-100 focus-within:bg-gray-100">
                                    <td class="border-t px-6 py-4">
                                    <span class="font-bold">
                                        Profit before tax
                                    </span>
                                    </td>
                                    <td class="border-t px-6 py-4 text-right">
                                        <span class="font-bold">{{
                                                $filters.currency(form.total_income_before_tax)
                                            }}</span>
                                    </td>
                                </tr>
                                <tr v-for="(item,index) in form.charts.income_tax" :key="index"
                                    class="hover:bg-gray-100 focus-within:bg-gray-100">
                                    <td class="border-t px-8 py-4">
                                    <span class=" flex items-center">
                                    {{ item.name }}
                                    </span>
                                    </td>
                                    <td class="border-t px-6 py-4">
                                        <jet-input id="name" @keyup="updateTotal" type="number"
                                                   class="mt-1 block w-full"
                                                   v-model="item.amount"/>
                                    </td>
                                </tr>
                                <tr
                                    class="hover:bg-gray-100 focus-within:bg-gray-100">
                                    <td class="border-t px-6 py-4">
                                    <span class="font-bold">
                                        Total income tax
                                    </span>
                                    </td>
                                    <td class="border-t px-6 py-4 text-right">
                                        <span class="font-bold">{{
                                                $filters.currency(form.total_income_tax)
                                            }}</span>
                                    </td>
                                </tr>
                                <tr
                                    class="hover:bg-gray-100 focus-within:bg-gray-100">
                                    <td class="border-t px-6 py-4">
                                    <span class="font-bold">
                                        Profit after tax
                                    </span>
                                    </td>
                                    <td class="border-t px-6 py-4 text-right">
                                        <span class="font-bold">{{ $filters.currency(form.net_profit) }}</span>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
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
import mapValues from 'lodash/mapValues'
import pickBy from 'lodash/pickBy'
import throttle from 'lodash/throttle'
import JetLabel from '@/Jetstream/Label.vue'
import SelectInput from '@/Jetstream/SelectInput.vue'
import JetConfirmationModal from '@/Jetstream/ConfirmationModal.vue'
import JetDangerButton from '@/Jetstream/DangerButton.vue'
import JetSecondaryButton from '@/Jetstream/SecondaryButton.vue'
import ClientMenu from '@/Pages/Clients/ClientMenu.vue'
import JetInput from "@/Jetstream/Input.vue";
import JetInputError from "@/Jetstream/InputError.vue";
import JetButton from "@/Jetstream/Button.vue";
import JetCheckbox from "@/Jetstream/Checkbox.vue";
import TextareaInput from "@/Jetstream/TextareaInput.vue";

export default {
    components: {
        AppLayout,
        Icon,
        TextareaInput,
        JetLabel,
        JetInput,
        JetInputError,
        SelectInput,
        JetConfirmationModal,
        JetDangerButton,
        JetSecondaryButton,
        ClientMenu,
        JetButton,
        JetCheckbox,
    },
    props: {
        client: Object,
        statement: Object,
        sales: Object,
        costsOfGoodsSold: Object,
        expenses: Object,
        otherExpenses: Object,
        otherIncome: Object,
        incomeTax: Object,
        netFinanceCosts: Object,
    },
    data() {
        return {
            form: this.$inertia.form({
                '_method': 'PUT',
                year: this.statement.year,
                description: this.statement.description,
                as_at_date: this.statement.as_at_date,
                reporting_month: this.statement.reporting_month,
                months_in_year: this.statement.months_in_year,
                audit_status: this.statement.audit_status,
                real_annual_inflation_rate: this.statement.real_annual_inflation_rate,
                nominal_annual_inflation_rate: this.statement.nominal_annual_inflation_rate,
                total_sales: 0,
                total_operating_expenses: 0,
                total_gross_margin: 0,
                total_other_income: 0,
                total_other_expenses: 0,
                total_income_before_tax: 0,
                net_profit: 0,
                total_operating_profit: 0,
                total_cost_of_goods_sold: 0,
                total_income_tax: 0,
                total_net_finance_costs: 0,
                charts:this.statement.charts
            }),
            pageTitle: "Edit Statement",
            pageDescription: "Edit Statement",

        }
    },
    mounted() {
        this.updateTotal()
    },
    methods: {
        submit() {
            this.form.post(this.route('clients.income_statements.update', this.statement.id), {})
        },
        updateTotal() {
            this.form.total_sales = 0;
            this.form.total_operating_expenses = 0;
            this.form.total_gross_margin = 0;
            this.form.total_other_income = 0;
            this.form.total_other_expenses = 0;
            this.form.total_income_before_tax = 0;
            this.form.net_profit = 0;
            this.form.total_operating_profit = 0;
            this.form.total_cost_of_goods_sold = 0;
            this.form.total_income_tax = 0;
            this.form.total_net_finance_costs = 0;
            this.form.charts.sales.forEach(item => {
                this.form.total_sales += parseFloat(item.amount || '0')
            })
            this.form.charts.cost_of_goods_sold.forEach(item => {
                this.form.total_cost_of_goods_sold += parseFloat(item.amount || '0')
            })
            this.form.total_gross_margin = this.form.total_sales - this.form.total_cost_of_goods_sold;
            this.form.charts.other_income.forEach(item => {
                this.form.total_other_income += parseFloat(item.amount || '0')
            })
            this.form.charts.operating_expenses.forEach(item => {
                this.form.total_operating_expenses += parseFloat(item.amount || '0')
            })
            this.form.charts.other_expenses.forEach(item => {
                this.form.total_other_expenses += parseFloat(item.amount || '0')
            })
            this.form.total_operating_profit = this.form.total_gross_margin + this.form.total_other_income - this.form.total_operating_expenses - this.form.total_other_expenses;
            this.form.charts.net_finance_costs.forEach(item => {
                this.form.total_net_finance_costs += parseFloat(item.amount || '0')
            })
            this.form.total_income_before_tax = this.form.total_operating_profit - this.form.total_net_finance_costs;
            this.form.charts.income_tax.forEach(item => {
                this.form.total_income_tax += parseFloat(item.amount || '0')
            })
            this.form.net_profit = this.form.total_income_before_tax - this.form.total_income_tax;
        }
    },
}
</script>

<style scoped>

</style>
