<template>
  <app-layout title="Restructures & Resets" description="Candidate restructures found in E-Banker's reschedule history, the register where two people confirm them, what the EIR revenue booked for each, and every move of a floating loan's rate">
    <template #actions>
      <button v-if="canGovern" type="button" class="primary-btn" :disabled="busy" @click="detect"><font-awesome-icon icon="search" /> {{ busy ? 'Reading the history...' : 'Find candidates' }}</button>
    </template>

    <div class="w-full space-y-5">
      <KpiRow :cards="cards" />

      <Notice id="candidate-not-confirmed" tone="warning">
        A candidate is evidence that a loan's terms may have changed, never a confirmed restructure. Nothing here reaches the accounts until one person classifies it with a reason and a different person approves. The engine's suggestion is only a suggestion.
      </Notice>

      <PageTabs v-model="current" :tabs="tabs" query="tab" />

      <!-- the restructure register -->
      <template v-if="current === 'register'">
        <div class="maiic-filterbar flex flex-wrap items-end gap-3">
          <div>
            <label class="maiic-flabel" for="rs-status">Status</label>
            <select id="rs-status" v-model="filters.status" class="maiic-select">
              <option value="">All</option><option value="CANDIDATE">Candidates</option><option value="PROPOSED">Proposed</option><option value="CONFIRMED">Confirmed</option>
            </select>
          </div>
          <div>
            <label class="maiic-flabel" for="rs-suggest">Engine's suggestion</label>
            <select id="rs-suggest" v-model="filters.suggested" class="maiic-select">
              <option value="">All</option><option v-for="s in suggestions" :key="s" :value="s">{{ classLabel(s) }}</option>
            </select>
          </div>
          <div>
            <label class="maiic-flabel" for="rs-source">Evidence</label>
            <select id="rs-source" v-model="filters.source" class="maiic-select">
              <option value="">All</option><option v-for="(l, k) in sourceLabels" :key="k" :value="k">{{ l }}</option>
            </select>
          </div>
          <div class="min-w-[14rem] flex-1">
            <label class="maiic-flabel" for="rs-search">Loan</label>
            <input id="rs-search" v-model="filters.search" type="search" class="maiic-input" placeholder="Name or contract" />
          </div>
          <label class="flex items-center gap-2 pb-2 text-sm text-gray-700"><input v-model="filters.bookable" type="checkbox" /> Only those that can be booked</label>
        </div>

        <div class="maiic-panel">
          <div class="border-b border-gray-200 p-4 dark:border-slate-700">
            <h3 class="font-semibold text-gray-900 dark:text-slate-100">Restructure register</h3>
            <p class="mt-1 text-xs text-gray-500">From the instalment chart history (RS_02a and the live chart P2_10), the dated copies of the chart (RS_02b to RS_02e), MODIFICATION_LOG (RS_02f) and the rate changes of fixed-rate loans. A regeneration that only re-amortises the balance for a PLR change is a reset, on the Rate resets tab, not here.</p>
          </div>
          <div class="maiic-table-wrap">
            <table class="maiic-table maiic-table-compact">
              <thead><tr><th>#</th><th>Loan</th><th>Date</th><th>Evidence</th><th>What changed</th><th>Engine's suggestion</th><th>Status</th><th>Accounting</th><th class="text-right">Actions</th></tr></thead>
              <tbody>
                <tr v-for="e in pagedEvents" :key="e.id">
                  <td class="font-semibold">#{{ e.id }}</td>
                  <td><div class="font-semibold">{{ e.customer_name || '-' }}</div><div class="text-xs text-gray-500">{{ e.contract_id }}</div></td>
                  <td class="whitespace-nowrap">{{ e.event_date || 'No date' }}</td>
                  <td class="min-w-[12rem] max-w-[16rem] text-xs"><div class="font-semibold">{{ sourceLabels[e.source] || e.source }}</div><div class="text-gray-500">{{ e.source_reference }}</div></td>
                  <td class="max-w-[16rem] text-xs">
                    <div v-if="e.old_maturity || e.new_maturity">Maturity {{ e.old_maturity || '-' }} to {{ e.new_maturity || '-' }}</div>
                    <div v-if="e.old_instalments !== null || e.new_instalments !== null">Instalments due {{ e.old_instalments ?? '-' }} to {{ e.new_instalments ?? '-' }}</div>
                    <div v-if="e.old_instalment || e.new_instalment">Instalment {{ money(e.old_instalment) }} to {{ money(e.new_instalment) }}</div>
                    <div v-if="e.old_balance !== null && e.new_balance !== null">Balance {{ money(e.old_balance) }} to {{ money(e.new_balance) }}</div>
                    <div v-if="Number(e.capitalised_amount) > 0" class="font-semibold text-amber-700 dark:text-amber-300">Capitalised {{ money(e.capitalised_amount) }}</div>
                    <div v-if="e.old_rate !== null || e.new_rate !== null">Rate {{ pct(e.old_rate) }} to {{ pct(e.new_rate) }}</div>
                  </td>
                  <td class="max-w-[18rem] text-xs">
                    <span class="maiic-badge" :class="suggestBadge(e.suggested_class)">{{ classLabel(e.suggested_class) }}</span>
                    <div class="mt-1 text-gray-600">{{ e.suggestion_reason }}</div>
                    <div v-if="e.incomplete_reason" class="mt-1 font-semibold text-red-700 dark:text-red-300">Cannot be booked: {{ e.incomplete_reason }}</div>
                  </td>
                  <td class="text-xs">
                    <span class="maiic-badge" :class="statusBadge(e.status)">{{ statusLabel(e.status) }}</span>
                    <div v-if="e.classification" class="mt-1 font-semibold">{{ classLabel(e.classification) }}</div>
                    <div v-if="e.reason" class="text-gray-600">{{ e.reason }}</div>
                    <div v-if="e.board_approval_ref" class="text-gray-500">Board: {{ e.board_approval_ref }}<span v-if="e.board_approval_date">, {{ e.board_approval_date }}</span></div>
                    <div v-else-if="e.classification === 'MODIFICATION'" class="text-amber-700 dark:text-amber-300">No Board approval recorded (directive 16(1))</div>
                    <div v-if="e.qualitative_factors.length" class="text-gray-500">Qualitative: {{ e.qualitative_factors.map(f => factors[f] || f).join('; ') }}</div>
                    <div v-if="e.proposed_by_name" class="mt-1 text-gray-500">Proposed by {{ e.proposed_by_name }}</div>
                    <div v-if="e.approved_by_name" class="text-gray-500">Approved by {{ e.approved_by_name }}</div>
                    <div v-if="e.close_reason && e.status === 'CANDIDATE'" class="text-gray-500">{{ e.closed_by_name }}: {{ e.close_reason }}</div>
                  </td>
                  <td class="max-w-[15rem] text-xs">
                    <template v-if="e.modification">
                      <span class="maiic-badge" :class="modBadge(e.modification.status)">{{ modLabel(e.modification.status) }}</span>
                      <div class="mt-1">Version {{ e.modification.old_schedule_version }} to {{ e.modification.new_schedule_version }}, {{ e.modification.reporting_period }}</div>
                      <template v-if="e.modification.status === 'APPLIED'">
                        <div class="font-semibold">{{ treatmentLabel(e.modification.treatment) }}</div>
                        <div>Before {{ money(e.modification.carrying_amount_before) }}; after {{ money(e.modification.carrying_amount_after) }}</div>
                        <div v-if="e.modification.change_pct !== null">Change {{ Number(e.modification.change_pct).toFixed(2) }}% against {{ Number(e.modification.threshold_pct).toFixed(2) }}%</div>
                        <div :class="Number(e.modification.gain_loss) < 0 ? 'text-red-700 dark:text-red-300' : 'text-maiic-700 dark:text-maiic-300'">{{ e.modification.treatment === 'CATCH_UP' ? 'Catch-up' : 'Gain or loss' }} {{ money(e.modification.gain_loss) }}</div>
                        <div v-if="e.modification.revised_eir">EIR from now {{ (Number(e.modification.revised_eir) * 100).toFixed(4) }}%</div>
                      </template>
                      <div v-if="e.modification.blocked_reason" class="mt-1 text-red-700 dark:text-red-300">{{ e.modification.blocked_reason }}</div>
                    </template>
                    <span v-else-if="e.status === 'CONFIRMED'" class="text-gray-500">No accounting: {{ noAccounting(e) }}</span>
                    <span v-else class="text-gray-400">-</span>
                  </td>
                  <td class="text-right">
                    <div v-if="canGovern" class="flex justify-end gap-1.5">
                      <button v-if="e.status === 'CANDIDATE'" type="button" class="maiic-action maiic-action-edit" title="Classify" @click="openPropose(e)"><font-awesome-icon icon="pen" /></button>
                      <button v-if="e.status === 'PROPOSED' && e.proposed_by !== me" type="button" class="maiic-action maiic-action-view" title="Approve" @click="approve(e)"><font-awesome-icon icon="check" /></button>
                      <button v-if="e.status === 'PROPOSED' && e.proposed_by !== me" type="button" class="maiic-action maiic-action-delete" title="Reject" @click="closeIt(e, 'reject')"><font-awesome-icon icon="times" /></button>
                      <button v-if="e.status === 'PROPOSED' && e.proposed_by === me" type="button" class="maiic-action maiic-action-edit" title="Withdraw" @click="closeIt(e, 'withdraw')"><font-awesome-icon icon="ban" /></button>
                      <button v-if="e.status === 'CONFIRMED'" type="button" class="maiic-action maiic-action-edit" title="Reverse" @click="closeIt(e, 'reverse')"><font-awesome-icon icon="history" /></button>
                    </div>
                  </td>
                </tr>
                <tr v-if="!events.length"><td colspan="9" class="maiic-empty">No candidate has been found yet. <template v-if="canGovern">Press <strong>Find candidates</strong> at the top right to read the reschedule history (RS_02a to RS_02f) in the landing zone.</template><template v-else>A user who governs the EIR runs the search.</template></td></tr>
                <tr v-else-if="!filteredEvents.length"><td colspan="9" class="maiic-empty">No event matches these filters.</td></tr>
              </tbody>
            </table>
          </div>
          <div v-if="filteredEvents.length > 15" class="border-t border-gray-100 px-4 py-3 dark:border-slate-700"><ClientPager v-model="eventPage" :total="filteredEvents.length" /></div>
        </div>
      </template>

      <!-- the modifications the IFRS 9 note discloses: its own tab (navigation review of 15 October 2026) -->
      <template v-else-if="current === 'note'">
        <div class="maiic-panel p-4">
          <h3 class="font-semibold text-gray-900 dark:text-slate-100">Modifications in the IFRS 9 note, {{ summary.disclosure_year }} (IFRS 7.35J and 7.20A)</h3>
          <p v-if="!disclosureCount" class="mt-1 text-sm text-gray-500">No confirmed modification has been booked in {{ summary.disclosure_year }}. A confirmed event is booked by the EIR revenue run of its month.<span v-if="summary.disclosures.pending"> {{ summary.disclosures.pending }} confirmed event(s) await that run.</span><span v-if="summary.disclosures.blocked"> {{ summary.disclosures.blocked }} could not be booked; the reason is on the row.</span></p>
          <table v-else class="maiic-table maiic-table-compact mt-3">
            <thead><tr><th>Line</th><th class="num">Loans</th><th class="num">Amount before</th><th class="num">Gain or loss</th></tr></thead>
            <tbody>
              <tr><td>Modified while the allowance was lifetime ECL (35J(a))</td><td class="num">{{ summary.disclosures.lifetime_ecl_modified.count }}</td><td class="num">{{ money(summary.disclosures.lifetime_ecl_modified.amortised_cost_before) }}</td><td class="num">{{ money(summary.disclosures.lifetime_ecl_modified.net_gain_loss) }}</td></tr>
              <tr><td>All modifications not derecognised</td><td class="num">{{ summary.disclosures.all_modified.count }}</td><td class="num">{{ money(summary.disclosures.all_modified.amortised_cost_before) }}</td><td class="num">{{ money(summary.disclosures.all_modified.net_gain_loss) }}</td></tr>
              <tr><td>Derecognised on a substantial modification (20A)</td><td class="num">{{ summary.disclosures.derecognised.count }}</td><td class="num">{{ money(summary.disclosures.derecognised.carrying_amount) }}</td><td class="num">{{ money(summary.disclosures.derecognised.gain_loss) }}</td></tr>
              <tr><td>Revised estimates, the B5.4.6 catch-up</td><td class="num">{{ summary.disclosures.catch_up.count }}</td><td class="num">-</td><td class="num">{{ money(summary.disclosures.catch_up.adjustment) }}</td></tr>
              <tr><td>Modified while lifetime ECL, now on 12-month ECL (35J(b)), gross carrying amount</td><td class="num">{{ summary.disclosures.lifetime_modified_now_12_month.count }}</td><td class="num">{{ money(summary.disclosures.lifetime_modified_now_12_month.gross_carrying_amount) }}</td><td class="num">-</td></tr>
            </tbody>
          </table>
        </div>
      </template>

      <!-- the rate-reset register -->
      <template v-else>
        <div class="maiic-filterbar flex flex-wrap items-end gap-3">
          <div>
            <label class="maiic-flabel" for="rr-status">Classification</label>
            <select id="rr-status" v-model="resetFilters.status" class="maiic-select">
              <option value="">All</option><option value="DETECTED">Awaiting a classification</option><option value="PROPOSED">Proposed</option><option value="APPROVED">Approved</option>
            </select>
          </div>
          <div>
            <label class="maiic-flabel" for="rr-type">Type</label>
            <select id="rr-type" v-model="resetFilters.type" class="maiic-select">
              <option value="">All</option><option value="PLR_RESET">Moved with the PLR</option><option value="SPREAD_CHANGE">Spread changed</option>
            </select>
          </div>
          <div class="min-w-[14rem] flex-1">
            <label class="maiic-flabel" for="rr-search">Loan</label>
            <input id="rr-search" v-model="resetFilters.search" type="search" class="maiic-input" placeholder="Name or contract" />
          </div>
          <div v-if="canGovern" class="flex flex-wrap gap-2 pb-0.5">
            <button type="button" class="secondary-btn" :disabled="!selectedDetected.length" @click="proposeResets('IN_CONTRACT')">Propose as in-contract ({{ selectedDetected.length }})</button>
            <button type="button" class="secondary-btn" :disabled="!selectedDetected.length" @click="proposeResets('NEGOTIATED')">Propose as negotiated ({{ selectedDetected.length }})</button>
            <button type="button" class="primary-btn" :disabled="!selectedApprovable.length" @click="approveResets">Approve ({{ selectedApprovable.length }})</button>
            <button type="button" class="secondary-btn" :disabled="building" title="Re-amortise the remaining instalments of each floating loan at each prime-rate reset, as a Reset schedule version" @click="buildResets">{{ building ? 'Building...' : 'Build reset schedules' }}</button>
          </div>
        </div>

        <!-- the last reset schedule build: what was built, kept and why a loan was held -->
        <div v-if="resetBuild" class="maiic-panel p-4 text-sm">
          <div class="font-semibold text-gray-900 dark:text-slate-100">Reset schedules: {{ resetBuild.built }} built, {{ resetBuild.kept }} kept, {{ resetBuild.blocked }} held, across {{ resetBuild.contracts }} loan(s)</div>
          <p class="mt-1 text-xs text-gray-500">A version already used by a solved or locked month is kept and built on; the rest are rebuilt. The EIR itself is unchanged: a floating loan already accrues at the rate charged each month.</p>
          <ul v-if="resetBuild.held.length" class="mt-2 space-y-0.5 text-xs text-gray-700 dark:text-slate-300">
            <li v-for="h in resetBuild.held" :key="h.contract_id"><span class="font-medium text-gray-900 dark:text-slate-100">{{ h.contract_id }}</span>: {{ h.reason }}</li>
          </ul>
        </div>

        <div class="maiic-panel">
          <div class="border-b border-gray-200 p-4 dark:border-slate-700">
            <h3 class="font-semibold text-gray-900 dark:text-slate-100">Rate resets</h3>
            <p class="mt-1 text-xs text-gray-500">Each prime-rate reset of a floating loan, found once from the PLR series on the spread locked at drawdown, set against the rate the loan book shows E-Banker charged; and each change of the spread, as its own event even on the date of a PLR reset. A move the reset pipeline cannot take (the loan is held there, or the engine treats it as fixed-rate) is still recorded, marked held, with the reason. The EIR already follows the rate charged each month on a floating loan (decision D38, IFRS 9 B5.4.5, no catch-up); this register records each move and its classification. In-contract: a move the contract provides for. Negotiated: a re-pricing agreed outside it, which becomes a restructure candidate.</p>
          </div>
          <div class="maiic-table-wrap">
            <table class="maiic-table maiic-table-compact">
              <thead><tr><th class="w-8"><input type="checkbox" :checked="allOnPage" aria-label="Select the page" @change="togglePage($event.target.checked)" /></th><th>Loan</th><th>Month</th><th>Type</th><th class="num">Rate</th><th class="num">PLR</th><th class="num">Spread</th><th>Evidence</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
              <tbody>
                <tr v-for="r in pagedResets" :key="r.id">
                  <td><input v-if="r.classification_status !== 'APPROVED'" v-model="selected" type="checkbox" :value="r.id" :aria-label="'Select reset ' + r.id" /></td>
                  <td><div class="font-semibold">{{ r.customer_name || '-' }}</div><div class="text-xs text-gray-500">{{ r.contract_id }}</div></td>
                  <td class="whitespace-nowrap">{{ r.reporting_period }}<div class="text-xs text-gray-500">from {{ r.reset_date }}</div></td>
                  <td class="text-xs"><span class="maiic-badge" :class="r.event_type === 'PLR_RESET' ? 'maiic-badge-green' : 'maiic-badge-gold'">{{ r.event_type === 'PLR_RESET' ? 'Moved with the PLR' : 'Spread changed' }}</span></td>
                  <td class="num whitespace-nowrap">{{ pct(r.old_rate) }} to {{ pct(r.new_rate) }}</td>
                  <td class="num whitespace-nowrap">{{ pct(r.old_reference_rate) }} to {{ pct(r.new_reference_rate) }}</td>
                  <td class="num whitespace-nowrap">{{ pct(r.old_spread) }} to {{ pct(r.new_spread) }}</td>
                  <td class="min-w-[16rem] max-w-[22rem] text-xs text-gray-600">{{ r.evidence }}</td>
                  <td class="text-xs">
                    <span class="maiic-badge" :class="resetBadge(r.classification_status)">{{ resetLabel(r.classification_status) }}</span>
                    <div v-if="r.classification" class="mt-1 font-semibold">{{ r.classification === 'IN_CONTRACT' ? 'In-contract reset' : 'Negotiated' }}</div>
                    <div v-else-if="r.suggested_classification" class="mt-1 text-gray-500">Suggested: in-contract</div>
                    <div v-if="r.reason" class="text-gray-600">{{ r.reason }}</div>
                    <div v-if="r.proposed_by_name" class="text-gray-500">Proposed by {{ r.proposed_by_name }}</div>
                    <div v-if="r.approved_by_name" class="text-gray-500">Approved by {{ r.approved_by_name }}</div>
                    <div v-if="r.restructure_event_id" class="text-gray-500">Restructure candidate #{{ r.restructure_event_id }}</div>
                    <div v-if="r.close_reason && r.classification_status === 'DETECTED'" class="text-gray-500">{{ r.close_reason }}</div>
                    <div class="mt-1 text-gray-500 dark:text-slate-400" :title="r.note || ''">Reset pipeline: {{ pipelineLabel(r) }}<template v-if="r.new_schedule_version">, schedule version {{ r.new_schedule_version }}</template></div>
                    <div v-if="r.status === 'BLOCKED' && r.note" class="text-amber-700 dark:text-amber-300">{{ r.note }}</div>
                  </td>
                  <td class="text-right">
                    <div v-if="canGovern && r.classification_status === 'PROPOSED'" class="flex justify-end gap-1.5">
                      <button v-if="r.proposed_by !== me" type="button" class="maiic-action maiic-action-delete" title="Reject" @click="closeReset(r, 'reject')"><font-awesome-icon icon="times" /></button>
                      <button v-else type="button" class="maiic-action maiic-action-edit" title="Withdraw" @click="closeReset(r, 'withdraw')"><font-awesome-icon icon="ban" /></button>
                    </div>
                  </td>
                </tr>
                <tr v-if="!resets.length"><td colspan="10" class="maiic-empty">No rate move is recorded yet. <template v-if="canGovern">Press <strong>Find candidates</strong> at the top right: it also reads the loan-book rates and the PLR series.</template></td></tr>
                <tr v-else-if="!filteredResets.length"><td colspan="10" class="maiic-empty">No rate move matches these filters.</td></tr>
              </tbody>
            </table>
          </div>
          <div v-if="filteredResets.length > 15" class="border-t border-gray-100 px-4 py-3 dark:border-slate-700"><ClientPager v-model="resetPage" :total="filteredResets.length" /></div>
        </div>
      </template>
    </div>

    <jet-modal :show="open" max-width="2xl" @close="open = false">
      <form v-if="target" class="p-6" @submit.prevent="submit">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Classify event #{{ target.id }}</h3>
        <p class="mt-1 text-sm text-gray-600">{{ target.customer_name }} ({{ target.contract_id }}), {{ target.event_date || 'no date' }}. The engine suggests <strong>{{ classLabel(target.suggested_class) }}</strong>: {{ target.suggestion_reason }}</p>
        <p v-if="target.incomplete_reason" class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-200">Cannot be booked: {{ target.incomplete_reason }} It can still be classified as a correction or as not a change.</p>
        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
          <div class="md:col-span-2">
            <label class="maiic-flabel" for="rs-class">Classification</label>
            <select id="rs-class" v-model="form.classification" class="maiic-select">
              <option value="">Choose the classification</option>
              <option v-for="c in classifications" :key="c" :value="c">{{ classLabel(c) }}</option>
            </select>
            <p class="mt-1 text-xs text-gray-500">{{ classHelp[form.classification] || 'Modification: a change of the contract (IFRS 9 5.4.3). Revised estimate: new expected flows under the same contract (B5.4.6). Contractual reset: a change the contract provides for. Correction or not a change: no accounting.' }}</p>
            <p v-if="form.errors.classification" class="mt-1 text-xs text-red-600">{{ form.errors.classification }}</p>
          </div>
          <div class="md:col-span-2">
            <label class="maiic-flabel" for="rs-reason">Reason</label>
            <textarea id="rs-reason" v-model="form.reason" rows="3" class="maiic-input" placeholder="Why: the restructure agreement, the Credit committee paper, the correction ticket"></textarea>
            <p v-if="form.errors.reason" class="mt-1 text-xs text-red-600">{{ form.errors.reason }}</p>
          </div>
          <template v-if="form.classification === 'MODIFICATION'">
            <div>
              <label class="maiic-flabel" for="rs-board">Board approval (directive 16(1))</label>
              <input id="rs-board" v-model="form.board_approval_ref" type="text" class="maiic-input" placeholder="Board or credit committee minute" />
            </div>
            <div>
              <label class="maiic-flabel" for="rs-board-date">Date of the approval</label>
              <input id="rs-board-date" v-model="form.board_approval_date" type="date" class="maiic-input" />
            </div>
            <div class="md:col-span-2">
              <span class="maiic-flabel">Qualitative factors: any one derecognises the loan whatever the 10 percent test says</span>
              <label v-for="(l, k) in factors" :key="k" class="mt-1 flex items-center gap-2 text-sm text-gray-700"><input v-model="form.qualitative_factors" type="checkbox" :value="k" /> {{ l }}</label>
            </div>
            <div>
              <label class="maiic-flabel" for="rs-fv">Fair value of the new loan (only if derecognised)</label>
              <input id="rs-fv" v-model="form.fair_value" type="number" step="0.01" min="0" class="maiic-input" />
              <p v-if="form.errors.fair_value" class="mt-1 text-xs text-red-600">{{ form.errors.fair_value }}</p>
            </div>
            <div>
              <label class="maiic-flabel" for="rs-fvb">Basis of the fair value</label>
              <input id="rs-fvb" v-model="form.fair_value_basis" type="text" class="maiic-input" placeholder="For example the new flows at the market rate for the borrower" />
              <p v-if="form.errors.fair_value_basis" class="mt-1 text-xs text-red-600">{{ form.errors.fair_value_basis }}</p>
            </div>
            <div class="md:col-span-2">
              <label class="maiic-flabel" for="rs-fees">Fees received (+) or costs incurred (-) on the modification</label>
              <input id="rs-fees" v-model="form.modification_fees" type="number" step="0.01" class="maiic-input" />
              <p class="mt-1 text-xs text-gray-500">They adjust the carrying amount and are spread over the remaining term through a revised EIR (IFRS 9 5.4.3). Leave blank when there were none.</p>
            </div>
          </template>
        </div>
        <div class="mt-6 flex justify-end gap-2">
          <button type="button" class="secondary-btn" :disabled="form.processing" @click="open = false">Cancel</button>
          <button type="submit" class="primary-btn" :disabled="form.processing || !form.classification">Propose</button>
        </div>
      </form>
    </jet-modal>
  </app-layout>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import JetModal from '@/Jetstream/Modal.vue'
