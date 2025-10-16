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
                        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Shareholder</h2>
                        <inertia-link class="btn btn-blue" :href="route('clients.shareholders.index',client.id)">
                            <span>Back </span>
                        </inertia-link>
                    </div>
                    <div class="mt-4">
                        <form @submit.prevent="submit" enctype="multipart/form-data">
                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <jet-label for="name" value="Name"/>
                                    <jet-input id="name" type="text" class="mt-1 block w-full"
                                               v-model="form.name"/>
                                    <jet-input-error :message="form.errors.name" class="mt-2"/>

                                </div>
                                <div>
                                    <jet-label for="gender" value="Gender"/>
                                    <select
                                        class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                                        name="gender" v-model="form.gender" id="gender" required>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                    </select>
                                    <jet-input-error :message="form.errors.gender" class="mt-2"/>
                                </div>
                                <div>
                                    <jet-label for="dob" value="Date of Birth"/>
                                    <flat-pickr
                                        v-model="form.dob"
                                        class="form-control w-full"
                                        placeholder="Select date"
                                        id="dob"
                                        name="dob">
                                    </flat-pickr>
                                    <jet-input-error :message="form.errors.dob" class="mt-2"/>

                                </div>
                                <div>
                                    <jet-label for="shares" value="% Shares"/>
                                    <jet-input id="shares" type="text" class="block w-full" v-model="form.shares"/>
                                    <jet-input-error :message="form.errors.shares" class="mt-2"/>

                                </div>
                                <div>
                                    <jet-label for="itc_ref_no" value="ITC Ref No"/>
                                    <jet-input id="itc_ref_no" type="text" class="block w-full" v-model="form.itc_ref_no"/>
                                    <jet-input-error :message="form.errors.itc_ref_no" class="mt-2"/>
                                </div>
                                <div>
                                    <jet-label for="itc_ref_date" value="ITC Date"/>
                                    <flat-pickr
                                        v-model="form.itc_ref_date"
                                        class="form-control w-full"
                                        placeholder="Select date"
                                        id="itc_ref_date"
                                        name="itc_ref_date">
                                    </flat-pickr>
                                    <jet-input-error :message="form.errors.itc_ref_date" class="mt-2"/>

                                </div>
                                <div>
                                    <jet-label for="number_of_paid_debts" value="No of Paid Debts > Defauts"/>
                                    <jet-input id="number_of_paid_debts" type="text" class="block w-full" v-model="form.number_of_paid_debts"/>
                                    <jet-input-error :message="form.errors.number_of_paid_debts" class="mt-2"/>

                                </div>  <div>
                                <jet-label for="number_of_defaulted_debts" value="No of Defaults"/>
                                <jet-input id="number_of_defaulted_debts" type="text" class="block w-full" v-model="form.number_of_defaulted_debts"/>
                                <jet-input-error :message="form.errors.number_of_defaulted_debts" class="mt-2"/>

                            </div>
                                <div>
                                    <jet-label for="number_of_judgements" value="No of Judgements"/>
                                    <jet-input id="number_of_judgements" type="text" class="block w-full" v-model="form.number_of_judgements"/>
                                    <jet-input-error :message="form.errors.number_of_judgements" class="mt-2"/>

                                </div>
                                <div>
                                    <jet-label for="number_of_trace_alerts" value="No of Trace Alerts"/>
                                    <jet-input id="number_of_trace_alerts" type="text" class="block w-full" v-model="form.number_of_trace_alerts"/>
                                    <jet-input-error :message="form.errors.number_of_trace_alerts" class="mt-2"/>

                                </div>
                                <div>
                                    <jet-label for="blacklisted">
                                        <div class="flex items-center">
                                            <jet-checkbox name="visible_to_client" :value="form.blacklisted" id="blacklisted"  v-model:checked="form.blacklisted" />
                                            <div class="ml-2">
                                                Blacklisted
                                            </div>
                                        </div>
                                    </jet-label>
                                </div>
                                <div>
                                    <jet-label for="fraud_alert">
                                        <div class="flex items-center">
                                            <jet-checkbox name="fraud_alert" :value="form.fraud_alert" id="fraud_alert"  v-model:checked="form.fraud_alert" />
                                            <div class="ml-2">
                                                Fraud Alert
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
        shareholder: Object,

    },
    data() {
        return {
            form: this.$inertia.form({
                '_method': 'PUT',
                name: this.shareholder.name,
                description: this.shareholder.description,
                gender: this.shareholder.gender,
                itc_date: this.shareholder.itc_date,
                dob: this.shareholder.dob,
                shares: this.shareholder.shares,
                itc_ref_no: this.shareholder.itc_ref_no,
                itc_ref_date: this.shareholder.itc_ref_date,
                number_of_paid_debts: this.shareholder.number_of_paid_debts,
                number_of_defaulted_debts: this.shareholder.number_of_defaulted_debts,
                number_of_judgements: this.shareholder.number_of_judgements,
                number_of_trace_alerts: this.shareholder.number_of_trace_alerts,
                blacklisted: this.shareholder.blacklisted,
                fraud_alert: this.shareholder.fraud_alert,
            }),
            pageTitle: "Edit Client Shareholder",
            pageDescription: "Edit Client Shareholder",

        }
    },

    methods: {
        submit() {
            this.form.post(this.route('clients.shareholders.update', this.shareholder.id), {})
        },
    },
}
</script>

<style scoped>

</style>
