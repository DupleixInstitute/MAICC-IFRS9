<template>
    <app-layout :title="'Edit ' + (financialPeriod.name || 'financial period')" description="Change the period's name, dates or description">
        <template #actions>
            <inertia-link :href="route('accounting.financial_periods.index')" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50">Back to periods</inertia-link>
        </template>

        <form class="maiic-panel p-6" @submit.prevent="submit">
            <period-fields :form="form"/>
            <div class="mt-6 flex justify-end gap-2 border-t border-gray-100 pt-5">
                <inertia-link :href="route('accounting.financial_periods.index')" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm hover:bg-gray-50">Cancel</inertia-link>
                <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-lg bg-maiic-600 px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-maiic-700 disabled:opacity-50">
                    <font-awesome-icon icon="check"/> {{ form.processing ? 'Saving...' : 'Save changes' }}
                </button>
            </div>
        </form>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import PeriodFields from './Partials/PeriodFields.vue'

export default {
    components: { AppLayout, PeriodFields },
    props: { financialPeriod: Object },
    data() {
        const p = this.financialPeriod
        return {
            form: this.$inertia.form({
                branch_id: p.branch_id,
                name: p.name,
                start_date: p.start_date ? String(p.start_date).slice(0, 10) : null,
                end_date: p.end_date ? String(p.end_date).slice(0, 10) : null,
                description: p.description,
            }),
        }
    },
    methods: {
        submit() { this.form.put(this.route('accounting.financial_periods.update', this.financialPeriod.id)) },
    },
}
</script>
