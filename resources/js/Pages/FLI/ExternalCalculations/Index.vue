<template>
  <app-layout title="External Calculations" description="A forward-looking model calibrated outside the system, entered with its evidence, proposed by one person and approved by another; and what the route would do to the month under the model in force">
    <template #actions>
      <select v-model="periodSel" @change="go" class="maiic-input !w-40" aria-label="Month">
        <option v-for="p in periods" :key="p" :value="p">{{ monthLabel(p) }}</option>
      </select>
      <button v-if="canGovern" type="button" class="primary-btn text-sm" @click="showForm = !showForm"><font-awesome-icon icon="plus" class="mr-1" />Enter an external model</button>
    </template>

    <div class="space-y-4">
      <!-- the entry form -->
      <form v-if="showForm" class="maiic-panel space-y-3 p-5" @submit.prevent="submit">
        <h3 class="font-semibold text-gray-900">An externally calibrated model</h3>
        <p class="text-xs text-gray-500">proxy(t) = slope x driver(t - lag) + intercept. The route reads the driver and the measure from the system's own series, so both must be held. It reaches no loan until a second person approves it.</p>
        <div class="grid gap-3 md:grid-cols-3">
          <label class="text-xs font-semibold text-gray-700">Driver<select v-model="form.statistic_code" class="maiic-input mt-1 w-full" required><option value="" disabled>Choose</option><option v-for="d in drivers" :key="d.code" :value="d.code">{{ d.name }} ({{ d.code }})</option></select></label>
          <label class="text-xs font-semibold text-gray-700">Credit-loss measure<select v-model="form.proxy_code" class="maiic-input mt-1 w-full" required><option value="" disabled>Choose</option><option v-for="p in proxies" :key="p.code" :value="p.code">{{ p.name }} ({{ p.code }})</option></select></label>
          <label class="text-xs font-semibold text-gray-700">Lag in months<input v-model.number="form.lag_months" type="number" min="0" max="36" class="maiic-input mt-1 w-full" required /></label>
          <label class="text-xs font-semibold text-gray-700">Slope<input v-model="form.slope" type="number" step="any" class="maiic-input mt-1 w-full" required /></label>
          <label class="text-xs font-semibold text-gray-700">Intercept<input v-model="form.intercept" type="number" step="any" class="maiic-input mt-1 w-full" required /></label>
          <label class="text-xs font-semibold text-gray-700">R² (optional)<input v-model="form.r_squared" type="number" step="any" min="0" max="1" class="maiic-input mt-1 w-full" /></label>
          <label class="text-xs font-semibold text-gray-700">Observations (optional)<input v-model="form.n_obs" type="number" min="1" class="maiic-input mt-1 w-full" /></label>
          <label class="text-xs font-semibold text-gray-700">Calibrated on data to<input v-model="form.calibrated_period" type="month" class="maiic-input mt-1 w-full" required /></label>
          <label class="text-xs font-semibold text-gray-700">Governs from<input v-model="form.effective_from" type="month" class="maiic-input mt-1 w-full" required /></label>
        </div>
        <label class="block text-xs font-semibold text-gray-700">Evidence<textarea v-model="form.evidence" rows="3" class="maiic-input mt-1 w-full" required placeholder="The workbook or report, who calibrated it, the data and the period it was fitted on, the tests it passed"></textarea></label>
        <div class="flex justify-end gap-2"><button type="button" class="secondary-btn text-sm" @click="showForm = false">Cancel</button><button type="submit" class="primary-btn text-sm" :disabled="form.processing">Propose</button></div>
      </form>

      <!-- what the route would do this month -->
      <div class="maiic-panel">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-3 dark:border-slate-700">
          <div><h3 class="font-semibold text-gray-900">What the route would do to {{ monthLabel(period) }}</h3><p class="text-xs text-gray-500">Route {{ preview.route }}, method {{ preview.method }}, weighting {{ preview.weighting }}. Nothing is written until the route is applied.</p></div>
          <Link :href="route('fli.overview', { period })" class="secondary-btn !px-3 !py-1 text-xs">Apply it from the Overview</Link>
        </div>
        <div class="grid gap-3 px-5 py-3 text-sm md:grid-cols-2">
          <div><span class="text-xs font-semibold uppercase text-gray-500">Model in force</span><div v-if="preview.fit">Version {{ preview.fit.version }}: {{ preview.fit.statistic_code }} to {{ preview.fit.proxy_code }}, lag {{ preview.fit.lag_months }} months, since {{ monthLabel(preview.fit.effective_from) }}</div><div v-else class="text-amber-700">None: the PD holds.</div></div>
          <div><span class="text-xs font-semibold uppercase text-gray-500">Scenario set</span><div v-if="preview.set">{{ preview.set.name }}, v{{ preview.set.version }} ({{ preview.set.status }})</div><div v-else class="text-amber-700">No approved set for the month.</div></div>
        </div>
        <p v-if="preview.problem" class="px-5 pb-3 text-sm text-red-700">{{ preview.problem }}</p>
        <div v-if="Object.keys(preview.scenarios || {}).length" class="maiic-table-wrap"><table class="maiic-table">
          <thead><tr><th>Scenario</th><th class="num">Weight</th><th class="num">Driver</th><th>Driver month</th><th class="num">Predicted measure</th><th class="num">Adjustment to the PD</th></tr></thead>
          <tbody><tr v-for="(s, name) in preview.scenarios" :key="name"><td>{{ name }}</td><td class="num">{{ s.weight }}%</td><td class="num">{{ s.driver }}</td><td>{{ s.driver_period }}</td><td class="num">{{ s.predicted_proxy }}</td><td class="num font-semibold">{{ (s.adjustment * 100).toFixed(2) }}%</td></tr></tbody>
        </table></div>
        <p v-if="preview.base" class="px-5 py-2 text-xs text-gray-500">Base: {{ preview.base.statistic_code }} {{ preview.base.base_value }} for {{ preview.base.base_period }}, predicted measure {{ preview.base.predicted_base_proxy }}. Window driver: {{ preview.base.window_driver_source }}.</p>
      </div>

      <!-- the external versions -->
      <div class="maiic-panel">
        <div class="border-b border-gray-200 px-5 py-3 dark:border-slate-700"><h3 class="font-semibold text-gray-900">External models</h3><p class="text-xs text-gray-500">{{ versions.length }} entered. Approved ones appear with every other version on Model Versions.</p></div>
        <div class="maiic-table-wrap"><table class="maiic-table">
          <thead><tr><th class="num">Version</th><th>Driver and measure</th><th class="num">Lag</th><th class="num">Slope</th><th class="num">Intercept</th><th>Governs from</th><th>Status</th><th>Proposed</th><th>Approved</th><th>Evidence</th><th v-if="canGovern"></th></tr></thead>
          <tbody>
            <tr v-for="v in paged" :key="v.id">
              <td class="num">{{ v.version }}</td>
              <td><div>{{ v.driver_name }}</div><div class="text-xs text-gray-500">{{ v.proxy_name }}</div></td>
              <td class="num">{{ v.lag_months }}</td><td class="num">{{ Number(v.slope).toFixed(6) }}</td><td class="num">{{ Number(v.intercept).toFixed(4) }}</td>
              <td>{{ monthLabel(v.effective_from) }}</td>
              <td><span class="maiic-badge" :class="badge(v.status)">{{ v.status }}</span></td>
              <td class="text-xs">{{ v.proposer || '-' }}</td><td class="text-xs">{{ v.approver || v.approver_label || '-' }}</td>
              <td class="max-w-[18rem] truncate text-xs" :title="v.evidence">{{ v.evidence }}</td>
              <td v-if="canGovern" class="num whitespace-nowrap">
                <template v-if="v.status === 'PROPOSED'">
                  <button v-if="v.proposed_by !== userId" type="button" class="maiic-action maiic-action-view" title="Approve" @click="approve(v)"><font-awesome-icon icon="check" /></button>
                  <button type="button" class="maiic-action maiic-action-delete ml-1" title="Reject" @click="reject(v)"><font-awesome-icon icon="times" /></button>
                </template>
              </td>
            </tr>
            <tr v-if="!versions.length"><td :colspan="canGovern ? 11 : 10" class="maiic-empty">No external model has been entered. Use Enter an external model at the top right.</td></tr>
          </tbody></table></div>
        <RowPager v-model="page" :total="versions.length" class="border-t border-gray-100" />
      </div>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import RowPager from '@/Components/Maiic/RowPager.vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { confirmDialog } from '@/Components/confirmDialog'
