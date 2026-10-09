<template>
    <app-layout :title="role.display_name" description="What people with this role may see and do">
        <template #actions>
            <inertia-link v-if="can('users.roles.update')" :href="route('users.roles.edit', role.id)" class="inline-flex items-center gap-2 rounded-lg bg-maiic-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-maiic-700"><font-awesome-icon icon="pen"/> Edit role</inertia-link>
            <inertia-link :href="route('users.roles.index')" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50">Back to roles</inertia-link>
        </template>

        <div class="space-y-4">
            <p class="flex flex-wrap items-center gap-2 text-xs text-gray-600">
                <span class="maiic-badge" :class="String(role.is_system) === '1' ? 'maiic-badge-gold' : 'maiic-badge-grey'">{{ String(role.is_system) === '1' ? 'Built in' : 'Custom' }}</span>
                <span>System name: <b>{{ role.name }}</b></span><span class="text-gray-300">|</span>
                <span>Group email: <b>{{ role.group_email || '-' }}</b></span><span class="text-gray-300">|</span>
                <span>Email everyone in the role: <b>{{ Number(role.send_email_to_role_members) ? 'Yes' : 'No' }}</b></span><span class="text-gray-300">|</span>
                <span>Members can hand work to each other: <b>{{ Number(role.can_reassign) ? 'Yes' : 'No' }}</b></span>
            </p>
            <div class="maiic-panel p-4">
                <permission-picker :model-value="role.permissions || []" :permissions="permissions" readonly/>
            </div>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import PermissionPicker from './Partials/PermissionPicker.vue'

export default {
    components: { AppLayout, PermissionPicker },
    props: { role: Object, permissions: Object },
}
</script>
