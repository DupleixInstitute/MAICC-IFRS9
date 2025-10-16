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
                        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Add User</h2>
                        <inertia-link class="btn btn-blue" :href="route('clients.login_details.index',client.id)">
                            <span>Back </span>
                        </inertia-link>
                    </div>
                    <div class="mt-4">
                        <form @submit.prevent="submit">
                            <div class="grid grid-cols-1 gap-4">
                                <div>
                                    <jet-label for="existing_user">
                                        <div class="flex items-center">
                                            <jet-checkbox name="existing_user" id="existing_user"  v-model:checked="form.existing_user" />
                                            <div class="ml-2">
                                                Existing User
                                            </div>
                                        </div>
                                    </jet-label>
                                </div>
                                <div v-if="form.existing_user===true">
                                    <div class="grid grid-cols-1 gap-4 mt-4">
                                        <div>
                                            <jet-label for="user_id" value="User"/>
                                            <Multiselect
                                                id="user_id"
                                                v-model="form.user_id"
                                                mode="single"
                                                :searchable="true"
                                                v-bind="usersMultiSelect"
                                            />
                                            <jet-input-error :message="form.errors.user_id" class="mt-2"/>
                                        </div>
                                    </div>
                                </div>
                                <div v-else>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <jet-label for="first_name" value="First Name"/>
                                            <jet-input id="first_name" type="text" class="mt-1 block w-full"
                                                       v-model="form.first_name"
                                                       required
                                                       autofocus autocomplete="first_name"/>
                                            <jet-input-error :message="form.errors.first_name" class="mt-2"/>
                                        </div>
                                        <div class="">
                                            <jet-label for="last_name" value="Last Name"/>
                                            <jet-input id="last_name" type="text" class="mt-1 block w-full" v-model="form.last_name"
                                                       required autocomplete="last_name"/>
                                            <jet-input-error :message="form.errors.last_name" class="mt-2"/>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 mt-4 space-x-4">
                                        <div>
                                            <jet-label for="email" value="Email"/>
                                            <jet-input id="email" type="email" class="mt-1 block w-full" v-model="form.email"
                                                       required/>
                                            <jet-input-error :message="form.errors.email" class="mt-2"/>

                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 mt-4 space-x-4">
                                        <div>
                                            <jet-label for="password" value="Password"/>
                                            <jet-input id="password" type="password" class="mt-1 block w-full"
                                                       v-model="form.password"
                                                       required autocomplete="new-password"/>
                                            <jet-input-error :message="form.errors.password" class="mt-2"/>

                                        </div>

                                        <div>
                                            <jet-label for="password_confirmation" value="Confirm Password"/>
                                            <jet-input id="password_confirmation" type="password" class="mt-1 block w-full"
                                                       v-model="form.password_confirmation" required autocomplete="new-password"/>
                                            <jet-input-error :message="form.errors.password_confirmation" class="mt-2"/>

                                        </div>
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
const fetchUsers = async (query) => {
    let where = ''
    if (query) {

    }
    const response = await fetch(
        route('users.search') + '?type=client&s=' + query,
        {}
    );

    const data = await response.json(); // Here you have the data that you need
    return data.map((item) => {
        return {value: item.id, label: item.name, item: item}
    })
}
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
import FileInput from "@/Jetstream/FileInput.vue";
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
        FileInput,
    },
    props: {
        client: Object,

    },
    data() {
        return {
            form: this.$inertia.form({
                existing_user: false,
                client_id: null,
                user_id: null,
                first_name: this.client.first_name,
                last_name: this.client.last_name,
                email: this.client.email,
                password: null,
                password_confirmation: null,
            }),
            usersMultiSelect: {
                value: null,
                remark: null,
                placeholder: 'Search for User',
                filterResults: false,
                minChars: 2,
                resolveOnLoad: false,
                delay: 4,
                searchable: true,
                options: async (query) => {
                    return await fetchUsers(query)
                }
            },
            pageTitle: "Create Client Login Details",
            pageDescription: "Create Client Login Details",

        }
    },

    methods: {
        submit() {
            this.form.post(this.route('clients.login_details.store', this.client.id), {})
        },
    },
}
</script>

<style scoped>

</style>