import { promptReason } from '@/Components/Maiic/promptReason'

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

export default {
  components: { AppLayout, RowPager, Link },
  props: { period: String, periods: Array, versions: Array, preview: Object, drivers: Array, proxies: Array, canGovern: Boolean, userId: Number },
  data() {
    return {
      periodSel: this.period, page: 1, showForm: false,
      form: useForm({ statistic_code: '', proxy_code: '', lag_months: 0, slope: '', intercept: '', r_squared: '', n_obs: '', calibrated_period: this.period, effective_from: this.period, evidence: '' }),
    }
  },
  computed: { paged() { return this.versions.slice((this.page - 1) * 15, this.page * 15) } },
  methods: {
    monthLabel(p) { if (!p) return '-'; const [y, m] = String(p).split('-'); return m ? `${MONTHS[Number(m) - 1]} ${y}` : p },
    go() { router.get(route('fli.external.index'), { period: this.periodSel }) },
    badge(s) { return { PUBLISHED: 'maiic-badge-green', PROPOSED: 'maiic-badge-gold', REJECTED: 'maiic-badge-red', RETIRED: 'maiic-badge-red' }[s] || 'maiic-badge-grey' },
    submit() { this.form.transform(d => ({ ...d, r_squared: d.r_squared === '' ? null : d.r_squared, n_obs: d.n_obs === '' ? null : d.n_obs })).post(route('fli.external.propose'), { preserveScroll: true, onSuccess: () => { this.showForm = false; this.form.reset() } }) },
    async approve(v) { if (await confirmDialog({ title: `Approve external version ${v.version}?`, message: `It governs every month from ${this.monthLabel(v.effective_from)} until a later version.`, confirmLabel: 'Approve' })) router.post(route('fli-models.approve-external', v.id), {}, { preserveScroll: true }) },
    async reject(v) { const reason = await promptReason({ title: `Reject external version ${v.version}?`, label: 'Reason', confirmLabel: 'Reject', tone: 'danger' }); if (reason) router.post(route('fli-models.reject-external', v.id), { reason }, { preserveScroll: true }) },
  },
}
</script>
