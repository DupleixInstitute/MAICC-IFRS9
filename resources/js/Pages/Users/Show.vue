<template>
    <app-layout :title="profile.name" description="This person's sign-in, role and contact details">
        <template #actions>
            <inertia-link v-if="can('users.update')" :href="route('users.edit', profile.id)" class="inline-flex items-center gap-2 rounded-lg bg-maiic-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-maiic-700">
                <font-awesome-icon icon="pen"/> Edit user
            </inertia-link>
            <inertia-link :href="route('users.index')" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50">Back to users</inertia-link>
        </template>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-4">
            <div class="maiic-panel p-6 text-center">
                <img v-if="profile.profile_photo_url" :src="profile.profile_photo_url" alt="" class="mx-auto h-24 w-24 rounded-full object-cover ring-4 ring-maiic-50">
                <div class="mt-3 text-lg font-bold text-gray-900">{{ profile.name }}</div>
                <div class="text-sm text-gray-500">{{ profile.email }}</div>
                <div class="mt-3 flex flex-wrap justify-center gap-1">
                    <span v-if="profile.current_role" class="maiic-badge maiic-badge-green">{{ humanise(profile.current_role) }}</span>
                    <span class="maiic-badge" :class="Number(profile.active) ? 'maiic-badge-green' : 'maiic-badge-grey'">{{ Number(profile.active) ? 'Active' : 'Inactive' }}</span>
                </div>
            </div>

            <div class="maiic-panel lg:col-span-3">
                <div class="border-b border-gray-200 px-5 py-3"><h3 class="font-semibold text-gray-900">Details</h3></div>
                <dl class="grid grid-cols-1 gap-x-8 sm:grid-cols-2">
                    <div v-for="row in rows" :key="row.label" class="flex justify-between gap-4 border-b border-gray-100 px-5 py-3 text-sm">
                        <dt class="text-gray-500">{{ row.label }}</dt>
                        <dd class="text-right font-semibold text-gray-900">{{ row.value || '-' }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'

export default {
    components: { AppLayout },
    props: { profile: Object },
    computed: {
        rows() {
            const p = this.profile
            return [
                { label: 'Role', value: this.humanise(p.current_role) },
                { label: 'Branch', value: p.branch?.name },
                { label: 'Email', value: p.email },
                { label: 'Group email', value: p.group_email },
                { label: 'Mobile', value: p.mobile },
                { label: 'Telephone', value: p.tel },
                { label: 'Gender', value: this.humanise(p.gender) },
                { label: 'External ID', value: p.external_id },
                { label: 'Address', value: p.address },
                { label: 'Last sign-in', value: p.last_login },
                { label: 'Can hand work to other users', value: p.can_reassign ? 'Yes' : 'No' },
            ]
        },
    },
    methods: {
        humanise(v) {
            if (!v) return ''
            const t = String(v).replace(/_/g, ' ')
            return t.charAt(0).toUpperCase() + t.slice(1)
        },
    },
}
</script>
