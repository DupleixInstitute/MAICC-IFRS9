<template>
    <app-layout title="New ticket" description="Log an enhancement request, an issue or a change request">
        <template #actions>
            <inertia-link :href="route('tickets.index')" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50">Back to tickets</inertia-link>
        </template>

        <form class="maiic-panel p-6" @submit.prevent="submit">
            <div class="mb-2 flex items-center justify-between">
                <div class="maiic-section-title my-0 flex-1">The request</div>
                <span class="ml-4 text-sm text-gray-500">Will be logged as <span class="font-mono font-bold text-maiic-700">#{{ nextReference }}</span></span>
            </div>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                <div class="md:col-span-3">
                    <label for="t-title" class="maiic-flabel">Title</label>
                    <input id="t-title" v-model="form.title" type="text" required class="maiic-input" placeholder="Short summary of the request"/>
                    <p v-if="form.errors.title" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.title }}</p>
                </div>
                <div class="md:col-span-3">
                    <label for="t-desc" class="maiic-flabel">Description</label>
                    <textarea id="t-desc" v-model="form.description" rows="5" class="maiic-input" placeholder="Details, context and what done looks like"></textarea>
                    <p v-if="form.errors.description" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.description }}</p>
                </div>
                <div>
                    <label for="t-cat" class="maiic-flabel">Category</label>
                    <select id="t-cat" v-model="form.category" class="maiic-select"><option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option></select>
                    <p v-if="form.errors.category" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.category }}</p>
                </div>
                <div>
                    <label for="t-pri" class="maiic-flabel">Priority</label>
                    <select id="t-pri" v-model="form.priority" class="maiic-select"><option v-for="(label, key) in priorities" :key="key" :value="key">{{ label }}</option></select>
                    <p v-if="form.errors.priority" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.priority }}</p>
                </div>
                <div>
                    <label for="t-status" class="maiic-flabel">Status</label>
                    <select id="t-status" v-model="form.status" class="maiic-select"><option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option></select>
                    <p v-if="form.errors.status" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.status }}</p>
                </div>
            </div>

            <div class="maiic-section-title">Who and when</div>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-4">
                <div>
                    <label for="t-req" class="maiic-flabel">Requested by</label>
                    <input id="t-req" v-model="form.requested_by" type="text" class="maiic-input" placeholder="e.g. Finance team"/>
                    <p v-if="form.errors.requested_by" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.requested_by }}</p>
                </div>
                <div>
                    <label for="t-src" class="maiic-flabel">Source</label>
                    <input id="t-src" v-model="form.source" type="text" class="maiic-input" placeholder="e.g. email, meeting, phone"/>
                    <p v-if="form.errors.source" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.source }}</p>
                </div>
                <div>
                    <label for="t-assign" class="maiic-flabel">Assign to</label>
                    <select id="t-assign" v-model="form.assigned_to" class="maiic-select">
                        <option :value="null">Unassigned</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                    </select>
                    <p v-if="form.errors.assigned_to" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.assigned_to }}</p>
                </div>
                <div>
                    <label for="t-due" class="maiic-flabel">Due date</label>
                    <input id="t-due" v-model="form.due_date" type="date" class="maiic-input"/>
                    <p v-if="form.errors.due_date" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.due_date }}</p>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2 border-t border-gray-100 pt-5">
                <inertia-link :href="route('tickets.index')" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm hover:bg-gray-50">Cancel</inertia-link>
                <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-lg bg-maiic-600 px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-maiic-700 disabled:opacity-50">
                    <font-awesome-icon icon="check"/> {{ form.processing ? 'Saving...' : 'Create ticket' }}
                </button>
            </div>
        </form>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'

export default {
    components: { AppLayout },
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
        submit() { this.form.post(this.route('tickets.store')) },
    },
}
</script>
