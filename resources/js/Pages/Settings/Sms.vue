<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('settings.index')">Settings /
                </inertia-link>
                SMS
            </h2>
        </template>
            <div class=" mx-auto">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">
                    <form @submit.prevent="submit" enctype="multipart/form-data">
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <jet-label for="sms_enabled"
                                           value="SMS Enabled"/>
                                <select
                                    class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                                    name="sms_enabled" v-model="form.sms_enabled" id="sms_enabled">
                                    <option value="yes">Yes</option>
                                    <option value="no">No</option>
                                </select>
                                <jet-input-error :message="form.errors.sms_enabled" class="mt-2"/>
                            </div>
                            <div v-if="form.sms_enabled==='yes'">
                                <jet-label for="active_sms_gateway"
                                           value="Default SMS Gateway"/>
                                <select
                                    class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                                    name="active_sms_gateway" v-model="form.active_sms_gateway" id="active_sms_gateway">
                                    <option v-for="item in smsGateways" :value="item.id">{{ item.name }}</option>
                                </select>
                                <jet-input-error :message="form.errors.active_sms_gateway" class="mt-2"/>
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

export default {
    metaInfo: {title: 'Tariffs'},
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
    },
    props: {
        settings: Object,
        smsGateways: Object,
    },
    data() {
        return {
            form: this.$inertia.form({
                sms_enabled: this.settings.sms_enabled.setting_value,
                active_sms_gateway: this.settings.active_sms_gateway.setting_value,
            }),
            pageTitle: "SMS Settings",
            pageDescription: "Manage Settings",
        }
    },
    methods: {
        submit() {
            this.form.post(this.route('settings.sms.update'), {})
        },
    },
}
</script>

<style scoped>

</style>
