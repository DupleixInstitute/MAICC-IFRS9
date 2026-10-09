<template>
    <app-layout title="Add financial period" description="Open a new financial period. Saving it closes the period that is open now">
        <template #actions>
            <inertia-link :href="route('accounting.financial_periods.index')" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50">Back to periods</inertia-link>
        </template>

        <form class="maiic-panel p-6" @submit.prevent="submit">
            <p class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">Only one period is open at a time: when you save, any period still open is closed.</p>
            <period-fields :form="form"/>
            <div class="mt-6 flex justify-end gap-2 border-t border-gray-100 pt-5">
                <inertia-link :href="route('accounting.financial_periods.index')" class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm hover:bg-gray-50">Cancel</inertia-link>
                <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-lg bg-maiic-600 px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-maiic-700 disabled:opacity-50">
                    <font-awesome-icon icon="check"/> {{ form.processing ? 'Saving...' : 'Add period' }}
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
    data() {
        return {
            form: this.$inertia.form({ branch_id: null, name: null, start_date: null, end_date: null, description: null }),
        }
    },
    methods: {
        submit() { this.form.post(this.route('accounting.financial_periods.store')) },
    },
}
</script>
