<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Add New Credit Loss Record
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6">
                        <!-- Form Header -->
                        <div class="mb-6">
                            <h3 class="text-lg font-medium text-gray-900">Add Credit Loss Data</h3>
                            <p class="mt-1 text-sm text-gray-600" v-if="form.portfolio_id">
                                For {{ getPortfolioName(form.portfolio_id) }} portfolio
                            </p>
                        </div>

                        <form @submit.prevent="submit">
                            <!-- Portfolio & Period -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <jet-label for="portfolio" value="Portfolio *" />
                                    <select
                                        id="portfolio_id"
                                        v-model="form.portfolio_id"
                                        required
                                        class="mt-1 block w-full rounded-md border-gray-300 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <jet-input-error :message="form.errors.period" class="mt-2" />
                                </div>
                            </div>

                            <!-- Data Selection Toggle - THIS IS THE IMPORTANT SECTION -->
                            <div class="mb-6">
                                <label class="block text-sm font-medium text-gray-700 mb-3">Select Data to Input</label>
                                <div class="flex flex-wrap gap-3">
                                    <label v-for="field in availableFields" :key="field.name" class="inline-flex items-center">
                                        <input
                                            type="checkbox"
                                            :value="field.name"
                                            v-model="selectedFields"
                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        <span class="ml-2 text-sm text-gray-700">{{ field.label }}</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Credit Risk Parameters -->
                            <div v-if="showSection('credit_risk')" class="mb-6">
                                <h4 class="text-md font-medium text-gray-900 mb-4 border-b pb-2">Credit Risk Parameters</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div v-if="showField('pd_value')">
                                        <jet-label for="pd_value" value="Probability of Default (PD)" />
                                        <div class="mt-1 relative rounded-md shadow-sm">
                                            <input
                                                id="pd_value"
                                                type="number"
                                                step="0.0001"
                                                min="0"
                                                max="1"
                                                v-model="form.pd_value"
                                                class="block w-full rounded-md border-gray-300 pr-10 focus:border-indigo-500 focus:ring-indigo-500"
                                                placeholder="0.0000"
                                            />
                                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500 sm:text-sm">%</span>
                                            </div>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">Value between 0 and 1 (e.g., 0.025 for 2.5%)</p>
                                        <jet-input-error :message="form.errors.pd_value" class="mt-2" />
                                    </div>

                                    <div v-if="showField('lgd_value')">
                                        <jet-label for="lgd_value" value="Loss Given Default (LGD)" />
                                        <div class="mt-1 relative rounded-md shadow-sm">
                                            <input
                                                id="lgd_value"
                                                type="number"
                                                step="0.0001"
                                                min="0"
                                                max="1"
                                                v-model="form.lgd_value"
                                                class="block w-full rounded-md border-gray-300 pr-10 focus:border-indigo-500 focus:ring-indigo-500"
                                                placeholder="0.0000"
                                            />
                                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500 sm:text-sm">%</span>
                                            </div>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">Value between 0 and 1 (e.g., 0.45 for 45%)</p>
                                        <jet-input-error :message="form.errors.lgd_value" class="mt-2" />
                                    </div>
                                </div>
                            </div>

                            <!-- Financial Values -->
                            <div v-if="showSection('financial')" class="mb-6">
                                <h4 class="text-md font-medium text-gray-900 mb-4 border-b pb-2">Financial Values</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div v-if="showField('ecl_value')">
                                        <jet-label for="ecl_value" value="Expected Credit Loss (ECL)" />
                                        <div class="mt-1 relative rounded-md shadow-sm">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500 sm:text-sm">$</span>
                                            </div>
                                            <input
                                                id="ecl_value"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                v-model="form.ecl_value"
                                                class="block w-full pl-7 rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                                placeholder="0.00"
                                            />
                                        </div>
                                        <jet-input-error :message="form.errors.ecl_value" class="mt-2" />
                                    </div>

                                    <div v-if="showField('npl_value')">
                                        <jet-label for="npl_value" value="Non-Performing Loans (NPL)" />
                                        <div class="mt-1 relative rounded-md shadow-sm">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500 sm:text-sm">$</span>
                                            </div>
                                            <input
                                                id="npl_value"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                v-model="form.npl_value"
                                                class="block w-full pl-7 rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                                placeholder="0.00"
                                            />
                                        </div>
                                        <jet-input-error :message="form.errors.npl_value" class="mt-2" />
                                    </div>

                                    <div v-if="showField('ead_value')">
                                        <jet-label for="ead_value" value="Exposure at Default (EAD)" />
                                        <div class="mt-1 relative rounded-md shadow-sm">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500 sm:text-sm">$</span>
                                            </div>
                                            <input
                                                id="ead_value"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                v-model="form.ead_value"
                                                class="block w-full pl-7 rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                                placeholder="0.00"
                                            />
                                        </div>
                                        <jet-input-error :message="form.errors.ead_value" class="mt-2" />
                                    </div>
                                </div>
                            </div>

                            <!-- Classification & Additional Info -->
                            <div v-if="showSection('classification')" class="mb-6">
                                <h4 class="text-md font-medium text-gray-900 mb-4 border-b pb-2">Classification & Additional Information</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div v-if="showField('stage')">
                                        <jet-label for="stage" value="IFRS 9 Stage" />
                                        <select
                                            id="stage"
                                            v-model="form.stage"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="">Select Stage</option>
                                            <option value="1">Stage 1 - Performing</option>
                                            <option value="2">Stage 2 - Underperforming</option>
                                            <option value="3">Stage 3 - Non-performing</option>
                                        </select>
                                        <jet-input-error :message="form.errors.stage" class="mt-2" />
                                    </div>

                                    <div v-if="showField('credit_rating')">
                                        <jet-label for="credit_rating" value="Credit Rating" />
                                        <select
                                            id="credit_rating"
                                            v-model="form.credit_rating"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="">Select Rating</option>
                                            <option value="AAA">AAA - Highest Quality</option>
                                            <option value="AA">AA - High Quality</option>
                                            <option value="A">A - Upper Medium Grade</option>
                                            <option value="BBB">BBB - Medium Grade</option>
                                            <option value="BB">BB - Lower Medium Grade</option>
                                            <option value="B">B - Speculative</option>
                                            <option value="CCC">CCC - Poor Quality</option>
                                            <option value="CC">CC - Highly Speculative</option>
                                            <option value="C">C - Lowest Quality</option>
                                            <option value="D">D - In Default</option>
                                        </select>
                                        <jet-input-error :message="form.errors.credit_rating" class="mt-2" />
                                    </div>
                                </div>
                            </div>

                            <!-- Additional Financials -->
                            <div v-if="showSection('additional_financial')" class="mb-6">
                                <h4 class="text-md font-medium text-gray-900 mb-4 border-b pb-2">Additional Financial Values</h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div v-if="showField('provision_value')">
                                        <jet-label for="provision_value" value="Provision Value" />
                                        <div class="mt-1 relative rounded-md shadow-sm">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500 sm:text-sm">$</span>
                                            </div>
                                            <input
                                                id="provision_value"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                v-model="form.provision_value"
                                                class="block w-full pl-7 rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                                placeholder="0.00"
                                            />
                                        </div>
                                        <jet-input-error :message="form.errors.provision_value" class="mt-2" />
                                    </div>

                                    <div v-if="showField('write_off_value')">
                                        <jet-label for="write_off_value" value="Write-off Value" />
                                        <div class="mt-1 relative rounded-md shadow-sm">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500 sm:text-sm">$</span>
                                            </div>
                                            <input
                                                id="write_off_value"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                v-model="form.write_off_value"
                                                class="block w-full pl-7 rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                                placeholder="0.00"
                                            />
                                        </div>
                                        <jet-input-error :message="form.errors.write_off_value" class="mt-2" />
                                    </div>

                                    <div v-if="showField('recovery_value')">
                                        <jet-label for="recovery_value" value="Recovery Value" />
                                        <div class="mt-1 relative rounded-md shadow-sm">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500 sm:text-sm">$</span>
                                            </div>
                                            <input
                                                id="recovery_value"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                v-model="form.recovery_value"
                                                class="block w-full pl-7 rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                                placeholder="0.00"
                                            />
                                        </div>
                                        <jet-input-error :message="form.errors.recovery_value" class="mt-2" />
                                    </div>
                                </div>
                            </div>

                            <!-- Scenario Information -->
                            <div v-if="showSection('scenario')" class="mb-6">
                                <h4 class="text-md font-medium text-gray-900 mb-4 border-b pb-2">Scenario Information</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div v-if="showField('scenario_profile_id')">
                                        <jet-label for="scenario_profile_id" value="Scenario Profile" />
                                        <select
                                            id="scenario_profile_id"
                                            v-model="form.scenario_profile_id"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="">Select Scenario Profile</option>
                                            <option v-for="profile in profiles" :key="profile.id" :value="profile.id">
                                                {{ profile.name }}
                                            </option>
                                        </select>
                                        <jet-input-error :message="form.errors.scenario_profile_id" class="mt-2" />
                                    </div>

                                    <div v-if="showField('scenario_id')">
                                        <jet-label for="scenario_id" value="Scenario" />
                                        <select
                                            id="scenario_id"
                                            v-model="form.scenario_id"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="">Select Scenario</option>
                                            <option v-for="scenario in filteredScenarios" :key="scenario.id" :value="scenario.id">
                                                {{ scenario.name }}
                                            </option>
                                        </select>
                                        <jet-input-error :message="form.errors.scenario_id" class="mt-2" />
                                    </div>

                                    <div v-if="showField('is_forecast')" class="flex items-center">
                                        <input
                                            id="is_forecast"
                                            type="checkbox"
                                            v-model="form.is_forecast"
                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        />
                                        <jet-label for="is_forecast" value="This is a forecast data" class="ml-2" />
                                        <jet-input-error :message="form.errors.is_forecast" class="mt-2" />
                                    </div>
                                </div>
                            </div>

                            <!-- Source & Notes -->
                            <div class="mb-6">
                                <h4 class="text-md font-medium text-gray-900 mb-4 border-b pb-2">Additional Information</h4>
                                <div class="grid grid-cols-1 gap-6">
                                    <div v-if="showField('source')">
                                        <jet-label for="source" value="Data Source" />
                                        <select
                                            id="source"
                                            v-model="form.source"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="">Select Source</option>
                                            <option value="Manual Entry">Manual Entry</option>
                                            <option value="System Generated">System Generated</option>
                                            <option value="External Data">External Data</option>
                                            <option value="Regulatory Report">Regulatory Report</option>
                                            <option value="Internal Model">Internal Model</option>
                                        </select>
                                        <jet-input-error :message="form.errors.source" class="mt-2" />
                                    </div>

                                    <div v-if="showField('notes')">
                                        <jet-label for="notes" value="Notes" />
                                        <textarea
                                            id="notes"
                                            v-model="form.notes"
                                            rows="3"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            placeholder="Additional notes or comments..."
                                        ></textarea>
                                        <jet-input-error :message="form.errors.notes" class="mt-2" />
                                    </div>
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
                                <jet-button
                                    :class="{ 'opacity-25': form.processing }"
                                    :disabled="form.processing"
                                >
                                    Create Record
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
        profiles: Array,
    },
    data() {
        return {
            selectedFields: ['period', 'ecl_value', 'npl_value', 'ead_value'], // Default selected fields
            availableFields: [
                { name: 'pd_value', label: 'PD Value', section: 'credit_risk' },
                { name: 'lgd_value', label: 'LGD Value', section: 'credit_risk' },
                { name: 'ecl_value', label: 'ECL Value', section: 'financial' },
                { name: 'npl_value', label: 'NPL Value', section: 'financial' },
                { name: 'ead_value', label: 'EAD Value', section: 'financial' },
                { name: 'stage', label: 'IFRS 9 Stage', section: 'classification' },
                { name: 'credit_rating', label: 'Credit Rating', section: 'classification' },
                { name: 'provision_value', label: 'Provision Value', section: 'additional_financial' },
                { name: 'write_off_value', label: 'Write-off Value', section: 'additional_financial' },
                { name: 'recovery_value', label: 'Recovery Value', section: 'additional_financial' },
                { name: 'scenario_profile_id', label: 'Scenario Profile', section: 'scenario' },
                { name: 'scenario_id', label: 'Scenario', section: 'scenario' },
                { name: 'is_forecast', label: 'Forecast Data', section: 'scenario' },
                { name: 'source', label: 'Data Source', section: 'additional' },
                { name: 'notes', label: 'Notes', section: 'additional' },
            ],
            form: useForm({
                portfolio_id: '',
                period: '',
                ecl_value: null,
                npl_value: null,
                pd_value: null,
                lgd_value: null,
                ead_value: null,
                stage: '',
                credit_rating: '',
                provision_value: null,
                write_off_value: null,
                recovery_value: null,
                scenario_profile_id: '',
                scenario_id: '',
                is_forecast: false,
                source: 'Manual Entry',
                notes: '',
            })
        }
    },
    computed: {
        filteredScenarios() {
            if (!this.form.scenario_profile_id) return [];
            const profile = this.profiles.find(p => p.id == this.form.scenario_profile_id);
            return profile ? profile.scenarios : [];
        }
    },
    methods: {
       submit() {
            if (!this.form.portfolio_id) {
                alert('Please select a portfolio.');
                return;
            }

            this.form.post(route('credit-loss-data.store'), {
                onSuccess: () => {
                    // Form will automatically redirect on success
                },
            })
        },
        
        showField(fieldName) {
            return this.selectedFields.includes(fieldName);
        },
        showSection(section) {
            return this.availableFields.some(field => 
                field.section === section && this.selectedFields.includes(field.name)
            );
        },
        getPortfolioName(portfolioId) {
            const portfolio = this.portfolios.find(p => p.id == portfolioId);
            return portfolio ? portfolio.name : '';
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