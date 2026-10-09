<template>
    <app-layout title="Roles & Permissions" description="Each role is a set of permissions; give a user a role to decide what they can see and do">
        <template #actions>
            <inertia-link v-if="can('users.roles.create')" :href="route('users.roles.create')"
                          class="inline-flex items-center gap-2 rounded-lg bg-maiic-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-maiic-700">
                <font-awesome-icon icon="plus"/> Add role
            </inertia-link>
        </template>

        <div class="maiic-panel">
            <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 px-4 py-3">
                <input v-model="form.search" type="search" aria-label="Search" placeholder="Search role" class="maiic-input w-64 py-1.5"/>
                <button v-if="form.search" type="button" class="text-xs font-bold text-gray-500 hover:text-maiic-700" @click="form.search = null">Clear</button>
                <span class="ml-auto text-xs text-gray-500"><b class="text-sm text-gray-900">{{ roles.total ?? roles.data.length }}</b> roles</span>
            </div>
            <div class="maiic-table-wrap">
                <table class="maiic-table">
                    <thead>
                    <tr>
                        <th>Role</th>
                        <th>Type</th>
                        <th class="num">Permissions</th>
                        <th class="num">Users</th>
                        <th>Group email</th>
                        <th>Email everyone in the role</th>
                        <th class="num">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="role in roles.data" :key="role.id">
                        <td>
                            <inertia-link :href="route('users.roles.show', role.id)" class="font-semibold text-gray-900 hover:text-maiic-700">{{ role.display_name }}</inertia-link>
                            <div class="text-xs text-gray-400">{{ role.name }}</div>
                        </td>
                        <td><span class="maiic-badge" :class="role.is_system == '1' ? 'maiic-badge-gold' : 'maiic-badge-grey'">{{ role.is_system == '1' ? 'Built in' : 'Custom' }}</span></td>
                        <td class="num">{{ role.permissions_count ?? '-' }}</td>
                        <td class="num">{{ role.users_count ?? '-' }}</td>
                        <td>{{ role.group_email || '-' }}</td>
                        <td><span class="maiic-badge" :class="role.send_email_to_role_members == '1' ? 'maiic-badge-green' : 'maiic-badge-grey'">{{ role.send_email_to_role_members == '1' ? 'Yes' : 'No' }}</span></td>
                        <td class="w-px">
                            <row-actions :view-href="route('users.roles.show', role.id)"
                                         :edit-href="can('users.roles.update') ? route('users.roles.edit', role.id) : null"
                                         :deletable="can('users.roles.destroy') && role.is_system == '0'"
                                         @delete="destroy(role)"/>
                        </td>
                    </tr>
                    <tr v-if="roles.data.length === 0">
                        <td class="maiic-empty" colspan="7">No roles match. Clear the search, or add a role.</td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="roles.links && roles.links.length > 3" class="border-t border-gray-100 px-4 pb-4"><pagination :links="roles.links"/></div>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import RowActions from '@/Shared/RowActions.vue'
import { confirmDialog } from '@/Components/confirmDialog'
import pickBy from 'lodash/pickBy'

export default {
    components: { AppLayout, Pagination, RowActions },
    props: { roles: Object, filters: Object },
    data() {
        return { form: { search: this.filters?.search ?? null } }
    },
    watch: {
        form: {
            handler: _.debounce(function () {
                this.$inertia.get(this.route('users.roles.index'), pickBy(this.form), { preserveState: true, replace: true })
            }, 450),
            deep: true,
        },
    },
    methods: {
        async destroy(role) {
            if (!(await confirmDialog({
                title: 'Delete the role ' + role.display_name + '?',
                message: 'Users who have only this role lose its permissions straight away. This cannot be undone.',
                confirmLabel: 'Delete role',
                tone: 'danger',
            }))) return
            this.$inertia.delete(this.route('users.roles.destroy', role.id))
        },
    },
}
</script>
