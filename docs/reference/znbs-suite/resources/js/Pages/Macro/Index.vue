<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import ZnbsPageHeader from '@/Components/ZnbsPageHeader.vue'
import IconAction from '@/Components/IconAction.vue'
import AppModal from '@/Components/AppModal.vue'
import ImportGuide from '@/Components/ImportGuide.vue'
import { num } from '@/format'

// ── Types ─────────────────────────────────────────────────────────────────────

interface MacroVariable {
  id: number
  code: string
  name: string
  category: string
  unit: string
  frequency: string
  source: string | null
  description: string | null
  is_active: boolean
  icaap_selected: boolean
  sort_order: number
  shock_direction: string
  default_mild_shock: string | null
  default_severe_shock: string | null
  shock_unit: string
  latest_observation: MacroObservation | null
}

interface MacroObservation {
  id: number
  macro_variable_id: number
  period_date: string
  period_label: string
  period_type: string
  value: string
  value_stressed_mild: string | null
  value_stressed_severe: string | null
  source: string | null
  notes: string | null
  created_at: string
}

// ── Props ─────────────────────────────────────────────────────────────────────

interface Table9Row {
  variable_code: string
  variable_name: string
  category: string
  unit: string | null
  base_value: number | null
  mild_path: number | null
  severe_path: number | null
  value_type: string | null
  period_label: string | null
  approval_status: string
  used_by_scenarios: string
  linked: boolean
}
interface Table9 {
  as_of: string | null
  basis: string
  variable_count: number
  approved_count: number
  missing_count: number
  columns: string[]
  rows: Table9Row[]
}

const props = defineProps<{
  variables: MacroVariable[]
  observations: Record<number, MacroObservation[]>
  categories: string[]
  frequencies: Record<string, string>
  shockUnits: Record<string, string>
  table9?: Table9
  can: { manage: boolean }
}>()

// ── Tabs ──────────────────────────────────────────────────────────────────────

const activeTab = ref<'dashboard' | 'variables' | 'data' | 'import' | 'table9'>('dashboard')

// ── Dashboard ─────────────────────────────────────────────────────────────────

// Dashboard category filter: a multi-select box. All categories shown by default;
// the user ticks the ones they want to see.
const selectedCategories = ref<string[]>([...props.categories])
const categoryBoxOpen = ref(false)

const activeVariables = computed(() =>
  props.variables.filter(v => v.is_active)
)

// ── Variables-tab scope ─────────────────────────────────────────────────────
// Default the Variables list to the governed ICAAP macro house-view
// (icaap_selected — the same flag that drives the ICAAP macro tables/Table 9),
// with a "View all" toggle to reveal every tracked variable. Client-side filter,
// mirroring the scenario pickers. Falls back to All when nothing is flagged yet
// so the table is never empty out of the box.
type MacroScope = 'icaap' | 'all'
const macroScope = ref<MacroScope>('icaap')
const icaapVariableCount = computed(() => props.variables.filter(v => v.icaap_selected).length)
if (macroScope.value === 'icaap' && icaapVariableCount.value === 0) macroScope.value = 'all'
const scopedVariables = computed(() => macroScope.value === 'icaap' ? props.variables.filter(v => v.icaap_selected) : props.variables)

const categorisedVariables = computed(() => {
  const vars = activeVariables.value.filter(v => selectedCategories.value.includes(v.category))

  const groups: Record<string, MacroVariable[]> = {}
  for (const v of vars) {
    if (!groups[v.category]) groups[v.category] = []
    groups[v.category].push(v)
  }
  return groups
})

const allCategoriesSelected = computed(() => selectedCategories.value.length === props.categories.length)
function toggleCategory(cat: string) {
  const i = selectedCategories.value.indexOf(cat)
  if (i >= 0) selectedCategories.value.splice(i, 1)
  else selectedCategories.value.push(cat)
}
function selectAllCategories() { selectedCategories.value = [...props.categories] }
function clearCategories() { selectedCategories.value = [] }

// Each category header gets its own vibrant colour. The colour is hashed from the
// category NAME (stable, same colour every render) so nothing is hardcoded per
// category - it works for whatever categories the backend returns.
const CATEGORY_PALETTE = [
  'text-emerald-400', 'text-sky-400', 'text-amber-400', 'text-rose-400',
  'text-violet-400', 'text-cyan-400', 'text-orange-400', 'text-pink-400',
  'text-lime-400', 'text-indigo-400', 'text-teal-400', 'text-fuchsia-400',
]
function categoryColor(cat: string): string {
  let h = 0
  for (let i = 0; i < cat.length; i++) h = (h * 31 + cat.charCodeAt(i)) >>> 0
  return CATEGORY_PALETTE[h % CATEGORY_PALETTE.length]
}

function latestValue(v: MacroVariable): string {
  const obs = v.latest_observation
  if (!obs) return '-'
  return `${parseFloat(obs.value).toLocaleString()} ${v.unit}`
}

function latestLabel(v: MacroVariable): string {
  return v.latest_observation?.period_label ?? '-'
}

function shockDisplay(v: MacroVariable, level: 'mild' | 'severe'): string {
  const val = level === 'mild' ? v.default_mild_shock : v.default_severe_shock
  if (val === null || val === undefined) return '-'
  const sign = parseFloat(val) > 0 ? '+' : ''
  const unit = v.shock_unit === 'pp' ? 'pp' : v.shock_unit === 'pct' ? '%' : ''
  return `${sign}${parseFloat(val).toFixed(1)}${unit}`
}

function shockColor(v: MacroVariable, level: 'mild' | 'severe'): string {
  const val = parseFloat(level === 'mild' ? v.default_mild_shock ?? '0' : v.default_severe_shock ?? '0')
  if (v.shock_direction === 'down') return level === 'mild' ? 'text-amber-400' : 'text-red-400'
  if (v.shock_direction === 'up')   return level === 'mild' ? 'text-amber-400' : 'text-red-400'
  return val >= 0 ? 'text-amber-400' : 'text-red-400'
}

// ── Variable CRUD ─────────────────────────────────────────────────────────────

const showVarForm = ref(false)
const editingVar = ref<MacroVariable | null>(null)

const varForm = useForm({
  code: '',
  name: '',
  category: '',
  unit: '%',
  frequency: 'quarterly',
  source: '',
  description: '',
  shock_direction: 'both',
  default_mild_shock: '' as number | string,
  default_severe_shock: '' as number | string,
  shock_unit: 'pp',
  sort_order: 0,
  wb_code: '' as string,
  imf_code: '' as string,
})

// ── View modal (eye icon) ─────────────────────────────────────────────────────
const showViewModal = ref(false)
const viewVariable = ref<MacroVariable | null>(null)
const viewObservations = ref<MacroObservation[]>([])
const viewSummary = ref<Record<string, any>>({})
const viewLoading = ref(false)
const viewFilterType = ref<'all' | 'actual' | 'forecast'>('all')
const viewFilterYearFrom = ref<string>('')
const viewFilterYearTo = ref<string>('')

function openView(v: MacroVariable) {
  viewVariable.value = v
  viewObservations.value = []
  viewSummary.value = {}
  viewFilterType.value = 'all'
  viewFilterYearFrom.value = ''
  viewFilterYearTo.value = ''
  showViewModal.value = true
  viewLoading.value = true
  fetch(route('macro.variables.show', v.id), { headers: { Accept: 'application/json' } })
    .then(r => r.json())
    .then(d => {
      viewObservations.value = d.observations ?? []
      viewSummary.value = d.summary ?? {}
    })
    .finally(() => { viewLoading.value = false })
}

const viewFilteredObs = computed(() => {
  return viewObservations.value.filter(o => {
    if (viewFilterType.value !== 'all' && (o as any).value_type !== viewFilterType.value) return false
    const yr = parseInt(o.period_label?.replace(/\D/g, '').slice(0, 4) ?? '0', 10)
    if (viewFilterYearFrom.value && yr < parseInt(viewFilterYearFrom.value, 10)) return false
    if (viewFilterYearTo.value   && yr > parseInt(viewFilterYearTo.value, 10))   return false
    return true
  })
})

