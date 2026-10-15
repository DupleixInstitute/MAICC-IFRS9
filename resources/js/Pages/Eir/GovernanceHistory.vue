<template>
    <app-layout :title="'History: ' + label" description="Every value this setting has held and every proposal, with who proposed and decided, the reasons and the supporting documents, newest first.">
        <div class="w-full space-y-4">
            <div class="maiic-panel flex flex-wrap items-center justify-between gap-2 p-4 text-sm text-gray-700">
                <div>
                    <span class="font-mono text-xs text-gray-500">{{ settingKey }}</span>
                    <span v-if="evidenceRequired" class="maiic-badge maiic-badge-gold ml-2">A supporting document is required before a change is approved</span>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('eir-governance.index')" class="secondary-btn">Governance Centre</Link>
                    <a :href="route('eir-governance.history', { key: settingKey, download: 'csv' })" class="secondary-btn">Export</a>
                </div>
            </div>

            <div v-if="$page.props.errors?.governance" class="maiic-panel border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $page.props.errors.governance }}</div>

            <div v-for="r in rows" :key="r.id" class="maiic-panel p-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <div class="text-sm font-semibold text-gray-900">{{ r.value }} <span class="font-normal text-gray-500">from {{ r.effective_from }}</span></div>
                        <div class="text-xs text-gray-500">Change {{ r.id }} · proposed by {{ r.proposed_by }}</div>
                    </div>
                    <span class="maiic-badge" :class="badge(r.status)">{{ statusLabel(r.status) }}</span>
                </div>
                <p v-if="r.reason" class="mt-2 text-sm text-gray-700"><span class="font-medium">Reason:</span> {{ r.reason }}</p>
                <p v-if="r.approved_by" class="mt-1 text-xs text-gray-600">Approved by {{ r.approved_by }} at {{ r.approved_at }}</p>
                <p v-if="r.reviewed_by" class="mt-1 text-xs text-gray-600">{{ r.status === 'REJECTED' ? 'Rejected' : 'Withdrawn' }} by {{ r.reviewed_by }} at {{ r.reviewed_at }}: {{ r.review_reason }}</p>

                <div class="mt-3">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Supporting documents</div>
                    <div v-if="!r.documents.length" class="mt-1 text-xs text-gray-500">None.</div>
                    <div v-for="d in r.documents" :key="d.id" class="mt-1 flex flex-wrap items-center gap-2 text-sm">
                        <a :href="route('eir-governance.documents.open', d.id)" class="text-sky-700 underline" :class="d.removed ? 'line-through' : ''">{{ d.name }}</a>
                        <span class="text-xs text-gray-500">{{ d.type }}<span v-if="d.description">, {{ d.description }}</span><span v-if="d.removed"> (removed, kept on record)</span></span>
                        <span class="font-mono text-[10px] text-gray-400" :title="'SHA-256 ' + d.sha256">{{ d.sha256.slice(0, 12) }}</span>
                        <button v-if="r.status === 'PROPOSED' && !d.removed" type="button" class="text-xs text-red-700 underline" @click="removeDocument(d)">remove</button>
                    </div>
                </div>

                <div v-if="r.status === 'PROPOSED'" class="mt-4 rounded-md border border-gray-200 bg-gray-50 p-3">
                    <div class="grid gap-2 md:grid-cols-4">
                        <input type="file" class="text-sm md:col-span-2" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.png,.jpg,.jpeg" @change="files[r.id] = $event.target.files[0]"/>
                        <select v-model="docType[r.id]" class="maiic-select"><option v-for="t in documentTypes" :key="t" :value="t">{{ t }}</option></select>
                        <input v-model="docDescription[r.id]" type="text" maxlength="255" class="maiic-input" placeholder="Short description"/>
                    </div>
                    <div class="mt-3 flex flex-wrap justify-end gap-2">
                        <button type="button" class="secondary-btn" :disabled="!files[r.id] || processing" @click="attach(r)">Attach document</button>
                        <button v-if="r.proposed_by !== 'installation'" type="button" class="secondary-btn" @click="review(r, 'withdraw')">Withdraw</button>
                        <button type="button" class="secondary-btn" @click="review(r, 'reject')">Reject</button>
                    </div>
                </div>
            </div>
            <div v-if="!rows.length" class="maiic-panel p-6 text-center text-sm text-gray-500">No change has been recorded for this setting.</div>

            <div class="maiic-panel">
                <div class="px-4 pt-3 text-sm font-semibold text-gray-900">Audit entries</div>
                <div class="maiic-table-wrap">
                    <table class="maiic-table">
                        <thead><tr><th>When</th><th>Event</th><th>Change</th><th>By</th></tr></thead>
                        <tbody>
                            <tr v-for="e in pagedEvents" :key="e.id"><td class="whitespace-nowrap">{{ e.at }}</td><td>{{ e.action }}</td><td>{{ e.change }}</td><td>{{ e.by || '-' }}</td></tr>
                        </tbody>
                    </table>
                </div>
                <RowPager v-model="page" :total="events.length"/>
            </div>
        </div>

        <jet-dialog-modal :show="dialog.show" max-width="lg" @close="dialog.show = false">
            <template #title><div class="font-semibold text-gray-900">{{ dialog.title }}</div></template>
            <template #content><label class="block text-sm font-medium text-gray-700">Reason (at least 10 characters)<textarea v-model="dialog.reason" rows="3" class="maiic-input mt-1 w-full"></textarea></label></template>
            <template #footer><button class="secondary-btn mr-2" @click="dialog.show = false">Cancel</button><button class="primary-btn" :disabled="processing || dialog.reason.trim().length < 10" @click="dialog.submit()">Confirm</button></template>
        </jet-dialog-modal>
        <teleport to="head"><title>Governance history</title></teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetDialogModal from '@/Jetstream/DialogModal.vue'
