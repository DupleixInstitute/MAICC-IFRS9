<template>
  <app-layout title="E-Banker Feed" description="Packs loaded from E-Banker, the checks each pack passed, and the approved builds of the loan book">
    <template #actions>
      <a v-if="tab === 'loads' || tab === 'queries'" :href="route('eir-feed.queries')" class="secondary-btn" title="The extract queries, as one file to run on E-Banker"><font-awesome-icon icon="download" /> Download the queries</a>
      <button v-if="tab === 'loads' && canGovern" type="button" class="primary-btn" @click="packOpen = true"><font-awesome-icon icon="upload" /> Load a pack</button>
      <button v-if="tab === 'builds' && canGovern" type="button" class="primary-btn" @click="buildOpen = true"><font-awesome-icon icon="plus" /> Propose a build</button>
    </template>

    <div class="space-y-5">
      <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="maiic-kpi" style="--accent:#0d9488"><div class="maiic-kpi-label">Route in force</div><div class="truncate text-base font-bold text-gray-900" :title="routeInForce || 'No approved value in the Governance Centre'">{{ routeInForce || 'Not set' }}</div></div>
        <div class="maiic-kpi" style="--accent:#0d9488"><div class="maiic-kpi-label">Build method in force</div><div class="truncate text-base font-bold text-gray-900" :title="methodInForce || 'No approved value in the Governance Centre'">{{ methodInForce || 'Not set' }}</div></div>
        <div class="maiic-kpi" style="--accent:#0d9488" title="A view or build after this date is refused, never estimated"><div class="maiic-kpi-label">Last posting loaded</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ lastLedgerDate || '-' }}</div></div>
        <div class="maiic-kpi" style="--accent:#0d9488" :title="locks.join(', ') || 'No period is locked'"><div class="maiic-kpi-label">Locked periods</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ locks.length }}</div></div>
      </div>

      <div>
        <div class="maiic-panel">

          <!-- Loads -->
          <template v-if="tab === 'loads'">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-3">
              <p class="text-sm text-gray-500">Every pack that came in, by any route. A pack that fails a check is quarantined, with the file and row named. Use <strong>Load a pack</strong> for the steps.</p>
            </div>
            <div class="maiic-table-wrap">
              <table class="maiic-table">
                <thead><tr><th>Load</th><th>Pack</th><th>Route</th><th>Period</th><th>Status</th><th class="num">Files</th><th>Checks</th><th>Loaded</th><th class="text-right">View</th></tr></thead>
                <tbody>
                  <template v-for="l in pageOf(loads, 'loads')" :key="l.id">
                    <tr>
                      <td class="font-mono text-xs" :title="'Pack fingerprint ' + l.hash">{{ l.id }}</td>
                      <td class="max-w-xs truncate" :title="l.pack">{{ l.pack }}</td>
                      <td class="text-xs whitespace-nowrap">{{ $filters.plain(l.route) }}</td>
                      <td class="whitespace-nowrap">{{ l.period || 'History' }}</td>
                      <td><span class="maiic-badge" :class="statusBadge(l.status)">{{ l.status }}</span></td>
                      <td class="num">{{ l.files }}<span v-if="l.failed" class="text-red-600"> ({{ l.failed }} failed)</span></td>
                      <td class="text-xs whitespace-nowrap">
                        <span v-if="gateCount(l, 'PASS')" class="maiic-badge maiic-badge-green mr-1">{{ gateCount(l, 'PASS') }} passed</span>
                        <span v-if="gateCount(l, 'WARN')" class="maiic-badge maiic-badge-gold mr-1">{{ gateCount(l, 'WARN') }} warning</span>
                        <span v-if="gateCount(l, 'FAIL') || l.failed" class="maiic-badge maiic-badge-red">{{ gateCount(l, 'FAIL') + l.failed }} failed</span>
                        <span v-if="l.accepted_exceptions.length" class="maiic-badge maiic-badge-gold">{{ l.accepted_exceptions.length }} accepted {{ l.accepted_exceptions.length === 1 ? 'exception' : 'exceptions' }}</span>
                        <span v-if="!l.gates.length && !l.failed && !l.accepted_exceptions.length" class="text-gray-400">None recorded</span>
                      </td>
                      <td class="text-xs whitespace-nowrap">{{ String(l.loaded_at || '').slice(0, 16) }}<br><span class="text-gray-500">{{ l.loaded_by || 'system' }}</span></td>
                      <td class="text-right">
                        <button type="button" class="maiic-action maiic-action-view" :title="open === l.id ? 'Hide the checks and watermarks' : 'Show the checks and watermarks'" @click="open = open === l.id ? null : l.id">
                          <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/><path fill-rule="evenodd" d="M.66 10.59a1.65 1.65 0 010-1.18C2.1 5.74 5.73 3 10 3s7.9 2.74 9.34 6.41c.15.38.15.8 0 1.18C17.9 14.26 14.27 17 10 17S2.1 14.26.66 10.59zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/></svg>
                        </button>
                      </td>
                    </tr>
                    <tr v-if="open === l.id" class="bg-gray-50">
                      <td colspan="9" class="!py-4">
                        <div class="grid gap-4 md:grid-cols-2">
                          <div>
                            <div class="maiic-kpi-label">Checks</div>
                            <ul class="space-y-1 text-xs">
                              <li v-for="g in l.gates" :key="g.gate" :class="g.result === 'FAIL' ? 'text-red-700' : (g.result === 'WARN' ? 'text-amber-700' : 'text-gray-600')">
                                <span class="font-mono font-semibold">{{ g.gate }}</span> {{ g.result }}<span v-if="g.detail">: {{ g.detail }}</span><span v-if="g.failures.length"> - {{ g.failures.join('; ') }}</span>
                              </li>
                              <li v-for="f in l.failures" :key="f.file" class="text-red-700"><span class="font-semibold">{{ f.file }}</span>: {{ f.failures.join('; ') }}</li>
                              <li v-for="(e, i) in l.accepted_exceptions" :key="'e' + i" class="text-amber-700">Accepted exception: {{ e }}</li>
                              <li v-if="!l.gates.length && !l.failures.length && !l.accepted_exceptions.length" class="text-gray-400">No checks recorded on this load.</li>
                            </ul>
                          </div>
                          <div>
                            <div class="maiic-kpi-label">Watermarks (last key loaded per extract)</div>
                            <div class="flex flex-wrap gap-x-4 gap-y-1 font-mono text-xs text-gray-600">
                              <span v-for="(v, k) in l.watermarks" :key="k" class="whitespace-nowrap">{{ k }} = {{ v }}</span>
                              <span v-if="!Object.keys(l.watermarks || {}).length" class="font-sans text-gray-400">None recorded.</span>
                            </div>
                          </div>
                        </div>
                      </td>
                    </tr>
                  </template>
                  <tr v-if="!loads.length"><td colspan="9" class="maiic-empty">No pack has been loaded yet. Download the queries, run them on E-Banker and load the zip here.</td></tr>
                </tbody>
              </table>
            </div>
            <LocalPager v-model="page.loads" :total="loads.length"/>
          </template>

          <!-- Build -->
          <template v-else-if="tab === 'builds'">
            <div class="border-b border-gray-200 px-5 py-3">
              <p class="text-sm text-gray-500">One person proposes a build of the loan book and a second approves it. The differences from the previous build are shown first; a locked period is never restated. Use <strong>Propose a build</strong> for the steps.</p>
            </div>
            <div class="maiic-table-wrap">
              <table class="maiic-table">
                <thead><tr><th>Build</th><th>Method</th><th>Periods</th><th>Status</th><th>Proposed by</th><th>Approved by</th><th>Result</th><th v-if="canGovern" class="text-right">Action</th></tr></thead>
                <tbody>
                  <tr v-for="b in pageOf(builds, 'builds')" :key="b.id" class="align-top">
                    <td class="font-mono text-xs">{{ b.id }}</td>
                    <td>{{ b.method === '?' ? 'In force' : b.method }}</td>
                    <td class="whitespace-nowrap">{{ b.from }} to {{ b.to }}</td>
                    <td><span class="maiic-badge" :class="b.status === 'BUILT' ? 'maiic-badge-green' : (b.status === 'PROPOSED' ? 'maiic-badge-gold' : 'maiic-badge-grey')" :title="b.superseded ? 'Approved: the build was written as a new build above' : ''">{{ b.superseded ? 'Approved, superseded' : ({ BUILT: 'Built', PROPOSED: 'Proposed', REJECTED: 'Rejected' }[b.status] || b.status) }}</span></td>
                    <td class="text-xs whitespace-nowrap">{{ b.requested_by || 'system' }}<br><span class="text-gray-500">{{ b.created_at }}</span></td>
                    <td class="text-xs">{{ b.approver || '-' }}</td>
                    <td class="text-xs">
                      <span v-for="p in b.summary.slice(0, 4)" :key="p.period" class="mr-3 inline-block whitespace-nowrap">{{ p.period }}: {{ p.rows }} rows<span v-if="p.changed">, {{ p.changed }} changed</span><span v-if="p.flagged">, {{ p.flagged }} flagged</span></span>
                      <span v-if="b.summary.length > 4" class="text-gray-400" :title="b.summary.slice(4).map(p => p.period + ': ' + p.rows + ' rows').join('\n')">and {{ b.summary.length - 4 }} more periods</span>
                    </td>
                    <td v-if="canGovern" class="text-right whitespace-nowrap">
                      <button v-if="b.status === 'PROPOSED' && b.requested_by_id !== me" @click="approve(b.id)" class="secondary-btn" title="Approve this build and write it; the differences are kept on the build">Approve and build</button>
                      <span v-else-if="b.status === 'PROPOSED'" class="text-xs text-gray-500" title="The person who proposed a build cannot approve it">Waiting for a second person</span>
                    </td>
                  </tr>
                  <tr v-if="!builds.length"><td :colspan="canGovern ? 8 : 7" class="maiic-empty">No build yet. Use <strong>Propose a build</strong> to choose the months.</td></tr>
                </tbody>
              </table>
            </div>
            <LocalPager v-model="page.builds" :total="builds.length"/>
          </template>

          <!-- Periods built -->
          <template v-else-if="tab === 'periods'">
            <div class="border-b border-gray-200 px-5 py-3">
              <p class="text-sm text-gray-500">The loan book rows built for each period, by method. A flag names a difference from the source, it never hides one. Lock a period once its month-end is signed off: nothing then restates it. Unlocking is requested by one person and approved by another.</p>
            </div>
            <!-- unlocks requested by one person, awaiting a second (maker-checker) -->
            <div v-if="unlockRequests.length" class="border-b border-amber-200 bg-amber-50 px-5 py-3">
              <div class="text-sm font-semibold text-amber-800">Unlocks awaiting approval ({{ unlockRequests.length }})</div>
              <ul class="mt-2 divide-y divide-amber-200 dark:divide-amber-700">
                <li v-for="r in unlockRequests" :key="r.id" class="flex flex-wrap items-start justify-between gap-3 py-2 text-sm">
                  <div class="min-w-0">
                    <div class="font-medium text-gray-900">{{ r.period }}</div>
                    <div class="text-gray-700">{{ r.reason }}</div>
                    <div class="text-xs text-gray-500">Requested by {{ r.requested_by_name || '-' }}, {{ r.requested_at }}</div>
                  </div>
                  <div v-if="canGovern" class="flex items-center gap-1.5">
                    <button v-if="r.requested_by !== me" type="button" class="maiic-action maiic-action-view" :title="'Approve and unlock ' + r.period" @click="approveUnlock(r)"><font-awesome-icon icon="check"/></button>
                    <button v-if="r.requested_by !== me" type="button" class="maiic-action maiic-action-delete" title="Reject" @click="closeUnlock(r, 'reject')"><font-awesome-icon icon="times"/></button>
                    <button v-else type="button" class="maiic-action maiic-action-edit" title="Withdraw" @click="closeUnlock(r, 'withdraw')"><font-awesome-icon icon="ban"/></button>
                  </div>
                </li>
              </ul>
            </div>
            <div class="maiic-table-wrap">
              <table class="maiic-table">
                <thead><tr><th>Period</th><th>Method</th><th class="num">Rows</th><th class="num">Flagged</th><th>Locked</th><th v-if="canGovern" class="text-right">Action</th></tr></thead>
                <tbody>
                  <tr v-for="p in pageOf(periods, 'periods')" :key="p.reporting_period + p.build_method">
                    <td>{{ p.reporting_period }}</td><td>{{ p.build_method }}</td><td class="num">{{ fmt(p.n) }}</td><td class="num">{{ fmt(p.flagged) }}</td>
                    <td><span v-if="locks.includes(p.reporting_period)" class="maiic-badge maiic-badge-grey">Locked</span><span v-else class="text-xs text-gray-500 dark:text-slate-400">Open<template v-if="runOfRecord[p.reporting_period] && runOfRecord[p.reporting_period].pre_run_records"> · no run record</template></span></td>
                    <td v-if="canGovern" class="text-right">
                      <span v-if="unlockRequested(p.reporting_period)" class="text-xs text-amber-700">Unlock requested</span>
                      <button v-else type="button" class="maiic-action maiic-action-neutral" :title="(locks.includes(p.reporting_period) ? 'Request the unlock of ' : 'Lock ') + p.reporting_period" @click="openLock(p.reporting_period)">
                        <font-awesome-icon :icon="locks.includes(p.reporting_period) ? 'lock-open' : 'lock'"/>
                      </button>
                    </td>
                  </tr>
                  <tr v-if="!periods.length"><td :colspan="canGovern ? 6 : 5" class="maiic-empty">Nothing has been built from the loaded packs yet. Use the Loan Book Builds tab to propose one.</td></tr>
                </tbody>
              </table>
            </div>
            <LocalPager v-model="page.periods" :total="periods.length"/>
          </template>

          <!-- Query register -->
          <template v-else>
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-3">
              <p class="text-sm text-gray-500">Each E-Banker extract: its source table, its key, and whether it is pulled incrementally from the last key loaded.</p>
              <input v-model="querySearch" class="maiic-input md:w-72" placeholder="Search query, title or table" @input="page.queries = 1"/>
            </div>
            <div class="maiic-table-wrap">
              <table class="maiic-table">
                <thead><tr><th>Query</th><th>Title</th><th>Source table</th><th>Key</th><th>Date column</th><th>Incremental</th><th class="num">Rows loaded</th><th>Last date</th></tr></thead>
                <tbody>
                  <tr v-for="q in pageOf(filteredQueries, 'queries')" :key="q.query_id">
                    <td class="font-mono text-xs whitespace-nowrap">{{ q.query_id }} v{{ q.version }}</td><td>{{ q.title }}</td><td class="font-mono text-xs">{{ q.source_table || '-' }}</td><td class="font-mono text-xs">{{ q.key_column || 'row' }}</td><td class="font-mono text-xs">{{ q.date_column || '-' }}</td>
                    <td><span v-if="q.incremental" class="maiic-badge maiic-badge-green">Yes</span><span v-else class="text-gray-400">No</span></td>
                    <td class="num">{{ fmt(q.rows) }}</td><td class="text-xs whitespace-nowrap">{{ q.last_date || '' }}</td>
                  </tr>
                  <tr v-if="!filteredQueries.length"><td colspan="8" class="maiic-empty">{{ queries.length ? 'No query matches the search.' : 'The query register is empty.' }}</td></tr>
                </tbody>
              </table>
            </div>
            <LocalPager v-model="page.queries" :total="filteredQueries.length"/>
          </template>
        </div>
      </div>
    </div>

    <!-- Load a pack -->
    <jet-modal :show="packOpen" max-width="5xl" @close="packOpen = false">
      <form class="p-6" @submit.prevent="landPack">
        <h3 class="text-lg font-semibold text-gray-900">Load an E-Banker pack</h3>
        <ImportSpecPanel class="mt-3" spec-key="ebanker-pack" :collapsed="true"/>
        <div class="mt-3 grid gap-4 md:grid-cols-2">
          <div class="rounded-lg bg-maiic-50 p-3 text-sm text-gray-700">
            <p class="font-semibold text-maiic-800">What a pack is</p>
            <p class="mt-1">The results of the versioned E-Banker queries, one CSV file per query, with a <span class="font-mono">manifest.json</span> that lists each file, its query and its row count, zipped into one file.</p>
            <p class="mt-2 font-semibold text-maiic-800">How to make one</p>
            <ol class="mt-1 list-decimal space-y-0.5 pl-5">
              <li>Press <strong>Download the queries</strong> and run them in SQL Developer, starting with RUN 0 (ISO dates and a point for decimals).</li>
              <li>Export each result as CSV with its query id in the file name (for example <span class="font-mono">P1_01_ledger_all.csv</span>).</li>
              <li>Write the manifest: every file, its query id and its row count.</li>
              <li>Zip the CSV files and the manifest together, and choose the zip below.</li>
            </ol>
          </div>
          <div class="rounded-lg bg-gray-50 p-3 text-sm text-gray-700">
            <p class="font-semibold text-gray-800">What happens when you load it</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5">
              <li>Every file is checked against the manifest before anything is kept: the row counts, the accounts against the account master, the balances against the ledger, the month-end rows and the date format.</li>
              <li>A pack that fails a check is quarantined: it is listed with the file and row named, and nothing reads it.</li>
              <li>The same pack loaded twice is recognised and nothing changes; a row that changed is kept as a new version.</li>
              <li>Loading a pack does not change the loan book: a build does, on the Loan Book Builds tab, with a second person's approval.</li>
            </ul>
          </div>
        </div>
        <div class="mt-4">
          <label class="maiic-flabel" for="pack-file">Pack (zip)</label>
          <input id="pack-file" type="file" accept=".zip" class="block w-full text-sm" @change="packFile = $event.target.files[0]" />
        </div>
        <label class="mt-3 flex items-center gap-2 text-sm text-gray-700"><input v-model="packDryRun" type="checkbox" /> Check only: run the checks and keep nothing</label>
        <div class="mt-6 flex justify-end gap-2">
          <button type="button" class="secondary-btn" :disabled="landing" @click="packOpen = false">Cancel</button>
          <button type="submit" class="primary-btn" :disabled="!packFile || landing">{{ landing ? 'Checking...' : (packDryRun ? 'Check the pack' : 'Load the pack') }}</button>
        </div>
      </form>
    </jet-modal>

    <!-- Propose a build -->
    <jet-modal :show="buildOpen" max-width="2xl" @close="buildOpen = false">
      <form class="p-6" @submit.prevent="requestBuild">
        <h3 class="text-lg font-semibold text-gray-900">Propose a build of the loan book</h3>
        <div class="mt-3 rounded-lg bg-maiic-50 p-3 text-sm text-gray-700">
          <ul class="list-disc space-y-0.5 pl-5">
            <li>A build writes the month-end loan book for each month in the range from the packs loaded. It is only a proposal until a second person approves it here.</li>
            <li><strong>A: from the stored run</strong> takes E-Banker's own Loan Book Report for the month end. <strong>B: derived from the ledger</strong> rebuilds each balance from the postings. Leave it on the method in force unless you have a reason.</li>
            <li>The proposal shows, month by month, what would change against the loan book held now. A locked month is never restated.</li>
            <li>Calculations already run for a month (staging, PD, LGD, ECL, EIR) are not re-run by the build; run them again after it.</li>
          </ul>
        </div>
        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
          <label><span class="maiic-flabel">From</span><input v-model="build.from" type="month" class="maiic-input" required/></label>
          <label><span class="maiic-flabel">To</span><input v-model="build.to" type="month" class="maiic-input" required/></label>
          <label><span class="maiic-flabel">Method</span>
            <select v-model="build.method" class="maiic-select"><option value="">In force on each period end</option><option value="A">A: from the stored run</option><option value="B">B: derived from the ledger</option></select>
          </label>
        </div>
        <label class="mt-3 flex items-center gap-2 text-sm text-gray-700" title="Retires rows of this month that came from older file imports, not from any build, and that this build does not produce. Mega Farm and out-of-scope rows are never touched."><input v-model="build.retire_stale" type="checkbox"/> Retire rows from older imports that this build does not produce</label>
        <div class="mt-6 flex justify-end gap-2">
          <button type="button" class="secondary-btn" :disabled="building" @click="buildOpen = false">Cancel</button>
          <button type="submit" class="primary-btn" :disabled="building || !build.from || !build.to">{{ building ? 'Proposing...' : 'Propose the build' }}</button>
        </div>
      </form>
    </jet-modal>

    <!-- Lock or unlock a reporting period -->
    <jet-modal :show="!!lockForm.period" max-width="lg" @close="lockForm.period = null">
      <form class="p-6" @submit.prevent="submitLock">
        <h3 class="text-lg font-semibold text-gray-900">{{ lockForm.unlock ? 'Request the unlock of' : 'Lock' }} {{ lockForm.period }}</h3>
        <p class="mt-2 text-sm text-gray-600" v-if="!lockForm.unlock">A locked period is never restated: the loan book build, staging, the PD, the LGD, the forward-looking route, the ECL, the revenue roll-forward and write-offs all refuse to write it. The governance values in force are kept with the lock.</p>
        <p class="mt-2 text-sm text-gray-600" v-else>Unlocking lets the period be restated, so it is not one person's act: this records the request with its reason, and the period stays locked until a different person with EIR governance approves it. Give the approval for the restatement as the reason.</p>
        <!-- the run whose figures the lock reports (run records, Staging Module Specification v1 section 8.8) -->
        <template v-if="!lockForm.unlock && lockStatus">
          <p v-if="lockStatus.ok && lockStatus.run_id" class="mt-3 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">
            The lock names run {{ lockStatus.run_id }}, the month-end run whose figures the month reports.
          </p>
          <div v-else-if="!lockStatus.ok && lockStatus.pre_run_records" class="mt-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
            <p>This month has no run record: it was computed before run records existed, so a lock cannot name the run behind its figures.</p>
            <label class="mt-2 flex items-start gap-2">
              <input v-model="lockForm.withoutRunRecord" type="checkbox" class="mt-0.5 rounded border-gray-300">
              <span>Lock it without a run record. The lock and the audit log say so; the reason must explain it (at least 20 characters).</span>
            </label>
          </div>
          <p v-else-if="!lockStatus.ok" class="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-900 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200">
            {{ lockStatus.message }}
          </p>
        </template>
        <label class="mt-4 block"><span class="maiic-flabel">Reason (written to the audit log)</span>
          <textarea v-model="lockForm.reason" rows="3" class="maiic-input" minlength="10" maxlength="500" required></textarea>
        </label>
        <p v-if="$page.props.errors.reason" class="mt-1 text-xs text-red-600">{{ $page.props.errors.reason }}</p>
        <div class="mt-6 flex justify-end gap-2">
          <button type="button" class="secondary-btn" :disabled="locking" @click="lockForm.period = null">Cancel</button>
          <button type="submit" class="primary-btn" :disabled="locking || !lockAllowed">{{ locking ? 'Saving...' : (lockForm.unlock ? 'Request the unlock' : (lockForm.withoutRunRecord ? 'Lock without a run record' : 'Lock the period')) }}</button>
        </div>
      </form>
    </jet-modal>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetModal from '@/Jetstream/Modal.vue'
