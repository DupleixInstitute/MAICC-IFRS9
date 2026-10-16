<template>
  <app-layout title="Account Exclusions" description="E-Banker accounts that are not loans of the book: the vendor's UAT test accounts and approvals duplicated on a sister account. Every build leaves an approved exclusion out">
    <template #actions>
      <button v-if="canGovern" type="button" class="primary-btn" @click="openPropose"><font-awesome-icon icon="plus" /> Propose exclusion</button>
    </template>

    <div class="w-full space-y-5">
      <KpiRow :cards="cards" />

      <Notice id="exclusions-intro">
        An approved exclusion takes the account out of the loan book, the contract master, the fee lines, the drawdowns and the cash movements, so no EIR, revenue, staging,
        PD, LGD, ECL or report sees it. One person proposes it with the reason and the evidence and attaches the supporting document on its record; a different person approves it.
        On approval the account's rows are moved to the archive, never deleted, and a withdrawal (asked by one person, approved by another) puts them back.
        An approval is refused while any of the account's rows is in a locked reporting period.
      </Notice>

      <!-- Propose exclusion acts on the register, so the page button sits at its top right -->
      <div data-actions-anchor></div>
      <div class="maiic-panel">
        <div class="border-b border-gray-200 p-4 dark:border-slate-700">
          <h3 class="font-semibold text-gray-900 dark:text-slate-100">Exclusion register</h3>
          <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Proposals first, then the approved exclusions, then the closed ones. "Moves if approved" is what the account holds in the book now; "In the archive" is what an approval moved out.</p>
        </div>
        <div class="maiic-table-wrap">
          <table class="maiic-table maiic-table-compact">
            <thead><tr><th>Account</th><th>Category</th><th>Reason and evidence</th><th>Sister account</th><th>Status</th><th>Rows</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
              <tr v-for="e in paged" :key="e.id">
                <td><div class="font-semibold text-gray-900 dark:text-slate-100">{{ e.customer_name || '-' }}</div><div class="text-xs text-gray-500 dark:text-slate-400">{{ e.contract_id }} <span class="text-gray-400">#{{ e.id }}</span></div></td>
                <td class="whitespace-nowrap text-xs">{{ e.category_label }}</td>
                <td class="max-w-[20rem] text-xs"><div class="text-gray-800 dark:text-slate-200">{{ e.reason }}</div><div class="mt-0.5 text-gray-500 dark:text-slate-400">{{ e.evidence }}</div></td>
                <td class="whitespace-nowrap text-xs">{{ e.sister_contract_id || '-' }}</td>
                <td class="text-xs">
                  <span class="maiic-badge" :class="badge(e.status)">{{ label(e.status) }}</span>
                  <div class="mt-1 text-gray-500 dark:text-slate-400">Proposed by {{ e.proposed_by_name || '-' }}</div>
                  <div v-if="e.approved_by_name" class="text-gray-500 dark:text-slate-400">Approved by {{ e.approved_by_name }}</div>
                  <div v-if="e.withdrawal_requested_by" class="mt-1 font-semibold text-amber-700 dark:text-amber-300">Withdrawal asked by {{ e.withdrawal_requested_by_name }}: {{ e.withdrawal_reason }}</div>
                  <div v-if="e.close_reason" class="text-gray-500 dark:text-slate-400">{{ e.closed_by_name }}: {{ e.close_reason }}</div>
                </td>
                <td class="text-xs">
                  <template v-if="e.status === 'PROPOSED'">
                    <div class="font-semibold text-gray-700 dark:text-slate-300">Moves if approved</div>
                    <div v-for="(n, t) in e.footprint" :key="t" class="text-gray-500 dark:text-slate-400">{{ t }} {{ n }}</div>
                    <div v-if="!Object.keys(e.footprint || {}).length" class="text-gray-400">nothing held</div>
                    <div v-if="e.locked_months && e.locked_months.length" class="mt-1 font-semibold text-red-700 dark:text-red-300">Locked: {{ e.locked_months.join(', ') }}</div>
                  </template>
                  <template v-else>
                    <div class="font-semibold text-gray-700 dark:text-slate-300">In the archive</div>
                    <div v-for="(n, t) in e.archived_tables" :key="t" class="text-gray-500 dark:text-slate-400">{{ t }} {{ n }}</div>
                    <div v-if="!Object.keys(e.archived_tables || {}).length" class="text-gray-400">none</div>
                  </template>
                </td>
                <td class="text-right">
                  <div class="flex justify-end gap-1.5">
                    <Link :href="route('approvals.record', ['account-exclusion', e.id])" class="maiic-action maiic-action-view" title="Documents and history"><font-awesome-icon icon="paperclip" /></Link>
                    <template v-if="canGovern">
                      <button v-if="e.status === 'PROPOSED' && e.proposed_by !== me" type="button" class="maiic-action maiic-action-view" title="Approve" @click="approve(e)"><font-awesome-icon icon="check" /></button>
                      <button v-if="e.status === 'PROPOSED' && e.proposed_by !== me" type="button" class="maiic-action maiic-action-delete" title="Reject" @click="closeIt(e, 'reject')"><font-awesome-icon icon="times" /></button>
                      <button v-if="e.status === 'PROPOSED' && e.proposed_by === me" type="button" class="maiic-action maiic-action-edit" title="Withdraw" @click="closeIt(e, 'withdraw')"><font-awesome-icon icon="ban" /></button>
                      <button v-if="e.status === 'APPROVED' && !e.withdrawal_requested_by" type="button" class="maiic-action maiic-action-edit" title="Ask to withdraw" @click="closeIt(e, 'withdraw')"><font-awesome-icon icon="history" /></button>
                      <button v-if="e.status === 'APPROVED' && e.withdrawal_requested_by && e.withdrawal_requested_by !== me" type="button" class="maiic-action maiic-action-view" title="Approve the withdrawal" @click="approveWithdrawal(e)"><font-awesome-icon icon="check-circle" /></button>
                      <button v-if="e.status === 'APPROVED' && e.withdrawal_requested_by" type="button" class="maiic-action maiic-action-delete" :title="e.withdrawal_requested_by === me ? 'Cancel the withdrawal request' : 'Decline the withdrawal'" @click="dropWithdrawal(e)"><font-awesome-icon icon="times" /></button>
                    </template>
                  </div>
                </td>
              </tr>
              <tr v-if="!register.length"><td colspan="7" class="maiic-empty">No account is excluded. Use <strong>Propose exclusion</strong> above this table, at its top right, for an E-Banker account that is not a loan of the book.</td></tr>
            </tbody>
          </table>
        </div>
        <RowPager v-model="page" :total="register.length" class="border-t border-gray-100 dark:border-slate-700" />
      </div>
    </div>

    <jet-modal :show="open" max-width="2xl" @close="open = false">
      <form class="p-6" @submit.prevent="submit">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Propose an account exclusion</h3>
        <p class="mt-1 text-sm text-gray-600 dark:text-slate-400">Only for an E-Banker account that is not a loan of the book: a UAT test account, or an approval duplicated on a sister account and never drawn. A closed or written-off loan is a loan and stays in the book.</p>
        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <label class="maiic-flabel" for="ex-contract">E-Banker account</label>
            <input id="ex-contract" v-model="form.contract_id" type="text" class="maiic-input" placeholder="For example 104430000063" />
            <p v-if="form.errors.contract_id" class="mt-1 text-xs text-red-600">{{ form.errors.contract_id }}</p>
          </div>
          <div>
            <label class="maiic-flabel" for="ex-category">Category</label>
            <select id="ex-category" v-model="form.category" class="maiic-select">
              <option value="">Choose the category</option>
              <option v-for="(text, key) in categories" :key="key" :value="key">{{ text }}</option>
            </select>
            <p v-if="form.errors.category" class="mt-1 text-xs text-red-600">{{ form.errors.category }}</p>
          </div>
          <div class="md:col-span-2">
            <label class="maiic-flabel" for="ex-reason">Reason</label>
            <input id="ex-reason" v-model="form.reason" type="text" class="maiic-input" placeholder="Why the account is not a loan of the book" />
            <p v-if="form.errors.reason" class="mt-1 text-xs text-red-600">{{ form.errors.reason }}</p>
          </div>
          <div class="md:col-span-2">
            <label class="maiic-flabel" for="ex-evidence">Evidence</label>
            <textarea id="ex-evidence" v-model="form.evidence" rows="3" class="maiic-input" placeholder="Where it is recorded: the E-Banker status history row, the account master, MAIIC's confirmation"></textarea>
            <p v-if="form.errors.evidence" class="mt-1 text-xs text-red-600">{{ form.errors.evidence }}</p>
          </div>
          <div class="md:col-span-2">
            <label class="maiic-flabel" for="ex-sister">Sister account (for a duplicate approval)</label>
            <input id="ex-sister" v-model="form.sister_contract_id" type="text" class="maiic-input" placeholder="The account the loan runs on" />
            <p v-if="form.errors.sister_contract_id" class="mt-1 text-xs text-red-600">{{ form.errors.sister_contract_id }}</p>
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-2">
          <button type="button" class="secondary-btn" :disabled="form.processing" @click="open = false">Cancel</button>
          <button type="submit" class="primary-btn" :disabled="form.processing">Propose</button>
        </div>
      </form>
    </jet-modal>
  </app-layout>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import JetModal from '@/Jetstream/Modal.vue'
