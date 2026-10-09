<template>
  <app-layout>
    <template #header>
      <div>
        <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
          <span>EIR &amp; Revenue Recognition</span><span>/</span><span class="font-medium text-maiic-700">Governance Centre</span>
        </div>
        <h2 class="font-semibold text-xl text-gray-800">Governance Centre</h2>
        <p class="mt-1 text-sm text-gray-600">Every calculation setting the engines use, with its options, effective date, proposer and approver</p>
      </div>
    </template>

    <div class="w-full space-y-6">
      <div v-if="errorMessage && !showModal" class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
        <font-awesome-icon icon="exclamation-circle" class="mt-0.5" />
        <div><div class="font-semibold">The change was not saved</div><div>{{ errorMessage }}</div></div>
      </div>

      <div>
        <KpiRow :cards="cards" />
        <p class="mt-2 text-xs text-gray-500">A change applies from its effective date forward, once a second person approves it. A month already run keeps its settings; a setting with no approved value stops the calculation that needs it.</p>
      </div>

      <div>
      <PageTabs v-model="area" :tabs="areaTabs" flush />
      <div class="bg-white rounded-b-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b">
          <h3 class="font-semibold text-gray-900">{{ currentArea.label }}<span class="font-normal text-gray-500">, in force on {{ asOf }}</span></h3>
          <p class="text-xs text-gray-500">{{ currentArea.description }}</p>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead>
              <tr><th v-for="h in ['Setting', 'Value in force', 'Options', 'Proposed and upcoming', 'Actions']" :key="h" class="th">{{ h }}</th></tr>
            </thead>
            <tbody class="divide-y">
              <template v-for="s in visibleSettings" :key="s.key">
                <tr class="hover:bg-maiic-50">
                  <td class="td max-w-md">
                    <div class="font-medium text-gray-900">{{ s.label }}</div>
                    <div class="text-[11px] font-mono text-gray-400">{{ s.key }}</div>
                    <p class="mt-1 text-xs text-gray-500">{{ plain(s.description) }}</p>
                  </td>
                  <td class="td">
                    <template v-if="s.in_force">
                      <div class="font-medium text-gray-900">{{ s.in_force.value }}</div>
                      <div class="text-xs text-gray-500">From {{ s.in_force.effective_from }}</div>
                      <div class="text-xs text-gray-500">{{ s.in_force.approver ? 'Approved by ' + s.in_force.approver : 'Dupleix recommended default' }}</div>
                    </template>
                    <span v-else class="badge-red">No approved value: the calculation stops</span>
                  </td>
                  <td class="td text-xs text-gray-600">
                    <div v-for="o in s.options" :key="o" :class="s.in_force && o === s.in_force.value ? 'font-semibold text-maiic-800' : ''">
                      {{ o }}<span v-if="o === s.default" class="text-gray-400"> (recommended)</span>
                      <template v-if="s.key === 'fli_transmission_method' && cardFor(o)">
                        <span class="ml-1 maiic-badge" :class="cardFor(o).available ? 'maiic-badge-green' : 'maiic-badge-gold'">{{ cardFor(o).available ? 'preconditions met' : 'not yet available' }}</span>
                        <button type="button" @click="openCard = openCard === o ? null : o" class="ml-1 text-xs text-sky-700 underline dark:text-sky-300">{{ openCard === o ? 'hide the card' : 'method card' }}</button>
                        <div v-if="openCard === o" class="mt-1 mb-2 rounded border border-gray-200 bg-gray-50 p-3 text-xs dark:border-slate-700 dark:bg-slate-900/60">
                          <p class="text-gray-700 dark:text-slate-300">{{ plain(cardFor(o).what) }}</p>
                          <p class="mt-1 font-mono">{{ cardFor(o).formula }}</p>
                          <ul class="mt-1 text-gray-500"><li v-for="(v, k) in cardFor(o).symbols" :key="k">{{ k }}: {{ v }}</li></ul>
                          <ul class="mt-1 list-disc pl-4"><li v-for="(i, n) in cardFor(o).implies" :key="n">{{ i }}</li></ul>
                          <div class="mt-1 space-y-0.5"><div v-for="(pc, n) in cardFor(o).preconditions" :key="n"><span :class="pc.met ? 'text-maiic-700' : 'text-red-700'">{{ pc.met ? '✓' : '✗' }}</span> {{ pc.name }}: <span class="text-gray-500">{{ pc.figure }}</span></div></div>
                          <p v-if="cardFor(o).example" class="mt-1 text-gray-600 dark:text-slate-400">Example on {{ cardFor(o).example.contract_id }} ({{ cardFor(o).example.customer_name }}): {{ cardFor(o).example.steps.join('; ') }}</p>
                          <p v-if="!cardFor(o).available" class="mt-1 text-amber-700">This method cannot be selected until every precondition above is met.</p>
                        </div>
                      </template>
                    </div>
                  </td>
                  <td class="td text-xs">
                    <div v-for="r in pendingRows(s)" :key="r.id" class="mb-2">
                      <span :class="r.state === 'PROPOSED' ? 'badge-yellow' : 'badge-blue'">{{ r.state === 'PROPOSED' ? 'Awaiting approval' : 'Approved, applies from ' + r.effective_from }}</span>
                      <div class="mt-1 font-medium text-gray-800">{{ r.value }}<span v-if="r.state === 'PROPOSED'"> from {{ r.effective_from }}</span></div>
                      <div class="text-gray-500">Proposed by {{ r.proposer || 'unknown' }}<span v-if="r.approver">, approved by {{ r.approver }}</span></div>
                      <div class="text-gray-500 italic">{{ r.reason }}</div>
                      <button
                        v-if="r.state === 'PROPOSED'"
                        @click="approve(r)"
                        :disabled="!canApprove(r) || processing"
                        class="mt-1 inline-flex items-center gap-1 rounded-md bg-maiic-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-maiic-700 disabled:cursor-not-allowed disabled:bg-gray-300"
                        :title="canApprove(r) ? 'Approve this change' : 'The person who proposed a change cannot approve it'"
                      >Approve</button>
                    </div>
                    <span v-if="!pendingRows(s).length" class="text-gray-400">None</span>
                  </td>
                  <td class="td whitespace-nowrap">
                    <div class="flex items-center gap-1.5">
                      <button type="button" @click="openPropose(s)" class="maiic-action maiic-action-edit" title="Propose a change"><font-awesome-icon icon="pen" /></button>
                      <button type="button" @click="toggleHistory(s.key)" class="maiic-action" :class="expanded === s.key ? 'maiic-action-view' : 'maiic-action-neutral'" :title="expanded === s.key ? 'Hide history' : 'Show every value and the audit copies'"><font-awesome-icon icon="history" /></button>
                    </div>
                  </td>
                </tr>
                <tr v-if="expanded === s.key">
                  <td colspan="5" class="td bg-gray-50">
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Every value of {{ s.label }}</h4>
                    <table class="min-w-full text-xs">
                      <thead>
                        <tr><th v-for="h in ['State', 'Value', 'Effective from', 'Proposed by', 'Approved by', 'Reason']" :key="h" class="sub-th">{{ h }}</th></tr>
                      </thead>
                      <tbody>
                        <tr v-for="r in s.rows" :key="'row-' + r.id">
                          <td class="sub-td"><span :class="stateClass(r.state)">{{ stateLabel(r.state) }}</span></td>
                          <td class="sub-td">{{ r.value }}</td>
                          <td class="sub-td">{{ r.effective_from }}</td>
                          <td class="sub-td">{{ r.proposer || (r.approver ? 'unknown' : 'Dupleix (seeded)') }}</td>
                          <td class="sub-td">{{ r.approver || (r.status === 'APPROVED' ? 'Seeded default' : 'Not yet') }}<span v-if="r.approved_at" class="text-gray-400"> {{ r.approved_at }}</span></td>
                          <td class="sub-td">{{ r.reason }}</td>
                        </tr>
                      </tbody>
                    </table>
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mt-4 mb-2">Superseded values (audit copies)</h4>
                    <p v-if="!s.history.length" class="text-xs text-gray-400">No value has been superseded yet.</p>
                    <table v-else class="min-w-full text-xs">
                      <thead>
                        <tr><th v-for="h in ['Value', 'Was effective from', 'Superseded on', 'By', 'Replaced by row']" :key="h" class="sub-th">{{ h }}</th></tr>
                      </thead>
                      <tbody>
                        <tr v-for="h in s.history" :key="'hist-' + h.id">
                          <td class="sub-td">{{ h.value }}</td>
                          <td class="sub-td">{{ h.effective_from }}</td>
                          <td class="sub-td">{{ h.superseded_at }}</td>
                          <td class="sub-td">{{ h.superseded_by || 'unknown' }}</td>
                          <td class="sub-td">#{{ h.superseded_by_setting_id }}</td>
                        </tr>
                      </tbody>
                    </table>
                  </td>
                </tr>
              </template>
              <tr v-if="!visibleSettings.length"><td colspan="5" class="p-10 text-center text-sm text-gray-500">{{ area === 'pending' ? 'Nothing is awaiting approval.' : 'No settings in this area.' }}</td></tr>
            </tbody>
          </table>
        </div>
      </div>
      </div>
    </div>

    <jet-dialog-modal :show="showModal" max-width="2xl" @close="closeModal">
      <template #title>
        <div>
          <div class="font-semibold text-gray-900">Propose a change: {{ proposing?.label }}</div>
          <p class="mt-1 text-sm font-normal text-gray-500">{{ plain(proposing?.description) }}</p>
        </div>
      </template>
      <template #content>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <label class="field sm:col-span-2">New value
            <select v-model="form.value" class="form-input mt-1">
              <option value="">Choose an option</option>
              <option v-for="o in proposing?.options || []" :key="o" :value="o" :disabled="isInForce(o)">{{ o }}{{ isInForce(o) ? ' (in force now)' : '' }}</option>
            </select>
          </label>
          <label class="field">Effective from
            <input v-model="form.effective_from" type="date" class="form-input mt-1">
            <span class="block mt-1 text-xs font-normal text-gray-500">Must be later than the last approved change. The new value applies from this date forward only.</span>
          </label>
          <label class="field sm:col-span-2">Reason
            <textarea v-model="form.reason" rows="3" maxlength="500" class="form-input mt-1" placeholder="Why the convention is changing, in plain words; the auditor reads this"></textarea>
            <span class="block mt-1 text-xs font-normal text-gray-500">At least 10 characters; recorded in the audit log with the old and the new value.</span>
          </label>
        </div>
        <div v-if="errorMessage" class="mt-4 rounded-md bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">{{ errorMessage }}</div>
      </template>
      <template #footer>
        <button @click="closeModal" class="secondary-btn mr-2">Cancel</button>
        <button @click="save" :disabled="processing || !form.value || !form.effective_from || form.reason.trim().length < 10" class="primary-btn">{{ processing ? 'Saving...' : 'Propose change' }}</button>
      </template>
    </jet-dialog-modal>

    <teleport to="head"><title>Governance Centre</title></teleport>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetDialogModal from '@/Jetstream/DialogModal.vue'
