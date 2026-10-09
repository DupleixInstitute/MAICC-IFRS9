<template>
  <app-layout title="Take-on Schedules" description="The take-on workbook as loaded: each block with its account mapping, fees and checks, and the take-on population built from it">
    <template #actions>
      <button v-if="canGovern && load" @click="build" class="primary-btn" title="Build the take-on population under the basis in force; the previous build is replaced">Build the take-on population</button>
    </template>

    <div class="space-y-5">
      <details class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm text-gray-600">
        <summary class="cursor-pointer"><span class="font-semibold text-gray-800">Basis in force:</span> {{ basisInForce }}</summary>
        <p class="mt-1 pb-1">Where a facility has a mapped block and its fees, the EIR is solved from origination and rolled to 31 July 2024; where it has not, the loan starts at its take-on balance and is flagged. A blank fee means no such fee; a zero means known to be nil. Every figure keeps the sheet and cell it came from.</p>
      </details>

      <div v-if="load" class="grid grid-cols-3 gap-3 lg:grid-cols-6">
        <div class="maiic-kpi" style="--accent:#0d9488"><div class="maiic-kpi-label">Blocks</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ load.summary.blocks || 0 }}</div></div>
        <div class="maiic-kpi" style="--accent:#16a34a"><div class="maiic-kpi-label">Mapped</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ load.summary.mapped || 0 }}</div></div>
        <div class="maiic-kpi" style="--accent:#d97706"><div class="maiic-kpi-label">Flagged</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ load.summary.flagged || 0 }}</div></div>
        <div class="maiic-kpi" style="--accent:#64748b"><div class="maiic-kpi-label">Not matched</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ load.summary.not_matched || 0 }}</div></div>
        <div class="maiic-kpi" style="--accent:#dc2626"><div class="maiic-kpi-label">Refused</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ load.summary.refused || 0 }}</div></div>
        <div class="maiic-kpi" style="--accent:#d97706"><div class="maiic-kpi-label">No fee row</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ load.summary.no_fee_row || 0 }}</div></div>
      </div>

      <div>
        <div class="maiic-panel">
          <template v-if="tab === 'blocks'">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-3">
              <p class="text-sm text-gray-500"><span v-if="load">{{ load.pack }}, {{ load.status }}, loaded {{ String(load.loaded_at || '').slice(0, 16) }}</span><span v-else>No workbook has been loaded yet.</span></p>
              <input v-model="search.blocks" class="maiic-input md:w-72" placeholder="Search block, facility or account" @input="page.blocks = 1"/>
            </div>
            <details v-if="canGovern" class="border-b border-gray-200 px-5 py-2 text-sm text-gray-700" :open="!load">
              <summary class="cursor-pointer font-semibold text-maiic-800">Load a take-on workbook</summary>
              <p class="mt-1">Choose the take-on workbook exactly as received and the returned mapping workbook (account mapping and fees), both as .xlsx, then click Load.</p>
              <form @submit.prevent="upload" class="mt-2 flex flex-wrap items-end gap-3 pb-1">
                <label><span class="maiic-flabel">Original workbook (as received)</span><input type="file" accept=".xlsx" class="text-sm" @change="files.original = $event.target.files[0]"/></label>
                <label><span class="maiic-flabel">Mapping workbook with fees</span><input type="file" accept=".xlsx" class="text-sm" @change="files.mapping = $event.target.files[0]"/></label>
                <button type="submit" class="primary-btn" :disabled="!files.original || !files.mapping || uploading">{{ uploading ? 'Loading...' : 'Load' }}</button>
              </form>
            </details>
            <div class="maiic-table-wrap">
              <table class="maiic-table">
                <thead><tr><th>#</th><th>Block</th><th>Facility</th><th class="num">Principal</th><th class="num">Rate %</th><th>Account</th><th class="num">Fees</th><th>Status</th><th>Checks</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                  <template v-for="b in pageOf(filteredBlocks, 'blocks')" :key="b.id">
                  <tr class="align-top">
                    <td class="font-mono text-xs">{{ b.block_no }}<br><span class="text-gray-400">row {{ b.sheet_row }}</span></td>
                    <td class="min-w-[10rem]">{{ b.title }}<span v-if="b.restructured" class="ml-1 maiic-badge maiic-badge-gold">restructured</span><br><span class="text-xs text-gray-500">{{ b.lines }} lines</span></td>
                    <td class="min-w-[10rem]">{{ b.facility_name }}<br><span class="text-xs text-gray-500 whitespace-nowrap">{{ b.value_date }} to {{ b.maturity_date }}</span></td>
                    <td class="num">{{ fmt(b.principal) }}</td>
                    <td class="num">{{ b.rate != null ? b.rate.toFixed(2) : '-' }}</td>
                    <td class="font-mono text-xs">{{ b.account || '-' }}<span v-if="b.corrected_account" class="block text-amber-700">was {{ b.proposed_account }}</span>
                      <span class="block font-sans text-gray-500"><span v-if="b.confidence">{{ b.confidence }} confidence</span><span v-if="b.confirmed">, confirmed {{ b.confirmed }}</span></span></td>
                    <td class="num">{{ b.total_fees != null ? fmt(b.total_fees) : '' }}<span v-if="b.total_fees == null" class="text-xs text-amber-700">blank</span></td>
                    <td><span class="maiic-badge" :class="b.status === 'MAPPED' ? 'maiic-badge-green' : (b.status === 'REFUSED' ? 'maiic-badge-red' : (b.status === 'FLAGGED' ? 'maiic-badge-gold' : 'maiic-badge-grey'))">{{ b.status }}</span></td>
                    <td class="text-xs whitespace-nowrap">
                      <span v-if="(b.gates.refusals || []).length" class="maiic-badge maiic-badge-red mr-1">{{ b.gates.refusals.length }} refused</span>
                      <span v-if="(b.gates.flags || []).length" class="maiic-badge maiic-badge-gold">{{ b.gates.flags.length }} {{ b.gates.flags.length === 1 ? 'flag' : 'flags' }}</span>
                      <span v-if="!(b.gates.refusals || []).length && !(b.gates.flags || []).length" class="text-gray-400">None</span>
                    </td>
                    <td class="text-right whitespace-nowrap">
                      <button type="button" class="maiic-action maiic-action-view" :title="open === b.id ? 'Hide the checks' : 'Show the checks'" @click="open = open === b.id ? null : b.id">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/><path fill-rule="evenodd" d="M.66 10.59a1.65 1.65 0 010-1.18C2.1 5.74 5.73 3 10 3s7.9 2.74 9.34 6.41c.15.38.15.8 0 1.18C17.9 14.26 14.27 17 10 17S2.1 14.26.66 10.59zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                      </button>
                      <button v-if="canGovern" @click="edit = JSON.parse(JSON.stringify(b))" class="maiic-action maiic-action-edit ml-1" title="Confirm the mapping or enter the fees">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/></svg>
                      </button>
                    </td>
                  </tr>
                  <tr v-if="open === b.id" class="bg-gray-50">
                    <td colspan="10" class="!py-3 text-xs">
                      <div class="maiic-kpi-label">Checks on block {{ b.block_no }}</div>
                      <div v-for="(r, i) in (b.gates.refusals || [])" :key="'r' + i" class="text-red-700">Refused: {{ r }}</div>
                      <div v-for="(f, i) in (b.gates.flags || [])" :key="'f' + i" class="text-amber-700">Flag: {{ f }}</div>
                      <div v-if="!(b.gates.refusals || []).length && !(b.gates.flags || []).length" class="text-gray-400">No check raised anything on this block.</div>
                      <div v-if="b.comment" class="mt-1 text-gray-600">Comment: {{ b.comment }}</div>
                    </td>
                  </tr>
                  </template>
                  <tr v-if="!filteredBlocks.length"><td colspan="10" class="maiic-empty">{{ blocks.length ? 'No block matches the search.' : 'No blocks loaded. Open Load a take-on workbook above.' }}</td></tr>
                </tbody>
              </table>
            </div>
            <LocalPager v-model="page.blocks" :total="filteredBlocks.length"/>
          </template>

          <template v-else>
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-3">
              <p class="text-sm text-gray-500">The accounts E-Banker migrated on 31 July 2024 (the 301, 303 and 305 legs), each with the basis it was built on.</p>
              <input v-model="search.population" class="maiic-input md:w-72" placeholder="Search account or basis" @input="page.population = 1"/>
            </div>
            <div class="maiic-table-wrap">
              <table class="maiic-table">
                <thead><tr><th>Account</th><th>Basis</th><th>Origination</th><th class="num">Original principal</th><th class="num">Rate %</th><th class="num">Fees</th><th class="num">Take-on principal</th><th class="num">Opening recovery</th><th class="num">Schedule balance 31 Jul 2024</th><th class="num">Difference</th><th class="num">EIR from origination</th><th class="num">Amortised cost 31 Jul 2024</th><th class="num">Take-on balance</th><th class="num">Difference</th><th class="num">Schedule lines</th><th>Flags</th></tr></thead>
                <tbody>
                  <tr v-for="c in pageOf(filteredPopulation, 'population')" :key="c.account">
                    <td class="font-mono text-xs">{{ c.account }}</td>
                    <td><span class="maiic-badge" :class="c.basis === 'RECOMPUTED' ? 'maiic-badge-green' : (c.basis === 'REFUSED' ? 'maiic-badge-red' : 'maiic-badge-grey')">{{ c.basis }}</span></td>
                    <td class="text-xs whitespace-nowrap">{{ c.origination_date || '-' }}</td>
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
                    <td class="min-w-[14rem] text-xs text-amber-700">{{ c.flags.join('; ') }}</td>
                  </tr>
                  <tr v-if="!filteredPopulation.length"><td colspan="16" class="maiic-empty">{{ population.length ? 'No account matches the search.' : 'Not built yet. Load the workbook, then use Build the take-on population (top right).' }}</td></tr>
                </tbody>
              </table>
            </div>
            <LocalPager v-model="page.population" :total="filteredPopulation.length"/>
          </template>
        </div>
      </div>
    </div>

    <!-- Confirm a mapping or enter fees -->
    <div v-if="edit" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-900/50" @click="edit = null"></div>
      <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
        <h3 class="text-lg font-bold">Block {{ edit.block_no }}: {{ edit.title }}</h3>
        <p class="text-xs text-gray-500">What you enter here replaces the workbook's entry and is written to the audit log.</p>
        <div class="mt-4 space-y-3 text-sm">
          <div class="grid grid-cols-2 gap-3">
            <label><span class="maiic-flabel">Mapping correct?</span><select v-model="edit.confirmed" class="maiic-select"><option :value="null">Not yet</option><option value="Y">Y: {{ edit.proposed_account }}</option><option value="N">N: another account</option></select></label>
            <label v-if="edit.confirmed === 'N'"><span class="maiic-flabel">Correct account</span><input v-model="edit.corrected_account" class="maiic-input" placeholder="000104420000005"/></label>
          </div>
          <label class="block"><span class="maiic-flabel">Comment</span><input v-model="edit.comment" class="maiic-input"/></label>
          <button @click="confirm" class="secondary-btn text-sm" :disabled="!edit.confirmed">Save the mapping</button>
          <hr/>
          <div class="grid grid-cols-3 gap-3">
            <label><span class="maiic-flabel">Arrangement fee</span><input v-model="edit.arrangement_fee" type="number" step="0.01" class="maiic-input"/></label>
            <label><span class="maiic-flabel">Legal fees</span><input v-model="edit.legal_fees" type="number" step="0.01" class="maiic-input"/></label>
            <label><span class="maiic-flabel">Other integral fees</span><input v-model="edit.other_fees" type="number" step="0.01" class="maiic-input"/></label>
            <label><span class="maiic-flabel">Date charged</span><input v-model="edit.fee_date" type="date" class="maiic-input"/></label>
            <label><span class="maiic-flabel">Deducted from payout?</span><select v-model="edit.fee_deducted" class="maiic-select"><option :value="null">-</option><option value="Y">Y</option><option value="N">N</option></select></label>
            <label><span class="maiic-flabel">Source</span><input v-model="edit.fee_source" class="maiic-input" placeholder="Offer letter ref or receipt"/></label>
          </div>
          <div class="flex justify-end gap-2"><button @click="edit = null" class="secondary-btn text-sm">Close</button><button @click="saveFees" class="primary-btn text-sm">Save the fees</button></div>
        </div>
      </div>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import LocalPager from '@/Components/Data/LocalPager.vue'
