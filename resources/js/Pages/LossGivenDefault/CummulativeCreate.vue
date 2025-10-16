<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Cummulative LGD - Essentials
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <form @submit.prevent="submitForm">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Start Reporting Period -->
                            <div>
                                <jet-label for="start_period" value="Start Period" />
                                <input type="month" v-model="form.start_period" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>

                            <!-- End Reporting Period -->
                            <div>
                                <jet-label for="end_period" value="End Period" />
                                <input type="month" v-model="form.reporting_period" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
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
                        </div>

                        <div class="flex justify-end mt-6 gap-4">
                            <jet-button @click="toggleModal" class=" items-center px-4 py-2 bg-black-600 hover:bg-gray-400 text-black-700 rounded-md">
                                Calculate
                            </jet-button>
                            <Link href="/loss-given-default/cummulative" class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-400 text-black-700 rounded-md">
                                Back
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Include Modal Component -->
        <CummulativeManual
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
import CummulativeManual from './Components/CummulativeManual.vue';

export default{
    components:{
        AppLayout,
        JetButton,
        JetLabel,
        CummulativeManual,
    },
    props:{
        portfolio_group:{
            type: Array,
            required: true,
        },
    },

setup(){
const form = useForm({
    portfolio_group: '',
    start_period: '',
    reporting_period: '',
    calculation_source: '',
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

const submitForm = () => {
    if (form.calculation_source === 'system') {
        form.post(route('lgd-cummulative.system'));
    } else {
        toggleModal();
    }
};



const deleteLGD = (id) => {
    if (confirm('Are you sure you want to delete this Loss Given Default?')) {
        router.delete(route('lgd-cummulative.delete', id));
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