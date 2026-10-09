<template>
    <app-layout title="Add role" description="Name the role and tick what people with it may see and do">
        <template #actions>
            <inertia-link :href="route('users.roles.index')" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50">Back to roles</inertia-link>
        </template>

        <form class="maiic-panel p-6" @submit.prevent="submit">
            <div class="maiic-section-title mt-0">Role</div>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                <div>
                    <label for="display_name" class="maiic-flabel">Display name</label>
                    <input id="display_name" v-model="form.display_name" type="text" class="maiic-input" required autofocus placeholder="e.g. Finance Officer">
                    <p v-if="form.errors.display_name" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.display_name }}</p>
                </div>
                <div>
                    <label for="name" class="maiic-flabel">System name (no spaces)</label>
                    <input id="name" v-model="form.name" type="text" class="maiic-input" required placeholder="e.g. finance_officer">
                    <p v-if="form.errors.name" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label for="group_email" class="maiic-flabel">Group email (optional)</label>
                    <input id="group_email" v-model="form.group_email" type="email" class="maiic-input">
                    <p v-if="form.errors.group_email" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.group_email }}</p>
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input v-model="form.send_email_to_role_members" type="checkbox" class="rounded border-gray-300 text-maiic-600 focus:ring-maiic-500"> Email everyone in the role
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input v-model="form.can_reassign_role_members" type="checkbox" class="rounded border-gray-300 text-maiic-600 focus:ring-maiic-500"> Members can hand work to each other
                </label>
            </div>

            <div class="maiic-section-title">Permissions <span class="ml-1 font-semibold normal-case tracking-normal text-gray-500">({{ form.permissions.length }} ticked)</span></div>
            <p v-if="form.errors.permissions" class="mb-2 text-xs font-semibold text-red-600">{{ form.errors.permissions }}</p>
            <permission-picker v-model="form.permissions" :permissions="permissions"/>

            <div class="mt-6 flex justify-end gap-2 border-t border-gray-100 pt-5">
                <inertia-link :href="route('users.roles.index')" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm hover:bg-gray-50">Cancel</inertia-link>
                <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-lg bg-maiic-600 px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-maiic-700 disabled:opacity-50">
                    <font-awesome-icon icon="check"/> {{ form.processing ? 'Saving...' : 'Add role' }}
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
    props: { permissions: Object },
    data() {
        return {
            form: this.$inertia.form({
                name: null,
                display_name: null,
                permissions: [],
                send_email_to_role_members: false,
                group_email: null,
                can_reassign_role_members: false,
            }),
        }
    },
    methods: {
        submit() { this.form.post(this.route('users.roles.store')) },
    },
}
</script>