// ── Country + year-range for WB/IMF imports ─────────────────────────────────────
// Blank = the service defaults (ZMB, full history), so omitting them is a no-op.
const importCountry = ref('')
const importYearFrom = ref<number | null>(null)
const importYearTo = ref<number | null>(null)
function importQuery(): string {
  const p = new URLSearchParams()
  if (importCountry.value.trim()) p.set('country', importCountry.value.trim().toUpperCase())
  if (importYearFrom.value) p.set('year_from', String(importYearFrom.value))
  if (importYearTo.value) p.set('year_to', String(importYearTo.value))
  const s = p.toString()
  return s ? `?${s}` : ''
}
function importPayload(): Record<string, string> {
  const o: Record<string, string> = {}
  if (importCountry.value.trim()) o.country = importCountry.value.trim().toUpperCase()
  if (importYearFrom.value) o.year_from = String(importYearFrom.value)
  if (importYearTo.value) o.year_to = String(importYearTo.value)
  return o
}

// ── World Bank fetch ──────────────────────────────────────────────────────────
const showWbModal = ref(false)
const wbVariable = ref<MacroVariable | null>(null)
const wbPreview = ref<any>(null)
const wbLoading = ref(false)
const wbError = ref<string | null>(null)
const wbCommitting = ref(false)

function wbFetch() {
  if (!wbVariable.value) return
  wbPreview.value = null
  wbError.value = null
  wbLoading.value = true
  fetch(route('macro.variables.preview-worldbank', wbVariable.value.id) + importQuery(), { headers: { Accept: 'application/json' } })
    .then(async r => {
      const body = await r.json()
      if (!r.ok || body.error) { wbError.value = body.error || `HTTP ${r.status}` }
      else { wbPreview.value = body }
    })
    .catch(e => { wbError.value = e?.message ?? 'Network error' })
    .finally(() => { wbLoading.value = false })
}

function openWb(v: MacroVariable) {
  wbVariable.value = v
  showWbModal.value = true
  wbFetch()
}

function commitWb() {
  if (!wbVariable.value) return
  wbCommitting.value = true
  router.post(route('macro.variables.import-worldbank', wbVariable.value.id), importPayload(), {
    onFinish: () => { wbCommitting.value = false; showWbModal.value = false },
  })
}

// ── IMF WEO upload + preview ──────────────────────────────────────────────────
const showImfModal = ref(false)
const imfVariable = ref<MacroVariable | null>(null)
const imfFile = ref<File | null>(null)
const imfPreview = ref<any>(null)
const imfLoading = ref(false)
const imfError = ref<string | null>(null)
const imfCommitting = ref(false)

function openImf(v: MacroVariable) {
  imfVariable.value = v
  imfFile.value = null
  imfPreview.value = null
  imfError.value = null
  showImfModal.value = true
}

function imfPreviewFetch() {
  if (!imfFile.value || !imfVariable.value) return
  imfPreview.value = null
  imfError.value = null
  imfLoading.value = true
  const fd = new FormData()
  fd.append('weo_file', imfFile.value)
  for (const [k, v] of Object.entries(importPayload())) fd.append(k, v)
  fetch(route('macro.variables.preview-imf-weo', imfVariable.value.id), {
    method: 'POST', body: fd,
    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': (document.querySelector('meta[name=csrf-token]') as HTMLMetaElement)?.content ?? '' },
  })
    .then(async r => {
      const body = await r.json()
      if (!r.ok || body.error) { imfError.value = body.error || `HTTP ${r.status}` }
      else { imfPreview.value = body }
    })
    .catch(e => { imfError.value = e?.message ?? 'Network error' })
    .finally(() => { imfLoading.value = false })
}

function onImfFile(e: Event) {
  imfFile.value = (e.target as HTMLInputElement).files?.[0] ?? null
  imfPreview.value = null
  imfError.value = null
  imfPreviewFetch()
}

function commitImf() {
  if (!imfVariable.value || !imfFile.value) return
  imfCommitting.value = true
  router.post(route('macro.variables.import-imf-weo', imfVariable.value.id),
    { weo_file: imfFile.value, ...importPayload() },
    { forceFormData: true, onFinish: () => { imfCommitting.value = false; showImfModal.value = false } }
  )
}

function openAddVar() {
  editingVar.value = null
  varForm.reset()
  varForm.unit = '%'
  varForm.frequency = 'quarterly'
  varForm.shock_direction = 'both'
  varForm.shock_unit = 'pp'
  varForm.wb_code = ''
  varForm.imf_code = ''
  showVarForm.value = true
}

function openEditVar(v: MacroVariable) {
  editingVar.value = v
  varForm.code = v.code
  varForm.name = v.name
  varForm.category = v.category
  varForm.unit = v.unit
  varForm.frequency = v.frequency
  varForm.source = v.source ?? ''
  varForm.description = v.description ?? ''
  varForm.shock_direction = v.shock_direction
  varForm.default_mild_shock = v.default_mild_shock ?? ''
  varForm.default_severe_shock = v.default_severe_shock ?? ''
  varForm.shock_unit = v.shock_unit
  varForm.sort_order = v.sort_order
  const ext = (v as any).external_codes ?? {}
  varForm.wb_code = ext.world_bank ?? ''
  varForm.imf_code = ext.imf_weo ?? ''
  showVarForm.value = true
}

function submitVar() {
  if (editingVar.value) {
    varForm.put(route('macro.variables.update', editingVar.value.id), {
      onSuccess: () => { showVarForm.value = false; varForm.reset() },
    })
  } else {
    varForm.post(route('macro.variables.store'), {
      onSuccess: () => { showVarForm.value = false; varForm.reset() },
    })
  }
}

function toggleVar(v: MacroVariable) {
  router.patch(route('macro.variables.toggle', v.id))
}
// Mark/unmark this indicator as part of the ICAAP macro house-view (icaap_selected):
// the governed set whose latest values drive the ICAAP macro tables.
function toggleIcaap(v: MacroVariable) {
  router.patch(route('macro.variables.toggle-icaap', v.id), {}, { preserveScroll: true })
}
function destroyVar(v: MacroVariable) {
  if (!confirm(`Delete macro variable "${v.name}" (${v.code})?\n\nThis removes its observations and mild/severe shock definition. It is blocked if an approved scenario uses it as a shock.`)) return
  router.delete(route('macro.variables.destroy', v.id), { preserveScroll: true })
}

// ── Observation Entry ─────────────────────────────────────────────────────────

const selectedVarId = ref<number | ''>('')
const showObsForm = ref(false)

const selectedVarObs = computed(() =>
  selectedVarId.value ? (props.observations[selectedVarId.value] ?? []).slice().reverse() : []
)
const selectedVar = computed(() =>
  props.variables.find(v => v.id === selectedVarId.value) ?? null
)

const obsForm = useForm({
  macro_variable_id: '' as number | string,
  period_date: '',
  period_label: '',
  period_type: 'quarterly',
  value_type: 'actual' as 'actual' | 'estimate' | 'forecast',
  value: '' as number | string,
  value_stressed_mild: '' as number | string,
  value_stressed_severe: '' as number | string,
  source: '',
  notes: '',
})

// A period_date in the future cannot be Actual - it must be a projection.
const obsIsFuture = computed(() => {
  if (!obsForm.period_date) return false
  const d = new Date(obsForm.period_date)
  const today = new Date(); today.setHours(0, 0, 0, 0)
  return d.getTime() > today.getTime()
})

watch(selectedVarId, (varId) => {
  obsForm.macro_variable_id = varId

  if (!varId) {
    showObsForm.value = false
    return
  }

  const variable = props.variables.find(v => v.id === varId)
  if (variable) {
    obsForm.period_type = variable.frequency
  }
})

function openObsForm(varId: number) {
  selectedVarId.value = varId
  showObsForm.value = true
  activeTab.value = 'data'
}

function submitObs() {
  obsForm.post(route('macro.observations.store'), {
    onSuccess: () => {
      obsForm.reset()
      obsForm.macro_variable_id = selectedVarId.value
      showObsForm.value = false
    },
  })
}

function deleteObs(id: number) {
  if (confirm('Delete this observation?')) {
    router.delete(route('macro.observations.destroy', id))
  }
}

