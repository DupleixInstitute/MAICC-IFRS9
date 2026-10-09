<template>
  <app-layout title="Manual Overlays" description="Judgement added on top of the forward-looking adjustment, each with a reason, an owner and an expiry, approved by a second person">
    <template #actions>
      <form @submit.prevent="go" class="flex items-center gap-2">
        <input v-model="view.period" type="month" class="maiic-input !w-44" aria-label="Period" required/>
        <button type="submit" class="secondary-btn text-sm">View period</button>
        <button v-if="canGovern" type="button" @click="showForm = !showForm" class="primary-btn text-sm">{{ showForm ? 'Close the form' : 'Propose an overlay' }}</button>
      </form>
    </template>

    <div class="space-y-4">
      <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="maiic-kpi !py-3" style="--accent:#d97706"><div class="maiic-kpi-label">In force for {{ period }}</div><div class="text-xl font-bold">{{ summary.in_force }}</div></div>
        <div class="maiic-kpi !py-3" style="--accent:#d97706"><div class="maiic-kpi-label">Awaiting a second person</div><div class="text-xl font-bold">{{ summary.proposed }}</div><div v-if="summary.proposed" class="text-xs text-amber-700">the scenario set cannot lock until these are approved or rejected</div></div>
        <div class="maiic-kpi !py-3" style="--accent:#d97706"><div class="maiic-kpi-label">ECL added by overlays</div><div class="text-xl font-bold">{{ fmt(summary.ecl_line) }}</div><div class="text-xs text-gray-500">over {{ summary.loans }} loans with a PD and an ECL</div></div>
        <div class="maiic-kpi !py-3" style="--accent:#d97706"><div class="maiic-kpi-label">Scenario set</div><div class="text-sm font-bold">{{ approvedSet ? (approvedSet.name + ' v' + approvedSet.version + ' (' + approvedSet.status + ')') : 'None approved for ' + period }}</div><div v-if="!approvedSet && rules.overlay_needs_set" class="text-xs text-red-700">the rule requires an approved set before an overlay can be proposed</div></div>
      </div>

      <div v-if="showForm && canGovern" class="maiic-panel p-5">
        <h3 class="font-semibold text-gray-900">Propose an overlay</h3>
        <p class="text-sm text-gray-500">A second person approves it before it reaches any loan. The adjustment is on the PD: +15 means every PD in scope is multiplied by 1.15; -10 by 0.90.</p>
        <form @submit.prevent="propose" class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
          <label class="text-sm"><span class="maiic-flabel">Reporting period</span><input v-model="form.reporting_period" type="month" class="maiic-input w-full" required/></label>
          <label class="text-sm"><span class="maiic-flabel">Scope</span>
            <select v-model="form.scope" class="maiic-select w-full"><option value="book">The whole book</option><option value="product_group">One product group</option><option value="contract">One contract</option></select></label>
          <label class="text-sm" v-if="form.scope !== 'book'"><span class="maiic-flabel">{{ form.scope === 'contract' ? 'Contract id' : 'Product group' }}</span>
            <select v-if="form.scope === 'product_group'" v-model="form.scope_value" class="maiic-select w-full" required><option value="" disabled>Choose</option><option v-for="g in productGroups" :key="g" :value="g">{{ g }}</option></select>
            <input v-else v-model="form.scope_value" type="text" class="maiic-input w-full" required placeholder="the contract id as the loan book carries it"/></label>
          <label class="text-sm"><span class="maiic-flabel">Adjustment to the PD, percent</span><input v-model="form.adjustment_pct" type="number" step="0.01" class="maiic-input w-full" required placeholder="+15 or -10"/></label>
          <label class="text-sm"><span class="maiic-flabel">In force until (expiry period)</span><input v-model="form.expiry_period" type="month" class="maiic-input w-full" required/></label>
          <label class="text-sm"><span class="maiic-flabel">Owner</span><select v-model="form.owner_id" class="maiic-select w-full"><option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option></select></label>
          <label class="text-sm md:col-span-3"><span class="maiic-flabel">Reason: the event the statistics cannot see</span><textarea v-model="form.reason" rows="2" class="maiic-input w-full" required></textarea></label>
          <label class="text-sm md:col-span-3"><span class="maiic-flabel">Evidence: a reference or the text</span><textarea v-model="form.evidence" rows="2" class="maiic-input w-full"></textarea></label>
          <div class="md:col-span-3 flex gap-2"><button type="submit" class="primary-btn text-sm">Propose</button><button type="button" @click="showForm = false" class="secondary-btn text-sm">Cancel</button></div>
        </form>
      </div>

      <div class="maiic-panel">
        <div class="border-b border-gray-200 px-5 py-4 dark:border-slate-700"><h3 class="font-semibold text-gray-900">Overlay register for {{ period }}</h3><p class="text-xs text-gray-500">{{ overlays.length }} overlay(s) whose life covers the period, newest first. The ECL line is what each adds to the booked ECL of its loans (Stage 3 adds nothing, it is already at 100 percent).</p></div>
        <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>#</th><th>Scope</th><th class="num">PD adjustment</th><th>Reason and evidence</th><th>Owner</th><th>Period to expiry</th><th>Status</th><th class="num">Loans in scope</th><th class="num">ECL line</th><th v-if="canGovern"></th></tr></thead>
          <tbody>
            <tr v-for="o in overlays" :key="o.id" :class="o.in_force ? 'font-semibold' : ''">
              <td class="font-mono text-xs">{{ o.id }}</td>
              <td>{{ scopeLabel(o) }}</td>
              <td class="num">{{ pct(o.adjustment) }}</td>
              <td class="text-xs"><div>{{ o.reason }}</div><div v-if="o.evidence" class="text-gray-500">Evidence: {{ o.evidence }}</div><div v-if="o.rejected_reason" class="text-red-700">Rejected: {{ o.rejected_reason }}</div></td>
              <td class="text-xs">{{ o.owner || '-' }}</td>
              <td class="text-xs">{{ o.reporting_period }} to {{ o.expiry_period }}</td>
              <td><span class="maiic-badge" :class="badge(o.status)">{{ statusLabel(o.status) }}</span><div class="text-xs text-gray-500">Proposed by {{ o.proposer || '-' }}<span v-if="o.approver || o.approver_label">, approved by {{ o.approver || o.approver_label }}</span><span v-if="o.rejecter">, rejected by {{ o.rejecter }}</span></div></td>
              <td class="num">{{ o.loans_in_scope }}</td>
              <td class="num">{{ fmt(o.ecl_line) }}</td>
              <td v-if="canGovern" class="whitespace-nowrap">
                <div class="flex justify-end gap-1.5">
                  <button v-if="o.status === 'PROPOSED'" type="button" @click="post('fli-overlays.approve', o.id)" class="maiic-action maiic-action-view" title="Approve (second person)"><font-awesome-icon icon="check" /></button>
                  <button v-if="o.status === 'PROPOSED'" type="button" @click="reject(o.id)" class="maiic-action maiic-action-delete" title="Reject"><font-awesome-icon icon="times-circle" /></button>
                  <button v-if="o.status === 'APPROVED'" type="button" @click="expire(o.id)" class="maiic-action maiic-action-edit" title="Expire now"><font-awesome-icon icon="calendar" /></button>
                </div>
              </td>
            </tr>
            <tr v-if="!overlays.length"><td colspan="10" class="maiic-empty">No overlay covers {{ period }}.<span v-if="canGovern"> Use <strong>Propose an overlay</strong> at the top right.</span> The register is read when the Governance Centre setting says "Manual overlay" or "Regression plus overlay".</td></tr>
          </tbody></table></div>
      </div>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import { promptReason } from '@/Components/Maiic/promptReason'

