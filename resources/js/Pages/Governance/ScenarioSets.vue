<template>
  <app-layout title="Scenario Sets" description="One versioned set of economic scenarios and weights per period, proposed by one person, approved by another and locked with the ECL">
    <template #actions>
      <form @submit.prevent="seed" class="flex items-center gap-2">
        <input v-model="form.period" type="month" class="maiic-input !w-44" aria-label="Period" required/>
        <button v-if="canGovern && !sets.length" type="submit" class="primary-btn text-sm">Propose the first set</button>
        <button type="button" @click="go" class="secondary-btn text-sm">View period</button>
      </form>
    </template>

    <div class="space-y-4">
      <p class="text-xs text-gray-500">
        <span class="font-semibold text-gray-700">Rules in force (Governance Centre):</span> at least {{ rules.min_count }} scenarios; base at least {{ rules.base_floor }}%; no weight above {{ rules.single_ceiling }}%; calibration note on every downside {{ rules.note_required ? 'required' : 'optional' }}; weighting: {{ rules.weighting }}.
      </p>

      <div v-if="overlays && overlays.length" class="-mt-2 text-xs text-gray-600">
        <span class="font-semibold">Overlays against {{ period }}:</span>
        <span v-for="o in overlays" :key="o.id" class="mr-3">#{{ o.id }} {{ o.scope }}{{ o.scope_value ? ' ' + o.scope_value : '' }} {{ pct(o.adjustment) }} <span class="maiic-badge" :class="o.status === 'APPROVED' ? 'maiic-badge-green' : 'maiic-badge-gold'">{{ o.status }}</span></span>
        <a :href="route('fli-overlays.index', { period })" class="text-maiic-700 underline dark:text-maiic-300">open the register</a>
      </div>

      <div v-if="!sets.length" class="maiic-panel maiic-empty">No scenario set for {{ period }}.<span v-if="canGovern"> Use <strong>Propose the first set</strong> at the top right: Base 50, Upside 15, Downside 25 and Severe 10, each anchored to a year Malawi has lived through, for the CFO to set the weights.</span></div>

      <div v-for="s in sets" :key="s.id" class="maiic-panel">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-200 p-5 dark:border-slate-700">
          <div>
            <h3 class="text-base font-bold">{{ s.name }} <span class="text-gray-400">v{{ s.version }}</span> <span class="maiic-badge ml-2" :class="badge(s.status)">{{ s.status }}</span></h3>
            <p class="text-sm text-gray-500">{{ plain(s.narrative) }}</p>
            <p class="text-xs text-gray-500">Sources: {{ s.source_vintage || '-' }} · proposed {{ s.proposer || '-' }} {{ s.proposed_at || '' }} · approved {{ s.approver || '-' }} {{ s.approved_at || '' }} · locked {{ s.locked_at || '-' }}<span v-if="s.supersedes_id"> · supersedes set {{ s.supersedes_id }}: {{ s.version_reason }}</span></p>
            <p v-if="s.validation && !s.validation.ok" class="mt-1 text-xs text-red-700 dark:text-red-300">{{ s.validation.problems.join('; ') }}</p>
            <p v-if="s.validation && s.validation.overlays_pending && s.validation.overlays_pending.length" class="mt-1 text-xs text-amber-700 dark:text-amber-300">Overlay{{ s.validation.overlays_pending.length === 1 ? '' : 's' }} {{ s.validation.overlays_pending.join(', ') }} still proposed: the set cannot lock until each is approved or rejected.</p>
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
        <!-- The editor: the proposer sets the weights, adds or removes scenarios and edits the shocks until the set is approved; read-only after that. -->
        <div v-if="editing === s.id && draft" class="border-b border-amber-200 bg-amber-50/60 p-5 dark:border-amber-800 dark:bg-amber-900/20">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h4 class="maiic-section-title mt-0">Editing {{ s.name }} v{{ s.version }} <span class="text-xs font-normal text-gray-500">(the CFO's decision: weights that sum to 100, the base at least {{ rules.base_floor }}, no weight above {{ rules.single_ceiling }}, at least {{ rules.min_count }} scenarios, a calibration note on every downside)</span></h4>
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
        <div class="space-y-5 p-5">
          <div>
            <h4 class="maiic-section-title mt-0">Scenarios and weights</h4>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
              <div v-for="x in s.scenarios" :key="x.id" class="flex flex-col rounded-lg border p-4 text-sm" :class="x.is_base ? 'border-maiic-300 bg-maiic-50/60' : 'border-gray-200 bg-white'">
                <div class="flex items-start justify-between gap-2">
                  <div class="font-semibold text-gray-900">{{ x.name }} <span v-if="x.is_base && String(x.name).toLowerCase() !== 'base'" class="maiic-badge maiic-badge-grey ml-1">base</span></div>
                  <div class="text-right"><div class="text-2xl font-bold leading-none text-maiic-800">{{ Number(x.weight).toFixed(0) }}%</div><div class="mt-0.5 text-[11px] uppercase tracking-wider text-gray-500">weight</div></div>
                </div>
                <div class="mt-1 text-xs text-gray-500">PD multiplier <strong class="text-gray-800">{{ x.pd_multiplier == null ? '-' : Number(x.pd_multiplier).toFixed(2) }}</strong></div>
                <div v-if="x.anchored_to" class="mt-2 text-xs"><span class="font-semibold text-gray-700">Anchored to:</span> <span class="text-gray-600">{{ plain(x.anchored_to) }}</span></div>
                <div v-if="x.calibration_note" class="mt-1 text-xs"><span class="font-semibold text-gray-700">Calibration:</span> <span class="text-gray-600">{{ plain(x.calibration_note) }}</span></div>
                <div v-if="x.shocks && x.shocks.length" class="mt-2 border-t border-gray-100 pt-2">
                  <div class="text-[11px] uppercase tracking-wider text-gray-500">Shocks on the base path</div>
                  <ul class="mt-1 space-y-0.5 text-xs">
                    <li v-for="sh in x.shocks" :key="sh.id" class="flex justify-between gap-3" :title="sh.note || sh.statistic_code"><span class="text-gray-700">{{ seriesName(sh.statistic_code) }}</span><span class="whitespace-nowrap font-semibold text-gray-900">{{ shock(sh) }}</span></li>
                  </ul>
                </div>
                <div v-else-if="x.is_base" class="mt-2 border-t border-gray-100 pt-2 text-xs text-gray-500">No shocks: the base path as the sources stand.</div>
              </div>
            </div>
          </div>

          <div>
            <h4 class="maiic-section-title mt-0">The paths, first forecast year</h4>
            <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Series</th><th class="num">Base</th><th v-for="name in otherScenarios(s)" :key="name" class="num whitespace-nowrap">{{ name }} <span class="font-normal opacity-80">{{ weightOf(s, name) }}</span></th></tr></thead>
              <tbody><tr v-for="(o, code) in s.paths.base" :key="code"><td :title="code">{{ seriesName(code) }}</td><td class="num">{{ fmt(o[0]) }}</td><td v-for="name in otherScenarios(s)" :key="name" class="num">{{ fmt(s.paths.scenarios[name].path[code] ? s.paths.scenarios[name].path[code][0] : null) }}</td></tr>
              <tr v-if="!Object.keys(s.paths.base || {}).length"><td :colspan="2 + otherScenarios(s).length" class="maiic-empty">No base path for this period yet: load the macro statistics first.</td></tr></tbody></table></div>
          </div>

          <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div v-if="s.sensitivity">
              <h4 class="maiic-section-title mt-0">Sensitivity ({{ s.sensitivity.loans }} loans)</h4>
              <p v-if="s.sensitivity.note" class="text-xs text-amber-700">{{ s.sensitivity.note }}</p>
              <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>ECL under</th><th class="num">Amount</th></tr></thead>
                <tbody><tr v-for="(p, name) in s.sensitivity.per_scenario" :key="name"><td>{{ name }} at 100 percent</td><td class="num">{{ fmt(p.ecl) }}</td></tr>
                <tr class="total"><td>Weighted</td><td class="num">{{ fmt(s.sensitivity.weighted_ecl) }}</td></tr>
                <tr><td>Ten points from the base to the downside</td><td class="num">{{ fmt(s.sensitivity.ten_points_to_downside) }}</td></tr>
                <tr><td>Ten points from the base to the upside</td><td class="num">{{ fmt(s.sensitivity.ten_points_to_upside) }}</td></tr></tbody></table></div>
              <p class="mt-1 text-xs text-gray-500">{{ s.sensitivity.basis }}</p>
            </div>
            <div v-if="s.backtest">
              <h4 class="maiic-section-title mt-0">Back-test</h4>
              <p class="text-xs" :class="s.backtest.review_weights ? 'text-red-700' : 'text-gray-600'">{{ s.backtest.note }}</p>
              <div v-if="s.backtest.rows && s.backtest.rows.length" class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Series</th><th class="num">Predicted base</th><th class="num">Actual</th><th class="num">Miss</th><th>Within range</th></tr></thead>
                <tbody><tr v-for="r in s.backtest.rows" :key="r.series"><td :title="r.series">{{ seriesName(r.series) }}</td><td class="num">{{ fmt(r.predicted_base) }}</td><td class="num">{{ fmt(r.actual) }}</td><td class="num">{{ fmt(r.miss) }}</td><td>{{ r.within_range ? 'yes' : 'NO' }}</td></tr></tbody></table></div>
            </div>
          </div>
        </div>
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
  props: { period: String, sets: Array, rules: Object, periods: Array, overlays: Array, canGovern: Boolean, seriesNames: { type: Object, default: () => ({}) } },
  data() { return { form: { period: this.period }, editing: null, draft: null } },
  computed: {
    weightsSum() { return this.draft ? this.draft.scenarios.reduce((a, x) => a + (Number(x.weight) || 0), 0) : 0 },
  },
  methods: {
    seriesName(code) { return this.seriesNames[code] || code },
    // the scenarios other than the base: the base column is the base path itself
    otherScenarios(s) { const base = (s.scenarios || []).filter(x => x.is_base).map(x => x.name); return Object.keys((s.paths && s.paths.scenarios) || {}).filter(n => !base.includes(n)) },
    weightOf(s, name) { const x = (s.scenarios || []).find(y => y.name === name); return x ? Number(x.weight).toFixed(0) + '%' : '' },
    shock(sh) {
      const v = Number(sh.value); const sign = v > 0 ? '+' : ''; const num = v.toLocaleString('en-GB', { maximumFractionDigits: 4 })
      const t = sh.kind === 'pct' ? sign + num + '%' : (sh.kind === 'abs' ? sign + num + ' points' : (sh.kind === 'replace' ? 'set to ' + num : (sh.kind === 'mult' ? 'times ' + num : sh.kind + ' ' + num)))
      return Number(sh.year_offset || 0) > 0 ? t + ' (year ' + (Number(sh.year_offset) + 1) + ')' : t
    },
    // specification references are for the build team, not the screen
    plain(t) { return String(t || '').replace(/\s*\((?:spec(?:ification)? v\d+ section [\d.]*\d[^)]*|O\d+|decision D\d+)\)/gi, '').replace(/\s+(?:of\s+)?(?:the\s+)?spec(?:ification)? v\d+ section [\d.]*\d/gi, '').replace(/\s+of section [\d.]*\d/g, '') },
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
    async newVersion(id) { const reason = await promptReason({ title: 'Start a new version?', message: 'The reason is written to the set and the audit log.', label: 'Why a new version?', confirmLabel: 'New version' }); if (reason) router.post(route('scenario-sets.version', id), { reason }, { preserveScroll: true }) },
  },
}
</script>
