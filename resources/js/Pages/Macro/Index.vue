<template>
  <app-layout title="Macro Statistics" description="The economic variables the forward-looking model reads, their values with where each came from, and the scenario assumptions built on them">
    <template #actions>
      <button v-if="canManage && tab === 'Variables'" type="button" class="primary-btn text-sm" @click="openVariable()"><font-awesome-icon icon="plus" class="mr-1" />Add variable</button>
      <button v-if="canManage && tab === 'Data Entry'" type="button" class="primary-btn text-sm" @click="openValue()"><font-awesome-icon icon="plus" class="mr-1" />Add value</button>
      <button v-if="canManage" type="button" class="secondary-btn text-sm" @click="showImport = true"><font-awesome-icon icon="upload" class="mr-1" />Import</button>
      <a :href="route('macro-statistics.export')" class="secondary-btn text-sm"><font-awesome-icon icon="download" class="mr-1" />Export CSV</a>
    </template>

    <div class="space-y-4">
      <!-- macro overrides waiting for a second person (Staging Module Specification v1, section 8.6) -->
      <div v-if="overrides && overrides.length" class="maiic-panel border-amber-200">
        <div class="border-b border-gray-200 px-5 py-3"><h3 class="font-semibold text-gray-900">Overrides awaiting a second person</h3><p class="text-xs text-gray-500">A manual value, a correction or a deletion is applied only when a second person approves it, with the source of the value attached on its record.</p></div>
        <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Override</th><th>What</th><th>Reason</th><th>Proposed by</th><th>Source attached</th><th class="num">Actions</th></tr></thead>
          <tbody>
            <tr v-for="o in overrides" :key="o.id">
              <td>{{ o.id }}</td>
              <td class="text-xs">{{ o.kind }}<span v-if="o.series_code"> {{ o.series_code }}</span><span v-if="o.observation_id"> observation {{ o.observation_id }}</span>: {{ o.payload }}</td>
              <td class="max-w-xs text-xs">{{ o.reason }}</td>
              <td class="text-xs">{{ o.proposer || '-' }}</td>
              <td>{{ o.documents ? 'Yes' : 'Not yet' }}</td>
              <td class="num whitespace-nowrap"><a :href="route('approvals.record', ['macro-override', o.id])" class="secondary-btn text-xs mr-1">Record, documents</a><button v-if="canManage" type="button" class="primary-btn text-xs" @click="approveOverride(o)">Approve</button></td>
            </tr>
          </tbody></table></div>
      </div>
      <!-- Dashboard -->
      <template v-if="tab === 'Dashboard'">
        <p class="text-xs text-gray-500">Each card is one variable. Click a card to see and edit its values. To add a variable, open Variables; to load many values at once, open Import / Export.</p>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-3 2xl:grid-cols-4">
          <div v-if="!series.length" class="maiic-panel maiic-empty md:col-span-3">No variable is set up yet. Open Variables and use Add variable.</div>
          <div v-for="s in series" :key="s.id" class="maiic-kpi !py-3 cursor-pointer hover:shadow-md" style="--accent:#15803d" :title="'Open ' + s.code + ' in Data Entry'" @click="select(s)">
            <div class="maiic-kpi-label">{{ s.code }}{{ s.unit ? ', ' + s.unit : '' }}</div>
            <div class="text-sm font-bold">{{ s.name }}</div>
            <div class="text-xs text-gray-500">{{ s.n }} values{{ s.n ? ', ' + (s.first || '').slice(0, 7) + ' to ' + (s.last || '').slice(0, 7) : '' }}<span v-if="s.forecasts">, {{ s.forecasts }} forecast</span></div>
            <div class="text-xs text-gray-500" v-if="s.last_batch">last from {{ s.last_batch.source }} {{ (s.last_batch.fetched_at || '').slice(0, 10) }}</div>
            <div class="text-xs text-amber-700" v-else>no values yet</div>
          </div>
        </div>
      </template>

      <!-- Variables -->
      <div v-if="tab === 'Variables'" class="maiic-panel">
        <div class="border-b border-gray-200 px-5 py-3 dark:border-slate-700"><h3 class="font-semibold text-gray-900">Variables</h3><p class="text-xs text-gray-500">{{ series.length }} variables. A variable with values, or one a model or a scenario uses, cannot be deleted; the message says what holds it.</p></div>
        <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Code</th><th>Variable</th><th>Unit</th><th>Frequency</th><th>Source</th><th>Source codes</th><th class="num">Values</th><th>Why MAIIC needs it</th><th v-if="canManage"></th></tr></thead>
          <tbody>
            <tr v-for="s in pagedSeries" :key="s.id">
              <td class="font-mono text-xs">{{ s.code }}</td><td class="font-semibold">{{ s.name }}</td><td class="text-xs">{{ s.unit }}</td><td class="text-xs">{{ s.frequency }}</td><td class="text-xs">{{ s.source }}</td>
              <td class="whitespace-nowrap text-xs leading-6">
                <div v-if="s.codes.world_bank"><span class="inline-block w-16 font-semibold text-gray-500">WB:</span><span class="font-mono text-gray-800">{{ s.codes.world_bank }}</span></div>
                <div v-if="s.codes.imf_weo"><span class="inline-block w-16 font-semibold text-gray-500">IMF:</span><span class="font-mono text-gray-800">{{ s.codes.imf_weo }}</span></div>
                <div v-if="s.codes.rbm || s.codes.landing_zone"><span class="inline-block w-16 font-semibold text-gray-500">{{ s.codes.rbm ? 'RBM:' : 'E-Banker:' }}</span><span class="font-mono text-gray-800">{{ s.codes.rbm || s.codes.landing_zone }}</span></div>
                <span v-if="!s.codes.world_bank && !s.codes.imf_weo && !s.codes.rbm && !s.codes.landing_zone" class="text-gray-400">-</span>
              </td>
              <td class="num">{{ s.n }}</td><td class="max-w-[18rem] text-xs">{{ s.why }}</td>
              <td v-if="canManage" class="num whitespace-nowrap">
                <button type="button" class="maiic-action maiic-action-view" title="See its values" @click="select(s)"><font-awesome-icon icon="eye" /></button>
                <button type="button" class="maiic-action maiic-action-edit ml-1" title="Edit the variable" @click="openVariable(s)"><font-awesome-icon icon="pen" /></button>
                <button type="button" class="maiic-action maiic-action-delete ml-1" title="Delete the variable" @click="deleteVariable(s)"><font-awesome-icon icon="trash" /></button>
              </td>
            </tr>
            <tr v-if="!series.length"><td :colspan="canManage ? 9 : 8" class="maiic-empty">No variable yet. Use Add variable at the top right.</td></tr>
          </tbody></table></div>
        <LocalPager v-model="varPage" :total="series.length" class="border-t border-gray-100"/>
      </div>

      <!-- Data entry -->
      <div v-if="tab === 'Data Entry'" class="space-y-4">
        <div class="maiic-filterbar flex flex-wrap items-end gap-3">
          <label class="text-xs"><span class="maiic-flabel">Variable</span><select v-model="sel" @change="go" class="maiic-select !w-80"><option v-for="s in series" :key="s.id" :value="s.id">{{ s.code }}, {{ s.name }}</option></select></label>
          <span class="text-xs text-gray-500">{{ current ? current.frequency + ' values' + (current.unit ? ' in ' + current.unit : '') : '' }}. A value typed here, or corrected, needs a reason; the old value is kept in the audit log.</span>
        </div>
        <div class="maiic-panel"><div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Period</th><th class="num">Value</th><th>Actual or forecast</th><th>Published</th><th>Source</th><th>Batch</th><th v-if="canManage"></th></tr></thead>
          <tbody><tr v-for="o in pagedObs" :key="o.id"><td>{{ String(o.period).slice(0, 10) }}</td><td class="num">{{ Number(o.value).toLocaleString('en-GB', { maximumFractionDigits: 4 }) }}</td><td><span class="maiic-badge" :class="o.is_forecast ? 'maiic-badge-gold' : 'maiic-badge-green'">{{ o.is_forecast ? 'Forecast' : 'Actual' }}</span></td>
            <td class="text-xs">{{ o.published_at ? String(o.published_at).slice(0, 10) : '-' }}</td><td class="max-w-[16rem] truncate text-xs" :title="o.source + (o.notes ? ' | ' + o.notes : '')">{{ o.source }}</td><td class="text-xs">{{ o.batch_source }} {{ (o.fetched_at || '').slice(0, 10) }}</td>
            <td v-if="canManage" class="num whitespace-nowrap"><button type="button" class="maiic-action maiic-action-edit" title="Correct this value" @click="openValue(o)"><font-awesome-icon icon="pen" /></button><button type="button" class="maiic-action maiic-action-delete ml-1" title="Delete this value" @click="deleteValue(o)"><font-awesome-icon icon="trash" /></button></td></tr>
          <tr v-if="!observations.length"><td :colspan="canManage ? 7 : 6" class="maiic-empty">No values for this variable yet. Use Add value, or import a file on Import / Export.</td></tr></tbody></table></div>
          <LocalPager v-model="obsPage" :total="observations.length" class="border-t border-gray-100"/></div>
      </div>

      <!-- Scenario assumptions: one set at a time, a summary of every scenario side by side, then one scenario's shocks -->
      <div v-if="tab === 'Scenario Assumptions'" class="space-y-4">
        <div class="maiic-panel flex flex-wrap items-end gap-3 px-5 py-3">
          <label class="text-xs"><span class="maiic-flabel">Month</span><select v-model="aPeriod" @change="goAssumptions" class="maiic-select !w-36"><option v-for="p in assumptions.periods" :key="p" :value="p">{{ p }}</option></select></label>
          <label v-if="assumptions.sets.length" class="text-xs"><span class="maiic-flabel">Scenario set</span>
            <select v-model="aSetId" class="maiic-select !w-[34rem] max-w-full"><option v-for="s in assumptions.sets" :key="s.id" :value="s.id">{{ s.name }}, version {{ s.version }} ({{ statusLabel(s.status) }})</option></select></label>
          <span v-if="aSet" class="maiic-badge" :class="aSet.editable ? 'maiic-badge-gold' : 'maiic-badge-green'">{{ statusLabel(aSet.status) }}</span>
          <div class="ml-auto flex gap-2">
            <button v-if="aSet && aSet.status === 'DRAFT' && canManage" type="button" class="secondary-btn !px-3 !py-1.5 text-xs" @click="discard(aSet)"><font-awesome-icon icon="trash" class="mr-1" />Discard this draft</button>
            <Link :href="route('scenario-sets.index', { period: assumptions.period })" class="secondary-btn !px-3 !py-1.5 text-xs">Propose or approve</Link>
          </div>
        </div>

        <div v-if="!assumptions.sets.length" class="maiic-panel maiic-empty">No scenario set for this month. Start one from a profile on <Link :href="route('scenarios.profiles')" class="font-semibold text-maiic-700 underline">Scenario Profiles</Link>, then set its assumptions here.</div>

        <template v-if="aSet">
          <!-- the scenario weights: the probability of each scenario, totalling 100 -->
          <div class="maiic-panel flex flex-wrap items-end gap-4 px-5 py-3">
            <div class="w-full text-sm font-semibold text-gray-900 sm:w-auto sm:self-center">Scenario weights</div>
            <label v-for="sc in aSet.scenarios" :key="sc.id" class="text-xs"><span class="maiic-flabel">{{ sc.name }}{{ sc.is_base && sc.name.toLowerCase() !== 'base' ? ' (base)' : '' }}</span>
              <span class="flex items-center gap-1"><input v-if="aSet.editable" v-model.number="edit(aSet, sc).weight" type="number" min="0" max="100" step="0.5" class="maiic-input !w-24 text-right" /><span v-else class="inline-block w-16 rounded-lg bg-gray-50 px-3 py-2 text-right text-sm font-semibold dark:bg-slate-900">{{ sc.weight }}</span> %</span></label>
            <div class="text-xs"><span class="maiic-flabel">Total</span><span class="inline-block rounded-lg px-3 py-2 text-sm font-bold" :class="Math.abs(weightTotal - 100) < 0.01 ? 'bg-maiic-50 text-maiic-800' : 'bg-red-50 text-red-700'">{{ weightTotal }} %</span></div>
            <div class="ml-auto flex gap-2">
              <button v-if="aSet.editable && canManage" type="button" class="primary-btn text-sm" @click="saveWeights(aSet)">Save weights</button>
              <button v-if="!aSet.editable && canManage" type="button" class="primary-btn text-sm" @click="newVersion(aSet)" title="An approved set is locked; a new version is a draft copy you can change">New version to change it</button>
            </div>
          </div>

          <!-- every scenario side by side -->
          <div class="maiic-panel">
            <div class="border-b border-gray-200 px-5 py-3 dark:border-slate-700"><h3 class="font-semibold text-gray-900">{{ assumptions.year }} at a glance</h3><p class="text-xs text-gray-500">Each variable a scenario moves, its base value and its value in each scenario. {{ aSet.editable ? 'Open a scenario below to change it.' : 'This set is approved: a change is a new version, made on Scenario Sets.' }}</p></div>
            <div class="maiic-table-wrap"><table class="maiic-table">
              <thead><tr><th>Variable</th><th class="num">Base</th><th v-for="sc in aSet.scenarios.filter(x => !x.is_base)" :key="sc.id" class="num">{{ sc.name }} <span class="font-normal opacity-75">{{ sc.weight }}%</span></th></tr></thead>
              <tbody>
                <tr v-for="code in pagedGlance" :key="code">
                  <td class="font-semibold">{{ varName(code) }} <span class="font-mono text-[11px] font-normal text-gray-400">{{ code }}</span></td>
                  <td class="num text-gray-600">{{ fmt((aSet.base[code] || [])[0]) }}</td>
                  <td v-for="sc in aSet.scenarios.filter(x => !x.is_base)" :key="sc.id" class="num" :class="moved(sc, code) ? 'font-semibold text-gray-900' : 'text-gray-400'">{{ fmt((sc.path[code] || [])[0]) }}</td>
                </tr>
                <tr v-if="!glanceCodes.length"><td :colspan="2 + aSet.scenarios.length" class="maiic-empty">No scenario moves any variable yet: every scenario follows the base.</td></tr>
              </tbody>
            </table></div>
            <LocalPager v-model="glancePage" :total="glanceCodes.length" class="border-t border-gray-100"/>
          </div>

          <!-- one scenario at a time -->
          <div class="maiic-panel">
            <div class="flex flex-wrap gap-2 border-b border-gray-200 px-5 pt-3 dark:border-slate-700">
              <button v-for="sc in aSet.scenarios" :key="sc.id" type="button" @click="aScenarioId = sc.id"
                      class="-mb-px border-b-2 px-3 pb-2 text-sm font-semibold" :class="aScenario && aScenario.id === sc.id ? 'border-maiic-600 text-maiic-800' : 'border-transparent text-gray-500 hover:text-gray-800'">
                {{ sc.name }} <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-slate-700 dark:text-slate-200">{{ sc.weight }}%</span>
              </button>
            </div>
            <div v-if="aScenario" class="space-y-3 p-5">
              <div class="flex flex-wrap items-center gap-4 text-sm">
                <span class="font-semibold">Weight {{ aSet.editable ? edit(aSet, aScenario).weight : aScenario.weight }}%</span>
                <span class="text-gray-600">{{ aScenario.anchored_to }}</span>
                <div v-if="aSet.editable" class="ml-auto flex gap-2">
                  <button type="button" class="secondary-btn text-sm" @click="addShock(aSet, aScenario)"><font-awesome-icon icon="plus" class="mr-1" />Add a shock</button>
                  <button type="button" class="primary-btn text-sm" @click="saveScenario(aSet, aScenario)">Save {{ aScenario.name }}</button>
                </div>
              </div>
              <p v-if="aScenario.is_base && !edit(aSet, aScenario).shocks.length" class="text-sm text-gray-500">The base follows the values of each variable as known at the month-end; it needs no shock.</p>
              <div v-else class="maiic-table-wrap"><table class="maiic-table">
                <thead><tr><th>Variable</th><th>Year</th><th>How it moves</th><th class="num">By</th><th class="num">Base</th><th class="num">Becomes</th><th>Why</th><th v-if="aSet.editable"></th></tr></thead>
                <tbody>
                  <tr v-for="(sh, i) in pagedShocks(aSet, aScenario)" :key="shockOffset + i">
                    <td><select v-if="aSet.editable" v-model="sh.statistic_code" class="maiic-select !w-72 !py-1 text-sm"><option v-for="v in assumptions.variables" :key="v.statistic_code" :value="v.statistic_code">{{ v.statistic_name }}</option></select><span v-else>{{ varName(sh.statistic_code) }}</span></td>
                    <td><select v-if="aSet.editable" v-model.number="sh.year_offset" class="maiic-select !w-24 !py-1 text-sm"><option v-for="o in [0, 1, 2]" :key="o" :value="o">{{ assumptions.year + o }}</option></select><span v-else>{{ assumptions.year + sh.year_offset }}</span></td>
                    <td><select v-if="aSet.editable" v-model="sh.kind" class="maiic-select !w-40 !py-1 text-sm"><option value="abs">add points</option><option value="pct">percent change</option><option value="replace">replace with</option></select><span v-else>{{ kindLabel(sh.kind) }}</span></td>
                    <td class="num"><input v-if="aSet.editable" v-model.number="sh.value" type="number" step="any" class="maiic-input !w-24 !py-1 text-right text-sm" /><span v-else>{{ sh.value }}</span></td>
                    <td class="num text-gray-500" :title="(aSet.base_source[sh.statistic_code] || [])[sh.year_offset]">{{ fmt((aSet.base[sh.statistic_code] || [])[sh.year_offset]) }}</td>
                    <td class="num font-semibold">{{ fmt(shocked(aSet, sh)) }}</td>
                    <td><input v-if="aSet.editable" v-model="sh.note" class="maiic-input !py-1 text-sm" placeholder="optional" /><span v-else class="text-gray-600">{{ sh.note }}</span></td>
                    <td v-if="aSet.editable" class="num"><button type="button" class="maiic-action maiic-action-delete" title="Remove this shock" @click="removeShock(aSet, aScenario, shockOffset + i)"><font-awesome-icon icon="trash" /></button></td>
                  </tr>
                  <tr v-if="!edit(aSet, aScenario).shocks.length"><td :colspan="aSet.editable ? 8 : 7" class="maiic-empty">No shock: this scenario follows the base path.</td></tr>
                </tbody>
              </table>
              <LocalPager v-model="shockPage" :total="edit(aSet, aScenario).shocks.length" class="border-t border-gray-100"/></div>
            </div>
          </div>
        </template>
      </div>

      <!-- Import / export -->
      <div v-if="tab === 'Import / Export'" class="space-y-4">
        <p class="text-xs text-gray-500">Use Import at the top right to load values from your own file, the World Bank, the IMF or the Reserve Bank. Every load is a batch below.</p>
        <div class="maiic-panel">
          <div class="border-b border-gray-200 px-4 py-3"><h3 class="maiic-section-title !my-0">Import history: committed batches</h3></div>
          <div class="maiic-table-wrap"><table class="maiic-table"><thead><tr><th>Batch</th><th>Source</th><th>Address</th><th>Fetched</th><th>Committed by</th><th class="num">Rows</th><th>Variables</th></tr></thead>
            <tbody>
              <tr v-for="b in pagedBatches" :key="b.id" class="text-xs"><td>{{ b.id }}</td><td>{{ b.source }}</td><td class="break-all">{{ b.address }}</td><td>{{ b.fetched_at }}</td><td>{{ b.committed_by_name || 'system' }}</td><td class="num">{{ b.rows }}</td><td>{{ batchSeries(b) }}</td></tr>
              <tr v-if="!(batches || []).length"><td colspan="7" class="maiic-empty">No batch has been committed yet.</td></tr>
            </tbody></table></div>
          <LocalPager v-model="batchPage" :total="(batches || []).length" class="border-t border-gray-100"/>
        </div>
      </div>
    </div>

    <!-- import: the sources, the how-to and the preview, in one window -->
    <div v-if="showImport" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto py-8">
      <div class="fixed inset-0 bg-gray-500/75" @click="showImport = false"></div>
      <div class="relative mx-4 w-full max-w-6xl rounded-xl bg-white shadow-xl dark:bg-slate-800">
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-slate-700">
          <h3 class="text-lg font-semibold">Import macro data</h3>
          <button type="button" class="maiic-action maiic-action-neutral" title="Close" @click="showImport = false"><font-awesome-icon icon="times" /></button>
        </div>
        <div class="grid gap-5 p-6 text-sm lg:grid-cols-3">
          <div class="space-y-4 lg:col-span-2">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 2xl:grid-cols-4">
              <div class="rounded-lg border border-maiic-300 bg-maiic-50/40 p-3 dark:bg-maiic-900/10">
                <div class="font-semibold text-gray-900">Your own file (any variable)</div>
                <p class="text-xs text-gray-500">The export's layout: series_code, period, value, is_forecast, source, published_at.</p>
                <FileDrop class="mt-2" label="" accept=".csv,.txt" placeholder="Choose the CSV file" @file="f => own.file = f"/>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                  <button type="button" class="secondary-btn text-xs" :disabled="!own.file" @click="previewOwn">Preview</button>
                  <a :href="route('import-samples.show', 'macro-series')" class="text-xs font-semibold text-maiic-700 underline">Sample CSV</a>
                </div>
              </div>
              <div class="rounded-lg border border-gray-200 p-3">
                <div class="font-semibold text-gray-900">World Bank (live)</div>
                <p class="text-xs text-gray-500">Fetched from the World Bank, no file needed.</p>
                <label class="mt-2 block"><span class="maiic-flabel">Variable</span>
                  <select v-model="wb.series_code" class="maiic-select"><option value="">Choose</option><option v-for="s in series.filter(x => x.codes.world_bank)" :key="s.id" :value="s.code">{{ s.code }}</option></select></label>
                <div class="mt-2 grid grid-cols-3 gap-2">
                  <label><span class="maiic-flabel">Country</span><input v-model="wb.country" class="maiic-input" placeholder="MWI"/></label>
                  <label><span class="maiic-flabel">From</span><input v-model="wb.from" type="number" class="maiic-input"/></label>
                  <label><span class="maiic-flabel">To</span><input v-model="wb.to" type="number" class="maiic-input"/></label>
                </div>
                <button type="button" class="secondary-btn mt-3 text-xs" :disabled="!wb.series_code" @click="preview('world_bank', wb)">Preview</button>
              </div>
              <div class="rounded-lg border border-gray-200 p-3">
                <div class="font-semibold text-gray-900">IMF World Economic Outlook</div>
                <p class="text-xs text-gray-500">The WEO download, tab-delimited.</p>
                <label class="mt-2 block"><span class="maiic-flabel">Variable</span>
                  <select v-model="imf.series_code" class="maiic-select"><option value="">Choose</option><option v-for="s in series.filter(x => x.codes.imf_weo)" :key="s.id" :value="s.code">{{ s.code }}</option></select></label>
                <FileDrop class="mt-2" label="" accept=".xls,.txt,.tsv,.csv" placeholder="Choose the WEO file" @file="f => imf.file = f"/>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                  <button type="button" class="secondary-btn text-xs" :disabled="!imf.series_code || !imf.file" @click="preview('imf_weo', imf)">Preview</button>
                  <a :href="route('import-samples.show', 'imf-weo')" class="text-xs font-semibold text-maiic-700 underline">Sample file</a>
                </div>
              </div>
              <div class="rounded-lg border border-gray-200 p-3">
                <div class="font-semibold text-gray-900">Reserve Bank policy rate</div>
                <p class="text-xs text-gray-500">A CSV of two columns, date and rate.</p>
                <FileDrop class="mt-2" label="" accept=".csv,.txt" placeholder="Choose the CSV file" @file="f => rbm.file = f"/>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                  <button type="button" class="secondary-btn text-xs" :disabled="!rbm.file" @click="preview('rbm_file', rbm)">Preview</button>
                  <a :href="route('import-samples.show', 'rbm-policy-rate')" class="text-xs font-semibold text-maiic-700 underline">Sample CSV</a>
                </div>
              </div>
            </div>

            <!-- the layout of each file source: columns, types, required, sample and check result -->
            <div>
              <div class="mb-2 flex flex-wrap items-center gap-1" role="tablist" aria-label="File layout">
                <button v-for="o in specSources" :key="o.key" type="button" role="tab" :aria-selected="specSource === o.key ? 'true' : 'false'"
                        class="rounded-md px-3 py-1 text-xs font-semibold transition"
                        :class="specSource === o.key ? 'bg-maiic-600 text-white dark:bg-maiic-500' : 'text-gray-600 hover:bg-gray-100 dark:text-slate-300 dark:hover:bg-slate-700'"
                        @click="specSource = o.key">{{ o.label }}</button>
              </div>
              <ImportSpecPanel :spec-key="specSource" :check="specCheck && specCheck.key === specSource ? specCheck : null"/>
            </div>

            <div v-if="previewError" class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-3 text-red-800">
              <font-awesome-icon icon="times" class="mt-0.5 text-red-500" />
              <div><div class="font-semibold">The preview could not be made</div><p class="mt-0.5 text-xs">{{ previewError }}</p></div>
            </div>
            <div v-if="ownPreview">
              <div class="text-xs text-gray-600">{{ ownPreview.rows }} rows over {{ ownPreview.previews.length }} variable(s)<span v-if="ownPreview.error_count" class="text-red-700">; {{ ownPreview.error_count }} line(s) skipped</span>.</div>
              <ul v-if="ownPreview.errors.length" class="mt-1 list-disc pl-5 text-xs text-red-700"><li v-for="(e, i) in ownPreview.errors.slice(0, 8)" :key="i">{{ e }}</li></ul>
              <div class="maiic-table-wrap mt-1 max-h-64 overflow-y-auto rounded-lg border border-gray-200"><table class="maiic-table"><thead><tr><th>Variable</th><th>Period</th><th class="num">Value</th><th>Type</th><th>Published</th></tr></thead>
                <tbody><template v-for="p in ownPreview.previews" :key="p.series_code"><tr v-for="r in p.rows" :key="p.series_code + r.period"><td>{{ p.series_code }}</td><td>{{ r.period }}</td><td class="num">{{ r.value }}</td><td>{{ r.value_type }}</td><td>{{ r.published_at || '-' }}</td></tr></template></tbody></table></div>
              <button v-if="canManage && ownPreview.rows" type="button" class="primary-btn mt-2 text-sm" @click="commitOwn">Import {{ ownPreview.rows }} rows</button>
            </div>
            <div v-if="previewData">
              <div class="text-xs text-gray-500">{{ previewData.series_code }}, {{ previewData.source }}, {{ previewData.address }}, {{ previewData.rows.length }} rows<span v-if="previewData.estimates_start_after">, estimates start after {{ previewData.estimates_start_after }}</span><span v-if="previewData.note" class="text-amber-700">. {{ previewData.note }}</span></div>
              <div class="maiic-table-wrap mt-1 max-h-64 overflow-y-auto rounded-lg border border-gray-200"><table class="maiic-table"><thead><tr><th>Period</th><th class="num">Value</th><th>Type</th></tr></thead><tbody><tr v-for="r in previewData.rows" :key="r.period"><td>{{ r.period }}</td><td class="num">{{ r.value }}</td><td>{{ r.value_type }}</td></tr></tbody></table></div>
              <button v-if="canManage && previewData.rows.length" type="button" class="primary-btn mt-2 text-sm" @click="commit">Commit {{ previewData.rows.length }} rows as a batch</button>
            </div>
          </div>

          <ImportHowTo title="How to load macro data">
            <li>To add a new variable (for example a maize price), open Variables and use Add variable. Then load its values.</li>
            <li>Your own file loads any variable: one row per value with series_code, period (yyyy-mm, yyyy-mm-dd or yyyy), value, is_forecast (1 or 0), source and published_at (the date the publisher released it). Export CSV gives the same layout, so you can export, edit and import back.</li>
            <li>World Bank is fetched live; the IMF and Reserve Bank sources are their own files.</li>
            <li>Press Preview. Nothing is saved until you have seen the rows and pressed Import or Commit.</li>
            <li>One value is added or corrected on Data Entry, with a reason. Every load is a batch in the history below.</li>
          </ImportHowTo>
        </div>

      </div>
    </div>

    <!-- add or edit a variable -->
    <div v-if="varForm" class="fixed inset-0 z-50 flex items-center justify-center">
      <div class="absolute inset-0 bg-gray-500/75" @click="varForm = null"></div>
      <form class="relative mx-4 w-full max-w-2xl space-y-3 rounded-xl bg-white p-6 shadow-xl dark:bg-slate-800" @submit.prevent="saveVariable">
        <h3 class="text-lg font-semibold">{{ varForm.id ? 'Edit ' + varForm.statistic_code : 'Add a variable' }}</h3>
        <div class="grid gap-3 md:grid-cols-2">
          <label class="text-xs font-semibold">Code<input v-model="varForm.statistic_code" class="maiic-input mt-1 w-full uppercase" placeholder="MAIZE_PRICE" required /><span class="font-normal text-gray-500">Capitals, digits and underscores.</span><div v-if="errors.statistic_code" class="text-red-700">{{ errors.statistic_code }}</div></label>
          <label class="text-xs font-semibold">Name<input v-model="varForm.statistic_name" class="maiic-input mt-1 w-full" placeholder="Maize price, MWK per kg" required /></label>
          <label class="text-xs font-semibold">Unit<input v-model="varForm.unit" class="maiic-input mt-1 w-full" placeholder="percent, MWK per USD" /></label>
          <label class="text-xs font-semibold">Frequency<select v-model="varForm.frequency" class="maiic-select mt-1 w-full"><option value="monthly">Monthly</option><option value="quarterly">Quarterly</option><option value="yearly">Yearly</option></select></label>
          <label class="text-xs font-semibold">Source<input v-model="varForm.data_source" class="maiic-input mt-1 w-full" placeholder="Reserve Bank of Malawi, NSO, IMF" /></label>
          <label class="text-xs font-semibold">Link<input v-model="varForm.website_link" class="maiic-input mt-1 w-full" placeholder="https://" /></label>
          <label class="text-xs font-semibold">World Bank code<input v-model="varForm.world_bank" class="maiic-input mt-1 w-full" placeholder="FP.CPI.TOTL.ZG" /></label>
          <label class="text-xs font-semibold">IMF WEO code<input v-model="varForm.imf_weo" class="maiic-input mt-1 w-full" placeholder="PCPIPCH" /></label>
          <label class="text-xs font-semibold">RBM or other code<input v-model="varForm.other_code" class="maiic-input mt-1 w-full" placeholder="policy_rate" /></label>
          <label class="text-xs font-semibold md:col-span-2">Why MAIIC needs it<input v-model="varForm.statistic_description" class="maiic-input mt-1 w-full" maxlength="199" /></label>
        </div>
        <div class="flex justify-end gap-2"><button type="button" class="secondary-btn text-sm" @click="varForm = null">Cancel</button><button type="submit" class="primary-btn text-sm">{{ varForm.id ? 'Save' : 'Add the variable' }}</button></div>
      </form>
    </div>

    <!-- add or correct a value -->
    <div v-if="valForm" class="fixed inset-0 z-50 flex items-center justify-center">
      <div class="absolute inset-0 bg-gray-500/75" @click="valForm = null"></div>
      <form class="relative mx-4 w-full max-w-lg space-y-3 rounded-xl bg-white p-6 shadow-xl dark:bg-slate-800" @submit.prevent="saveValue">
        <h3 class="text-lg font-semibold">{{ valForm.id ? 'Correct the value of ' + String(valForm.period).slice(0, 10) : 'Add a value to ' + (current ? current.code : '') }}</h3>
        <div class="grid gap-3 md:grid-cols-2">
          <label class="text-xs font-semibold">Period<input v-model="valForm.period" type="date" class="maiic-input mt-1 w-full" :disabled="!!valForm.id" required /><span class="font-normal text-gray-500">For a yearly value, the year's last day.</span></label>
          <label class="text-xs font-semibold">Value<input v-model="valForm.value" type="number" step="any" class="maiic-input mt-1 w-full" required /></label>
          <label class="flex items-center gap-2 text-xs font-semibold"><input v-model="valForm.is_forecast" type="checkbox" /> This is a forecast</label>
          <label class="text-xs font-semibold">Published on<input v-model="valForm.published_at" type="date" class="maiic-input mt-1 w-full" /></label>
          <label class="text-xs font-semibold md:col-span-2">Reason<input v-model="valForm.reason" class="maiic-input mt-1 w-full" required placeholder="for example: RBM Monthly Economic Report, July 2026" /></label>
        </div>
        <div class="flex justify-end gap-2"><button type="button" class="secondary-btn text-sm" @click="valForm = null">Cancel</button><button type="submit" class="primary-btn text-sm">Save</button></div>
      </form>
    </div>
  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Link, router } from '@inertiajs/vue3'