export default {
  components: { AppLayout },
  props: { period: String, overlays: Array, rules: Object, approvedSet: Object, summary: Object, periods: Array, productGroups: Array, users: Array, canGovern: Boolean },
  data() {
    const me = this.$page.props.user ? this.$page.props.user.id : null
    return { view: { period: this.period }, showForm: false, form: { reporting_period: this.period, scope: 'book', scope_value: '', adjustment_pct: '', expiry_period: this.period, owner_id: me, reason: '', evidence: '' } }
  },
  methods: {
    fmt(v) { return v == null ? '-' : Number(v).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
    pct(v) { const p = Number(v) * 100; return (p > 0 ? '+' : '') + p.toFixed(2) + '%' },
    scopeLabel(o) { return o.scope === 'book' ? 'The whole book' : (o.scope === 'product_group' ? 'Product group ' + o.scope_value : 'Contract ' + o.scope_value) },
    badge(s) { return s === 'APPROVED' ? 'maiic-badge-green' : (s === 'PROPOSED' ? 'maiic-badge-gold' : (s === 'REJECTED' ? 'maiic-badge-red' : 'maiic-badge-grey')) },
    go() { router.get(route('fli-overlays.index'), { period: this.view.period }) },
    post(name, id, data = {}) { router.post(route(name, id), data, { preserveScroll: true }) },
    propose() { router.post(route('fli-overlays.propose'), { ...this.form, scope_value: this.form.scope === 'book' ? null : this.form.scope_value }, { preserveScroll: true, onSuccess: (page) => { if (!(page.props.flash && page.props.flash.error)) this.showForm = false } }) },
    statusLabel(s) { return { APPROVED: 'Approved', PROPOSED: 'Proposed', REJECTED: 'Rejected', EXPIRED: 'Expired' }[s] || s },
    async reject(id) { const reason = await promptReason({ title: 'Reject this overlay?', message: 'The reason is written to the register and the audit log.', label: 'Why is it rejected?', confirmLabel: 'Reject', tone: 'danger' }); if (reason) this.post('fli-overlays.reject', id, { reason }) },
    async expire(id) { const reason = await promptReason({ title: 'Expire this overlay now?', message: 'It is withdrawn before its expiry period; the reason is written to the audit log.', label: 'Why is it withdrawn early?', confirmLabel: 'Expire now', tone: 'warning', required: false }); if (reason !== null) this.post('fli-overlays.expire', id, { reason }) },
  },
}
</script>
