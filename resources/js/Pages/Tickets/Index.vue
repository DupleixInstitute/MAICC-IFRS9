<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Support Tickets</h2>
        </template>

        <!-- status summary -->
        <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            <button v-for="card in summaryCards" :key="card.key" @click="setStatus(card.status)"
                    :class="['rounded-lg border px-4 py-3 text-left transition', form.status === card.status ? 'border-maiic-500 ring-1 ring-maiic-500 bg-maiic-50' : 'border-gray-200 bg-white hover:border-gray-300']">
                <div class="text-2xl font-bold text-gray-800">{{ card.value }}</div>
                <div class="text-xs font-medium text-gray-500">{{ card.label }}</div>
            </button>
        </div>

        <div class="mx-auto mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-1 flex-wrap items-center gap-3">
                <filter-search v-model="form.search" class="w-full max-w-md" @reset="reset">
                    <div class="w-64 space-y-3 px-4 py-4">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Status</label>
                            <select v-model="form.status" class="w-full rounded border-gray-300 text-sm focus:border-maiic-500 focus:ring-maiic-500">
                                <option :value="null">All statuses</option>
                                <option v-for="(label, key) in statuses" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Category</label>
                            <select v-model="form.category" class="w-full rounded border-gray-300 text-sm focus:border-maiic-500 focus:ring-maiic-500">
                                <option :value="null">All categories</option>
                                <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>
                    </div>
                </filter-search>
            </div>
            <inertia-link v-if="can('tickets.create')" class="inline-flex items-center gap-1 rounded-md bg-maiic-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-maiic-800" :href="route('tickets.create')">
                <span>New</span><span class="hidden md:inline">Ticket</span>
            </inertia-link>
        </div>

        <div class="mx-auto">
            <div class="overflow-x-auto rounded-lg bg-white shadow">
                <table class="w-full whitespace-no-wrap">
                    <thead class="bg-gray-50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="px-6 py-3">Ref</th>
                        <th class="px-6 py-3">Title</th>
                        <th class="px-6 py-3">Category</th>
                        <th class="px-6 py-3">Priority</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Assignee</th>
                        <th class="px-6 py-3">Updated</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    <tr v-for="ticket in tickets.data" :key="ticket.id" class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <inertia-link :href="route('tickets.show', ticket.id)" class="font-mono text-sm font-semibold text-maiic-700 hover:underline">
                                {{ ticket.reference_display }}
                            </inertia-link>
                        </td>
                        <td class="max-w-xs px-6 py-4">
                            <inertia-link :href="route('tickets.show', ticket.id)" class="block truncate font-medium text-gray-800 hover:text-maiic-700">
                                {{ ticket.title }}
                            </inertia-link>
                        </td>
                        <td class="px-6 py-4">
                            <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', categoryClass(ticket.category)]">{{ ticket.category_label }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', priorityClass(ticket.priority)]">{{ ticket.priority_label }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span :class="['inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium', statusClass(ticket.status)]">
                                <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>{{ ticket.status_label }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ ticket.assignee ? ticket.assignee.name : '-' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ formatDate(ticket.updated_at) }}</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-1.5">
                                <inertia-link :href="route('tickets.show', ticket.id)" title="View ticket"
                                              class="flex h-8 w-8 items-center justify-center rounded-lg bg-maiic-50 text-maiic-600 transition hover:bg-maiic-100 hover:text-maiic-800">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                </inertia-link>
                                <inertia-link v-if="can('tickets.update')" :href="route('tickets.show', ticket.id) + '?edit=1'" title="Edit ticket"
                                              class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-600 transition hover:bg-amber-100 hover:text-amber-800">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                                </inertia-link>
                                <button v-if="can('tickets.destroy')" @click="deleteAction(ticket.id)" title="Delete ticket"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg bg-red-50 text-red-600 transition hover:bg-red-100 hover:text-red-800">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M10 11v6M14 11v6"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="tickets.data.length === 0">
                        <td class="px-6 py-10 text-center text-gray-500" colspan="8">No tickets found.</td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <pagination :links="tickets.links"/>
        </div>

        <jet-confirmation-modal :show="confirmingDeletion" @close="confirmingDeletion = false">
            <template #title>Delete Ticket</template>
            <template #content>Are you sure you want to permanently delete this ticket and its activity trail?</template>
            <template #footer>
                <jet-secondary-button @click.native="confirmingDeletion = false">Nevermind</jet-secondary-button>
                <jet-danger-button class="ml-2" @click.native="destroy">Delete Ticket</jet-danger-button>
            </template>
        </jet-confirmation-modal>

        <teleport to="head">
            <title>{{ pageTitle }}</title>
            <meta property="og:description" :content="pageDescription">
        </teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Jetstream/Pagination.vue'
