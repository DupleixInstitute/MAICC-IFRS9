<template>
  <div>
    <!-- Header: what this matrix is -->
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
      <div class="min-w-0">
        <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-maiic-700">
          {{ type === 'cumulative' ? 'Cumulative transition matrix' : 'Transition matrix' }}
          <span class="text-gray-400">#{{ transitionMatrix?.id }}</span>
        </p>
        <h2 class="text-xl font-extrabold text-maiic-900">
          {{ windowLabel }}<span v-if="segmentLabel" class="font-semibold text-gray-500">, {{ segmentLabel }}</span>
        </h2>
        <div class="mt-1.5 flex flex-wrap items-center gap-1.5 text-xs">
          <span v-if="horizonLabel" class="maiic-badge maiic-badge-green">{{ horizonLabel }}</span>
          <span v-if="basisLabel" class="maiic-badge maiic-badge-grey" :title="'Aggregation set on the transition profile: ' + (meta.profile?.aggregation || '')">{{ basisLabel }}</span>
          <span class="maiic-badge maiic-badge-grey">{{ methodLabel }}</span>
          <span v-if="meta.profile" class="maiic-badge maiic-badge-grey">Profile {{ meta.profile.code }}<template v-if="meta.profile.name && meta.profile.name !== meta.profile.code"> ({{ meta.profile.name }})</template></span>
          <span class="maiic-badge" :class="mode === 'edit' ? 'maiic-badge-gold' : 'maiic-badge-grey'">{{ mode === 'edit' ? 'Editing' : 'Read only' }}</span>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button v-if="startStages.length" type="button" class="secondary-btn" title="Download this matrix as a CSV file (opens in Excel)" @click="downloadCsv">
          <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2a1 1 0 0 1 1 1v7.59l2.3-2.3a1 1 0 1 1 1.4 1.42l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.42L9 10.59V3a1 1 0 0 1 1-1Zm-7 13a1 1 0 0 1 1 1v1h12v-1a1 1 0 1 1 2 0v2a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-2a1 1 0 0 1 1-1Z"/></svg>
          Download CSV
        </button>
      </div>
    </div>

    <div v-if="loading" class="maiic-empty">Loading the matrix...</div>
    <div v-else-if="loadError" class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
      <span class="mt-0.5 inline-flex h-5 w-5 flex-none items-center justify-center rounded-full bg-red-600 text-xs font-bold text-white">!</span>
      <div><p class="font-bold">The matrix could not be loaded</p><p>{{ loadError }}</p></div>
    </div>

    <template v-else>
      <!-- Headline figures: one line -->
      <div v-if="startStages.length" class="mb-4 grid grid-cols-2 gap-3" :class="kpiCols">
        <div class="maiic-kpi !py-3">
          <p class="maiic-kpi-label">Starting {{ isCount ? 'loans' : 'balance' }}</p>
          <p class="maiic-kpi-value !text-xl">{{ formatCompact(grandStart) }}</p>
        </div>
        <div v-for="start in startStages" :key="'k' + start.id" class="maiic-kpi !py-3" :style="{ '--accent': stageHex(start) }">
          <p class="maiic-kpi-label">PD from {{ stageName(start) }}</p>
          <p class="maiic-kpi-value !text-xl">{{ pct(pdPercentages[start.category_name]) }}</p>
          <p class="text-[11px] text-gray-500">on {{ formatCompact(startTotals[start.category_name] || 0) }}</p>
        </div>
      </div>

      <!-- Matrix grid -->
      <div class="overflow-x-auto rounded-xl border border-gray-200 shadow-sm">
        <table class="w-full border-collapse text-sm">
          <thead>
            <tr class="bg-maiic-700 text-white">
              <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider whitespace-nowrap">
                From \ To
                <div class="text-[10px] font-semibold normal-case tracking-normal text-maiic-100">{{ unitLabel }}</div>
              </th>
              <th v-for="end in endStages" :key="end.id" class="px-4 py-3 text-right whitespace-nowrap">
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-bold" :class="stageChip(end, true)">
                  {{ stageName(end) }}
                  <span v-if="isDefault(end)" class="rounded bg-white/25 px-1 text-[10px] uppercase tracking-wider">default</span>
                </span>
              </th>
              <th class="bg-maiic-800 px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider whitespace-nowrap">Row total</th>
              <th class="bg-red-600 px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider whitespace-nowrap">PD to {{ defaultName }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="start in startStages" :key="start.id" class="border-t border-gray-100">
              <td class="bg-gray-50 px-4 py-3 whitespace-nowrap">
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold" :class="stageChip(start)">
                  From {{ stageName(start) }}
                </span>
              </td>
              <td v-for="end in endStages" :key="end.id" class="px-4 py-2.5 text-right align-middle"
                  :class="isDefault(end) ? 'border-x border-red-100' : ''" :style="mode === 'edit' ? {} : cellStyle(start, end)">
                <template v-if="mode !== 'edit'">
                  <div class="font-semibold tabular-nums text-gray-900">{{ formatAmount(cell(start, end)) }}</div>
                  <div class="text-[11px] font-semibold tabular-nums text-gray-600">{{ share(start, end) }}</div>
                </template>
                <input v-else-if="matrix[start.category_name] && matrix[start.category_name][end.category_name]" type="number" step="0.01"
                       v-model.number="matrix[start.category_name][end.category_name][valueKey]"
                       class="maiic-input min-w-[9rem] px-2 py-1 text-right tabular-nums" />
                <span v-else class="text-xs text-gray-400" title="No figure was stored for this cell">-</span>
              </td>
              <td class="bg-maiic-50 px-4 py-2.5 text-right font-bold tabular-nums text-maiic-900 whitespace-nowrap">
                {{ formatAmount(rowTotal(start)) }}
                <div class="text-[11px] font-semibold text-gray-500">{{ grandShare(rowTotal(start)) }} of all</div>
              </td>
              <td class="bg-red-50 px-4 py-2.5 text-right whitespace-nowrap">
                <span class="inline-flex min-w-[4.5rem] justify-center rounded-full px-2.5 py-1 text-sm font-extrabold tabular-nums" :class="pdTone(pdPercentages[start.category_name])">
                  {{ pct(pdPercentages[start.category_name]) }}
                </span>
              </td>
            </tr>
            <tr v-if="!startStages.length">
              <td :colspan="endStages.length + 3" class="maiic-empty">
                No stage categories found for this matrix. Check the transition profile set-up.
              </td>
            </tr>
          </tbody>
          <tfoot v-if="startStages.length">
            <tr class="border-t-2 border-maiicgold-500 bg-gray-100">
              <th class="px-4 py-3 text-left text-[11px] font-extrabold uppercase tracking-wider text-maiic-900">Total at end</th>
              <th v-for="end in endStages" :key="end.id" class="px-4 py-3 text-right tabular-nums text-maiic-900">
                <div class="font-extrabold">{{ formatAmount(colTotal(end)) }}</div>
                <div class="text-[11px] font-semibold text-gray-500">{{ grandShare(colTotal(end)) }}</div>
              </th>
              <th class="px-4 py-3 text-right font-extrabold tabular-nums text-maiic-900">{{ formatAmount(grandStart) }}</th>
              <th class="bg-red-50"></th>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- How the PD is derived, with the first row's own figures -->
      <p v-if="example" class="mt-3 text-xs text-gray-600">
        <span class="font-bold text-gray-800">PD to {{ defaultName }}</span> = {{ isCount ? 'loans' : 'balance' }} that ended in {{ defaultName }} ÷ the row total for that starting stage<template v-if="type === 'cumulative'">, after adding the monthly matrices in the window together cell by cell</template>.
        For {{ example.name }}: {{ formatAmount(example.toDefault) }} ÷ {{ formatAmount(example.total) }} = {{ pct(example.pd) }}.
      </p>

      <!-- Legend -->
      <div v-if="startStages.length && mode !== 'edit'" class="mt-2 flex flex-wrap items-center gap-x-5 gap-y-1 text-xs text-gray-500">
        <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm" :style="{ background: rgba(HEAT.stay, 0.4) }"></span> stayed or improved</span>
        <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm" :style="{ background: rgba(HEAT.worse, 0.4) }"></span> moved to a worse stage</span>
        <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm" :style="{ background: rgba(HEAT.default, 0.4) }"></span> moved into or stayed in default</span>
        <span class="inline-flex items-center gap-1.5"><span class="h-3 w-3 rounded-sm" :style="{ background: rgba(HEAT.exit, 0.4) }"></span> repaid or settled</span>
        <span>Darker = larger share of the row. Small figure = share of the row total.</span>
      </div>
    </template>

    <div class="mt-5 flex justify-between gap-2">
      <button type="button" class="secondary-btn" @click="$emit('close')">Close</button>
      <button v-if="mode === 'edit' && startStages.length" type="button" class="primary-btn" :disabled="saving" @click="submit">
        {{ saving ? 'Saving...' : 'Save updates' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { notice } from '@/Components/Maiic/notice'
import { usePage } from '@inertiajs/vue3'
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const props = defineProps({
  transitionMatrix: Object,
  type: {
    type: String,
    default: 'normal', // or 'cumulative'
  },
  mode: String, // 'view' or 'edit'
})

defineEmits(['close'])

const matrix = ref({})
const startStages = ref([])
const endStages = ref([])
const startTotals = ref({})
const pdPercentages = ref({})
const endStageTotals = ref({})
const grandTotal = ref(0)
const meta = ref({})
const loading = ref(true)
const loadError = ref('')
const saving = ref(false)

// Determine field key depending on type
const valueKey = computed(() => (props.type === 'cumulative' ? 'transition_balance_cummulated' : 'transition_balance_month'))

// ---- Labels ---------------------------------------------------------------
const page = usePage()
const currencyCode = computed(() => page.props?.currency?.code || page.props?.currency?.international_code || '')
const isCount = computed(() => String(meta.value?.profile?.aggregation || '').toLowerCase() === 'count')
const basisLabel = computed(() => {
  const a = String(meta.value?.profile?.aggregation || '').toLowerCase()
  if (a === 'balance') return 'Amount-weighted (balance)'
  if (a === 'count') return 'Count of loans'
  return meta.value?.profile?.aggregation ? `Basis: ${meta.value.profile.aggregation}` : ''
})
const unitLabel = computed(() => (isCount.value ? 'number of loans' : (currencyCode.value ? `amounts in ${currencyCode.value}` : 'amounts')))
const methodLabel = computed(() => (props.type === 'cumulative' ? 'PD: pooled default rate' : 'PD: observed default rate'))
const segmentLabel = computed(() => {
  if (meta.value?.segment) return meta.value.segment
  const m = props.transitionMatrix || {}
  if (m.portfolio?.name) return m.portfolio.name
  if (m.sector?.name) return `${m.sector.code}. ${m.sector.name}`
  return ''
})

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
const monthLabel = (p) => {
  if (!p) return ''
  const [y, m] = String(p).substring(0, 7).split('-')
  return m ? `${MONTHS[Number(m) - 1]} ${y}` : p
}
const monthsBetween = (a, b) => {
  if (!a || !b) return null
  const [ay, am] = String(a).substring(0, 7).split('-').map(Number)
  const [by, bm] = String(b).substring(0, 7).split('-').map(Number)
  return (by - ay) * 12 + (bm - am)
}
const startPeriod = computed(() => props.transitionMatrix?.start_reporting_period || props.transitionMatrix?.start_period)
const endPeriod = computed(() => props.transitionMatrix?.end_reporting_period || props.transitionMatrix?.end_period)
const windowLabel = computed(() => (startPeriod.value && endPeriod.value
  ? `${monthLabel(startPeriod.value)} to ${monthLabel(endPeriod.value)}`
  : 'Transition matrix'))
const horizonLabel = computed(() => {
  if (props.type === 'cumulative') {
    const n = props.transitionMatrix?.periods_count
    return n ? `${n} matrices pooled` : ''
  }
  const m = monthsBetween(startPeriod.value, endPeriod.value)
  if (m === 12) return '12-month (annual)'
  if (m === 1) return 'One-month'
  return m ? `${m}-month window` : ''
})

// Stage names come from the profile's grade labels: a numeric grade reads
// "Stage n", any other label (e.g. Paid) is shown as stored.
const gradeOf = (opt) => String(opt?.text_value ?? opt?.category_name ?? '')
const isNumericGrade = (opt) => /^\d+$/.test(gradeOf(opt))
const stageName = (opt) => (isNumericGrade(opt) ? `Stage ${gradeOf(opt)}` : gradeOf(opt))
const isDefault = (opt) => Number(opt?.default_value) === 1 || opt?.default_value === true
const defaultEnd = computed(() => endStages.value.find(isDefault))
const defaultName = computed(() => (defaultEnd.value ? stageName(defaultEnd.value) : 'default'))

// ---- Formatting (display only) -------------------------------------------
const formatAmount = (value) => {
  const n = Number(value)
  if (Number.isNaN(n)) return value
  return isCount.value
    ? n.toLocaleString(undefined, { maximumFractionDigits: 0 })
    : n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
const formatCompact = (v) => {
  const n = Number(v || 0)
  if (isCount.value) return n.toLocaleString(undefined, { maximumFractionDigits: 0 }) + ' loans'
  const a = Math.abs(n)
  const c = currencyCode.value ? currencyCode.value + ' ' : ''
  if (a >= 1e9) return c + (n / 1e9).toFixed(2) + 'B'
  if (a >= 1e6) return c + (n / 1e6).toFixed(2) + 'M'
  if (a >= 1e3) return c + (n / 1e3).toFixed(1) + 'K'
  return c + n.toFixed(2)
}
const pct = (v) => (v === null || v === undefined || v === '' ? '0.00%' : Number(v).toFixed(2) + '%')

// ---- Figures (read straight from the server's response) -------------------
const cell = (start, end) => Number(matrix.value?.[start.category_name]?.[end.category_name]?.[valueKey.value] ?? 0)
const rowTotal = (start) => Number(startTotals.value?.[start.category_name] || 0)
const colTotal = (end) => Number(endStageTotals.value?.[end.category_name] || 0)
const grandStart = computed(() => Object.values(startTotals.value || {}).reduce((s, v) => s + Number(v || 0), 0))
const rowShare = (start, end) => {
  const total = rowTotal(start)
  return total > 0 ? cell(start, end) / total : 0
}
const share = (start, end) => (rowShare(start, end) * 100).toFixed(1) + '%'
const grandShare = (v) => (grandStart.value > 0 ? ((Number(v || 0) / grandStart.value) * 100).toFixed(1) + '%' : '0.0%')

// The worked example under the table: the first starting stage with a balance.
const example = computed(() => {
  if (!defaultEnd.value) return null
  const start = startStages.value.find((s) => rowTotal(s) > 0)
  if (!start) return null
  return { name: stageName(start), toDefault: cell(start, defaultEnd.value), total: rowTotal(start), pd: pdPercentages.value?.[start.category_name] }
})

const kpiCols = computed(() => ({ 3: 'sm:grid-cols-3', 4: 'sm:grid-cols-4', 5: 'sm:grid-cols-5', 6: 'sm:grid-cols-6' }[startStages.value.length + 1] || 'sm:grid-cols-4'))

// ---- Colours --------------------------------------------------------------
// Heat by direction of travel: green = stayed or improved, amber = moved to a
// worse stage, red = default, slate = left the book (repaid / settled).
const HEAT = { stay: '22,163,74', worse: '245,158,11', default: '220,38,38', exit: '100,116,139' }
const rgba = (rgb, a) => `rgba(${rgb},${a})`
const direction = (start, end) => {
  if (isDefault(end)) return 'default'
  if (!isNumericGrade(end)) return 'exit'
  if (!isNumericGrade(start)) return 'stay'
  return Number(gradeOf(end)) > Number(gradeOf(start)) ? 'worse' : 'stay'
}
const cellStyle = (start, end) => {
  const s = rowShare(start, end)
  if (s <= 0) return {}
  return { backgroundColor: rgba(HEAT[direction(start, end)], Math.min(0.5, 0.07 + s * 0.5)) }
}
// A start grade is the default grade when it matches the default end grade.
const isDefaultGrade = (opt) => isDefault(opt) || (!!defaultEnd.value && gradeOf(opt) === gradeOf(defaultEnd.value))
const stageHex = (opt) => {
  if (isDefaultGrade(opt)) return '#dc2626'
  if (!isNumericGrade(opt)) return '#64748b'
  return Number(gradeOf(opt)) <= 1 ? '#16a34a' : '#f59e0b'
}
const stageChip = (opt, onDark = false) => {
  if (isDefaultGrade(opt)) {
    return onDark ? 'bg-red-600 text-white' : 'bg-red-100 text-red-700'
  }
  if (!isNumericGrade(opt)) return onDark ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-700'
  return Number(gradeOf(opt)) <= 1
    ? (onDark ? 'bg-maiic-500 text-white' : 'bg-maiic-100 text-maiic-800')
    : (onDark ? 'bg-maiicgold-400 text-maiic-900' : 'bg-amber-100 text-amber-800')
}
const pdTone = (v) => {
  const n = Number(v || 0)
  if (n >= 50) return 'bg-red-600 text-white'
  if (n >= 20) return 'bg-red-100 text-red-700'
  if (n >= 5) return 'bg-amber-100 text-amber-800'
  return 'bg-maiic-100 text-maiic-800'
}

// ---- Data ---------------------------------------------------------------
const dataUrl = computed(() => (props.type === 'cumulative'
  ? `/transition-matrix-cummulative/${props.transitionMatrix.id}/data`
  : `/transition-matrix/${props.transitionMatrix.id}/data`))

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const res = await axios.get(dataUrl.value)
    matrix.value = res.data.matrix || {}
    startStages.value = res.data.startStages || []
    endStages.value = res.data.endStages || []
    startTotals.value = res.data.startTotals || {}
    pdPercentages.value = res.data.pdPercentages || {}
    endStageTotals.value = res.data.endStageTotals || {}
    grandTotal.value = res.data.grandTotal
    meta.value = res.data.meta || {}
  } catch (error) {
    console.error('Failed to load matrix data:', error)
    loadError.value = error.response?.data?.message || 'The server did not return the matrix. Try again, or check the transition profile set-up.'
  } finally {
    loading.value = false
  }
}

onMounted(load)

// ---- Download -----------------------------------------------------------
function downloadCsv() {
  const q = (v) => {
    const s = String(v ?? '')
    return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s
  }
  const raw = (v) => (Number.isFinite(Number(v)) ? Number(v).toFixed(isCount.value ? 0 : 2) : '')
  const lines = []
  lines.push([props.type === 'cumulative' ? 'Cumulative transition matrix' : 'Transition matrix', `#${props.transitionMatrix?.id}`].map(q).join(','))
  lines.push(['Window', windowLabel.value].map(q).join(','))
  if (segmentLabel.value) lines.push(['Segment', segmentLabel.value].map(q).join(','))
  if (horizonLabel.value) lines.push(['Horizon', horizonLabel.value].map(q).join(','))
  if (basisLabel.value) lines.push(['Basis', basisLabel.value].map(q).join(','))
  lines.push(['Units', unitLabel.value].map(q).join(','))
  lines.push('')
  const header = ['From \\ To', ...endStages.value.map(stageName), 'Row total', `PD to ${defaultName.value} %`,
    ...endStages.value.map((e) => `${stageName(e)} share of row %`)]
  lines.push(header.map(q).join(','))
  for (const s of startStages.value) {
    lines.push([
      `From ${stageName(s)}`,
      ...endStages.value.map((e) => raw(cell(s, e))),
      raw(rowTotal(s)),
      Number(pdPercentages.value?.[s.category_name] ?? 0).toFixed(2),
      ...endStages.value.map((e) => (rowShare(s, e) * 100).toFixed(2)),
    ].map(q).join(','))
  }
  lines.push(['Total at end', ...endStages.value.map((e) => raw(colTotal(e))), raw(grandStart.value), ''].map(q).join(','))
  const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' })
  const a = document.createElement('a')
  a.href = URL.createObjectURL(blob)
  const tag = String(startPeriod.value || '').substring(0, 7) + '_to_' + String(endPeriod.value || '').substring(0, 7)
  a.download = `${props.type === 'cumulative' ? 'cumulative-' : ''}transition-matrix-${props.transitionMatrix?.id}_${tag}.csv`
  document.body.appendChild(a)
  a.click()
  a.remove()
  setTimeout(() => URL.revokeObjectURL(a.href), 1000)
}

// ---- Edit -----------------------------------------------------------------
async function submit() {
  const flattened = []

  for (const [start, ends] of Object.entries(matrix.value)) {
    for (const [end, c] of Object.entries(ends)) {
      flattened.push({
        start_stage: start,
        end_stage: end,
        [valueKey.value]: c[valueKey.value] ?? 0,
      })
    }
  }

  const updateUrl =
    props.type === 'cumulative'
      ? `/transition-matrix-cummulative/${props.transitionMatrix.id}/update-data`
      : `/transition-matrix/${props.transitionMatrix.id}/update-data`

  saving.value = true
  try {
    await axios.post(updateUrl, { matrix: flattened })
    await load()
    notice('Matrix saved', 'The new figures are stored. The matrix above now shows them as saved.')
  } catch (error) {
    console.error('Submission failed:', error.response?.data || error.message)
    notice('The matrix was not saved', error.response?.data?.message || 'The server refused the update. Check the figures and try again.', 'danger')
  } finally {
    saving.value = false
  }
}
</script>