import KpiRow from '@/Components/Maiic/KpiRow.vue'
import PageTabs from '@/Components/Maiic/PageTabs.vue'

// Setting areas for the tabs (display grouping only; the engine reads keys).
const AREAS = [
  { key: 'interest', label: 'Interest & rates', description: 'How rates, resets, moratoria and day counts feed the effective interest rate.',
    keys: ['plr_mid_period', 'reset_trigger', 'moratorium_capitalisation', 'rate_source_precedence', 'margin_basis', 'stage3_interest_basis', 'day_count', 'period_rate_basis', 'rate_change_classification', 'modification_threshold', 'partly_drawn_interest_basis'] },
  { key: 'cashflows', label: 'Cash flows', description: 'Which records and schedules count as the contractual and expected cash flows.',
    keys: ['cash_source', 'manual_policy_handling', 'counter_reset_handling', 'contractual_record', 'expected_cashflow_basis', 'schedule_approval_control'] },
  { key: 'data', label: 'Data sources', description: 'Where the loan book, history and macro data come from, and which source wins.',
    keys: ['history_before_dec_2025', 'ebanker_feed_route', 'loan_book_build_method', 'takeon_history_basis', 'macro_source_precedence'] },
  { key: 'accounting', label: 'Accounting & controls', description: 'Reconciliation tolerance, journals, exports, special balances and maker-checker.',
    keys: ['recon_tolerance', 'trueup_gl_account', 'auditor_export_format', 'fee_reclass_journal', 'historic_materiality_assessment', 'keyman_insurance_treatment', 'nascomex_preference_shares', 'maker_checker_admin_override'] },
  { key: 'staging', label: 'Staging', description: 'Days past due, Stage 3 triggers, rebuttals and cure periods.',
    keys: ['staging_rebuttal', 'dpd_basis', 'stage3_missed_instalments', 'stage_cure_months'] },
  { key: 'fli', label: 'Forward-looking', description: 'How the forward-looking adjustment is built and tested, and how scenarios are weighted.',
    keys: ['fli_adjustment_route', 'fli_transmission_method', 'fli_asset_correlation', 'fli_expected_sign_test', 'fli_r2_cutoff', 'fli_min_observations', 'fli_alpha', 'fli_normality_limits', 'scenario_weighting_method', 'scenario_minimum_count', 'scenario_weight_bounds', 'scenario_calibration_note', 'overlay_requires_approved_set'] },
  { key: 'megafarm', label: 'Mega Farm', description: 'Scope and probability of default for the Mega Farm programme.',
    keys: ['mega_farms_scope', 'megafarm_pd_method', 'megafarm_scalar_ceiling'] },
]
const KNOWN = AREAS.flatMap(a => a.keys)

