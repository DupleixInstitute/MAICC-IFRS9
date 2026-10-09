<template>
  <app-layout title="Collateral Register" description="Every item of collateral imported, by month-end, with its nominal, market and execution values">
    <template #actions>
      <Link :href="route('collateral.register.import')" class="primary-btn">Import register</Link>
    </template>

    <div class="space-y-4">
      <form @submit.prevent="applyFilters" class="maiic-filterbar !mb-0 flex flex-wrap items-end gap-3">
        <div><label class="maiic-flabel" for="f-from">From</label><input id="f-from" v-model="filters.registration_date_from" type="month" class="maiic-input w-44"/></div>
        <div><label class="maiic-flabel" for="f-to">To</label><input id="f-to" v-model="filters.registration_date_to" type="month" class="maiic-input w-44"/></div>
        <div><label class="maiic-flabel" for="f-type">Collateral type code</label><input id="f-type" v-model="filters.type_code" type="text" class="maiic-input w-40"/></div>
        <div><label class="maiic-flabel" for="f-cid">Customer ID</label><input id="f-cid" v-model="filters.customer_id" type="text" class="maiic-input w-36"/></div>
        <div class="min-w-[12rem] flex-1"><label class="maiic-flabel" for="f-name">Customer name</label><input id="f-name" v-model="filters.customer_name" type="text" class="maiic-input"/></div>
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
                <th>Type</th>
                <th>Period</th>
                <th class="num">Nominal value</th>
                <th class="num">Market value</th>
                <th class="num">Execution value</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in collateralRegisters.data" :key="item.id">
                <td class="font-mono text-xs">{{ item.customer_id }}</td>
                <td class="font-semibold text-gray-900">{{ item.customer_name }}</td>
                <td>{{ item.collateral_type }}</td>
                <td class="whitespace-nowrap">{{ formatPeriod(item.period) }}</td>
                <td class="num">{{ formatCurrency(item.nominal_value) }}</td>
                <td class="num">{{ formatCurrency(item.market_value) }}</td>
                <td class="num">{{ formatCurrency(item.execution_value) }}</td>
              </tr>
              <tr v-if="!collateralRegisters.data.length">
                <td colspan="7" class="maiic-empty">{{ anyFilter ? 'No register row matches these filters.' : 'The register is empty. Use Import register (top right) to load a month-end register.' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <Pagination v-if="collateralRegisters.links" :links="collateralRegisters.links"/>
    </div>
  </app-layout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { computed, reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
  collateralRegisters: Object,
  filters: Object,
})

const filters = reactive({
  registration_date_from: props.filters?.registration_date_from || '',
  registration_date_to: props.filters?.registration_date_to || '',
  type_code: props.filters?.type_code || '',
  customer_id: props.filters?.customer_id || '',
  customer_name: props.filters?.customer_name || '',
})
const anyFilter = computed(() => Object.values(props.filters || {}).some(v => v))

function applyFilters() {
  router.get(route('collateral.register.index'), filters, { preserveState: true })
}

function resetFilters() {
  Object.keys(filters).forEach(k => { filters[k] = '' })
  router.get(route('collateral.register.index'), {}, { preserveState: false })
}

const formatPeriod = (date) => {
  if (!date) return ''
  const d = new Date(date)
  return d.toLocaleDateString('en-GB', { year: 'numeric', month: 'short' })
}

const formatCurrency = (value) => {
  if (!value) return '0.00'
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value)
}
</script>
