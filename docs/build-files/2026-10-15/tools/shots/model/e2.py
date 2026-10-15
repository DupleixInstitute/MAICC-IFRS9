import os
os.chdir(r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/Eir')

def rep(s, a, b):
    if a not in s:
        raise SystemExit('NOT FOUND: ' + a[:120])
    return s.replace(a, b)

p='Calculations.vue'; s=open(p,encoding='utf-8').read()
s=rep(s,'''          <Link :href="route('eir-data.index')" class="hover:text-maiic-700">EIR Data</Link>
          <span>/</span>
          <span class="font-medium text-maiic-700">Calculations</span>''','''          <span>EIR &amp; Revenue Recognition</span>
          <span>/</span>
          <span class="font-medium text-maiic-700">Calculations</span>''')
s=rep(s,'''        <p class="mt-1 text-sm text-gray-600">Calculate, independently approve, and lock each contract's original effective interest rate</p>
      </div>
    </template>''','''        <p class="mt-1 text-sm text-gray-600">Calculate each contract's original effective interest rate, then have it approved and locked by someone else</p>
      </div>
    </template>
    <template #actions>
      <button v-if="selected.length" type="button" class="primary-btn" @click="calculate">Calculate selected ({{ selected.length }})</button>
    </template>''')
s=rep(s,'''      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div v-for="s in statuses" :key="s" class="bg-white border border-gray-200 shadow-sm rounded-lg p-4">
          <div class="text-2xl font-bold text-gray-800">{{ summary[s]?.contract_count || 0 }}</div>
          <div class="text-xs text-gray-500">{{ s }} contracts</div>
        </div>
      </div>''','''      <KpiRow :cards="cards" />''')
s=rep(s,'''      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
        <form class="grid grid-cols-1 md:grid-cols-4 gap-3" @submit.prevent="applyFilters">
          <input v-model="filter.contract_id" class="form-input" placeholder="Contract, account or reference">
          <select v-model="filter.status" class="form-input">
            <option value="">All statuses</option>
            <option v-for="s in statuses" :key="s">{{ s }}</option>
          </select>''','''      <div class="maiic-filterbar">
        <form class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end" @submit.prevent="applyFilters">
          <label class="block"><span class="maiic-flabel">Contract</span><input v-model="filter.contract_id" class="form-input" placeholder="Contract, account or reference"></label>
          <label class="block"><span class="maiic-flabel">Status</span><select v-model="filter.status" class="form-input">
            <option value="">All statuses</option>
            <option v-for="s in statuses" :key="s" :value="s">{{ statusLabel(s) }}</option>
          </select></label>''')
s=rep(s,'''          <span>Showing {{ contracts.from || 0 }}\u2013{{ contracts.to || 0 }} of {{ contracts.total || 0 }} matching records.</span>''','''          <span v-if="contracts.total">Showing {{ contracts.from }} to {{ contracts.to }} of {{ Number(contracts.total).toLocaleString() }} matching contracts.</span>
          <span v-else>No contracts match.</span>''')
s=rep(s,'''      <div v-if="selected.length" class="bg-maiic-50 border border-maiic-200 rounded-lg p-4 flex items-center justify-between">
        <span class="font-medium text-maiic-900">{{ selected.length }} contract(s) selected</span>
        <button @click="calculate" class="primary-btn">Calculate selected</button>
      </div>
''','')
s=rep(s,'''                <th class="th">Periodic EIR</th>
                <th class="th">Effective annual EIR</th>
                <th class="th">Evidence</th>
                <th class="th">Action</th>''','''                <th class="th text-right">Periodic EIR</th>
                <th class="th text-right">Effective annual EIR</th>
                <th class="th">Evidence</th>
                <th class="th">Actions</th>''')
s=rep(s,'''<span :class="statusClass(contract.calculation_status)">{{ contract.calculation_status }}</span>''','''<span :class="statusClass(contract.calculation_status)">{{ statusLabel(contract.calculation_status) }}</span>''')
s=rep(s,'''                <td class="td">{{ percent(contract.eir_period) }}</td>
                <td class="td font-medium">{{ percent(contract.eir_effective_annual) }}</td>''','''                <td class="td text-right tabular-nums">{{ percent(contract.eir_period) }}</td>
                <td class="td text-right tabular-nums font-medium">{{ percent(contract.eir_effective_annual) }}</td>''')
s=rep(s,'''                  <div class="flex flex-col items-start gap-2">
                    <button v-if="contract.calculation_status === 'CALCULATED'" @click="approve(contract)" class="primary-btn">Approve & lock</button>
                    <button v-if="!contract.locked_at" @click="recalculate(contract)" class="secondary-btn">
                      {{ contract.calculation_status === 'PENDING' ? 'Calculate' : contract.calculation_status === 'BLOCKED' ? 'Retry calculation' : 'Recalculate' }}
                    </button>
                    <button v-if="contract.locked_at && canReopen" @click="openReopenModal(contract)" class="secondary-btn border-amber-300 text-amber-800 hover:bg-amber-50">Reopen locked EIR</button>
                    <span v-else-if="contract.locked_at" class="text-xs text-gray-500">Locked \u2014 administrator reopening required</span>
                  </div>''','''                  <div class="flex items-center gap-1.5">
                    <button v-if="!contract.locked_at" type="button" @click="recalculate(contract)" class="maiic-action maiic-action-neutral"
                            :title="contract.calculation_status === 'PENDING' ? 'Calculate' : contract.calculation_status === 'BLOCKED' ? 'Retry calculation' : 'Recalculate'">
                      <font-awesome-icon icon="calculator" />
                    </button>
                    <button v-if="contract.calculation_status === 'CALCULATED'" type="button" @click="approve(contract)" class="maiic-action maiic-action-view" title="Approve and lock">
                      <font-awesome-icon icon="lock" />
                    </button>
                    <button v-if="contract.locked_at && canReopen" type="button" @click="openReopenModal(contract)" class="maiic-action maiic-action-edit" title="Reopen locked EIR">
                      <font-awesome-icon icon="lock-open" />
                    </button>
                    <span v-else-if="contract.locked_at" class="text-xs text-gray-500">Locked, an administrator must reopen it</span>
                  </div>''')
s=rep(s,'''                <td colspan="7" class="p-8 text-center text-gray-500">No contracts match these filters.</td>''','''                <td colspan="7" class="p-10 text-center text-sm text-gray-500">
                  <template v-if="hasActiveFilters">No contracts match these filters. Clear the filters to see every contract.</template>
                  <template v-else>No contracts are loaded yet. Load the contract master through EIR Data Intake first.</template>
                </td>''')
s=rep(s,'''        <div class="p-4 flex flex-wrap gap-2 border-t">
          <button
            v-for="link in contracts.links"
            :key="link.label"
            v-html="link.label"
            :disabled="!link.url"
            @click="go(link.url)"
            class="px-3 py-1 border rounded text-sm disabled:opacity-40"
            :class="link.active ? 'bg-maiic-600 text-white' : 'bg-white text-gray-700'"
          ></button>
        </div>''','''        <div v-if="contracts.links && contracts.links.length > 3" class="px-4 pb-4 border-t">
          <Pagination :links="contracts.links" />
        </div>''')
s=rep(s,'''      <p v-if="Object.keys(errors).length" class="text-sm text-red-700">{{ Object.values(errors)[0] }}</p>''','''      <div v-if="Object.keys(errors).length" class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
        <font-awesome-icon icon="exclamation-circle" class="mt-0.5" />
        <div><div class="font-semibold">The action could not be completed</div><div>{{ Object.values(errors)[0] }}</div></div>
      </div>''')
s=rep(s,"{{ reopenModal.processing ? 'Reopening\u2026'","{{ reopenModal.processing ? 'Reopening...'")
s=rep(s,"import { Link, router } from '@inertiajs/vue3'","import { Link, router } from '@inertiajs/vue3'\nimport KpiRow from '@/Components/Maiic/KpiRow.vue'")
s=rep(s,"components: { AppLayout, Link },","components: { AppLayout, Link, KpiRow },")
s=rep(s,'''  computed: {
    selectable() {''','''  computed: {
    cards() {
      const accents = { PENDING: '#d97706', REOPENED: '#c69b2c', BLOCKED: '#dc2626', CALCULATED: '#0f766e', LOCKED: '#15803d' }
      return this.statuses.map(s => ({ label: this.statusLabel(s), value: Number(this.summary[s]?.contract_count || 0), sub: 'contracts', accent: accents[s] }))
    },
    selectable() {''')
s=rep(s,'''    percent(v) {
      return v === null || v === undefined ? '\u2014' ''','''    statusLabel(s) {
      return { PENDING: 'Pending', REOPENED: 'Reopened', BLOCKED: 'Blocked', CALCULATED: 'Calculated', LOCKED: 'Locked' }[s] || s
    },
    percent(v) {
      return v === null || v === undefined ? '-' ''')
open(p,'w',encoding='utf-8').write(s)
print('ok')
