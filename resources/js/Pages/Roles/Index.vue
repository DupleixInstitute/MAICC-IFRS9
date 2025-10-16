<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Roles
            </h2>
        </template>
        <div class=" mx-auto mb-4 flex justify-between items-center">
            <filter-search v-model="form.search" class="w-full max-w-md mr-4" @reset="reset">
                <div class="w-80 mt-2 px-4 py-6 shadow-xl bg-white rounded">
                </div>
            </filter-search>
            <inertia-link v-if="can('users.roles.create')" class="btn btn-blue" :href="route('users.roles.create')">
                <span>Create </span>
                <span class="hidden md:inline">Role</span>
            </inertia-link>
        </div>
        <div class=" mx-auto">
            <div class="bg-white rounded shadow overflow-x-auto">
                <table class="w-full whitespace-no-wrap">
                    <thead class="bg-gray-50">
                    <tr class="text-left font-bold">
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Name</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">System</th>
                        <th class="font-medium text-gray-500">Group Email</th>
                        <th class="font-medium text-gray-500">Send Group Email To All In Role?</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="role in roles.data" :key="role.id" class="hover:bg-gray-100 focus-within:bg-gray-100">
                        <td class="border-t">
                            <inertia-link class="px-6 py-4 flex items-center" :href="route('users.roles.show', role.id)"
                                          tabindex="-1">
                                {{ role.display_name }}
                            </inertia-link>
                        </td>
                        <td class="border-t">
                            <span class="px-6 py-4 flex items-center" v-if="role.is_system=='0'">No</span>
                            <span class="px-6 py-4 flex items-center" v-if="role.is_system=='1'">Yes</span>
                        </td>
                        <td class="border-t">
                            <span v-if="role.group_email">{{ role.group_email }}</span>
                            <span v-else class="text-red-500">N/A</span>
                        </td>
                        <td class="border-t">
                            <span class="px-6 py-4 flex items-center text-green-500" v-if="role.send_email_to_role_members=='1'">Yes</span>
                            <span class="px-6 py-4 flex items-center text-red-500" v-if="role.send_email_to_role_members=='0'">No</span>
                        </td>
                        <td class="border-t w-px pr-2">
                            <div class=" flex items-center space-x-2">
                                <inertia-link :href="route('users.roles.show', role.id)"
                                              tabindex="-1" class="text-blue-600 hover:text-blue-900">
                                    View
                                </inertia-link>
                                <inertia-link v-if="can('users.update')" :href="route('users.roles.edit', role.id)"
                                              tabindex="-1" class="text-indigo-600 hover:text-indigo-900">
                                    Edit
                                </inertia-link>
                                <a href="#" v-if="can('users.roles.destroy') && role.is_system=='0'" @click="deleteAction(role.id)"
                                   class="text-red-600 hover:text-red-900">Delete</a>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="roles.length === 0">
                        <td class="border-t px-6 py-4" colspan="3">No roles found.</td>
                    </tr>
                    </tbody>
                </table>

            </div>
            <pagination :links="roles.links"/>
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
        roles: Object,
        filters: Object,

    },
    data() {
        return {
            form: {
                processing: false
            },
            confirmingUserDeletion: false,
            selectedRecord: null,
            pageTitle: "Roles",
            pageDescription: "Manage Roles",

        }
    },
    watch: {
        form: {
            handler: _.debounce(function () {
                let query = pickBy(this.form)
                this.$inertia.get(this.route('users.roles.index', Object.keys(query).length ? query : {}))
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

            this.$inertia.delete(this.route('users.roles.destroy', this.selectedRecord))
            this.confirmingUserDeletion = false
        },
    },
}
</script>

<style scoped>

</style>
