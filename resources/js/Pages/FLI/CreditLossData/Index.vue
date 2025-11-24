<template>
  <app-layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Credit Loss Data
      </h2>

      <div class="mb-4 flex justify-end space-x-4">
        <div class="flex space-x-3">
            <inertia-link
                :href="route('credit-loss-data.create')"
                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-800 focus:outline-none focus:border-indigo-800 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition"
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add New Record
            </inertia-link>

            <inertia-link
                :href="route('credit-loss-data.importView')"
                class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 active:bg-green-800 focus:outline-none focus:border-green-800 focus:ring focus:ring-green-300 disabled:opacity-25 transition"
            >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                </svg>
                Import CSV
            </inertia-link>
        </div>
        </div>
    </template>

    <div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Total Records</dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900">{{ totalRecords }}</dd>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Portfolios</dt>
                    <dd class="mt-1 text-lg font-semibold text-gray-900">{{ portfolios.length }}</dd>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Periods</dt>
                    <dd class="mt-1 text-2xl font-semibold text-green-600">{{ uniquePeriods.length }}</dd>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Metrics</dt>
                    <dd class="mt-1 text-2xl font-semibold text-blue-600">{{ definitions.length }}</dd>
                </div>
            </div>
        </div>

        <!-- Filters -->
         <div class="mb-6 flex space-x-4 items-center">
            <input
                v-model="form.period"
                type="month"
                class="flex-1 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 px-3 py-2 text-sm"
                placeholder="Select Period"
            />

            <select v-model="form.portfolio_id"
                class="flex-1 border-gray-300 rounded-md px-3 py-2 text-sm">
                <option value="">All Portfolios</option>
                <option v-for="portfolio in portfolios" :key="portfolio.id" :value="portfolio.id">
                    {{ portfolio.name }}
                </option>
            </select>

            <select v-model="form.definition_id"
                class="flex-1 border-gray-300 rounded-md px-3 py-2 text-sm">
                <option value="">All Metrics</option>
                <option v-for="definition in definitions" :key="definition.id" :value="definition.id">
                    {{ definition.name }}
                </option>
            </select>

            <!-- Buttons -->
            <button
                type="button"
                @click="applyFilters"
                class="px-3 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700"
            >
                Apply
            </button>

            <button
                type="button"
                @click="resetFilters"
                class="px-3 py-2 bg-gray-300 text-sm rounded-md hover:bg-gray-400"
            >
                Reset
            </button>
        </div>



        <!-- Portfolios -->
        <div v-for="portfolio in portfolios" :key="portfolio.id" class="bg-white shadow rounded-lg mb-8">
            <div class="px-6 py-4 bg-gray-50 border-b flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-800">{{ portfolio.name }}</h3>
                <span class="text-sm text-gray-500">
                    {{ portfolioData[portfolio.id]?.total ?? 0 }} total records
                </span>
            </div>

            <div v-if="portfolioData[portfolio.id]?.data?.length" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Period</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Metric</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Value</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Source</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="record in portfolioData[portfolio.id].data" :key="record.id">
                            <td class="px-6 py-4 text-sm text-gray-900">{{ formatPeriod(record.period) }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <span :class="getMetricBadgeClass(record.definition?.code)"
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                    {{ record.definition?.name || 'N/A' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium"
                                :class="getValueColor(record.definition?.code, record.value)">
                                {{ formatValue(record.definition?.code, record.value) }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ record.source || 'Manual' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex space-x-2">
                                    <button @click="editRecord(record)" class="text-green-600 hover:text-green-900"><i class="fas fa-pen"></i></button>
                                    <button @click="deleteRecord(record)" class="text-red-600 hover:text-red-900"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="mt-4 flex flex-wrap justify-center">
                    <template v-for="(link, index) in portfolioData[portfolio.id].links" :key="index">
                        <div v-if="!link.url"
                            class="px-3 py-2 text-sm text-gray-400 border rounded mr-1 mb-1"
                            v-html="link.label" />
                        <inertia-link
                            v-else :href="link.url"
                            class="px-3 py-2 text-sm border rounded mr-1 mb-1 hover:bg-indigo-50"
                            :class="{ 'bg-indigo-100 font-bold': link.active }"
                            v-html="link.label" />
                    </template>
                </div>
            </div>

            <div v-else class="p-6 text-center text-gray-500">
                No data available for this portfolio
            </div>
        </div>

    </div>
</div>


  </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import { Inertia } from '@inertiajs/inertia'
import { Link } from '@inertiajs/vue3'
import '@fortawesome/fontawesome-free/css/all.css';

export default {
  components: { AppLayout, Link },
  props: {
    totalRecords: Number,
    portfolios: Array,
    portfolioData: Object,
    definitions: Array,
    uniquePeriods: Array,
    filters: Object
  },
  data() {
    return {
      form: {
        period: this.filters.period || '',
        definition_id: this.filters.definition_id || '',
        portfolio_id: this.filters.portfolio_id || ''
      }
    }
  },
  methods: {
    applyFilters() {
      const params = {}
      if (this.form.period) params.period = this.form.period
      if (this.form.definition_id) params.definition_id = this.form.definition_id
      if (this.form.portfolio_id) params.portfolio_id = this.form.portfolio_id

      Inertia.get(route('credit-loss-data.index'), params, {
        preserveState: true,
        preserveScroll: true
      })
    },

     resetFilters() {
        this.form.period = '';
        this.form.definition_id = '';
        this.form.portfolio_id = '';

        this.applyFilters();
    },

    formatPeriod(period) {
      if (!period) return 'N/A'
      const [year, month] = period.split('-')
      return new Date(year, month - 1).toLocaleDateString('en-US', { year: 'numeric', month: 'long' })
    },
    formatValue(metricCode, value) {
      if (value == null) return '-'
      const percentageMetrics = ['PD', 'LGD']
      const currencyMetrics = ['ECL', 'NPL', 'EAD']
      if (percentageMetrics.includes(metricCode))
        return (value * 100).toFixed(2) + '%'
      if (currencyMetrics.includes(metricCode))
        return new Intl.NumberFormat().format(value)
      return value
    },

    getMetricBadgeClass(metricCode) {
            const classes = {
                'ECL': 'bg-blue-100 text-blue-800',
                'PD': 'bg-green-100 text-green-800',
                'LGD': 'bg-yellow-100 text-yellow-800',
                'EAD': 'bg-purple-100 text-purple-800',
                'NPL': 'bg-red-100 text-red-800',
                'STAGE': 'bg-gray-100 text-gray-800',
                'CREDIT_RATING': 'bg-indigo-100 text-indigo-800'
            };
            return classes[metricCode] || 'bg-gray-100 text-gray-800';
        },
        
        getValueColor(metricCode, value) {
            if (value === null || value === undefined) return 'text-gray-500';
            
            if (['PD', 'LGD', 'NPL'].includes(metricCode)) {
                if (value > 0.1) return 'text-red-600';
                if (value > 0.05) return 'text-yellow-600';
                return 'text-green-600';
            }
            return 'text-gray-900';
        },
        
        getInputClass(metricCode) {
            const percentageMetrics = ['PD', 'LGD'];
            if (percentageMetrics.includes(metricCode)) {
                return 'pr-10';
            }
            return '';
        },
        

        editRecord(creditLossData) {
            this.$inertia.get(route('credit-loss-data.edit', creditLossData.id));
        },
        
        deleteRecord(creditLossData) {
            if (confirm('Are you sure you want to delete this record?')) {
                Inertia.delete(route('credit-loss-data.destroy', creditLossData.id));
            }
        }
    },
    

  }
</script>
