<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('settings.index')">Settings /
                </inertia-link>
                General
            </h2>
        </template>

        <div class=" mx-auto">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">
                <form @submit.prevent="submit" enctype="multipart/form-data">
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <jet-label for="company_name" value="Organisation Name"/>
                            <jet-input id="company_name" type="text" class="mt-1 block w-full"
                                       v-model="form.company_name"/>
                            <jet-input-error :message="form.errors.company_name" class="mt-2"/>
                        </div>
                        <div>
                            <jet-label for="company_email" value="Organisation Email"/>
                            <jet-input id="company_email" type="email" class="mt-1 block w-full"
                                       v-model="form.company_email"/>
                            <jet-input-error :message="form.errors.company_email" class="mt-2"/>
                        </div>
                        <div>
                            <jet-label for="company_mobile" value="Organisation Mobile"/>
                            <jet-input id="company_mobile" type="text" class="mt-1 block w-full"
                                       v-model="form.company_mobile"/>
                            <jet-input-error :message="form.errors.company_mobile" class="mt-2"/>
                        </div>
                        <div>
                            <jet-label for="company_tel" value="Organisation Tel"/>
                            <jet-input id="company_tel" type="text" class="mt-1 block w-full"
                                       v-model="form.company_tel"/>
                            <jet-input-error :message="form.errors.company_tel" class="mt-2"/>
                        </div>
                        <div>
                            <jet-label for="company_website" value="Organisation Website"/>
                            <jet-input id="company_website" type="text" class="mt-1 block w-full"
                                       v-model="form.company_website"/>
                            <jet-input-error :message="form.errors.company_website" class="mt-2"/>
                        </div>
                        <div>
                            <jet-label for="company_address" value="Organisation Address"/>
                            <textarea-input id="company_address" type="text" class="mt-1 block w-full"
                                            v-model="form.company_address"/>
                            <jet-input-error :message="form.errors.company_address" class="mt-2"/>
                        </div>
                        <div>
                            <jet-label for="company_logo" value="Logo"/>
                            <file-input v-model="form.company_logo" id="company_logo" class="mt-1 block w-full"
                                        type="file"/>
                            <jet-input-error :message="form.errors.company_logo" class="mt-2"/>
                        </div>
                        <div>
                            <jet-label for="company_small_logo" value="Small Logo"/>
                            <file-input v-model="form.company_small_logo" id="company_small_logo"
                                        class="mt-1 block w-full" type="file"/>
                            <jet-input-error :message="form.errors.company_small_logo" class="mt-2"/>
                        </div>
                        <div>
                            <jet-label for="company_letterhead" value="Letterhead"/>
                            <file-input v-model="form.company_letterhead" id="company_letterhead"
                                        class="mt-1 block w-full" type="file"/>
                            <jet-input-error :message="form.errors.company_letterhead" class="mt-2"/>
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
import Select from "@/Jetstream/Select.vue";
import JetButton from "@/Jetstream/Button.vue";
import JetInput from "@/Jetstream/Input.vue";
import JetCheckbox from "@/Jetstream/Checkbox.vue";
import JetInputError from "@/Jetstream/InputError.vue";
import FileInput from "@/Jetstream/FileInput.vue";
import TextareaInput from "@/Jetstream/TextareaInput.vue";

export default {
    components: {
        Select,
        AppLayout,
        JetButton,
        JetInput,
        JetCheckbox,
        JetInputError,
        FileInput,
        Icon,
        Pagination,
        SearchFilter,
        FilterSearch,
        JetLabel,
        SelectInput,
        JetConfirmationModal,
        JetDangerButton,
        JetSecondaryButton,
        TextareaInput,
    },
    props: {
        settings: Object,
        timezones: Object,
    },
    data() {
        return {
            form: this.$inertia.form({
                company_name: this.settings.company_name.setting_value,
                company_email: this.settings.company_email.setting_value,
                company_mobile: this.settings.company_mobile.setting_value,
                company_tel: this.settings.company_tel.setting_value,
                invoice_reference_prefix: this.settings.invoice_reference_prefix.setting_value,
                invoice_reference_format: this.settings.invoice_reference_format.setting_value,
                sales_email: this.settings.sales_email.setting_value,
                invoice_due_after_days: this.settings.invoice_due_after_days.setting_value,
                invoice_terms_and_conditions: this.settings.invoice_terms_and_conditions.setting_value,
                company_logo: null,
                company_small_logo: null,
                company_letterhead: null,
            }),
            pageTitle: "General Settings",
            pageDescription: "Manage Settings",
        }
    },
    methods: {
        submit() {
            this.form.post(this.route('settings.general.update'), {})
        },
    },
}
</script>

<style scoped>

</style>
