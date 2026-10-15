<template>
    <!-- The GL fee legs that could not be placed on one loan, and where two fee
         sources disagree for a loan (spec 7.8.4 and 7.8.5). Held lines are tied
         by a person with a reason; nothing is placed by guess. -->
    <div class="maiic-panel">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 pt-3 dark:border-slate-700">
            <nav class="flex gap-6 overflow-x-auto">
                <button v-for="t in tabs" :key="t.key" type="button" class="whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-semibold"
                        :class="tab === t.key ? 'border-maiic-600 text-maiic-700 dark:text-maiic-300' : 'border-transparent text-gray-500 hover:text-gray-800 dark:text-slate-400 dark:hover:text-slate-200'"
                        @click="tab = t.key">
                    {{ t.label }}
                    <span class="ml-1 rounded-full px-2 py-0.5 text-xs" :class="tab === t.key ? 'bg-maiic-100 text-maiic-800' : 'bg-gray-100 text-gray-600'">{{ t.count }}</span>
                </button>
            </nav>
        </div>

        <!-- held GL fee lines -->
        <div v-if="tab === 'held'">
            <p class="px-5 py-2 text-xs text-gray-500">Fee postings on the fee income GLs whose batch names no single loan. Each is held, never dropped and never guessed: tie it to its loan with the reason, and it joins the fee lines below for classification and review.</p>
            <div class="maiic-table-wrap">
                <table class="maiic-table">
                    <thead><tr><th>Date</th><th>Batch</th><th class="num">Amount</th><th>GL</th><th>Narration</th><th>Why it is held</th><th>Loan the evidence points to</th><th v-if="canGovern" class="text-right">Action</th></tr></thead>
                    <tbody>
                        <tr v-for="h in pagedHeld" :key="h.id">
                            <td class="whitespace-nowrap">{{ h.transaction_date || '-' }}</td>
                            <td class="whitespace-nowrap text-xs">{{ h.batch }}</td>
                            <td class="num">{{ money(h.amount) }}</td>
                            <td class="font-mono text-xs">{{ h.gl_code }}</td>
                            <td class="max-w-[16rem] text-xs">{{ h.narration || '-' }}</td>
                            <td class="max-w-[16rem] text-xs" :title="h.reason">{{ reasonLabel(h.reason) }}</td>
                            <td class="text-xs">
                                <template v-if="h.candidate_contract_id"><span class="font-semibold" :class="h.candidate_dismissed ? 'line-through text-gray-400' : ''">{{ h.candidate_contract_id }}</span><div class="text-gray-500">{{ h.candidate_evidence }}</div><div v-if="h.candidate_dismissed" class="text-amber-700 dark:text-amber-300">Not this loan: {{ h.candidate_dismissed }}</div></template>
                                <span v-else class="text-gray-400">None</span>
                            </td>
                            <td v-if="canGovern" class="text-right">
                                <button type="button" class="maiic-action maiic-action-edit" title="Tie this fee line to its loan" @click="openTie(h)">
                                    <font-awesome-icon icon="paperclip"/>
                                </button>
                                <button v-if="h.candidate_contract_id && !h.candidate_dismissed" type="button" class="maiic-action maiic-action-delete ml-1" title="Not this loan's fee: the posting stays held and no longer stops this loan's EIR" @click="notThisLoan(h)">
                                    <font-awesome-icon icon="ban"/>
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!held.length"><td :colspan="canGovern ? 8 : 7" class="maiic-empty">No fee posting is held: every GL fee leg was placed on its loan, or the GL fee build has not been run.</td></tr>
                    </tbody>
                </table>
            </div>
            <LocalPager v-model="heldPage" :total="held.length"/>
        </div>

        <!-- source differences -->
        <div v-else>
            <p class="px-5 py-2 text-xs text-gray-500">One fee, one source: for each loan the source of record wins and the other sources' lines are kept but left out of the EIR. Where the other source carries a different amount, Finance checks the difference here.</p>
            <div class="maiic-table-wrap">
                <table class="maiic-table">
                    <thead><tr><th>Loan</th><th>Fee type</th><th>Source of record</th><th class="num">Amount of record</th><th>Other source</th><th class="num">Other amount</th><th class="num">Difference</th></tr></thead>
                    <tbody>
                        <tr v-for="(d, i) in pagedDiff" :key="d.contract_id + d.fee_type + d.other_source + i">
                            <td class="font-semibold">{{ d.contract_id }}</td>
                            <td class="capitalize">{{ d.fee_type }}</td>
                            <td>{{ d.source_of_record }}</td>
                            <td class="num">{{ money(d.amount_of_record) }}</td>
                            <td>{{ d.other_source }}</td>
                            <td class="num">{{ money(d.other_amount) }}</td>
                            <td class="num font-semibold" :class="d.difference ? 'text-amber-700 dark:text-amber-300' : ''">{{ money(d.difference) }}</td>
                        </tr>
                        <tr v-if="!differences.length"><td colspan="7" class="maiic-empty">No difference between fee sources: where a loan's fees come from more than one source, the amounts agree, or no line has been superseded yet.</td></tr>
                    </tbody>
                </table>
            </div>
            <LocalPager v-model="diffPage" :total="differences.length"/>
        </div>

        <jet-modal :show="!!tie" max-width="lg" @close="tie = null">
            <form v-if="tie" class="p-6" @submit.prevent="submitTie">
                <h3 class="text-lg font-semibold text-gray-900">Tie the fee line to its loan</h3>
                <p class="mt-1 text-sm text-gray-600">{{ money(tie.amount) }} on GL {{ tie.gl_code }}, {{ tie.transaction_date }}, batch {{ tie.batch }}. The line then waits for its classification and review like any other fee line.</p>
                <label class="mt-4 block"><span class="maiic-flabel">Loan (contract or account number)</span>
                    <input v-model="form.contract_id" class="maiic-input" required maxlength="60"/>
                    <span v-if="tie.loans.length" class="mt-1 block text-xs text-gray-500">Loans in the batch: {{ tie.loans.join(', ') }}</span>
                </label>
                <label class="mt-3 block"><span class="maiic-flabel">Reason (written to the audit log)</span>
                    <textarea v-model="form.reason" rows="3" class="maiic-input" minlength="10" maxlength="500" required></textarea>
                </label>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="secondary-btn" :disabled="saving" @click="tie = null">Cancel</button>
                    <button type="submit" class="primary-btn" :disabled="saving || !form.contract_id.trim() || form.reason.trim().length < 10">{{ saving ? 'Tying...' : 'Tie to the loan' }}</button>
                </div>
            </form>
        </jet-modal>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import JetModal from '@/Jetstream/Modal.vue'
