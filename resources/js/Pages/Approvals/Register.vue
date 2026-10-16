<template>
    <app-layout title="Approvals" description="Every decision awaiting a person and every decision made, across the Governance Centre and the approval workflows: who proposed it, who decided it, when, why, and the documents behind it.">
        <div class="w-full space-y-4">
            <KpiRow :cards="[
                { label: 'Items (filtered)', value: total },
                { label: 'Awaiting a decision', value: pending, accent: pending ? '#d97706' : '#0f766e' },
                { label: 'Decided items', value: reconciliation.decided },
                { label: 'Reconciled to the audit log', value: reconciliation.reconciled ? 'Yes' : 'No', accent: reconciliation.reconciled ? '#0f766e' : '#be123c' },
            ]"/>

            <div class="maiic-panel p-4">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
                    <label class="text-xs text-gray-600 lg:col-span-2">Workflow
                        <select v-model="form.workflow" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            <option value="">All</option>
                            <option v-for="(label, key) in workflows" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </label>
                    <label class="text-xs text-gray-600">Person
                        <select v-model="form.person" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            <option value="">Anyone</option>
                            <option v-for="p in people" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </label>
                    <label class="text-xs text-gray-600">Status
                        <select v-model="form.status" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            <option value="">Any</option>
                            <option value="pending">Awaiting a decision</option>
                            <option value="decided">Decided</option>
                            <option value="grandfathered">Approved before supporting documents were required</option>
                            <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </label>
                    <label class="text-xs text-gray-600">From
                        <input v-model="form.from" type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                    </label>
                    <label class="text-xs text-gray-600">To
                        <input v-model="form.to" type="date" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                    </label>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" class="primary-btn" @click="apply(1)">Apply</button>
                    <button type="button" class="secondary-btn" @click="clear">Clear</button>
                    <a :href="route('approvals.register.export', { format: 'xlsx', ...query })" class="secondary-btn">Excel</a>
                    <a :href="route('approvals.register.export', { format: 'pdf', ...query })" class="secondary-btn">PDF</a>
                </div>
            </div>

            <div v-if="!reconciliation.reconciled" class="maiic-panel border-l-4 border-rose-500 p-4 text-sm text-gray-700">
                <div class="font-semibold text-gray-900">Not reconciled to the audit log</div>
                <div v-if="reconciliation.decided_without_entry.length" class="mt-2">
                    {{ reconciliation.decided_without_entry.length }} decided item(s) have no audit entry:
                    <span v-for="(w, i) in reconciliation.decided_without_entry.slice(0, 10)" :key="'w' + i" class="mr-2 font-mono text-xs">{{ w.workflow }} {{ w.id }}</span>
                    <span v-if="reconciliation.decided_without_entry.length > 10">and {{ reconciliation.decided_without_entry.length - 10 }} more (in the export)</span>
                </div>
                <div v-if="reconciliation.entries_without_item.length" class="mt-2">
                    {{ reconciliation.entries_without_item.length }} audit decision entr{{ reconciliation.entries_without_item.length === 1 ? 'y names' : 'ies name' }} an item not on record:
                    <span v-for="(o, i) in reconciliation.entries_without_item.slice(0, 10)" :key="'o' + i" class="mr-2 font-mono text-xs">entry {{ o.entry }} ({{ o.workflow }} {{ o.id }})</span>
                </div>
            </div>

            <div class="maiic-panel">
                <div class="maiic-table-wrap">
                    <table class="maiic-table">
                        <thead><tr><th>Workflow</th><th>Item</th><th>Status</th><th>Proposed</th><th>Decided</th><th>Reason</th><th>Documents</th><th></th></tr></thead>
                        <tbody>
                            <tr v-for="r in rows" :key="r.workflow + r.id">
                                <td class="text-xs">{{ r.workflow_label }}</td>
                                <td><a :href="r.url" class="text-sky-700 underline">{{ r.title }}</a> <span class="font-mono text-[11px] text-gray-500">#{{ r.id }}</span></td>
                                <td>
                                    <span class="maiic-badge" :class="r.pending ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700'">{{ r.pending ? 'Awaiting: ' + r.status : r.status }}</span>
                                    <div v-if="r.grandfathered" class="mt-1 text-[11px] text-amber-800">Approved before supporting documents were required</div>
                                    <div v-else-if="r.bootstrap" class="mt-1 text-[11px] text-gray-500">System Bootstrap (not a MAIIC approval)</div>
                                </td>
                                <td class="whitespace-nowrap text-xs">{{ r.proposed_by || '-' }}<br><span class="text-gray-500">{{ r.proposed_at }}</span></td>
                                <td class="whitespace-nowrap text-xs">{{ r.decided_by || '-' }}<br><span class="text-gray-500">{{ r.decided_at }}</span></td>
                                <td class="max-w-xs text-xs">{{ r.reason }}</td>
                                <td class="text-xs">{{ r.documents }}<span v-if="r.document_names" class="block text-gray-500">{{ r.document_names }}</span></td>
                                <td><button v-if="canLink && r.pending" type="button" class="text-xs text-sky-700 underline" @click="openLink(r)">link a document</button></td>
                            </tr>
                            <tr v-if="!rows.length"><td colspan="8" class="text-center text-gray-500">No approval matches the filters.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex items-center justify-between px-4 py-3 text-xs text-gray-600">
                    <span>{{ total ? ((page - 1) * perPage + 1) + ' to ' + Math.min(page * perPage, total) + ' of ' + total : '' }}</span>
                    <span class="space-x-2">
                        <button type="button" class="secondary-btn" :disabled="page <= 1" @click="apply(page - 1)">Previous</button>
                        <button type="button" class="secondary-btn" :disabled="page * perPage >= total" @click="apply(page + 1)">Next</button>
                    </span>
                </div>
            </div>
        </div>

        <div v-if="link.show" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-lg bg-white p-5 shadow-xl">
                <div class="text-sm font-semibold text-gray-900">Link a document to {{ link.row.workflow_label }} {{ link.row.title }}</div>
                <p class="mt-1 text-xs text-gray-600">A document already uploaded for another decision (its number is shown on that decision's page). The file is not copied; the link counts as this decision's evidence.</p>
                <label class="mt-3 block text-xs text-gray-600">Document number
                    <input v-model="link.document_id" type="number" min="1" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                </label>
                <label class="mt-3 block text-xs text-gray-600">Which part of it supports this decision
                    <textarea v-model="link.reason" rows="3" class="mt-1 w-full rounded-md border-gray-300 text-sm"></textarea>
                </label>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="secondary-btn" @click="link.show = false">Cancel</button>
                    <button type="button" class="primary-btn" :disabled="processing || !link.document_id || link.reason.trim().length < 10" @click="submitLink">Link</button>
                </div>
            </div>
        </div>
        <teleport to="head"><title>Approvals</title></teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import KpiRow from '@/Components/Maiic/KpiRow.vue'

export default {
    components: { AppLayout, KpiRow },
    props: {
        rows: { type: Array, default: () => [] },
        total: { type: Number, default: 0 },
        page: { type: Number, default: 1 },
        perPage: { type: Number, default: 100 },
        pending: { type: Number, default: 0 },
        filters: { type: Object, default: () => ({}) },
        workflows: { type: Object, default: () => ({}) },
        statuses: { type: Array, default: () => [] },
        people: { type: Array, default: () => [] },
        reconciliation: { type: Object, default: () => ({ reconciled: true, decided: 0, decided_without_entry: [], entries_without_item: [] }) },
        canLink: { type: Boolean, default: false },
    },
    data() {
        return {
            form: { workflow: this.filters.workflow || '', person: this.filters.person || '', status: this.filters.status || '', from: this.filters.from || '', to: this.filters.to || '' },
            link: { show: false, row: null, document_id: '', reason: '' },
            processing: false,
        }
    },
    computed: {
        query() {
            return Object.fromEntries(Object.entries(this.form).filter(([, v]) => v !== '' && v !== null))
        },
    },
    methods: {
        apply(page) {
            this.$inertia.get(this.route('approvals.register'), { ...this.query, page }, { preserveScroll: true })
        },
        clear() {
            this.form = { workflow: '', person: '', status: '', from: '', to: '' }
            this.apply(1)
        },
        openLink(row) {
            this.link = { show: true, row, document_id: '', reason: '' }
        },
        submitLink() {
            this.processing = true
            this.$inertia.post(this.route('approvals.register.link'), { target: this.link.row.workflow, id: this.link.row.id, document_id: this.link.document_id, reason: this.link.reason },
                { preserveScroll: true, onFinish: () => { this.processing = false }, onSuccess: () => { this.link.show = false } })
        },
    },
}
</script>
