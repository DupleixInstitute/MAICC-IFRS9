<template>
  <app-layout title="Scenario Sets" description="One set per reporting period, versioned: proposed by one person, approved by another, locked with the period's ECL; every scenario but the base is a shock on the base path, and the back-test and sensitivity are stored with the set (spec v4 section 15)">
    <template #actions>
      <form @submit.prevent="seed" class="flex items-center gap-2">
        <input v-model="form.period" type="month" class="maiic-input" required/>
        <button v-if="canGovern && !sets.length" type="submit" class="primary-btn text-sm">Propose the first set (15.8)</button>
        <button type="button" @click="go" class="secondary-btn text-sm">View period</button>
      </form>
    </template>

    <div class="space-y-6">
      <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-100">
        <span class="font-semibold">The rules in force</span> (Governance Centre): at least {{ rules.min_count }} scenarios; the base at least {{ rules.base_floor }} percent; no single weight above {{ rules.single_ceiling }} percent; a calibration note on every downside {{ rules.note_required ? 'required' : 'optional' }}; weighting: {{ rules.weighting }}.
      </div>

      <div v-if="!sets.length" class="maiic-panel p-6 text-sm text-gray-500">No scenario set for {{ period }}. The first set of specification 15.8 (Base 50, Upside 15, Downside 25, Severe 10, each anchored to a year Malawi has lived through) can be proposed here for Dr Thom to set the weights.</div>

      <div v-for="s in sets" :key="s.id" class="maiic-panel">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-200 p-5 dark:border-slate-700">
          <div>
            <h3 class="text-base font-bold">{{ s.name }} <span class="text-gray-400">v{{ s.version }}</span> <span class="maiic-badge ml-2" :class="badge(s.status)">{{ s.status }}</span></h3>
            <p class="text-sm text-gray-500">{{ s.narrative }}</p>
            <p class="text-xs text-gray-500">Sources: {{ s.source_vintage || '-' }} · proposed {{ s.proposer || '-' }} {{ s.proposed_at || '' }} · approved {{ s.approver || '-' }} {{ s.approved_at || '' }} · locked {{ s.locked_at || '-' }}<span v-if="s.supersedes_id"> · supersedes set {{ s.supersedes_id }}: {{ s.version_reason }}</span></p>
            <p v-if="s.validation && !s.validation.ok" class="mt-1 text-xs text-red-700 dark:text-red-300">{{ s.validation.problems.join('; ') }}</p>
          </div>
          <div v-if="canGovern" class="flex gap-2">
            <button v-if="s.status === 'DRAFT'" @click="post('scenario-sets.propose', s.id)" class="secondary-btn text-xs">Propose</button>
            <button v-if="s.status === 'PROPOSED'" @click="post('scenario-sets.approve', s.id)" class="primary-btn text-xs">Approve (second person)</button>
            <button v-if="s.status === 'APPROVED'" @click="post('scenario-sets.lock', s.id)" class="secondary-btn text-xs">Lock with the period</button>
            <button v-if="s.status === 'LOCKED' || s.status === 'APPROVED'" @click="newVersion(s.id)" class="secondary-btn text-xs">New version</button>
          </div>
        </div>
        <div class="grid grid-cols-1 gap-6 p-5 lg:grid-cols-2">
          <div>
            <h4 class="maiic-section-title mt-0">Scenarios and weights</h4>
            <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Scenario</th><th class="num">Weight %</th><th class="num">PD ×</th><th>Anchored to</th><th>Shocks on the base</th></tr></thead>
              <tbody><tr v-for="x in s.scenarios" :key="x.id"><td>{{ x.name }}<span v-if="x.is_base" class="maiic-badge maiic-badge-grey ml-1">base</span><div class="text-xs text-gray-500">{{ x.calibration_note }}</div></td><td class="num">{{ Number(x.weight).toFixed(0) }}</td><td class="num">{{ x.pd_multiplier }}</td><td class="text-xs">{{ x.anchored_to }}</td><td class="text-xs"><div v-for="sh in x.shocks" :key="sh.id">{{ sh.statistic_code }} {{ sh.kind === 'pct' ? '+' + sh.value + '%' : (sh.kind === 'abs' ? (sh.value > 0 ? '+' : '') + sh.value + ' points' : sh.kind + ' ' + sh.value) }}</div></td></tr></tbody></table></div>
          </div>
          <div>
            <h4 class="maiic-section-title mt-0">The paths, first forecast year</h4>
            <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Series</th><th class="num">Base</th><th v-for="(sc, name) in s.paths.scenarios" :key="name" class="num">{{ name }}</th></tr></thead>
              <tbody><tr v-for="(o, code) in s.paths.base" :key="code"><td>{{ code }}</td><td class="num">{{ fmt(o[0]) }}</td><td v-for="(sc, name) in s.paths.scenarios" :key="name" class="num">{{ fmt(sc.path[code] ? sc.path[code][0] : null) }}</td></tr></tbody></table></div>
            <template v-if="s.sensitivity">
              <h4 class="maiic-section-title">Sensitivity ({{ s.sensitivity.loans }} loans)</h4>
              <p v-if="s.sensitivity.note" class="text-xs text-amber-700">{{ s.sensitivity.note }}</p>
              <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>ECL under</th><th class="num">Amount</th></tr></thead>
                <tbody><tr v-for="(p, name) in s.sensitivity.per_scenario" :key="name"><td>{{ name }} at 100 percent</td><td class="num">{{ fmt(p.ecl) }}</td></tr>
                <tr class="total"><td>Weighted</td><td class="num">{{ fmt(s.sensitivity.weighted_ecl) }}</td></tr>
                <tr><td>Ten points from the base to the downside</td><td class="num">{{ fmt(s.sensitivity.ten_points_to_downside) }}</td></tr>
                <tr><td>Ten points from the base to the upside</td><td class="num">{{ fmt(s.sensitivity.ten_points_to_upside) }}</td></tr></tbody></table></div>
              <p class="text-xs text-gray-500">{{ s.sensitivity.basis }}</p>
            </template>
            <template v-if="s.backtest">
              <h4 class="maiic-section-title">Back-test</h4>
              <p class="text-xs" :class="s.backtest.review_weights ? 'text-red-700' : 'text-gray-600'">{{ s.backtest.note }}</p>
              <div v-if="s.backtest.rows && s.backtest.rows.length" class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Series</th><th class="num">Predicted base</th><th class="num">Actual</th><th class="num">Miss</th><th>Within range</th></tr></thead>
                <tbody><tr v-for="r in s.backtest.rows" :key="r.series"><td>{{ r.series }}</td><td class="num">{{ fmt(r.predicted_base) }}</td><td class="num">{{ fmt(r.actual) }}</td><td class="num">{{ fmt(r.miss) }}</td><td>{{ r.within_range ? 'yes' : 'NO' }}</td></tr></tbody></table></div>
            </template>
          </div>
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
  props: { period: String, sets: Array, rules: Object, periods: Array, canGovern: Boolean },
  data() { return { form: { period: this.period } } },
  methods: {
    fmt(v) { return v == null ? '-' : Number(v).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
    badge(s) { return s === 'APPROVED' ? 'maiic-badge-green' : (s === 'LOCKED' ? 'maiic-badge-solid-green' : (s === 'PROPOSED' ? 'maiic-badge-gold' : 'maiic-badge-grey')) },
    go() { router.get(route('scenario-sets.index'), { period: this.form.period }) },
    seed() { router.post(route('scenario-sets.seed'), { period: this.form.period }) },
    post(name, id) { router.post(route(name, id), {}, { preserveScroll: true }) },
    newVersion(id) { const reason = prompt('Why a new version? (written to the set and the audit log)'); if (reason) router.post(route('scenario-sets.version', id), { reason }, { preserveScroll: true }) },
  },
}
</script>
