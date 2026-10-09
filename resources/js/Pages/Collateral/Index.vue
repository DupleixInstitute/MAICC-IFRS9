<template>
  <AppLayout title="Collateral Allocation" description="The discounted collateral allocated to each customer's exposure, by reporting period">
    <template #actions>
      <button type="button" class="secondary-btn" @click="openCollateralReportModal">Download report</button>
      <Link :href="route('collateral.allocate')" class="primary-btn">Allocate collateral</Link>
    </template>

    <div class="space-y-4">
      <div v-if="summary" class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="maiic-kpi"><div class="maiic-kpi-label">Allocations</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ (summary.total_allocations || 0).toLocaleString() }}</div></div>
        <div class="maiic-kpi"><div class="maiic-kpi-label">Exposure (MWK)</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ formatCurrency(summary.total_exposure) }}</div></div>
        <div class="maiic-kpi"><div class="maiic-kpi-label">Discounted collateral (MWK)</div><div class="text-xl font-bold text-gray-900 tabular-nums">{{ formatCurrency(summary.total_discounted) }}</div></div>
        <div class="maiic-kpi" style="--accent:#d97706"><div class="maiic-kpi-label">Average coverage</div><div class="text-xl font-bold tabular-nums" :class="getCoverageClass(summary.average_coverage)">{{ summary.average_coverage != null ? (summary.average_coverage * 100).toFixed(2) + '%' : '-' }}</div></div>
      </div>

      <form @submit.prevent="applyFilters" class="maiic-filterbar !mb-0 flex flex-wrap items-end gap-3">
        <div><label class="maiic-flabel" for="f-period">Reporting period</label><input id="f-period" type="month" v-model="filters.reporting_period" class="maiic-input w-44"/></div>
        <div>
          <label class="maiic-flabel" for="f-type">Collateral type</label>
          <select id="f-type" v-model="filters.type_code" class="maiic-select w-40">
            <option value="">All types</option>
            <option v-for="type in types" :key="type" :value="type">{{ type }}</option>
          </select>
        </div>
        <div><label class="maiic-flabel" for="f-cid">Customer ID</label><input id="f-cid" type="text" v-model="filters.customer_id" class="maiic-input w-36"/></div>
        <div class="min-w-[12rem] flex-1"><label class="maiic-flabel" for="f-name">Customer name</label><input id="f-name" type="text" v-model="filters.customer_name" class="maiic-input"/></div>
        <button type="submit" class="primary-btn">Apply</button>
        <button type="button" @click="resetFilters" class="secondary-btn">Clear</button>
      </form>

      <div class="maiic-panel">
        <div class="maiic-table-wrap">
          <table class="maiic-table">
            <thead>
              <tr>
                <th>Customer ID</th>
                <th>Customer name</th>
                <th>Reporting period</th>
                <th>Basis</th>
                <th class="num">Exposure (MWK)</th>
                <th class="num">Discounted collateral (MWK)</th>
                <th class="num">Coverage</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in allocations.data" :key="item.id">
                <td class="font-mono text-xs">{{ item.customer_id }}</td>
                <td class="font-semibold text-gray-900">{{ item.customer_name }}</td>
                <td class="whitespace-nowrap">{{ formatPeriod(item.reporting_period) }}</td>
                <td class="capitalize">{{ item.allocation_basis }}</td>
                <td class="num">{{ formatCurrency(item.total_customer_exposure) }}</td>
                <td class="num">{{ formatCurrency(item.discounted_collateral) }}</td>
                <td class="num"><span class="font-semibold" :class="getCoverageClass(item.coverage_ratio)">{{ (item.coverage_ratio * 100).toFixed(2) }}%</span></td>
              </tr>
              <tr v-if="!allocations.data.length">
                <td colspan="7" class="maiic-empty">{{ anyFilter ? 'No allocation matches these filters.' : 'No collateral has been allocated yet. Import the register, then use Allocate collateral (top right).' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <Pagination v-if="allocations.links" :links="allocations.links"/>
    </div>

    <!-- Download report -->
    <div v-if="showCollateralReportModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-slate-900/50" @click="showCollateralReportModal = false"></div>
      <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
        <h3 class="text-lg font-bold text-gray-900">Download the allocation report</h3>
        <p class="mt-1 text-sm text-gray-500">A CSV file of the allocations for one reporting period, contract by contract.</p>
        <label class="maiic-flabel mt-4" for="r-period">Reporting period</label>
        <input id="r-period" type="month" v-model="collateralReportPeriod" class="maiic-input"/>
        <p v-if="reportError" class="mt-1 text-xs text-red-600">{{ reportError }}</p>
        <div class="mt-5 flex justify-end gap-2">
          <button @click="showCollateralReportModal = false" class="secondary-btn">Cancel</button>
          <button @click="downloadCollateralReport" :disabled="collateralReportLoading" class="primary-btn">{{ collateralReportLoading ? 'Preparing...' : 'Download' }}</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { computed, reactive, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
  allocations: Object,
  summary: Object,
  types: Array,
  filters: Object,
})

const filters = reactive({
  reporting_period: props.filters?.reporting_period || '',
  type_code: props.filters?.type_code || '',
  customer_id: props.filters?.customer_id || '',
  customer_name: props.filters?.customer_name || '',
})
const anyFilter = computed(() => Object.values(props.filters || {}).some(v => v))

const showCollateralReportModal = ref(false)
const collateralReportPeriod = ref('')
const collateralReportLoading = ref(false)
const reportError = ref('')

const openCollateralReportModal = () => {
  collateralReportPeriod.value = filters.reporting_period || ''
  reportError.value = ''
  showCollateralReportModal.value = true
}

const downloadCollateralReport = () => {
  if (!collateralReportPeriod.value) {
    reportError.value = 'Choose a reporting period.'
    return
  }
  collateralReportLoading.value = true
  const [year, month] = collateralReportPeriod.value.split('-')
  const params = new URLSearchParams({ reporting_year: year, reporting_month: month })
  window.open(route('collateral.allocations.download-report') + '?' + params.toString(), '_blank')
  setTimeout(() => {
    collateralReportLoading.value = false
    showCollateralReportModal.value = false
  }, 1500)
}

function applyFilters() {
  router.get(route('collateral.allocations.index'), filters, { preserveState: true })
}

function resetFilters() {
  filters.reporting_period = ''
  filters.type_code = ''
  filters.customer_id = ''
  filters.customer_name = ''
  router.get(route('collateral.allocations.index'), {}, { preserveState: false })
}

const getCoverageClass = (coverage) => {
  if (coverage >= 0.8) return 'text-maiic-700'
  if (coverage >= 0.5) return 'text-amber-600'
  return 'text-red-600'
}

const formatCurrency = (value) => {
  if (!value) return '0.00'
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value)
}

const formatPeriod = (date) => {
  if (!date) return ''
  const d = new Date(date)
  return d.toLocaleDateString('en-GB', { year: 'numeric', month: 'short' })
}
</script>
