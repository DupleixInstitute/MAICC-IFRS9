<template>
  <app-layout>
    <template #header>
      <div class="pb-1">
        <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-maiicgold-600 dark:text-amber-300">Administration &amp; Support &middot; Configuration</div>
        <h2 class="mt-1 text-3xl font-semibold tracking-tight text-gray-900 dark:text-white">Governance Centre</h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-slate-400">Every calculation setting the engines use: its value in force, its options, who proposed and who approved it, and from when.</p>
        <div class="mt-3 flex flex-wrap gap-2 text-[11px]">
          <span class="rounded-full border border-gray-300 bg-white px-2.5 py-1 font-semibold text-gray-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">{{ settings.length }} settings</span>
          <span class="rounded-full border border-maiic-300 bg-maiic-50 px-2.5 py-1 font-semibold text-maiic-800 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-200">{{ settings.length - noValueCount }} in force on {{ asOf }}</span>
          <span class="rounded-full border px-2.5 py-1 font-semibold" :class="pendingCount ? 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-400/40 dark:bg-amber-400/10 dark:text-amber-200' : 'border-gray-300 bg-white text-gray-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-400'">{{ pendingCount }} awaiting approval</span>
          <span v-if="upcomingCount" class="rounded-full border border-sky-300 bg-sky-50 px-2.5 py-1 font-semibold text-sky-800 dark:border-sky-400/40 dark:bg-sky-400/10 dark:text-sky-200">{{ upcomingCount }} approved from a later date</span>
          <span v-if="noValueCount" class="rounded-full border border-red-300 bg-red-50 px-2.5 py-1 font-semibold text-red-700 dark:border-rose-400/40 dark:bg-rose-400/10 dark:text-rose-200">{{ noValueCount }} with no approved value</span>
        </div>
        <p class="mt-3 max-w-4xl text-xs leading-relaxed text-gray-500 dark:text-slate-400">
          A change is proposed by one person and applies from its effective date forward once a <span class="font-semibold text-maiic-700 dark:text-emerald-300">second person approves it</span>.
          A month already run keeps the settings it ran with; a setting with <span class="font-semibold text-red-700 dark:text-rose-300">no approved value</span> stops the calculation that needs it rather than guessing.
        </p>
      </div>
    </template>

    <div class="w-full space-y-5">
      <div v-if="errorMessage && !showModal" class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-rose-500/40 dark:bg-rose-500/10 dark:text-rose-200">
        <font-awesome-icon icon="exclamation-circle" class="mt-0.5" />
        <div><div class="font-semibold">The change was not saved</div><div>{{ errorMessage }}</div></div>
      </div>

      <!-- the two views -->
      <nav aria-label="Governance Centre views" class="flex flex-wrap gap-2">
        <button v-for="v in views" :key="v.id" type="button" :aria-pressed="view === v.id"
                class="flex items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-semibold transition"
                :class="view === v.id ? 'border-maiic-600 bg-maiic-600 text-white shadow-sm' : 'border-gray-200 bg-white text-gray-700 hover:border-maiic-300 hover:text-maiic-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200'"
                @click="view = v.id">
          <font-awesome-icon :icon="v.icon" />
          {{ v.label }}
          <span class="rounded-full px-2 py-0.5 text-[11px]" :class="view === v.id ? 'bg-white/20 text-white' : (v.id === 'attention' && v.count ? 'bg-amber-100 text-amber-800 dark:bg-amber-400/20 dark:text-amber-200' : 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-300')">{{ v.count }}</span>
        </button>
      </nav>

      <!-- search and the area chips -->
      <div class="maiic-panel p-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h3 class="text-base font-semibold text-gray-900 dark:text-slate-100">{{ view === 'areas' ? 'Browse the settings' : 'Settings that need attention' }}</h3>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-slate-400">
              <template v-if="view === 'areas'">Showing {{ shownCount }} of {{ settings.length }} settings. Open a setting to see its options, every value it has had, and to propose a change.</template>
              <template v-else>Changes awaiting a second person's approval, approved changes that start later, and settings with no approved value.</template>
            </p>
          </div>
          <label class="block w-full lg:w-96">
            <span class="sr-only">Search the settings</span>
            <input v-model="search" type="search" placeholder="Search a setting, its key, its value or what it does" class="maiic-input w-full" />
          </label>
        </div>
        <div v-if="view === 'areas'" class="mt-3 flex flex-wrap gap-2" aria-label="Filter by area">
          <button type="button" class="rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                  :class="selected === 'all' ? 'border-gray-800 bg-gray-800 text-white dark:border-slate-200 dark:bg-slate-200 dark:text-slate-900' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200'"
                  @click="selected = 'all'">All areas</button>
          <button v-for="(a, i) in areas" :key="a.key" type="button" class="rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                  :class="selected === a.key ? palette(i).chipOn : palette(i).chip" @click="selected = a.key">
            {{ a.label }} <span class="opacity-70">{{ a.settings.length }}</span>
          </button>
        </div>
      </div>

      <!-- the areas, each a coloured band of setting cards -->
      <template v-if="view === 'areas'">
        <div v-for="a in shownAreas" :key="a.key" class="space-y-3">
          <div class="flex items-center gap-2.5">
            <span class="h-5 w-1.5 rounded-full" :class="palette(a.index).bar"></span>
            <div class="text-sm font-bold uppercase tracking-[0.14em]" :class="palette(a.index).text">{{ a.label }}</div>
            <span class="rounded-full border px-2 py-0.5 text-[10px] font-semibold" :class="palette(a.index).pill">{{ a.shown.length }} setting{{ a.shown.length === 1 ? '' : 's' }}</span>
            <span class="h-px flex-1 bg-gradient-to-r from-gray-300 to-transparent dark:from-slate-700"></span>
          </div>
          <p class="-mt-1 text-xs text-gray-500 dark:text-slate-400">{{ a.description }}</p>
          <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <button v-for="s in a.shown" :key="s.key" type="button" class="group flex flex-col rounded-xl border border-gray-200 bg-white p-4 text-left shadow-sm transition hover:shadow-md dark:border-slate-700 dark:bg-slate-800/70"
                    :class="palette(a.index).card" @click="openDetail(s)">
              <div class="flex items-start justify-between gap-2">
                <div class="text-sm font-semibold text-gray-900 group-hover:text-maiic-800 dark:text-slate-100">{{ s.label }}</div>
                <span v-if="!s.in_force" class="shrink-0 rounded-full border border-red-300 bg-red-50 px-2 py-0.5 text-[10px] font-semibold text-red-700 dark:border-rose-400/40 dark:bg-rose-400/10 dark:text-rose-200">no value</span>
                <span v-else-if="hasPending(s)" class="shrink-0 rounded-full border border-amber-300 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-800 dark:border-amber-400/40 dark:bg-amber-400/10 dark:text-amber-200">awaiting approval</span>
                <span v-else-if="s.in_force.value === s.default" class="shrink-0 rounded-full border border-maiic-200 bg-maiic-50 px-2 py-0.5 text-[10px] font-semibold text-maiic-700 dark:border-emerald-500/40 dark:bg-emerald-500/10 dark:text-emerald-300">recommended</span>
                <span v-else class="shrink-0 rounded-full border border-sky-300 bg-sky-50 px-2 py-0.5 text-[10px] font-semibold text-sky-800 dark:border-sky-400/40 dark:bg-sky-400/10 dark:text-sky-200">changed</span>
              </div>
              <div class="mt-0.5 font-mono text-[10px] text-gray-400">{{ s.key }}</div>
              <div class="mt-2 text-sm font-medium" :class="s.in_force ? 'text-maiic-800 dark:text-emerald-300' : 'text-red-700 dark:text-rose-300'">{{ s.in_force ? $filters.plain(s.in_force.value) : 'No approved value: the calculation stops' }}</div>
              <p class="mt-1 line-clamp-2 flex-1 text-xs leading-relaxed text-gray-500 dark:text-slate-400" :title="plain(s.description)">{{ plain(s.description) }}</p>
              <div class="mt-3 flex items-center justify-between text-[11px]">
                <span class="text-gray-400">{{ s.in_force ? sinceText(s) : '' }}</span>
                <span class="font-semibold text-maiic-700 group-hover:text-maiic-800 dark:text-emerald-300">Open &rarr;</span>
              </div>
            </button>
          </div>
        </div>
        <div v-if="!shownAreas.length" class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-slate-700">No setting matches the search.</div>
      </template>

      <!-- needs attention -->
      <template v-else>
        <div v-if="!attention.length" class="rounded-xl border border-maiic-200 bg-maiic-50 p-6 text-sm text-maiic-800 dark:border-emerald-500/30 dark:bg-emerald-500/5 dark:text-emerald-200">Nothing needs attention: every setting has an approved value and no change is waiting.</div>
        <div v-else class="grid gap-3 lg:grid-cols-2">
          <button v-for="s in attention" :key="s.key" type="button" class="flex flex-col rounded-xl border border-gray-200 bg-white p-4 text-left shadow-sm transition hover:shadow-md dark:border-slate-700 dark:bg-slate-800/70" @click="openDetail(s)">
            <div class="flex items-start justify-between gap-2">
              <div class="text-sm font-semibold text-gray-900 dark:text-slate-100">{{ s.label }}</div>
              <span class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600 dark:bg-slate-700 dark:text-slate-300">{{ areaOf(s) }}</span>
            </div>
            <div class="mt-0.5 font-mono text-[10px] text-gray-400">{{ s.key }}</div>
            <div v-if="!s.in_force" class="mt-2 text-sm font-semibold text-red-700 dark:text-rose-300">No approved value: the calculation stops</div>
            <div v-for="r in pendingRows(s)" :key="r.id" class="mt-2 rounded-lg border px-3 py-2 text-xs" :class="r.state === 'PROPOSED' ? 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100' : 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-400/30 dark:bg-sky-400/10 dark:text-sky-100'">
              <span class="font-semibold">{{ r.state === 'PROPOSED' ? 'Awaiting approval' : 'Approved, applies from ' + r.effective_from }}:</span> {{ $filters.plain(r.value) }}<span v-if="r.state === 'PROPOSED'"> from {{ r.effective_from }}</span>
              <div class="opacity-80">Proposed by {{ r.proposer || 'unknown' }}<span v-if="r.approver">, approved by {{ r.approver }}</span>; {{ r.reason }}</div>
            </div>
          </button>
        </div>
      </template>
    </div>

    <!-- one setting in full -->
    <jet-modal :show="detail !== null" max-width="5xl" @close="detail = null">
      <div v-if="detail" class="p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="text-[11px] font-semibold uppercase tracking-[0.14em] text-gray-500">{{ areaOf(detail) }}</div>
            <h3 class="mt-0.5 text-xl font-semibold text-gray-900 dark:text-slate-100">{{ detail.label }}</h3>
            <div class="font-mono text-[11px] text-gray-400">{{ detail.key }}</div>
          </div>
          <div class="flex gap-2">
            <button type="button" class="primary-btn" @click="openPropose(detail)"><font-awesome-icon icon="pen" /> Propose a change</button>
            <button type="button" class="secondary-btn" @click="detail = null">Close</button>
          </div>
        </div>
        <p class="mt-3 text-sm leading-relaxed text-gray-600 dark:text-slate-300">{{ plain(detail.description) }}</p>

        <div class="mt-4 grid gap-4 md:grid-cols-2">
          <div class="rounded-lg border border-gray-200 p-3 dark:border-slate-700">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Value in force on {{ asOf }}</div>
            <template v-if="detail.in_force">
              <div class="mt-1 text-base font-semibold text-maiic-800 dark:text-emerald-300">{{ $filters.plain(detail.in_force.value) }}</div>
              <div class="text-xs text-gray-500">{{ sinceText(detail) }}; {{ detail.in_force.approver ? 'approved by ' + detail.in_force.approver : 'the Dupleix recommended default' }}</div>
            </template>
            <div v-else class="mt-1 text-sm font-semibold text-red-700">No approved value: the calculation stops</div>
          </div>
          <div class="rounded-lg border border-gray-200 p-3 dark:border-slate-700">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Options</div>
            <div v-for="o in detail.options" :key="o" class="mt-1 text-sm" :class="detail.in_force && o === detail.in_force.value ? 'font-semibold text-maiic-800 dark:text-emerald-300' : 'text-gray-700 dark:text-slate-300'">
              {{ $filters.plain(o) }}<span v-if="o === detail.default" class="text-xs text-gray-400"> (recommended)</span>
              <template v-if="detail.key === 'fli_transmission_method' && cardFor(o)">
                <span class="ml-1 maiic-badge" :class="cardFor(o).available ? 'maiic-badge-green' : 'maiic-badge-gold'">{{ cardFor(o).available ? 'preconditions met' : 'not yet available' }}</span>
                <button type="button" class="ml-1 text-xs text-sky-700 underline dark:text-sky-300" @click="openCard = openCard === o ? null : o">{{ openCard === o ? 'hide the card' : 'method card' }}</button>
                <div v-if="openCard === o" class="mb-2 mt-1 rounded border border-gray-200 bg-gray-50 p-3 text-xs font-normal dark:border-slate-700 dark:bg-slate-900/60">
                  <p class="text-gray-700 dark:text-slate-300">{{ plain(cardFor(o).what) }}</p>
                  <p class="mt-1 font-mono">{{ cardFor(o).formula }}</p>
                  <ul class="mt-1 text-gray-500"><li v-for="(v, k) in cardFor(o).symbols" :key="k">{{ k }}: {{ v }}</li></ul>
                  <ul class="mt-1 list-disc pl-4"><li v-for="(i, n) in cardFor(o).implies" :key="n">{{ i }}</li></ul>
                  <div class="mt-1 space-y-0.5"><div v-for="(pc, n) in cardFor(o).preconditions" :key="n"><span :class="pc.met ? 'text-maiic-700' : 'text-red-700'">{{ pc.met ? 'met' : 'not met' }}</span> {{ pc.name }}: <span class="text-gray-500">{{ pc.figure }}</span></div></div>
                  <p v-if="cardFor(o).example" class="mt-1 text-gray-600 dark:text-slate-400">Example on {{ cardFor(o).example.contract_id }} ({{ cardFor(o).example.customer_name }}): {{ cardFor(o).example.steps.join('; ') }}</p>
                  <p v-if="!cardFor(o).available" class="mt-1 text-amber-700">This method cannot be selected until every precondition above is met.</p>
                </div>
              </template>
            </div>
          </div>
        </div>

        <div v-if="pendingRows(detail).length" class="mt-4">
          <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Proposed and upcoming</div>
          <div v-for="r in pendingRows(detail)" :key="r.id" class="mt-2 flex flex-wrap items-center justify-between gap-2 rounded-lg border px-3 py-2 text-sm" :class="r.state === 'PROPOSED' ? 'border-amber-200 bg-amber-50 dark:border-amber-400/30 dark:bg-amber-400/10' : 'border-sky-200 bg-sky-50 dark:border-sky-400/30 dark:bg-sky-400/10'">
            <div>
              <span class="font-semibold">{{ r.state === 'PROPOSED' ? 'Awaiting approval' : 'Approved, applies from ' + r.effective_from }}:</span> {{ $filters.plain(r.value) }}<span v-if="r.state === 'PROPOSED'"> from {{ r.effective_from }}</span>
              <div class="text-xs text-gray-600 dark:text-slate-300">Proposed by {{ r.proposer || 'unknown' }}<span v-if="r.approver">, approved by {{ r.approver }}</span>; <span class="italic">{{ r.reason }}</span></div>
            </div>
            <button v-if="r.state === 'PROPOSED'" type="button" class="primary-btn" :disabled="!canApprove(r) || processing" :title="canApprove(r) ? 'Approve this change' : 'The person who proposed a change cannot approve it'" @click="approve(r)">Approve</button>
          </div>
        </div>

        <div class="mt-5">
          <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500">Every value of {{ detail.label }}</div>
          <div class="maiic-table-wrap mt-2">
            <table class="maiic-table maiic-table-compact">
              <thead><tr><th>State</th><th>Value</th><th>Effective from</th><th>Proposed by</th><th>Approved by</th><th>Reason</th></tr></thead>
              <tbody>
                <tr v-for="r in detail.rows" :key="'row-' + r.id">
                  <td><span :class="stateClass(r.state)">{{ stateLabel(r.state) }}</span></td>
                  <td>{{ $filters.plain(r.value) }}</td>
                  <td class="whitespace-nowrap">{{ $filters.since(r.effective_from) }}</td>
                  <td>{{ r.proposer || (r.approver ? 'unknown' : 'Dupleix (seeded)') }}</td>
                  <td>{{ r.approver || (r.status === 'APPROVED' ? 'Seeded default' : 'Not yet') }}<span v-if="r.approved_at" class="block text-gray-400">{{ r.approved_at }}</span></td>
                  <td class="max-w-[18rem]">{{ r.reason }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="mt-4 text-[11px] font-semibold uppercase tracking-wide text-gray-500">Superseded values (audit copies)</div>
          <p v-if="!detail.history.length" class="mt-1 text-xs text-gray-400">No value has been superseded yet.</p>
          <div v-else class="maiic-table-wrap mt-2">
            <table class="maiic-table maiic-table-compact">
              <thead><tr><th>Value</th><th>Was effective from</th><th>Superseded on</th><th>By</th><th>Replaced by row</th></tr></thead>
              <tbody>
                <tr v-for="h in detail.history" :key="'hist-' + h.id">
                  <td>{{ h.value }}</td><td>{{ $filters.since(h.effective_from) }}</td><td>{{ h.superseded_at }}</td><td>{{ h.superseded_by || 'unknown' }}</td><td>#{{ h.superseded_by_setting_id }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </jet-modal>

    <!-- propose a change -->
    <jet-dialog-modal :show="showModal" max-width="2xl" @close="closeModal">
      <template #title>
        <div>
          <div class="font-semibold text-gray-900">Propose a change: {{ proposing?.label }}</div>
          <p class="mt-1 text-sm font-normal text-gray-500">{{ plain(proposing?.description) }}</p>
        </div>
      </template>
      <template #content>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <label class="field sm:col-span-2">New value
            <select v-model="form.value" class="form-input mt-1">
              <option value="">Choose an option</option>
              <option v-for="o in proposing?.options || []" :key="o" :value="o" :disabled="isInForce(o)">{{ $filters.plain(o) }}{{ isInForce(o) ? ' (in force now)' : '' }}</option>
            </select>
          </label>
          <label class="field">Effective from
            <input v-model="form.effective_from" type="date" class="form-input mt-1">
            <span class="mt-1 block text-xs font-normal text-gray-500">Must be later than the last approved change. The new value applies from this date forward only.</span>
          </label>
          <label class="field sm:col-span-2">Reason
            <textarea v-model="form.reason" rows="3" maxlength="500" class="form-input mt-1" placeholder="Why the convention is changing, in plain words; the auditor reads this"></textarea>
            <span class="mt-1 block text-xs font-normal text-gray-500">At least 10 characters; recorded in the audit log with the old and the new value.</span>
          </label>
        </div>
        <div v-if="errorMessage" class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ errorMessage }}</div>
      </template>
      <template #footer>
        <button class="secondary-btn mr-2" @click="closeModal">Cancel</button>
        <button :disabled="processing || !form.value || !form.effective_from || form.reason.trim().length < 10" class="primary-btn" @click="save">{{ processing ? 'Saving...' : 'Propose change' }}</button>
      </template>
    </jet-dialog-modal>

    <teleport to="head"><title>Governance Centre</title></teleport>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetDialogModal from '@/Jetstream/DialogModal.vue'
