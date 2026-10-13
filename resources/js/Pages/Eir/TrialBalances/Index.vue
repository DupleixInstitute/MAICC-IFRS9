<template>
  <app-layout title="Trial Balances" description="Finance's monthly trial balance from E-Banker: loaded through the landing zone's gates, read by the GL reconciliation and the AFS bridge">
    <div class="w-full space-y-5">
      <KpiRow :cards="cards" />

      <!-- the action sits right-aligned above the table it fills -->
      <div v-if="canUpload" class="flex justify-end">
        <button type="button" class="primary-btn" @click="open = true"><font-awesome-icon icon="upload" /> Upload trial balance</button>
      </div>

      <div class="maiic-panel">
        <div class="border-b border-gray-200 p-4 dark:border-slate-700">
          <h3 class="font-semibold text-gray-900 dark:text-slate-100">Months held</h3>
          <p class="mt-1 text-xs text-gray-500">One row per month and basis. Post-closing is the month-end balance; pre-closing is December before the year-end closing entries (from the AFS bridge).</p>
        </div>
        <div class="maiic-table-wrap">
          <table class="maiic-table">
            <thead><tr><th>Month</th><th>Basis</th><th class="num">GL lines</th><th class="num">Debits</th><th class="num">Credits</th><th class="num">Difference</th><th>Source file</th></tr></thead>
            <tbody>
              <tr v-for="p in pagedPeriods" :key="p.period + p.basis">
                <td class="whitespace-nowrap font-semibold">{{ p.period }}</td>
                <td><span class="maiic-badge" :class="p.basis === 'PRECLOSING' ? 'maiic-badge-gold' : 'maiic-badge-green'">{{ p.basis === 'PRECLOSING' ? 'Pre-closing' : 'Post-closing' }}</span></td>
                <td class="num">{{ p.lines }}</td>
                <td class="num">{{ money(p.debit) }}</td>
                <td class="num">{{ money(p.credit) }}</td>
                <td class="num" :class="Math.abs(p.difference) > 0.01 ? 'font-semibold text-red-600' : 'text-gray-500'">{{ money(p.difference) }}</td>
                <td class="max-w-[16rem] truncate text-xs text-gray-600" :title="p.source_file">{{ p.source_file || '-' }}</td>
              </tr>
              <tr v-if="!periods.length"><td colspan="7" class="maiic-empty">No trial balance is held yet. Use <strong>Upload trial balance</strong> above the table.</td></tr>
            </tbody>
          </table>
        </div>
        <div v-if="periods.length > 15" class="border-t border-gray-100 px-4 py-3"><ClientPager v-model="page" :total="periods.length" /></div>
      </div>

      <div class="maiic-panel">
        <div class="border-b border-gray-200 p-4 dark:border-slate-700">
          <h3 class="font-semibold text-gray-900 dark:text-slate-100">Loads</h3>
          <p class="mt-1 text-xs text-gray-500">Every file loaded, with its gates. A refused file keeps its rows in the landing zone but nothing reads them.</p>
        </div>
        <div class="maiic-table-wrap">
          <table class="maiic-table">
            <thead><tr><th>Load</th><th>File</th><th>Month</th><th class="num">Lines</th><th>Status</th><th>Gates</th><th>Loaded</th></tr></thead>
            <tbody>
              <tr v-for="l in pagedLoads" :key="l.id">
                <td class="font-semibold">#{{ l.id }}</td>
                <td class="max-w-[16rem] truncate text-xs" :title="l.file">{{ l.file }}</td>
                <td class="whitespace-nowrap">{{ l.period || '-' }}</td>
                <td class="num">{{ l.rows }}</td>
                <td><span class="maiic-badge" :class="l.status === 'LANDED' ? 'maiic-badge-green' : 'maiic-badge-red'">{{ l.status === 'LANDED' ? 'Landed' : 'Refused' }}</span></td>
                <td class="text-xs">
                  <div v-for="g in l.gates" :key="g.gate" :class="g.result === 'PASS' ? 'text-gray-600' : 'font-semibold text-red-600'" :title="(g.failures || []).join('; ')">
                    {{ label(g.gate) }}: {{ g.result === 'PASS' ? 'passed' : 'failed' }}<span v-if="g.detail" class="text-gray-400"> ({{ g.detail }})</span>
                  </div>
                </td>
                <td class="whitespace-nowrap text-xs text-gray-600">{{ when(l.loaded_at) }}<div class="text-gray-400">{{ l.loaded_by || 'System' }}</div></td>
              </tr>
              <tr v-if="!loads.length"><td colspan="7" class="maiic-empty">No trial balance has been loaded from a file yet.</td></tr>
            </tbody>
          </table>
        </div>
        <div v-if="loads.length > 15" class="border-t border-gray-100 px-4 py-3"><ClientPager v-model="loadPage" :total="loads.length" /></div>
      </div>
    </div>

    <jet-modal :show="open" max-width="5xl" @close="open = false">
      <form class="p-6" @submit.prevent="submit">
        <h3 class="text-lg font-semibold text-gray-900">Upload a trial balance</h3>
        <ImportSpecPanel class="mt-3" spec-key="trial-balance" :file="form.file" :check-data="{ period: form.period, sheet: form.sheet }"
                         :extra-steps="tbSteps" />
        <div class="mt-4 space-y-4">
          <div>
            <label class="maiic-flabel" for="tb-file">File</label>
            <input id="tb-file" type="file" accept=".xls,.xlsx" class="block w-full text-sm" @change="form.file = $event.target.files[0]" />
            <p v-if="form.errors.file" class="mt-1 text-xs text-red-600">{{ form.errors.file }}</p>
          </div>
          <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div>
              <label class="maiic-flabel" for="tb-basis">Basis</label>
              <select id="tb-basis" v-model="form.basis" class="maiic-select">
                <option value="POSTCLOSING">Post-closing (month-end)</option>
                <option value="PRECLOSING">Pre-closing (December, before closing)</option>
              </select>
            </div>
            <div>
              <label class="maiic-flabel" for="tb-period">Month (only if the file has no date)</label>
              <input id="tb-period" v-model="form.period" type="month" class="maiic-input" />
              <p v-if="form.errors.period" class="mt-1 text-xs text-red-600">{{ form.errors.period }}</p>
            </div>
            <div>
              <label class="maiic-flabel" for="tb-sheet">Sheet (optional)</label>
              <input id="tb-sheet" v-model="form.sheet" type="text" class="maiic-input" placeholder="the first sheet" />
            </div>
          </div>
        </div>
        <div class="mt-6 flex justify-end gap-2">
          <button type="button" class="secondary-btn" :disabled="form.processing" @click="open = false">Cancel</button>
          <button type="submit" class="primary-btn" :disabled="form.processing || !form.file">{{ form.processing ? 'Loading...' : 'Load trial balance' }}</button>
        </div>
      </form>
    </jet-modal>
  </app-layout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import JetModal from '@/Jetstream/Modal.vue'
