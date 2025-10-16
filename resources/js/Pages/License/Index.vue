<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                License
            </h2>
        </template>

        <div class=" mx-auto">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">
                <div class="grid grid-cols-2 gap-2">
                    <div class="mt-2">
                        <div v-if="decodedPurchaseCode">
                            <table class="w-full whitespace-no-wrap">
                                <tbody>
                                <tr>
                                    <td class="border-t">Licensed To</td>
                                    <td class="border-t">
                                        {{ decodedPurchaseCode.licensed_to }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="border-t">Product</td>
                                    <td class="border-t">
                                        {{ decodedPurchaseCode.product.name }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="border-t">Package</td>
                                    <td class="border-t">
                                        {{ decodedPurchaseCode.product_package.name }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="border-t">Start Date</td>
                                    <td class="border-t">
                                        {{ moment(decodedPurchaseCode.start_date).format("YYYY-MM-DD") }}
                                    </td>
                                </tr>
                                <tr>
                                    <td class="border-t">Expires</td>
                                    <td class="border-t">
                                        <span>
                                            {{ moment(decodedPurchaseCode.end_date).format("YYYY-MM-DD") }}
                                        </span>
                                        <span v-if="decodedPurchaseCode.active" class="ml-1 px-2 rounded-full bg-green-100 text-green-800">
                                            Active
                                        </span>
                                        <span v-if="!decodedPurchaseCode.active" class="ml-1 px-2 rounded-full bg-red-100 text-red-800">
                                            Expired
                                        </span>
                                    </td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div>
                        <div v-if="!decodedPurchaseCode">
                            <p>No license found on this account. Please enter your licence</p>
                        </div>
                        <form @submit.prevent="submit">
                            <div class="grid grid-cols-1 gap-2 mt-2">
                                <div class="">
                                    <jet-label for="purchase_code" value="License Key"/>
                                    <textarea-input id="purchase_code" class="mt-1 block w-full"
                                                    v-model="form.purchase_code" required/>
                                    <jet-input-error :message="form.errors.purchase_code" class="mt-2"/>
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
import JetInputError from "@/Jetstream/InputError.vue";
import TextareaInput from "@/Jetstream/TextareaInput.vue";
import JetButton from "@/Jetstream/Button.vue";

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
        JetInputError,
        TextareaInput,
        JetButton,
    },
    props: {
        purchaseCode: String,
        decodedPurchaseCode: Object,

    },
    data() {
        return {
            form: this.$inertia.form({
                purchase_code: this.purchaseCode,
            }),
            confirmingDeletion: false,
            selectedRecord: null,
            pageTitle: "License",
            pageDescription: "Manage Licenses",

        }
    },
    watch: {},
    methods: {
        submit() {
            this.form.post(this.route('license.verify'), {})

        },
    },
}
</script>

<style scoped>

</style>