import JetModal from '@/Jetstream/Modal.vue'

// Setting areas (display grouping only; the engine reads keys).
const AREAS = [
  { key: 'interest', label: 'Interest & rates', description: 'How rates, resets, moratoria and day counts feed the effective interest rate.',
    keys: ['plr_mid_period', 'reset_trigger', 'moratorium_capitalisation', 'rate_source_precedence', 'margin_basis', 'stage3_interest_basis', 'day_count', 'period_rate_basis', 'rate_change_classification', 'modification_threshold', 'partly_drawn_interest_basis'] },
  { key: 'cashflows', label: 'Cash flows', description: 'Which records and schedules count as the contractual and expected cash flows.',
    keys: ['cash_source', 'manual_policy_handling', 'counter_reset_handling', 'contractual_record', 'expected_cashflow_basis', 'schedule_approval_control'] },
  { key: 'data', label: 'Data sources', description: 'Where the loan book, history and macro data come from, and which source wins.',
    keys: ['history_before_dec_2025', 'ebanker_feed_route', 'loan_book_build_method', 'takeon_history_basis', 'macro_source_precedence'] },
  { key: 'accounting', label: 'Accounting & controls', description: 'Reconciliation tolerance, journals, exports, special balances and maker-checker.',
    keys: ['recon_tolerance', 'trueup_gl_account', 'auditor_export_format', 'fee_reclass_journal', 'historic_materiality_assessment', 'keyman_insurance_treatment', 'nascomex_preference_shares', 'maker_checker_admin_override', 'fee_gl_reconciliation'] },
  { key: 'staging', label: 'Staging', description: 'Days past due, Stage 3 triggers, rebuttals and cure periods.',
    keys: ['staging_rebuttal', 'dpd_basis', 'stage3_missed_instalments', 'stage_cure_months'] },
  { key: 'segments', label: 'PD & LGD segments', description: 'How the book is split for the PD and LGD, the history a segment needs for its own rate, and what a thin segment does.',
    keys: ['pd_segmentation_basis', 'pd_segment_min_observations', 'pd_segment_thin_rule', 'pd_sector_unverified_codes', 'lgd_segment_min_cohort'] },
  { key: 'fli', label: 'Forward-looking', description: 'How the forward-looking adjustment is built and tested, and how scenarios are weighted.',
    keys: ['fli_adjustment_route', 'fli_transmission_method', 'fli_asset_correlation', 'fli_expected_sign_test', 'fli_r2_cutoff', 'fli_min_observations', 'fli_alpha', 'fli_normality_limits', 'scenario_weighting_method', 'scenario_minimum_count', 'scenario_weight_bounds', 'scenario_calibration_note', 'overlay_requires_approved_set', 'fli_model_cadence', 'fli_forecast_horizon', 'fli_movement_commentary'] },
  { key: 'megafarm', label: 'Mega Farm', description: 'Scope and probability of default for the Mega Farm programme.',
    keys: ['mega_farms_scope', 'megafarm_pd_method', 'megafarm_scalar_ceiling'] },
]
const KNOWN = AREAS.flatMap(a => a.keys)