import LocalPager from '@/Components/Data/LocalPager.vue'
import ImportSpecPanel from '@/Components/Imports/ImportSpecPanel.vue'
import { router } from '@inertiajs/vue3'
import { confirmDialog } from '@/Components/confirmDialog'
import { promptReason } from '@/Components/Maiic/promptReason.js'

export default {
  components: { AppLayout, JetModal, LocalPager, ImportSpecPanel },
  props: { tab: { type: String, default: 'loads' }, tabCounts: Object, loads: Array, queries: Array, builds: Array, periods: Array, routeInForce: String, methodInForce: String, locks: Array, runOfRecord: { type: Object, default: () => ({}) }, unlockRequests: { type: Array, default: () => [] }, lastLedgerDate: String, canGovern: Boolean, me: Number },
  data() {
    return {
      open: null, querySearch: '',
      page: { loads: 1, builds: 1, periods: 1, queries: 1 },
      packFile: null, packDryRun: false, packOpen: false, buildOpen: false, landing: false, building: false, locking: false, lockForm: { period: null, unlock: false, reason: '', withoutRunRecord: false }, build: { from: '', to: '', method: '', retire_stale: false },
    }
  },
  computed: {
    lockStatus() { return this.lockForm.period ? (this.runOfRecord[this.lockForm.period] || null) : null },
    /** a lock names its run of record; without one only by an explicit choice, for a month before run records, with a longer reason */
    lockAllowed() {
      const reason = this.lockForm.reason.trim().length
      if (this.lockForm.unlock || !this.lockStatus || this.lockStatus.ok) return reason >= 10
      return this.lockStatus.pre_run_records && this.lockForm.withoutRunRecord && reason >= 20
    },
    filteredQueries() {
      const s = this.querySearch.trim().toLowerCase()
      if (!s) return this.queries
      return this.queries.filter(q => [q.query_id, q.title, q.source_table].some(v => String(v || '').toLowerCase().includes(s)))
    },
  },
  methods: {
    pageOf(rows, key) { const p = this.page[key]; return rows.slice((p - 1) * 15, p * 15) },
    fmt(n) { return Number(n || 0).toLocaleString() },
    gateCount(l, result) { return (l.gates || []).filter(g => g.result === result).length },
    statusBadge(s) { return s === 'LANDED' ? 'maiic-badge-green' : (s === 'QUARANTINED' ? 'maiic-badge-red' : 'maiic-badge-grey') },
    landPack() {
      if (!this.packFile) return
      this.landing = true
      router.post(route('eir-feed.land'), { pack: this.packFile, dry_run: this.packDryRun ? 1 : 0 }, { forceFormData: true, preserveScroll: true, onFinish: () => { this.landing = false; if (!this.packDryRun) { this.packFile = null; this.packOpen = false } } })
    },
    requestBuild() {
      this.building = true
      router.post(route('eir-feed.build'), this.build, { preserveScroll: true, onFinish: () => { this.building = false; this.buildOpen = false } })
    },
    openLock(period) {
      this.lockForm = { period, unlock: this.locks.includes(period), reason: '', withoutRunRecord: false }
    },
    submitLock() {
      this.locking = true
      router.post(route('eir-feed.lock-period', this.lockForm.period), { reason: this.lockForm.reason, unlock: this.lockForm.unlock ? 1 : 0, without_run_record: this.lockForm.withoutRunRecord && !this.lockForm.unlock ? 1 : 0 }, { preserveScroll: true, onSuccess: () => { this.lockForm.period = null }, onFinish: () => { this.locking = false } })
    },
    unlockRequested(period) { return this.unlockRequests.some(r => r.period === period) },
    async approveUnlock(r) {
      if (!(await confirmDialog({ title: 'Unlock ' + r.period + '?', message: 'Requested by ' + (r.requested_by_name || 'another user') + ': ' + r.reason + '. Once unlocked, every engine may restate the period.', confirmLabel: 'Approve and unlock', tone: 'danger' }))) return
      router.post(route('eir-feed.unlock.approve', r.id), {}, { preserveScroll: true })
    },
    async closeUnlock(r, action) {
      const words = { reject: 'Reject', withdraw: 'Withdraw' }
      const reason = await promptReason({ title: words[action] + ' the unlock of ' + r.period, message: 'Give the reason; it is kept with the request and in the audit trail.', confirmLabel: words[action] })
      if (!reason) return
      router.post(route('eir-feed.unlock.close', r.id), { action, reason }, { preserveScroll: true })
    },
    async approve(id) {
      if (!(await confirmDialog({ title: 'Approve build ' + id + '?', message: 'The build is written to the loan book. The differences from the previous build are kept on the build.', confirmLabel: 'Approve and build' }))) return
      router.post(route('eir-feed.approve', id), {}, { preserveScroll: true })
    },
  },
}
</script>