import KpiRow from '@/Components/Maiic/KpiRow.vue'
import PageTabs from '@/Components/Maiic/PageTabs.vue'
import Notice from '@/Components/Maiic/Notice.vue'
import ClientPager from '@/Components/ClientPager.vue'
import { confirmDialog } from '@/Components/confirmDialog'
import { promptReason } from '@/Components/Maiic/promptReason.js'

const props = defineProps({
  tab: { type: String, default: 'register' },
  events: { type: Array, default: () => [] },
  resets: { type: Array, default: () => [] },
  me: { type: Number, default: null },
  canGovern: { type: Boolean, default: false },
  classifications: { type: Array, default: () => [] },
  factors: { type: Object, default: () => ({}) },
  summary: { type: Object, default: () => ({ disclosures: {} }) },
  resetBuild: { type: Object, default: null },
})

const current = ref(props.tab)
const tabs = computed(() => [
  { key: 'register', label: 'Restructure register', count: props.events.length },
  { key: 'note', label: 'IFRS 9 note', count: null },
  { key: 'resets', label: 'Rate resets', count: props.resets.length },
])
const sourceLabels = { CHART_REPLACED: 'Chart regenerated', CHART_COPY: 'Dated copies of the chart', MODIFICATION_LOG: 'MODIFICATION_LOG edit', RATE_CHANGE: 'Rate change', OPENING_BAL: 'Migration worksheet (CV_10)' }
const TAKEON = '2024-07-31'
const suggestions = ['MODIFICATION', 'UNCERTAIN', 'REVISED_ESTIMATE', 'CONTRACTUAL_RESET', 'CORRECTION']
const classHelp = {
  MODIFICATION: 'A change of the contract (IFRS 9 5.4.3). Schedule version N+1 is loaded and the revenue run of the month tests it: below the modification threshold (O16) with no qualitative factor, a modification gain or loss; otherwise derecognition and a new loan at its fair value with a new EIR.',
  REVISED_ESTIMATE: 'New expected flows under the same contract, for example a prepayment re-spread (B5.4.6): version N+1 and the catch-up to profit or loss.',
  CONTRACTUAL_RESET: 'A change the contract provides for: on a floating loan the monthly re-estimation already carries it (B5.4.5, no catch-up); on a fixed-rate loan the revised flows take the B5.4.6 catch-up.',
  CORRECTION: 'A correction of E-Banker\'s record: no accounting.',
  NOT_A_CHANGE: 'The evidence did not change the contract: no accounting.',
}

