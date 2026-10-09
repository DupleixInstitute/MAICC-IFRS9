<template>
  <app-layout title="Regression (FLI Adjustments)" description="The fitted macro relationships for a period, the one approved for use, and what it does to each loan's PD">
    <template #actions>
      <form @submit.prevent="go" class="flex items-center gap-2"><input v-model="form.period" type="month" class="maiic-input !w-44" aria-label="Period"/><button type="button" @click="go" class="secondary-btn text-sm">View</button>
        <button v-if="canGovern" type="button" @click="apply" class="primary-btn text-sm">Apply the route to {{ form.period }}</button></form>
    </template>

    <div class="space-y-4">
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="maiic-kpi !px-4 !py-3" style="--accent:#0284c7"><div class="maiic-kpi-label">Method in force</div><div class="truncate text-sm font-bold" :title="methodInForce">{{ methodInForce }}</div></div>
        <div class="maiic-kpi !px-4 !py-3" style="--accent:#0284c7"><div class="maiic-kpi-label">Loans with a post-FLI PD</div><div class="text-xl font-bold">{{ loans && loans.n ? loans.n : 0 }} <span v-if="loans && loans.n" class="text-xs font-normal text-gray-500">{{ loans.adjusted }} adjusted</span></div></div>
        <div class="maiic-kpi !px-4 !py-3" style="--accent:#0284c7"><div class="maiic-kpi-label">Average PD, before and after</div><div class="text-xl font-bold">{{ loans && loans.n ? pct(loans.pre) + ' to ' + pct(loans.post) : '-' }}</div></div>
      </div>

      <!-- The fit in use, pinned: what the route applies to every loan of the period. -->
      <div v-if="approved" class="maiic-panel border-l-4 !border-l-maiic-600 bg-maiic-50/60 px-5 py-3">
        <div class="flex flex-wrap items-center gap-2">
          <span class="maiic-badge maiic-badge-solid-green">Approved for use</span>
          <span class="font-semibold text-gray-900">Fit {{ approved.id }}: {{ approved.driver_name }} drives the {{ approved.proxy_name }}, {{ approved.lag_months == 0 ? 'in the same month' : approved.lag_months + ' months later' }}</span>
        </div>
        <div class="mt-1.5 flex flex-wrap gap-x-5 gap-y-1 text-xs text-gray-600">
          <span>Slope <strong class="text-gray-900">{{ n(approved.slope, 6) }}</strong></span>
          <span>Intercept <strong class="text-gray-900">{{ n(approved.intercept, 4) }}</strong></span>
          <span>R² <strong class="text-gray-900">{{ n(approved.r_squared, 3) }}</strong></span>
          <span v-if="approved.p_value != null">p <strong class="text-gray-900">{{ n(approved.p_value, 4) }}</strong></span>
          <span>Months of data <strong class="text-gray-900">{{ approved.n_obs }}</strong></span>
          <span v-if="approved.expected_sign">Expected: {{ signLabel(approved.expected_sign) }}</span>
          <span>Proposed by <strong class="text-gray-900">{{ approved.proposer || '-' }}</strong></span>
          <span>Approved by <strong class="text-gray-900">{{ approved.approver || '-' }}</strong> on {{ approved.approved_at || '-' }}</span>
          <span v-if="approved.approver_label" class="maiic-badge maiic-badge-gold">{{ approved.approver_label }}</span>
        </div>
      </div>
      <div v-else class="maiic-panel border-l-4 !border-l-maiicgold-500 px-5 py-3 text-sm text-gray-700">
        <span class="font-semibold">No fit approved for {{ period }}:</span> the PD holds (overlay at zero). Propose an applied fit below; a second person approves it.
      </div>

      <div class="maiic-panel">
        <div class="border-b border-gray-200 px-5 py-3 dark:border-slate-700"><h3 class="font-semibold text-gray-900">Fits for {{ period }}</h3><p class="text-xs text-gray-500">{{ fits.length }} fit(s), applied first then declined, strongest first. A reviewer proposes an applied fit for use and a second person approves it.</p></div>
        <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Fit</th><th>Driver and credit-loss measure</th><th class="num">Lag</th><th class="num">Slope</th><th class="num">R²</th><th class="num">p</th><th class="num">n</th><th>Verdict</th><th>Approval</th><th v-if="canGovern"></th></tr></thead>
          <tbody><tr v-for="f in pagedFits" :key="f.id" :class="f.approval_status === 'APPROVED' ? 'bg-maiic-50 font-semibold' : ''">
            <td class="font-mono text-xs">{{ f.id }}</td>
            <td class="min-w-[14rem]"><div :title="f.statistic_code">{{ f.driver_name }}</div><div class="text-xs text-gray-500" :title="f.proxy_code">{{ f.proxy_name }}</div></td>
            <td class="num">{{ f.lag_months }}</td>
            <td class="num" :title="'Intercept ' + n(f.intercept, 4)">{{ n(f.slope, 6) }}</td>
            <td class="num">{{ n(f.r_squared, 3) }}</td>
            <td class="num">{{ n(f.p_value, 4) }}</td>
            <td class="num">{{ f.n_obs }}</td>
            <td class="whitespace-nowrap"><span class="maiic-badge" :class="f.verdict === 'applied' ? 'maiic-badge-green' : 'maiic-badge-red'">{{ f.verdict === 'applied' ? 'Applied' : (f.verdict === 'declined' ? 'Declined' : f.verdict) }}</span><div v-if="f.declined_plain" class="mt-0.5 text-xs font-normal text-gray-500">{{ f.declined_plain }}</div></td>
            <td class="text-xs"><span class="maiic-badge whitespace-nowrap" :class="f.approval_status === 'APPROVED' ? 'maiic-badge-green' : (f.approval_status === 'PROPOSED' ? 'maiic-badge-gold' : 'maiic-badge-grey')">{{ approvalLabel(f.approval_status) }}</span><div v-if="f.approver || f.approver_label" class="mt-0.5 font-normal text-gray-500">{{ f.approver || f.approver_label }}</div></td>
            <td v-if="canGovern" class="whitespace-nowrap"><div class="flex justify-end gap-1.5"><button v-if="f.verdict === 'applied' && f.approval_status === 'NONE'" type="button" @click="post('fli-adjustments.propose', f.id)" class="maiic-action maiic-action-edit" title="Propose this fit for use"><font-awesome-icon icon="share" /></button><button v-if="f.approval_status === 'PROPOSED'" type="button" @click="post('fli-adjustments.approve', f.id)" class="maiic-action maiic-action-view" title="Approve this fit"><font-awesome-icon icon="check" /></button></div></td></tr>
          <tr v-if="!fits.length"><td :colspan="canGovern ? 10 : 9" class="maiic-empty">No fits for this period yet. Run the correlation on the Correlation Finder tab, then come back to propose one.</td></tr></tbody></table></div>
        <RowPager v-model="page" :total="fits.length" class="border-t border-gray-100" />
      </div>

      <details class="maiic-panel group">
        <summary class="flex cursor-pointer select-none items-center justify-between px-5 py-4">
          <span><span class="font-semibold text-gray-900">How the adjustment reaches the PD</span><span class="ml-2 text-xs text-gray-500">{{ cards.length }} methods; in force: {{ methodInForce }}</span></span>
          <span class="text-xs font-semibold text-maiic-700 group-open:hidden">Show</span><span class="hidden text-xs font-semibold text-maiic-700 group-open:inline">Hide</span>
        </summary>
      <div class="grid grid-cols-1 gap-4 border-t border-gray-200 p-5 lg:grid-cols-2">
        <div v-for="c in cards" :key="c.key" class="rounded-xl border border-gray-200 p-5 text-sm" :class="c.in_force ? 'ring-2 ring-sky-400' : ''">
          <h3 class="text-base font-bold">{{ c.title }} <span v-if="c.in_force" class="maiic-badge maiic-badge-green ml-1">In force</span><span v-if="c.seeded" class="maiic-badge maiic-badge-grey ml-1">Seeded</span><span v-if="!c.available" class="maiic-badge maiic-badge-gold ml-1">Preconditions not met</span></h3>
          <p class="mt-1 text-gray-700 dark:text-slate-300">{{ plain(c.what) }}</p>
          <p class="mt-2 font-mono text-xs">{{ c.formula }}</p>
          <ul class="mt-1 text-xs text-gray-500"><li v-for="(v, k) in c.symbols" :key="k">{{ k }}: {{ v }}</li></ul>
          <ul class="mt-2 list-disc pl-5 text-xs"><li v-for="(i, n) in c.implies" :key="n">{{ i }}</li></ul>
          <div class="mt-2 space-y-0.5 text-xs"><div v-for="(p, n) in c.preconditions" :key="n"><span :class="p.met ? 'text-maiic-700 dark:text-maiic-300' : 'text-red-700 dark:text-red-300'">{{ p.met ? '✓' : '✗' }}</span> {{ p.name }}: <span class="text-gray-500">{{ p.figure }}</span></div></div>
          <p v-if="c.example" class="mt-2 rounded bg-gray-50 p-2 text-xs dark:bg-slate-900/60">Example on {{ c.example.contract_id }} ({{ c.example.customer_name }}, stage {{ c.example.stage }}): {{ c.example.steps.join('; ') }}</p>
        </div>
      </div>
      </details>

      <details v-if="lastRun" class="maiic-panel group">
        <summary class="flex cursor-pointer select-none items-center justify-between px-5 py-4">
          <span><span class="font-semibold text-gray-900">Last run for {{ period }}</span><span class="ml-2 text-xs text-gray-500">{{ lastRun.loans }} loans, {{ lastRun.adjusted }} adjusted, {{ lastRun.held }} held</span></span>
          <span class="text-xs font-semibold text-maiic-700 group-open:hidden">Show</span><span class="hidden text-xs font-semibold text-maiic-700 group-open:inline">Hide</span>
        </summary>
        <div class="space-y-1 border-t border-gray-200 px-5 py-4 text-xs text-gray-600">
          <div>Route {{ lastRun.route }}, method {{ lastRun.method }}, weighting {{ lastRun.weighting }}<span v-if="lastRun.fit_relationship">, fit {{ lastRun.fit }} {{ lastRun.fit_relationship }}</span>.</div>
          <div v-if="lastRun.note" class="text-amber-700">{{ lastRun.note }}</div>
          <div>Adjustment per scenario: <span v-for="(a, n) in lastRun.scenarios" :key="n" class="mr-3">{{ n }} {{ a }}</span></div>
        </div>
      </details>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'
