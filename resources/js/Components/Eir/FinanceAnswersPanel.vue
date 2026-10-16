<template>
    <!-- Finance's answers on the fee reconciliation workbook (spec v4 section 7.8.9). Nothing changes on upload:
         each answer waits here until a second person approves it (applied through the service that owns the
         decision), rejects it, or records it as not actionable. A locked EIR is never changed: it is flagged. -->
    <div class="space-y-4">
        <Notice id="answers-intro">
            <b>Download for Finance</b> gives Finance the reconciliation workbook from the live data, each row with a hidden key.
            <b>Upload Finance's answers</b> reads it back (or the offline workbook of 16 October 2026, matched by loan account, fee type and amount) and stores the file as the supporting document.
            Nothing changes on upload: a different person, with eir.govern, approves or rejects each answer, or approves all of a sheet with one reason.
            Approving applies the answer through the action that already owns it (tie a held posting, decide an overlap, classify and review a line); a locked EIR is never changed, it is flagged for review.
        </Notice>

        <Notice v-if="basis && basis.locked.length" id="answers-basis" tone="warning">
            <b>LOS charges never posted to the ledger</b> (Governance Centre): in force "{{ basis.in_force }}".
            Under "{{ basis.other }}" the fee inputs of {{ basis.locked.length }} locked EIR(s) would change, {{ money(basisTotal) }} in all, and each would be flagged for review, never reopened:
            <span v-for="(c, i) in basis.locked" :key="c.contract_id"><b>{{ c.customer || c.contract_id }}</b> {{ c.contract_id }} ({{ money(c.amount) }}){{ i < basis.locked.length - 1 ? ', ' : '.' }}</span>
            {{ basis.confirmed_charged }} of the {{ basis.never_posted }} never-posted lines are confirmed charged by an approved answer.
            <Link :href="route('eir-governance.index')" class="font-semibold underline">Governance Centre</Link>
        </Notice>

        <!-- the uploads, newest first -->
        <div v-if="uploads.length" class="maiic-panel">
            <div class="maiic-table-wrap">
                <table class="maiic-table">
                    <thead><tr><th>Upload</th><th>Workbook</th><th>Read by</th><th>Uploaded by</th><th>Status</th><th>Waiting, by sheet</th><th>Refused rows</th></tr></thead>
                    <tbody>
                        <tr v-for="u in uploads" :key="u.id">
                            <td class="font-semibold">#{{ u.id }}</td>
                            <td class="max-w-[16rem] text-xs">
                                <a v-for="d in u.documents" :key="d.id" :href="route('approvals.documents.open', d.id)" class="block text-sky-700 underline dark:text-sky-300">{{ d.name }}</a>
                                <span v-if="!u.documents.length">{{ u.original_name }}</span>
                                <Link :href="route('approvals.record', ['fee-workbook', u.id])" class="text-xs text-gray-500 underline dark:text-slate-400">Approval record</Link>
                            </td>
                            <td class="text-xs">{{ u.basis === 'KEYED' ? 'Hidden keys, fingerprint checked' : 'No keys: loan account, fee type, amount' }}</td>
                            <td class="text-xs">{{ u.uploader }}<div class="text-gray-500 dark:text-slate-400">{{ String(u.uploaded_at || '').slice(0, 16) }}</div></td>
                            <td><span :class="u.status === 'OPEN' ? 'maiic-badge-gold' : u.status === 'DECIDED' ? 'maiic-badge-green' : 'maiic-badge-grey'">{{ u.status === 'OPEN' ? 'Open' : u.status.charAt(0) + u.status.slice(1).toLowerCase() }}</span></td>
                            <td class="text-xs">
                                <div v-for="(n, sheet) in u.waiting" :key="sheet" class="flex items-center justify-between gap-2 py-0.5">
                                    <span>{{ sheet }}: {{ n }}</span>
                                    <button v-if="canDecide && u.uploaded_by !== userId" type="button" class="secondary-btn !px-2 !py-0.5 text-xs" @click="openSheet(u, sheet, n)">Approve all</button>
                                </div>
                                <span v-if="!Object.keys(u.waiting).length" class="text-gray-400">None</span>
                            </td>
                            <td class="max-w-[18rem] text-xs">
                                <template v-if="u.refused.length">
                                    <button type="button" class="font-semibold text-amber-700 underline dark:text-amber-300" @click="refusedOf = u">{{ u.refused.length }} row(s)</button>
                                </template>
                                <span v-else class="text-gray-400">None</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="maiic-filterbar">
            <div class="grid grid-cols-1 items-end gap-3 md:grid-cols-4">
                <label class="block"><span class="maiic-flabel">Sheet</span>
                    <select v-model="filter.answer_sheet" class="maiic-select w-full" @change="apply"><option value="">All sheets</option><option v-for="s in sheets" :key="s" :value="s">{{ s }}</option></select>
                </label>
                <label class="block"><span class="maiic-flabel">Status</span>
                    <select v-model="filter.answer_status" class="maiic-select w-full" @change="apply">
                        <option value="">All statuses</option>
                        <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}{{ counts[s.value] ? ' (' + counts[s.value] + ')' : '' }}</option>
                    </select>
                </label>
            </div>
        </div>

        <!-- the buttons act on the answers: below the cards and the tabs, at the top right of the table -->
        <div data-actions-anchor></div>
        <div class="maiic-panel">
            <div class="maiic-table-wrap">
                <table class="maiic-table">
                    <thead><tr><th>Sheet, row</th><th>Record</th><th>Finance's answer</th><th>Entered by</th><th>Decision</th><th v-if="canDecide" class="text-right">Decide</th></tr></thead>
                    <tbody>
                        <tr v-for="a in rows" :key="a.id">
                            <td class="whitespace-nowrap text-xs"><div class="font-semibold">{{ a.sheet }}</div>row {{ a.row_number }}, upload #{{ a.upload_id }}</td>
                            <td class="max-w-[15rem] text-xs"><div class="font-mono">{{ a.record_key }}</div><div v-if="a.contract_id" class="text-gray-500 dark:text-slate-400">Loan {{ a.contract_id }}</div><div class="text-gray-500 dark:text-slate-400">{{ contextLine(a) }}</div></td>
                            <td class="max-w-[18rem] text-xs">
                                <div class="font-semibold">{{ a.answer || 'No answer picked' }}</div>
                                <div v-if="a.loan_account_given">Loan account given: {{ a.loan_account_given }}</div>
                                <div v-if="a.correct_amount !== null && a.correct_amount !== undefined">Correct amount: {{ money(a.correct_amount) }}</div>
                                <div v-if="a.detail">{{ a.detail }}</div>
                                <div v-if="a.comment" class="italic text-gray-600 dark:text-slate-300">"{{ a.comment }}"</div>
                            </td>
                            <td class="text-xs">{{ a.proposer }}</td>
                            <td class="max-w-[22rem] text-xs">
                                <span :class="statusClass(a.status)">{{ statusLabel(a.status) }}</span>
                                <div v-if="a.decider" class="mt-1 text-gray-500 dark:text-slate-400">{{ a.decider }}, {{ String(a.decided_at || '').slice(0, 16) }}: {{ a.decision_reason }}</div>
                                <div v-if="a.outcome" class="mt-1">{{ a.outcome }}</div>
                                <div v-if="a.follow_up" class="mt-1 rounded bg-amber-50 px-2 py-1 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200">Follow-up: {{ a.follow_up }}</div>
                            </td>
                            <td v-if="canDecide" class="whitespace-nowrap text-right">
                                <template v-if="a.status === 'PROPOSED' && a.proposed_by !== userId">
                                    <button type="button" class="maiic-action maiic-action-view" title="Approve: apply the answer through its service" @click="openDecide(a, 'approve')"><font-awesome-icon icon="check"/></button>
                                    <button type="button" class="maiic-action maiic-action-neutral ml-1" title="Not actionable: record it, apply nothing" @click="openDecide(a, 'not_actionable')"><font-awesome-icon icon="minus"/></button>
                                    <button type="button" class="maiic-action maiic-action-delete ml-1" title="Reject the answer" @click="openDecide(a, 'reject')"><font-awesome-icon icon="ban"/></button>
                                </template>
                                <span v-else-if="a.status === 'PROPOSED'" class="text-xs text-gray-400" title="The person who entered the answers cannot decide them">Yours: a second person decides</span>
                            </td>
                        </tr>
                        <tr v-if="!rows.length"><td :colspan="canDecide ? 6 : 5" class="maiic-empty">
                            <template v-if="filter.answer_sheet || filter.answer_status">No answer matches these filters. Clear a filter to see more.</template>
                            <template v-else>No answers yet. Press Download for Finance, send the workbook to Finance, then upload the answered workbook with Upload Finance's answers.</template>
                        </td></tr>
                    </tbody>
                </table>
            </div>
            <div v-if="answers && answers.links && answers.links.length > 3" class="border-t px-4 pb-4 dark:border-slate-700"><Pagination :links="answers.links"/></div>
        </div>

        <jet-dialog-modal :show="!!deciding" @close="deciding = null">
            <template #title>{{ actionLabel(deciding?.action) }}: {{ deciding?.answer.sheet }}, row {{ deciding?.answer.row_number }}</template>
            <template #content>
                <div v-if="deciding" class="space-y-3">
                    <p class="text-sm text-gray-700 dark:text-slate-300">Finance's answer: <b>{{ deciding.answer.answer || 'No answer picked' }}</b><span v-if="deciding.answer.comment"> ("{{ deciding.answer.comment }}")</span>, on {{ deciding.answer.record_key }}.</p>
                    <p class="text-sm text-gray-700 dark:text-slate-300">{{ actionHelp(deciding.action) }}</p>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">Reason (written to the audit log)<textarea v-model="reason" rows="3" class="maiic-input mt-1 w-full" placeholder="e.g. Checked against the GL posting and the offer letter."></textarea></label>
                </div>
            </template>
            <template #footer><button type="button" class="secondary-btn mr-2" @click="deciding = null">Cancel</button><button type="button" class="primary-btn" :disabled="saving || reason.trim().length < 10" @click="submitDecide">{{ actionLabel(deciding?.action) }}</button></template>
        </jet-dialog-modal>

        <jet-dialog-modal :show="!!sheetApproval" @close="sheetApproval = null">
            <template #title>Approve all of {{ sheetApproval?.sheet }}, upload #{{ sheetApproval?.upload.id }}</template>
            <template #content>
                <div v-if="sheetApproval" class="space-y-3">
                    <p class="text-sm text-gray-700 dark:text-slate-300">{{ sheetApproval.count }} answer(s) are approved with one reason, each applied on its own. One that cannot be applied stays proposed and is named.</p>
                    <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">Reason (written to the audit log for each answer)<textarea v-model="reason" rows="3" class="maiic-input mt-1 w-full"></textarea></label>
                </div>
            </template>
            <template #footer><button type="button" class="secondary-btn mr-2" @click="sheetApproval = null">Cancel</button><button type="button" class="primary-btn" :disabled="saving || reason.trim().length < 10" @click="submitSheet">Approve all</button></template>
        </jet-dialog-modal>

        <jet-dialog-modal :show="!!refusedOf" max-width="2xl" @close="refusedOf = null">
            <template #title>Rows refused from upload #{{ refusedOf?.id }}</template>
            <template #content>
                <p class="text-sm text-gray-700 dark:text-slate-300">Each row below matched no record in the system, or more than one, or gave an answer that is not in its list. Nothing was read from it; ask Finance to correct it and upload again.</p>
                <ul class="mt-3 max-h-96 list-disc space-y-1 overflow-y-auto pl-5 text-sm text-gray-800 dark:text-slate-200"><li v-for="(r, i) in refusedOf?.refused || []" :key="i"><b>{{ r.sheet }}</b>: {{ r.reason }}</li></ul>
            </template>
            <template #footer><button type="button" class="secondary-btn" @click="refusedOf = null">Close</button></template>
        </jet-dialog-modal>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import JetDialogModal from '@/Jetstream/DialogModal.vue'