// the register
const filters = reactive({ status: '', suggested: '', source: '', search: '', bookable: false })
const eventPage = ref(1)
const filteredEvents = computed(() => props.events.filter(e =>
  (!filters.status || e.status === filters.status) && (!filters.suggested || e.suggested_class === filters.suggested) && (!filters.source || e.source === filters.source)
  && (!filters.bookable || !e.incomplete_reason)
  && (!filters.search || `${e.customer_name || ''} ${e.contract_id}`.toLowerCase().includes(filters.search.toLowerCase()))))
const pagedEvents = computed(() => filteredEvents.value.slice((eventPage.value - 1) * 15, eventPage.value * 15))
watch(filters, () => { eventPage.value = 1 })

const open = ref(false)
const target = ref(null)
const form = useForm({ classification: '', reason: '', qualitative_factors: [], board_approval_ref: '', board_approval_date: '', fair_value: '', fair_value_basis: '', modification_fees: '' })
function openPropose(e) { target.value = e; form.reset(); form.clearErrors(); open.value = true }
function submit() {
  form.post(route('eir-restructures.propose', target.value.id), { preserveScroll: true, onSuccess: () => { if (!Object.keys(form.errors).length) open.value = false } })
}
async function approve(e) {
  const books = ['MODIFICATION', 'REVISED_ESTIMATE'].includes(e.classification) || e.classification === 'CONTRACTUAL_RESET'
  if (!(await confirmDialog({ title: `Approve event #${e.id}?`, message: `${e.customer_name}: ${classLabel(e.classification)}${e.event_date ? ' of ' + e.event_date : ''}. ${books ? 'Where it changes the accounting, E-Banker\'s new schedule is loaded as the next version and the revenue run of its month books it.' : 'It changes no figure.'}`, confirmLabel: 'Approve' }))) return
  router.post(route('eir-restructures.approve', e.id), {}, { preserveScroll: true })
}
async function closeIt(e, action) {
  const words = { reject: 'Reject', withdraw: 'Withdraw', reverse: 'Reverse' }
  const reason = await promptReason({ title: `${words[action]} event #${e.id}`, message: action === 'reverse' ? 'The booked restructure and its schedule version are removed while its month is open; the event becomes a candidate again. Re-run the revenue from its month.' : 'The event becomes a candidate again; the reason is kept and in the audit trail.', confirmLabel: words[action], tone: action === 'reverse' ? 'danger' : 'primary' })
  if (!reason) return
  router.post(route('eir-restructures.close', e.id), { action, reason }, { preserveScroll: true })
}