import KpiRow from '@/Components/Maiic/KpiRow.vue'
import ClientPager from '@/Components/ClientPager.vue'
import ImportSpecPanel from '@/Components/Imports/ImportSpecPanel.vue'

const props = defineProps({
  periods: { type: Array, default: () => [] },
  loads: { type: Array, default: () => [] },
  summary: { type: Object, default: () => ({}) },
  canUpload: { type: Boolean, default: false },
})

const open = ref(false)
const tbSteps = ["Loading the same file twice changes nothing. A corrected file for a month already held replaces that month's lines; the old version is kept in the landing zone."]
const page = ref(1)
const loadPage = ref(1)
const pagedPeriods = computed(() => props.periods.slice((page.value - 1) * 15, page.value * 15))
const pagedLoads = computed(() => props.loads.slice((loadPage.value - 1) * 15, loadPage.value * 15))

const form = useForm({ file: null, basis: 'POSTCLOSING', period: '', sheet: '' })
function submit() {
  form.post(route('eir-trial-balances.upload'), {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => { open.value = false; form.reset() },
  })
}

const cards = computed(() => [
  { label: 'Months held', value: Number(props.summary.months || 0), sub: 'post-closing' },
  { label: 'First month', value: props.summary.first || '-' },
  { label: 'Latest month', value: props.summary.last || '-' },
  { label: 'Refused files', value: Number(props.summary.refused || 0), accent: props.summary.refused ? '#dc2626' : null },
])

const money = (v) => Number(v || 0).toLocaleString('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const when = (v) => v ? new Date(v).toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-'
const label = (g) => String(g).replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase())
</script>