import Notice from '@/Components/Maiic/Notice.vue'
import Pagination from '@/Components/Pagination.vue'

const props = defineProps({
    answers: { type: Object, default: null },
    uploads: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    counts: { type: Object, default: () => ({}) },
    sheets: { type: Array, default: () => [] },
    basis: { type: Object, default: null },
    canDecide: { type: Boolean, default: false },
})

const page = usePage()
const userId = computed(() => page.props.auth?.user?.id ?? null)
const rows = computed(() => props.answers?.data || [])
const basisTotal = computed(() => (props.basis?.locked || []).reduce((s, c) => s + Number(c.amount || 0), 0))
const filter = reactive({ answer_sheet: props.filters.sheet || '', answer_status: props.filters.status || '' })
const statuses = [
    { value: 'PROPOSED', label: 'Proposed' }, { value: 'APPROVED', label: 'Approved' }, { value: 'NOT_ACTIONABLE', label: 'Not actionable' },
    { value: 'REJECTED', label: 'Rejected' }, { value: 'WITHDRAWN', label: 'Withdrawn' }, { value: 'FOLLOW_UP', label: 'Needs follow-up' },
]
const deciding = ref(null)
const sheetApproval = ref(null)
const refusedOf = ref(null)
const reason = ref('')
const saving = ref(false)