// the resets
const resetFilters = reactive({ status: '', type: '', search: '' })
const resetPage = ref(1)
const selected = ref([])
const filteredResets = computed(() => props.resets.filter(r =>
  (!resetFilters.status || r.classification_status === resetFilters.status) && (!resetFilters.type || r.event_type === resetFilters.type)
  && (!resetFilters.search || `${r.customer_name || ''} ${r.contract_id}`.toLowerCase().includes(resetFilters.search.toLowerCase()))))
const pagedResets = computed(() => filteredResets.value.slice((resetPage.value - 1) * 15, resetPage.value * 15))
watch(resetFilters, () => { resetPage.value = 1; selected.value = [] })
const byId = computed(() => Object.fromEntries(props.resets.map(r => [r.id, r])))
const selectedDetected = computed(() => selected.value.filter(id => byId.value[id]?.classification_status === 'DETECTED'))
const selectedApprovable = computed(() => selected.value.filter(id => byId.value[id]?.classification_status === 'PROPOSED' && byId.value[id]?.proposed_by !== props.me))
const allOnPage = computed(() => pagedResets.value.filter(r => r.classification_status !== 'APPROVED').length > 0 && pagedResets.value.filter(r => r.classification_status !== 'APPROVED').every(r => selected.value.includes(r.id)))
function togglePage(on) {
  const ids = pagedResets.value.filter(r => r.classification_status !== 'APPROVED').map(r => r.id)
  selected.value = on ? [...new Set([...selected.value, ...ids])] : selected.value.filter(id => !ids.includes(id))
}
async function proposeResets(classification) {
  const reason = await promptReason({ title: `Propose ${selectedDetected.value.length} rate move(s) as ${classification === 'IN_CONTRACT' ? 'in-contract resets' : 'negotiated'}`, message: classification === 'IN_CONTRACT' ? 'A move the contract provides for: the PLR changed, or MAIIC varied the spread under a clause that allows it.' : 'A re-pricing agreed with the borrower outside the contract: on approval it becomes a restructure candidate (IFRS 9 5.4.3).', confirmLabel: 'Propose' })
  if (!reason) return
  router.post(route('eir-restructures.resets.propose'), { ids: selectedDetected.value, classification, reason }, { preserveScroll: true, onSuccess: () => { selected.value = [] } })
}
async function approveResets() {
  if (!(await confirmDialog({ title: `Approve ${selectedApprovable.value.length} rate move(s)?`, message: 'You are approving the classification another person proposed. A move in a locked month is refused.', confirmLabel: 'Approve' }))) return
  router.post(route('eir-restructures.resets.approve'), { ids: selectedApprovable.value }, { preserveScroll: true, onSuccess: () => { selected.value = [] } })
}
async function closeReset(r, action) {
  const reason = await promptReason({ title: `${action === 'reject' ? 'Reject' : 'Withdraw'} the classification of move #${r.id}`, message: 'It returns to awaiting a classification; the reason is kept.', confirmLabel: action === 'reject' ? 'Reject' : 'Withdraw' })
  if (!reason) return
  router.post(route('eir-restructures.resets.close', r.id), { action, reason }, { preserveScroll: true })
}

