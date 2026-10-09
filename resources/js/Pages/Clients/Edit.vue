<template>
    <app-layout title="Edit Client" description="Change the customer ID, name, phone, type or status of this client">
        <template #actions>
            <Link :href="route('clients.index')" class="secondary-btn">Back to clients</Link>
        </template>
        <div class="maiic-panel max-w-3xl">
            <form @submit.prevent="submit">
                <div class="grid grid-cols-1 gap-5 p-6 md:grid-cols-2">
                    <div>
                        <label class="maiic-flabel" for="customer_id">Customer ID</label>
                        <input id="customer_id" v-model="form.customer_id" type="text" class="maiic-input" required/>
                        <p v-if="form.errors.customer_id" class="mt-1 text-xs text-red-600">{{ form.errors.customer_id }}</p>
                        <p v-else class="mt-1 text-xs text-gray-500">The ID the loan book uses for this borrower.</p>
                    </div>
                    <div>
                        <label class="maiic-flabel" for="name">Name</label>
                        <input id="name" v-model="form.name" type="text" class="maiic-input" required autocomplete="name"/>
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="maiic-flabel" for="mobile">Phone</label>
                        <input id="mobile" v-model="form.mobile" type="text" class="maiic-input"/>
                        <p v-if="form.errors.mobile" class="mt-1 text-xs text-red-600">{{ form.errors.mobile }}</p>
                    </div>
                    <div>
                        <label class="maiic-flabel" for="type">Type</label>
                        <select id="type" v-model="form.type" class="maiic-select" required>
                            <option value="individual">Individual</option>
                            <option value="corporate">Corporate</option>
                        </select>
                        <p v-if="form.errors.type" class="mt-1 text-xs text-red-600">{{ form.errors.type }}</p>
                    </div>
                    <div>
                        <label class="maiic-flabel" for="status">Status</label>
                        <select id="status" v-model="form.status" class="maiic-select" required>
                            <option value="active">Active</option>
                            <option value="pending">Pending</option>
                            <option value="inactive">Inactive</option>
                            <option value="archived">Archived</option>
                        </select>
                        <p v-if="form.errors.status" class="mt-1 text-xs text-red-600">{{ form.errors.status }}</p>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4">
                    <Link :href="route('clients.index')" class="secondary-btn">Cancel</Link>
                    <button type="submit" class="primary-btn" :disabled="form.processing">{{ form.processing ? 'Saving...' : 'Save client' }}</button>
                </div>
            </form>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'

export default {
    components: { AppLayout, Link },
    props: {
        client: Object,
    },
    data() {
        return {
            form: this.$inertia.form({
                _method: 'PUT',
                customer_id: this.client.customer_id,
                name: this.client.name,
                mobile: this.client.mobile,
                type: this.client.type || 'individual',
                status: this.client.status || 'active',
            }),
        }
    },
    methods: {
        submit() {
            this.form.post(this.route('clients.update', this.client.id))
        },
    },
}
</script>
