<template>
  <app-layout title="Write-offs" description="Every write-off the system knows of: those the E-Banker ledger carries, and the register for write-offs that are not on the loan account">
    <template #actions>
      <button v-if="canGovern" type="button" class="primary-btn" @click="openPropose"><font-awesome-icon icon="plus" /> Propose write-off</button>
    </template>

    <div class="w-full space-y-5">
      <KpiRow :cards="cards" />

      <div class="maiic-panel">
        <div class="border-b border-gray-200 p-4 dark:border-slate-700">
          <h3 class="font-semibold text-gray-900 dark:text-slate-100">Write-offs in the ledger</h3>
          <p class="mt-1 text-xs text-gray-500">A credit to a loan account is a write-off, not a collection, when its type is 300, when its batch debits GL 1064 (Term Loans Write Offs), or when it is an ACCOUNT CLOSURE in a TRF batch on a status-H account. These need no approval: E-Banker's posting is the evidence.</p>
        </div>
        <div class="maiic-table-wrap">
          <table class="maiic-table maiic-table-compact">
            <thead><tr><th>Date</th><th>Loan</th><th class="num">Amount</th><th>Batch</th><th>Narration</th><th>Why it is a write-off</th></tr></thead>
            <tbody>
              <tr v-for="(w, i) in pagedLedger" :key="w.account + w.date + i">
                <td class="whitespace-nowrap">{{ w.date }}</td>
                <td><div class="font-semibold">{{ w.customer_name || '-' }}</div><div class="text-xs text-gray-500">{{ w.contract_id }}</div></td>
                <td class="num">{{ money(w.amount) }}</td>
                <td class="whitespace-nowrap text-xs">{{ w.batch }}<div class="text-gray-400">type {{ w.type }}</div></td>
                <td class="max-w-[14rem] text-xs text-gray-600">{{ w.narration }}</td>
                <td class="max-w-[14rem] text-xs">{{ w.rule }}</td>
              </tr>
              <tr v-if="!ledger.length"><td colspan="6" class="maiic-empty">No write-off in the ledger.</td></tr>
            </tbody>
          </table>
        </div>
        <div v-if="ledger.length > 15" class="border-t border-gray-100 px-4 py-3"><ClientPager v-model="ledgerPage" :total="ledger.length" /></div>
      </div>

      <div class="maiic-panel">
        <div class="border-b border-gray-200 p-4 dark:border-slate-700">
          <h3 class="font-semibold text-gray-900 dark:text-slate-100">Write-off register</h3>
          <p class="mt-1 text-xs text-gray-500">For a write-off that is not on the loan account (for example one made only in the audit adjustments or at GL level). One person proposes with the evidence; a different person approves. An approved write-off is written to the loan's actual transactions as a write-off, which the EIR treats as derecognition, not cash.</p>
        </div>
        <div class="maiic-table-wrap">
          <table class="maiic-table maiic-table-compact">
            <thead><tr><th>#</th><th>Loan</th><th>Date</th><th class="num">Amount</th><th>Reason and evidence</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
              <tr v-for="w in register" :key="w.id">
                <td class="font-semibold">#{{ w.id }}</td>
                <td><div class="font-semibold">{{ w.customer_name || '-' }}</div><div class="text-xs text-gray-500">{{ w.contract_id }}</div></td>
                <td class="whitespace-nowrap">{{ w.write_off_date }}</td>
                <td class="num">{{ money(w.amount) }}</td>
                <td class="max-w-[18rem] text-xs"><div>{{ w.reason }}</div><div class="text-gray-500">{{ w.evidence }}</div><div v-if="w.gl_reference" class="text-gray-400">{{ w.gl_reference }}</div></td>
                <td class="text-xs">
                  <span class="maiic-badge" :class="badge(w.status)">{{ label(w.status) }}</span>
                  <div class="mt-1 text-gray-500">Proposed by {{ w.proposed_by_name || '-' }}</div>
                  <div v-if="w.approved_by_name" class="text-gray-500">Approved by {{ w.approved_by_name }}</div>
                  <div v-if="w.close_reason" class="text-gray-500">{{ w.closed_by_name }}: {{ w.close_reason }}</div>
                </td>
                <td class="text-right">
                  <div v-if="canGovern" class="flex justify-end gap-1.5">
                    <button v-if="w.status === 'PROPOSED' && w.proposed_by !== me" type="button" class="maiic-action maiic-action-view" title="Approve" @click="approve(w)"><font-awesome-icon icon="check" /></button>
                    <button v-if="w.status === 'PROPOSED' && w.proposed_by !== me" type="button" class="maiic-action maiic-action-delete" title="Reject" @click="closeIt(w, 'reject')"><font-awesome-icon icon="times" /></button>
                    <button v-if="w.status === 'PROPOSED' && w.proposed_by === me" type="button" class="maiic-action maiic-action-edit" title="Withdraw" @click="closeIt(w, 'withdraw')"><font-awesome-icon icon="ban" /></button>
                    <button v-if="w.status === 'APPROVED'" type="button" class="maiic-action maiic-action-edit" title="Reverse" @click="closeIt(w, 'reverse')"><font-awesome-icon icon="history" /></button>
                  </div>
                </td>
              </tr>
              <tr v-if="!register.length"><td colspan="7" class="maiic-empty">No write-off in the register. Use <strong>Propose write-off</strong> at the top right for one that is not on the loan account.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <jet-modal :show="open" max-width="2xl" @close="open = false">
      <form class="p-6" @submit.prevent="submit">
        <h3 class="text-lg font-semibold text-gray-900">Propose a write-off</h3>
        <p class="mt-1 text-sm text-gray-600">Only for a write-off that is not posted on the loan account in E-Banker. A write-off E-Banker posted (type 300, or a batch to GL 1064) is already counted.</p>
        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
          <div class="md:col-span-2">
            <label class="maiic-flabel" for="wo-contract">Loan</label>
            <select id="wo-contract" v-model="form.contract_id" class="maiic-select">
              <option value="">Choose the loan</option>
              <option v-for="c in contracts" :key="c.contract_id" :value="c.contract_id">{{ c.customer_name }} ({{ c.contract_id }})</option>
            </select>
            <p v-if="form.errors.contract_id" class="mt-1 text-xs text-red-600">{{ form.errors.contract_id }}</p>
          </div>
          <div>
            <label class="maiic-flabel" for="wo-date">Write-off date</label>
            <input id="wo-date" v-model="form.write_off_date" type="date" class="maiic-input" />
            <p v-if="form.errors.write_off_date" class="mt-1 text-xs text-red-600">{{ form.errors.write_off_date }}</p>
          </div>
          <div>
            <label class="maiic-flabel" for="wo-amount">Amount written off</label>
            <input id="wo-amount" v-model="form.amount" type="number" step="0.01" min="0" class="maiic-input" />
            <p v-if="form.errors.amount" class="mt-1 text-xs text-red-600">{{ form.errors.amount }}</p>
          </div>
          <div class="md:col-span-2">
            <label class="maiic-flabel" for="wo-reason">Reason</label>
            <input id="wo-reason" v-model="form.reason" type="text" class="maiic-input" placeholder="Why the loan is written off" />
            <p v-if="form.errors.reason" class="mt-1 text-xs text-red-600">{{ form.errors.reason }}</p>
          </div>
          <div class="md:col-span-2">
            <label class="maiic-flabel" for="wo-evidence">Evidence</label>
            <textarea id="wo-evidence" v-model="form.evidence" rows="3" class="maiic-input" placeholder="Where it is recorded: board minute, audit adjustment, GL batch"></textarea>
            <p v-if="form.errors.evidence" class="mt-1 text-xs text-red-600">{{ form.errors.evidence }}</p>
          </div>
          <div class="md:col-span-2">
            <label class="maiic-flabel" for="wo-gl">GL reference (optional)</label>
            <input id="wo-gl" v-model="form.gl_reference" type="text" class="maiic-input" placeholder="For example GL 1064 / 1876, batch TRF 26 of 31 Jan 2026" />
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
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import JetModal from '@/Jetstream/Modal.vue'
import KpiRow from '@/Components/Maiic/KpiRow.vue'
import ClientPager from '@/Components/ClientPager.vue'
import { confirmDialog } from '@/Components/confirmDialog'
import { promptReason } from '@/Components/Maiic/promptReason.js'

