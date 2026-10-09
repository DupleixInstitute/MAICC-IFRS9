<template>
    <app-layout :title="'Edit ' + role.display_name" description="Change the role's name, emails and what people with it may see and do">
        <template #actions>
            <inertia-link :href="route('users.roles.show', role.id)" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50">View role</inertia-link>
            <inertia-link :href="route('users.roles.index')" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50">Back to roles</inertia-link>
        </template>

        <form class="maiic-panel p-6" @submit.prevent="submit">
            <div class="maiic-section-title mt-0">Role</div>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                <div>
                    <label for="display_name" class="maiic-flabel">Display name</label>
                    <input id="display_name" v-model="form.display_name" type="text" class="maiic-input" required>
                    <p v-if="form.errors.display_name" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.display_name }}</p>
                </div>
                <div>
                    <label for="name" class="maiic-flabel">System name{{ isSystem ? ' (built in, cannot change)' : '' }}</label>
                    <input id="name" v-model="form.name" type="text" class="maiic-input disabled:bg-gray-50 disabled:text-gray-500" required :disabled="isSystem">
                    <p v-if="form.errors.name" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label for="group_email" class="maiic-flabel">Group email (optional)</label>
                    <input id="group_email" v-model="form.group_email" type="email" class="maiic-input">
                    <p v-if="form.errors.group_email" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.group_email }}</p>
                </div>
                <div>
                    <label for="send_email_to_role_members" class="maiic-flabel">Email everyone in the role</label>
                    <select id="send_email_to_role_members" v-model="form.send_email_to_role_members" class="maiic-select">
                        <option :value="1">Yes</option>
                        <option :value="0">No</option>
                    </select>
                </div>
                <div>
                    <label for="can_reassign" class="maiic-flabel">Members can hand work to each other</label>
                    <select id="can_reassign" v-model="form.can_reassign" class="maiic-select">
                        <option :value="1">Yes</option>
                        <option :value="0">No</option>
                    </select>
                    <p v-if="form.errors.can_reassign" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.can_reassign }}</p>
                </div>
            </div>

            <div class="maiic-section-title">Permissions <span class="ml-1 font-semibold normal-case tracking-normal text-gray-500">({{ form.permissions.length }} ticked)</span></div>
            <permission-picker v-model="form.permissions" :permissions="permissions"/>

            <div class="mt-6 flex justify-end gap-2 border-t border-gray-100 pt-5">
                <inertia-link :href="route('users.roles.index')" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm hover:bg-gray-50">Cancel</inertia-link>
                <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-lg bg-maiic-600 px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-maiic-700 disabled:opacity-50">
                    <font-awesome-icon icon="check"/> {{ form.processing ? 'Saving...' : 'Save changes' }}
                </button>
            </div>
        </form>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import PermissionPicker from './Partials/PermissionPicker.vue'

export default {
    components: { AppLayout, PermissionPicker },
    props: { role: Object, permissions: Object },
    data() {
        return {
            form: this.$inertia.form({
                name: this.role.name,
                display_name: this.role.display_name,
                permissions: this.role.permissions || [],
                send_email_to_role_members: Number(this.role.send_email_to_role_members || 0),
                group_email: this.role.group_email,
                can_reassign: Number(this.role.can_reassign || 0),
            }),
        }
    },
    computed: {
        isSystem() { return String(this.role.is_system) === '1' },
    },
    methods: {
        submit() { this.form.put(this.route('users.roles.update', this.role.id)) },
    },
}
</script>
