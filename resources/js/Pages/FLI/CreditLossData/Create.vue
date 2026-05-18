<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('credit-loss-data.index')">
                    Credit Loss Data
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> Add New Record
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6">
                        <!-- Form Header -->
                        <div class="mb-6">
                            <h3 class="text-lg font-medium text-gray-900">Add Credit Loss Data</h3>
                            <p class="mt-1 text-sm text-gray-600">
                                Add a new credit loss metric record for a specific portfolio and period.
                            </p>
                        </div>

                        <form @submit.prevent="submit">
                            <!-- Portfolio, Period & Metric -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                                <div>
                                    <jet-label for="portfolio_id" value="Portfolio *" />
                                    <select
                                        id="portfolio_id"
                                        v-model="form.portfolio_id"
                                        required
                                        class="mt-1 block w-full rounded-md border-gray-300 bg-white shadow-sm focus:border-maiic-500 focus:ring-maiic-500"
                                        :class="{ 'border-red-300 focus:border-red-500 focus:ring-red-500': form.errors.portfolio_id }"
                                    >
                                        <option value="">Select a portfolio</option>
                                        <option v-for="portfolio in portfolios" :key="portfolio.id" :value="portfolio.id">
                                            {{ portfolio.name }}
                                        </option>
                                    </select>
                                    <jet-input-error :message="form.errors.portfolio_id" class="mt-2" />
                                </div>

                                <div>
                                    <jet-label for="period" value="Reporting Period *" />
                                    <input
                                        id="period"
                                        type="month"
                                        v-model="form.period"
                                        required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-maiic-500 focus:ring-maiic-500"
                                        :class="{ 'border-red-300 focus:border-red-500 focus:ring-red-500': form.errors.period }"
                                    />
                                    <jet-input-error :message="form.errors.period" class="mt-2" />
                                </div>

                                <div>
                                    <jet-label for="definition_id" value="Metric Type *" />
                                    <select
                                        id="definition_id"
                                        v-model="form.definition_id"
                                        required
                                        @change="updateInputConfig"
                                        class="mt-1 block w-full rounded-md border-gray-300 bg-white shadow-sm focus:border-maiic-500 focus:ring-maiic-500"
                                        :class="{ 'border-red-300 focus:border-red-500 focus:ring-red-500': form.errors.definition_id }"
                                    >
                                        <option value="">Select a metric</option>
                                        <option v-for="definition in definitions" :key="definition.id" :value="definition.id">
                                            {{ definition.name }} ({{ definition.code }})
                                        </option>
                                    </select>
                                    <jet-input-error :message="form.errors.definition_id" class="mt-2" />
                                </div>
                            </div>

                            <!-- Value Input -->
                            <div class="mb-6">
                                <jet-label for="value" :value="`${selectedMetricName} Value`" />
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <input
                                        id="value"
                                        :type="inputConfig.type"
                                        :step="inputConfig.step"
                                        :min="inputConfig.min"
                                        :max="inputConfig.max"
                                        v-model="form.value"
                                        :class="[inputConfig.class, { 'border-red-300 focus:border-red-500 focus:ring-red-500': form.errors.value }]"
                                        :placeholder="inputConfig.placeholder"
                                        class="block w-full rounded-md border-gray-300 focus:border-maiic-500 focus:ring-maiic-500"
                                    />
                                    <div v-if="inputConfig.suffix" class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">{{ inputConfig.suffix }}</span>
                                    </div>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">{{ inputConfig.description }}</p>
                                <jet-input-error :message="form.errors.value" class="mt-2" />
                            </div>

                            <!-- Source & Notes -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <jet-label for="source" value="Data Source" />
                                    <select
                                        id="source"
                                        v-model="form.source"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-maiic-500 focus:ring-maiic-500"
                                    >
                                        <option value="Manual Entry">Manual Entry</option>
                                        <option value="System Generated">System Generated</option>
                                        <option value="External Data">External Data</option>
                                        <option value="Regulatory Report">Regulatory Report</option>
                                        <option value="Internal Model">Internal Model</option>
                                    </select>
                                    <jet-input-error :message="form.errors.source" class="mt-2" />
                                </div>

                                <div>
                                    <jet-label for="notes" value="Notes" />
                                    <textarea
                                        id="notes"
                                        v-model="form.notes"
                                        rows="3"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-maiic-500 focus:ring-maiic-500"
                                        placeholder="Additional notes or comments..."
                                    ></textarea>
                                    <jet-input-error :message="form.errors.notes" class="mt-2" />
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div class="flex items-center justify-end mt-8 pt-6 border-t border-gray-200">
                                <inertia-link
                                    :href="route('credit-loss-data.index')"
                                    class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 active:bg-gray-500 focus:outline-none focus:border-gray-500 focus:ring focus:ring-gray-300 disabled:opacity-25 transition mr-3"
                                >
                                    Cancel
                                </inertia-link>
                            <jet-button :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                {{ editData ? 'Update Record' : 'Create Record' }}
                            </jet-button>

                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetButton from '@/Jetstream/Button.vue'
