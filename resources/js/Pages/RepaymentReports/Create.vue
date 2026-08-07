<template>
    <app-layout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    New LGD Payment Tracking Calculation
                    <HelpManual />
                </h2>

                <Link
                    :href="route('lgd-calculations.index')"
                    class="inline-flex items-center bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg shadow-md transition duration-300"
                >
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back
                </Link>
            </div>
        </template>

        <div class="max-w-2xl mx-auto mt-6">
            <div class="bg-white shadow-md rounded-lg p-6">
                <form @submit.prevent="submit">
                    <!-- Portfolio -->
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Portfolio <span class="text-red-500">*</span>
                        </label>
                        <select v-model="form.portfolio_id"
                                class="w-full border-gray-300 rounded-md shadow-sm"
                                :class="{ 'border-red-500': errors.portfolio_id }">
                            <option value="">Select Portfolio</option>
                            <option v-for="p in portfolios" :key="p.id" :value="p.id">
                                {{ p.name }}
                            </option>
                        </select>
                        <p v-if="errors.portfolio_id" class="mt-1 text-sm text-red-600">{{ errors.portfolio_id }}</p>
                    </div>

                    <!-- Period Range -->
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Start Period <span class="text-red-500">*</span>
                            </label>
                            <input type="month"
                                   v-model="form.start_period"
                                   class="w-full border-gray-300 rounded-md shadow-sm"
                                   :class="{ 'border-red-500': errors.start_period }">
                            <p v-if="errors.start_period" class="mt-1 text-sm text-red-600">{{ errors.start_period }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                End Period <span class="text-red-500">*</span>
                            </label>
                            <input type="month"
                                   v-model="form.end_period"
                                   class="w-full border-gray-300 rounded-md shadow-sm"
                                   :class="{ 'border-red-500': errors.end_period }">
                            <p v-if="errors.end_period" class="mt-1 text-sm text-red-600">{{ errors.end_period }}</p>
                        </div>
                    </div>

                    <!-- Recalculate Existing -->
                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" v-model="form.recalculate_existing" class="rounded border-gray-300">
                            <span class="ml-2 text-sm text-gray-600">
                                Recalculate even if calculation already exists
                            </span>
                        </label>
                    </div>

                    <!-- Info Box -->
                    <div class="bg-maiic-50 border border-maiic-200 rounded-lg p-4 mb-4">
                        <div class="flex">
                            <i class="fas fa-info-circle text-maiic-500 mt-1 mr-2"></i>
                            <div class="text-sm text-maiic-700">
                                <p class="font-medium">Processing Information:</p>
                                <ul class="list-disc ml-4 mt-1">
                                    <li>Large datasets will be processed in the background</li>
                                    <li>You will be notified when calculation completes</li>
                                    <li>You can check status in the calculations list</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Error Display -->
                    <div v-if="Object.keys(errors).length > 0" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                        <p class="text-sm text-red-600">Please fix the errors above before submitting.</p>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end space-x-2">
                        <Link :href="route('lgd-calculations.index')"
                              class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">
                            Cancel
                        </Link>
                        <button type="submit"
                                :disabled="processing"
                                class="px-4 py-2 bg-maiic-600 text-white rounded hover:bg-maiic-700 disabled:opacity-50">
                            <span v-if="processing">Processing...</span>
                            <span v-else>Start Calculation</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </app-layout>
</template>

<script>
import { ref, reactive } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import HelpManual from '@/Components/HelpManual.vue';

export default {
    components: {
        AppLayout,
        HelpManual
    },
    props: {
        portfolios: {
            type: Array,
            required: true
        },
        availablePeriods: {
            type: Array,
            default: () => []
        }
    },
    setup() {
        const processing = ref(false);
        const errors = ref({});

        const form = reactive({
            portfolio_id: '',
            start_period: '',
            end_period: '',
            recalculate_existing: false
        });

        const submit = () => {
            processing.value = true;
            errors.value = {};

            router.post(route('lgd-calculations.store'), form, {
                onSuccess: () => {
                    processing.value = false;
                },
                onError: (err) => {
                    errors.value = err;
                    processing.value = false;
                }
            });
        };

        return {
            form,
            processing,
            errors,
            submit
        };
    }
};
</script>
