<template>
<AppLayout>
  <template #header>
    <div class="flex justify-between items-center">
      <div>
        <h2 class="font-semibold text-xl text-gray-800">Collateral Allocations</h2>
        <p class="mt-1 text-sm text-gray-600">List of allocations by Customer ID</p>
      </div>

      <div class="flex space-x-2">
        <inertia-link
          class="bg-maiic-600 text-white px-4 py-2 rounded hover:bg-maiic-700"
          :href="route('collateral.register.index')"
        >
          <i class="fa fa-eye"></i> View Register
        </inertia-link>

        <button
          @click="openCollateralReportModal"
          class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded shadow-md"
        >
          <i class="fas fa-file-archive mr-2"></i> Download Report
        </button>

        <inertia-link
          class="bg-maiic-600 text-white px-4 py-2 rounded hover:bg-maiic-700"
          :href="route('collateral.allocate')"
        >
          + Allocate
        </inertia-link>
      </div>
    </div>
  </template>

<!-- --- Summary Cards --- -->
<div v-if="summary">
  <div class="flex items-center justify-between mb-4">
    <h3 class="text-sm font-medium text-gray-900">Summary 
        <span>
          <i class="fas fa-arrow-right"></i>
        </span>
    </h3>
    <button @click="summaryExpanded = !summaryExpanded"
            class="inline-flex items-center px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded-md transition-colors">
      <svg xmlns="http://www.w3.org/2000/svg" 
           class="h-4 w-4 mr-1 transform transition-transform" 
           :class="{ 'rotate-180': summaryExpanded }" 
           fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
              d="M19 9l-7 7-7-7" />
      </svg>
      {{ summaryExpanded ? 'Hide' : 'Show' }} Summary
    </button>
  </div>

      <transition name="slide-down">
        <div v-show="summaryExpanded" class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
          <!-- Total Allocations -->
          <div class="bg-white shadow rounded-lg p-4">
            <div class="text-sm font-medium text-gray-500">Total Allocations</div>
            <div class="text-2xl font-semibold text-gray-700">{{ summary.total_allocations || 0 }}</div>
          </div>

          <!-- Total Exposure -->
          <div class="bg-white shadow rounded-lg p-4">
            <div class="text-sm font-medium text-gray-500">Total Exposure (MKW)</div>
            <div class="text-2xl font-semibold text-gray-700">{{ formatCurrency(summary.total_exposure) }}</div>
          </div>

          <!-- Total Discounted Allocated -->
          <div class="bg-white shadow rounded-lg p-4">
            <div class="text-sm font-medium text-gray-500">Total Discounted Allocated (MKW)</div>
            <div class="text-2xl font-semibold text-gray-700">{{ formatCurrency(summary.total_discounted) }}</div>
          </div>

          <!-- Coverage Ratio -->
          <div class="bg-white shadow rounded-lg p-4">
            <div class="flex items-center justify-between">
              <div class="text-sm font-medium text-gray-500">Coverage Ratio (%)</div>
              <span :class="getCoverageChangeClass(summary.coverage_change)">
                {{ summary.coverage_change >= 0 ? '▲' : '▼' }}
                {{ (summary.coverage_change * 100).toFixed(2) }}%
              </span>
            </div>
            <div class="text-2xl font-semibold" :class="getCoverageClass(summary.average_coverage)">
              {{ (summary.average_coverage * 100).toFixed(2) }}
            </div>
          </div>
        </div>
      </transition>
    </div>


  <!-- --- Filters --- -->
  <div class="bg-white shadow-sm rounded-lg mt-6 p-6">
    <form @submit.prevent="applyFilters" class="grid grid-cols-6 gap-4">
      <!-- Reporting Period -->
      <div>
        <label class="block text-sm font-medium text-gray-700">Reporting Period</label>
        <input type="month" v-model="filters.reporting_period" class="w-full border-gray-300 rounded shadow-sm"/>
      </div>

      <!-- Collateral Type -->
      <div>
        <label class="block text-sm font-medium text-gray-700">Collateral Type</label>
        <select v-model="filters.type_code" class="w-full border-gray-300 rounded shadow-sm">
          <option value="">All Types</option>
          <option v-for="type in types" :key="type" :value="type">{{ type }}</option>
        </select>
      </div>

      <!-- Customer ID -->
      <div>
        <label class="block text-sm font-medium text-gray-700">Customer ID</label>
        <input type="text" v-model="filters.customer_id" class="w-full border-gray-300 rounded shadow-sm"/>
      </div>

      <!-- Customer Name -->
      <div>
        <label class="block text-sm font-medium text-gray-700">Customer Name</label>
        <input type="text" v-model="filters.customer_name" class="w-full border-gray-300 rounded shadow-sm"/>
      </div>

      <!-- Buttons -->
      <div class="flex items-end space-x-2">
        <button type="submit" class="bg-maiic-600 text-white px-4 py-2 rounded hover:bg-maiic-700">Apply</button>
        <button type="button" @click="resetFilters" class="bg-gray-800 text-white px-4 py-2 rounded hover:bg-gray-500">Reset</button>
      </div>
    </form>
  </div>

  <!-- --- Allocations Table --- -->
  <div class="bg-white shadow-sm rounded-lg mt-6 p-6">
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-100">
          <tr>
            <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Customer ID</th>
            <th class="px-3 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Customer Name</th>
            <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Reporting Period</th>
            <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Basis</th>
            <th class="px-3 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Exposure (MKW)</th>
            <th class="px-3 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Discounted Allocated (MKW)</th>
            <th class="px-3 py-3 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">Coverage Ratio (%)</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in allocations.data" :key="item.id" class="bg-white divide-y divide-gray-200">
            <td class="px-3 py-4 text-sm text-gray-500">{{ item.customer_id }}</td>
            <td class="px-3 py-4 text-sm text-gray-500">{{ item.customer_name }}</td>
            <td class="px-3 py-4 text-center text-sm text-gray-500">{{ formatPeriod(item.reporting_period) }}</td>
            <td class="px-3 py-4 text-center text-sm text-gray-500">{{ item.allocation_basis }}</td>
            <td class="px-3 py-4 text-right text-sm text-gray-500">{{ formatCurrency(item.total_customer_exposure) }}</td>
            <td class="px-3 py-4 text-right text-sm text-gray-500">{{ formatCurrency(item.discounted_collateral) }}</td>
            <td class="px-3 py-4 text-center text-sm" :class="getCoverageClass(item.coverage_ratio)">
              {{ (item.coverage_ratio * 100).toFixed(2) }}%
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <Pagination v-if="allocations.links" :links="allocations.links" class="mt-4" />

  <!-- --- Download Report Modal --- -->
  <div v-if="showCollateralReportModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
    <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
      <h2 class="text-lg font-bold mb-4">Download Collateral Allocation Report</h2>

      <label class="block mb-2 text-sm font-medium text-gray-700">Reporting Period</label>
      <input type="month" v-model="collateralReportPeriod" class="border-gray-300 rounded-md shadow-sm w-full mb-4"/>

      <div class="flex justify-end space-x-2">
        <button @click="showCollateralReportModal=false" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
        <button @click="downloadCollateralReport" :disabled="collateralReportLoading" class="px-4 py-2 bg-maiic-600 text-white rounded hover:bg-maiic-700">
          <span v-if="collateralReportLoading">Preparing…</span>
          <span v-else>Download</span>
        </button>
      </div>
    </div>
  </div>

</AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import '@fortawesome/fontawesome-free/css/all.css';
import { reactive, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { defineProps } from 'vue'

const props = defineProps({ 
  allocations: Object,
  summary: Object,
  types: Array,
  filters: Object
})

const summaryExpanded = ref(true)

const filters = reactive({
  reporting_period: props.filters?.reporting_period || '',
  type_code: props.filters?.type_code || '',
  customer_id: props.filters?.customer_id || '',
  customer_name: props.filters?.customer_name || ''
})

const showCollateralReportModal = ref(false);
const collateralReportPeriod = ref('');
const collateralReportLoading = ref(false);

const openCollateralReportModal = () => {
    collateralReportPeriod.value = '';
    showCollateralReportModal.value = true;
};

const downloadCollateralReport = () => {
    if (!collateralReportPeriod.value) {
        alert('Please select a reporting period.');
        return;
    }

    collateralReportLoading.value = true;
    const [year, month] = collateralReportPeriod.value.split('-');
    const params = new URLSearchParams({ reporting_year: year, reporting_month: month });
    window.open(route('collateral.allocations.download-report') + '?' + params.toString(), '_blank');

    setTimeout(() => {
        collateralReportLoading.value = false;
        showCollateralReportModal.value = false;
    }, 1500);
};

function applyFilters() {
    router.get(route('collateral.allocations.index'), filters, { preserveState: true });
}

function resetFilters() {
    filters.reporting_period = '';
    filters.type_code = '';
    filters.customer_id = '';
    filters.customer_name = '';
    router.get(route('collateral.allocations.index'), {}, { preserveState: false });
}

  const getCoverageClass = (coverage) => {
    if (coverage >= 0.8) return 'text-green-600' 
    if (coverage >= 0.5) return 'text-yellow-600' 
    return 'text-red-600'                         
  }

  // Coverage change visual
  const getCoverageChangeClass = (change) => {
    if (change > 0) return 'text-green-600 font-semibold'
    if (change < 0) return 'text-red-600 font-semibold'
    return 'text-gray-500'
  }

  // Currency formatting
  const formatCurrency = (value) => {
    if (!value) return '0.00'
    return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value)
  }

  const formatPeriod = (date) => {
    if (!date) return '';
    const d = new Date(date);
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short' });
  };


</script>
