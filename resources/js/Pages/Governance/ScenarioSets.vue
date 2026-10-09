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

      <div v-if="overlays && overlays.length" class="maiic-panel p-4 text-sm">
        <span class="font-semibold">Overlays against {{ period }}</span> (the register, spec 15.7):
        <span v-for="o in overlays" :key="o.id" class="mr-3">#{{ o.id }} {{ o.scope }}{{ o.scope_value ? ' ' + o.scope_value : '' }} {{ pct(o.adjustment) }} <span class="maiic-badge" :class="o.status === 'APPROVED' ? 'maiic-badge-green' : 'maiic-badge-gold'">{{ o.status }}</span></span>
        <a :href="route('fli-overlays.index', { period })" class="text-maiic-700 underline dark:text-maiic-300">open the register</a>
      </div>

      <div v-if="!sets.length" class="maiic-panel p-6 text-sm text-gray-500">No scenario set for {{ period }}. The first set of specification 15.8 (Base 50, Upside 15, Downside 25, Severe 10, each anchored to a year Malawi has lived through) can be proposed here for Dr Thom to set the weights.</div>

      <div v-for="s in sets" :key="s.id" class="maiic-panel">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-200 p-5 dark:border-slate-700">
          <div>
            <h3 class="text-base font-bold">{{ s.name }} <span class="text-gray-400">v{{ s.version }}</span> <span class="maiic-badge ml-2" :class="badge(s.status)">{{ s.status }}</span></h3>
            <p class="text-sm text-gray-500">{{ s.narrative }}</p>
            <p class="text-xs text-gray-500">Sources: {{ s.source_vintage || '-' }} · proposed {{ s.proposer || '-' }} {{ s.proposed_at || '' }} · approved {{ s.approver || '-' }} {{ s.approved_at || '' }} · locked {{ s.locked_at || '-' }}<span v-if="s.supersedes_id"> · supersedes set {{ s.supersedes_id }}: {{ s.version_reason }}</span></p>
            <p v-if="s.validation && !s.validation.ok" class="mt-1 text-xs text-red-700 dark:text-red-300">{{ s.validation.problems.join('; ') }}</p>
            <p v-if="s.validation && s.validation.overlays_pending && s.validation.overlays_pending.length" class="mt-1 text-xs text-amber-700 dark:text-amber-300">Overlay{{ s.validation.overlays_pending.length === 1 ? '' : 's' }} {{ s.validation.overlays_pending.join(', ') }} still proposed: the set cannot lock until each is approved or rejected (15.7).</p>
            <p v-if="s.overlays_at_approval" class="mt-1 text-xs text-gray-500">Overlays in force at approval: {{ s.overlays_at_approval.overlays.length ? s.overlays_at_approval.overlays.map(o => '#' + o.id + ' ' + o.scope + (o.scope_value ? ' ' + o.scope_value : '') + ' ' + pct(o.adjustment)).join('; ') : 'none' }}</p>
          </div>
          <div v-if="canGovern" class="flex gap-2">
            <button v-if="s.editable && editing !== s.id" @click="openEditor(s)" class="secondary-btn text-xs">Edit weights, scenarios and shocks</button>
            <button v-if="s.status === 'DRAFT'" @click="post('scenario-sets.propose', s.id)" class="secondary-btn text-xs">Propose</button>
            <button v-if="s.status === 'PROPOSED'" @click="post('scenario-sets.approve', s.id)" class="primary-btn text-xs">Approve (second person)</button>
            <button v-if="s.status === 'APPROVED'" @click="post('scenario-sets.lock', s.id)" class="secondary-btn text-xs">Lock with the period</button>
            <button v-if="s.status === 'LOCKED' || s.status === 'APPROVED'" @click="newVersion(s.id)" class="secondary-btn text-xs">New version</button>
          </div>
        </div>
        <!-- The editor (system audit of 9 October 2026, finding M5): the proposer sets the weights, adds or removes scenarios and edits the shocks until the set is approved; read-only after that. -->
        <div v-if="editing === s.id && draft" class="border-b border-amber-200 bg-amber-50/60 p-5 dark:border-amber-800 dark:bg-amber-900/20">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h4 class="maiic-section-title mt-0">Editing {{ s.name }} v{{ s.version }} <span class="text-xs font-normal text-gray-500">(the decision the spec reserves for the CFO: weights that sum to 100, the base at least {{ rules.base_floor }}, no weight above {{ rules.single_ceiling }}, at least {{ rules.min_count }} scenarios, a calibration note on every downside)</span></h4>
            <div class="text-sm" :class="Math.abs(weightsSum - 100) < 0.005 ? 'text-maiic-700 dark:text-maiic-300' : 'text-red-700 dark:text-red-300'">Weights sum to {{ weightsSum.toFixed(2) }}</div>
          </div>
          <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3">
            <label class="text-sm"><span class="block text-xs font-semibold text-gray-500">Name</span><input v-model="draft.name" type="text" class="maiic-input w-full"/></label>
            <label class="text-sm md:col-span-2"><span class="block text-xs font-semibold text-gray-500">Source vintage</span><input v-model="draft.source_vintage" type="text" class="maiic-input w-full"/></label>
            <label class="text-sm md:col-span-3"><span class="block text-xs font-semibold text-gray-500">Narrative</span><textarea v-model="draft.narrative" rows="2" class="maiic-input w-full"></textarea></label>
          </div>
          <div class="mt-4 space-y-3">
            <div v-for="(x, i) in draft.scenarios" :key="x.key" class="rounded-lg border border-gray-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-900/40">
              <div class="grid grid-cols-2 gap-3 md:grid-cols-6">
                <label class="text-sm"><span class="block text-xs font-semibold text-gray-500">Scenario</span><input v-model="x.name" type="text" class="maiic-input w-full" maxlength="40"/></label>
                <label class="text-sm"><span class="block text-xs font-semibold text-gray-500">Weight %</span><input v-model.number="x.weight" type="number" step="0.01" min="0" max="100" class="maiic-input w-full"/></label>
                <label class="text-sm"><span class="block text-xs font-semibold text-gray-500">PD multiplier</span><input v-model.number="x.pd_multiplier" type="number" step="0.01" min="0.01" class="maiic-input w-full"/></label>
                <label class="flex items-end gap-2 text-sm"><input type="radio" :name="'base-' + s.id" :checked="x.is_base" @change="setBase(i)"/> <span>the base</span></label>
                <label class="text-sm md:col-span-2"><span class="block text-xs font-semibold text-gray-500">Anchored to</span><input v-model="x.anchored_to" type="text" class="maiic-input w-full"/></label>
                <label class="text-sm col-span-2 md:col-span-5"><span class="block text-xs font-semibold text-gray-500">Calibration note (required on every downside)</span><input v-model="x.calibration_note" type="text" class="maiic-input w-full"/></label>
                <div class="flex items-end justify-end"><button v-if="!x.is_base" type="button" @click="removeScenario(i)" class="secondary-btn text-xs">Remove scenario</button></div>
              </div>
              <div v-if="!x.is_base" class="mt-2">
                <div class="text-xs font-semibold text-gray-500">Shocks on the base path</div>
                <div v-for="(sh, j) in x.shocks" :key="j" class="mt-1 grid grid-cols-2 gap-2 md:grid-cols-6">
                  <input v-model="sh.statistic_code" type="text" class="maiic-input" placeholder="series, e.g. CPI" maxlength="32"/>
                  <select v-model="sh.kind" class="maiic-select"><option value="pct">percent change</option><option value="abs">absolute change</option><option value="replace">replacement value</option><option value="mult">multiplier</option></select>
                  <input v-model.number="sh.value" type="number" step="any" class="maiic-input" placeholder="value"/>
                  <input v-model.number="sh.year_offset" type="number" min="0" max="5" class="maiic-input" placeholder="year offset"/>
                  <input v-model="sh.note" type="text" class="maiic-input" placeholder="note"/>
                  <button type="button" @click="x.shocks.splice(j, 1)" class="secondary-btn text-xs">Remove shock</button>
                </div>
                <button type="button" @click="x.shocks.push({ statistic_code: '', kind: 'abs', value: 0, year_offset: 0, note: '' })" class="secondary-btn mt-1 text-xs">Add a shock</button>
                <p v-if="!x.shocks.length" class="text-xs text-red-700">A scenario without a shock would be the base under another name.</p>
              </div>
            </div>
          </div>
          <div class="mt-3 flex gap-2">
            <button type="button" @click="addScenario" class="secondary-btn text-xs">Add a scenario</button>
            <button type="button" @click="save(s.id)" class="primary-btn text-xs">Save and re-check the rules</button>
            <button type="button" @click="editing = null; draft = null" class="secondary-btn text-xs">Cancel</button>
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
  props: { period: String, sets: Array, rules: Object, periods: Array, overlays: Array, canGovern: Boolean },
  data() { return { form: { period: this.period }, editing: null, draft: null } },
  computed: {
    weightsSum() { return this.draft ? this.draft.scenarios.reduce((a, x) => a + (Number(x.weight) || 0), 0) : 0 },
  },
  methods: {
    fmt(v) { return v == null ? '-' : Number(v).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
    pct(v) { const p = Number(v) * 100; return (p > 0 ? '+' : '') + p.toFixed(2) + '%' },
    // the editor works on a copy of the set; nothing reaches the server until Save
    openEditor(s) {
      this.editing = s.id
      this.draft = { name: s.name, narrative: s.narrative, source_vintage: s.source_vintage, remove: [],
        scenarios: s.scenarios.map(x => ({ key: 'id-' + x.id, id: x.id, name: x.name, weight: Number(x.weight), is_base: !!x.is_base, anchored_to: x.anchored_to, calibration_note: x.calibration_note, narrative: x.narrative, pd_multiplier: x.pd_multiplier == null ? null : Number(x.pd_multiplier),
          shocks: (x.shocks || []).map(sh => ({ statistic_code: sh.statistic_code, kind: sh.kind, value: Number(sh.value), year_offset: Number(sh.year_offset || 0), note: sh.note || '' })) })) }
    },
    setBase(i) { this.draft.scenarios.forEach((x, k) => { x.is_base = k === i }) },
    addScenario() { this.draft.scenarios.push({ key: 'new-' + Date.now(), id: null, name: '', weight: 0, is_base: false, anchored_to: '', calibration_note: '', narrative: '', pd_multiplier: 1, shocks: [] }) },
    removeScenario(i) { const x = this.draft.scenarios[i]; if (x.id) this.draft.remove.push(x.id); this.draft.scenarios.splice(i, 1) },
    save(id) {
      const payload = { name: this.draft.name, narrative: this.draft.narrative, source_vintage: this.draft.source_vintage, remove: this.draft.remove,
        scenarios: this.draft.scenarios.map(x => ({ id: x.id, name: x.name, weight: x.weight, is_base: x.is_base, anchored_to: x.anchored_to, calibration_note: x.calibration_note, narrative: x.narrative, pd_multiplier: x.pd_multiplier, shocks: x.is_base ? [] : x.shocks })) }
      // a refused change comes back as an error flash: the editor stays open with the draft intact
      router.post(route('scenario-sets.update', id), payload, { preserveScroll: true, onSuccess: (page) => { if (!(page.props.flash && page.props.flash.error)) { this.editing = null; this.draft = null } } })
    },
    badge(s) { return s === 'APPROVED' ? 'maiic-badge-green' : (s === 'LOCKED' ? 'maiic-badge-solid-green' : (s === 'PROPOSED' ? 'maiic-badge-gold' : 'maiic-badge-grey')) },
    go() { router.get(route('scenario-sets.index'), { period: this.form.period }) },
    seed() { router.post(route('scenario-sets.seed'), { period: this.form.period }) },
    post(name, id) { router.post(route(name, id), {}, { preserveScroll: true }) },
    newVersion(id) { const reason = prompt('Why a new version? (written to the set and the audit log)'); if (reason) router.post(route('scenario-sets.version', id), { reason }, { preserveScroll: true }) },
  },
}
</script>