import FilterSearch from '@/Jetstream/FilterSearch.vue'
import JetConfirmationModal from '@/Jetstream/ConfirmationModal.vue'
import JetDangerButton from '@/Jetstream/DangerButton.vue'
import JetSecondaryButton from '@/Jetstream/SecondaryButton.vue'
import mapValues from 'lodash/mapValues'
import pickBy from 'lodash/pickBy'

export default {
    components: { AppLayout, Pagination, FilterSearch, JetConfirmationModal, JetDangerButton, JetSecondaryButton },
    props: {
        tickets: Object,
        filters: Object,
        statuses: Object,
        categories: Object,
        priorities: Object,
        counts: Object,
    },
    data() {
        return {
            form: {
                search: this.filters.search,
                status: this.filters.status,
                category: this.filters.category,
            },
            confirmingDeletion: false,
            selectedRecord: null,
            pageTitle: 'Support Tickets',
            pageDescription: 'Track enhancement, issue and change requests.',
        }
    },
    computed: {
        summaryCards() {
            return [
                { key: 'all', label: 'All', value: this.counts.all, status: null },
                { key: 'open', label: 'Open', value: this.counts.open, status: 'open' },
                { key: 'in_progress', label: 'In Progress', value: this.counts.in_progress, status: 'in_progress' },
                { key: 'resolved', label: 'Resolved', value: this.counts.resolved, status: 'resolved' },
                { key: 'closed', label: 'Closed', value: this.counts.closed, status: 'closed' },
            ]
        },
    },
    watch: {
        form: {
            handler: _.debounce(function () {
                let query = pickBy(this.form, (v) => v !== null && v !== '' && v !== undefined)
                this.$inertia.get(this.route('tickets.index'), query, { preserveState: true, replace: true })
            }, 400),
            deep: true,
        },
    },
    methods: {
        setStatus(status) {
            this.form.status = status
        },
        reset() {
            this.form = mapValues(this.form, () => null)
        },
        formatDate(value) {
            if (!value) return '-'
            return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
        },
        statusClass(status) {
            return {
                open: 'bg-amber-100 text-amber-800',
                in_progress: 'bg-maiic-100 text-maiic-800',
                on_hold: 'bg-gray-200 text-gray-600',
                resolved: 'bg-maiic-600 text-white',
                closed: 'bg-gray-100 text-gray-500',
            }[status] || 'bg-gray-100 text-gray-600'
        },
        priorityClass(priority) {
            return {
                low: 'bg-gray-100 text-gray-600',
                medium: 'bg-maiic-100 text-maiic-800',
                high: 'bg-amber-100 text-amber-800',
                critical: 'bg-red-100 text-red-700',
            }[priority] || 'bg-gray-100 text-gray-600'
        },
        categoryClass(category) {
            return {
                enhancement: 'bg-maiic-100 text-maiic-800',
                issue: 'bg-red-100 text-red-700',
                change_request: 'bg-amber-100 text-amber-800',
                other: 'bg-gray-100 text-gray-600',
            }[category] || 'bg-gray-100 text-gray-600'
        },
    },
}
</script>
