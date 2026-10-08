<template>
  <app-layout title="Mega Farm Programme" description="Government money MAIIC runs on its behalf: out of the EIR engine, in the ECL module, MAIIC's share of the net amount; the loans staged under the programme's own class and their PD by the governed method (spec v4 section 16, decision D30)">
    <template #actions>
      <form @submit.prevent="go" class="flex items-center gap-2">
        <select v-model="form.period" class="maiic-select"><option v-for="p in periods" :key="p" :value="p">{{ p }}</option></select>
        <button type="button" @click="go" class="secondary-btn text-sm">View</button>
        <button v-if="canGovern && period" type="button" @click="run" class="primary-btn text-sm">Run the programme's ECL for {{ form.period }}</button>
      </form>
    </template>

    <div class="space-y-6">
      <div class="rounded-lg p-4 text-sm" :class="confirmed ? 'border border-maiic-200 bg-maiic-50 text-maiic-900 dark:border-maiic-800 dark:bg-maiic-900/30 dark:text-maiic-100' : 'border border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-100'">
        <span class="font-semibold">{{ confirmed ? 'D30 confirmed in the Governance Centre.' : 'D30 not yet confirmed: every run here carries the seeded settings and is a system figure for Dr Thom, not a MAIIC approval.' }}</span>
        Scope: {{ settings.mega_farms_scope || '-' }}. PD method: {{ settings.megafarm_pd_method || '-' }}. Scalar ceiling: {{ settings.megafarm_scalar_ceiling || '-' }}.
        <Link :href="route('eir-governance.index')" class="underline">Change them under maker-checker.</Link>
      </div>

      <div v-if="!periods.length" class="maiic-panel p-6 text-sm text-gray-500">No Mega Farm loans in any loan book yet. The monthly extracts MF_01 to MF_08 land by the E-Banker Feed; the November 2025 book sits on the demo database.</div>

      <template v-if="period">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
          <div class="maiic-kpi" style="--accent:#0284c7"><div class="maiic-kpi-label">Loans in {{ period }}</div><div class="maiic-kpi-value">{{ book.reduce((a, b) => a + Number(b.loans), 0).toLocaleString() }}</div></div>
          <div class="maiic-kpi" style="--accent:#0284c7"><div class="maiic-kpi-label">Gross carrying amount</div><div class="maiic-kpi-value">{{ fmt(book.reduce((a, b) => a + Number(b.carrying), 0)) }}</div></div>
          <div class="maiic-kpi" style="--accent:#dc2626"><div class="maiic-kpi-label">Stage 3</div><div class="maiic-kpi-value">{{ fmt(book.reduce((a, b) => a + Number(b.stage3_carrying), 0)) }}</div><div class="text-xs text-gray-500">{{ book.reduce((a, b) => a + Number(b.stage3_loans), 0) }} loans, under the 91-day class</div></div>
          <div class="maiic-kpi" style="--accent:#0284c7"><div class="maiic-kpi-label">MAIIC's ECL on the book</div><div class="maiic-kpi-value">{{ fmt(book.reduce((a, b) => a + Number(b.ecl || 0), 0)) }}</div><div class="text-xs text-gray-500">as last run, at MAIIC's share</div></div>
        </div>
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
          <div class="maiic-panel"><div class="border-b border-gray-200 p-4 dark:border-slate-700"><h3 class="text-base font-bold">By scheme</h3></div>
            <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Scheme</th><th>GL</th><th class="num">Loans</th><th class="num">Carrying</th><th class="num">Stage 3</th><th class="num">PD</th><th class="num">MAIIC ECL</th></tr></thead>
              <tbody><tr v-for="b in book" :key="b.product_code"><td>{{ b.product_group }}</td><td class="font-mono text-xs">{{ b.product_code }}</td><td class="num">{{ b.loans }}</td><td class="num">{{ fmt(b.carrying) }}</td><td class="num">{{ fmt(b.stage3_carrying) }}</td><td class="num">{{ b.pd != null ? (Number(b.pd) * 100).toFixed(2) + '%' : '-' }}</td><td class="num">{{ fmt(b.ecl) }}</td></tr></tbody></table></div></div>
          <div class="maiic-panel"><div class="border-b border-gray-200 p-4 dark:border-slate-700"><h3 class="text-base font-bold">By stage</h3></div>
            <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Stage</th><th class="num">Loans</th><th class="num">Carrying</th><th class="num">MAIIC ECL</th></tr></thead>
              <tbody><tr v-for="s in byStage" :key="s.stage"><td>{{ s.stage || 'unstaged' }}</td><td class="num">{{ s.loans }}</td><td class="num">{{ fmt(s.carrying) }}</td><td class="num">{{ fmt(s.ecl) }}</td></tr></tbody></table></div></div>
        </div>
      </template>

      <div class="maiic-panel">
        <div class="border-b border-gray-200 p-4 dark:border-slate-700"><h3 class="text-base font-bold">Runs</h3><p class="text-sm text-gray-500">Each run records its scope, method, scalar, LGD and share, or the reason it was declined; nothing is invented when a method's precondition is not met.</p></div>
        <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Run</th><th>Period</th><th>Scope</th><th>Method</th><th class="num">Loans</th><th class="num">Gross</th><th class="num">Programme ECL</th><th class="num">Share</th><th class="num">MAIIC ECL</th><th>Basis / declined</th><th>By</th></tr></thead>
          <tbody><tr v-for="r in runs" :key="r.id" class="align-top text-xs">
            <td>{{ r.id }}</td><td>{{ r.reporting_period }}</td><td>{{ r.scope }}</td><td>{{ r.method }}</td><td class="num">{{ r.loans }}</td><td class="num">{{ fmt(r.gross) }}</td><td class="num">{{ fmt(r.programme_ecl) }}</td><td class="num">{{ (Number(r.share) * 100).toFixed(0) }}%</td><td class="num">{{ fmt(r.maiic_ecl) }}</td>
            <td><span v-if="r.declined" class="text-amber-700 dark:text-amber-300">Declined: {{ r.declined }}</span>
              <span v-else>PD by stage {{ JSON.stringify(r.basis.pd_by_scenario || r.basis.pd_by_stage) }}; scalar {{ r.basis.scalar }} (measured {{ r.basis.basis && r.basis.basis.measured_scalar }}, ceiling {{ r.basis.basis && r.basis.basis.ceiling }}); LGD {{ r.basis.lgd != null ? (Number(r.basis.lgd) * 100).toFixed(2) + '%' : '-' }}</span>
              <div v-if="r.approver_label" class="text-gray-500">{{ r.approver_label }}</div></td>
            <td>{{ r.run_by_name || 'system' }}<br>{{ (r.created_at || '').slice(0, 16) }}</td></tr>
          <tr v-if="!runs.length"><td colspan="11" class="maiic-empty">No run yet.</td></tr></tbody></table></div>
      </div>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'

export default {
  components: { AppLayout, Link },
  props: { period: String, periods: Array, book: Array, byStage: Array, settings: Object, runs: Array, canGovern: Boolean, confirmed: Boolean },
  data() { return { form: { period: this.period } } },
  methods: {
    fmt(v) { return v == null ? '-' : Number(v).toLocaleString('en-GB', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) },
    go() { router.get(route('megafarm.index'), { period: this.form.period }) },
    run() { if (confirm('Run the Mega Farm ECL for ' + this.form.period + ' under the settings in force?')) router.post(route('megafarm.run'), { period: this.form.period }, { preserveScroll: true }) },
  },
}
</script>
