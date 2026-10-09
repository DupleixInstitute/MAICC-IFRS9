<template>
  <app-layout title="Take-on Schedules" description="Tamanda's workbook landed, not typed: each block with its mapping, confidence, tick and fees, the gate results, and the build of the take-on population (spec v4 section 6.9)">
    <template #actions>
      <button v-if="canGovern && load" @click="build" class="primary-btn text-sm">Build the take-on population</button>
    </template>

    <div class="space-y-6">
      <div class="rounded-lg border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900 dark:border-teal-800 dark:bg-teal-900/30 dark:text-teal-100">
        <span class="font-semibold">Basis in force:</span> {{ basisInForce }}. Where a facility has a mapped block and its fees, the EIR is solved from origination and rolled to 31 July 2024; where it has not, the loan starts at its take-on balance and is flagged. A blank fee means no such fee; a zero means known to be nil. Every figure traces to the sheet and cell it came from.
      </div>

      <div v-if="load" class="grid grid-cols-2 gap-4 md:grid-cols-6">
        <div class="maiic-kpi" style="--accent:#0d9488"><div class="maiic-kpi-label">Blocks</div><div class="maiic-kpi-value">{{ load.summary.blocks || 0 }}</div></div>
        <div class="maiic-kpi" style="--accent:#16a34a"><div class="maiic-kpi-label">Mapped</div><div class="maiic-kpi-value">{{ load.summary.mapped || 0 }}</div></div>
        <div class="maiic-kpi" style="--accent:#d97706"><div class="maiic-kpi-label">Flagged</div><div class="maiic-kpi-value">{{ load.summary.flagged || 0 }}</div></div>
        <div class="maiic-kpi" style="--accent:#64748b"><div class="maiic-kpi-label">Not matched</div><div class="maiic-kpi-value">{{ load.summary.not_matched || 0 }}</div></div>
        <div class="maiic-kpi" style="--accent:#dc2626"><div class="maiic-kpi-label">Refused</div><div class="maiic-kpi-value">{{ load.summary.refused || 0 }}</div></div>
        <div class="maiic-kpi" style="--accent:#d97706"><div class="maiic-kpi-label">No fee row</div><div class="maiic-kpi-value">{{ load.summary.no_fee_row || 0 }}</div></div>
      </div>

      <div class="maiic-panel">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 p-5 dark:border-slate-700">
          <div>
            <h3 class="text-base font-bold">The workbook</h3>
            <p v-if="load" class="text-sm text-gray-500">{{ load.pack }} · {{ load.status }} · landed {{ load.loaded_at }}</p>
            <p v-else class="text-sm text-gray-500">Nothing landed yet. The bootstrap lands the committed copy; a returned workbook is uploaded here beside it.</p>
          </div>
          <form v-if="canGovern" @submit.prevent="upload" class="flex flex-wrap items-end gap-2 text-xs">
            <label><span class="maiic-flabel">Original (as received)</span><input type="file" accept=".xlsx" @change="files.original = $event.target.files[0]"/></label>
            <label><span class="maiic-flabel">Mapping with fees</span><input type="file" accept=".xlsx" @change="files.mapping = $event.target.files[0]"/></label>
            <button type="submit" class="secondary-btn text-sm" :disabled="!files.original || !files.mapping">Land</button>
          </form>
        </div>
        <div class="maiic-table-wrap">
          <table class="maiic-table">
            <thead><tr><th>#</th><th>Block</th><th>Facility</th><th class="num">Principal</th><th class="num">Rate %</th><th>Account</th><th>Confidence</th><th>Tick</th><th class="num">Fees</th><th>Status</th><th>Gates</th><th v-if="canGovern"></th></tr></thead>
            <tbody>
              <tr v-for="b in blocks" :key="b.id" class="align-top">
                <td class="font-mono text-xs">{{ b.block_no }}<br><span class="text-gray-400">row {{ b.sheet_row }}</span></td>
                <td>{{ b.title }}<span v-if="b.restructured" class="ml-1 maiic-badge maiic-badge-gold">restructured</span><br><span class="text-xs text-gray-500">{{ b.lines }} lines</span></td>
                <td>{{ b.facility_name }}<br><span class="text-xs text-gray-500">{{ b.value_date }} to {{ b.maturity_date }}</span></td>
                <td class="num">{{ fmt(b.principal) }}</td>
                <td class="num">{{ b.rate != null ? b.rate.toFixed(2) : '-' }}</td>
                <td class="font-mono text-xs">{{ b.account || '-' }}<span v-if="b.corrected_account" class="block text-amber-700">was {{ b.proposed_account }}</span></td>
                <td>{{ b.confidence || '' }}</td>
                <td>{{ b.confirmed || '' }}</td>
                <td class="num">{{ b.total_fees != null ? fmt(b.total_fees) : '' }}<span v-if="b.total_fees == null" class="text-xs text-amber-700">blank</span></td>
                <td><span class="maiic-badge" :class="b.status === 'MAPPED' ? 'maiic-badge-green' : (b.status === 'REFUSED' ? 'maiic-badge-red' : (b.status === 'FLAGGED' ? 'maiic-badge-gold' : 'maiic-badge-grey'))">{{ b.status }}</span></td>
                <td class="text-xs">
                  <div v-for="(r, i) in (b.gates.refusals || [])" :key="'r' + i" class="text-red-700 dark:text-red-300">{{ r }}</div>
                  <div v-for="(f, i) in (b.gates.flags || [])" :key="'f' + i" class="text-amber-700 dark:text-amber-300">{{ f }}</div>
                </td>
                <td v-if="canGovern" class="whitespace-nowrap"><button @click="edit = JSON.parse(JSON.stringify(b))" class="secondary-btn text-xs">Confirm / fees</button></td>
              </tr>
              <tr v-if="!blocks.length"><td colspan="12" class="maiic-empty">No blocks landed.</td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="maiic-panel">
        <div class="border-b border-gray-200 p-5 dark:border-slate-700"><h3 class="text-base font-bold">The take-on population</h3><p class="text-sm text-gray-500">The accounts E-Banker migrated on 31 July 2024 (the 301 principal, 303 opening interest and 305 opening recovery legs), each with the basis it was built on</p></div>
        <div class="maiic-table-wrap">
          <table class="maiic-table">
            <thead><tr><th>Account</th><th>Basis</th><th>Origination</th><th class="num">Original principal</th><th class="num">Rate %</th><th class="num">Fees</th><th class="num">Take-on principal</th><th class="num">Opening recovery</th><th class="num">Schedule balance 31 Jul 2024</th><th class="num">Difference</th><th class="num">EIR from origination</th><th class="num">Amortised cost 31 Jul 2024</th><th class="num">Take-on balance</th><th class="num">Difference</th><th class="num">Schedule lines</th><th>Flags</th></tr></thead>
            <tbody>
              <tr v-for="c in population" :key="c.account">
                <td class="font-mono text-xs">{{ c.account }}</td>
                <td><span class="maiic-badge" :class="c.basis === 'RECOMPUTED' ? 'maiic-badge-green' : (c.basis === 'REFUSED' ? 'maiic-badge-red' : 'maiic-badge-grey')">{{ c.basis }}</span></td>
                <td class="text-xs">{{ c.origination_date || '-' }}</td>
                <td class="num">{{ c.original_principal != null ? fmt(c.original_principal) : '-' }}</td>
                <td class="num">{{ c.contractual_rate != null ? c.contractual_rate.toFixed(2) : '-' }}</td>
                <td class="num">{{ c.fees_total != null ? fmt(c.fees_total) : '' }}</td>
                <td class="num">{{ fmt(c.takeon_posting) }}</td>
                <td class="num">{{ fmt(c.takeon_opening_recovery) }}</td>
                <td class="num">{{ c.schedule_balance_at_takeon != null ? fmt(c.schedule_balance_at_takeon) : '-' }}</td>
                <td class="num" :class="c.difference_at_takeon > 0 ? 'text-red-700' : (c.difference_at_takeon < 0 ? 'text-teal-700' : '')">{{ c.difference_at_takeon != null ? fmt(c.difference_at_takeon) : '-' }}</td>
                <td class="num">{{ c.recomputed_eir != null ? (c.recomputed_eir * 100).toFixed(4) + '%' : '-' }}</td>
                <td class="num">{{ c.recomputed_amortised_cost != null ? fmt(c.recomputed_amortised_cost) : '-' }}</td>
                <td class="num">{{ c.takeon_balance != null ? fmt(c.takeon_balance) : '-' }}</td>
                <td class="num" :class="c.recomputed_difference > 0 ? 'text-red-700' : (c.recomputed_difference < 0 ? 'text-teal-700' : '')">{{ c.recomputed_difference != null ? fmt(c.recomputed_difference) : '-' }}</td>
                <td class="num">{{ c.schedule_lines_written || '-' }}</td>
                <td class="text-xs text-amber-700 dark:text-amber-300">{{ c.flags.join('; ') }}</td>
              </tr>
              <tr v-if="!population.length"><td colspan="16" class="maiic-empty">Not built yet.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Confirm a mapping or enter fees -->
    <div v-if="edit" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-900/50" @click="edit = null"></div>
      <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-800">
        <h3 class="text-lg font-bold">Block {{ edit.block_no }}: {{ edit.title }}</h3>
        <p class="text-xs text-gray-500">The entry here counts over the workbook's and is written to the audit log.</p>
        <div class="mt-4 space-y-3 text-sm">
          <div class="grid grid-cols-2 gap-3">
            <label><span class="maiic-flabel">Mapping correct?</span><select v-model="edit.confirmed" class="maiic-select"><option :value="null">not yet</option><option value="Y">Y: {{ edit.proposed_account }}</option><option value="N">N: another account</option></select></label>
            <label v-if="edit.confirmed === 'N'"><span class="maiic-flabel">Correct account</span><input v-model="edit.corrected_account" class="maiic-input" placeholder="000104420000005"/></label>
          </div>
          <label><span class="maiic-flabel">Comment</span><input v-model="edit.comment" class="maiic-input"/></label>
          <button @click="confirm" class="secondary-btn text-sm" :disabled="!edit.confirmed">Save the mapping</button>
          <hr class="dark:border-slate-700"/>
          <div class="grid grid-cols-3 gap-3">
            <label><span class="maiic-flabel">Arrangement fee</span><input v-model="edit.arrangement_fee" type="number" step="0.01" class="maiic-input"/></label>
            <label><span class="maiic-flabel">Legal fees</span><input v-model="edit.legal_fees" type="number" step="0.01" class="maiic-input"/></label>
            <label><span class="maiic-flabel">Other integral fees</span><input v-model="edit.other_fees" type="number" step="0.01" class="maiic-input"/></label>
            <label><span class="maiic-flabel">Date charged</span><input v-model="edit.fee_date" type="date" class="maiic-input"/></label>
            <label><span class="maiic-flabel">Deducted from payout?</span><select v-model="edit.fee_deducted" class="maiic-select"><option :value="null">-</option><option value="Y">Y</option><option value="N">N</option></select></label>
            <label><span class="maiic-flabel">Source</span><input v-model="edit.fee_source" class="maiic-input" placeholder="offer letter ref or receipt"/></label>
          </div>
          <div class="flex justify-end gap-2"><button @click="edit = null" class="secondary-btn text-sm">Close</button><button @click="saveFees" class="primary-btn text-sm">Save the fees</button></div>
        </div>
      </div>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'

