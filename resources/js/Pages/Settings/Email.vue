<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('settings.index')">Settings /
                </inertia-link>
                System
            </h2>
        </template>
        <div class=" mx-auto">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">
                <form @submit.prevent="submit" enctype="multipart/form-data">
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <jet-label for="mail_mailer"
                                       value="Mailer"/>
                            <select
                                class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                                name="mail_mailer" v-model="form.mail_mailer" id="mail_mailer" required>
                                <option value="smtp">smtp</option>
                                <option value="sendmail">Sendmail</option>
                            </select>
                            <jet-input-error :message="form.errors.mail_mailer" class="mt-2"/>
                        </div>
                        <div class="grid grid-cols-1 gap-4" v-if="form.mail_mailer==='smtp'">
                            <div>
                                <jet-label for="mail_host"
                                           value="Mail Host"/>
                                <jet-input id="mail_host" type="text" class="mt-1 block w-full"
                                           v-model="form.mail_host"/>
                                <jet-input-error :message="form.errors.mail_host" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="mail_port"
                                           value="Mail Port"/>
                                <jet-input id="mail_port" type="text" class="mt-1 block w-full"
                                           v-model="form.mail_port"/>
                                <jet-input-error :message="form.errors.mail_port" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="mail_username"
                                           value="Mail Username"/>
                                <jet-input id="mail_username" type="text" class="mt-1 block w-full"
                                           v-model="form.mail_username"/>
                                <jet-input-error :message="form.errors.mail_username" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="mail_password"
                                           value="Mail Password"/>
                                <jet-input id="mail_password" type="text" class="mt-1 block w-full"
                                           v-model="form.mail_password"/>
                                <jet-input-error :message="form.errors.mail_password" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="mail_encryption"
                                           value="Mail Encryption"/>
                                <jet-input id="mail_encryption" type="text" class="mt-1 block w-full"
                                           v-model="form.mail_encryption"/>
                                <jet-input-error :message="form.errors.mail_encryption" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="mail_from_address"
                                           value="Mail From Address"/>
                                <jet-input id="mail_from_address" type="text" class="mt-1 block w-full"
                                           v-model="form.mail_from_address"/>
                                <jet-input-error :message="form.errors.mail_from_address" class="mt-2"/>
                            </div>
                            <div>
                                <jet-label for="mail_from_name"
                                           value="Mail From Name"/>
                                <jet-input id="mail_from_name" type="text" class="mt-1 block w-full"
                                           v-model="form.mail_from_name"/>
                                <jet-input-error :message="form.errors.mail_from_name" class="mt-2"/>
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
        timezones: Object,
    },
    data() {
        return {
            form: this.$inertia.form({
                mail_mailer: this.settings.mail_mailer.setting_value,
                mail_host: this.settings.mail_host.setting_value,
                mail_port: this.settings.mail_port.setting_value,
                mail_username: this.settings.mail_username.setting_value,
                mail_password: this.settings.mail_password.setting_value,
                mail_encryption: this.settings.mail_encryption.setting_value,
                mail_from_address: this.settings.mail_from_address.setting_value,
                mail_from_name: this.settings.mail_from_name.setting_value,
            }),
            pageTitle: "Email Settings",
            pageDescription: "Manage Settings",
        }
    },
    methods: {
        submit() {
            this.form.post(this.route('settings.email.update'), {})
        },
    },
}
</script>

<style scoped>

</style>