import KpiRow from '@/Components/Maiic/KpiRow.vue'
import Notice from '@/Components/Maiic/Notice.vue'
import RowPager from '@/Components/Maiic/RowPager.vue'
import { confirmDialog } from '@/Components/confirmDialog'
import { promptReason } from '@/Components/Maiic/promptReason.js'

const props = defineProps({
  register: { type: Array, default: () => [] },
  categories: { type: Object, default: () => ({}) },
  me: { type: Number, default: null },
  canGovern: { type: Boolean, default: false },
  summary: { type: Object, default: () => ({}) },
})

const open = ref(false)
// the register is sent whole: fifteen a page, kept on a page that still exists after a decision
const page = ref(1)
const paged = computed(() => props.register.slice((page.value - 1) * 15, page.value * 15))
watch(() => props.register.length, (n) => { page.value = Math.min(page.value, Math.max(1, Math.ceil(n / 15))) })
const form = useForm({ contract_id: '', category: '', reason: '', evidence: '', sister_contract_id: '' })

function openPropose() { form.reset(); form.clearErrors(); open.value = true }
function submit() {
  form.post(route('account-exclusions.store'), { preserveScroll: true, onSuccess: () => { if (!Object.keys(form.errors).length) open.value = false } })
}
async function approve(e) {
  const moves = Object.entries(e.footprint || {}).map(([t, n]) => `${t} ${n}`).join(', ') || 'nothing held'
  if (!(await confirmDialog({ title: `Approve exclusion #${e.id}?`, message: `${e.customer_name || e.contract_id} leaves the book. Rows moved to the archive: ${moves}.`, confirmLabel: 'Approve' }))) return
  router.post(route('account-exclusions.approve', e.id), {}, { preserveScroll: true })
}
async function closeIt(e, action) {
  const words = { reject: 'Reject', withdraw: e.status === 'APPROVED' ? 'Ask to withdraw' : 'Withdraw' }
  const message = e.status === 'APPROVED'
    ? 'Give the reason the account belongs in the book after all. A second person approves the withdrawal; its rows then come back from the archive.'
    : 'Give the reason; it is kept on the register and in the audit trail.'
  const reason = await promptReason({ title: `${words[action]} exclusion #${e.id}`, message, confirmLabel: words[action] })
  if (!reason) return
  router.post(route('account-exclusions.close', e.id), { action, reason }, { preserveScroll: true })
}
async function approveWithdrawal(e) {
  if (!(await confirmDialog({ title: `Approve the withdrawal of exclusion #${e.id}?`, message: `${e.customer_name || e.contract_id} comes back into the book: its archived rows are restored. Asked by ${e.withdrawal_requested_by_name}: ${e.withdrawal_reason}`, confirmLabel: 'Approve the withdrawal' }))) return
  router.post(route('account-exclusions.withdrawal.approve', e.id), {}, { preserveScroll: true })
}
async function dropWithdrawal(e) {
  const mine = e.withdrawal_requested_by === props.me
  const reason = await promptReason({ title: mine ? `Cancel the withdrawal request of #${e.id}` : `Decline the withdrawal of #${e.id}`, message: 'Give the reason; the exclusion stands.', confirmLabel: mine ? 'Cancel the request' : 'Decline' })
  if (!reason) return
  router.post(route('account-exclusions.withdrawal.drop', e.id), { reason }, { preserveScroll: true })
}

const cards = computed(() => [
  { label: 'Excluded (approved)', value: Number(props.summary.approved || 0) },
  { label: 'Awaiting approval', value: Number(props.summary.proposed || 0), accent: props.summary.proposed ? '#d97706' : null },
  { label: 'Withdrawals awaiting approval', value: Number(props.summary.withdrawals || 0), accent: props.summary.withdrawals ? '#d97706' : null },
  { label: 'Rows held in the archive', value: Number(props.summary.rows_held || 0) },
])

const label = (s) => ({ PROPOSED: 'Proposed', APPROVED: 'Excluded', REJECTED: 'Rejected', WITHDRAWN: 'Withdrawn' }[s] || s)
const badge = (s) => ({ PROPOSED: 'maiic-badge-gold', APPROVED: 'maiic-badge-green', REJECTED: 'maiic-badge-red', WITHDRAWN: 'maiic-badge-grey' }[s] || 'maiic-badge-grey')
</script>
