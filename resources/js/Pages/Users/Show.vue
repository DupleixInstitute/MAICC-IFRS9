<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('users.index')">Users
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> {{ profile.name }}
            </h2>
        </template>
        <div class="mx-auto">
            <div class="md:flex md:items-start">
                <div class="bg-white relative shadow-xl mb-4 mt-20 w-full md:w-3/12">
                    <div class="col-span-12 lg:col-span-4 xxl:col-span-3 flex lg:block flex-col-reverse">
                        <div class="intro-y box mt-5 lg:mt-0">
                            <user-menu :profile="profile"></user-menu>
                        </div>
                    </div>

                </div>
                <div class="w-full md:w-9/12 md:ml-4 bg-white sm:mt-4">
                    <table class="border-collapse w-full border border-gray-400 bg-white text-sm shadow-sm">
                        <tbody>
                        <tr>
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Name</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{ profile.name }}</td>
                        </tr>
                        <tr>
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Gender</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">
                                <span class="capitalize">{{ profile.gender }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Role</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{ profile.current_role }}</td>
                        </tr>
                        <tr>
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Branch</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500" v-if="profile.branch">{{ profile.branch?.name }}</td>
                        </tr>
                        <tr v-if="profile.current_role==='doctor'">
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Qualifications</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{ profile.qualifications }}</td>
                        </tr>
                        <tr v-if="profile.current_role==='doctor'">
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Practice Number</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{ profile.practice_number }}</td>
                        </tr>

                        <tr>
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">External ID</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{ profile.external_id }}</td>
                        </tr>
                        <tr>
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Mobile</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{ profile.mobile }}</td>
                        </tr>
                        <tr>
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Tel</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{ profile.tel }}</td>
                        </tr>
                        <tr>
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Email</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{ profile.email }}</td>
                        </tr>
                        <tr>
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Group Email</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{ profile.group_email ?? "N/A" }}</td>
                        </tr>
                        <tr>
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Zip</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{ profile.zip }}</td>
                        </tr>
                        <tr>
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Address</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{ profile.address }}</td>
                        </tr>
                        <tr>
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Last Login</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{ profile.last_login }}</td>
                        </tr>
                        <tr>
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Can reasign application to other users?</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{ profile.can_reassign ? 'Yes' : 'No' }}</td>
                        </tr>
                        </tbody>
                    </table>
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
import UserMenu from '@/Pages/Users/UserMenu.vue'

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
        UserMenu,
    },
    props: {
        profile: Object,

    },
    data() {
        return {

            confirmingUserDeletion: false,
            selectedRecord: null,
            pageTitle: "User Profile",
            pageDescription: "User Profile",

        }
    },
    watch: {

    },
    methods: {
        reset() {
            this.form = mapValues(this.form, () => null)
        },
        deleteAction(id) {
            this.confirmingUserDeletion = true
            this.selectedRecord = id
        },
        destroy() {

            this.$inertia.delete(this.route('users.destroy', this.profile.id))
            this.confirmingUserDeletion = false
        },
    },
}
</script>

<style scoped>

</style>
