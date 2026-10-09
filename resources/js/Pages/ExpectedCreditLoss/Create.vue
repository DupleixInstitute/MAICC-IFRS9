<template>
    <app-layout title="Run ECL calculation" description="Choose the month, the portfolio or sector, and the PD, LGD and measurement basis, then run the calculation">
        <template #actions>
            <Link :href="route('expected-credit-loss.index')" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50">
                Back to ECL results
            </Link>
        </template>

        <form class="maiic-panel p-6" @submit.prevent="submitForm">
            <div class="maiic-section-title mt-0">What to calculate</div>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                <div>
                    <label for="reporting_period" class="maiic-flabel">Loan book month</label>
                    <input id="reporting_period" v-model="form.reporting_period" type="month" required class="maiic-input">
                    <p v-if="form.errors.reporting_period" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.reporting_period }}</p>
                </div>
                <div>
                    <label for="ecl_calculation_level" class="maiic-flabel">Calculate by</label>
                    <select id="ecl_calculation_level" v-model="form.ecl_calculation_level" class="maiic-select">
                        <option value="portfolio">Portfolio</option>
                        <option value="sector">Sector</option>
                    </select>
                    <p v-if="form.errors.ecl_calculation_level" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.ecl_calculation_level }}</p>
                </div>
                <div v-if="form.ecl_calculation_level === 'portfolio'">
                    <label for="ecl_calculation_id" class="maiic-flabel">Portfolio</label>
                    <select id="ecl_calculation_id" v-model="form.ecl_calculation_id" class="maiic-select">
                        <option value="">Select a portfolio</option>
                        <option v-for="portfolio in portfolios" :key="portfolio.id" :value="portfolio.id">{{ portfolio.name }}</option>
                    </select>
                    <p v-if="form.errors.ecl_calculation_id" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.ecl_calculation_id }}</p>
                </div>
                <div v-else>
                    <label for="ecl_calculation_code" class="maiic-flabel">Sector</label>
                    <select id="ecl_calculation_code" v-model="form.ecl_calculation_code" class="maiic-select">
                        <option value="">Select a sector</option>
                        <option v-for="sector in sectors" :key="sector.code" :value="sector.code">{{ sector.code }} - {{ sector.name }}</option>
                    </select>
                    <p v-if="form.errors.ecl_calculation_code" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.ecl_calculation_code }}</p>
                </div>
            </div>

            <div class="maiic-section-title">Inputs</div>
            <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                <div>
                    <label for="pd_type" class="maiic-flabel">PD to use</label>
                    <select id="pd_type" v-model="form.pd_type" class="maiic-select">
                        <option value="pd_prefli">PD before forward-looking adjustment</option>
                        <option value="pd_post_fli">PD after forward-looking adjustment</option>
                    </select>
                    <p v-if="form.errors.pd_type" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.pd_type }}</p>
                </div>
                <div>
                    <label for="lgd_type" class="maiic-flabel">LGD to use</label>
                    <select id="lgd_type" v-model="form.lgd_type" class="maiic-select">
                        <option value="customer_lgd">Customer LGD</option>
                        <option value="collection_lgd">Collection LGD</option>
                        <option value="both">Both (customer x collection)</option>
                    </select>
                    <p v-if="form.errors.lgd_type" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.lgd_type }}</p>
                </div>
            </div>

            <div class="maiic-section-title">Measurement basis</div>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition"
                       :class="form.discounting_mode === 'discounted' ? 'border-maiic-500 bg-maiic-50' : 'border-gray-300 hover:bg-gray-50'">
                    <input v-model="form.discounting_mode" type="radio" value="discounted" class="mt-1 text-maiic-600 focus:ring-maiic-500">
                    <span>
                        <span class="block font-semibold text-gray-900">Discounted ECL, at the EIR</span>
                        <span class="mt-1 block text-sm text-gray-600">Discounts the expected loss at the approved, locked effective interest rate. The undiscounted amount is kept as a check.</span>
                    </span>
                </label>
                <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition"
                       :class="form.discounting_mode === 'undiscounted' ? 'border-maiic-500 bg-maiic-50' : 'border-gray-300 hover:bg-gray-50'">
                    <input v-model="form.discounting_mode" type="radio" value="undiscounted" class="mt-1 text-maiic-600 focus:ring-maiic-500">
                    <span>
                        <span class="block font-semibold text-gray-900">Undiscounted ECL</span>
                        <span class="mt-1 block text-sm text-gray-600">PD x LGD x exposure with no interest rate applied. Use it for comparison and transition analysis.</span>
                    </span>
                </label>
            </div>
            <p v-if="form.discounting_mode === 'discounted'" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                Loans without a locked EIR or a usable remaining term stay visible as unresolved exceptions.
            </p>
            <p v-if="form.errors.discounting_mode" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.discounting_mode }}</p>

            <div class="mt-6 flex justify-end gap-2 border-t border-gray-100 pt-5">
                <Link :href="route('expected-credit-loss.index')" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50">Cancel</Link>
                <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-lg bg-maiic-600 px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-maiic-700 disabled:opacity-50">
                    <font-awesome-icon :icon="form.processing ? 'spinner' : 'calculator'" :spin="form.processing"/>
                    {{ form.processing ? 'Calculating...' : 'Run ECL calculation' }}
                </button>
            </div>
        </form>
    </app-layout>
</template>

<script>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
    components: { AppLayout, Link },
    props: {
        loanBooks: { type: Object, default: () => ({}) },
        portfolios: { type: Array, required: true },
        sectors: { type: Array, required: true },
    },
    setup(props) {
        const form = useForm({
            ecl_calculation_level: 'portfolio',
            ecl_calculation_id: props.loanBooks?.portfolios ?? '',
            ecl_calculation_code: props.loanBooks?.sectors ?? '',
            reporting_period: props.loanBooks?.reporting_period ?? '',
            pd_type: 'pd_prefli',
            lgd_type: 'collection_lgd',
            discounting_mode: 'undiscounted',
        });
        const submitForm = () => {
            form.post(route('expected-credit-loss.calculation'), { onSuccess: () => form.reset() });
        };
        return { form, submitForm };
    },
};
</script>
