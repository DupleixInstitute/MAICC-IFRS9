<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('clients.index')">Clients
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> {{ client.name }}
            </h2>
        </template>
        <div class=" mx-auto">
            <div class="flex items-start">
                <div class="bg-white relative shadow-xl mt-20 w-3/12 ">
                    <div class="col-span-12 lg:col-span-4 xxl:col-span-3 flex lg:block flex-col-reverse">
                        <div class="intro-y box mt-5 lg:mt-0">
                            <client-menu :client="client"></client-menu>
                        </div>
                    </div>

                </div>
                <div class="w-9/12 ml-2 bg-white p-5">
                    <div class="flex justify-between ">
                        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Create Invoice</h2>
                        <inertia-link class="btn btn-blue" :href="route('clients.copayers.index',client.id)">
                            <span>Back </span>
                        </inertia-link>
                    </div>
                    <div class="mt-4">
                        <form @submit.prevent="submit" enctype="multipart/form-data">
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <jet-label for="date" value="Date"/>
                                    <flat-pickr
                                        v-model="form.date"
                                        class="form-control w-full"
                                        placeholder="Select date"
                                        name="date"
                                        id="date">
                                    </flat-pickr>
                                    <jet-input-error :message="form.errors.date" class="mt-2"/>

                                </div>
                                <div>
                                    <jet-label for="due_date" value="Due Date"/>
                                    <flat-pickr
                                        v-model="form.due_date"
                                        class="form-control w-full"
                                        placeholder="Select date"
                                        name="due_date"
                                        id="due_date">
                                    </flat-pickr>
                                    <jet-input-error :message="form.errors.due_date" class="mt-2"/>

                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-2 mt-2">
                                <div>
                                    <jet-label for="sponsor" value="Sponsor"/>
                                    <select
                                        class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                                        name="sponsor" v-model="form.sponsor" id="sponsor">
                                        <option value="cash">Cash</option>
                                        <option value="co_payer">CoPayer</option>
                                        <option value="both">Both</option>
                                    </select>
                                    <jet-input-error :message="form.errors.sponsor" class="mt-2"/>
                                </div>
                                <div v-if="form.sponsor=='co_payer'||form.sponsor=='both'">
                                    <jet-label for="co_payer_id" value="CoPayer"/>
                                    <Multiselect
                                        id="co_payer_id"
                                        v-model="form.co_payer_id"
                                        mode="single"
                                        :searchable="true"
                                        :options="$page.props.coPayers"
                                    />
                                    <jet-input-error :message="form.errors.co_payer_id" class="mt-2"/>
                                </div>
                                <div>
                                    <jet-label for="doctor_id" value="Doctor"/>
                                    <Multiselect
                                        id="doctor_id"
                                        v-model="form.doctor_id"
                                        mode="single"
                                        :searchable="true"
                                        :options="$page.props.doctors"
                                    />
                                    <jet-input-error :message="form.errors.doctor_id" class="mt-2"/>
                                </div>
                            </div>
                            <div class="mb-2 mt-2">
                                <table class="w-full whitespace-no-wrap">
                                    <thead class="bg-gray-50">
                                    <tr class="text-left font-bold">
                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Service</th>
                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Cost</th>
                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Qty</th>
                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Tax</th>
                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Cash</th>
                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">CoPayer</th>
                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Total</th>
                                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500"></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr v-for="(item,index) in form.invoice_items" class="">
                                        <td class="border-t py-2 px-2">
                                            <div>
                                                <Multiselect
                                                    v-model="form.invoice_items[index].tariff_id"
                                                    v-bind="tariffsMultiSelect"
                                                    :required="true"
                                                    v-on:select="addTariff(index)"
                                                />
                                            </div>
                                        </td>
                                        <td class="border-t py-2 px-2">
                                            <jet-input :id="'unit_cost_'+index" type="text" class="mt-1 block w-full"
                                                       v-model="form.invoice_items[index].unit_cost"
                                                       v-on:input="updateInvoice"/>
                                        </td>
                                        <td class="border-t py-2">
                                            <jet-input :id="'qty_'+index" type="text" class="mt-1 block w-full"
                                                       v-model="form.invoice_items[index].qty"
                                                       v-on:input="updateInvoice"
                                                       required/>
                                        </td>
                                        <td class="border-t py-2 px-2">
                                            <Multiselect
                                                id="tax_rate_id"
                                                v-model="form.invoice_items[index].tax_rate_id"
                                                mode="single"
                                                :searchable="true"
                                                v-on:select="updateInvoice"
                                                :options="$page.props.taxRates"
                                            />
                                        </td>
                                        <td class="border-t py-2 px-2">
                                            <jet-input :id="'cash_amount_'+index" type="text" class="mt-1 block w-full"
                                                       v-model="form.invoice_items[index].cash_amount"
                                                       v-on:input="updateInvoice"/>
                                        </td>
                                        <td class="border-t py-2 px-2">
                                            <jet-input :id="'co_payer_amount_'+index" type="text"
                                                       class="mt-1 block w-full"
                                                       v-model="form.invoice_items[index].co_payer_amount"
                                                       v-on:input="updateInvoice"/>
                                        </td>
                                        <td class="border-t py-2 px-2">
                                            <jet-input readonly :id="'total_'+index" type="text"
                                                       class="mt-1 block w-full"
                                                       v-model="form.invoice_items[index].total"/>
                                        </td>
                                        <td class="border-t py-2">
                                            <a href="#" @click="form.invoice_items.splice(index,1)">
                                                <font-awesome-icon icon="trash"
                                                                   class="w-4 h-4 mr-2 text-red-500"></font-awesome-icon>
                                            </a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="border-t" colspan="8">
                                            <a href="#" @click="addService">
                                                <font-awesome-icon icon="plus" class="w-4 h-4 mr-2"></font-awesome-icon>
                                                Add Service
                                            </a>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="grid grid-cols-1 gap-2 mt-2">
                                <div>
                                    <jet-label for="terms" value="Terms"/>
                                    <textarea-input id="terms" class="mt-1 block w-full"
                                                    v-model="form.terms"/>
                                    <jet-input-error :message="form.errors.terms" class="mt-2"/>

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
import JetInput from "@/Jetstream/Input.vue";
import JetInputError from "@/Jetstream/InputError.vue";
import JetButton from "@/Jetstream/Button.vue";
import JetCheckbox from "@/Jetstream/Checkbox.vue";
import TextareaInput from "@/Jetstream/TextareaInput.vue";

