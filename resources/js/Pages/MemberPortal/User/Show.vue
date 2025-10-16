<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('portal.invoices.index')">
                    Invoices
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> Invoice #{{ invoice.id }}
            </h2>
        </template>

        <div class="py-12">
            <div class=" mx-auto">
                <div class="grid grid-cols-12 gap-6">
                    <div class="col-span-12 lg:col-span-4 xxl:col-span-3 flex lg:block">
                        <div class="intro-y bg-white mt-5 lg:mt-0">
                            <div class="relative flex items-center p-5">
                                <div class="ml-4 mr-auto">
                                    <div class="font-medium text-base">Invoice #{{ invoice.id }}</div>
                                    <div class="text-gray-600">{{ invoice.reference }}</div>
                                </div>
                                <jet-dropdown align="right" width="60">
                                    <template #trigger>
                                        <span class="inline-flex rounded-md">
                                            <button type="button"
                                                    class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:bg-gray-50 hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                     viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                     stroke-width="1.5"
                                                     stroke-linecap="round" stroke-linejoin="round"
                                                     class="feather feather-more-horizontal w-5 h-5 text-gray-600 dark:text-gray-300">
                                            <circle cx="12" cy="12" r="1"></circle>
                                            <circle cx="19" cy="12" r="1"></circle>
                                            <circle cx="5" cy="12" r="1"></circle>
                                        </svg>
                                            </button>
                                        </span>
                                    </template>

                                    <template #content>
                                        <div class="w-60 shadow-xl bg-white rounded">
                                            <jet-dropdown-link :href="route('portal.invoices.email',invoice.id)">
                                                <font-awesome-icon icon="envelope"/>
                                                Email Invoice
                                            </jet-dropdown-link>
                                            <a :href="route('portal.invoices.pdf',invoice.id)"
                                               class="block px-4 py-2 text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out"
                                               target="_blank">
                                                <font-awesome-icon icon="file-pdf"/>
                                                Download Pdf
                                            </a>
                                            <a @click.prevent="printInvoice" href="#"
                                               class="block px-4 py-2 text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out">
                                                <font-awesome-icon icon="print"/>
                                                Print Invoice
                                            </a>
                                            <a v-if="invoice.sponsor==='co_payer'" @click.prevent="printClaimForm"
                                               href="#"
                                               class="block px-4 py-2 text-sm leading-5 text-gray-700 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 transition duration-150 ease-in-out">
                                                <font-awesome-icon icon="print"/>
                                                Print Claim Form
                                            </a>
                                        </div>
                                    </template>
                                </jet-dropdown>
                            </div>
                            <div class="p-5 border-t border-gray-200 dark:border-dark-5">
                                <div class="flex justify-between">
                                    <span class="font-medium">Date</span>
                                    <span>{{ invoice.date }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="font-medium">Due Date</span>
                                    <span>{{ invoice.due_date }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="font-medium">Member</span>
                                    <span v-if="invoice.member_id">
                                        {{ invoice.member.name }}
                                    </span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="font-medium">Sponsor</span>
                                    <span v-if="invoice.sponsor=='cash'">Cash</span>
                                    <span v-if="invoice.sponsor=='co_payer'">
                                        <span v-if="invoice.co_payer">
                                                {{ invoice.co_payer.name }}
                                        </span>
                                    </span>
                                </div>
                            </div>
                            <div class="p-5 border-t border-gray-200 dark:border-dark-5">
                                <div class="flex justify-between">
                                    <span class="font-medium">Currency</span>
                                    <span v-if="invoice.currency">{{ invoice.currency.name }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="font-medium">Amount</span>
                                    <span>{{ invoice.amount }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="font-medium">Tax</span>
                                    <span>{{ invoice.tax_total }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="font-medium">Cash Amount</span>
                                    <span>{{ invoice.cash_amount }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="font-medium">CoPayer</span>
                                    <span>{{ invoice.co_payer_amount }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="font-medium">Paid</span>
                                    <span class="text-green-400">{{ invoice.amount - invoice.balance }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="font-medium">Balance</span>
                                    <span class="text-red-400">{{ invoice.balance }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white p-5 mt-5">
                            <div class="flex items-center">
                                <div class="font-medium text-lg">Notes</div>
                            </div>

                            <div class="mt-4">{{ invoice.client_notes }}</div>
                        </div>

                        <div class="bg-white p-5 mt-5">
                            <div class="flex items-center">
                                <div class="font-medium text-lg">Terms</div>
                            </div>
                            <div class="mt-4">{{ invoice.terms }}</div>
                        </div>
                    </div>
                    <div class="col-span-12 lg:col-span-8 xxl:col-span-9">
                        <div class="bg-white p-5">
                            <div class="flex justify-between">
                                <h3>Invoice Items</h3>
                            </div>
                            <table class="w-full whitespace-no-wrap mt-4">
                                <thead class="bg-gray-50">
                                <tr class="text-left font-bold">
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Name</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Qty</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Unit Cost</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Tax</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Cash</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">CoPayer</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Total</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="invoice_item in invoice.invoice_items" :key="invoice_item.id"
                                    class="hover:bg-gray-100 focus-within:bg-gray-100">
                                    <td class="border-t">
                                      <span class="px-6 py-4 flex items-center">
                                          {{ invoice_item.name }}
                                      </span>
                                    </td>
                                    <td class="border-t">
                                        <span class="px-6 py-4 flex items-center">
                                            {{ invoice_item.qty }}
                                        </span>
                                    </td>
                                    <td class="border-t">
                                        <span class="px-6 py-4 flex items-center">
                                            {{ invoice_item.unit_cost }}
                                        </span>
                                    </td>
                                    <td class="border-t">
                                        <span class="px-6 py-4 flex items-center">
                                            {{ invoice_item.tax_total }}
                                        </span>
                                    </td>
                                    <td class="border-t">
                                       <span class="px-6 py-4 flex items-center">
                                            {{ invoice_item.cash_amount }}
                                        </span>
                                    </td>
                                    <td class="border-t">
                                   <span class="px-6 py-4 flex items-center">
                                        {{ invoice_item.co_payer_amount }}
                                    </span>
                                    </td>
                                    <td class="border-t">
                                       <span class="px-6 py-4 flex items-center">
                                            {{ invoice_item.total }}
                                        </span>
                                    </td>

                                </tr>
                                <tr v-if="invoice.invoice_items.length === 0">
                                    <td class="border-t px-6 py-4" colspan="7">No items found.</td>
                                </tr>
                                </tbody>
                            </table>
                            <div class="flex justify-between mt-4">
                                <h3>Invoice Payments</h3>
                                <inertia-link class="btn btn-blue" :href="route('portal.payments.create',invoice.id)">
                                    <span>Pay Online </span>
                                </inertia-link>
                            </div>
                            <table class="w-full whitespace-no-wrap mt-4">
                                <thead class="bg-gray-50">
                                <tr class="text-left font-bold">
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">ID</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Payer</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Amount</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Method</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Receipt</th>
                                    <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Date</th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="invoice_payment in invoice.invoice_payments" :key="invoice_payment.id"
                                    class="hover:bg-gray-100 focus-within:bg-gray-100">
                                    <td class="border-t">
                                      <span class="px-6 py-4 flex items-center">
                                          {{ invoice_payment.id }}
                                      </span>
                                    </td>
                                    <td class="border-t">
                                        <span class="px-6 py-4 flex items-center">
                                            <span v-if="invoice_payment.paid_by=='member'">
                                                Member
                                            </span>
                                             <span v-if="invoice_payment.paid_by=='co_payer'">
                                                CoPayer
                                            </span>
                                        </span>
                                    </td>
                                    <td class="border-t">
                                        <span class="px-6 py-4 flex items-center">
                                            {{ invoice_payment.amount }}
                                        </span>
                                    </td>
                                    <td class="border-t">
                                        <span class="px-6 py-4 flex items-center">
                                            {{ invoice_payment.payment_type.name }}
                                        </span>
                                    </td>
                                    <td class="border-t">
                                       <span class="px-6 py-4 flex items-center">
                                            {{ invoice_payment.receipt }}
                                        </span>
                                    </td>
                                    <td class="border-t">
                                   <span class="px-6 py-4 flex items-center">
                                        {{ invoice_payment.date }}
                                    </span>
                                    </td>
                                    <td class="border-t w-px pr-2">
                                        <div class=" flex items-center space-x-2">
                                            <a href="#" @click.prevent="printPayment(invoice_payment.id)"
                                               tabindex="-1" class="text-blue-600 hover:text-blue-900">
                                                Print
                                            </a>
                                            <inertia-link :href="route('billing.payments.pdf', invoice_payment.id)"
                                                          tabindex="-1" class="text-blue-600 hover:text-blue-900">
                                                PDF
                                            </inertia-link>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="invoice.invoice_payments.length === 0">
                                    <td class="border-t px-6 py-4" colspan="8">No payments found.</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
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
import AppLayout from '@/Pages/MemberPortal/Layouts/AppLayout.vue'
import JetButton from "@/Jetstream/Button.vue";
import JetInput from "@/Jetstream/Input.vue";
import JetInputError from "@/Jetstream/InputError.vue";
import JetCheckbox from "@/Jetstream/Checkbox.vue";
import JetLabel from "@/Jetstream/Label.vue";
import Select from "@/Jetstream/Select.vue";
import FileInput from "@/Jetstream/FileInput.vue";
import TextareaInput from "@/Jetstream/TextareaInput.vue";
import JetDropdown from '@/Jetstream/Dropdown.vue'
import JetDropdownLink from '@/Jetstream/DropdownLink.vue'
import JetDialogModal from '@/Jetstream/DialogModal.vue'
import JetSecondaryButton from '@/Jetstream/SecondaryButton.vue'
import JetConfirmationModal from '@/Jetstream/ConfirmationModal.vue'
import JetDangerButton from '@/Jetstream/DangerButton.vue'
import print from 'print-js'

export default {
    props: {
        invoice: Object,
        printInvoicePayment: Boolean,
        invoicePaymentID: Number,
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
        JetDropdown,
        JetDropdownLink,
        JetDialogModal,
        JetSecondaryButton,
        JetConfirmationModal,
        JetDangerButton,

    },
    data() {
        return {
            confirmClaim: false,
            confirmPaymentDeletion: false,
            confirmingDeletion: false,
            selectedPaymentRecord: null,
            selectedRecord: null,
            processing: false,
            pageTitle: "Invoice Details",
            pageDescription: "Invoice Details",
        }

    },
    mounted() {
        if (this.printInvoicePayment) {
            this.printPayment(this.invoicePaymentID)
        }
    },
    methods: {

        printPayment(id) {
            axios.get(this.route('portal.payments.print', id)).then(response => {
                print({printable: response.data, type: 'raw-html'})
            })

        },
        printInvoice() {
            axios.get(this.route('portal.invoices.print', this.invoice.id)).then(response => {
                print({printable: response.data, type: 'raw-html'})
            })

        },
        printClaimForm() {
            axios.get(this.route('portal.invoices.print_claim_form', this.invoice.id)).then(response => {
                print({printable: response.data, type: 'raw-html'})
            })

        }

    }
}
</script>
<style scoped>

</style>
