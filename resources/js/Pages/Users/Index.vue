<template>
    <app-layout title="Users" description="Who can sign in to the system, and the role that sets what each person can do">
        <template #actions>
            <inertia-link v-if="can('users.create')" :href="route('users.create')"
                          class="inline-flex items-center gap-2 rounded-lg bg-maiic-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-maiic-700">
                <font-awesome-icon icon="plus"/> Add user
            </inertia-link>
        </template>

        <div class="maiic-panel">
            <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 px-4 py-3">
                <input v-model="form.search" type="search" aria-label="Search" placeholder="Search name or email" class="maiic-input w-64 py-1.5"/>
                <select v-model="form.role" aria-label="Role" title="Role" class="maiic-select w-48 py-1.5">
                    <option :value="null">All roles</option>
                    <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.display_name }}</option>
                </select>
                <button v-if="form.search || form.role" type="button" class="text-xs font-bold text-gray-500 hover:text-maiic-700" @click="reset">Clear</button>
                <span class="ml-auto text-xs text-gray-500"><b class="text-sm text-gray-900">{{ users.total ?? users.data.length }}</b> users</span>
            </div>
            <div class="maiic-table-wrap">
                <table class="maiic-table">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>Role</th>
                        <th class="num">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="user in users.data" :key="user.id">
                        <td>
                            <inertia-link :href="route('users.show', user.id)" class="flex items-center gap-2 font-semibold text-gray-900 hover:text-maiic-700">
                                <img v-if="user.profile_photo_url" class="h-7 w-7 rounded-full object-cover" :src="user.profile_photo_url" alt="">
                                {{ user.name }}
                                <span v-if="user.deleted_at" class="maiic-badge maiic-badge-grey">Removed</span>
                            </inertia-link>
                        </td>
                        <td>{{ user.email }}</td>
                        <td>{{ user.mobile || '-' }}</td>
                        <td>
                            <span v-for="role in user.roles" :key="role.id" class="maiic-badge maiic-badge-green mr-1">{{ role.display_name }}</span>
                            <span v-if="!user.roles || !user.roles.length" class="maiic-badge maiic-badge-grey">No role</span>
                        </td>
                        <td class="w-px">
                            <row-actions :view-href="route('users.show', user.id)"
                                         :edit-href="can('users.update') ? route('users.edit', user.id) : null"
                                         :deletable="can('users.destroy')"
                                         @delete="destroy(user)"/>
                        </td>
                    </tr>
                    <tr v-if="users.data.length === 0">
                        <td class="maiic-empty" colspan="5">No users match. Clear the search, or add a user.</td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="users.links && users.links.length > 3" class="border-t border-gray-100 px-4 pb-4"><pagination :links="users.links"/></div>
        </div>
        <HelpManual/>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import RowActions from '@/Shared/RowActions.vue'
import HelpManual from '@/Components/HelpManual.vue'
import { confirmDialog } from '@/Components/confirmDialog'
import pickBy from 'lodash/pickBy'

export default {
    components: { AppLayout, Pagination, RowActions, HelpManual },
    props: { users: Object, filters: Object, roles: [Object, Array] },
    data() {
        return { form: { search: this.filters.search, role: this.filters.role } }
    },
    watch: {
        form: {
            handler: _.debounce(function () {
                this.$inertia.get(this.route('users.index'), pickBy(this.form), { preserveState: true, replace: true })
            }, 450),
            deep: true,
        },
    },
    methods: {
        reset() {
            this.form = { search: null, role: null }
        },
        async destroy(user) {
            if (!(await confirmDialog({
                title: 'Delete ' + user.name + '?',
                message: 'Their access is removed straight away. This cannot be undone.',
                confirmLabel: 'Delete user',
                tone: 'danger',
            }))) return
            this.$inertia.delete(this.route('users.destroy', user.id))
        },
    },
}
</script>
