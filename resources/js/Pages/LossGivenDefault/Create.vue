<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Loss Given Default Monthly - Essentials
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <form @submit.prevent="submitForm" >
                        <input type="hidden" v-model="form.id" />
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Start Reporting Period -->
                            <div>
                                <jet-label for="start_period" value="Start Period" />
                                <input type="month" v-model="form.start_period" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-maiic-500 focus:ring-maiic-500">
                            </div>

                            <!-- End Reporting Period -->
                            <div>
                                <jet-label for="end_period" value="End Period" />
                                <input type="month" v-model="form.reporting_period" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-maiic-500 focus:ring-maiic-500">
                            </div>

                            <!-- Portfolio Group -->
                            <div>
                                <jet-label for="portfolio_group" value="Portfolio Group" />
                                <select v-model="form.portfolio_group" class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                                    <option value="">Select Portfolio</option>
                                    <option v-for="portfolio in portfolio_group" :key="portfolio.id" :value="portfolio.id">
                                        {{ portfolio.name }}
                                    </option>
                                </select>
                            </div>

                            <!-- Calculation Source -->
                            <div>
                                <jet-label for="calculation_source" value="Calculation Source" />
                                <select v-model="form.calculation_source" class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                                    <option value="">Select Source</option>
                                    <option value="manual">Manual</option>
                                    <option value="system">System</option>
                                </select>
                            </div>

                            <!-- Discounting Option -->
                            <div>
                                <label class="flex items-center">
                                    <input type="checkbox" v-model="form.is_discounting" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <span class="ml-2 text-sm text-gray-700">Enable Discounting</span>
                                </label>
                            </div>
                        </div>

                        <!-- Discounting Options (shown when discounting is enabled) -->
                        <div v-if="form.is_discounting" class="border-t pt-6 mt-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Discounting Configuration</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Discount Rate Source -->
                                <div>
                                    <jet-label for="discount_rate_source" value="Interest Rate Source" />
                                    <select v-model="form.discount_rate_source" class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                                        <option value="">Select Source</option>
                                        <option value="manual">Manual Rate</option>
                                        <option value="loan_book">From Loan Book</option>
                                    </select>
                                </div>

                                <!-- Manual Interest Rate (shown when manual source is selected) -->
                                <div v-if="form.discount_rate_source === 'manual'">
                                    <jet-label for="interest_rate" value="Interest Rate (%)" />
                                    <input type="number"
                                           v-model="form.interest_rate"
                                           step="0.01"
                                           min="0"
                                           max="100"
                                           placeholder="e.g., 10.5"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-maiic-500 focus:ring-maiic-500">
                                    <p class="mt-1 text-sm text-gray-500">Enter interest rate as percentage (e.g., 10.5 for 10.5%)</p>
                                </div>

                                <!-- Loan Book Info (shown when loan_book source is selected) -->
                                <div v-if="form.discount_rate_source === 'loan_book'" class="col-span-2">
                                    <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                            <div class="ml-3">
                                                <h3 class="text-sm font-medium text-blue-800">Interest Rate from Loan Book</h3>
                                                <div class="mt-2 text-sm text-blue-700">
                                                    <p>Individual interest rates will be used from each contract's loan book data. Each contract may have different rates based on their original loan terms.</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end mt-6 gap-4">
                            <jet-button @click="toggleModal" class=" items-center px-4 py-2 bg-black-600 hover:bg-gray-400 text-black-700 rounded-md">
                                Calculate
                            </jet-button>
                            <Link href="/loss-given-default/list" class="inline-flex items-center px-4 py-2 bg-maiic-600 hover:bg-green-400 text-black-700 rounded-md">
                                Back
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Include Modal Component -->
        <ManualCalculation
            :show="showModal"
            :start-period="form.start_period"
            :reporting-period="form.reporting_period"
            :portfolio-group="form.portfolio_group"
            :mode="form.calculation_source"
            :default-values="defaultManualValues"
            :is-update="isUpdateMode"
            @close="showModal = false"
        />
    </app-layout>
</template>
<script>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import JetButton from '@/Jetstream/Button.vue';
import JetLabel from '@/Jetstream/Label.vue';
import ManualCalculation from './Components/ManualCalculation.vue';

export default{
    components:{
        AppLayout,
        JetButton,
        JetLabel,
        ManualCalculation,

    },
    props: {
        lossGivenDefault: {
            type: Object,
            default: () => ({}),
        },
        portfolio_group: {
            type: Array,
            required: true,
        },
    },

setup(props){
    const form = useForm({
        portfolio_group: props.lossGivenDefault?.portfolio_group?.id
            ?? props.lossGivenDefault?.lgd_calculation_id ?? '',
        start_period: props.lossGivenDefault?.start_period ?? '',
        reporting_period: props.lossGivenDefault?.reporting_period ?? '',
        calculation_source: props.lossGivenDefault?.calculation_source ?? '',
        loss_given_default_percentage: props.lossGivenDefault?.loss_given_default_percentage ?? '',
        mode: props.lossGivenDefault?.mode ?? '',
        // Discounting fields
        is_discounting: props.lossGivenDefault?.is_discounting ?? false,
        discount_rate_source: props.lossGivenDefault?.discount_rate_source ?? '',
        interest_rate: props.lossGivenDefault?.interest_rate ? (props.lossGivenDefault.interest_rate * 100) : '', // Convert decimal to percentage for display
    });

const showModal = ref(false);
const defaultManualValues = ref({});
const isUpdateMode = ref(false);

const toggleModal = (existingValues = null) => {
    if (form.calculation_source === 'manual') {
        if (existingValues) {
            defaultManualValues.value = existingValues;
            isUpdateMode.value = true;
        } else {
            defaultManualValues.value = {};
            isUpdateMode.value = false;
        }
        showModal.value = true;
    }
};

const openEditModal = () => {
    if (!props.lossGivenDefault) return;

    defaultManualValues.value = {
        start_total_stage3: props.lossGivenDefault.start_total_stage3,
        end_total_stage3: props.lossGivenDefault.end_total_stage3,
        cure_amount_stage1: props.lossGivenDefault.cure_amount_stage1,
        cure_amount_stage2: props.lossGivenDefault.cure_amount_stage2,
        partially_recovered_amount: props.lossGivenDefault.partially_recovered_amount,
        fully_recovered_amount: props.lossGivenDefault.fully_recovered_amount,
        total_disbursments: props.lossGivenDefault.total_disbursments,
        cure_rate: props.lossGivenDefault.cure_rate,
        recovery_rate: props.lossGivenDefault.recovery_rate,
    };

    isUpdateMode.value = true;
    showModal.value = true;
};

        const submitForm = () => {
            // Convert interest rate from percentage to decimal if manual source is selected
            if (form.discount_rate_source === 'manual' && form.interest_rate) {
                // Convert percentage to decimal (e.g., 12 -> 0.12)
                form.interest_rate = parseFloat(form.interest_rate) / 100;
            }

            if (props.lossGivenDefault?.id) {
                form.put(route('loss-given-default.updateManual', props.lossGivenDefault.id));
            } else if (form.calculation_source === 'system') {
                form.post(route('loss-given-default.systemCalculation'));
            } else {
                toggleModal();
            }
        };

const deleteLGD = (id) => {
    if (confirm('Are you sure you want to delete this Loss Given Default?')) {
        router.delete(route('loss-given-default.delete', id));
    }
};
return {
    form,
    submitForm,
    deleteLGD,
    useForm,
    toggleModal,
    showModal,
    defaultManualValues,
    isUpdateMode,
};
}
}
</script>
