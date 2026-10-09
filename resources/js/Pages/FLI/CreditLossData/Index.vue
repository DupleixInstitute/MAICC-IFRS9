<template>
  <app-layout>
    <template #header>
      <div>
        <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
          <span>Administration</span><span>/</span><span>Legacy FLI tools</span><span>/</span><span class="font-medium text-maiic-700">Credit Loss Data</span>
        </div>
        <h2 class="text-xl font-semibold text-gray-800">Credit Loss Data</h2>
        <p class="mt-1 text-sm text-gray-600">The historical loss measures (default rates, losses and similar) the forward-looking model is fitted to</p>
      </div>
    </template>
    <template #actions>
      <Link :href="route('credit-loss-data.importView')" class="secondary-btn">Import CSV</Link>
      <Link :href="route('credit-loss-data.create')" class="primary-btn">Add record</Link>
    </template>

    <LegacyNotice class="mb-4" />
    <div class="w-full space-y-4">
        <KpiRow :cards="[
            { label: 'Records', value: Number(totalRecords || 0) },
            { label: 'Portfolios', value: portfolios.length },
            { label: 'Periods', value: uniquePeriods.length },
            { label: 'Metrics', value: definitions.length },
        ]" />

        <div class="maiic-filterbar !mb-0">
          <div class="flex flex-wrap items-end gap-2">
            <label class="block"><span class="maiic-flabel">Period</span><input v-model="form.period" type="month" class="maiic-input !w-44" /></label>
            <label class="block"><span class="maiic-flabel">Portfolio</span>
              <select v-model="form.portfolio_id" class="maiic-select !w-56">
                <option value="">All portfolios</option>
                <option v-for="portfolio in portfolios" :key="portfolio.id" :value="portfolio.id">{{ portfolio.name }}</option>
              </select>
            </label>
            <label class="block"><span class="maiic-flabel">Metric</span>
              <select v-model="form.definition_id" class="maiic-select !w-56">
                <option value="">All metrics</option>
                <option v-for="definition in definitions" :key="definition.id" :value="definition.id">{{ definition.name }}</option>
              </select>
            </label>
            <button type="button" class="primary-btn" @click="applyFilters">Apply</button>
            <button type="button" class="secondary-btn" @click="resetFilters">Reset</button>
          </div>
        </div>

        <!-- Portfolios -->
        <div v-for="portfolio in portfolios" :key="portfolio.id" class="maiic-panel">
            <div class="px-5 py-4 border-b flex justify-between items-center">
                <h3 class="font-semibold text-gray-900">{{ portfolio.name }}</h3>
                <span class="text-xs text-gray-500">
                    {{ portfolioData[portfolio.id]?.total ?? 0 }} total records
                </span>
            </div>

            <div v-if="portfolioData[portfolio.id]?.data?.length" class="overflow-x-auto">
                <table class="maiic-table">
                    <thead>
                        <tr><th>Period</th><th>Metric</th><th class="num">Value</th><th>Source</th><th class="text-right">Actions</th></tr>
                    </thead>

                    <tbody>
                        <tr v-for="record in portfolioData[portfolio.id].data" :key="record.id">
                            <td class="px-6 py-4 text-sm text-gray-900">{{ formatPeriod(record.period) }}</td>
                            <td class="whitespace-nowrap text-gray-900">
                                <span :class="getMetricBadgeClass(record.definition?.code)"
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium">
                                    {{ record.definition?.name || 'N/A' }}
                                </span>
                            </td>
                            <td class="num whitespace-nowrap font-medium"
                                :class="getValueColor(record.definition?.code, record.value)">
                                {{ formatValue(record.definition?.code, record.value) }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">{{ record.source || 'Manual' }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex justify-end gap-1.5">
                                    <button type="button" class="maiic-action maiic-action-edit" title="Edit record" @click="editRecord(record)"><font-awesome-icon icon="pen" /></button>
                                    <button type="button" class="maiic-action maiic-action-delete" title="Delete record" @click="deleteRecord(record)"><font-awesome-icon icon="trash" /></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="portfolioData[portfolio.id].links && portfolioData[portfolio.id].links.length > 3" class="border-t border-gray-100 px-4 pb-4">
                    <Pagination :links="portfolioData[portfolio.id].links" />
                </div>
            </div>

            <div v-else class="maiic-empty">
                No credit loss data for this portfolio yet. Use <strong>Import CSV</strong> or <strong>Add record</strong> at the top right.
            </div>
        </div>

    </div>
  </app-layout>
</template>

<script>
import { confirmDialog } from '@/Components/confirmDialog'
import AppLayout from '@/Layouts/AppLayout.vue'
import LegacyNotice from '@/Components/Maiic/LegacyNotice.vue'
import { Inertia } from '@inertiajs/inertia'
import { Link } from '@inertiajs/vue3'
import KpiRow from '@/Components/Maiic/KpiRow.vue'

export default {
  components: { AppLayout, LegacyNotice, Link, KpiRow },
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
                'ECL': 'bg-maiic-100 text-maiic-800',
                'PD': 'bg-maiic-100 text-maiic-800',
                'LGD': 'bg-amber-100 text-amber-800',
                'EAD': 'bg-maiic-100 text-maiic-800',
                'NPL': 'bg-red-100 text-red-800',
                'STAGE': 'bg-gray-100 text-gray-800',
                'CREDIT_RATING': 'bg-maiic-100 text-maiic-800'
            };
            return classes[metricCode] || 'bg-gray-100 text-gray-800';
        },

        getValueColor(metricCode, value) {
            if (value === null || value === undefined) return 'text-gray-500';

            if (['PD', 'LGD', 'NPL'].includes(metricCode)) {
                if (value > 0.1) return 'text-red-600';
                if (value > 0.05) return 'text-amber-600';
                return 'text-maiic-600';
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

        async deleteRecord(creditLossData) {
            if (await confirmDialog({ title: 'Delete this record?', message: 'The credit loss figure is removed. This cannot be undone.', confirmLabel: 'Delete', tone: 'danger' })) {
                Inertia.delete(route('credit-loss-data.destroy', creditLossData.id));
            }
        }
    },


  }
</script>