// the same as eir:build-reset-schedules: a Reset schedule version at each prime-rate reset of a floating loan
const building = ref(false)
async function buildResets() {
  if (!(await confirmDialog({ title: 'Build the reset schedules?', message: 'For each floating loan, the remaining instalments are re-amortised at each prime-rate reset found, as a Reset version of the schedule. Version 1 and any version a solved or locked month used are kept as they are; the EIR is not changed. It is recorded in the audit log.', confirmLabel: 'Build' }))) return
  building.value = true
  router.post(route('eir-restructures.resets.build'), {}, { preserveScroll: true, onFinish: () => { building.value = false } })
}

const busy = ref(false)
async function detect() {
  if (!(await confirmDialog({ title: 'Find candidates?', message: 'Reads the reschedule history, the dated chart copies, MODIFICATION_LOG, the loan-book rates and the PLR series again. Candidates no one has acted on are refreshed; nothing is confirmed. It can take a minute.', confirmLabel: 'Find candidates' }))) return
  busy.value = true
  router.post(route('eir-restructures.detect'), {}, { preserveScroll: true, onFinish: () => { busy.value = false } })
}

const disclosureCount = computed(() => {
  const d = props.summary.disclosures || {}
  return (d.all_modified?.count || 0) + (d.derecognised?.count || 0) + (d.catch_up?.count || 0) + (d.lifetime_modified_now_12_month?.count || 0)
})
const cards = computed(() => [
  { label: 'Candidates', value: Number(props.summary.candidates || 0), sub: `${props.summary.incomplete || 0} cannot be booked` },
  { label: 'Awaiting approval', value: Number(props.summary.proposed || 0), accent: props.summary.proposed ? '#d97706' : null },
  { label: 'Confirmed', value: Number(props.summary.confirmed || 0), sub: `${props.summary.restructured || 0} restructured` },
  { label: 'Booked', value: Number(props.summary.applied || 0), sub: `${props.summary.pending || 0} await the run, ${props.summary.blocked || 0} blocked`, accent: props.summary.blocked ? '#dc2626' : null },
  { label: 'Rate moves', value: Number((props.summary.resets_detected || 0) + (props.summary.resets_proposed || 0) + (props.summary.resets_approved || 0)), sub: `${props.summary.spread_changes || 0} spread changes, ${props.summary.resets_approved || 0} approved` },
])

