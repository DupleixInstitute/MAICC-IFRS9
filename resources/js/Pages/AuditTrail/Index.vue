<template>
    <app-layout title="Audit Trail" description="Every recorded change, module action and export in the system, newest first, with who made it">
        <template #actions>
            <a :href="exportUrl" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50">
                <font-awesome-icon icon="download"/> Export CSV
            </a>
        </template>
        <div class="maiic-panel">
            <!-- One slim filter row, with the two log sizes on the right -->
            <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 px-4 py-3">
                <input v-model="form.search" type="search" aria-label="Search" placeholder="Search action or record"
                       class="maiic-input w-48 py-1.5"/>
                <select v-model="form.source" aria-label="Source" title="Source" class="maiic-select w-36 py-1.5">
                    <option :value="null">All sources</option>
                    <option value="activity">Record changes</option>
                    <option value="module">Module actions and exports</option>
                </select>
                <select v-model="form.user_id" aria-label="User" title="User" class="maiic-select w-36 py-1.5">
                    <option :value="null">All users</option>
                    <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
                <label class="flex items-center gap-1 text-xs font-bold uppercase tracking-wider text-gray-500">From
                    <input v-model="form.from" type="date" class="maiic-input w-36 py-1.5"/></label>
                <label class="flex items-center gap-1 text-xs font-bold uppercase tracking-wider text-gray-500">To
                    <input v-model="form.to" type="date" class="maiic-input w-36 py-1.5"/></label>
                <button v-if="hasFilters" type="button" class="text-xs font-bold text-gray-500 hover:text-maiic-700" @click="clear">Clear</button>
                <div class="ml-auto flex items-center gap-3 text-xs text-gray-500">
                    <span><b class="text-sm text-gray-900">{{ counts.activity.toLocaleString('en-GB') }}</b> record changes</span>
                    <span class="text-gray-300">|</span>
                    <span><b class="text-sm text-gray-900">{{ counts.module.toLocaleString('en-GB') }}</b> module actions</span>
                </div>
            </div>

            <div class="maiic-table-wrap">
                <table class="maiic-table">
                    <thead>
                    <tr>
                        <th>When</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Record</th>
                        <th>Source</th>
                        <th class="num">Details</th>
                    </tr>
                    </thead>
                    <tbody>
                    <template v-for="(e, i) in entries" :key="i">
                        <tr>
                            <td class="whitespace-nowrap text-xs text-gray-600">{{ formatDateTime(e.created_at) }}</td>
                            <td class="whitespace-nowrap font-semibold">{{ e.user }}</td>
                            <td class="max-w-xs">{{ e.action }}</td>
                            <td class="whitespace-nowrap text-gray-600">
                                {{ humanise(e.entity) }}<span v-if="e.entity_id" class="text-gray-400"> #{{ e.entity_id }}</span>
                            </td>
                            <td>
                                <span class="maiic-badge" :class="e.kind === 'Export' ? 'maiic-badge-grey' : e.source === 'module' ? 'maiic-badge-gold' : 'maiic-badge-green'">
                                    {{ e.kind }}
                                </span>
                            </td>
                            <td class="num">
                                <button v-if="e.details && Object.keys(cleanDetails(e.details)).length" type="button"
                                        class="maiic-action maiic-action-view" :title="expanded === i ? 'Hide the details' : 'Show the details'"
                                        @click="expanded = expanded === i ? null : i">
                                    <font-awesome-icon :icon="expanded === i ? 'minus' : 'eye'"/>
                                </button>
                                <span v-else class="text-xs text-gray-400">-</span>
                            </td>
                        </tr>
                        <tr v-if="expanded === i">
                            <td colspan="6" class="bg-maiic-50/40">
                                <dl class="grid grid-cols-1 gap-x-6 gap-y-1 text-xs sm:grid-cols-2 lg:grid-cols-3">
                                    <div v-for="(v, k) in cleanDetails(e.details)" :key="k" class="flex gap-3 border-b border-gray-100 py-1">
                                        <dt class="w-40 flex-none text-gray-500">{{ humanise(k) }}</dt>
                                        <dd class="min-w-0 break-words font-semibold text-gray-900">{{ typeof v === 'object' ? JSON.stringify(v) : v }}</dd>
                                    </div>
                                </dl>
                            </td>
                        </tr>
                    </template>
                    <tr v-if="entries.length === 0">
                        <td class="maiic-empty" colspan="6">No audit entries match these filters. Widen the dates or clear the filters.</td>
                    </tr>
                    </tbody>
                </table>
            </div>

            <!-- The standard pager; this list merges two logs, so it pages on the server -->
            <div v-if="pagination.last_page > 1" class="border-t border-gray-100 px-4 py-3">
                <ClientPager :model-value="pagination.current_page" :total="pagination.total" :per-page="pagination.per_page" @update:model-value="go"/>
            </div>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import pickBy from 'lodash/pickBy'
import ClientPager from '@/Components/ClientPager.vue'

export default {
    components: { AppLayout, ClientPager },
    props: {
        entries: Array,
        filters: Object,
        pagination: Object,
        users: Array,
        counts: Object,
    },
    data() {
        return {
            form: {
                search: this.filters.search,
                source: this.filters.source,
                user_id: this.filters.user_id,
                from: this.filters.from,
                to: this.filters.to,
            },
            expanded: null,
        }
    },
    watch: {
        form: {
            handler: _.debounce(function () {
                this.go(1)
            }, 450),
            deep: true,
        },
    },
    computed: {
        // the filters on screen, as a CSV of the whole filtered trail
        exportUrl() {
            const query = pickBy({ ...this.form, export: 'csv' }, (v) => v !== null && v !== '' && v !== undefined)
            return this.route('audit-trail.index') + '?' + new URLSearchParams(query).toString()
        },
        hasFilters() {
            return Object.values(this.form).some(v => v !== null && v !== '' && v !== undefined)
        },
    },
    methods: {
        go(page) {
            this.expanded = null
            const query = pickBy({ ...this.form, page: page > 1 ? page : null },
                (v) => v !== null && v !== '' && v !== undefined)
            this.$inertia.get(this.route('audit-trail.index'), query, { preserveState: true, replace: true })
        },
        clear() {
            this.form = { search: null, source: null, user_id: null, from: null, to: null }
        },
        humanise(v) {
            if (!v) return ''
            const t = String(v).replace(/_/g, ' ')
            return t.charAt(0).toUpperCase() + t.slice(1)
        },
        formatDateTime(value) {
            if (!value) return ''
            return new Date(value).toLocaleString('en-GB', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
        },
        cleanDetails(details) {
            if (!details || typeof details !== 'object') return {}
            // Drop empty members so only substance is shown.
            return Object.fromEntries(Object.entries(details).filter(([, v]) => v !== null && v !== undefined))
        },
    },
}
</script>