import RowPager from '@/Components/Maiic/RowPager.vue'
import { Link } from '@inertiajs/vue3'

export default {
    components: { AppLayout, JetDialogModal, RowPager, Link },
    props: {
        settingKey: { type: String, default: '' },
        label: { type: String, default: '' },
        evidenceRequired: { type: Boolean, default: false },
        rows: { type: Array, default: () => [] },
        events: { type: Array, default: () => [] },
        documentTypes: { type: Array, default: () => [] },
        userId: { type: Number, default: null },
    },
    data() {
        return { page: 1, processing: false, files: {}, docType: {}, docDescription: {}, dialog: { show: false, title: '', reason: '', submit: () => {} } }
    },
    computed: {
        pagedEvents() { return this.events.slice((this.page - 1) * 15, this.page * 15) },
    },
    methods: {
        statusLabel(s) { return { PROPOSED: 'Awaiting approval', APPROVED: 'Approved', REJECTED: 'Rejected', WITHDRAWN: 'Withdrawn' }[s] || s },
        badge(s) { return s === 'APPROVED' ? 'maiic-badge-green' : (s === 'PROPOSED' ? 'maiic-badge-gold' : 'maiic-badge-grey') },
        attach(r) {
            const data = new FormData()
            data.append('file', this.files[r.id])
            data.append('document_type', this.docType[r.id] || this.documentTypes[0])
            if (this.docDescription[r.id]) data.append('description', this.docDescription[r.id])
            this.processing = true
            this.$inertia.post(this.route('eir-governance.documents.attach', r.id), data, { preserveScroll: true, forceFormData: true, onFinish: () => { this.processing = false; this.files[r.id] = null } })
        },
        open(title, submit) { this.dialog = { show: true, title, reason: '', submit } },
        review(r, action) {
            this.open(action === 'reject' ? 'Reject the proposal' : 'Withdraw the proposal', () => {
                this.processing = true
                this.$inertia.post(this.route('eir-governance.review', r.id), { action, reason: this.dialog.reason }, { preserveScroll: true, onFinish: () => { this.processing = false }, onSuccess: () => { this.dialog.show = false } })
            })
        },
        removeDocument(d) {
            this.open('Remove ' + d.name + ' from the proposal', () => {
                this.processing = true
                this.$inertia.post(this.route('eir-governance.documents.remove', d.id), { reason: this.dialog.reason }, { preserveScroll: true, onFinish: () => { this.processing = false }, onSuccess: () => { this.dialog.show = false } })
            })
        },
    },
}
</script>
