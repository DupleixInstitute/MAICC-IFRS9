<template>
  <app-layout title="Client Book Reconciliation" description="The client's own loan books against the book the system rebuilt from the E-Banker queries and the ledger: every difference, and the governed overrides that take the client's figure where its book is the right one">
    <template #actions>
      <template v-if="canGovern && tab === 'books'">
        <button type="button" class="secondary-btn" @click="openTemplate"><font-awesome-icon icon="download" /> Download template</button>
        <button type="button" class="primary-btn" @click="openLand"><font-awesome-icon icon="upload" /> Land a client book</button>
      </template>
      <template v-if="canGovern && tab === 'differences' && selectedBook">
        <a :href="route('client-books.differences-csv', selectedBook.id)" class="secondary-btn" v-if="selectedBook.csv_path"><font-awesome-icon icon="file-download" /> Differences CSV</a>
        <button type="button" class="secondary-btn" @click="compare(selectedBook)"><font-awesome-icon icon="sync-alt" /> Compare again</button>
        <button type="button" class="primary-btn" :disabled="!picked.length" @click="proposeSelected"><font-awesome-icon icon="check-circle" /> Propose the client's figures ({{ picked.length }})</button>
      </template>
      <template v-if="canGovern && tab === 'overrides'">
        <button type="button" class="primary-btn" @click="openOverride"><font-awesome-icon icon="plus" /> Propose override</button>
      </template>
      <template v-if="canGovern && tab === 'overlays'">
        <button type="button" class="primary-btn" @click="openOverlay"><font-awesome-icon icon="upload" /> Import Finance overlay</button>
      </template>
    </template>

    <div class="w-full space-y-5">
      <KpiRow :cards="cards" />

      <PageTabs v-model="tab" :tabs="tabs" query="tab" />

      <!-- ============ Client books ============ -->
      <template v-if="tab === 'books'">
        <Notice id="client-books-intro">
          The system's loan book for a month is rebuilt from the E-Banker queries and the ledger. Land the loan book the client gave for the same month-end
          (MAIIC's monthly E-Banker loan book report, the audited ECL model, or the template below filled in) and the system compares the two loan by loan: the balance,
          the arrears bracket and, where the client gives one, the stage. Each difference is then decided on the Differences tab: an override that takes the client's figure,
          approved by a second person and applied when the month is rebuilt, or the system's figure kept with the reason. The file is kept as received and is the evidence of every override proposed from it.
        </Notice>
        <div data-actions-anchor></div>
        <div class="maiic-panel">
          <div class="border-b border-gray-200 p-4 dark:border-slate-700">
            <h3 class="font-semibold text-gray-900 dark:text-slate-100">Client books landed</h3>
            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Newest month first. "To decide" counts the open differences; the differences CSV is written beside the register each time a book is compared.</p>
          </div>
          <div class="maiic-table-wrap">
            <table class="maiic-table maiic-table-compact">
              <thead><tr><th>Month-end</th><th>Client book</th><th class="num">Rows</th><th class="num">Total</th><th>Compared</th><th class="num">Same</th><th class="num">To decide</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
              <tbody>
                <tr v-for="b in pagedBooks" :key="b.id">
                  <td class="whitespace-nowrap font-semibold">{{ b.reporting_period }}</td>
                  <td class="max-w-[22rem] text-xs"><div class="font-semibold text-gray-900 dark:text-slate-100">{{ b.source_label }}</div><div class="text-gray-500 dark:text-slate-400">{{ b.kind_label }} - {{ b.file_name }} <span class="text-gray-400">#{{ b.id }}</span></div><div class="text-gray-400">Landed {{ (b.landed_at || '').slice(0, 16) }} by {{ b.landed_by_name || '-' }}</div></td>
                  <td class="num">{{ b.row_count }}</td>
                  <td class="num text-xs">{{ money(b.rows_total) }}<div v-if="b.printed_total !== null" class="text-gray-400">printed {{ money(b.printed_total) }}</div></td>
                  <td class="whitespace-nowrap text-xs">{{ b.compared_at ? b.compared_at.slice(0, 16) : 'not yet' }}<div v-if="b.summary" class="text-gray-400">{{ b.summary.compared }} matched</div></td>
                  <td class="num">{{ b.summary ? b.summary.same : '-' }}</td>
                  <td class="num"><span :class="b.open ? 'font-semibold text-amber-700 dark:text-amber-300' : ''">{{ b.summary ? b.open : '-' }}</span></td>
                  <td><span class="maiic-badge" :class="b.status === 'COMPARED' ? 'maiic-badge-green' : (b.status === 'WITHDRAWN' ? 'maiic-badge-grey' : 'maiic-badge-gold')">{{ bookLabel(b.status) }}</span></td>
                  <td class="text-right">
                    <div class="flex justify-end gap-1.5">
                      <Link v-if="b.status !== 'WITHDRAWN' && b.summary" :href="route('client-books.index', { book: b.id, tab: 'differences' })" class="maiic-action maiic-action-view" title="Open the differences"><font-awesome-icon icon="table" /></Link>
                      <a :href="route('client-books.file', b.id)" class="maiic-action maiic-action-view" title="The file as received"><font-awesome-icon icon="paperclip" /></a>
                      <a v-if="b.csv_path" :href="route('client-books.differences-csv', b.id)" class="maiic-action maiic-action-view" title="Differences CSV"><font-awesome-icon icon="file-download" /></a>
                      <template v-if="canGovern && b.status !== 'WITHDRAWN'">
                        <button type="button" class="maiic-action maiic-action-edit" title="Compare with the system's book" @click="compare(b)"><font-awesome-icon icon="sync-alt" /></button>
                        <button type="button" class="maiic-action maiic-action-delete" title="Withdraw (landed in error)" @click="withdrawBook(b)"><font-awesome-icon icon="ban" /></button>
                      </template>
                    </div>
                  </td>
                </tr>
                <tr v-if="!books.length"><td colspan="9" class="maiic-empty">No client book is landed yet. Use <strong>Land a client book</strong> above this table, at its top right.</td></tr>
              </tbody>
            </table>
          </div>
          <RowPager v-model="bookPage" :total="books.length" class="border-t border-gray-100 dark:border-slate-700" />
        </div>
      </template>

      <!-- ============ Differences ============ -->
      <template v-if="tab === 'differences'">
        <div class="maiic-panel p-4">
          <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-[18rem]">
              <label class="maiic-flabel" for="cb-book">Client book</label>
              <select id="cb-book" class="maiic-select" :value="bookId || ''" @change="go({ book: $event.target.value || null, status: status || null })">
                <option value="">Choose a compared book</option>
                <option v-for="b in comparedBooks" :key="b.id" :value="b.id">{{ b.reporting_period }} - {{ b.source_label }} (#{{ b.id }})</option>
              </select>
            </div>
            <div>
              <label class="maiic-flabel" for="cb-status">Status</label>
              <select id="cb-status" class="maiic-select" :value="status" @change="go({ book: bookId, status: $event.target.value || null })">
                <option value="">Every status</option>
                <option v-for="(text, key) in diffStatuses" :key="key" :value="key">{{ text }}</option>
              </select>
            </div>
            <div v-if="selectedBook && selectedBook.summary" class="ml-auto text-xs text-gray-600 dark:text-slate-300">
              <span v-for="(n, c) in selectedBook.summary.by_cause" :key="c" class="mr-3 whitespace-nowrap">{{ c }}: <strong>{{ n }}</strong></span>
            </div>
          </div>
        </div>
        <Notice v-if="selectedBook && lockedPeriod(selectedBook.reporting_period)" id="client-books-locked" tone="warning">
          {{ selectedBook.reporting_period }} is a locked period: an override could not take effect because a locked month is never rebuilt. Unlock it under Period close first, or keep the system's figures with the reasons.
        </Notice>
        <div data-actions-anchor></div>
        <div class="maiic-panel">
          <div class="border-b border-gray-200 p-4 dark:border-slate-700">
            <h3 class="font-semibold text-gray-900 dark:text-slate-100">Differences{{ selectedBook ? ' - ' + selectedBook.reporting_period + ', ' + selectedBook.source_label : '' }}</h3>
            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Open differences first. Tick the ones where the client's book is right and propose its figures together, or decide one at a time. Brackets: 0 to 30 days (current included), 31 to 90, 91 to 180, over 180.</p>
          </div>
          <div class="maiic-table-wrap">
            <table class="maiic-table maiic-table-compact">
              <thead><tr>
                <th class="w-8"><input v-if="canGovern" type="checkbox" :checked="allPicked" title="Every open difference on this page" @change="pickPage($event.target.checked)" /></th>
                <th>Loan</th><th class="num">Client amount</th><th class="num">System amount</th><th>Client arrears</th><th>System arrears</th><th>Stage</th><th>Cause</th><th>Status</th><th class="text-right">Actions</th>
              </tr></thead>
              <tbody>
                <tr v-for="d in pagedDiffs" :key="d.id">
                  <td><input v-if="canGovern && d.status === 'OPEN' && d.contract_id" v-model="picked" type="checkbox" :value="d.id" /></td>
                  <td><div class="font-semibold text-gray-900 dark:text-slate-100">{{ d.customer_name || '-' }}</div><div class="text-xs text-gray-500 dark:text-slate-400">{{ d.contract_id || 'no system loan' }}<span v-if="d.matched_on" class="text-gray-400"> - by {{ d.matched_on }}</span></div></td>
                  <td class="num" :class="d.balance_differs ? 'font-semibold text-amber-700 dark:text-amber-300' : ''">{{ money(d.client_amount) }}</td>
                  <td class="num">{{ money(d.system_amount) }}<div v-if="d.client_amount !== null && d.system_amount !== null && d.balance_differs" class="text-xs text-gray-400">diff {{ money(d.client_amount - d.system_amount) }}</div></td>
                  <td class="whitespace-nowrap text-xs" :class="d.bucket_differs ? 'font-semibold text-amber-700 dark:text-amber-300' : ''">{{ d.client_bracket || '-' }}<div v-if="d.client_days !== null" class="text-gray-400">{{ d.client_days }} days</div></td>
                  <td class="whitespace-nowrap text-xs">{{ d.system_bracket || '-' }}<div v-if="d.system_days !== null" class="text-gray-400">{{ d.system_days }} days</div></td>
                  <td class="whitespace-nowrap text-xs" :class="d.stage_differs ? 'font-semibold text-amber-700 dark:text-amber-300' : ''">{{ d.client_stage ? 'client ' + d.client_stage : '' }}<div>{{ d.system_stage ? 'system ' + d.system_stage : '-' }}</div></td>
                  <td class="max-w-[14rem] text-xs">{{ d.cause }}</td>
                  <td class="text-xs">
                    <span class="maiic-badge" :class="diffBadge(d.status)">{{ diffStatuses[d.status] || d.status }}</span>
                    <div v-if="d.staging_override_id" class="mt-1 text-gray-500 dark:text-slate-400">staging #{{ d.staging_override_id }}</div>
                    <div v-if="d.gross_override_id" class="text-gray-500 dark:text-slate-400">gross #{{ d.gross_override_id }}</div>
                    <div v-if="d.decision_reason" class="mt-1 max-w-[14rem] text-gray-500 dark:text-slate-400">{{ d.decided_by_name }}: {{ d.decision_reason }}</div>
                  </td>
                  <td class="text-right">
                    <div v-if="canGovern" class="flex justify-end gap-1.5">
                      <button v-if="['OPEN', 'KEPT'].includes(d.status) && d.contract_id" type="button" class="maiic-action maiic-action-view" title="Propose the client's figure as an override" @click="openPropose(d)"><font-awesome-icon icon="check" /></button>
                      <button v-if="d.status === 'OPEN'" type="button" class="maiic-action maiic-action-edit" title="Keep the system's figure" @click="keep(d, 'keep')"><font-awesome-icon icon="lock" /></button>
                      <button v-if="d.status === 'KEPT'" type="button" class="maiic-action maiic-action-edit" title="Open again" @click="keep(d, 'reopen')"><font-awesome-icon icon="history" /></button>
                    </div>
                  </td>
                </tr>
                <tr v-if="!differences.length"><td colspan="10" class="maiic-empty">{{ bookId ? 'No difference with this status. The books agree on every loan compared, or every difference is decided.' : 'Choose a compared client book above.' }}</td></tr>
              </tbody>
            </table>
          </div>
          <RowPager v-model="diffPage" :total="differences.length" class="border-t border-gray-100 dark:border-slate-700" />
        </div>
      </template>

      <!-- ============ Overrides ============ -->
      <template v-if="tab === 'overrides'">
        <Notice id="client-books-overrides">
          An override takes a client's or audited figure for one loan in one month: a staging day count, or a gross (carrying) amount. One person proposes it with the reason;
          its evidence is the client's book (linked automatically when it comes from a reconciliation) or a document attached on its record; a different person approves it.
          It changes nothing until the month is rebuilt, and the system's own figure stays named on the loan beside it. After the rebuild, run the month-end (staging, PD, LGD, ECL) and compare the client's book again.
        </Notice>
        <div v-if="rebuilds.length" class="maiic-panel border-amber-300 p-4 dark:border-amber-700">
          <h3 class="font-semibold text-gray-900 dark:text-slate-100">Rebuilds due</h3>
          <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Months with an approved override the loan book has not been rebuilt with yet.</p>
          <div class="mt-3 space-y-2">
            <div v-for="r in rebuilds" :key="r.period" class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-slate-700">
              <div><strong>{{ r.period }}</strong>: {{ r.overrides }} approved override(s); last built {{ r.last_built ? r.last_built.slice(0, 16) : 'never' }}<span v-if="r.locked" class="ml-2 font-semibold text-red-700 dark:text-red-300">locked</span></div>
              <div v-if="canGovern && !r.locked">
                <Link v-if="r.proposed_build" :href="route('eir-feed.index', { tab: 'builds' })" class="secondary-btn">Build #{{ r.proposed_build }} proposed: approve it on Loan Book Builds</Link>
                <button v-else type="button" class="primary-btn" @click="proposeRebuild(r.period)">Propose the rebuild of {{ r.period }}</button>
              </div>
            </div>
          </div>
        </div>
        <div data-actions-anchor></div>
        <div class="maiic-panel">
          <div class="border-b border-gray-200 p-4 dark:border-slate-700">
            <h3 class="font-semibold text-gray-900 dark:text-slate-100">Override register</h3>
            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Proposals first, then the approved overrides, then the closed ones. The paperclip opens the evidence and the history; an approval is refused without a document.</p>
          </div>
          <div class="maiic-table-wrap">
            <table class="maiic-table maiic-table-compact">
              <thead><tr><th>Loan</th><th>Month-end</th><th>Override</th><th>Reason</th><th class="num">Evidence</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
              <tbody>
                <tr v-for="o in pagedOverrides" :key="o.type + o.id">
                  <td><div class="font-semibold text-gray-900 dark:text-slate-100">{{ o.customer_name || '-' }}</div><div class="text-xs text-gray-500 dark:text-slate-400">{{ o.contract_id }}</div></td>
                  <td class="whitespace-nowrap">{{ o.reporting_period }}</td>
                  <td class="whitespace-nowrap text-xs">
                    <template v-if="o.type === 'staging'"><div class="font-semibold">Staging #{{ o.id }}</div><div>{{ o.override_days !== null ? o.override_days + ' days' : "the directive's count" }}</div><div class="text-gray-400">{{ sourceLabel(o.source) }}</div></template>
                    <template v-else><div class="font-semibold">Gross amount #{{ o.id }}</div><div>{{ money(o.override_amount) }}</div></template>
                  </td>
                  <td class="max-w-[24rem] text-xs text-gray-700 dark:text-slate-300">{{ o.reason }}</td>
                  <td class="num">{{ o.documents }}</td>
                  <td class="text-xs"><span class="maiic-badge" :class="ovBadge(o.status)">{{ ovLabel(o.status) }}</span>
                    <div class="mt-1 text-gray-500 dark:text-slate-400">Proposed by {{ o.proposed_by_name || '-' }}</div>
                    <div v-if="o.approved_by_name && o.status !== 'PROPOSED'" class="text-gray-500 dark:text-slate-400">{{ o.status === 'REJECTED' ? 'Rejected' : 'Approved' }} by {{ o.approved_by_name }}</div>
                  </td>
                  <td class="text-right">
                    <div class="flex justify-end gap-1.5">
                      <Link :href="route('approvals.record', [o.workflow, o.id])" class="maiic-action maiic-action-view" title="Evidence and history"><font-awesome-icon icon="paperclip" /></Link>
                      <template v-if="canGovern">
                        <button v-if="o.status === 'PROPOSED' && o.proposed_by !== me" type="button" class="maiic-action maiic-action-view" title="Approve" @click="decide(o, 'approve')"><font-awesome-icon icon="check" /></button>
                        <button v-if="o.status === 'PROPOSED' && o.proposed_by !== me" type="button" class="maiic-action maiic-action-delete" title="Reject" @click="decide(o, 'reject')"><font-awesome-icon icon="times" /></button>
                        <button v-if="o.status === 'PROPOSED' && o.proposed_by === me" type="button" class="maiic-action maiic-action-edit" title="Withdraw" @click="decide(o, 'withdraw')"><font-awesome-icon icon="ban" /></button>
                        <button v-if="o.status === 'APPROVED'" type="button" class="maiic-action maiic-action-edit" title="Withdraw (stops applying at the next rebuild)" @click="decide(o, 'withdraw')"><font-awesome-icon icon="history" /></button>
                      </template>
                    </div>
                  </td>
                </tr>
                <tr v-if="!overrides.length"><td colspan="7" class="maiic-empty">No override is proposed. Propose one from a difference on the Differences tab, or with <strong>Propose override</strong> above this table.</td></tr>
              </tbody>
            </table>
          </div>
          <RowPager v-model="ovPage" :total="overrides.length" class="border-t border-gray-100 dark:border-slate-700" />
        </div>
      </template>

      <!-- ============ Finance overlays ============ -->
      <template v-if="tab === 'overlays'">
        <Notice id="client-books-overlays">
          A Finance overlay records Finance's own figure for a loan beside the system's, as it was at the time, and changes nothing. Where Finance's figure is the right one,
          it applies only through an approved override: land Finance's figures as a client book (the template) and propose them from the Differences tab, or propose an override directly.
        </Notice>
        <div data-actions-anchor></div>
        <div class="maiic-panel">
          <div class="border-b border-gray-200 p-4 dark:border-slate-700">
            <h3 class="font-semibold text-gray-900 dark:text-slate-100">Finance overlays</h3>
            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">Ageing overlays (day counts and brackets) and gross amount overlays, newest month first.</p>
          </div>
          <div class="maiic-table-wrap">
            <table class="maiic-table maiic-table-compact">
              <thead><tr><th>Loan</th><th>Month-end</th><th>Overlay</th><th>System</th><th>Reference</th><th>Finance</th><th>Comment</th></tr></thead>
              <tbody>
                <tr v-for="o in pagedOverlays" :key="o.kind + o.id">
                  <td><div class="font-semibold text-gray-900 dark:text-slate-100">{{ o.customer_name || '-' }}</div><div class="text-xs text-gray-500 dark:text-slate-400">{{ o.contract_id }}</div></td>
                  <td class="whitespace-nowrap">{{ o.reporting_period }}</td>
                  <td class="whitespace-nowrap text-xs">{{ o.kind === 'ageing' ? 'Ageing' : 'Gross amount' }}<div class="text-gray-400">{{ o.source_file }}</div></td>
                  <td class="whitespace-nowrap text-xs">{{ o.kind === 'ageing' ? (o.system_bracket || '-') + (o.system_days !== null ? ' (' + o.system_days + ' days)' : '') : money(o.system_amount) }}</td>
                  <td class="whitespace-nowrap text-xs">{{ o.kind === 'ageing' ? (o.workbook_bracket || '-') : money(o.reference_amount) }}</td>
                  <td class="whitespace-nowrap text-xs">{{ o.kind === 'ageing' ? (o.finance_bracket || '-') + (o.finance_days !== null ? ' (' + o.finance_days + ' days)' : '') : money(o.finance_amount) }}</td>
                  <td class="max-w-[24rem] text-xs text-gray-600 dark:text-slate-400">{{ o.comment }}</td>
                </tr>
                <tr v-if="!allOverlays.length"><td colspan="7" class="maiic-empty">No Finance overlay is recorded. Use <strong>Import Finance overlay</strong> above this table.</td></tr>
              </tbody>
            </table>
          </div>
          <RowPager v-model="olPage" :total="allOverlays.length" class="border-t border-gray-100 dark:border-slate-700" />
        </div>
      </template>
    </div>

    <!-- land a client book -->
    <jet-modal :show="modal === 'land'" max-width="2xl" @close="modal = null">
      <form class="p-6" @submit.prevent="submitLand">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Land a client book</h3>
        <p class="mt-1 text-sm text-gray-600 dark:text-slate-400">The file is read as received and kept as the evidence. It is compared with the system's book of the same month-end as soon as it lands.</p>
        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <label class="maiic-flabel" for="ld-period">Month-end</label>
            <select id="ld-period" v-model="land.period" class="maiic-select"><option value="">Choose the month</option><option v-for="p in periods" :key="p" :value="p">{{ p }}</option></select>
            <p v-if="land.errors.period" class="mt-1 text-xs text-red-600">{{ land.errors.period }}</p>
          </div>
          <div>
            <label class="maiic-flabel" for="ld-kind">Kind of book</label>
            <select id="ld-kind" v-model="land.kind" class="maiic-select"><option v-for="(text, key) in kinds" :key="key" :value="key">{{ text }}</option></select>
            <p v-if="land.errors.kind" class="mt-1 text-xs text-red-600">{{ land.errors.kind }}</p>
          </div>
          <div class="md:col-span-2">
            <label class="maiic-flabel" for="ld-label">Name</label>
            <input id="ld-label" v-model="land.label" type="text" class="maiic-input" placeholder="For example: E-Banker loan book report, 31 August 2026 (received 11 September 2026)" />
            <p v-if="land.errors.label" class="mt-1 text-xs text-red-600">{{ land.errors.label }}</p>
          </div>
          <div class="md:col-span-2">
            <label class="maiic-flabel" for="ld-file">File (.xls, .xlsx or .csv)</label>
            <input id="ld-file" type="file" accept=".xls,.xlsx,.csv" class="maiic-input" @input="land.file = $event.target.files[0]" />
            <p v-if="land.errors.file" class="mt-1 text-xs text-red-600">{{ land.errors.file }}</p>
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-2">
          <button type="button" class="secondary-btn" :disabled="land.processing" @click="modal = null">Cancel</button>
          <button type="submit" class="primary-btn" :disabled="land.processing">{{ land.processing ? 'Landing and comparing...' : 'Land and compare' }}</button>
        </div>
      </form>
    </jet-modal>

    <!-- download the template -->
    <jet-modal :show="modal === 'template'" max-width="lg" @close="modal = null">
      <div class="p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Download the reconciliation template</h3>
        <p class="mt-1 text-sm text-gray-600 dark:text-slate-400">Every loan of the month with the system's own carrying amount, day count and stage filled in. The client fills its own figures in the blank columns where they differ (a row left blank takes no position) and the file is landed back as a "Reconciliation template".</p>
        <label class="maiic-flabel mt-4" for="tp-period">Month-end</label>
        <select id="tp-period" v-model="templatePeriod" class="maiic-select"><option value="">Choose the month</option><option v-for="p in periods" :key="p" :value="p">{{ p }}</option></select>
        <div class="mt-6 flex justify-end gap-2">
          <button type="button" class="secondary-btn" @click="modal = null">Cancel</button>
          <a :href="templatePeriod ? route('client-books.template', { period: templatePeriod }) : null" class="primary-btn" :class="!templatePeriod ? 'pointer-events-none opacity-50' : ''" @click="modal = null">Download</a>
        </div>
      </div>
    </jet-modal>

    <!-- propose the client's figure for one difference -->
    <jet-modal :show="modal === 'propose'" max-width="2xl" @close="modal = null">
      <form v-if="current" class="p-6" @submit.prevent="submitPropose">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Override {{ current.customer_name || current.contract_id }} for {{ current.reporting_period }}</h3>
        <p class="mt-1 text-sm text-gray-600 dark:text-slate-400">{{ current.cause }}. The client's figures are filled in; change the day count only where the evidence gives a better one, and say so in the reason. A different person approves.</p>
        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
          <div v-if="current.bucket_differs || current.stage_differs">
            <label class="maiic-flabel" for="pr-days">Staging day count</label>
            <input id="pr-days" v-model="propose.days" type="number" min="0" class="maiic-input" />
            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">System: {{ current.system_days ?? '-' }} days ({{ current.system_bracket || '-' }})</p>
            <p v-if="propose.errors.days" class="mt-1 text-xs text-red-600">{{ propose.errors.days }}</p>
          </div>
          <div v-if="current.balance_differs">
            <label class="maiic-flabel" for="pr-amount">Gross (carrying) amount</label>
            <input id="pr-amount" v-model="propose.amount" type="number" step="0.01" class="maiic-input" />
            <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">System: {{ money(current.system_amount) }}</p>
            <p v-if="propose.errors.amount" class="mt-1 text-xs text-red-600">{{ propose.errors.amount }}</p>
          </div>
          <div class="md:col-span-2">
            <label class="maiic-flabel" for="pr-reason">Reason</label>
            <textarea id="pr-reason" v-model="propose.reason" rows="3" class="maiic-input" placeholder="Why the client's figure is the right one: for example, audited and approved in the December 2025 AFS"></textarea>
            <p v-if="propose.errors.reason" class="mt-1 text-xs text-red-600">{{ propose.errors.reason }}</p>
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-2">
          <button type="button" class="secondary-btn" :disabled="propose.processing" @click="modal = null">Cancel</button>
          <button type="submit" class="primary-btn" :disabled="propose.processing">Propose</button>
        </div>
      </form>
    </jet-modal>

    <!-- propose an override directly -->
    <jet-modal :show="modal === 'override'" max-width="2xl" @close="modal = null">
      <form class="p-6" @submit.prevent="submitOverride">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Propose an override</h3>
        <p class="mt-1 text-sm text-gray-600 dark:text-slate-400">For a figure that does not come from a landed client book. Attach its evidence on its record (the paperclip) before a second person approves it.</p>
        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <label class="maiic-flabel" for="ov-type">Override</label>
            <select id="ov-type" v-model="ov.type" class="maiic-select"><option value="staging">Staging day count</option><option value="gross">Gross (carrying) amount</option></select>
          </div>
          <div>
            <label class="maiic-flabel" for="ov-period">Month-end</label>
            <select id="ov-period" v-model="ov.period" class="maiic-select"><option value="">Choose the month</option><option v-for="p in periods" :key="p" :value="p">{{ p }}</option></select>
            <p v-if="ov.errors.period" class="mt-1 text-xs text-red-600">{{ ov.errors.period }}</p>
          </div>
          <div>
            <label class="maiic-flabel" for="ov-contract">E-Banker account</label>
            <input id="ov-contract" v-model="ov.contract_id" type="text" class="maiic-input" placeholder="For example 104420000005" />
            <p v-if="ov.errors.contract_id" class="mt-1 text-xs text-red-600">{{ ov.errors.contract_id }}</p>
          </div>
          <div v-if="ov.type === 'staging'">
            <label class="maiic-flabel" for="ov-days">Day count</label>
            <input id="ov-days" v-model="ov.days" type="number" min="0" class="maiic-input" />
            <p v-if="ov.errors.days" class="mt-1 text-xs text-red-600">{{ ov.errors.days }}</p>
          </div>
          <div v-else>
            <label class="maiic-flabel" for="ov-amount">Amount</label>
            <input id="ov-amount" v-model="ov.amount" type="number" step="0.01" class="maiic-input" />
            <p v-if="ov.errors.amount" class="mt-1 text-xs text-red-600">{{ ov.errors.amount }}</p>
          </div>
          <div v-if="ov.type === 'staging'" class="md:col-span-2">
            <label class="maiic-flabel" for="ov-source">Where the day count comes from</label>
            <select id="ov-source" v-model="ov.source" class="maiic-select"><option value="audited_workbook">An audited figure</option><option value="client_book">The client's book</option></select>
          </div>
          <div class="md:col-span-2">
            <label class="maiic-flabel" for="ov-reason">Reason</label>
            <textarea id="ov-reason" v-model="ov.reason" rows="3" class="maiic-input"></textarea>
            <p v-if="ov.errors.reason" class="mt-1 text-xs text-red-600">{{ ov.errors.reason }}</p>
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-2">
          <button type="button" class="secondary-btn" :disabled="ov.processing" @click="modal = null">Cancel</button>
          <button type="submit" class="primary-btn" :disabled="ov.processing">Propose</button>
        </div>
      </form>
    </jet-modal>

    <!-- import a Finance overlay -->
    <jet-modal :show="modal === 'overlay'" max-width="lg" @close="modal = null">
      <form class="p-6" @submit.prevent="submitOverlay">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-slate-100">Import a Finance overlay</h3>
        <p class="mt-1 text-sm text-gray-600 dark:text-slate-400">A CSV in the overlay layout. It is recorded beside the system's figures and changes nothing.</p>
        <label class="maiic-flabel mt-4" for="ol-type">Overlay</label>
        <select id="ol-type" v-model="overlay.type" class="maiic-select"><option value="ageing">Ageing (day counts and brackets)</option><option value="gross">Gross amount</option></select>
        <label class="maiic-flabel mt-4" for="ol-file">CSV file</label>
        <input id="ol-file" type="file" accept=".csv" class="maiic-input" @input="overlay.file = $event.target.files[0]" />
        <p v-if="overlay.errors.file" class="mt-1 text-xs text-red-600">{{ overlay.errors.file }}</p>
        <div class="mt-6 flex justify-end gap-2">
          <button type="button" class="secondary-btn" :disabled="overlay.processing" @click="modal = null">Cancel</button>
          <button type="submit" class="primary-btn" :disabled="overlay.processing">Import</button>
        </div>
      </form>
    </jet-modal>
  </app-layout>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import JetModal from '@/Jetstream/Modal.vue'
import KpiRow from '@/Components/Maiic/KpiRow.vue'
import Notice from '@/Components/Maiic/Notice.vue'
import PageTabs from '@/Components/Maiic/PageTabs.vue'
import RowPager from '@/Components/Maiic/RowPager.vue'
import { confirmDialog } from '@/Components/confirmDialog'
import { promptReason } from '@/Components/Maiic/promptReason.js'

const props = defineProps({
  books: { type: Array, default: () => [] },
  bookId: { type: Number, default: null },
  status: { type: String, default: '' },
  differences: { type: Array, default: () => [] },
  overrides: { type: Array, default: () => [] },
  rebuilds: { type: Array, default: () => [] },
  overlays: { type: Object, default: () => ({ ageing: [], gross: [] }) },
  kinds: { type: Object, default: () => ({}) },
  periods: { type: Array, default: () => [] },
  locked: { type: Array, default: () => [] },
  me: { type: Number, default: null },
  canGovern: { type: Boolean, default: false },
})

const tab = ref('books')
const tabs = computed(() => [
  { key: 'books', label: 'Client books', count: props.books.length },
  { key: 'differences', label: 'Differences', count: props.differences.length },
  { key: 'overrides', label: 'Overrides', count: props.overrides.length },
  { key: 'overlays', label: 'Finance overlays', count: allOverlays.value.length },
])

const diffStatuses = { OPEN: 'Open', OVERRIDE_PROPOSED: 'Override proposed', KEPT: "System's figure kept", RECONCILED: 'Reconciled', INFO: 'For information' }

// fifteen rows a page on every table, kept on a page that still exists after a decision
const pager = (rows) => {
  const page = ref(1)
  watch(() => rows().length, (n) => { page.value = Math.min(page.value, Math.max(1, Math.ceil(n / 15))) })
  return [page, computed(() => rows().slice((page.value - 1) * 15, page.value * 15))]
}
const [bookPage, pagedBooks] = pager(() => props.books)
const [diffPage, pagedDiffs] = pager(() => props.differences)
const [ovPage, pagedOverrides] = pager(() => props.overrides)
const allOverlays = computed(() => [
  ...props.overlays.ageing.map((o) => ({ ...o, kind: 'ageing' })),
  ...props.overlays.gross.map((o) => ({ ...o, kind: 'gross' })),
])
const [olPage, pagedOverlays] = pager(() => allOverlays.value)

const comparedBooks = computed(() => props.books.filter((b) => b.status === 'COMPARED'))
const selectedBook = computed(() => props.books.find((b) => b.id === props.bookId) || null)
const lockedPeriod = (p) => props.locked.includes(p)

const picked = ref([])
watch(() => props.differences, () => { picked.value = picked.value.filter((id) => props.differences.some((d) => d.id === id && d.status === 'OPEN')) })
const pickable = computed(() => pagedDiffs.value.filter((d) => d.status === 'OPEN' && d.contract_id).map((d) => d.id))
const allPicked = computed(() => pickable.value.length > 0 && pickable.value.every((id) => picked.value.includes(id)))
function pickPage(on) {
  picked.value = on ? [...new Set([...picked.value, ...pickable.value])] : picked.value.filter((id) => !pickable.value.includes(id))
}

function go(params) {
  router.get(route('client-books.index'), { ...params, tab: 'differences' }, { preserveScroll: true, preserveState: false })
}

const modal = ref(null)
const land = useForm({ period: '', kind: 'ebanker_report', label: '', file: null, compare: true })
function openLand() { land.reset(); land.clearErrors(); modal.value = 'land' }
function submitLand() { land.post(route('client-books.land'), { forceFormData: true, preserveScroll: true, onSuccess: () => { if (!Object.keys(land.errors).length) modal.value = null } }) }

const templatePeriod = ref('')
function openTemplate() { templatePeriod.value = props.periods[0] || ''; modal.value = 'template' }

async function compare(b) {
  if (!(await confirmDialog({ title: `Compare book #${b.id} again?`, message: `${b.source_label} is compared with the system's book of ${b.reporting_period} as it stands now. Decisions already made stay with their loans; a loan an approved override now agrees is marked reconciled.`, confirmLabel: 'Compare' }))) return
  router.post(route('client-books.compare', b.id), {}, { preserveScroll: true })
}
async function withdrawBook(b) {
  const reason = await promptReason({ title: `Withdraw client book #${b.id}`, message: 'For a file landed in error. Its open differences go with it; overrides already proposed from it stay on the Overrides tab.', confirmLabel: 'Withdraw' })
  if (!reason) return
  router.post(route('client-books.withdraw', b.id), { reason }, { preserveScroll: true })
}

const current = ref(null)
const propose = useForm({ reason: '', days: null, amount: null })
function openPropose(d) {
  current.value = d
  propose.reset(); propose.clearErrors()
  propose.days = d.bucket_differs || d.stage_differs ? d.client_days : null
  propose.amount = d.balance_differs ? d.client_amount : null
  modal.value = 'propose'
}
function submitPropose() {
  propose.transform((f) => ({ reason: f.reason, days: f.days === '' || f.days === null ? null : Number(f.days), amount: f.amount === '' || f.amount === null ? null : Number(f.amount) }))
    .post(route('client-books.differences.propose', current.value.id), { preserveScroll: true, onSuccess: () => { if (!Object.keys(propose.errors).length) modal.value = null } })
}
async function proposeSelected() {
  const reason = await promptReason({ title: `Propose the client's figures for ${picked.value.length} difference(s)`, message: "One reason for all of them: why the client's book is the right one. Each becomes an override with the client's book as its evidence; a different person approves them.", confirmLabel: 'Propose' })
  if (!reason) return
  router.post(route('client-books.propose-many', props.bookId), { ids: picked.value, reason }, { preserveScroll: true, onSuccess: () => { picked.value = [] } })
}
async function keep(d, action) {
  const reason = await promptReason({
    title: action === 'keep' ? `Keep the system's figure for ${d.customer_name || d.contract_id}` : `Open ${d.customer_name || d.contract_id} again`,
    message: action === 'keep' ? "Why the system's figure stands: for example, the client's book carries a balance E-Banker has since corrected." : 'Why the difference is opened again.',
    confirmLabel: action === 'keep' ? "Keep the system's figure" : 'Open again',
  })
  if (!reason) return
  router.post(route('client-books.differences.keep', d.id), { action, reason }, { preserveScroll: true })
}

const ov = useForm({ type: 'staging', contract_id: '', period: '', days: null, amount: null, source: 'audited_workbook', reason: '' })
function openOverride() { ov.reset(); ov.clearErrors(); ov.period = props.periods[0] || ''; modal.value = 'override' }
function submitOverride() { ov.post(route('client-books.overrides.store'), { preserveScroll: true, onSuccess: () => { if (!Object.keys(ov.errors).length) modal.value = null } }) }

async function decide(o, action) {
  const name = `${o.type === 'staging' ? 'staging' : 'gross amount'} override #${o.id}`
  let reason = null
  if (action === 'approve') {
    const what = o.type === 'staging' ? `${o.override_days} days` : money(o.override_amount)
    if (!(await confirmDialog({ title: `Approve ${name}?`, message: `${o.customer_name || o.contract_id} takes ${what} in ${o.reporting_period} when the month is rebuilt. Approved on the evidence attached (${o.documents} document(s)).`, confirmLabel: 'Approve' }))) return
  } else {
    reason = await promptReason({
      title: `${action === 'reject' ? 'Reject' : 'Withdraw'} ${name}`,
      message: o.status === 'APPROVED' ? 'The next rebuild of the month stops applying it. Give the reason.' : 'Give the reason; it is kept in the audit trail.',
      confirmLabel: action === 'reject' ? 'Reject' : 'Withdraw',
    })
    if (!reason) return
  }
  router.post(route('client-books.overrides.decide', [o.type, o.id]), { action, reason }, { preserveScroll: true })
}
async function proposeRebuild(period) {
  if (!(await confirmDialog({ title: `Propose the rebuild of ${period}?`, message: `The loan book of ${period} is built again from the landed E-Banker data with every approved override of the month. A different person approves the build on Loan Book Builds; then run the month-end and compare the client's book again.`, confirmLabel: 'Propose the rebuild' }))) return
  router.post(route('eir-feed.build'), { from: period, to: period }, { preserveScroll: true })
}

const overlay = useForm({ type: 'ageing', file: null })
function openOverlay() { overlay.reset(); overlay.clearErrors(); modal.value = 'overlay' }
function submitOverlay() { overlay.post(route('client-books.overlays.import'), { forceFormData: true, preserveScroll: true, onSuccess: () => { if (!Object.keys(overlay.errors).length) modal.value = null } }) }

const cards = computed(() => {
  const open = props.books.reduce((n, b) => n + (b.status === 'COMPARED' ? Number(b.open || 0) : 0), 0)
  return [
    { label: 'Client books landed', value: props.books.filter((b) => b.status !== 'WITHDRAWN').length },
    { label: 'Differences to decide', value: open, accent: open ? '#d97706' : null },
    { label: 'Overrides awaiting approval', value: props.overrides.filter((o) => o.status === 'PROPOSED').length, accent: props.overrides.some((o) => o.status === 'PROPOSED') ? '#d97706' : null },
    { label: 'Months to rebuild', value: props.rebuilds.length, accent: props.rebuilds.length ? '#d97706' : null },
  ]
})

const money = (v) => (v === null || v === undefined || v === '' ? '-' : Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }))
const bookLabel = (s) => ({ LANDED: 'Landed', COMPARED: 'Compared', WITHDRAWN: 'Withdrawn' }[s] || s)
const diffBadge = (s) => ({ OPEN: 'maiic-badge-red', OVERRIDE_PROPOSED: 'maiic-badge-gold', KEPT: 'maiic-badge-grey', RECONCILED: 'maiic-badge-green', INFO: 'maiic-badge-grey' }[s] || 'maiic-badge-grey')
const ovLabel = (s) => ({ PROPOSED: 'Proposed', APPROVED: 'Approved', REJECTED: 'Rejected', WITHDRAWN: 'Withdrawn' }[s] || s)
const ovBadge = (s) => ({ PROPOSED: 'maiic-badge-gold', APPROVED: 'maiic-badge-green', REJECTED: 'maiic-badge-red', WITHDRAWN: 'maiic-badge-grey' }[s] || 'maiic-badge-grey')
const sourceLabel = (s) => ({ directive_count: "the directive's count", audited_workbook: 'an audited figure', client_book: "the client's book" }[s] || s)
</script>
