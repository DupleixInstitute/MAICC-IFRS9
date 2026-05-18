<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                FLI External Calculations
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Step 1: Configuration & Parameters -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">1. Configuration & Parameters</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Scenario Set Selection -->
                        <div>
                            <jet-label for="scenario_set" value="Scenario Set"/>
                            <select id="scenario_set" v-model="form.scenario_set_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                                <option value="" disabled>Select Scenario Set</option>
                                <option v-for="set in scenarioSets" :key="set.id" :value="set.id">
                                    {{ set.name }}
                                </option>
                            </select>
                            <jet-input-error :message="errors.scenario_set_id" class="mt-2"/>
                        </div>

                        <!-- Reporting Date -->
                        <div>
                            <jet-label for="reporting_date" value="Reporting Date (YYYY-MM)"/>
                            <jet-input id="reporting_date" type="month" class="block w-full" v-model="form.reporting_period"/>
                            <jet-input-error :message="errors.reporting_period" class="mt-2"/>
                        </div>

                        <!-- Number of Forecasting Periods -->
                        <div>
                            <jet-label for="num_periods" value="Number of Forecasting Periods"/>
                            <jet-input id="num_periods" type="number" min="1" max="120" class="block w-full" v-model="form.number_of_forecasting_periods"/>
                        </div>

                        <!-- Forecasting Period Length -->
                        <div>
                            <jet-label for="period_length" value="Forecasting Period Length (Months)"/>
                            <jet-input id="period_length" type="number" min="1" max="12" class="block w-full" v-model="form.forecasting_period_length_months"/>
                        </div>

                        <!-- Economic Statistic -->
                        <div>
                            <jet-label for="economic_stat" value="Economic Data Statistic"/>
                            <select id="economic_stat" v-model="form.economic_data_statistic" class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                                <option value="">Select Statistic</option>
                                <option v-for="stat in economicStatistics" :key="stat.value" :value="stat.value">
                                    {{ stat.label }}
                                </option>
                            </select>
                        </div>

                        <!-- PD Proxy Statistic -->
                        <div>
                            <jet-label for="pd_proxy" value="PD Proxy Statistic"/>
                            <select id="pd_proxy" v-model="form.pd_proxy_statistic" class="mt-1 block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                                <option value="">Select Proxy</option>
                                <option v-for="stat in pdProxyStatistics" :key="stat.value" :value="stat.value">
                                    {{ stat.label }}
                                </option>
                            </select>
                        </div>

                        <!-- Base Forecast Period -->
                        <div>
                            <jet-label for="base_forecast" value="Base Forecast Period (YYYY-MM)"/>
                            <jet-input id="base_forecast" type="month" class="block w-full" v-model="form.base_forecast_period"/>
                        </div>

                        <!-- Base Macro Data Value -->
                        <div>
                            <jet-label for="base_macro" value="Base Macro Data Value"/>
                            <jet-input id="base_macro" type="number" step="0.0001" class="block w-full" v-model="form.base_macro_data_value"/>
                        </div>

                        <!-- Base PD Proxy Value -->
                        <div>
                            <jet-label for="base_pd" value="Base PD Proxy Value"/>
                            <jet-input id="base_pd" type="number" step="0.0001" min="0" max="100" class="block w-full" v-model="form.base_pd_proxy_value"/>
                        </div>
                    </div>

                    <!-- Regression Model Section -->
                    <div class="mt-6 border-2 border-indigo-200 rounded-lg p-4 bg-indigo-50">
                        <h4 class="text-md font-medium text-indigo-900 mb-4">Regression Model Parameters</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Regression Slope -->
                            <div>
                                <jet-label for="slope" value="Regression Slope"/>
                                <jet-input id="slope" type="number" step="0.0001" class="block w-full" v-model="form.regression_slope"/>
                                <p class="mt-1 text-xs text-gray-600">Coefficient for macro variable</p>
                            </div>

                            <!-- Regression Intercept -->
                            <div>
                                <jet-label for="intercept" value="Regression Intercept"/>
                                <jet-input id="intercept" type="number" step="0.0001" class="block w-full" v-model="form.regression_intercept"/>
                                <p class="mt-1 text-xs text-gray-600">Constant term in regression</p>
                            </div>
                        </div>
                    </div>

                    <!-- Supporting Documentation -->
                    <div class="mt-6 border-2 border-gray-200 rounded-lg p-4 bg-gray-50">
                        <h4 class="text-md font-medium text-gray-900 mb-4">Supporting Documentation</h4>
                        <div>
                            <jet-label for="attachment" value="Attach File (Optional)"/>
                            <input 
                                type="file" 
                                id="attachment" 
                                @change="handleFileUpload"
                                accept=".pdf,.xlsx,.xls,.csv,.doc,.docx"
                                class="mt-1 block w-full text-sm text-gray-500
                                    file:mr-4 file:py-2 file:px-4
                                    file:rounded file:border-0
                                    file:text-sm file:font-semibold
                                    file:bg-indigo-50 file:text-indigo-700
                                    hover:file:bg-indigo-100
                                    cursor-pointer"
                            />
                            <p class="mt-1 text-xs text-gray-600">Upload regression analysis, macro forecasts, or supporting documentation (Max 10MB)</p>
                            <div v-if="form.attachment" class="mt-2 flex items-center text-sm text-green-600">
                                <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                File attached: {{ form.attachment.name }}
                                <button @click="removeFile" class="ml-2 text-red-600 hover:text-red-800">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="Object.keys(errors).length" class="mt-4 bg-red-50 border border-red-200 rounded p-4">
                        <h4 class="font-medium text-red-800 mb-2">Please fix the following errors:</h4>
                        <ul class="text-sm text-red-700 list-disc list-inside">
                            <li v-for="(errorList, field) in errors" :key="field">
                                <span v-for="(error, index) in errorList" :key="index">{{ error }}</span>
                            </li>
                        </ul>
                    </div>

                    <div class="mt-4 flex justify-end">
                        <jet-button @click.native="saveParameters" :class="{ 'opacity-25': loading }" :disabled="loading">
                            {{ loading ? 'Processing...' : 'Save Parameters & Generate Forecast Table' }}
                        </jet-button>
                    </div>
                </div>

                <!-- Step 2: Forecast Generation -->
                <div v-if="showForecastTable" class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">2. Forecast Generation</h3>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Weighted Macro Value</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Predicted Value</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">FLI Adjustment</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr v-for="(row, index) in forecastData" :key="index">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ row.period_window }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ row.forecast_period }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <jet-input type="number" step="0.0001" class="w-32" v-model="row.weighted_macro_value" @input="recalculateRow(index)"/>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ formatNumber(row.predicted_value) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span :class="{'text-green-600': row.fli_adj < 0, 'text-red-600': row.fli_adj > 0}">
                                            {{ formatPercent(row.fli_adj) }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 flex justify-end">
                        <jet-button @click.native="saveAdjustments" class="bg-maiic-600 hover:bg-maiic-700" :disabled="savingAdjustments">
                            {{ savingAdjustments ? 'Saving...' : 'Save Adjustments' }}
                        </jet-button>
                    </div>
                </div>

                <!-- Step 3: Update Loanbook -->
                <div v-if="adjustmentsSaved" class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">3. Apply to Loanbook</h3>
                    
                    <div class="text-sm text-gray-600 mb-4">
                        Adjustments have been saved. You can now apply these FLI adjustments to the loan book.
                        This process will update the <code>pd_post_fli_adj</code> and <code>fli_adj</code> columns for all loans in the selected reporting period.
                    </div>

                    <div v-if="updateStats" class="bg-green-50 border border-green-200 rounded p-4 mb-4">
                        <h4 class="font-medium text-green-800 mb-3">Update Complete!</h4>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-gray-600">Total Loans Processed:</p>
                                <p class="text-lg font-semibold text-gray-800">{{ updateStats.total_loans }}</p>
                            </div>
                            <div>
                                <p class="text-gray-600">Loans Updated:</p>
                                <p class="text-lg font-semibold text-green-600">{{ updateStats.updated_loans }}</p>
                            </div>
                            <div>
                                <p class="text-gray-600">Stage 1 Updated:</p>
                                <p class="text-lg font-semibold text-blue-600">{{ updateStats.stage_1 }}</p>
                            </div>
                            <div>
                                <p class="text-gray-600">Stage 2 Updated:</p>
                                <p class="text-lg font-semibold text-yellow-600">{{ updateStats.stage_2 }}</p>
                            </div>
                            <div>
                                <p class="text-gray-600">Stage 3 Skipped:</p>
                                <p class="text-lg font-semibold text-red-600">{{ updateStats.stage_3 }}</p>
                            </div>
                            <div>
                                <p class="text-gray-600">No Matching FLI:</p>
                                <p class="text-lg font-semibold text-orange-600">{{ updateStats.errors }}</p>
                            </div>
                            <div>
                                <p class="text-gray-600">Floored at 0%:</p>
                                <p class="text-sm text-gray-700">{{ updateStats.floored }}</p>
                            </div>
                            <div>
                                <p class="text-gray-600">Capped at 100%:</p>
                                <p class="text-sm text-gray-700">{{ updateStats.capped }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <jet-button @click.native="updateLoanBook" class="bg-maiic-600 hover:bg-maiic-700" :disabled="updatingLoanbook">
                            {{ updatingLoanbook ? 'Updating...' : 'Update Loanbook' }}
                        </jet-button>
                    </div>
                </div>

            </div>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetButton from "@/Jetstream/Button.vue"
import JetInput from "@/Jetstream/Input.vue"
import JetInputError from "@/Jetstream/InputError.vue"
import JetLabel from "@/Jetstream/Label.vue"
import axios from 'axios'

export default {
    props: {
        scenarioSets: Array,
        economicStatistics: Array,
        pdProxyStatistics: Array
    },
    components: {
        AppLayout,
        JetButton,
        JetInput,
        JetLabel,
        JetInputError
    },
    data() {
        return {
            form: {
                reporting_period: '',
                scenario_set_id: '',
                number_of_forecasting_periods: 12,
                forecasting_period_length_months: 1,
                economic_data_statistic: '',
                pd_proxy_statistic: '',
                base_forecast_period: '',
                base_macro_data_value: 0,
                base_pd_proxy_value: 0,
                regression_slope: 0,
                regression_intercept: 0,
                attachment: null,
            },
            parameterId: null,
            showForecastTable: false,
            forecastData: [],
            savingAdjustments: false,
            adjustmentsSaved: false,
            updatingLoanbook: false,
            updateStats: null,
            loading: false,
            errors: {}
        }
    },
    methods: {
        handleFileUpload(event) {
            const file = event.target.files[0];
            if (file) {
                // Validate file size (10MB max)
                if (file.size > 10 * 1024 * 1024) {
                    this.$toast?.error('File size exceeds 10MB limit');
                    event.target.value = '';
                    return;
                }
                this.form.attachment = file;
                this.$toast?.success('File attached successfully');
            }
        },
        removeFile() {
            this.form.attachment = null;
            document.getElementById('attachment').value = '';
            this.$toast?.info('File removed');
        },
        async saveParameters() {
            this.loading = true;
            this.errors = {};
            
            try {
                const response = await axios.post(route('fli.external.save-parameters'), this.form);
                
                if (response.data.success) {
                    this.parameterId = response.data.parameter_id;
                    this.$toast?.success('Parameters saved successfully!');
                    await this.generateForecasts();
                } else {
                    this.$toast?.error('Failed to save parameters: ' + response.data.message);
                }
            } catch (error) {
                console.error('Error saving parameters:', error);
                if (error.response && error.response.data.errors) {
                    this.errors = error.response.data.errors;
                }
                this.$toast?.error('Failed to save parameters. Please check your inputs.');
            } finally {
                this.loading = false;
            }
        },
        async generateForecasts() {
            try {
                const response = await axios.post(route('fli.external.generate'), {
                    parameter_id: this.parameterId
                });
                
                if (response.data.success) {
                    this.forecastData = response.data.forecasts.map(f => ({
                        ...f,
                        weighted_macro_data_value: f.weighted_macro_data_value || 0
                    }));
                    this.showForecastTable = true;
                    this.adjustmentsSaved = false;
                    this.updateStats = null;
                    
                    // Calculate all rows initially
                    this.forecastData.forEach((_, index) => {
                        this.recalculateRow(index);
                    });
                    this.$toast?.success('Forecast table generated successfully!');
                } else {
                    this.$toast?.error('Failed to generate forecasts');
                }
            } catch (error) {
                console.error('Error generating forecasts:', error);
                this.$toast?.error('Failed to generate forecasts. Please check your inputs.');
            }
        },
        recalculateRow(index) {
            const row = this.forecastData[index];
            const slope = parseFloat(this.form.regression_slope) || 0;
            const intercept = parseFloat(this.form.regression_intercept) || 0;
            const weightedMacro = parseFloat(row.weighted_macro_value) || 0;
            
            // Calculate Predicted Value
            row.predicted_value = (slope * weightedMacro) + intercept;
            
            // Calculate FLI Adjustment
            // Formula: (Predicted / Base_Predicted) - 1
            // Base Predicted is the predicted value at period 0
            const baseRow = this.forecastData[0];
            const basePredicted = (slope * (parseFloat(baseRow.weighted_macro_value) || 0)) + intercept;
            
            if (basePredicted !== 0) {
                row.fli_adj = (row.predicted_value / basePredicted) - 1;
            } else {
                row.fli_adj = 0;
            }
        },
        async saveAdjustments() {
            this.savingAdjustments = true;
            try {
                // Prepare forecasts data with all required fields
                const forecasts = this.forecastData.map(f => ({
                    forecast_period: f.forecast_period,
                    forecast_window_in_months: f.forecast_window_in_months,
                    weighted_macro_data_value: f.weighted_macro_data_value
                }));

                const response = await axios.post(route('fli.external.save'), {
                    parameter_id: this.parameterId,
                    forecasts: forecasts
                });
                
                if (response.data.success) {
                    this.adjustmentsSaved = true;
                    this.$toast?.success('Adjustments saved successfully! ' + response.data.count + ' periods saved.');
                } else {
                    this.$toast?.error('Failed to save adjustments: ' + response.data.message);
                }
            } catch (error) {
                console.error('Error saving adjustments:', error);
                this.$toast?.error('Failed to save adjustments: ' + (error.response?.data?.message || error.message));
            } finally {
                this.savingAdjustments = false;
            }
        },
        async updateLoanBook() {
            if (!confirm('This will update PD values for all loans in the reporting period. Continue?')) {
                return;
            }
            
            this.updatingLoanbook = true;
            try {
                const response = await axios.post(route('fli.external.update-loanbook'), {
                    reporting_period: this.form.reporting_period,
                    scenario_set_id: this.form.scenario_set_id
                });
                
                if (response.data.success) {
                    this.updateStats = {
                        total_loans: response.data.stats.total_loans,
                        updated_loans: response.data.stats.stage_1_updated + response.data.stats.stage_2_updated,
                        errors: response.data.stats.no_matching_fli,
                        stage_1: response.data.stats.stage_1_updated,
                        stage_2: response.data.stats.stage_2_updated,
                        stage_3: response.data.stats.stage_3_skipped,
                        floored: response.data.stats.floored_at_zero,
                        capped: response.data.stats.capped_at_100
                    };
                    this.$toast?.success(`Loanbook updated successfully! ${this.updateStats.updated_loans} loans updated.`);
                } else {
                    this.$toast?.error('Failed to update loanbook: ' + response.data.message);
                }
            } catch (error) {
                console.error('Error updating loanbook:', error);
                this.$toast?.error('Failed to update loanbook: ' + (error.response?.data?.message || error.message));
            } finally {
                this.updatingLoanbook = false;
            }
        },
        formatNumber(value) {
            return value ? parseFloat(value).toFixed(4) : '0.0000';
        },
        formatPercent(value) {
            return value ? (parseFloat(value) * 100).toFixed(2) + '%' : '0.00%';
        }
    }
}
</script>
