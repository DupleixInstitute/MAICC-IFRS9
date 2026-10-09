<template>
  <app-layout title="Regression (FLI Adjustments)" description="The fitted macro relationships for a period, the one approved for use, and what it does to each loan's PD">
    <template #actions>
      <form @submit.prevent="go" class="flex items-center gap-2"><input v-model="form.period" type="month" class="maiic-input !w-44" aria-label="Period"/><button type="button" @click="go" class="secondary-btn text-sm">View</button>
        <button v-if="canGovern" type="button" @click="apply" class="primary-btn text-sm">Apply the route to {{ form.period }}</button></form>
    </template>

    <div class="space-y-4">
      <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="maiic-kpi !py-3" style="--accent:#0284c7"><div class="maiic-kpi-label">Method in force</div><div class="text-sm font-bold">{{ methodInForce }}</div></div>
        <div class="maiic-kpi !py-3" style="--accent:#0284c7"><div class="maiic-kpi-label">Approved fit</div><div class="text-sm font-bold">{{ approved ? ('#' + approved.id + ' ' + approved.statistic_code + ' to ' + approved.proxy_code + ' (lag ' + approved.lag_months + ')') : 'None: the PD holds (overlay at zero)' }}</div></div>
        <div class="maiic-kpi !py-3" style="--accent:#0284c7"><div class="maiic-kpi-label">Loans with a post-FLI PD</div><div class="text-xl font-bold">{{ loans && loans.n ? loans.n : 0 }}</div><div class="text-xs text-gray-500" v-if="loans && loans.n">{{ loans.adjusted }} adjusted, route {{ loans.route }}, fit {{ loans.fit }}, set {{ loans.set_id }}</div></div>
        <div class="maiic-kpi !py-3" style="--accent:#0284c7"><div class="maiic-kpi-label">Average PD, before and after</div><div class="text-xl font-bold">{{ loans && loans.n ? pct(loans.pre) + ' to ' + pct(loans.post) : '-' }}</div></div>
      </div>

      <p v-if="lastRun" class="-mt-3 text-xs text-gray-500">
        Last run for {{ period }}: route {{ lastRun.route }}, method {{ lastRun.method }}, weighting {{ lastRun.weighting }}<span v-if="lastRun.fit_relationship">, fit {{ lastRun.fit }} {{ lastRun.fit_relationship }}</span>; {{ lastRun.loans }} loans, {{ lastRun.adjusted }} adjusted, {{ lastRun.held }} held.<span v-if="lastRun.note" class="text-amber-700"> {{ lastRun.note }}</span>
        <span class="ml-1">Adjustment per scenario: <span v-for="(a, n) in lastRun.scenarios" :key="n" class="mr-2">{{ n }} {{ a }}</span></span>
      </p>

      <div class="maiic-panel">
        <div class="border-b border-gray-200 px-5 py-4 dark:border-slate-700"><h3 class="font-semibold text-gray-900">Fits for {{ period }}</h3><p class="text-xs text-gray-500">{{ fits.length }} fit(s), best first; declined ones show the reason. A reviewer proposes one for use and a second person approves it.</p></div>
        <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Fit</th><th>Driver</th><th>Proxy</th><th class="num">Lag</th><th class="num">Slope</th><th class="num">Intercept</th><th class="num">R²</th><th class="num">p</th><th class="num">n</th><th>Verdict</th><th>Approval</th><th v-if="canGovern"></th></tr></thead>
          <tbody><tr v-for="f in fits" :key="f.id" :class="f.approval_status === 'APPROVED' ? 'font-bold' : ''"><td class="font-mono text-xs">{{ f.id }}</td><td>{{ f.statistic_code }}</td><td class="text-xs">{{ f.proxy_code }}</td><td class="num">{{ f.lag_months }}</td><td class="num">{{ Number(f.slope).toFixed(6) }}</td><td class="num">{{ Number(f.intercept).toFixed(4) }}</td><td class="num">{{ Number(f.r_squared).toFixed(4) }}</td><td class="num">{{ f.p_value != null ? Number(f.p_value).toFixed(4) : '' }}</td><td class="num">{{ f.n_obs }}</td>
            <td><span class="maiic-badge" :class="f.verdict === 'applied' ? 'maiic-badge-green' : 'maiic-badge-red'">{{ f.verdict === 'applied' ? 'Applied' : (f.verdict === 'declined' ? 'Declined' : f.verdict) }}</span><span v-if="f.declined_reason" class="ml-1 text-xs text-gray-500">{{ f.declined_reason }}</span></td>
            <td class="text-xs"><span class="maiic-badge" :class="f.approval_status === 'APPROVED' ? 'maiic-badge-green' : (f.approval_status === 'PROPOSED' ? 'maiic-badge-gold' : 'maiic-badge-grey')">{{ approvalLabel(f.approval_status) }}</span><span v-if="f.approver || f.approver_label"><br>{{ f.approver || f.approver_label }} {{ f.approved_at }}</span></td>
            <td v-if="canGovern" class="whitespace-nowrap"><div class="flex justify-end gap-1.5"><button v-if="f.verdict === 'applied' && f.approval_status === 'NONE'" type="button" @click="post('fli-adjustments.propose', f.id)" class="maiic-action maiic-action-edit" title="Propose this fit for use"><font-awesome-icon icon="share" /></button><button v-if="f.approval_status === 'PROPOSED'" type="button" @click="post('fli-adjustments.approve', f.id)" class="maiic-action maiic-action-view" title="Approve this fit"><font-awesome-icon icon="check" /></button></div></td></tr>
          <tr v-if="!fits.length"><td colspan="12" class="maiic-empty">No fits for this period yet. Run the correlation on the Correlation Finder tab, then come back to propose one.</td></tr></tbody></table></div>
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
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { router } from '@inertiajs/vue3'

export default {
  components: { AppLayout },
  props: { period: String, fits: Array, approved: Object, cards: Array, methodInForce: String, loans: Object, lastRun: Object, periods: Array, canGovern: Boolean },
  data() { return { form: { period: this.period } } },
  methods: {
    approvalLabel(s) { return { APPROVED: 'Approved', PROPOSED: 'Proposed', NONE: 'Not proposed' }[s] || s },
    // Specification references are for the build team, not the screen.
    plain(t) { return String(t || '').replace(/\s*\((?:spec v\d+ section [\d.]+[^)]*|O\d+|decision D\d+)\)/g, '').replace(/\s*of section [\d.]+/g, '') },
    pct(v) { return v == null ? '-' : (Number(v) * 100).toFixed(2) + '%' },
    go() { router.get(route('fli-adjustments.index'), { period: this.form.period }) },
    post(name, id) { router.post(route(name, id), {}, { preserveScroll: true }) },
    apply() { router.post(route('fli-adjustments.apply'), { period: this.form.period }, { preserveScroll: true }) },
  },
}
</script>
