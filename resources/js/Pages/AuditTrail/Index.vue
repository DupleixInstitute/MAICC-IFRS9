<template>
    <app-layout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Audit Trail</h2>
                <div class="flex gap-3 text-sm text-gray-500">
                    <span><b class="text-gray-800">{{ counts.activity.toLocaleString() }}</b> activity entries</span>
                    <span>·</span>
                    <span><b class="text-gray-800">{{ counts.module.toLocaleString() }}</b> module audit entries</span>
                </div>
            </div>
        </template>

        <!-- filters -->
        <div class="mb-4 grid grid-cols-1 gap-3 rounded-lg bg-white p-4 shadow sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Search</label>
                <input v-model="form.search" type="search" placeholder="Action or entity…"
                       class="w-full rounded border-gray-300 text-sm focus:border-maiic-500 focus:ring-maiic-500"/>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">Source</label>
                <select v-model="form.source" class="w-full rounded border-gray-300 text-sm focus:border-maiic-500 focus:ring-maiic-500">
                    <option :value="null">All sources</option>
                    <option value="activity">Model activity</option>
                    <option value="module">Module audit</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">User</label>
                <select v-model="form.user_id" class="w-full rounded border-gray-300 text-sm focus:border-maiic-500 focus:ring-maiic-500">
                    <option :value="null">All users</option>
                    <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">From</label>
                <input v-model="form.from" type="date" class="w-full rounded border-gray-300 text-sm focus:border-maiic-500 focus:ring-maiic-500"/>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-600">To</label>
                <input v-model="form.to" type="date" class="w-full rounded border-gray-300 text-sm focus:border-maiic-500 focus:ring-maiic-500"/>
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg bg-white shadow">
            <table class="w-full whitespace-no-wrap">
                <thead class="bg-gray-50">
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <th class="px-4 py-3">When</th>
                    <th class="px-4 py-3">User</th>
                    <th class="px-4 py-3">Action</th>
                    <th class="px-4 py-3">Entity</th>
                    <th class="px-4 py-3">Source</th>
                    <th class="px-4 py-3">Details</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                <tr v-for="(e, i) in entries" :key="i" class="align-top hover:bg-gray-50">
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ formatDateTime(e.created_at) }}</td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-800">{{ e.user }}</td>
                    <td class="max-w-xs px-4 py-3 text-sm text-gray-700">{{ e.action }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">
                        {{ e.entity }}<span v-if="e.entity_id" class="text-gray-400"> #{{ e.entity_id }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                       e.source === 'module' ? 'bg-indigo-100 text-indigo-700' : 'bg-maiic-100 text-maiic-700']">
                            {{ e.source === 'module' ? 'Module audit' : 'Activity' }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <button v-if="e.details && Object.keys(cleanDetails(e.details)).length"
                                @click="expanded = expanded === i ? null : i"
                                class="text-xs font-medium text-maiic-700 hover:text-maiic-800">
                            {{ expanded === i ? 'Hide' : 'View' }}
                        </button>
                        <pre v-if="expanded === i"
                             class="mt-2 max-h-56 max-w-md overflow-auto rounded bg-gray-50 p-2 text-xs text-gray-600">{{ JSON.stringify(cleanDetails(e.details), null, 2) }}</pre>
                    </td>
                </tr>
                <tr v-if="entries.length === 0">
                    <td class="px-4 py-10 text-center text-gray-500" colspan="6">No audit entries match the filters.</td>
                </tr>
                </tbody>
            </table>
        </div>

        <!-- pagination -->
        <div class="mt-4 flex items-center justify-between text-sm text-gray-600">
            <span>Page {{ pagination.current_page }} of {{ pagination.last_page }} · {{ pagination.total.toLocaleString() }} entries</span>
            <div class="flex gap-2">
                <button :disabled="pagination.current_page <= 1" @click="go(pagination.current_page - 1)"
                        class="rounded border border-gray-300 bg-white px-3 py-1.5 font-medium disabled:opacity-40 hover:bg-gray-50">Previous</button>
                <button :disabled="pagination.current_page >= pagination.last_page" @click="go(pagination.current_page + 1)"
                        class="rounded border border-gray-300 bg-white px-3 py-1.5 font-medium disabled:opacity-40 hover:bg-gray-50">Next</button>
            </div>
        </div>

        <teleport to="head"><title>Audit Trail</title></teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import pickBy from 'lodash/pickBy'

export default {
    components: { AppLayout },
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
    methods: {
        go(page) {
            this.expanded = null
            const query = pickBy({ ...this.form, page: page > 1 ? page : null },
                (v) => v !== null && v !== '' && v !== undefined)
            this.$inertia.get(this.route('audit-trail.index'), query, { preserveState: true, replace: true })
        },
        formatDateTime(value) {
            if (!value) return ''
            return new Date(value).toLocaleString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
        },
        cleanDetails(details) {
            if (!details || typeof details !== 'object') return {}
            // Drop null members so the JSON viewer only shows substance.
            return Object.fromEntries(Object.entries(details).filter(([, v]) => v !== null && v !== undefined))
        },
    },
}
</script>
