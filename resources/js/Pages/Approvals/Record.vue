<template>
    <app-layout :title="label + ' ' + id" description="The item's history from the audit log, its supporting documents, and reject or withdraw with a reason. Approving stays on the workflow's own screen.">
        <div class="w-full space-y-4">
            <div class="maiic-panel flex flex-wrap items-center justify-between gap-2 p-4 text-sm text-gray-700">
                <div>
                    <span class="maiic-badge" :class="pending ? 'maiic-badge-gold' : 'maiic-badge-grey'">{{ pending ? 'Awaiting a decision' : (status || 'Decided') }}</span>
                    <span v-if="maker" class="ml-2 text-xs text-gray-500">Proposed by {{ maker }}</span>
                </div>
                <span v-if="evidence" class="text-xs text-amber-800">Required before approval: {{ evidence }}</span>
            </div>

            <div class="maiic-panel p-4">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Supporting documents</div>
                <div v-if="!documents.length" class="mt-1 text-xs text-gray-500">None.</div>
                <div v-for="d in documents" :key="d.id" class="mt-1 flex flex-wrap items-center gap-2 text-sm">
                    <a :href="route('approvals.documents.open', d.id)" class="text-sky-700 underline" :class="d.removed ? 'line-through' : ''">{{ d.name }}</a>
                    <span class="text-xs text-gray-500">{{ d.type }}<span v-if="d.description">, {{ d.description }}</span><span v-if="d.removed"> (removed, kept on record)</span></span>
                    <span class="font-mono text-[10px] text-gray-400" :title="'SHA-256 ' + d.sha256">{{ d.sha256.slice(0, 12) }}</span>
                    <button v-if="pending && !d.removed" type="button" class="text-xs text-red-700 underline" @click="removeDocument(d)">remove</button>
                </div>
                <div v-if="pending" class="mt-3 grid gap-2 md:grid-cols-4">
                    <input type="file" class="text-sm md:col-span-2" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.png,.jpg,.jpeg" @change="file = $event.target.files[0]"/>
                    <select v-model="docType" class="maiic-select"><option v-for="t in documentTypes" :key="t" :value="t">{{ t }}</option></select>
                    <input v-model="docDescription" type="text" maxlength="255" class="maiic-input" placeholder="Short description"/>
                </div>
                <div v-if="pending" class="mt-3 flex flex-wrap justify-end gap-2">
                    <button type="button" class="secondary-btn" :disabled="!file || processing" @click="attach">Attach document</button>
                    <button v-if="isMaker" type="button" class="secondary-btn" @click="decide('withdraw')">Withdraw</button>
                    <button type="button" class="secondary-btn" @click="decide('reject')">Reject</button>
                </div>
            </div>

            <div class="maiic-panel">
                <div class="px-4 pt-3 text-sm font-semibold text-gray-900">History</div>
                <div class="maiic-table-wrap">
                    <table class="maiic-table">
                        <thead><tr><th>When</th><th>Event</th><th>By</th><th>Reason</th></tr></thead>
                        <tbody>
                            <tr v-for="e in pagedEvents" :key="e.id"><td class="whitespace-nowrap">{{ e.at }}</td><td>{{ e.action }}</td><td>{{ e.by || '-' }}</td><td class="max-w-md text-xs">{{ e.reason || '' }}</td></tr>
                            <tr v-if="!events.length"><td colspan="4" class="text-center text-gray-500">No audit entry for this item.</td></tr>
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
        <teleport to="head"><title>Approval record</title></teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetDialogModal from '@/Jetstream/DialogModal.vue'
import RowPager from '@/Components/Maiic/RowPager.vue'

export default {
    components: { AppLayout, JetDialogModal, RowPager },
    props: {
        workflow: { type: String, default: '' },
        label: { type: String, default: '' },
        id: { type: Number, default: 0 },
        status: { type: String, default: '' },
        pending: { type: Boolean, default: false },
        evidence: { type: String, default: null },
        maker: { type: String, default: null },
        isMaker: { type: Boolean, default: false },
        events: { type: Array, default: () => [] },
        documents: { type: Array, default: () => [] },
        documentTypes: { type: Array, default: () => [] },
    },
    data() {
        return { page: 1, processing: false, file: null, docType: 'other', docDescription: '', dialog: { show: false, title: '', reason: '', submit: () => {} } }
    },
    computed: {
        pagedEvents() { return this.events.slice((this.page - 1) * 15, this.page * 15) },
    },
    methods: {
        attach() {
            const data = new FormData()
            data.append('file', this.file)
            data.append('document_type', this.docType)
            if (this.docDescription) data.append('description', this.docDescription)
            this.processing = true
            this.$inertia.post(this.route('approvals.documents.attach', [this.workflow, this.id]), data, { preserveScroll: true, forceFormData: true, onFinish: () => { this.processing = false; this.file = null } })
        },
        open(title, submit) { this.dialog = { show: true, title, reason: '', submit } },
        decide(action) {
            this.open(action === 'reject' ? 'Reject' : 'Withdraw', () => {
                this.processing = true
                this.$inertia.post(this.route('approvals.decide', [this.workflow, this.id]), { action, reason: this.dialog.reason }, { preserveScroll: true, onFinish: () => { this.processing = false }, onSuccess: () => { this.dialog.show = false } })
            })
        },
        removeDocument(d) {
            this.open('Remove ' + d.name, () => {
                this.processing = true
                this.$inertia.post(this.route('approvals.documents.remove', d.id), { reason: this.dialog.reason }, { preserveScroll: true, onFinish: () => { this.processing = false }, onSuccess: () => { this.dialog.show = false } })
            })
        },
    },
}
</script>
