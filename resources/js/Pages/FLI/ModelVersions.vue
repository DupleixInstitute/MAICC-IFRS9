<template>
  <app-layout title="Model Versions" description="Every version of the forward-looking model: the one in force for the month, when each took effect, who approved it and the months it governed">
    <template #actions>
      <select v-model="form.period" @change="go" class="maiic-input !w-40" aria-label="Month">
        <option v-for="p in periods" :key="p" :value="p">{{ monthLabel(p) }}</option>
      </select>
      <Link :href="route('fli-adjustments.index', { period })" class="secondary-btn text-sm">Fit a new version</Link>
      <Link :href="route('fli.external.index', { period })" class="secondary-btn text-sm">Enter an external model</Link>
    </template>

    <div class="space-y-4">
      <Notice v-if="inForce" id="version-in-force" tone="success">
        <div class="flex flex-wrap items-center gap-2">
          <span class="maiic-badge maiic-badge-solid-green">In force for {{ monthLabel(period) }}</span>
          <span class="font-semibold text-gray-900">Version {{ inForce.version }}: {{ inForce.statistic_code }} drives {{ inForce.proxy_code }}, {{ inForce.lag_months == 0 ? 'in the same month' : inForce.lag_months + ' months later' }}</span>
        </div>
        <div class="mt-1 text-xs text-gray-600">In force since {{ monthLabel(inForce.effective_from) }}, calibrated on data to {{ monthLabel(inForce.calibrated_period) }}; slope {{ n(inForce.slope, 6) }}, intercept {{ n(inForce.intercept, 4) }}<span v-if="inForce.r_squared">, R² {{ n(inForce.r_squared, 3) }}</span>. Cadence in force: {{ cadence || '-' }}.</div>
      </Notice>
      <Notice v-else id="no-version-in-force" tone="warning">
        <span class="font-semibold">No version is in force for {{ monthLabel(period) }}:</span> the PD holds. Fit one on Regression and approve it, or enter an externally calibrated model.
      </Notice>

      <div class="maiic-panel">
        <div class="border-b border-gray-200 px-5 py-3 dark:border-slate-700"><h3 class="font-semibold text-gray-900">All versions</h3><p class="text-xs text-gray-500">A version is never edited: a recalibration publishes a new one from its month forward. Retiring a version sends the months after it back to the version before it.</p></div>
        <div class="maiic-table-wrap"><table class="maiic-table">
          <thead><tr><th class="num">Version</th><th>Driver and credit-loss measure</th><th class="num">Lag</th><th class="num">Slope</th><th class="num">R²</th><th>Effective from</th><th>Source</th><th>Status</th><th>Approved by</th><th>Months governed</th><th v-if="canGovern"></th></tr></thead>
          <tbody>
            <tr v-for="v in paged" :key="v.id" :class="v.in_force ? 'bg-maiic-50 font-semibold dark:bg-maiic-900/20' : ''">
              <td class="num">{{ v.version }}</td>
              <td class="min-w-[14rem]"><div>{{ v.driver_name }}</div><div class="text-xs text-gray-500">{{ v.proxy_name }}</div></td>
              <td class="num">{{ v.lag_months }}</td>
              <td class="num" :title="'Intercept ' + n(v.intercept, 4)">{{ n(v.slope, 6) }}</td>
              <td class="num">{{ n(v.r_squared, 3) }}</td>
              <td class="whitespace-nowrap">{{ monthLabel(v.effective_from) }}</td>
              <td>{{ v.source === 'external' ? 'External' : 'Fit ' + (v.fli_fit_id || '') }}</td>
              <td><span class="maiic-badge" :class="badge(v.status)">{{ statusLabel(v.status) }}</span><div v-if="v.retired_reason" class="mt-0.5 max-w-[14rem] text-xs font-normal text-gray-500">{{ v.retired_reason }}</div></td>
              <td class="text-xs">{{ v.publisher || v.approver_label || '-' }}<div v-if="v.published_at" class="text-gray-400">{{ String(v.published_at).slice(0, 10) }}</div></td>
              <td class="text-xs">{{ v.months.length ? v.months.map(monthLabel).join(', ') : '-' }}</td>
              <td v-if="canGovern" class="num whitespace-nowrap">
                <button v-if="v.status === 'PUBLISHED'" type="button" class="maiic-action maiic-action-delete" title="Retire this version" @click="retire(v)"><font-awesome-icon icon="ban" /></button>
                <template v-if="v.status === 'PROPOSED'">
                  <button v-if="v.proposed_by !== userId" type="button" class="maiic-action maiic-action-view" title="Approve this external model" @click="approve(v)"><font-awesome-icon icon="check" /></button>
                  <button type="button" class="maiic-action maiic-action-delete ml-1" title="Reject" @click="reject(v)"><font-awesome-icon icon="times" /></button>
                </template>
              </td>
            </tr>
            <tr v-if="!versions.length"><td :colspan="canGovern ? 11 : 10" class="maiic-empty">No version yet. Approving a fit on Regression publishes the first.</td></tr>
          </tbody></table></div>
        <RowPager v-model="page" :total="versions.length" class="border-t border-gray-100" />
      </div>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import RowPager from '@/Components/Maiic/RowPager.vue'
import Notice from '@/Components/Maiic/Notice.vue'
import { Link, router } from '@inertiajs/vue3'
import { confirmDialog } from '@/Components/confirmDialog'
import { promptReason } from '@/Components/Maiic/promptReason'

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

export default {
  components: { AppLayout, RowPager, Notice, Link },
  props: { period: String, periods: Array, versions: Array, inForce: Object, cadence: String, canGovern: Boolean, userId: Number },
  data() { return { form: { period: this.period }, page: 1 } },
  computed: { paged() { return this.versions.slice((this.page - 1) * 15, this.page * 15) } },
  methods: {
    monthLabel(p) { if (!p) return '-'; const [y, m] = String(p).split('-'); return m ? `${MONTHS[Number(m) - 1]} ${y}` : p },
    go() { router.get(route('fli-models.index'), { period: this.form.period }) },
    n(v, d) { return v == null ? '' : Number(v).toFixed(d) },
    badge(s) { return { PUBLISHED: 'maiic-badge-green', PROPOSED: 'maiic-badge-gold', SUPERSEDED: 'maiic-badge-grey', RETIRED: 'maiic-badge-red', REJECTED: 'maiic-badge-red' }[s] || 'maiic-badge-grey' },
    statusLabel(s) { return { PUBLISHED: 'Published', PROPOSED: 'Proposed', SUPERSEDED: 'Superseded', RETIRED: 'Retired', REJECTED: 'Rejected' }[s] || s },
    async retire(v) {
      const reason = await promptReason({ title: `Retire version ${v.version}?`, message: 'The months after it fall back to the version before it, or to none. Run the route again for an open month.', label: 'Why it is retired', confirmLabel: 'Retire', tone: 'danger' })
      if (reason) router.post(route('fli-models.retire', v.id), { reason }, { preserveScroll: true })
    },
    async approve(v) {
      if (await confirmDialog({ title: `Approve external version ${v.version}?`, message: `It governs every month from ${this.monthLabel(v.effective_from)} until a later version.`, confirmLabel: 'Approve' })) router.post(route('fli-models.approve-external', v.id), {}, { preserveScroll: true })
    },
    async reject(v) {
      const reason = await promptReason({ title: `Reject external version ${v.version}?`, label: 'Reason', confirmLabel: 'Reject', tone: 'danger' })
      if (reason) router.post(route('fli-models.reject-external', v.id), { reason }, { preserveScroll: true })
    },
  },
}
</script>
