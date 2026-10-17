<template>
  <app-layout title="PD bases compared" description="Every PD segmentation basis measured beside the booked one, month by month: the whole book, by portfolio, by RBM sector, and by portfolio and sector. Only the governed basis is booked; the others are kept as the record behind the choice">
    <template #actions>
      <button v-if="canGovern && period" type="button" class="secondary-btn" @click="measure"><font-awesome-icon icon="sync-alt" /> Measure the bases again for {{ period }}</button>
    </template>

    <div class="w-full space-y-5">
      <KpiRow :cards="cards" />

      <Notice id="pd-bases-intro">
        Each month the PD engine books the basis governed on the Governance Centre (PD &amp; LGD segments) and measures the other three beside it on the same loans, window,
        minimum and thin-segment rule. A segment below the minimum takes its parent's PD (the portfolio, then the pooled book) under the thin rule, as it would if booked.
        Nothing here changes a booked PD, LGD or ECL. To change the booked basis, propose it on the Governance Centre; a second person approves it, and the months are run again.
      </Notice>

      <div class="maiic-panel p-4">
        <div class="flex flex-wrap items-end gap-4">
          <div>
            <label class="maiic-flabel" for="pdb-month">Month-end</label>
            <select id="pdb-month" class="maiic-select" :value="period" @change="router.get(route('pd-bases.index'), { period: $event.target.value }, { preserveScroll: true })">
              <option v-for="m in months" :key="m" :value="m">{{ m }}</option>
            </select>
          </div>
          <div v-if="booked" class="text-sm text-gray-700 dark:text-slate-300">Booked: <strong>{{ booked.label }}</strong>, window {{ booked.window }}</div>
          <div v-if="settings" class="text-xs text-gray-500 dark:text-slate-400">Minimum: {{ settings.minimum_option }}. Thin rule: {{ settings.thin_option }}.</div>
        </div>
      </div>

      <div data-actions-anchor></div>
      <div class="maiic-panel">
        <div class="border-b border-gray-200 p-4 dark:border-slate-700">
          <h3 class="font-semibold text-gray-900 dark:text-slate-100">Every basis for {{ period || 'no month yet' }}</h3>
          <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">The 12-month PD of each segment and stage: its own where it meets the minimum, else its parent's. Defaults / cohort in brackets. The booked basis is marked.</p>
        </div>
        <div class="maiic-table-wrap">
          <table class="maiic-table maiic-table-compact">
            <thead><tr><th>Basis</th><th>Segment</th><th class="num">Stage 1 PD</th><th class="num">Stage 2 PD</th><th class="num">Loans now</th></tr></thead>
            <tbody>
              <tr v-for="r in pagedRows" :key="r.basis + r.key" :class="r.booked ? 'bg-emerald-50/60 dark:bg-emerald-900/20' : ''">
                <td class="whitespace-nowrap text-xs"><span class="font-semibold text-gray-900 dark:text-slate-100">{{ r.basisLabel }}</span><span v-if="r.booked" class="maiic-badge maiic-badge-green ml-2">Booked</span></td>
                <td class="text-xs" :class="r.parent ? 'pl-6' : 'font-semibold'">{{ r.label }}</td>
                <td v-for="s in [1, 2]" :key="s" class="num text-xs">
                  <template v-if="r.stages[s]">
                    <span :class="r.stages[s].status === 'own' || r.stages[s].status === 'ok' ? 'font-semibold text-gray-900 dark:text-slate-100' : 'text-gray-500 dark:text-slate-400'">{{ pct(r.stages[s].applied ?? r.stages[s].observed) }}</span>
                    <div class="text-gray-400">{{ cellNote(r.stages[s]) }}</div>
                  </template>
                  <span v-else class="text-gray-400">-</span>
                </td>
                <td class="num text-xs">{{ (r.stages[1]?.loans || 0) + (r.stages[2]?.loans || 0) }}</td>
              </tr>
              <tr v-if="!rows.length"><td colspan="5" class="maiic-empty">No basis is measured for this month yet. The month-end measures them after the booked PD run{{ canGovern ? '; or use the button above this table' : '' }}.</td></tr>
            </tbody>
          </table>
        </div>
        <RowPager v-model="page" :total="rows.length" class="border-t border-gray-100 dark:border-slate-700" />
        <div v-for="b in bases.filter((x) => x.note && x.failed_cells)" :key="'n' + b.basis" class="border-t border-gray-100 px-4 py-2 text-xs text-amber-700 dark:border-slate-700 dark:text-amber-300">{{ b.label }}: {{ b.note }}</div>
      </div>

      <div class="maiic-panel">
        <div class="border-b border-gray-200 p-4 dark:border-slate-700">
          <h3 class="font-semibold text-gray-900 dark:text-slate-100">Cumulative PD over five years, {{ period }}</h3>
          <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">From each segment's 12-month PD at a constant hazard, as the booked ECL reads it: 1 - (1 - PD) to the power of the years. The booked basis's segments; Stage 3 is in default (PD 1).</p>
        </div>
        <div class="maiic-table-wrap">
          <table class="maiic-table maiic-table-compact">
            <thead><tr><th>Segment</th><th>Stage</th><th class="num">12-month</th><th class="num">Year 2</th><th class="num">Year 3</th><th class="num">Year 4</th><th class="num">Year 5</th></tr></thead>
            <tbody>
              <tr v-for="c in pagedCumulative" :key="c.key + c.stage">
                <td class="text-xs" :class="c.parent ? 'pl-6' : 'font-semibold'">{{ c.label }}</td><td>{{ c.stage }}</td>
                <td v-for="y in [1, 2, 3, 4, 5]" :key="y" class="num text-xs">{{ pct(1 - Math.pow(1 - c.pd, y)) }}</td>
              </tr>
              <tr v-if="!cumulative.length"><td colspan="7" class="maiic-empty">No booked basis is measured for this month.</td></tr>
            </tbody>
          </table>
        </div>
        <RowPager v-model="cumPage" :total="cumulative.length" class="border-t border-gray-100 dark:border-slate-700" />
      </div>

      <div class="maiic-panel">
        <div class="border-b border-gray-200 p-4 dark:border-slate-700">
          <h3 class="font-semibold text-gray-900 dark:text-slate-100">The pooled book, every month</h3>
          <p class="mt-1 text-xs text-gray-500 dark:text-slate-400">The whole MAIIC book's 12-month PD by stage and its LGD, from each month's booked run (defaults / cohort; the LGD's Stage 3 cohort). Before December 2025 the history is too short for a Stage 1 or 2 PD: those months book Stage 3 only.</p>
        </div>
        <div class="maiic-table-wrap">
          <table class="maiic-table maiic-table-compact">
            <thead><tr><th>Month-end</th><th class="num">Stage 1 PD</th><th class="num">Stage 1 over 3 years</th><th class="num">Stage 2 PD</th><th class="num">Stage 2 over 3 years</th><th class="num">LGD</th></tr></thead>
            <tbody>
              <tr v-for="h in pagedHistory" :key="h.period">
                <td class="whitespace-nowrap font-semibold">{{ h.period }}</td>
                <td class="num text-xs">{{ pct(h.s1) }}<div v-if="h.s1 !== null" class="text-gray-400">{{ h.s1_n }}</div></td>
                <td class="num text-xs">{{ h.s1 === null ? '-' : pct(1 - Math.pow(1 - h.s1, 3)) }}</td>
                <td class="num text-xs">{{ pct(h.s2) }}<div v-if="h.s2 !== null" class="text-gray-400">{{ h.s2_n }}</div></td>
                <td class="num text-xs">{{ h.s2 === null ? '-' : pct(1 - Math.pow(1 - h.s2, 3)) }}</td>
                <td class="num text-xs">{{ pct(h.lgd) }}<div v-if="h.lgd_n !== null" class="text-gray-400">{{ h.lgd_n }} loans</div></td>
              </tr>
              <tr v-if="!history.length"><td colspan="6" class="maiic-empty">No booked PD run yet.</td></tr>
            </tbody>
          </table>
        </div>
        <RowPager v-model="historyPage" :total="history.length" class="border-t border-gray-100 dark:border-slate-700" />
      </div>
    </div>
  </app-layout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import KpiRow from '@/Components/Maiic/KpiRow.vue'
import Notice from '@/Components/Maiic/Notice.vue'
import RowPager from '@/Components/Maiic/RowPager.vue'
import { confirmDialog } from '@/Components/confirmDialog'

const props = defineProps({
  period: { type: String, default: '' },
  months: { type: Array, default: () => [] },
  booked: { type: Object, default: null },
  bases: { type: Array, default: () => [] },
  history: { type: Array, default: () => [] },
  settings: { type: Object, default: null },
  canGovern: { type: Boolean, default: false },
})

const rows = computed(() => props.bases.flatMap((b) => b.segments.map((s) => ({ ...s, basis: b.basis, basisLabel: b.label, booked: b.booked }))))
const page = ref(1)
const pagedRows = computed(() => rows.value.slice((page.value - 1) * 15, page.value * 15))
const historyPage = ref(1)
const historyDesc = computed(() => [...props.history].reverse())
const pagedHistory = computed(() => historyDesc.value.slice((historyPage.value - 1) * 15, historyPage.value * 15))

const cumulative = computed(() => {
  const b = props.bases.find((x) => x.booked)
  if (!b) return []
  return b.segments.flatMap((s) => [1, 2].filter((st) => s.stages[st] && (s.stages[st].applied ?? s.stages[st].observed) !== null)
    .map((st) => ({ key: s.key, label: s.label, parent: s.parent, stage: st, pd: Number(s.stages[st].applied ?? s.stages[st].observed) })))
})

const cumPage = ref(1)
const pagedCumulative = computed(() => cumulative.value.slice((cumPage.value - 1) * 15, cumPage.value * 15))

const cellNote = (c) => {
  const n = `(${c.defaults}/${c.cohort})`
  if (c.status === 'parent') return `parent's ${n}`
  if (c.status === 'failed') return 'no PD under the thin rule'
  if (c.status === 'thin') return `thin ${n}`
  return n
}

async function measure() {
  if (!(await confirmDialog({ title: `Measure every basis again for ${props.period}?`, message: 'The four bases are measured on the month as it stands and replace the month\'s earlier comparison. Nothing booked changes.', confirmLabel: 'Measure' }))) return
  router.post(route('pd-bases.measure'), { period: props.period }, { preserveScroll: true })
}

const cards = computed(() => {
  const b = props.bases.find((x) => x.booked)
  return [
    { label: 'Booked basis', value: props.booked ? props.booked.label : '-' },
    { label: 'Bases measured', value: props.bases.length },
    { label: 'Booked cells on their own PD', value: b ? b.own_cells : 0 },
    { label: 'Booked cells taking a parent PD', value: b ? b.parent_cells : 0, accent: b && b.parent_cells ? '#d97706' : null },
  ]
})

const pct = (v) => (v === null || v === undefined ? '-' : (Number(v) * 100).toFixed(2) + '%')
</script>
