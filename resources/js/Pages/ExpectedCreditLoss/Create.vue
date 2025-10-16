<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                ECL - Input Essentials
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <form @submit.prevent="submitForm" >
                        <input type="hidden" v-model="form.id" />
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                            <!-- End Reporting Period -->
                            <div>
                                <jet-label for="end_period" value="Loan Book Period" />
                                <input type="month" v-model="form.reporting_period" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </div>

                            <!-- Portfolio Group -->
                            <div>
                                <jet-label for="portfolio_group" value="Portfolio Group" />
                                <select v-model="form.portfolios" class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                                    <option value="">Select Portfolio</option>
                                    <option v-for="portfolio in portfolios" :key="portfolio.id" :value="portfolio.id">
                                        {{ portfolio.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="flex justify-end mt-6 gap-4">
                            <jet-button class=" items-center px-4 py-2 bg-black-600 hover:bg-gray-400 text-black-700 rounded-md">
                                Calculate ECL
                            </jet-button>
                            <Link href="/expected-credit-loss/list" class="inline-flex items-center px-4 py-2 bg-green-600 hover:bg-green-400 text-black-700 rounded-md">
                                Back
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </app-layout>
</template>
<script>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import JetButton from '@/Jetstream/Button.vue';
import JetLabel from '@/Jetstream/Label.vue';

export default{
    components:{
        AppLayout,
        JetButton,
        JetLabel,

    },
    props: {
        loanBooks: {
            type: Object,
            default: () => ({}),
        },
        portfolios: {
            type: Array,
            required: true,
        },
    },

setup(props){
    const form = useForm({
        portfolios: props.loanBooks?.portfolios ?? '',
        reporting_period: props.loanBooks?.reporting_period ?? '',
        calculation_source: props.loanBooks?.calculation_source ?? '',
        mode: props.loanBooks?.mode ?? '',
    });



        const submitForm = () => {
        form.post(route('expected-credit-loss.calculation'), {
            onSuccess: () => {
                form.reset();
            },
            onError: (errors) => {
                console.error('Form submission error:', errors);
            },
        });
        };

return {
    form,
    submitForm,
    useForm,
};
}
}
</script>