const blank = (asOf) => ({ key: '', value: '', effective_from: asOf, reason: '' })

export default {
  components: { AppLayout, JetDialogModal, KpiRow, PageTabs },
  props: {
    settings: Array,
    asOf: String,
    userId: Number,
    adminOverride: { type: Boolean, default: false },
    methodCards: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
  },
  data() {
    return {
      area: 'interest', openCard: null, showModal: false, proposing: null, processing: false, expanded: null, form: blank(this.asOf) }
  },
  computed: {
    flashSuccess() {
      return this.$page.props.flash?.success
    },
    errorMessage() {
      return this.errors.governance || Object.values(this.errors)[0] || null
    },
    areas() {
      const list = AREAS.map(a => ({ ...a, settings: this.settings.filter(s => a.keys.includes(s.key)) }))
      const other = this.settings.filter(s => !KNOWN.includes(s.key))
      if (other.length) list.push({ key: 'other', label: 'Other', description: 'Settings not yet placed in an area.', settings: other })
      list.push({ key: 'pending', label: 'Awaiting approval', description: 'Every setting with a proposed change that a second person must approve.',
        settings: this.settings.filter(s => s.rows.some(r => r.state === 'PROPOSED')) })
      return list
    },
    areaTabs() {
      return this.areas.map(a => ({ key: a.key, label: a.label, count: a.settings.length }))
    },
    currentArea() {
      return this.areas.find(a => a.key === this.area) || this.areas[0]
    },
    visibleSettings() {
      return this.currentArea.settings
    },
    cards() {
      const noValue = this.settings.filter(s => !s.in_force).length
      const upcoming = this.settings.reduce((n, s) => n + s.rows.filter(r => r.state === 'UPCOMING').length, 0)
      return [
        { label: 'Settings', value: this.settings.length, sub: 'in the catalogue' },
        { label: 'In force', value: this.settings.length - noValue, sub: 'approved value today', accent: '#15803d' },
        { label: 'Awaiting approval', value: this.pendingCount, sub: 'proposed changes', accent: this.pendingCount ? '#d97706' : null },
        { label: 'Upcoming', value: upcoming, sub: 'approved, from a later date', accent: '#0284c7' },
        { label: 'No approved value', value: noValue, sub: 'calculations would stop', accent: noValue ? '#dc2626' : null, valueClass: noValue ? 'text-red-700' : '' },
      ]
    },
    pendingCount() {
      return this.settings.reduce((n, s) => n + s.rows.filter(r => r.state === 'PROPOSED').length, 0)
    },
  },
  methods: {
    // Specification references are for the build team, not the screen.
    plain(text) {
      return String(text || '')
        .replace(/\s*\((?:spec v\d+ section [\d.]+[^)]*|O\d+|decision D\d+)\)/g, '')
        .replace(/\s*Agreed as decision D\d+\./g, '')
        .replace(/\s*Open choice O\d+[^.]*\./g, '')
        .replace(/\s*Decided ([^(]*?) \(O\d+\):/g, ' Decided $1:')
        .trim()
    },
    cardFor(option) { return this.methodCards.find(c => c.key === option) || null },
    pendingRows(s) {
      return s.rows.filter(r => r.state === 'PROPOSED' || r.state === 'UPCOMING')
    },
    canApprove(r) {
      return this.adminOverride || (r.proposer_id !== null && r.proposer_id !== this.userId)
    },
    isInForce(option) {
      return Boolean(this.proposing?.in_force) && option === this.proposing.in_force.value
    },
    stateLabel(state) {
      return { IN_FORCE: 'In force', PAST: 'Past', UPCOMING: 'Upcoming', PROPOSED: 'Awaiting approval' }[state] || state
    },
    stateClass(state) {
      return state === 'IN_FORCE' ? 'badge-green' : state === 'PROPOSED' ? 'badge-yellow' : state === 'UPCOMING' ? 'badge-blue' : 'badge-gray'
    },
    toggleHistory(key) {
      this.expanded = this.expanded === key ? null : key
    },
    openPropose(s) {
      this.proposing = s
      this.form = { ...blank(this.asOf), key: s.key }
      this.showModal = true
    },
    closeModal() {
      if (this.processing) return
      this.showModal = false
      this.proposing = null
      this.form = blank(this.asOf)
    },
    save() {
      this.processing = true
      this.$inertia.post(this.route('eir-governance.propose'), this.form, {
        preserveScroll: true,
        onFinish: () => { this.processing = false },
        onSuccess: () => { this.showModal = false; this.proposing = null; this.form = blank(this.asOf) },
      })
    },
    approve(r) {
      if (!this.canApprove(r)) return
      this.processing = true
      this.$inertia.post(this.route('eir-governance.approve', r.id), {}, {
        preserveScroll: true,
        onFinish: () => { this.processing = false },
      })
    },
  },
}
</script>

<style scoped>
.field{@apply block text-sm font-medium text-gray-700}
.form-input{@apply block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-maiic-500}
.sub-th{@apply px-2 py-1 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap}
.sub-td{@apply px-2 py-1 align-top border-t border-gray-200}
.action{@apply mr-3 text-sm text-maiic-700 hover:underline}
</style>
