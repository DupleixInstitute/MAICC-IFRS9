<template>
    <app-layout :title="client.name" :description="'Client ' + (client.customer_id || '') + (client.type ? ', ' + client.type : '')">
        <template #actions>
            <Link :href="route('clients.files.index', client.id)" class="secondary-btn">Files</Link>
            <Link v-if="can('clients.update')" :href="route('clients.edit', client.id)" class="secondary-btn">Edit</Link>
            <button v-if="can('clients.destroy')" type="button" class="danger-btn" @click="destroy">Delete</button>
            <Link :href="route('clients.index')" class="secondary-btn">Back to clients</Link>
        </template>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
            <div class="maiic-panel">
                <div class="border-b border-gray-200 px-5 py-3 text-sm font-bold text-gray-900">Client</div>
                <dl class="divide-y divide-gray-100 text-sm">
                    <div v-for="row in identity" :key="row.label" class="grid grid-cols-5 gap-3 px-5 py-2.5">
                        <dt class="col-span-2 text-gray-500">{{ row.label }}</dt>
                        <dd class="col-span-3 font-medium text-gray-900">
                            <span v-if="row.badge" class="maiic-badge capitalize" :class="row.badge">{{ row.value }}</span>
                            <span v-else>{{ row.value || '-' }}</span>
                        </dd>
                    </div>
                </dl>
            </div>
            <div class="maiic-panel">
                <div class="border-b border-gray-200 px-5 py-3 text-sm font-bold text-gray-900">Contact</div>
                <dl class="divide-y divide-gray-100 text-sm">
                    <div v-for="row in contact" :key="row.label" class="grid grid-cols-5 gap-3 px-5 py-2.5">
                        <dt class="col-span-2 text-gray-500">{{ row.label }}</dt>
                        <dd class="col-span-3 font-medium text-gray-900">{{ row.value || '-' }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link } from '@inertiajs/vue3'
import { confirmDialog } from '@/Components/confirmDialog'

export default {
    components: { AppLayout, Link },
    props: {
        client: Object,
    },
    computed: {
        identity() {
            const c = this.client
            const statusBadge = { active: 'maiic-badge-green', pending: 'maiic-badge-gold', inactive: 'maiic-badge-grey', archived: 'maiic-badge-grey', deceased: 'maiic-badge-red' }[c.status] || 'maiic-badge-grey'
            const rows = [
                { label: 'Customer ID', value: c.customer_id },
                { label: 'Name', value: c.name },
                { label: 'Type', value: c.type },
                { label: 'Status', value: c.status, badge: statusBadge },
                { label: 'Sector', value: c.industry_type?.name || c.industry_code },
                { label: 'Branch', value: c.branch?.name },
            ]
            if (c.type === 'corporate') {
                rows.push(
                    { label: 'Trading name', value: c.trading_name },
                    { label: 'Legal type', value: c.legal_type?.name },
                    { label: 'Registration number', value: c.registration_number },
                    { label: 'Year of registration', value: c.registration_year },
                    { label: 'Country of registration', value: c.registration_country?.name },
                )
            } else {
                rows.push(
                    { label: 'ID number', value: c.id_number },
                    { label: 'Date of birth', value: c.dob },
                )
            }
            return rows
        },
        contact() {
            const c = this.client
            return [
                { label: 'Mobile', value: c.mobile },
                { label: 'Telephone', value: c.tel },
                { label: 'Email', value: c.email },
                { label: 'Address', value: c.address },
                { label: 'Postal address', value: c.postal_address },
                { label: 'Country', value: c.country?.name },
            ]
        },
    },
    methods: {
        async destroy() {
            if (!(await confirmDialog({
                title: 'Delete ' + this.client.name + '?',
                message: 'The client is removed from the client list.',
                confirmLabel: 'Delete',
                tone: 'danger',
            }))) return
            this.$inertia.delete(this.route('clients.destroy', this.client.id))
        },
    },
}
</script>
