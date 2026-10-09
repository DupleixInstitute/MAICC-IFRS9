<template>
    <app-layout :title="'Edit ' + profile.name" description="Change this person's details, roles or password">
        <template #actions>
            <inertia-link :href="route('users.show', profile.id)" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50">View user</inertia-link>
            <inertia-link :href="route('users.index')" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50">Back to users</inertia-link>
        </template>

        <form class="maiic-panel p-6" enctype="multipart/form-data" @submit.prevent="submit">
            <user-form-fields :form="form" :roles="roles" :branches="branches" editing/>
            <div class="mt-6 flex justify-end gap-2 border-t border-gray-100 pt-5">
                <inertia-link :href="route('users.index')" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm hover:bg-gray-50">Cancel</inertia-link>
                <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-lg bg-maiic-600 px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-maiic-700 disabled:opacity-50">
                    <font-awesome-icon icon="check"/> {{ form.processing ? 'Saving...' : 'Save changes' }}
                </button>
            </div>
        </form>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import UserFormFields from './Partials/UserFormFields.vue'

export default {
    components: { AppLayout, UserFormFields },
    props: { roles: [Object, Array], profile: Object, branches: [Object, Array] },
    data() {
        return {
            form: this.$inertia.form({
                branch_id: this.profile.branch_id,
                name: this.profile.name,
                gender: this.profile.gender,
                email: this.profile.email,
                group_email: this.profile.group_email,
                password: null,
                password_confirmation: null,
                mobile: this.profile.mobile,
                tel: this.profile.tel,
                zip: this.profile.zip,
                external_id: this.profile.external_id,
                address: this.profile.address,
                // A new file only; the stored photo stays unless one is chosen.
                photo: null,
                active: !!Number(this.profile.active),
                roles: this.profile.selected_roles,
                can_reassign: Number(this.profile.can_reassign || 0),
            }),
        }
    },
    methods: {
        submit() {
            this.form.put(this.route('users.update', this.profile.id), {
                onFinish: () => this.form.reset('password', 'password_confirmation'),
            })
        },
    },
}
</script>