const money = (v) => (v === null || v === undefined || v === '') ? '-' : Number(v).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const pct = (v) => (v === null || v === undefined || v === '') ? '-' : `${Number(v).toFixed(2)}%`
const classLabel = (s) => ({ MODIFICATION: 'Modification', REVISED_ESTIMATE: 'Revised estimate', CONTRACTUAL_RESET: 'Contractual reset', CORRECTION: 'Correction', NOT_A_CHANGE: 'Not a change', UNCERTAIN: 'Uncertain' }[s] || s || '-')
const suggestBadge = (s) => ({ MODIFICATION: 'maiic-badge-red', UNCERTAIN: 'maiic-badge-gold', REVISED_ESTIMATE: 'maiic-badge-gold', CORRECTION: 'maiic-badge-grey', CONTRACTUAL_RESET: 'maiic-badge-green' }[s] || 'maiic-badge-grey')
const statusLabel = (s) => ({ CANDIDATE: 'Candidate', PROPOSED: 'Proposed', CONFIRMED: 'Confirmed' }[s] || s)
const statusBadge = (s) => ({ CANDIDATE: 'maiic-badge-grey', PROPOSED: 'maiic-badge-gold', CONFIRMED: 'maiic-badge-green' }[s] || 'maiic-badge-grey')
const modLabel = (s) => ({ PENDING: 'Awaits the revenue run', APPLIED: 'Booked', BLOCKED: 'Blocked', REVERSED: 'Reversed' }[s] || s)
const modBadge = (s) => ({ PENDING: 'maiic-badge-gold', APPLIED: 'maiic-badge-green', BLOCKED: 'maiic-badge-red' }[s] || 'maiic-badge-grey')
const treatmentLabel = (t) => ({ MODIFICATION: 'Modified (5.4.3)', DERECOGNITION: 'Derecognised; new loan recognised', CATCH_UP: 'Catch-up (B5.4.6)' }[t] || t)
const resetLabel = (s) => ({ DETECTED: 'Awaiting a classification', PROPOSED: 'Proposed', APPROVED: 'Approved' }[s] || s)
const resetBadge = (s) => ({ DETECTED: 'maiic-badge-grey', PROPOSED: 'maiic-badge-gold', APPROVED: 'maiic-badge-green' }[s] || 'maiic-badge-grey')
// the reset pipeline's own state (status), beside the classification (classification_status)
const pipelineLabel = (r) => r.event_type === 'SPREAD_CHANGE' ? 'not scheduled (a spread change is classified here)'
  : ({ DETECTED: 'detected', SCHEDULED: 'Reset schedule built', SOLVED: 'solved', BLOCKED: 'held' }[r.status] || r.status || '-')
const noAccounting = (e) => (e.event_date && e.event_date <= TAKEON) ? 'before the take-on of 31 July 2024; the balance taken on and the take-on EIR already carry it (history and the RBM return)' : e.classification === 'CONTRACTUAL_RESET' ? 'a floating loan\'s reset is carried by the monthly re-estimation (D38)' : 'a ' + classLabel(e.classification).toLowerCase()
</script>
