<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Users
            </h2>
        </template>
        <div class=" mx-auto mb-4 flex justify-between items-center">
            <filter-search v-model="form.search" class="w-full max-w-md mr-4" @reset="reset">
                <div class="w-80 mt-2 px-4 py-6 shadow-xl bg-white rounded">
                    <div class="mb-2">
                        <jet-label for="gender" value="Gender"/>
                        <select v-model="form.gender"
                                class="mt-1 w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                            <option :value="null"/>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <jet-label for="role" value="Role"/>
                        <select v-model="form.role"
                                class="mt-1 w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                            <option :value="null"/>
                            <option v-for="role in roles" :value="role.id">{{ role.display_name }}</option>
                        </select>
                    </div>
                </div>
            </filter-search>
            <inertia-link v-if="can('users.create')" class="btn btn-blue" :href="route('users.create')">
                <span>Create </span>
                <span class="hidden md:inline">User</span>
            </inertia-link>
        </div>
        <div class=" mx-auto">
            <div class="bg-white rounded shadow overflow-x-auto">
                <table class="w-full whitespace-no-wrap">
                    <thead class="bg-gray-50">
                    <tr class="text-left font-bold">
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Name</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Email</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Mobile</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Gender</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Role</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-100 focus-within:bg-gray-100">
                        <td class="border-t">
                            <inertia-link class="px-6 py-4 flex items-center focus:text-indigo-500"
                                          :href="route('users.show', user.id)">
                                <img v-if="user.profile_photo_url" class="block w-5 h-5 rounded-full mr-2 -my-2"
                                     :src="user.profile_photo_url">
                                {{ user.name }}
                                <icon v-if="user.deleted_at" name="trash"
                                      class="flex-shrink-0 w-3 h-3 fill-gray-400 ml-2"/>
                            </inertia-link>
                        </td>
                        <td class="border-t">
                            <inertia-link class="px-6 py-4 flex items-center" :href="route('users.show', user.id)"
                                          tabindex="-1">
                                {{ user.email }}
                            </inertia-link>
                        </td>
                        <td class="border-t">
                            <inertia-link class="px-6 py-4 flex items-center" :href="route('users.show', user.id)"
                                          tabindex="-1">
                                {{ user.mobile }}
                            </inertia-link>
                        </td>
                        <td class="border-t">
                            <inertia-link class="px-6 py-4 flex items-center" :href="route('users.show', user.id)"
                                          tabindex="-1">
                                {{ user.gender }}
                            </inertia-link>
                        </td>
                        <td class="border-t">
                            <inertia-link class="px-6 py-4 flex items-center" :href="route('users.show', user.id)"
                                          tabindex="-1">
                                <span v-for="role in user.roles"
                                      class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                     {{ role.display_name }}
                                </span>
                            </inertia-link>
                        </td>
                        <td class="border-t w-px pr-2">
                            <div class=" flex items-center space-x-2">
                                <inertia-link :href="route('users.show', user.id)"
                                              tabindex="-1" class="text-blue-600 hover:text-blue-900">
                                    View
                                </inertia-link>
                                <inertia-link v-if="can('users.update')" :href="route('users.edit', user.id)"
                                              tabindex="-1" class="text-indigo-600 hover:text-indigo-900">
                                    Edit
                                </inertia-link>
                                <a href="#" v-if="can('users.destroy')" @click="deleteAction(user.id)"
                                   class="text-red-600 hover:text-red-900">Delete</a>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="users.length === 0">
                        <td class="border-t px-6 py-4" colspan="6">No users found.</td>
                    </tr>
                    </tbody>
                </table>

            </div>
            <pagination :links="users.links"/>
        </div>
        <jet-confirmation-modal :show="confirmingUserDeletion" @close="confirmingUserDeletion = false">
            <template #title>
                Delete Account
            </template>

            <template #content>
                Are you sure you want to delete your account? Once your account is deleted, all of its resources and
                data will be permanently deleted.
            </template>

            <template #footer>
                <jet-secondary-button @click.native="confirmingUserDeletion = false">
                    Nevermind
                </jet-secondary-button>

                <jet-danger-button class="ml-2" @click.native="destroy" :class="{ 'opacity-25': form.processing }"
                                   :disabled="form.processing">
                    Delete Account
                </jet-danger-button>
            </template>
        </jet-confirmation-modal>
        <teleport to="head">
            <title>{{ pageTitle }}</title>
            <meta property="og:description" :content="pageDescription">
        </teleport>
        <HelpManual />
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
import HelpManual from '../../Components/HelpManual.vue';

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
    },
    props: {
        users: Object,
        filters: Object,
        roles: Object,

    },
    data() {
        return {
            form: {
                search: this.filters.search,
                role: this.filters.role,
                gender: this.filters.gender,
                processing: false
            },
            confirmingUserDeletion: false,
            selectedRecord: null,
            pageTitle: "Users",
            pageDescription: "Manage Users",

        }
    },
    watch: {
        form: {
            handler: _.debounce(function () {
                let query = pickBy(this.form)
                this.$inertia.get(this.route('users.index', Object.keys(query).length ? query : {}))
            }, 500),
            deep: true,
        },
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

            this.$inertia.delete(this.route('users.destroy', this.selectedRecord))
            this.confirmingUserDeletion = false
        },
    },
}
</script>

<style scoped>

</style>