// One colour per area, full class strings so Tailwind keeps them; light and dark.
const PALETTES = [
  { bar: 'bg-emerald-500', text: 'text-emerald-700 dark:text-emerald-300', pill: 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-400/40 dark:bg-emerald-400/10 dark:text-emerald-200', card: 'hover:border-emerald-400', chip: 'border-emerald-300 bg-white text-emerald-800 hover:bg-emerald-50 dark:border-emerald-400/40 dark:bg-slate-800 dark:text-emerald-200', chipOn: 'border-emerald-600 bg-emerald-600 text-white' },
  { bar: 'bg-amber-500', text: 'text-amber-700 dark:text-amber-300', pill: 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-400/40 dark:bg-amber-400/10 dark:text-amber-200', card: 'hover:border-amber-400', chip: 'border-amber-300 bg-white text-amber-800 hover:bg-amber-50 dark:border-amber-400/40 dark:bg-slate-800 dark:text-amber-200', chipOn: 'border-amber-600 bg-amber-600 text-white' },
  { bar: 'bg-sky-500', text: 'text-sky-700 dark:text-sky-300', pill: 'border-sky-300 bg-sky-50 text-sky-800 dark:border-sky-400/40 dark:bg-sky-400/10 dark:text-sky-200', card: 'hover:border-sky-400', chip: 'border-sky-300 bg-white text-sky-800 hover:bg-sky-50 dark:border-sky-400/40 dark:bg-slate-800 dark:text-sky-200', chipOn: 'border-sky-600 bg-sky-600 text-white' },
  { bar: 'bg-violet-500', text: 'text-violet-700 dark:text-violet-300', pill: 'border-violet-300 bg-violet-50 text-violet-800 dark:border-violet-400/40 dark:bg-violet-400/10 dark:text-violet-200', card: 'hover:border-violet-400', chip: 'border-violet-300 bg-white text-violet-800 hover:bg-violet-50 dark:border-violet-400/40 dark:bg-slate-800 dark:text-violet-200', chipOn: 'border-violet-600 bg-violet-600 text-white' },
  { bar: 'bg-rose-500', text: 'text-rose-700 dark:text-rose-300', pill: 'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-400/40 dark:bg-rose-400/10 dark:text-rose-200', card: 'hover:border-rose-400', chip: 'border-rose-300 bg-white text-rose-800 hover:bg-rose-50 dark:border-rose-400/40 dark:bg-slate-800 dark:text-rose-200', chipOn: 'border-rose-600 bg-rose-600 text-white' },
  { bar: 'bg-teal-500', text: 'text-teal-700 dark:text-teal-300', pill: 'border-teal-300 bg-teal-50 text-teal-800 dark:border-teal-400/40 dark:bg-teal-400/10 dark:text-teal-200', card: 'hover:border-teal-400', chip: 'border-teal-300 bg-white text-teal-800 hover:bg-teal-50 dark:border-teal-400/40 dark:bg-slate-800 dark:text-teal-200', chipOn: 'border-teal-600 bg-teal-600 text-white' },
]

const blank = (asOf) => ({ key: '', value: '', effective_from: asOf, reason: '' })

export default {
  components: { AppLayout, JetDialogModal, JetModal },
  props: {
    settings: Array,
    asOf: String,
    userId: Number,
    adminOverride: { type: Boolean, default: false },
    methodCards: { type: Array, default: () => [] },
    errors: { type: Object, default: () => ({}) },
  },
  data() {
    return { view: 'areas', selected: 'all', search: '', detail: null, openCard: null, showModal: false, proposing: null, processing: false, form: blank(this.asOf) }
  },
  computed: {
    errorMessage() {
      return this.errors.governance || Object.values(this.errors)[0] || null
    },
    areas() {
      const list = AREAS.map(a => ({ ...a, settings: this.settings.filter(s => a.keys.includes(s.key)) }))
      const other = this.settings.filter(s => !KNOWN.includes(s.key))
      if (other.length) list.push({ key: 'other', label: 'Other', description: 'Settings not yet placed in an area.', settings: other })
      return list
    },
    shownAreas() {
      const term = this.search.trim().toLowerCase()
      return this.areas.map((a, index) => ({ ...a, index, shown: a.settings.filter(s => !term || this.haystack(s).includes(term)) }))
        .filter(a => (this.selected === 'all' || a.key === this.selected) && a.shown.length)
    },
    shownCount() {
      return this.shownAreas.reduce((n, a) => n + a.shown.length, 0)
    },
    attention() {
      const term = this.search.trim().toLowerCase()
      return this.settings.filter(s => (!s.in_force || this.pendingRows(s).length) && (!term || this.haystack(s).includes(term)))
    },
    pendingCount() {
      return this.settings.reduce((n, s) => n + s.rows.filter(r => r.state === 'PROPOSED').length, 0)
    },
    upcomingCount() {
      return this.settings.reduce((n, s) => n + s.rows.filter(r => r.state === 'UPCOMING').length, 0)
    },
    noValueCount() {
      return this.settings.filter(s => !s.in_force).length
    },
    views() {
      return [
        { id: 'areas', label: 'Settings by area', icon: 'cogs', count: this.settings.length },
        { id: 'attention', label: 'Needs attention', icon: 'exclamation-triangle', count: this.settings.filter(s => !s.in_force || this.pendingRows(s).length).length },
      ]
    },
  },
  watch: {
    // keep the open setting in step with the page after a proposal or an approval
    settings() {
      if (this.detail) this.detail = this.settings.find(s => s.key === this.detail.key) || null
    },
  },
  methods: {
    palette(i) { return PALETTES[i % PALETTES.length] },
    areaOf(s) { return (this.areas.find(a => a.settings.some(x => x.key === s.key)) || {}).label || '' },
    haystack(s) { return [s.label, s.key, this.plain(s.description), s.in_force ? s.in_force.value : ''].join(' ').toLowerCase() },
    sinceText(s) {
      const since = this.$filters.since(s.in_force.effective_from)
      return since === 'the seeded default' ? 'The seeded default' : 'From ' + since
    },
    // Specification references are for the build team, not the screen.
    plain(text) {
      return String(text || '')
        .replace(/\s*\((?:spec v\d+ section [\d.]+[^)]*|O\d+|decision D\d+)\)/g, '')
        .replace(/\s*Agreed as decision D\d+\./g, '')
        .replace(/\s*Open choice O\d+[^.]*\./g, '')
        .replace(/\s*Decided ([^(]*?) \(O\d+\):/g, ' Decided $1:')
        .trim()
    },
    cardFor(option) { return this.methodCards.find(c => c.key === option) || null },
    pendingRows(s) { return s.rows.filter(r => r.state === 'PROPOSED' || r.state === 'UPCOMING') },
    hasPending(s) { return s.rows.some(r => r.state === 'PROPOSED') },
    canApprove(r) { return this.adminOverride || (r.proposer_id !== null && r.proposer_id !== this.userId) },
    isInForce(option) { return Boolean(this.proposing?.in_force) && option === this.proposing.in_force.value },
    stateLabel(state) { return { IN_FORCE: 'In force', PAST: 'Past', UPCOMING: 'Upcoming', PROPOSED: 'Awaiting approval' }[state] || state },
    stateClass(state) { return state === 'IN_FORCE' ? 'maiic-badge maiic-badge-green' : state === 'PROPOSED' ? 'maiic-badge maiic-badge-gold' : 'maiic-badge maiic-badge-grey' },
    openDetail(s) { this.openCard = null; this.detail = s },
    openPropose(s) {
      this.proposing = s
      this.form = { ...blank(this.asOf), key: s.key }
      this.showModal = true
    },
    closeModal() {
      if (this.processing) return
      this.showModal = false
      this.proposing = null
      this.form = blank(this.asOf)
    },
    save() {
      this.processing = true
      this.$inertia.post(this.route('eir-governance.propose'), this.form, {
        preserveScroll: true,
        onFinish: () => { this.processing = false },
        onSuccess: () => { this.showModal = false; this.proposing = null; this.form = blank(this.asOf) },
      })
    },
    approve(r) {
      if (!this.canApprove(r)) return
      this.processing = true
      this.$inertia.post(this.route('eir-governance.approve', r.id), {}, { preserveScroll: true, onFinish: () => { this.processing = false } })
    },
  },
}
</script>

<style scoped>
.field{@apply block text-sm font-medium text-gray-700}
.form-input{@apply block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-maiic-500}
</style>