function money(v) { return Number(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }
function statusLabel(s) { return { PROPOSED: 'Proposed', APPROVED: 'Approved', REJECTED: 'Rejected', NOT_ACTIONABLE: 'Not actionable', WITHDRAWN: 'Withdrawn' }[s] || s }
function statusClass(s) { return s === 'APPROVED' ? 'maiic-badge-green' : s === 'PROPOSED' ? 'maiic-badge-gold' : s === 'REJECTED' ? 'maiic-badge-red' : 'maiic-badge-grey' }
function actionLabel(a) { return { approve: 'Approve', reject: 'Reject', not_actionable: 'Record as not actionable' }[a] || '' }
function actionHelp(a) {
    return {
        approve: 'Approving applies the answer through the action that owns it; where the system cannot make the change safely it is left as a follow-up, and a locked EIR is flagged for review, never changed.',
        reject: 'Rejecting applies nothing; the answer stays on record with your reason.',
        not_actionable: 'Recording it as not actionable applies nothing; the answer stays on record with your reason.',
    }[a] || ''
}
function contextLine(a) {
    const c = a.context || {}
    const bits = [c['Customer'], c['Fee type'], c['Amount'] !== undefined ? money(c['Amount']) : null, c['Batch'], c['Narration']].filter(Boolean)
    return bits.join(', ')
}
function apply() {
    const q = { tab: 'answers' }
    for (const [k, v] of Object.entries(filter)) if (v) q[k] = v
    router.get(route('eir-fee-classification.index'), q, { preserveState: true, preserveScroll: true })
}
function openDecide(answer, action) { reason.value = ''; deciding.value = { answer, action } }
function submitDecide() {
    saving.value = true
    router.post(route('eir-fee-classification.answers.decide', deciding.value.answer.id), { action: deciding.value.action, reason: reason.value },
        { preserveScroll: true, onSuccess: () => { deciding.value = null }, onFinish: () => { saving.value = false } })
}
function openSheet(upload, sheet, count) { reason.value = ''; sheetApproval.value = { upload, sheet, count } }
function submitSheet() {
    saving.value = true
    router.post(route('eir-fee-classification.answers.approve-sheet'), { upload_id: sheetApproval.value.upload.id, sheet: sheetApproval.value.sheet, reason: reason.value },
        { preserveScroll: true, onSuccess: () => { sheetApproval.value = null }, onFinish: () => { saving.value = false } })
}
</script>
