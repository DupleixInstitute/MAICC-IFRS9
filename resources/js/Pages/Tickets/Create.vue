<template>
    <app-layout>
        <template #header>
            <div class="flex items-center gap-2">
                <inertia-link :href="route('tickets.index')" class="text-gray-400 hover:text-gray-600">Support Tickets</inertia-link>
                <span class="text-gray-300">/</span>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">New Ticket</h2>
            </div>
        </template>

        <div class="mx-auto max-w-3xl">
            <form @submit.prevent="submit" class="space-y-6 rounded-lg bg-white p-6 shadow sm:p-8">
                <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                    <p class="text-sm text-gray-500">This ticket will be logged as</p>
                    <span class="font-mono text-lg font-bold text-maiic-700">#{{ nextReference }}</span>
                </div>

                <jet-validation-errors/>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-gray-700">Title <span class="text-red-500">*</span></label>
                    <input v-model="form.title" type="text" required class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-maiic-500 focus:ring-maiic-500" placeholder="Short summary of the request"/>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-semibold text-gray-700">Description</label>
                    <textarea v-model="form.description" rows="5" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-maiic-500 focus:ring-maiic-500" placeholder="Details, context, acceptance criteria…"></textarea>
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">Category</label>
                        <select v-model="form.category" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-maiic-500 focus:ring-maiic-500">
                            <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">Priority</label>
                        <select v-model="form.priority" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-maiic-500 focus:ring-maiic-500">
                            <option v-for="(label, key) in priorities" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">Status</label>
                        <select v-model="form.status" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-maiic-500 focus:ring-maiic-500">
                            <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">Requested by</label>
                        <input v-model="form.requested_by" type="text" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-maiic-500 focus:ring-maiic-500" placeholder="e.g. Barry — MAIIC"/>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">Source</label>
                        <input v-model="form.source" type="text" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-maiic-500 focus:ring-maiic-500" placeholder="e.g. email, meeting, phone"/>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">Assign to (responsible person)</label>
                        <select v-model="form.assigned_to" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-maiic-500 focus:ring-maiic-500">
                            <option :value="null">— Unassigned —</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">Due date</label>
                        <input v-model="form.due_date" type="date" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-maiic-500 focus:ring-maiic-500"/>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-5">
                    <inertia-link :href="route('tickets.index')" class="rounded-md px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800">Cancel</inertia-link>
                    <button type="submit" :disabled="form.processing" :class="{ 'opacity-50': form.processing }" class="rounded-md bg-maiic-700 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-maiic-800">
                        Create Ticket
                    </button>
                </div>
            </form>
        </div>

        <teleport to="head"><title>New Ticket</title></teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetValidationErrors from '@/Jetstream/ValidationErrors.vue'

export default {
    components: { AppLayout, JetValidationErrors },
    props: {
        nextReference: String,
        statuses: Object,
        categories: Object,
        priorities: Object,
        users: Array,
    },
    data() {
        return {
            form: this.$inertia.form({
                title: '',
                description: '',
                category: 'enhancement',
                priority: 'medium',
                status: 'open',
                requested_by: '',
                source: '',
                assigned_to: null,
                due_date: null,
            }),
        }
    },
    methods: {
        submit() {
            this.form.post(this.route('tickets.store'))
        },
    },
}
</script>
