<template>
    <app-layout title="Add user" description="Create a sign-in for a new person and give them a role">
        <template #actions>
            <inertia-link :href="route('users.index')" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50">Back to users</inertia-link>
        </template>

        <form class="maiic-panel p-6" enctype="multipart/form-data" @submit.prevent="submit">
            <user-form-fields :form="form" :roles="roles" :branches="branches"/>
            <div class="mt-6 flex justify-end gap-2 border-t border-gray-100 pt-5">
                <inertia-link :href="route('users.index')" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm hover:bg-gray-50">Cancel</inertia-link>
                <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-lg bg-maiic-600 px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-maiic-700 disabled:opacity-50">
                    <font-awesome-icon icon="check"/> {{ form.processing ? 'Saving...' : 'Add user' }}
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
    props: { roles: [Object, Array], branches: [Object, Array] },
    data() {
        return {
            form: this.$inertia.form({
                branch_id: null,
                name: null,
                gender: null,
                email: null,
                group_email: null,
                password: null,
                password_confirmation: null,
                mobile: null,
                tel: null,
                zip: null,
                external_id: null,
                address: null,
                photo: null,
                active: true,
                send_login_details: true,
                can_reassign: 0,
                roles: [],
            }),
        }
    },
    methods: {
        submit() {
            this.form.post(this.route('users.store'), {
                onFinish: () => this.form.reset('password', 'password_confirmation'),
            })
        },
    },
}
</script>