const fetchTariffs = async (query) => {
    let where = ''
    if (query) {
        where = '&s=' + encodeURIComponent(JSON.stringify({
            "ProgrammingLanguage": {
                "$regex": `${query}|${query.toUpperCase()}|${query[0].toUpperCase() + query.slice(1)}`
            }
        }))
    }
    const response = await fetch(
        route('tariffs.search') + '?s=' + query,
        {}
    );

    const data = await response.json(); // Here you have the data that you need
    return data.map((item) => {
        return {value: item.id, label: item.name + '(' + item.code + ')'}
    })
}
export default {
    metaInfo: {title: 'Clients'},
    components: {
        AppLayout,
        Icon,
        Pagination,
        SearchFilter,
        FilterSearch,
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
        TextareaInput,
    },
    props: {
        client: Object,
        taxRates: Object,
        doctors: Object,

    },
    data() {
        return {
            form: this.$inertia.form({
                co_payer_id: null,
                doctor_id: null,
                co_payer_cover: null,
                sponsor: null,
                date: null,
                due_date: null,
                invoice_items: [],
                amount: null,
                cover: null,
                terms: null,
                client_notes: null,
                admin_notes: null,
                description: null,
            }),
            tariffsMultiSelect: {
                value: null,
                remark: null,
                placeholder: 'Search for a service',
                filterResults: false,
                minChars: 2,
                resolveOnLoad: false,
                delay: 4,
                searchable: true,
                options: async (query) => {
                    return await fetchTariffs(query)
                }
            },
            pageTitle: "Create Invoice",
            pageDescription: "Manage Client Invoices",

        }
    },

    methods: {
        submit() {
            this.form.post(this.route('clients.invoices.store', this.client.id), {})
        },
        addService() {
            this.form.invoice_items.push({
                tariff_id: null,
                tax_rate_id: null,
                qty: 1,
                unit_cost: 0,
                tax_total: 0,
                co_payer_amount: 0,
                cash_amount: 0,
                total: 0,
                name: null,
            });
        },
        updateInvoice() {
            this.amount = 0;
            this.form.invoice_items.forEach((item, index) => {
                let total = 0;
                let tax = 0;
                let amount = item.unit_cost * item.qty;
                if (item.tax_rate_id) {
                    this.taxRates.forEach(taxRate => {
                        if (taxRate.type === 'fixed') {
                            tax = taxRate.amount;
                        } else {
                            tax = (taxRate.amount / 100) * amount;
                        }
                    })
                }
                item.tax_total = tax;
                item.total = amount + tax;
                this.amount += item.total;
            });
        },
        addTariff(index) {
            let item = this.form.invoice_items[index];
            if (item.tariff_id) {
                axios.get(this.route('tariffs.find', item.tariff_id)).then(result => {
                    item.name = result.data.name;
                    item.unit_cost = result.data.amount;
                    item.total = result.data.amount;
                    item.co_payer_amount = result.data.co_payer_amount;
                    item.cash_amount = result.data.cash_amount;
                })
            }
            this.updateInvoice();
        },

    },
}
</script>

<style scoped>

</style>