import LocalPager from '@/Components/Data/LocalPager.vue'
import { promptReason } from '@/Components/Maiic/promptReason.js'

const props = defineProps({
    held: { type: Array, default: () => [] },
    differences: { type: Array, default: () => [] },
    canGovern: { type: Boolean, default: false },
})

const tab = ref(props.held.length || !props.differences.length ? 'held' : 'differences')
const tabs = computed(() => [
    { key: 'held', label: 'Held GL fee lines', count: props.held.length },
    { key: 'differences', label: 'Source differences', count: props.differences.length },
])
const heldPage = ref(1)
const diffPage = ref(1)
const pagedHeld = computed(() => props.held.slice((heldPage.value - 1) * 15, heldPage.value * 15))
const pagedDiff = computed(() => props.differences.slice((diffPage.value - 1) * 15, diffPage.value * 15))

const tie = ref(null)
const saving = ref(false)
const form = ref({ contract_id: '', reason: '' })
async function notThisLoan(h) {
    const reason = await promptReason({ title: 'Not the fee of loan ' + h.candidate_contract_id, message: 'Say why this posting is not the fee of that loan. It stays held for the right loan, and that loan is no longer stopped by it. The reason is kept in the audit trail.', confirmLabel: 'Record it' })
    if (!reason) return
    router.post(route('eir-fee-classification.dismiss-candidate', h.id), { contract_id: h.candidate_contract_id, reason }, { preserveScroll: true })
}
function openTie(h) {
    tie.value = h
    form.value = { contract_id: h.candidate_contract_id || '', reason: '' }
}
function submitTie() {
    saving.value = true
    router.post(route('eir-fee-classification.tie-held', tie.value.id), form.value, {
        preserveScroll: true,
        onSuccess: () => { tie.value = null },
        onFinish: () => { saving.value = false },
    })
}
// the service writes CODE: explanation; the code reads as a short label, the explanation on hover
const reasonLabel = (r) => {
    const [code, ...rest] = String(r || '').split(':')
    const text = rest.join(':').trim()
    if (!text) return code
    return text.split(';')[0].replace(/\s*\(.*?\)\s*/g, ' ').trim().replace(/^./, c => c.toUpperCase())
}
const money = (v) => v === null || v === undefined ? '-' : Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
</script>