import JetInput from '@/Jetstream/Input.vue'
import JetInputError from '@/Jetstream/InputError.vue'
import JetLabel from '@/Jetstream/Label.vue'
import { useForm } from '@inertiajs/vue3'

export default {
    components: {
        AppLayout,
        JetButton,
        JetInput,
        JetInputError,
        JetLabel,
    },
    props: {
        portfolios: Array,
        definitions: Array,
        editData: { type: Object, default: null }
    },
    data() {
        return {
            inputConfig: {
                type: 'number',
                step: '0.0001',
                min: '0',
                max: '1',
                placeholder: '0.0000',
                suffix: '',
                description: 'Select a metric type to see input instructions',
                class: '',
                required: false
            },
            form: useForm({
                portfolio_id: this.editData ? this.editData.portfolio_id : '',
                period: this.editData ? this.editData.period : '',
                definition_id: this.editData ? this.editData.definition_id : '',
                value: this.editData ? this.editData.value : null,
                source: this.editData ? this.editData.source : 'Manual Entry',
                notes: this.editData ? this.editData.notes : '',
            }),
        }
    },
    computed: {
        selectedMetricName() {
            if (!this.form.definition_id) return 'Metric';
            const definition = this.definitions.find(d => d.id == this.form.definition_id);
            return definition ? definition.name : 'Metric';
        },
        selectedMetricCode() {
            if (!this.form.definition_id) return '';
            const definition = this.definitions.find(d => d.id == this.form.definition_id);
            return definition ? definition.code : '';
        }
    },
    methods: {
        submit() {
            if (!this.form.portfolio_id || !this.form.period || !this.form.definition_id) {
                alert('Please fill in all required fields.');
                return;
            }

            if (this.editData) {
                // UPDATE request
                this.form.put(route('credit-loss-data.update', this.editData.id), {
                    onSuccess: () => {
                        this.$inertia.visit(route('credit-loss-data.index'))
                    }
                });
            } else {
                // CREATE request
                this.form.post(route('credit-loss-data.store'), {
                    onSuccess: () => {
                        this.$inertia.visit(route('credit-loss-data.index'))
                    }
                });
            }
        },

        
        updateInputConfig() {
            const metricCode = this.selectedMetricCode;
            
            const configs = {
                'PD': {
                    type: 'number',
                    step: '0.0001',
                    min: '0',
                    max: '1',
                    placeholder: '0.0500',
                    suffix: '',
                    description: 'Probability of Default (0-1, e.g., 0.05 for 5%)',
                    class: '',
                    required: false
                },
                'LGD': {
                    type: 'number',
                    step: '0.0001',
                    min: '0',
                    max: '1',
                    placeholder: '0.4500',
                    suffix: '',
                    description: 'Loss Given Default (0-1, e.g., 0.45 for 45%)',
                    class: '',
                    required: false
                },
                'ECL': {
                    type: 'number',
                    step: '0.01',
                    min: '0',
                    placeholder: '0.00',
                    suffix: '',
                    description: 'Expected Credit Loss in currency',
                    class: '',
                    required: false
                },
                'NPL': {
                    type: 'number',
                    step: '0.01',
                    min: '0',
                    placeholder: '0.00',
                    suffix: '',
                    description: 'Non-Performing Loans in percentage',
                    class: '',
                    required: false
                },
                'EAD': {
                    type: 'number',
                    step: '0.01',
                    min: '0',
                    placeholder: '0.00',
                    suffix: '',
                    description: 'Exposure at Default in currency',
                    class: '',
                    required: false
                },
                // 'STAGE': {
                //     type: 'number',
                //     step: '1',
                //     min: '1',
                //     max: '3',
                //     placeholder: '1',
                //     suffix: '',
                //     description: 'IFRS 9 Stage (1, 2, or 3)',
                //     class: '',
                //     required: false
                // },
                'CREDIT_RATING': {
                    type: 'text',
                    step: null,
                    min: null,
                    max: null,
                    placeholder: 'e.g., AAA, AA, A, BBB',
                    suffix: '',
                    description: 'Credit rating or score',
                    class: '',
                    required: false
                }
            };

            this.inputConfig = configs[metricCode] || {
                type: 'number',
                step: '0.0001',
                min: null,
                max: null,
                placeholder: 'Enter value',
                suffix: '',
                description: 'Enter the metric value',
                class: '',
                required: false
            };
        }
    },
    mounted() {
        // Set default period to current month
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        this.form.period = `${year}-${month}`;
    }
}
</script>