import { router } from '@inertiajs/vue3'
import { confirmDialog } from '@/Components/confirmDialog'

export default {
  components: { AppLayout, LocalPager },
  props: { tab: { type: String, default: 'blocks' }, tabCounts: Object, load: Object, blocks: Array, population: Array, basisInForce: String, canGovern: Boolean },
  data() {
    return {
      files: { original: null, mapping: null }, edit: null, open: null, uploading: false,
      search: { blocks: '', population: '' }, page: { blocks: 1, population: 1 },
    }
  },
  computed: {
    filteredBlocks() {
      const s = this.search.blocks.trim().toLowerCase()
      return s ? this.blocks.filter(b => [b.block_no, b.title, b.facility_name, b.account, b.status].some(v => String(v ?? '').toLowerCase().includes(s))) : this.blocks
    },
    filteredPopulation() {
      const s = this.search.population.trim().toLowerCase()
      return s ? this.population.filter(c => [c.account, c.basis].some(v => String(v ?? '').toLowerCase().includes(s))) : this.population
    },
  },
  methods: {
    pageOf(rows, key) { const p = this.page[key]; return rows.slice((p - 1) * 15, p * 15) },
    fmt(v) { return v == null ? '' : Number(v).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
    upload() {
      this.uploading = true
      router.post(route('eir-takeon.upload'), { original: this.files.original, mapping: this.files.mapping }, { forceFormData: true, onFinish: () => { this.uploading = false } })
    },
    async build() {
      if (!(await confirmDialog({ title: 'Build the take-on population?', message: 'It is built under the basis in force and replaces the previous build.', confirmLabel: 'Build' }))) return
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
