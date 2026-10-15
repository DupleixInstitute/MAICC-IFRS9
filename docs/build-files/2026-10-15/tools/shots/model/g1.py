import os, re, sys
sys.path.insert(0, os.path.dirname(__file__))
from vh import load, save, rep, rx
os.chdir(r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/Governance')

p='Overlays.vue'; s=load(p)
s=rep(s,'''description="The register of judgement added to the forward-looking adjustment: every overlay carries its scope, reason, evidence, owner and expiry, is proposed by one person and approved by another, sits on the period's approved scenario set, and is shown as its own line in the ECL (spec v4 sections 14.6 and 15.7)"''','''description="Judgement added on top of the forward-looking adjustment, each with a reason, an owner and an expiry, approved by a second person"''')
s=rep(s,'''<input v-model="view.period" type="month" class="maiic-input" required/>''','''<input v-model="view.period" type="month" class="maiic-input !w-44" aria-label="Period" required/>''')
s=rep(s,'''<div class="space-y-6">
      <div class="grid grid-cols-1 gap-4 md:grid-cols-4">''','''<div class="space-y-4">
      <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">''')
s=s.replace('<div class="maiic-kpi" style="--accent:#d97706">','<div class="maiic-kpi !py-3" style="--accent:#d97706">')
s=s.replace('class="maiic-kpi-value"','class="text-xl font-bold"')
s=rep(s,'''<div class="maiic-kpi-label">ECL added by the overlays in force</div>''','''<div class="maiic-kpi-label">ECL added by overlays</div>''')
s=rep(s,'''<div class="maiic-kpi-label">Scenario set the overlays sit on</div>''','''<div class="maiic-kpi-label">Scenario set</div>''')
s=rep(s,''': 'none approved for ' + period }}''',''': 'None approved for ' + period }}''')
s=rep(s,'''<div class="border-b border-gray-200 p-5 dark:border-slate-700"><h3 class="text-base font-bold">The register for {{ period }}</h3><p class="text-sm text-gray-500">Every overlay whose life covers the period, newest first. The ECL line is what the overlay adds to the booked ECL of the loans in its scope, measured on the pre-FLI PD under the stage rule of the ECL engine (Stage 3 adds nothing: it is already at 100 percent).</p></div>''','''<div class="border-b border-gray-200 px-5 py-4 dark:border-slate-700"><h3 class="font-semibold text-gray-900">Overlay register for {{ period }}</h3><p class="text-xs text-gray-500">{{ overlays.length }} overlay(s) whose life covers the period, newest first. The ECL line is what each adds to the booked ECL of its loans (Stage 3 adds nothing, it is already at 100 percent).</p></div>''')
s=rep(s,'''<span class="maiic-badge" :class="badge(o.status)">{{ o.status }}</span><div class="text-xs text-gray-500">proposed {{ o.proposer || '-' }}<span v-if="o.approver || o.approver_label"> · approved {{ o.approver || o.approver_label }}</span><span v-if="o.rejecter"> · rejected {{ o.rejecter }}</span></div>''','''<span class="maiic-badge" :class="badge(o.status)">{{ statusLabel(o.status) }}</span><div class="text-xs text-gray-500">Proposed by {{ o.proposer || '-' }}<span v-if="o.approver || o.approver_label">, approved by {{ o.approver || o.approver_label }}</span><span v-if="o.rejecter">, rejected by {{ o.rejecter }}</span></div>''')
s=rep(s,'''<td v-if="canGovern" class="whitespace-nowrap">
                <button v-if="o.status === 'PROPOSED'" @click="post('fli-overlays.approve', o.id)" class="primary-btn text-xs">Approve (second person)</button>
                <button v-if="o.status === 'PROPOSED'" @click="reject(o.id)" class="secondary-btn ml-1 text-xs">Reject</button>
                <button v-if="o.status === 'APPROVED'" @click="expire(o.id)" class="secondary-btn text-xs">Expire now</button>
              </td>''','''<td v-if="canGovern" class="whitespace-nowrap">
                <div class="flex justify-end gap-1.5">
                  <button v-if="o.status === 'PROPOSED'" type="button" @click="post('fli-overlays.approve', o.id)" class="maiic-action maiic-action-view" title="Approve (second person)"><font-awesome-icon icon="check" /></button>
                  <button v-if="o.status === 'PROPOSED'" type="button" @click="reject(o.id)" class="maiic-action maiic-action-delete" title="Reject"><font-awesome-icon icon="times-circle" /></button>
                  <button v-if="o.status === 'APPROVED'" type="button" @click="expire(o.id)" class="maiic-action maiic-action-edit" title="Expire now"><font-awesome-icon icon="calendar" /></button>
                </div>
              </td>''')
s=rep(s,'''<tr v-if="!overlays.length"><td colspan="10" class="maiic-empty">No overlay covers {{ period }}. The forward-looking route reads this register when the Governance Centre setting says "Manual overlay" or "Regression plus overlay".</td></tr>''','''<tr v-if="!overlays.length"><td colspan="10" class="maiic-empty">No overlay covers {{ period }}.<span v-if="canGovern"> Use <strong>Propose an overlay</strong> at the top right.</span> The register is read when the Governance Centre setting says "Manual overlay" or "Regression plus overlay".</td></tr>''')
s=rep(s,'''<div v-if="showForm && canGovern" class="maiic-panel p-5">
        <h3 class="text-base font-bold">Propose an overlay</h3>''','''<div v-if="showForm && canGovern" class="maiic-panel p-5">
        <h3 class="font-semibold text-gray-900">Propose an overlay</h3>''')
s=s.replace('<span class="block text-xs font-semibold text-gray-500">','<span class="maiic-flabel">')
s=rep(s,'''    reject(id) { const reason = prompt('Why is the overlay rejected? (written to the register and the audit log)'); if (reason) this.post('fli-overlays.reject', id, { reason }) },
    expire(id) { const reason = prompt('Why is the overlay withdrawn before its expiry period? (written to the audit log)'); if (reason !== null) this.post('fli-overlays.expire', id, { reason }) },''','''    statusLabel(s) { return { APPROVED: 'Approved', PROPOSED: 'Proposed', REJECTED: 'Rejected', EXPIRED: 'Expired' }[s] || s },
    async reject(id) { const reason = await promptReason({ title: 'Reject this overlay?', message: 'The reason is written to the register and the audit log.', label: 'Why is it rejected?', confirmLabel: 'Reject', tone: 'danger' }); if (reason) this.post('fli-overlays.reject', id, { reason }) },
    async expire(id) { const reason = await promptReason({ title: 'Expire this overlay now?', message: 'It is withdrawn before its expiry period; the reason is written to the audit log.', label: 'Why is it withdrawn early?', confirmLabel: 'Expire now', tone: 'warning', required: false }); if (reason !== null) this.post('fli-overlays.expire', id, { reason }) },''')
s=rep(s,"import { router } from '@inertiajs/vue3'","import { router } from '@inertiajs/vue3'\nimport { promptReason } from '@/Components/Maiic/promptReason'")
save(p,s)

p='ScenarioSets.vue'; s=load(p)
s=rep(s,'''description="One set per reporting period, versioned: proposed by one person, approved by another, locked with the period's ECL; every scenario but the base is a shock on the base path, and the back-test and sensitivity are stored with the set (spec v4 section 15)"''','''description="One versioned set of economic scenarios and weights per period, proposed by one person, approved by another and locked with the ECL"''')
s=rep(s,'''<input v-model="form.period" type="month" class="maiic-input" required/>''','''<input v-model="form.period" type="month" class="maiic-input !w-44" aria-label="Period" required/>''')
s=rep(s,'''Propose the first set (15.8)''','''Propose the first set''')
s=rep(s,'''<div class="space-y-6">
      <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-100">
        <span class="font-semibold">The rules in force</span> (Governance Centre): at least {{ rules.min_count }} scenarios; the base at least {{ rules.base_floor }} percent; no single weight above {{ rules.single_ceiling }} percent; a calibration note on every downside {{ rules.note_required ? 'required' : 'optional' }}; weighting: {{ rules.weighting }}.
      </div>''','''<div class="space-y-4">
      <p class="text-xs text-gray-500">
        <span class="font-semibold text-gray-700">Rules in force (Governance Centre):</span> at least {{ rules.min_count }} scenarios; base at least {{ rules.base_floor }}%; no weight above {{ rules.single_ceiling }}%; calibration note on every downside {{ rules.note_required ? 'required' : 'optional' }}; weighting: {{ rules.weighting }}.
      </p>''')
s=rep(s,'''<span class="font-semibold">Overlays against {{ period }}</span> (the register, spec 15.7):''','''<span class="font-semibold">Overlays against {{ period }}:</span>''')
s=rep(s,'''<div v-if="!sets.length" class="maiic-panel p-6 text-sm text-gray-500">No scenario set for {{ period }}. The first set of specification 15.8 (Base 50, Upside 15, Downside 25, Severe 10, each anchored to a year Malawi has lived through) can be proposed here for Dr Thom to set the weights.</div>''','''<div v-if="!sets.length" class="maiic-panel maiic-empty">No scenario set for {{ period }}.<span v-if="canGovern"> Use <strong>Propose the first set</strong> at the top right: Base 50, Upside 15, Downside 25 and Severe 10, each anchored to a year Malawi has lived through, for the CFO to set the weights.</span></div>''')
s=rep(s,'''still proposed: the set cannot lock until each is approved or rejected (15.7).''','''still proposed: the set cannot lock until each is approved or rejected.''')
s=rep(s,'''(the decision the spec reserves for the CFO: weights that sum to 100,''','''(the CFO's decision: weights that sum to 100,''')
s=rep(s,'''<!-- The editor (system audit of 9 October 2026, finding M5): the proposer''','''<!-- The editor: the proposer''')
s=rep(s,'''    newVersion(id) { const reason = prompt('Why a new version? (written to the set and the audit log)'); if (reason) router.post(route('scenario-sets.version', id), { reason }, { preserveScroll: true }) },''','''    async newVersion(id) { const reason = await promptReason({ title: 'Start a new version?', message: 'The reason is written to the set and the audit log.', label: 'Why a new version?', confirmLabel: 'New version' }); if (reason) router.post(route('scenario-sets.version', id), { reason }, { preserveScroll: true }) },''')
s=rep(s,"import { router } from '@inertiajs/vue3'","import { router } from '@inertiajs/vue3'\nimport { promptReason } from '@/Components/Maiic/promptReason'")
save(p,s)
print('ok')
