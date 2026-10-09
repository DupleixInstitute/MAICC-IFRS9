<template>
    <app-layout title="Financial Periods" description="The accounting periods the system posts into; one is open at a time and closing it opens the next">
        <template #actions>
            <inertia-link v-if="can('accounting.financial_periods.create')" :href="route('accounting.financial_periods.create')"
                          class="inline-flex items-center gap-2 rounded-lg bg-maiic-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-maiic-700">
                <font-awesome-icon icon="plus"/> Add period
            </inertia-link>
        </template>

        <div class="maiic-panel">
            <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 px-4 py-3">
                <input v-model="form.search" type="search" aria-label="Search" placeholder="Search period" class="maiic-input w-64 py-1.5"/>
                <button v-if="form.search" type="button" class="text-xs font-bold text-gray-500 hover:text-maiic-700" @click="form.search = null">Clear</button>
                <span class="ml-auto text-xs text-gray-500"><b class="text-sm text-gray-900">{{ financialPeriods.total ?? financialPeriods.data.length }}</b> periods</span>
            </div>
            <div class="maiic-table-wrap">
                <table class="maiic-table">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Start date</th>
                        <th>End date</th>
                        <th>Status</th>
                        <th>Closed by</th>
                        <th class="num">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="p in financialPeriods.data" :key="p.id">
                        <td class="font-semibold">{{ p.name || 'Period ' + p.id }}<div v-if="p.description" class="text-xs font-normal text-gray-500">{{ p.description }}</div></td>
                        <td class="whitespace-nowrap">{{ p.start_date || '-' }}</td>
                        <td class="whitespace-nowrap">{{ p.end_date || '-' }}</td>
                        <td><span class="maiic-badge" :class="p.closed ? 'maiic-badge-grey' : 'maiic-badge-green'">{{ p.closed ? 'Closed' : 'Open' }}</span></td>
                        <td>{{ p.closed_by?.name || '-' }}</td>
                        <td class="w-px">
                            <row-actions :edit-href="can('accounting.financial_periods.update') ? route('accounting.financial_periods.edit', p.id) : null"
                                         :deletable="can('accounting.financial_periods.destroy')"
                                         @delete="destroy(p)">
                                <button v-if="can('accounting.financial_periods.update') && !p.closed" type="button"
                                        class="maiic-action maiic-action-neutral" title="Close this period and open the next" @click="closePeriod(p)">
                                    <font-awesome-icon icon="lock"/>
                                </button>
                            </row-actions>
                        </td>
                    </tr>
                    <tr v-if="financialPeriods.data.length === 0">
                        <td class="px-6 py-10 text-center" colspan="6">
                            <div class="text-base font-bold text-gray-800">No financial periods yet</div>
                            <p class="mt-1 text-sm text-gray-500">Add the first period with Add period; from then on, closing a period opens the next one.</p>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="financialPeriods.links && financialPeriods.links.length > 3" class="border-t border-gray-100 px-4 pb-4"><pagination :links="financialPeriods.links"/></div>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import RowActions from '@/Shared/RowActions.vue'
import { confirmDialog } from '@/Components/confirmDialog'
import pickBy from 'lodash/pickBy'

export default {
    components: { AppLayout, Pagination, RowActions },
    props: { financialPeriods: Object, filters: Object },
    data() {
        return { form: { search: this.filters.search } }
    },
    watch: {
        form: {
            handler: _.debounce(function () {
                this.$inertia.get(this.route('accounting.financial_periods.index'), pickBy(this.form), { preserveState: true, replace: true })
            }, 450),
            deep: true,
        },
    },
    methods: {
        async destroy(p) {
            if (!(await confirmDialog({ title: 'Delete this financial period?', message: 'The period record is removed. This cannot be undone.', confirmLabel: 'Delete', tone: 'danger' }))) return
            this.$inertia.delete(this.route('accounting.financial_periods.destroy', p.id))
        },
        async closePeriod(p) {
            if (!(await confirmDialog({ title: 'Close this period?', message: 'The period is closed with today as its end date, and a new period opens from today.', confirmLabel: 'Close period' }))) return
            this.$inertia.put(this.route('accounting.financial_periods.close', p.id))
        },
    },
}
</script>