// Preview of the period label the SERVER will derive from date + frequency.
// (The backend re-derives on save via App\Support\MacroPeriod::deriveLabel, so
//  this is display-only and can never drift into the stored value.)
function refreshPeriodLabel() {
  if (!obsForm.period_date) { obsForm.period_label = ''; return }
  const d = new Date(obsForm.period_date)
  const yr = d.getFullYear()
  const mo = d.getMonth() // 0-indexed

  if (obsForm.period_type === 'annual') {
    obsForm.period_label = `${yr}`
  } else if (obsForm.period_type === 'quarterly') {
    const q = Math.floor(mo / 3) + 1
    obsForm.period_label = `Q${q} ${yr}`
  } else {
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']
    obsForm.period_label = `${months[mo]} ${yr}`
  }
}

// On any date or frequency change: refresh the label preview and, for a future
// period, nudge an Actual classification to Forecast (a projection can't be Actual).
function onPeriodDateChange() {
  refreshPeriodLabel()
  if (obsIsFuture.value && obsForm.value_type === 'actual') {
    obsForm.value_type = 'forecast'
  }
}

// ── Import ────────────────────────────────────────────────────────────────────

const importForm = useForm({ csv_file: null as File | null })
function handleFile(e: Event) {
  const input = e.target as HTMLInputElement
  if (input.files?.[0]) importForm.csv_file = input.files[0]
}
function submitImport() {
  importForm.post(route('macro.observations.import'), {
    forceFormData: true,
    onSuccess: () => importForm.reset(),
  })
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function directionBadge(dir: string) {
  if (dir === 'up')   return 'badge-red'
  if (dir === 'down') return 'badge-blue'
  return 'badge-amber'
}

function totalObs(varId: number): number {
  return props.observations[varId]?.length ?? 0
}
</script>

<template>
  <AppLayout title="Macro Statistics">
    <div class="space-y-6">

      <!-- ── Header ── -->
      <ZnbsPageHeader eyebrow="Data Foundation" title="Macro Statistics Manager"
        :subtitle="`${variables.filter(v => v.is_active).length} active variables · ${Object.values(observations).flat().length} observations`">
        <template #actions>
          <a :href="route('macro.export.data')" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-500">↓ Export All Data</a>
        </template>
      </ZnbsPageHeader>

      <!-- Purpose banner: what this repository is and how it feeds the suite. -->
      <div data-dismissible class="flex items-start gap-2.5 rounded-xl border border-slate-700 bg-slate-800/40 px-4 py-2.5 text-sm text-slate-300">
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-sky-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01" stroke-linecap="round"/></svg>
        <span><b class="text-white">Macro-economic data repository.</b> The approved observations here are the governed source feeding scenario shocks, forecasts, transmission and ICAAP stress outputs. Capture actuals and forecasts on the tabs below, then approve them to release them to the engines.</span>
      </div>

      <!-- Table 9 - ICAAP Macro Assumptions now lives in its own tab (below). -->

      <div class="flex flex-wrap gap-1 border-b border-white/10 pb-px">
        <button v-for="tab in (['dashboard', 'variables', 'data', 'table9', 'import'] as const)" :key="tab"
          @click="activeTab = tab"
          :class="['engine-tab', activeTab === tab ? 'engine-tab-active' : '']">
          {{ tab === 'dashboard' ? 'Dashboard' : tab === 'variables' ? 'Variables' : tab === 'data' ? 'Data Entry' : tab === 'table9' ? 'Table 9 · Assumptions' : 'Import / Export' }}
        </button>
      </div>

      <!-- ══════════════════════════ DASHBOARD ══════════════════════════════ -->
      <!-- ══ TAB: Table 9 - ICAAP Macro Assumptions (governed paths + approval lineage) ══ -->
      <div v-if="activeTab === 'table9'" class="space-y-4">
        <div v-if="table9" class="rounded-xl border border-slate-800 bg-slate-900/50">
          <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800 px-4 py-2.5">
            <div>
              <div class="text-sm font-semibold text-white">Table 9 · ICAAP Macro Assumptions</div>
              <div class="text-[11px] text-slate-400">
                {{ table9.approved_count }} approved of {{ table9.variable_count }} scenario-referenced variables<span v-if="table9.as_of"> · as at {{ String(table9.as_of).slice(0, 10) }}</span>
                <span v-if="table9.missing_count > 0" class="text-amber-300"> · {{ table9.missing_count }} missing an approved observation</span>
              </div>
            </div>
            <a :href="route('macro.assumptions.export')"
              class="rounded-lg border border-emerald-700/50 bg-emerald-600/10 px-3 py-1.5 text-xs font-semibold text-emerald-300 hover:bg-emerald-600/20">↓ Export Table 9 (CSV)</a>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full text-[11px]">
              <thead class="bg-slate-800/60 text-slate-300">
                <tr>
                  <th class="px-2.5 py-1.5 text-left font-semibold">Variable</th>
                  <th class="px-2.5 py-1.5 text-left font-semibold">Category</th>
                  <th class="px-2.5 py-1.5 text-right font-semibold">Base</th>
                  <th class="px-2.5 py-1.5 text-right font-semibold">Mild</th>
                  <th class="px-2.5 py-1.5 text-right font-semibold">Severe</th>
                  <th class="px-2.5 py-1.5 text-left font-semibold">Type</th>
                  <th class="px-2.5 py-1.5 text-left font-semibold">Status</th>
                  <th class="px-2.5 py-1.5 text-left font-semibold">Used by scenarios</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-800/60">
                <tr v-for="row in table9.rows" :key="row.variable_code" class="hover:bg-slate-800/30">
                  <td class="px-2.5 py-1 text-slate-200"><span class="font-mono text-znbs-gold-light">{{ row.variable_code }}</span> <span class="text-slate-400">{{ row.variable_name }}</span></td>
                  <td class="px-2.5 py-1 text-slate-300">{{ row.category }}</td>
                  <td class="px-2.5 py-1 text-right text-slate-200">{{ row.base_value ?? '-' }}</td>
                  <td class="px-2.5 py-1 text-right text-amber-200/90">{{ row.mild_path ?? '-' }}</td>
                  <td class="px-2.5 py-1 text-right text-rose-300/90">{{ row.severe_path ?? '-' }}</td>
                  <td class="px-2.5 py-1 text-slate-400">{{ row.value_type ?? '-' }}</td>
                  <td class="px-2.5 py-1">
                    <span :class="['rounded px-1.5 py-0.5 text-[10px] font-semibold', row.linked ? 'bg-emerald-500/15 text-emerald-300' : 'bg-rose-500/15 text-rose-300']">{{ row.approval_status }}</span>
                  </td>
                  <td class="px-2.5 py-1 text-slate-400">{{ row.used_by_scenarios }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div v-else class="rounded-xl border border-dashed border-slate-700 bg-slate-900/40 px-5 py-10 text-center text-sm text-slate-400">
          No scenario-referenced macro variables yet. Approve macro observations and reference them from an approved scenario to populate Table 9.
        </div>
      </div>

      <div v-if="activeTab === 'dashboard'" class="space-y-5">

        <!-- Category filter box (multi-select): pick which categories to show -->
        <div class="flex items-center gap-3">
          <div class="relative inline-block">
            <button type="button" @click="categoryBoxOpen = !categoryBoxOpen"
              class="flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-sm font-medium text-slate-200 transition-colors hover:border-emerald-500">
              <svg class="h-4 w-4 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
              Filter categories
              <span class="rounded-full bg-emerald-600 px-2 py-0.5 text-xs font-semibold text-white">{{ allCategoriesSelected ? 'All' : `${selectedCategories.length}/${categories.length}` }}</span>
              <svg class="h-3 w-3 text-slate-400 transition-transform" :class="categoryBoxOpen ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <div v-if="categoryBoxOpen" class="absolute left-0 z-30 mt-2 w-64 rounded-xl border border-slate-700 bg-slate-900 p-2 shadow-[0_18px_50px_rgba(0,0,0,0.5)]">
              <div class="flex items-center justify-between px-2 py-1 text-xs">
                <button type="button" @click="selectAllCategories" class="font-semibold text-emerald-400 hover:text-emerald-300">Select all</button>
                <button type="button" @click="clearCategories" class="font-semibold text-slate-400 hover:text-slate-200">Clear</button>
              </div>
              <div class="mt-1 max-h-72 space-y-0.5 overflow-y-auto">
                <label v-for="cat in categories" :key="cat"
                  class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm text-slate-200 hover:bg-slate-800">
                  <input type="checkbox" :checked="selectedCategories.includes(cat)" @change="toggleCategory(cat)"
                    class="h-4 w-4 rounded border-slate-600 bg-slate-800 text-emerald-500 focus:ring-emerald-500 focus:ring-offset-0" />
                  {{ cat }}
                </label>
              </div>
            </div>
          </div>
          <span class="text-xs text-slate-500">{{ Object.keys(categorisedVariables).length }} categor{{ Object.keys(categorisedVariables).length === 1 ? 'y' : 'ies' }} shown</span>
        </div>

        <!-- Variable cards grouped by category -->
        <div v-for="(vars, cat) in categorisedVariables" :key="cat" class="space-y-2">
          <h2 :class="['text-2xl font-extrabold tracking-tight px-1', categoryColor(cat)]">{{ cat }}</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
            <div v-for="v in vars" :key="v.id"
              class="bg-slate-800/60 border border-slate-700 rounded-xl p-4 flex flex-col gap-3 hover:border-slate-500 transition-colors">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="text-xs font-mono text-emerald-400">{{ v.code }}</p>
                  <p class="text-sm font-medium text-slate-200 mt-0.5 leading-tight">{{ v.name }}</p>
                </div>
                <span class="text-xs text-slate-500 shrink-0">{{ frequencies[v.frequency] ?? v.frequency }}</span>
              </div>

              <!-- Latest value -->
              <div class="bg-slate-900/60 rounded-lg p-3">
                <p class="text-xs text-slate-500">Latest ({{ latestLabel(v) }})</p>
                <p class="text-lg font-bold text-white mt-0.5">{{ latestValue(v) }}</p>
              </div>

              <!-- Stress shocks -->
              <div class="grid grid-cols-2 gap-2 text-xs">
                <div class="bg-amber-950/30 border border-amber-800/30 rounded-lg p-2 text-center">
                  <p class="text-slate-500">Mild shock</p>
                  <p :class="shockColor(v, 'mild')" class="font-bold">{{ shockDisplay(v, 'mild') }}</p>
                </div>
                <div class="bg-red-950/30 border border-red-800/30 rounded-lg p-2 text-center">
                  <p class="text-slate-500">Severe shock</p>
                  <p :class="shockColor(v, 'severe')" class="font-bold">{{ shockDisplay(v, 'severe') }}</p>
                </div>
              </div>

              <!-- Footer -->
              <div class="flex items-center justify-between text-xs text-slate-500">
                <span>{{ totalObs(v.id) }} obs · {{ v.source ?? 'No source' }}</span>
                <button v-if="can.manage" @click="openObsForm(v.id)"
                  class="text-emerald-400 hover:text-emerald-300 transition-colors font-medium">
                  + Add Data
                </button>
              </div>
            </div>
          </div>
        </div>

        <p v-if="!Object.keys(categorisedVariables).length" class="text-slate-500 text-sm text-center py-12">
          {{ selectedCategories.length ? 'No active variables in the selected categories.' : 'No categories selected - use the Filter categories box to choose what to display.' }}
        </p>
      </div>

      <!-- ══════════════════════════ VARIABLES TAB ══════════════════════════ -->
      <div v-if="activeTab === 'variables'" class="space-y-4">

        <div class="flex flex-wrap items-center justify-between gap-2">
          <div class="flex items-center gap-2">
            <div class="inline-flex rounded-full border border-emerald-600/40 bg-slate-900 p-0.5 text-[11px] font-semibold" title="ICAAP macro = the governed icaap_selected house-view (the set that drives the ICAAP macro tables). All = every tracked variable.">
              <button v-for="s in (['icaap','all'] as const)" :key="s" type="button" @click="macroScope = s" class="rounded-full px-3 py-1 transition" :class="macroScope === s ? 'bg-emerald-500 text-slate-950' : 'text-slate-300 hover:text-white'">{{ s === 'icaap' ? 'ICAAP macro' : 'All variables' }}</button>
            </div>
            <span class="text-[11px] text-slate-500">{{ macroScope === 'icaap' ? `${icaapVariableCount} in the ICAAP house-view` : `${variables.length} variable(s)` }}</span>
          </div>
          <button v-if="can.manage" @click="openAddVar" class="btn-primary text-sm">+ Add Variable</button>
        </div>

        <!-- Variable Add/Edit form: centred modal overlay (not pinned to the page
             top) so it appears in view no matter where the Edit trigger was clicked. -->
        <AppModal :open="showVarForm" :title="editingVar ? 'Edit Variable' : 'New Variable'" tone="brand" size="lg" @close="showVarForm = false">
          <form @submit.prevent="submitVar" class="space-y-5">
          <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-4">
            <div>
              <label class="form-label">Code * <span class="text-slate-500 text-xs">(UPPER_SNAKE)</span></label>
              <input v-model="varForm.code" :disabled="!!editingVar" class="input-field uppercase"
                placeholder="e.g. GDP_GROWTH" />
              <p v-if="varForm.errors.code" class="form-error">{{ varForm.errors.code }}</p>
            </div>
            <div class="md:col-span-2">
              <label class="form-label">Name *</label>
              <input v-model="varForm.name" class="input-field" placeholder="e.g. Real GDP Growth Rate" />
              <p v-if="varForm.errors.name" class="form-error">{{ varForm.errors.name }}</p>
            </div>
            <div>
              <label class="form-label">Category *</label>
              <input v-model="varForm.category" list="category-list" class="input-field"
                placeholder="e.g. Growth" />
              <datalist id="category-list">
                <option v-for="cat in categories" :key="cat" :value="cat" />
              </datalist>
            </div>
            <div>
              <label class="form-label">Unit *</label>
              <input v-model="varForm.unit" class="input-field" placeholder="%, ZMW/USD, bps" />
            </div>
            <div>
              <label class="form-label">Frequency *</label>
              <select v-model="varForm.frequency" class="input-field">
                <option v-for="(label, key) in frequencies" :key="key" :value="key">{{ label }}</option>
              </select>
            </div>
            <div>
              <label class="form-label">Source</label>
              <input v-model="varForm.source" class="input-field" placeholder="e.g. BOZ, ZNBS" />
            </div>
            <div>
              <label class="form-label">Sort Order</label>
              <input v-model.number="varForm.sort_order" type="number" min="0" class="input-field" />
            </div>
            <div class="md:col-span-4">
              <label class="form-label">Description</label>
              <textarea v-model="varForm.description" rows="2" class="input-field" />
            </div>
            <div>
              <label class="form-label">World Bank code</label>
              <input v-model="varForm.wb_code" class="input-field font-mono" placeholder="e.g. NY.GDP.MKTP.KD.ZG" />
              <p class="text-[10px] text-slate-500 mt-1">Indicator code at api.worldbank.org</p>
            </div>
            <div>
              <label class="form-label">IMF WEO code</label>
              <input v-model="varForm.imf_code" class="input-field font-mono" placeholder="e.g. NGDP_RPCH" />
              <p class="text-[10px] text-slate-500 mt-1">Subject code in the WEO database</p>
            </div>
          </div>

          <!-- Shock calibration -->
          <div class="border-t border-slate-700 pt-4">
            <p class="text-xs font-semibold text-slate-400 mb-3">Default Stress Shocks</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-x-6 gap-y-4">
              <div>
                <label class="form-label">Shock Direction</label>
                <select v-model="varForm.shock_direction" class="input-field">
                  <option value="up">Up (adverse = higher)</option>
                  <option value="down">Down (adverse = lower)</option>
                  <option value="both">Both directions</option>
                </select>
              </div>
              <div>
                <label class="form-label">Shock Unit</label>
                <select v-model="varForm.shock_unit" class="input-field">
                  <option v-for="(label, key) in shockUnits" :key="key" :value="key">{{ label }}</option>
                </select>
              </div>
              <div>
                <label class="form-label">Mild Shock</label>
                <input v-model="varForm.default_mild_shock" type="number" step="any" class="input-field"
                  placeholder="e.g. -2.0 or +3.0" />
              </div>
              <div>
                <label class="form-label">Severe Shock</label>
                <input v-model="varForm.default_severe_shock" type="number" step="any" class="input-field"
                  placeholder="e.g. -5.0 or +8.0" />
              </div>
            </div>
          </div>

          </form>
          <template #footer>
            <button type="button" @click="showVarForm = false" class="btn-secondary py-1.5 text-xs">Cancel</button>
            <button type="button" @click="submitVar" :disabled="varForm.processing" class="btn-primary py-1.5 text-xs">
              {{ varForm.processing ? 'Saving…' : (editingVar ? 'Update Variable' : 'Add Variable') }}
            </button>
          </template>
        </AppModal>

        <!-- Variables table -->
        <div class="bg-slate-900 border border-slate-700 rounded-xl overflow-hidden">
          <table class="w-full text-sm">
            <thead class="bg-slate-800">
              <tr>
                <th class="px-4 py-2.5 text-left text-slate-400 font-medium">Code</th>
                <th class="px-4 py-2.5 text-left text-slate-400 font-medium">Name</th>
                <th class="px-4 py-2.5 text-left text-slate-400 font-medium">Category</th>
                <th class="px-4 py-2.5 text-left text-slate-400 font-medium">Unit</th>
                <th class="px-4 py-2.5 text-left text-slate-400 font-medium">Freq</th>
                <th class="px-4 py-2.5 text-left text-slate-400 font-medium">Mild</th>
                <th class="px-4 py-2.5 text-left text-slate-400 font-medium">Severe</th>
                <th class="px-4 py-2.5 text-center text-slate-400 font-medium">Obs</th>
                <th class="px-4 py-2.5 text-center text-slate-400 font-medium">Status</th>
                <th class="px-4 py-2.5"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="v in scopedVariables" :key="v.id"
                class="border-t border-slate-800 hover:bg-slate-800/40"
                :class="!v.is_active ? 'opacity-50' : ''">
                <td class="px-4 py-2.5 font-mono text-emerald-400 text-xs">{{ v.code }}</td>
                <td class="px-4 py-2.5 text-slate-200">{{ v.name }}</td>
                <td class="px-4 py-2.5 text-slate-400 text-xs">{{ v.category }}</td>
                <td class="px-4 py-2.5 text-slate-400 text-xs">{{ v.unit }}</td>
                <td class="px-4 py-2.5 text-slate-500 text-xs capitalize">{{ v.frequency }}</td>
                <td class="px-4 py-2.5 text-xs text-amber-400 font-mono">{{ shockDisplay(v, 'mild') }}</td>
                <td class="px-4 py-2.5 text-xs text-red-400 font-mono">{{ shockDisplay(v, 'severe') }}</td>
                <td class="px-4 py-2.5 text-center text-slate-400 text-xs">{{ totalObs(v.id) }}</td>
                <td class="px-4 py-2.5 text-center">
                  <div class="flex flex-col items-center gap-1">
                    <span :class="v.is_active ? 'badge-green' : 'badge-red'" class="text-xs">
                      {{ v.is_active ? 'Active' : 'Inactive' }}
                    </span>
                    <span v-if="v.icaap_selected" class="rounded-full border border-emerald-600/50 bg-emerald-500/15 px-2 py-0.5 text-[9px] font-semibold text-emerald-300" title="In the ICAAP macro house-view">★ ICAAP</span>
                  </div>
                </td>
                <td class="px-4 py-2.5 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <IconAction variant="view" title="View observations" @click="openView(v)" />
                    <button v-if="can.manage" @click="openWb(v)" title="Fetch from World Bank"
                      class="text-xs text-slate-400 hover:text-emerald-400 transition-colors">WB</button>
                    <button v-if="can.manage" @click="openImf(v)" title="Import IMF WEO file"
                      class="text-xs text-slate-400 hover:text-emerald-400 transition-colors">IMF</button>
                    <button v-if="can.manage" @click="toggleIcaap(v)" :title="v.icaap_selected ? 'In the ICAAP macro house-view - click to remove' : 'Add to the ICAAP macro house-view'"
                      class="text-xs transition-colors" :class="v.icaap_selected ? 'text-emerald-300 hover:text-emerald-200' : 'text-slate-500 hover:text-emerald-400'">
                      {{ v.icaap_selected ? '★' : '☆' }} ICAAP
                    </button>
                    <IconAction v-if="can.manage" variant="edit" title="Edit variable" @click="openEditVar(v)" />
                    <button v-if="can.manage" @click="toggleVar(v)"
                      class="text-xs text-slate-400 hover:text-white transition-colors">
                      {{ v.is_active ? 'Off' : 'On' }}
                    </button>
                    <IconAction v-if="can.manage" variant="delete" title="Delete variable" @click="destroyVar(v)" />
                  </div>
                </td>
              </tr>
              <tr v-if="!scopedVariables.length">
                <td colspan="10" class="px-4 py-8 text-center text-slate-500">
                  <template v-if="macroScope === 'icaap' && variables.length">No variables in the ICAAP macro house-view yet. <button type="button" class="underline hover:text-emerald-300" @click="macroScope = 'all'">View all variables</button>.</template>
                  <template v-else>No variables defined.</template>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ══════════════════════════ DATA ENTRY TAB ══════════════════════════ -->
      <div v-if="activeTab === 'data'" class="space-y-4">

        <!-- Variable selector -->
        <div class="w-80">
          <label class="form-label">Select Variable</label>
          <select v-model="selectedVarId" class="input-field">
            <option value="">- choose a variable -</option>
            <optgroup v-for="cat in categories" :key="cat" :label="cat">
              <option v-for="v in variables.filter(v => v.category === cat)" :key="v.id" :value="v.id">
                {{ v.code }} · {{ v.name }}
              </option>
            </optgroup>
          </select>
        </div>

        <!-- Selected variable info banner -->
        <div v-if="selectedVar"
          class="bg-slate-800/40 border border-slate-700 rounded-xl px-4 py-3 flex flex-wrap items-center gap-6 text-sm">
          <div>
            <span class="text-slate-500 text-xs">Variable</span>
            <p class="text-white font-medium">{{ selectedVar.name }}</p>
          </div>
          <div>
            <span class="text-slate-500 text-xs">Unit</span>
            <p class="text-slate-300">{{ selectedVar.unit }}</p>
          </div>
          <div>
            <span class="text-slate-500 text-xs">Frequency</span>
            <p class="text-slate-300 capitalize">{{ selectedVar.frequency }}</p>
          </div>
          <div>
            <span class="text-slate-500 text-xs">Default mild shock</span>
            <p class="text-amber-400">{{ shockDisplay(selectedVar, 'mild') }}</p>
          </div>
          <div>
            <span class="text-slate-500 text-xs">Default severe shock</span>
            <p class="text-red-400">{{ shockDisplay(selectedVar, 'severe') }}</p>
          </div>
          <div class="ml-auto">
            <button v-if="can.manage" type="button" @click="showObsForm = !showObsForm" class="btn-primary text-sm whitespace-nowrap">
              {{ showObsForm ? '✕ Cancel' : '+ Add Observation' }}
            </button>
          </div>
        </div>

        <!-- Observation form -->
        <form v-if="showObsForm && selectedVarId" @submit.prevent="submitObs"
          class="bg-slate-800/60 border border-slate-700 rounded-xl p-5 space-y-4">
          <h3 class="text-sm font-semibold text-slate-300">New Observation</h3>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
              <label class="form-label">Period Date *</label>
              <input v-model="obsForm.period_date" type="date" class="input-field" @change="onPeriodDateChange" />
              <p v-if="obsForm.errors.period_date" class="form-error">{{ obsForm.errors.period_date }}</p>
            </div>
            <div>
              <label class="form-label">Frequency *</label>
              <select v-model="obsForm.period_type" class="input-field" @change="onPeriodDateChange">
                <option value="monthly">Monthly</option>
                <option value="quarterly">Quarterly</option>
                <option value="annual">Annual</option>
              </select>
              <p class="text-[10px] text-slate-500 mt-1">How often the series is observed (not history vs forecast).</p>
            </div>
            <div>
              <label class="form-label">Type *</label>
              <select v-model="obsForm.value_type" class="input-field">
                <option value="actual" :disabled="obsIsFuture">Actual</option>
                <option value="estimate">Estimate</option>
                <option value="forecast">Forecast</option>
              </select>
              <p v-if="obsIsFuture" class="text-[10px] text-amber-400 mt-1">Future period - must be Estimate or Forecast.</p>
              <p v-else class="text-[10px] text-slate-500 mt-1">Historical (Actual) vs projection (Estimate / Forecast).</p>
            </div>
            <div>
              <label class="form-label">Period Label (auto)</label>
              <input :value="obsForm.period_label || '(auto)'" readonly tabindex="-1"
                class="input-field opacity-70 cursor-not-allowed" title="Derived from Period Date + Frequency on save" />
              <p class="text-[10px] text-slate-500 mt-1">Derived from date + frequency.</p>
            </div>
            <div>
              <label class="form-label">Value *</label>
              <input v-model="obsForm.value" type="number" step="any" class="input-field" />
              <p v-if="obsForm.errors.value" class="form-error">{{ obsForm.errors.value }}</p>
            </div>
            <div>
              <label class="form-label">Mild Stressed Value</label>
              <input v-model="obsForm.value_stressed_mild" type="number" step="any" class="input-field" />
            </div>
            <div>
              <label class="form-label">Severe Stressed Value</label>
              <input v-model="obsForm.value_stressed_severe" type="number" step="any" class="input-field" />
            </div>
            <div>
              <label class="form-label">Source</label>
              <input v-model="obsForm.source" class="input-field" placeholder="BOZ, ZNBS…" />
            </div>
            <div class="md:col-span-2">
              <label class="form-label">Notes</label>
              <input v-model="obsForm.notes" class="input-field" />
            </div>
          </div>
          <div class="flex gap-3">
            <button type="submit" :disabled="obsForm.processing" class="btn-primary text-sm">
              {{ obsForm.processing ? 'Saving…' : 'Save Observation' }}
            </button>
            <button type="button" @click="showObsForm = false" class="btn-secondary text-sm">Cancel</button>
          </div>
        </form>

        <!-- Observations table -->
        <div v-if="selectedVarId" class="bg-slate-900 border border-slate-700 rounded-xl overflow-hidden">
          <div class="px-4 py-3 bg-slate-800 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-300">
              {{ selectedVar?.name }} · {{ selectedVarObs.length }} observations
            </h3>
          </div>
          <table class="w-full text-sm">
            <thead class="bg-slate-800/40">
              <tr>
                <th class="px-4 py-2 text-left text-slate-500 text-xs font-medium">Period</th>
                <th class="px-4 py-2 text-right text-slate-500 text-xs font-medium">Actual</th>
                <th class="px-4 py-2 text-right text-slate-500 text-xs font-medium">Mild Stressed</th>
                <th class="px-4 py-2 text-right text-slate-500 text-xs font-medium">Severe Stressed</th>
                <th class="px-4 py-2 text-left text-slate-500 text-xs font-medium">Source</th>
                <th class="px-4 py-2"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="obs in selectedVarObs" :key="obs.id"
                class="border-t border-slate-800 hover:bg-slate-800/30">
                <td class="px-4 py-2.5 text-slate-200 font-medium text-xs">{{ obs.period_label }}</td>
                <td class="px-4 py-2.5 text-right font-mono text-white text-xs">
                  {{ parseFloat(obs.value).toLocaleString() }}
                </td>
                <td class="px-4 py-2.5 text-right font-mono text-amber-400 text-xs">
                  {{ obs.value_stressed_mild ? parseFloat(obs.value_stressed_mild).toLocaleString() : '-' }}
                </td>
                <td class="px-4 py-2.5 text-right font-mono text-red-400 text-xs">
                  {{ obs.value_stressed_severe ? parseFloat(obs.value_stressed_severe).toLocaleString() : '-' }}
                </td>
                <td class="px-4 py-2.5 text-slate-500 text-xs">{{ obs.source ?? '-' }}</td>
                <td class="px-4 py-2.5 text-right">
                  <IconAction variant="delete" title="Delete observation" @click="deleteObs(obs.id)" />
                </td>
              </tr>
              <tr v-if="!selectedVarObs.length">
                <td colspan="6" class="px-4 py-6 text-center text-slate-500 text-sm">
                  No observations yet. Click "+ Add Observation" to start.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="text-center py-16 text-slate-500">
          Select a variable above to view or add observations.
        </div>
      </div>

      <!-- ══════════════════════════ IMPORT / EXPORT TAB ════════════════════ -->
      <div v-if="activeTab === 'import'" class="space-y-6">

        <!-- CSV import -->
        <ImportGuide
          title="Import macro observations from CSV"
          subtitle="Existing rows for the same variable and period are updated, not duplicated."
          sample-label="Download import template"
          :sample-href="route('macro.export.template')"
          :steps="[
            { title: 'Download the import template', text: 'use the button at the top right. It has the header row and one example row: replace or delete the example row (EXAMPLE_CODE is rejected on purpose).' },
            { title: 'Fill in one row per variable and period', text: 'use the codes from the Variable Code Reference table further down in variable_code, the period date in period_date and the figure in value. Stressed values and source are optional.' },
            { title: 'Set the frequency and classification', text: 'period_type is monthly, quarterly or annual. value_type is actual, estimate or forecast (see the rules below).' },
            { title: 'Save the file as CSV', text: 'keep the header row exactly as it is.' },
            { title: 'Choose the file and click Import', text: 'use the form below. The whole file is checked first: if any row has an error, nothing is saved and the message tells you how many rows to fix. Otherwise you see how many rows were added and updated.' },
          ]"
        >
          <template #after>
            <div class="space-y-1">
              <div><span class="font-semibold">Columns:</span> <code>variable_code, period_date, period_label, period_type, value, value_type, value_stressed_mild, value_stressed_severe, source</code></div>
              <div><code>value_type</code> accepts only <code>actual</code>, <code>estimate</code> or <code>forecast</code>. A future-dated row must be <code>estimate</code> or <code>forecast</code>: blank on a future row is rejected, and blank on a past row defaults to <code>actual</code> with a warning.</div>
              <div><code>period_label</code> is optional. It is worked out from <code>period_date</code> and the frequency (e.g. <code>2025</code>, <code>Q1 2025</code>, <code>Mar 2025</code>).</div>
            </div>
          </template>
        </ImportGuide>

        <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-5 space-y-4">
          <h3 class="text-base font-semibold text-slate-200">Upload Observations CSV</h3>
          <form @submit.prevent="submitImport" class="flex flex-wrap items-end gap-4">
            <div>
              <label class="form-label">Select CSV</label>
              <input type="file" accept=".csv,.txt" @change="handleFile"
                class="text-sm text-slate-400 file:mr-3 file:rounded file:border-0 file:bg-slate-700 file:px-3 file:py-1.5 file:text-slate-200 file:cursor-pointer" />
              <p v-if="importForm.errors.csv_file" class="form-error">{{ importForm.errors.csv_file }}</p>
            </div>
            <button v-if="can.manage" type="submit" :disabled="importForm.processing || !importForm.csv_file"
              class="btn-primary text-sm">
              {{ importForm.processing ? 'Importing…' : 'Import' }}
            </button>
          </form>
        </div>

        <!-- Export options -->
        <div class="bg-slate-800/40 border border-slate-700 rounded-xl p-5 space-y-4">
          <h3 class="text-base font-semibold text-slate-200">Export</h3>
          <div class="flex flex-wrap gap-3">
            <a :href="route('macro.export.data')" class="btn-secondary text-sm">
              ↓ Export All Observations (CSV)
            </a>
          </div>
        </div>

        <!-- Variable code reference -->
        <div class="bg-slate-900 border border-slate-700 rounded-xl overflow-hidden">
          <div class="px-4 py-3 bg-slate-800">
            <h3 class="text-sm font-semibold text-slate-300">Variable Code Reference</h3>
            <p class="text-xs text-slate-500 mt-0.5">Use these codes in the <code>variable_code</code> column of your CSV.</p>
          </div>
          <table class="w-full text-xs">
            <thead class="bg-slate-800/40">
              <tr>
                <th class="px-4 py-2 text-left text-slate-500 font-medium">Code</th>
                <th class="px-4 py-2 text-left text-slate-500 font-medium">Name</th>
                <th class="px-4 py-2 text-left text-slate-500 font-medium">Category</th>
                <th class="px-4 py-2 text-left text-slate-500 font-medium">Unit</th>
                <th class="px-4 py-2 text-left text-slate-500 font-medium">Freq</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="v in variables.filter(v => v.is_active)" :key="v.id"
                class="border-t border-slate-800 hover:bg-slate-800/30">
                <td class="px-4 py-2 font-mono text-emerald-400">{{ v.code }}</td>
                <td class="px-4 py-2 text-slate-300">{{ v.name }}</td>
                <td class="px-4 py-2 text-slate-500">{{ v.category }}</td>
                <td class="px-4 py-2 text-slate-500">{{ v.unit }}</td>
                <td class="px-4 py-2 text-slate-500 capitalize">{{ v.frequency }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

    </div>

    <!-- ═══════════════════════════ VIEW MODAL ═══════════════════════════ -->
    <div v-if="showViewModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
      @click.self="showViewModal = false">
      <div class="bg-slate-900 border border-slate-700 rounded-xl w-full max-w-5xl max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between border-b border-slate-800 px-5 py-3">
          <div>
            <h3 class="text-base font-semibold text-slate-100">{{ viewVariable?.name }}</h3>
            <p class="text-xs text-slate-500 font-mono">{{ viewVariable?.code }} · {{ viewVariable?.unit }} · {{ viewVariable?.frequency }}</p>
          </div>
          <button @click="showViewModal = false" class="text-slate-400 hover:text-white text-xl leading-none">×</button>
        </div>

        <div class="px-5 py-3 border-b border-slate-800 flex flex-wrap gap-3 items-end">
          <div class="flex gap-1">
            <button v-for="t in (['all','actual','forecast'] as const)" :key="t" @click="viewFilterType = t"
              class="px-3 py-1 text-xs rounded transition-colors"
              :class="viewFilterType === t ? 'bg-emerald-600 text-white' : 'bg-slate-800 text-slate-400 hover:text-white'">
              {{ t === 'all' ? 'All' : (t === 'actual' ? 'Historical' : 'Forecast') }}
            </button>
          </div>
          <div>
            <label class="text-[10px] text-slate-500 block">Year from</label>
            <input v-model="viewFilterYearFrom" type="number" class="input-field w-24 text-xs" placeholder="1980" />
          </div>
          <div>
            <label class="text-[10px] text-slate-500 block">Year to</label>
            <input v-model="viewFilterYearTo" type="number" class="input-field w-24 text-xs" placeholder="2030" />
          </div>
          <div class="ml-auto text-xs text-slate-400">
            <span class="mr-3">Total: <span class="text-slate-200">{{ viewSummary.count ?? 0 }}</span></span>
            <span class="mr-3">Actual: <span class="text-emerald-400">{{ viewSummary.actual_count ?? 0 }}</span></span>
            <span class="mr-3">Forecast: <span class="text-amber-400">{{ viewSummary.forecast_count ?? 0 }}</span></span>
            <span>{{ viewSummary.first_period }} · {{ viewSummary.last_period }}</span>
          </div>
        </div>

        <div class="overflow-auto flex-1 px-5 py-3">
          <div v-if="viewLoading" class="text-center text-slate-400 py-12">Loading…</div>
          <table v-else class="w-full text-xs">
            <thead class="bg-slate-800/60 text-slate-400">
              <tr>
                <th class="px-3 py-2 text-left font-medium">Period</th>
                <th class="px-3 py-2 text-left font-medium">Type</th>
                <th class="px-3 py-2 text-right font-medium">Value</th>
                <th class="px-3 py-2 text-right font-medium">Mild Stressed</th>
                <th class="px-3 py-2 text-right font-medium">Severe Stressed</th>
                <th class="px-3 py-2 text-left font-medium">Source</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="o in viewFilteredObs" :key="o.id" class="border-t border-slate-800 hover:bg-slate-800/30">
                <td class="px-3 py-1.5 text-slate-200">{{ o.period_label }}</td>
                <td class="px-3 py-1.5">
                  <span class="px-2 py-0.5 rounded text-[10px]"
                    :class="(o as any).value_type === 'forecast' ? 'bg-amber-900/40 text-amber-300' : 'bg-emerald-900/40 text-emerald-300'">
                    {{ (o as any).value_type === 'forecast' ? 'Forecast' : 'Actual' }}
                  </span>
                </td>
                <td class="px-3 py-1.5 text-right text-slate-200 font-mono">{{ num(o.value, 2) }}</td>
                <td class="px-3 py-1.5 text-right text-amber-400 font-mono">{{ o.value_stressed_mild != null ? num(o.value_stressed_mild, 2) : '-' }}</td>
                <td class="px-3 py-1.5 text-right text-red-400 font-mono">{{ o.value_stressed_severe != null ? num(o.value_stressed_severe, 2) : '-' }}</td>
                <td class="px-3 py-1.5 text-slate-500">{{ o.source ?? '-' }}</td>
              </tr>
              <tr v-if="!viewFilteredObs.length"><td colspan="6" class="px-3 py-8 text-center text-slate-500">No observations match the current filter.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ═════════════════════════ WORLD BANK MODAL ═════════════════════════ -->
    <div v-if="showWbModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
      @click.self="showWbModal = false">
      <div class="bg-slate-900 border border-slate-700 rounded-xl w-full max-w-4xl max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between border-b border-slate-800 px-5 py-3">
          <div>
            <h3 class="text-base font-semibold text-slate-100">Fetch from World Bank: {{ wbVariable?.code }}</h3>
            <p class="text-xs text-slate-500">{{ wbVariable?.name }}</p>
          </div>
          <button @click="showWbModal = false" class="text-slate-400 hover:text-white text-xl leading-none">×</button>
        </div>

        <div class="overflow-auto flex-1 px-5 py-3">
          <!-- Country + year-range controls (blank = service defaults: ZMB, full history). -->
          <div class="mb-3 flex flex-wrap items-end gap-3 rounded-lg border border-slate-800 bg-slate-800/30 p-3">
            <div>
              <label class="block text-[10px] uppercase tracking-wide text-slate-500">Country (ISO3)</label>
              <input v-model="importCountry" type="text" maxlength="3" placeholder="ZMB" class="input-field w-20 text-xs uppercase" />
            </div>
            <div>
              <label class="block text-[10px] uppercase tracking-wide text-slate-500">Year from</label>
              <input v-model.number="importYearFrom" type="number" min="1950" max="2100" placeholder="1960" class="input-field w-24 text-xs" />
            </div>
            <div>
              <label class="block text-[10px] uppercase tracking-wide text-slate-500">Year to</label>
              <input v-model.number="importYearTo" type="number" min="1950" max="2100" placeholder="latest" class="input-field w-24 text-xs" />
            </div>
            <button @click="wbFetch" :disabled="wbLoading" class="btn-secondary text-xs disabled:opacity-40">Refresh preview</button>
            <span class="text-[10px] text-slate-500">Blank = Zambia, full history.</span>
          </div>
          <div v-if="wbLoading" class="text-center text-slate-400 py-12">Fetching…</div>
          <div v-else-if="wbError" class="text-red-400 text-sm py-6">
            <p class="font-semibold mb-1">Fetch failed</p>
            <p>{{ wbError }}</p>
          </div>
          <div v-else-if="wbPreview" class="space-y-3">
            <div class="text-xs text-slate-400 grid grid-cols-2 md:grid-cols-4 gap-3 bg-slate-800/40 rounded p-3">
              <div><span class="text-slate-500">Indicator</span><br /><span class="font-mono text-slate-200">{{ wbPreview.indicator_code ?? '-' }}</span></div>
              <div><span class="text-slate-500">Country</span><br /><span class="text-slate-200">{{ wbPreview.country }}</span></div>
              <div><span class="text-slate-500">Source</span><br /><span class="text-slate-200">{{ wbPreview.source }}</span></div>
              <div><span class="text-slate-500">Rows</span><br /><span :class="wbPreview.rows.length ? 'text-emerald-400 font-semibold' : 'text-slate-500'">{{ wbPreview.rows.length }}</span></div>
            </div>
            <div v-if="wbPreview.message" class="rounded border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-amber-200 text-xs">
              <span class="font-semibold">No data available.</span> {{ wbPreview.message }}
            </div>
            <table class="w-full text-xs">
              <thead class="bg-slate-800/60 text-slate-400 sticky top-0">
                <tr>
                  <th class="px-3 py-2 text-left font-medium">Year</th>
                  <th class="px-3 py-2 text-left font-medium">Type</th>
                  <th class="px-3 py-2 text-right font-medium">Value</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in wbPreview.rows" :key="r.period_label" class="border-t border-slate-800">
                  <td class="px-3 py-1 text-slate-200">{{ r.period_label }}</td>
                  <td class="px-3 py-1"><span class="px-2 py-0.5 rounded text-[10px] bg-emerald-900/40 text-emerald-300">Actual</span></td>
                  <td class="px-3 py-1 text-right text-slate-200 font-mono">{{ num(r.value, 2) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="border-t border-slate-800 px-5 py-3 flex justify-end gap-3">
          <button @click="showWbModal = false" class="text-xs text-slate-400 hover:text-white px-3 py-1.5">Cancel</button>
          <button v-if="can.manage" @click="commitWb" :disabled="!wbPreview || wbCommitting || wbLoading"
            class="btn-primary text-xs disabled:opacity-40">
            {{ wbCommitting ? 'Importing…' : 'Import ' + (wbPreview?.rows?.length ?? 0) + ' rows' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════ IMF WEO MODAL ═══════════════════════════ -->
    <div v-if="showImfModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
      @click.self="showImfModal = false">
      <div class="bg-slate-900 border border-slate-700 rounded-xl w-full max-w-4xl max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between border-b border-slate-800 px-5 py-3">
          <div>
            <h3 class="text-base font-semibold text-slate-100">Import IMF WEO · {{ imfVariable?.code }}</h3>
            <p class="text-xs text-slate-500">{{ imfVariable?.name }} · Download WEO data at imf.org/en/Publications/WEO</p>
          </div>
          <button @click="showImfModal = false" class="text-slate-400 hover:text-white text-xl leading-none">×</button>
        </div>

        <div class="overflow-auto flex-1 px-5 py-3 space-y-3">
          <!-- Country + year-range (blank = ZMB, all years in the file). -->
          <div class="flex flex-wrap items-end gap-3 rounded-lg border border-slate-800 bg-slate-800/30 p-3">
            <div>
              <label class="block text-[10px] uppercase tracking-wide text-slate-500">Country (ISO3)</label>
              <input v-model="importCountry" type="text" maxlength="3" placeholder="ZMB" class="input-field w-20 text-xs uppercase" @change="imfPreviewFetch" />
            </div>
            <div>
              <label class="block text-[10px] uppercase tracking-wide text-slate-500">Year from</label>
              <input v-model.number="importYearFrom" type="number" min="1950" max="2100" placeholder="all" class="input-field w-24 text-xs" @change="imfPreviewFetch" />
            </div>
            <div>
              <label class="block text-[10px] uppercase tracking-wide text-slate-500">Year to</label>
              <input v-model.number="importYearTo" type="number" min="1950" max="2100" placeholder="all" class="input-field w-24 text-xs" @change="imfPreviewFetch" />
            </div>
            <span class="text-[10px] text-slate-500">Blank = Zambia, all years.</span>
          </div>
          <ImportGuide
            title="Import IMF WEO data"
            :steps="[
              { title: 'Download the WEO data file', text: 'get it from imf.org/en/Publications/WEO (button at the top right). It is a tab-delimited .xls file.' },
              { title: 'Set the country and years', text: 'type the ISO3 country code and, if you want, a year range. Leave them blank for Zambia and all years.' },
              { title: 'Choose the file', text: 'pick the WEO file below. The preview shows the indicator, units and the first forecast year.' },
              { title: 'Check the preview and import', text: 'years after the IMF estimates cut-off are marked Forecast. When it looks right, click Import.' },
            ]"
          >
            <template #actions>
              <a href="https://www.imf.org/en/Publications/WEO" target="_blank" rel="noopener" class="import-guide-sample inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-bold transition">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><path d="M12 3v12m0 0-4-4m4 4 4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2" /></svg>
                Get WEO data (imf.org)
              </a>
            </template>
          </ImportGuide>
          <div>
            <label class="form-label">WEO data file (.xls, tab-delimited)</label>
            <input type="file" accept=".xls,.tsv,.txt" @change="onImfFile"
              class="input-field text-xs file:bg-slate-800 file:border-0 file:text-slate-200 file:px-3 file:py-1 file:mr-3" />
          </div>

          <div v-if="imfLoading" class="text-center text-slate-400 py-12">Parsing…</div>
          <div v-else-if="imfError" class="text-red-400 text-sm py-6">
            <p class="font-semibold mb-1">Parse failed</p>
            <p>{{ imfError }}</p>
          </div>
          <div v-else-if="imfPreview" class="space-y-3">
            <div class="text-xs text-slate-400 grid grid-cols-2 md:grid-cols-4 gap-3 bg-slate-800/40 rounded p-3">
              <div><span class="text-slate-500">Indicator</span><br /><span class="font-mono text-slate-200">{{ imfPreview.indicator_code ?? '-' }}</span></div>
              <div><span class="text-slate-500">Country</span><br /><span class="text-slate-200">{{ imfPreview.country }}</span></div>
              <div><span class="text-slate-500">Units</span><br /><span class="text-slate-200">{{ imfPreview.units ?? '-' }}</span></div>
              <div><span class="text-slate-500">Forecast from</span><br /><span class="text-amber-400 font-semibold">{{ imfPreview.estimates_start_after ? imfPreview.estimates_start_after + 1 : '-' }}</span></div>
            </div>
            <div v-if="imfPreview.message" class="rounded border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-amber-200 text-xs">
              <span class="font-semibold">No data available.</span> {{ imfPreview.message }}
            </div>
            <table class="w-full text-xs">
              <thead class="bg-slate-800/60 text-slate-400 sticky top-0">
                <tr>
                  <th class="px-3 py-2 text-left font-medium">Year</th>
                  <th class="px-3 py-2 text-left font-medium">Type</th>
                  <th class="px-3 py-2 text-right font-medium">Value</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in imfPreview.rows" :key="r.period_label" class="border-t border-slate-800">
                  <td class="px-3 py-1 text-slate-200">{{ r.period_label }}</td>
                  <td class="px-3 py-1">
                    <span class="px-2 py-0.5 rounded text-[10px]"
                      :class="r.value_type === 'forecast' ? 'bg-amber-900/40 text-amber-300' : 'bg-emerald-900/40 text-emerald-300'">
                      {{ r.value_type === 'forecast' ? 'Forecast' : 'Actual' }}
                    </span>
                  </td>
                  <td class="px-3 py-1 text-right text-slate-200 font-mono">{{ num(r.value, 2) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="border-t border-slate-800 px-5 py-3 flex justify-end gap-3">
          <button @click="showImfModal = false" class="text-xs text-slate-400 hover:text-white px-3 py-1.5">Cancel</button>
          <button v-if="can.manage" @click="commitImf" :disabled="!imfPreview || imfCommitting || imfLoading"
            class="btn-primary text-xs disabled:opacity-40">
            {{ imfCommitting ? 'Importing…' : 'Import ' + (imfPreview?.rows?.length ?? 0) + ' rows' }}
          </button>
        </div>
      </div>
    </div>

  </AppLayout>
</template>