import RowPager from '@/Components/Maiic/RowPager.vue'

export default {
  components: { AppLayout, RowPager },
  props: { period: String, fits: Array, approved: Object, cards: Array, methodInForce: String, loans: Object, lastRun: Object, periods: Array, canGovern: Boolean },
  data() { return { form: { period: this.period }, page: 1 } },
  computed: {
    pagedFits() { return this.fits.slice((this.page - 1) * 15, this.page * 15) },
  },
  methods: {
    approvalLabel(s) { return { APPROVED: 'Approved', PROPOSED: 'Proposed', NONE: 'Not proposed' }[s] || s },
    // Specification references are for the build team, not the screen.
    plain(t) { return String(t || '').replace(/\s*\((?:spec v\d+ section [\d.]+[^)]*|O\d+|decision D\d+)\)/g, '').replace(/\s*of section [\d.]+/g, '') },
    n(v, d) { return v == null ? '' : Number(v).toFixed(d) },
    signLabel(s) { return s === 'positive' ? 'moves with the driver' : (s === 'negative' ? 'moves against the driver' : '') },
    pct(v) { return v == null ? '-' : (Number(v) * 100).toFixed(2) + '%' },
    go() { router.get(route('fli-adjustments.index'), { period: this.form.period }) },
    post(name, id) { router.post(route(name, id), {}, { preserveScroll: true }) },
    apply() { router.post(route('fli-adjustments.apply'), { period: this.form.period }, { preserveScroll: true }) },
  },
}
</script>
