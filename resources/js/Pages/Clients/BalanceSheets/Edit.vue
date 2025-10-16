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
                        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Balance Sheet</h2>
                        <inertia-link class="btn btn-blue" :href="route('clients.balance_sheets.index',client.id)">
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
                            <div class="mt-4 grid grid-cols-1 md:grid-cols-2">
                                <div class="col-span-1 flex flex-col bg-white border-2 relative">
                                    <div class="bg-blue-950 text-white p-4 font-bold">
                                        <h4>Assets</h4>
                                    </div>
                                    <div class="p-4">
                                        <table class="mt-4 w-full whitespace-no-wrap table-auto">
                                            <tbody>
                                            <tr>
                                                <td colspan="2" class="px-6 py-4">
                                                    <h1 class="font-bold">Current Assets</h1>
                                                </td>
                                            </tr>
                                            <tr v-for="(item,index) in form.charts.current_assets" :key="index"
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
                                            <tr
                                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                                <td class="border-t px-6 py-4">
                                                    <span class="font-bold">
                                                   Total Current Assets
                                                    </span>
                                                </td>
                                                <td class="border-t px-6 py-4 text-right">
                                                    <span class="font-bold">{{
                                                            $filters.currency(form.total_current_assets)
                                                        }}</span>
                                                </td>
                                            </tr>
                                            <tr v-for="(item,index) in form.charts.other_current_assets" :key="index"
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
                                                <td colspan="2" class="px-6 py-4">
                                                    <h1 class="font-bold">Non-current Assets</h1>
                                                </td>
                                            </tr>
                                            <tr v-for="(item,index) in form.charts.fixed_assets" :key="index"
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
                                               Total Non-current Assets
                                                </span>
                                                </td>
                                                <td class="border-t px-6 py-4 text-right">
                                                    <span class="font-bold">{{
                                                            $filters.currency(form.total_fixed_assets)
                                                        }}</span>
                                                </td>
                                            </tr>
                                            <tr v-if="form.charts.other_assets.length">
                                                <td colspan="2" class="px-6 py-4">
                                                    <h1 class="font-bold">Other Assets</h1>
                                                </td>
                                            </tr>
                                            <tr v-for="(item,index) in form.charts.other_assets" :key="index"
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
                                            </tbody>
                                        </table>

                                    </div>
                                    <div
                                        class="bg-blue-950 text-white p-4 grid grid-cols-2 font-bold absolute bottom-0 w-full">
                                        <h4>Total Assets</h4>
                                        <h4 class="text-right">{{
                                                $filters.currency(form.total_assets)
                                            }}</h4>
                                    </div>
                                </div>
                                <div class="col-span-1 flex flex-col bg-white border-2 relative">
                                    <div class="bg-blue-950 text-white p-4 font-bold">
                                        <h4>Liabilities</h4>
                                    </div>
                                    <div class="p-4">
                                        <table class="mt-4 w-full whitespace-no-wrap table-auto">
                                            <tbody>
                                            <tr>
                                                <td colspan="2" class="px-6 py-4">
                                                    <h1 class="font-bold">Current Liabilities</h1>
                                                </td>
                                            </tr>
                                            <tr v-for="(item,index) in form.charts.current_liabilities" :key="index"
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
                                            <tr
                                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                                <td class="border-t px-6 py-4">
                                                    <span class="font-bold">
                                                   Total Current Liabilities
                                                    </span>
                                                </td>
                                                <td class="border-t px-6 py-4 text-right">
                                                    <span class="font-bold">{{
                                                            $filters.currency(form.total_current_liabilities)
                                                        }}</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="2" class="px-6 py-4">
                                                    <h1 class="font-bold">Long Term Liabilites</h1>
                                                </td>
                                            </tr>
                                            <tr v-for="(item,index) in form.charts.long_term_liabilities" :key="index"
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
                                               Total Long Term Liabilities
                                                </span>
                                                </td>
                                                <td class="border-t px-6 py-4 text-right">
                                                    <span class="font-bold">{{
                                                            $filters.currency(form.total_long_term_liabilities)
                                                        }}</span>
                                                </td>
                                            </tr>
                                            <tr
                                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                                <td class="border-t px-2 py-4">
                                                <span class="font-bold">
                                               Total  Liabilities
                                                </span>
                                                </td>
                                                <td class="border-t px-6 py-4 text-right">
                                                    <span class="font-bold">{{
                                                            $filters.currency(form.total_current_liabilities + form.total_long_term_liabilities)
                                                        }}</span>
                                                </td>
                                            </tr>
                                            </tbody>
                                        </table>

                                    </div>

                                    <div class="bg-blue-950 text-white p-4 font-bold">
                                        <h4>Owner's Equity</h4>
                                    </div>
                                    <div class="p-4">
                                        <table class="mt-4 w-full whitespace-no-wrap table-auto">
                                            <tbody>
                                            <tr v-for="(item,index) in form.charts.equity" :key="index"
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
                                            <tr
                                                class="hover:bg-gray-100 focus-within:bg-gray-100">
                                                <td class="border-t px-6 py-4">
                                                    <span class="font-bold">
                                                   Total owner's equity
                                                    </span>
                                                </td>
                                                <td class="border-t px-6 py-4 text-right">
                                                    <span class="font-bold">{{
                                                            $filters.currency(form.total_equity)
                                                        }}</span>
                                                </td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="bg-blue-950 text-white p-4 grid grid-cols-2 font-bold  bottom-0 w-ful">
                                        <h4>Total Equity/Liabilities</h4>
                                        <h4 class="text-right">{{
                                                $filters.currency(form.total_equity_liabilities)
                                            }}</h4>
                                    </div>
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
        sheet: Object,
        currentAssets: Object,
        otherAssets: Object,
        otherCurrentAssets: Object,
        fixedAssets: Object,
        currentLiabilities: Object,
        longTermLiabilities: Object,
        equity: Object,

    },
    data() {
        return {
            form: this.$inertia.form({
                '_method': 'PUT',
                year: this.sheet.year,
                description: this.sheet.description,
                as_at_date: this.sheet.as_at_date,
                total_current_assets: 0,
                total_fixed_assets: 0,
                total_other_assets: 0,
                total_other_current_assets: 0,
                total_current_liabilities: 0,
                total_long_term_liabilities: 0,
                total_assets: 0,
                total_equity: 0,
                total_liabilities: 0,
                total_working_capital: 0,
                total_equity_liabilities: 0,
                charts: this.sheet.charts
            }),
            pageTitle: "Edit Sheet",
            pageDescription: "Edit Sheet",

        }
    },
    mounted() {
        this.updateTotal()
    },
    methods: {
        submit() {
            this.form.post(this.route('clients.balance_sheets.update', this.sheet.id), {})
        },
        updateTotal() {
            this.form.total_current_assets = 0;
            this.form.total_fixed_assets = 0;
            this.form.total_other_assets = 0;
            this.form.total_other_current_assets = 0;
            this.form.total_current_liabilities = 0;
            this.form.total_long_term_liabilities = 0;
            this.form.total_assets = 0;
            this.form.total_equity = 0;
            this.form.total_equity_liabilities = 0;
            this.form.charts.current_assets.forEach(item => {
                this.form.total_current_assets += parseFloat(item.amount || '0')
            })
            this.form.charts.fixed_assets.forEach(item => {
                this.form.total_fixed_assets += parseFloat(item.amount || '0')
            })
            this.form.charts.other_assets.forEach(item => {
                this.form.total_other_assets += parseFloat(item.amount || '0')
            })
            this.form.charts.other_current_assets.forEach(item => {
                this.form.total_other_current_assets += parseFloat(item.amount || '0')
            })
            this.form.total_assets = this.form.total_current_assets + this.form.total_fixed_assets + this.form.total_other_assets + this.form.total_other_current_assets;
            this.form.charts.current_liabilities.forEach(item => {
                this.form.total_current_liabilities += parseFloat(item.amount || '0')
            })
            this.form.charts.long_term_liabilities.forEach(item => {
                this.form.total_long_term_liabilities += parseFloat(item.amount || '0')
            })
            this.form.total_working_capital = this.form.total_current_assets - this.form.total_current_liabilities;
            this.form.charts.equity.forEach(item => {
                this.form.total_equity += parseFloat(item.amount || '0')
            })
            this.form.total_liabilities = this.form.total_current_liabilities + this.form.total_long_term_liabilities;
            this.form.total_equity_liabilities = this.form.total_liabilities+ this.form.total_equity;

        }
    },
}
</script>

<style scoped>

</style>