import FileDrop from '@/Components/Data/FileDrop.vue'
import ImportHowTo from '@/Components/Data/ImportHowTo.vue'
import ImportSpecPanel from '@/Components/Imports/ImportSpecPanel.vue'
import LocalPager from '@/Components/Data/LocalPager.vue'
import { confirmDialog } from '@/Components/confirmDialog'
import { promptReason } from '@/Components/Maiic/promptReason'

// ?tab= of the section row -> the view shown (first view when none is given).
const VIEWS = { dashboard: 'Dashboard', variables: 'Variables', entry: 'Data Entry', scenarios: 'Scenario Assumptions', import: 'Import / Export' }
const KEYS = Object.fromEntries(Object.entries(VIEWS).map(([k, v]) => [v, k]))
const viewFromUrl = () => VIEWS[new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '').get('tab')] || 'Dashboard'

export default {
  components: { AppLayout, Link, FileDrop, ImportHowTo, ImportSpecPanel, LocalPager },
  props: { series: Array, selected: Number, observations: Array, batches: Array, sets: Array, assumptions: Object, canManage: Boolean, defaultCountry: String, errors: Object, overrides: { type: Array, default: () => [] }, userId: { type: Number, default: null } },
  data() {
    return { tab: viewFromUrl(), sel: this.selected, aPeriod: this.assumptions?.period, aSetId: this.defaultSetId(this.assumptions), aScenarioId: null,
      wb: { series_code: '', country: this.defaultCountry, from: 2000, to: new Date().getFullYear() }, imf: { series_code: '', file: null }, rbm: { file: null }, own: { file: null },
      showImport: false, specSource: 'macro-series', specCheck: null, specSources: [{ key: 'macro-series', label: 'Your own file' }, { key: 'imf-weo', label: 'IMF WEO file' }, { key: 'rbm-policy-rate', label: 'Reserve Bank file' }], batchPage: 1, varPage: 1, obsPage: 1, glancePage: 1, shockPage: 1, previewData: null, previewError: null, previewSource: null, ownPreview: null, varForm: null, valForm: null, edits: {} }
  },
  computed: {
    current() { return this.series.find(s => s.id === this.sel) },
    aSet() { return (this.assumptions?.sets || []).find(s => s.id === this.aSetId) || null },
    aScenario() { const sc = this.aSet ? this.aSet.scenarios : []; return sc.find(x => x.id === this.aScenarioId) || sc.find(x => !x.is_base) || sc[0] || null },
    weightTotal() { if (!this.aSet) return 0; return Math.round(this.aSet.scenarios.reduce((t, sc) => t + Number(this.aSet.editable ? this.edit(this.aSet, sc).weight : sc.weight), 0) * 100) / 100 },
    glanceCodes() {
      if (!this.aSet) return []
      const codes = new Set()
      this.aSet.scenarios.forEach(sc => (sc.shocks || []).forEach(sh => { if (Number(sh.year_offset) === 0) codes.add(sh.statistic_code) }))
      return [...codes].sort((a, b) => this.varName(a).localeCompare(this.varName(b)))
    },
    // the glance table and one scenario's shocks, 15 rows a page
    pagedGlance() { return this.glanceCodes.slice((this.glancePage - 1) * 15, this.glancePage * 15) },
    shockOffset() { return (this.shockPage - 1) * 15 },
    pagedBatches() { return (this.batches || []).slice((this.batchPage - 1) * 15, this.batchPage * 15) },
    pagedSeries() { return this.series.slice((this.varPage - 1) * 15, this.varPage * 15) },
    pagedObs() { const o = [...(this.observations || [])].reverse(); return o.slice((this.obsPage - 1) * 15, this.obsPage * 15) },
  },
  watch: { assumptions(v) { this.edits = {}; if (!(v.sets || []).some(s => s.id === this.aSetId)) this.aSetId = this.defaultSetId(v) }, aSetId() { this.aScenarioId = null; this.glancePage = 1; this.shockPage = 1 }, aScenarioId() { this.shockPage = 1 } },
  methods: {
    pagedShocks(set, sc) { return this.edit(set, sc).shocks.slice(this.shockOffset, this.shockOffset + 15) },
    addShock(set, sc) {
      const list = this.edit(set, sc).shocks
      list.push({ statistic_code: this.assumptions.variables[0]?.statistic_code, year_offset: 0, kind: 'abs', value: 0, note: '' })
      this.shockPage = Math.ceil(list.length / 15)
    },
    removeShock(set, sc, index) {
      const list = this.edit(set, sc).shocks
      list.splice(index, 1)
      this.shockPage = Math.min(this.shockPage, Math.max(1, Math.ceil(list.length / 15)))
    },
    fmt(v) { return v === null || v === undefined || v === '' || isNaN(v) ? '-' : Number(v).toLocaleString(undefined, { maximumFractionDigits: 2 }) },
    defaultSetId(a) { const sets = (a && a.sets) || []; const pick = sets.find(s => s.status === 'DRAFT' || s.status === 'PROPOSED') || sets.find(s => s.status === 'APPROVED' || s.status === 'LOCKED') || sets[0]; return pick ? pick.id : null },
    statusLabel(st) { return { DRAFT: 'draft', PROPOSED: 'proposed', APPROVED: 'approved', LOCKED: 'locked', SUPERSEDED: 'superseded' }[st] || st },
    varName(code) { const v = (this.assumptions?.variables || []).find(x => x.statistic_code === code); return v ? v.statistic_name : code },
    moved(sc, code) { const b = (this.aSet.base[code] || [])[0]; const v = (sc.path[code] || [])[0]; return v !== null && v !== undefined && b !== null && b !== undefined && Math.abs(v - b) > 1e-9 },
    saveWeights(set) { router.post(route('macro-statistics.assumptions.weights', set.id), { weights: set.scenarios.map(sc => ({ id: sc.id, weight: this.edit(set, sc).weight })) }, { preserveScroll: true, onSuccess: () => this.goAssumptions() }) },
    async newVersion(set) {
      const reason = await promptReason({ title: 'Change an approved set?', message: 'A draft copy of this set is made as a new version. Change its weights and shocks, then propose it; the approved set stays in force until the new one is approved.', label: 'Why it changes', confirmLabel: 'Create the draft' })
      if (reason) router.post(route('macro-statistics.assumptions.version', set.id), { reason }, { preserveScroll: true, onSuccess: () => { this.aSetId = null; this.goAssumptions() } })
    },
    async discard(set) { if (await confirmDialog({ title: `Discard draft version ${set.version}?`, message: 'The draft, its scenarios and their shocks are removed. The audit log keeps what was there. An approved set is not affected.', confirmLabel: 'Discard', tone: 'danger' })) router.post(route('scenario-sets.discard', set.id), {}, { preserveScroll: true, onSuccess: () => this.goAssumptions() }) },
    kindLabel(k) { return { abs: 'add points', pct: 'percent change', replace: 'replace with' }[k] || k },
    batchSeries(b) { try { return JSON.parse(b.series || '[]').map(x => x.code).join(', ') } catch (e) { return '' } },
    select(s) { this.sel = s.id; this.tab = 'Data Entry'; this.go() },
    approveOverride(o) { router.post(route('macro-statistics.overrides.approve', o.id), {}, { preserveScroll: true }) },
    go() { router.get(route('macro-statistics.index'), { series: this.sel, tab: KEYS[this.tab] }, { preserveState: true, preserveScroll: true, only: ['observations', 'selected', 'tabCounts', 'menu'] }) },
    goAssumptions() { router.get(route('macro-statistics.index'), { tab: 'scenarios', aperiod: this.aPeriod }, { preserveState: true, preserveScroll: true, only: ['assumptions'] }) },
    // variables
    openVariable(s = null) {
      this.varForm = s ? { id: s.id, statistic_code: s.code, statistic_name: s.name, unit: s.unit, frequency: s.frequency || 'yearly', data_source: s.source, website_link: s.website, world_bank: s.codes.world_bank || '', imf_weo: s.codes.imf_weo || '', other_code: s.codes.rbm || '', statistic_description: s.why }
        : { id: null, statistic_code: '', statistic_name: '', unit: '', frequency: 'monthly', data_source: '', website_link: '', world_bank: '', imf_weo: '', other_code: '', statistic_description: '' }
    },
    saveVariable() {
      const f = { ...this.varForm, statistic_code: String(this.varForm.statistic_code || '').toUpperCase().trim() }
      const opts = { preserveScroll: true, onSuccess: (p) => { if (!(p.props.flash && p.props.flash.error) && !Object.keys(p.props.errors || {}).length) this.varForm = null } }
      f.id ? router.put(route('macro-statistics.variables.update', f.id), f, opts) : router.post(route('macro-statistics.variables.store'), f, opts)
    },
    async deleteVariable(s) {
      if (await confirmDialog({ title: `Delete ${s.code}?`, message: s.n ? `It holds ${s.n} values, so the system will refuse until they are deleted.` : 'The variable is removed. This cannot be undone.', confirmLabel: 'Delete', tone: 'danger' }))
        router.delete(route('macro-statistics.variables.destroy', s.id), { preserveScroll: true })
    },
    // values
    openValue(o = null) {
      this.valForm = o ? { id: o.id, period: String(o.period).slice(0, 10), value: o.value, is_forecast: !!o.is_forecast, published_at: o.published_at ? String(o.published_at).slice(0, 10) : '', reason: '' }
        : { id: null, period: '', value: '', is_forecast: false, published_at: '', reason: '' }
    },
    saveValue() {
      const f = this.valForm
      const done = { preserveScroll: true, onSuccess: () => { this.valForm = null; this.go() } }
      if (f.id) router.put(route('macro-statistics.observations.update', f.id), { value: f.value, is_forecast: f.is_forecast, published_at: f.published_at || null, reason: f.reason }, done)
      else router.post(route('macro-statistics.manual'), { series_code: this.current.code, period: f.period, value: f.value, is_forecast: f.is_forecast, published_at: f.published_at || null, reason: f.reason }, done)
    },
    async deleteValue(o) {
      const reason = await promptReason({ title: 'Delete this value?', message: `${this.current ? this.current.code : ''} for ${String(o.period).slice(0, 10)}: ${o.value}. The old value is kept in the audit log.`, label: 'Why it is deleted', confirmLabel: 'Delete', tone: 'danger' })
      if (reason) router.delete(route('macro-statistics.observations.destroy', o.id), { data: { reason }, preserveScroll: true, onSuccess: () => this.go() })
    },
    // assumptions
    edit(set, sc) {
      const k = set.id + ':' + sc.id
      if (!this.edits[k]) this.edits[k] = { weight: sc.weight, shocks: (sc.shocks || []).map(s => ({ statistic_code: s.statistic_code, year_offset: Number(s.year_offset), kind: s.kind, value: Number(s.value), note: s.note || '' })) }
      return this.edits[k]
    },
    shocked(set, sh) {
      const b = (set.base[sh.statistic_code] || [])[sh.year_offset]
      if (sh.kind === 'replace') return sh.value
      if (b === null || b === undefined) return null
      return sh.kind === 'pct' ? b * (1 + Number(sh.value) / 100) : b + Number(sh.value)
    },
    saveScenario(set, sc) {
      const e = this.edit(set, sc)
      router.post(route('macro-statistics.assumptions.save', set.id), { scenario_id: sc.id, weight: e.weight, shocks: e.shocks }, { preserveScroll: true, onSuccess: () => this.goAssumptions() })
    },
    // imports
    xsrf() { const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/); return m ? decodeURIComponent(m[1]) : '' },
    async previewOwn() {
      this.previewError = null; this.previewData = null; this.ownPreview = null
      const fd = new FormData(); fd.append('file', this.own.file)
      const res = await fetch(route('macro-statistics.csv-preview'), { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-XSRF-TOKEN': this.xsrf(), Accept: 'application/json' } })
      const data = await res.json().catch(() => ({ error: 'The file could not be read.' }))
      this.specSource = 'macro-series'; this.specCheck = data.check || null
      if (!res.ok || data.error) { this.previewError = data.error || (data.message || 'Preview failed'); return }
      this.ownPreview = data
    },
    commitOwn() { router.post(route('macro-statistics.csv-commit'), { previews: this.ownPreview.previews }, { preserveScroll: true, onSuccess: () => { this.ownPreview = null; this.showImport = false } }) },
    async preview(source, form) {
      this.previewError = null; this.previewData = null; this.ownPreview = null; this.previewSource = source
      const fd = new FormData(); fd.append('source', source)
      Object.entries(form).forEach(([k, v]) => { if (v !== null && v !== '' && v !== undefined) fd.append(k, v) })
      const res = await fetch(route('macro-statistics.preview'), { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-XSRF-TOKEN': this.xsrf(), Accept: 'application/json' } })
      const data = await res.json().catch(() => ({ error: 'The preview could not be read.' }))
      if (source !== 'world_bank') { this.specSource = source === 'imf_weo' ? 'imf-weo' : 'rbm-policy-rate'; this.specCheck = data.check || null }
      if (!res.ok || data.error) { this.previewError = data.error || 'Preview failed'; return }
      this.previewData = data
    },
    commit() { router.post(route('macro-statistics.commit'), { preview: this.previewData, source: this.previewSource }, { preserveScroll: true, onSuccess: () => { this.previewData = null; this.showImport = false } }) },
  },
}
</script>