const props = defineProps({
  ledger: { type: Array, default: () => [] },
  register: { type: Array, default: () => [] },
  contracts: { type: Array, default: () => [] },
  me: { type: Number, default: null },
  canGovern: { type: Boolean, default: false },
  summary: { type: Object, default: () => ({}) },
})

const open = ref(false)
const ledgerPage = ref(1)
const pagedLedger = computed(() => props.ledger.slice((ledgerPage.value - 1) * 15, ledgerPage.value * 15))
const form = useForm({ contract_id: '', write_off_date: '', amount: '', reason: '', evidence: '', gl_reference: '' })

function openPropose() { form.reset(); form.clearErrors(); open.value = true }
function submit() {
  form.post(route('eir-write-offs.store'), { preserveScroll: true, onSuccess: () => { if (!Object.keys(form.errors).length) open.value = false } })
}
async function approve(w) {
  if (!(await confirmDialog({ title: `Approve write-off #${w.id}?`, message: `${w.customer_name}: ${money(w.amount)} on ${w.write_off_date}. It is written to the loan's actual transactions as a write-off.`, confirmLabel: 'Approve' }))) return
  router.post(route('eir-write-offs.approve', w.id), {}, { preserveScroll: true })
}
async function closeIt(w, action) {
  const words = { reject: 'Reject', withdraw: 'Withdraw', reverse: 'Reverse' }
  const reason = await promptReason({ title: `${words[action]} write-off #${w.id}`, message: 'Give the reason; it is kept on the register and in the audit trail.', confirmLabel: words[action] })
  if (!reason) return
  router.post(route('eir-write-offs.close', w.id), { action, reason }, { preserveScroll: true })
}

const cards = computed(() => [
  { label: 'In the ledger', value: Number(props.summary.ledger_count || 0), sub: money(props.summary.ledger_amount) },
  { label: 'Approved in the register', value: Number(props.summary.approved_count || 0), sub: money(props.summary.approved_amount) },
  { label: 'Awaiting approval', value: Number(props.summary.proposed || 0), accent: props.summary.proposed ? '#d97706' : null },
])

const money = (v) => Number(v || 0).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const label = (s) => ({ PROPOSED: 'Proposed', APPROVED: 'Approved', REJECTED: 'Rejected', WITHDRAWN: 'Withdrawn', REVERSED: 'Reversed' }[s] || s)
const badge = (s) => ({ PROPOSED: 'maiic-badge-gold', APPROVED: 'maiic-badge-green', REJECTED: 'maiic-badge-red', WITHDRAWN: 'maiic-badge-grey', REVERSED: 'maiic-badge-grey' }[s] || 'maiic-badge-grey')
</script>
