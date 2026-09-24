<template>
  <app-layout>
    <template #header>
      <div>
        <h2 class="font-semibold text-xl text-gray-800">Governance Centre</h2>
        <p class="mt-1 text-sm text-gray-600">Every calculation convention the EIR engine uses, as a setting with options, an effective date, a proposer and an approver</p>
      </div>
    </template>

    <div class="max-w-7xl mx-auto space-y-6">
      <div class="bg-maiic-50 border border-maiic-200 rounded-lg p-4 text-sm text-maiic-900">
        A change takes effect from its effective date forward only, and only once a second person approves it. A month already run keeps the settings it was run under. Nothing here is written in code: if a setting has no approved value, the calculation that needs it stops and says so.
      </div>
      <div v-if="flashSuccess" class="rounded-md bg-maiic-100 border border-maiic-200 px-4 py-3 text-sm text-maiic-900">{{ flashSuccess }}</div>
      <div v-if="errorMessage && !showModal" class="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">{{ errorMessage }}</div>

      <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-6 border-b">
          <h3 class="text-lg font-semibold">Settings in force on {{ asOf }}</h3>
          <p class="text-sm text-gray-500">{{ settings.length }} settings; {{ pendingCount }} awaiting approval</p>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
            <thead>
              <tr><th v-for="h in ['Setting', 'Value in force', 'Options', 'Proposed and upcoming', 'Actions']" :key="h" class="th">{{ h }}</th></tr>
            </thead>
            <tbody class="divide-y">
              <template v-for="s in settings" :key="s.key">
                <tr class="hover:bg-maiic-50">
                  <td class="td max-w-md">
                    <div class="font-medium text-gray-900">{{ s.label }}</div>
                    <div class="text-[11px] font-mono text-gray-400">{{ s.key }}</div>
                    <p class="mt-1 text-xs text-gray-500">{{ s.description }}</p>
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
                        class="action mt-1 disabled:text-gray-400 disabled:no-underline"
                        :title="canApprove(r) ? 'Approve this change' : 'The person who proposed a change cannot approve it'"
                      >Approve</button>
                    </div>
                    <span v-if="!pendingRows(s).length" class="text-gray-400">None</span>
                  </td>
                  <td class="td whitespace-nowrap">
                    <button @click="openPropose(s)" class="action">Propose change</button>
                    <button @click="toggleHistory(s.key)" class="action">{{ expanded === s.key ? 'Hide history' : 'History' }}</button>
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
              <tr v-if="!settings.length"><td colspan="5" class="p-8 text-center text-gray-500">No governance settings are defined.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <jet-dialog-modal :show="showModal" max-width="2xl" @close="closeModal">
      <template #title>
        <div>
          <div class="font-semibold text-gray-900">Propose a change: {{ proposing?.label }}</div>
          <p class="mt-1 text-sm font-normal text-gray-500">{{ proposing?.description }}</p>
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

const blank = (asOf) => ({ key: '', value: '', effective_from: asOf, reason: '' })

export default {
  components: { AppLayout, JetDialogModal },
  props: {
    settings: Array,
    asOf: String,
    userId: Number,
    adminOverride: { type: Boolean, default: false },
    errors: { type: Object, default: () => ({}) },
  },
  data() {
    return { showModal: false, proposing: null, processing: false, expanded: null, form: blank(this.asOf) }
  },
  computed: {
    flashSuccess() {
      return this.$page.props.flash?.success
    },
    errorMessage() {
      return this.errors.governance || Object.values(this.errors)[0] || null
    },
    pendingCount() {
      return this.settings.reduce((n, s) => n + s.rows.filter(r => r.state === 'PROPOSED').length, 0)
    },
  },
  methods: {
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
.primary-btn{@apply inline-flex px-4 py-2 rounded-md text-sm font-medium text-white bg-gradient-to-r from-maiic-600 to-maiic-600 disabled:opacity-50}
.secondary-btn{@apply inline-flex px-4 py-2 rounded-md text-sm font-medium bg-white border border-gray-300 text-gray-700}
.th{@apply px-4 py-3 bg-maiic-700 text-left text-[11px] font-bold text-white uppercase tracking-wider whitespace-nowrap}
.td{@apply px-4 py-2.5 align-top border-t border-gray-100}
.sub-th{@apply px-2 py-1 text-left text-[11px] font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap}
.sub-td{@apply px-2 py-1 align-top border-t border-gray-200}
tbody tr:nth-child(even){@apply bg-gray-50}
tbody tr:hover{@apply bg-maiic-50/60}
.badge-green{@apply inline-flex px-2 py-0.5 rounded-full text-xs bg-maiic-100 text-maiic-800}
.badge-blue{@apply inline-flex px-2 py-0.5 rounded-full text-xs bg-blue-100 text-blue-800}
.badge-yellow{@apply inline-flex px-2 py-0.5 rounded-full text-xs bg-amber-100 text-amber-800}
.badge-red{@apply inline-flex px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-800}
.badge-gray{@apply inline-flex px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-700}
.action{@apply mr-3 text-sm text-maiic-700 hover:underline}
</style>