export default {
  components: { AppLayout },
  props: { load: Object, blocks: Array, population: Array, basisInForce: String, canGovern: Boolean },
  data() {
    return { files: { original: null, mapping: null }, edit: null }
  },
  methods: {
    fmt(v) { return v == null ? '' : Number(v).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
    upload() {
      router.post(route('eir-takeon.upload'), { original: this.files.original, mapping: this.files.mapping }, { forceFormData: true })
    },
    build() {
      if (!confirm('Build the take-on population under the basis in force? The previous build is replaced.')) return
      router.post(route('eir-takeon.build'), {}, { preserveScroll: true })
    },
    confirm() {
      router.post(route('eir-takeon.confirm', this.edit.id), { confirmed: this.edit.confirmed, corrected_account: this.edit.corrected_account, comment: this.edit.comment }, { preserveScroll: true, onSuccess: () => { this.edit = null } })
    },
    saveFees() {
      const f = this.edit
      router.post(route('eir-takeon.fees', f.id), { arrangement_fee: f.arrangement_fee, legal_fees: f.legal_fees, other_fees: f.other_fees, fee_date: f.fee_date, fee_deducted: f.fee_deducted, fee_source: f.fee_source }, { preserveScroll: true, onSuccess: () => { this.edit = null } })
    },
  },
}
</script>
