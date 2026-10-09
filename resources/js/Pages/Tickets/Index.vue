<template>
    <app-layout title="Support Tickets" description="Log and follow enhancement requests, issues and change requests for the system">
        <template #actions>
            <inertia-link v-if="can('tickets.create')" :href="route('tickets.create')"
                          class="inline-flex items-center gap-2 rounded-lg bg-maiic-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-maiic-700">
                <font-awesome-icon icon="plus"/> New ticket
            </inertia-link>
        </template>

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <!-- Status tabs with the real count of tickets in each -->
            <div class="border-b border-gray-200 px-5 pt-4">
                <nav class="flex gap-6 overflow-x-auto">
                    <button v-for="t in statusTabs" :key="t.key" type="button" class="whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-semibold"
                            :class="form.status === t.status ? 'border-maiic-600 text-maiic-700' : 'border-transparent text-gray-500 hover:text-gray-800'"
                            @click="form.status = t.status">
                        {{ t.label }}
                        <span class="ml-1 rounded-full px-2 py-0.5 text-xs" :class="form.status === t.status ? 'bg-maiic-100 text-maiic-800' : 'bg-gray-100 text-gray-600'">{{ t.value }}</span>
                    </button>
                </nav>
            </div>

            <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 px-4 py-3">
                <input v-model="form.search" type="search" aria-label="Search" placeholder="Search reference or title" class="maiic-input w-64 py-1.5"/>
                <select v-model="form.category" aria-label="Category" title="Category" class="maiic-select w-48 py-1.5">
                    <option :value="null">All categories</option>
                    <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
                </select>
                <button v-if="form.search || form.category || form.status" type="button" class="text-xs font-bold text-gray-500 hover:text-maiic-700" @click="reset">Clear</button>
            </div>

            <div class="maiic-table-wrap">
                <table class="maiic-table">
                    <thead>
                    <tr>
                        <th>Ref</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Assigned to</th>
                        <th>Updated</th>
                        <th class="num">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="ticket in tickets.data" :key="ticket.id">
                        <td class="whitespace-nowrap">
                            <inertia-link :href="route('tickets.show', ticket.id)" class="font-mono text-sm font-semibold text-maiic-700 hover:underline">{{ ticket.reference_display }}</inertia-link>
                        </td>
                        <td class="max-w-xs">
                            <inertia-link :href="route('tickets.show', ticket.id)" class="block truncate font-semibold text-gray-900 hover:text-maiic-700" :title="ticket.title">{{ ticket.title }}</inertia-link>
                        </td>
                        <td><span class="maiic-badge" :class="categoryClass(ticket.category)">{{ ticket.category_label }}</span></td>
                        <td><span class="maiic-badge" :class="priorityClass(ticket.priority)">{{ ticket.priority_label }}</span></td>
                        <td><span class="maiic-badge" :class="statusClass(ticket.status)">{{ ticket.status_label }}</span></td>
                        <td>{{ ticket.assignee ? ticket.assignee.name : '-' }}</td>
                        <td class="whitespace-nowrap text-xs text-gray-500">{{ formatDate(ticket.updated_at) }}</td>
                        <td class="w-px">
                            <row-actions :view-href="route('tickets.show', ticket.id)"
                                         :edit-href="can('tickets.update') ? route('tickets.show', ticket.id) + '?edit=1' : null"
                                         :deletable="can('tickets.destroy')"
                                         @delete="destroy(ticket)"/>
                        </td>
                    </tr>
                    <tr v-if="tickets.data.length === 0">
                        <td class="px-6 py-10 text-center" colspan="8">
                            <div class="text-base font-bold text-gray-800">No tickets here</div>
                            <p class="mt-1 text-sm text-gray-500">{{ form.search || form.category || form.status ? 'Nothing matches these filters. Clear them to see every ticket.' : 'Log a request or a problem with New ticket.' }}</p>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="tickets.links && tickets.links.length > 3" class="border-t border-gray-100 px-4 pb-4"><pagination :links="tickets.links"/></div>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import RowActions from '@/Shared/RowActions.vue'
import { confirmDialog } from '@/Components/confirmDialog'
import pickBy from 'lodash/pickBy'

export default {
    components: { AppLayout, Pagination, RowActions },
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
                status: this.filters.status || null,
                category: this.filters.category,
            },
        }
    },
    computed: {
        statusTabs() {
            return [
                { key: 'all', label: 'All', value: this.counts.all, status: null },
                { key: 'open', label: 'Open', value: this.counts.open, status: 'open' },
                { key: 'in_progress', label: 'In progress', value: this.counts.in_progress, status: 'in_progress' },
                { key: 'resolved', label: 'Resolved', value: this.counts.resolved, status: 'resolved' },
                { key: 'closed', label: 'Closed', value: this.counts.closed, status: 'closed' },
            ]
        },
    },
    watch: {
        form: {
            handler: _.debounce(function () {
                const query = pickBy(this.form, (v) => v !== null && v !== '' && v !== undefined)
                this.$inertia.get(this.route('tickets.index'), query, { preserveState: true, replace: true })
            }, 400),
            deep: true,
        },
    },
    methods: {
        reset() {
            this.form = { search: null, status: null, category: null }
        },
        async destroy(ticket) {
            if (!(await confirmDialog({
                title: 'Delete ticket ' + ticket.reference_display + '?',
                message: 'The ticket and its activity trail are removed for good.',
                confirmLabel: 'Delete ticket',
                tone: 'danger',
            }))) return
            this.$inertia.delete(this.route('tickets.destroy', ticket.id))
        },
        formatDate(value) {
            if (!value) return '-'
            return new Date(value).toLocaleDateString('en-GB', { year: 'numeric', month: 'short', day: 'numeric' })
        },
        statusClass(status) {
            return { open: 'maiic-badge-gold', in_progress: 'maiic-badge-green', on_hold: 'maiic-badge-grey', resolved: 'maiic-badge-solid-green', closed: 'maiic-badge-grey' }[status] || 'maiic-badge-grey'
        },
        priorityClass(priority) {
            return { low: 'maiic-badge-grey', medium: 'maiic-badge-green', high: 'maiic-badge-gold', critical: 'maiic-badge-red' }[priority] || 'maiic-badge-grey'
        },
        categoryClass(category) {
            return { enhancement: 'maiic-badge-green', issue: 'maiic-badge-red', change_request: 'maiic-badge-gold', other: 'maiic-badge-grey' }[category] || 'maiic-badge-grey'
        },
    },
}
</script>
