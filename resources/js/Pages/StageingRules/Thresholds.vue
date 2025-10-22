<template>
    <app-layout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        IFRS 9 Staging Rules - Quantitative Thresholds
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">Configure days past due thresholds for automatic stage classification</p>
                </div>
                <div class="flex items-center space-x-2">
                    <div class="px-3 py-1 bg-green-100 text-green-800 text-xs font-medium rounded-full">
                        IFRS 9 Compliant
                    </div>
                </div>
            </div>
        </template>
        
        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Configuration Form -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4">
                    <h3 class="text-lg font-semibold text-white flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h4a1 1 0 010 2H6.414l2.293 2.293a1 1 0 01-1.414 1.414L5 6.414V8a1 1 0 01-2 0V4zm9 1a1 1 0 010-2h4a1 1 0 011 1v4a1 1 0 01-2 0V6.414l-2.293 2.293a1 1 0 11-1.414-1.414L13.586 5H12z" clip-rule="evenodd"></path>
                        </svg>
                        Threshold Configuration
                    </h3>
                    <p class="mt-1 text-blue-100 text-sm">Set quantitative rules for loan staging based on delinquency periods</p>
                </div>
                
                <form @submit.prevent="save" class="p-6 space-y-6">
                    <!-- Institution Type -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <jet-label class="text-sm font-medium text-gray-900">
                            Institution Type
                        </jet-label>
                        <p class="text-xs text-gray-500 mt-1">Identifier for this set of staging rules</p>
                        <div class="mt-3 relative">
                            <input 
                                v-model="form.institution_type" 
                                type="text" 
                                class="input-enhanced" 
                                placeholder="e.g., default, commercial, retail"
                            />
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <svg class="h-4 w-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2h-3a1 1 0 01-1-1v-2a1 1 0 00-1-1H9a1 1 0 00-1 1v2a1 1 0 01-1 1H4a1 1 0 110-2V4z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Threshold Configuration -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Stage 1 Threshold -->
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4 transition-all hover:shadow-md">
                            <div class="flex items-center mb-3">
                                <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white font-bold text-sm mr-3">
                                    1
                                </div>
                                <div>
                                    <jet-label class="text-green-900 font-semibold">
                                        Stage 1 Threshold
                                    </jet-label>
                                    <p class="text-xs text-green-700">12-month ECL (Performing loans)</p>
                                </div>
                            </div>
                            <div class="relative">
                                <input 
                                    v-model.number="form.stage_1_threshold" 
                                    type="number" 
                                    min="0" 
                                    max="365"
                                    class="input-threshold text-right pr-12" 
                                    placeholder="30"
                                />
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                    <span class="text-green-600 text-sm font-medium">days</span>
                                </div>
                            </div>
                            <p class="text-xs text-green-600 mt-2">Loans with ≤ {{ form.stage_1_threshold || 30 }} days past due</p>
                        </div>
                        
                        <!-- Stage 3 Threshold -->
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 transition-all hover:shadow-md">
                            <div class="flex items-center mb-3">
                                <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center text-white font-bold text-sm mr-3">
                                    3
                                </div>
                                <div>
                                    <jet-label class="text-red-900 font-semibold">
                                        Stage 3 Threshold
                                    </jet-label>
                                    <p class="text-xs text-red-700">Lifetime ECL (Credit-impaired)</p>
                                </div>
                            </div>
                            <div class="relative">
                                <input 
                                    v-model.number="form.stage_3_threshold" 
                                    type="number" 
                                    min="0" 
                                    max="365"
                                    class="input-threshold text-right pr-12" 
                                    placeholder="90"
                                />
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                    <span class="text-red-600 text-sm font-medium">days</span>
                                </div>
                            </div>
                            <p class="text-xs text-red-600 mt-2">Loans with ≥ {{ form.stage_3_threshold || 90 }} days past due</p>
                        </div>
                    </div>
                    
                    <!-- Stage 2 Info -->
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                        <div class="flex items-center mb-2">
                            <div class="w-8 h-8 bg-amber-500 rounded-full flex items-center justify-center text-white font-bold text-sm mr-3">
                                2
                            </div>
                            <div>
                                <h4 class="text-amber-900 font-semibold">Stage 2 (Calculated)</h4>
                                <p class="text-xs text-amber-700">Lifetime ECL (Significant increase in credit risk)</p>
                            </div>
                        </div>
                        <p class="text-sm text-amber-800 ml-11">
                            Loans between {{ form.stage_1_threshold || 30 }}+ and {{ (form.stage_3_threshold || 90) - 1 }} days past due
                        </p>
                    </div>
                    
                    <!-- Save Button -->
                    <div class="flex justify-end pt-4 border-t border-gray-200">
                        <button
                            type="submit"
                            :disabled="processing || !isValid"
                            class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200"
                        >
                            <svg v-if="processing" class="animate-spin -ml-1 mr-3 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg v-else class="-ml-1 mr-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            {{ processing ? 'Saving...' : 'Save Thresholds' }}
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Information Panel -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                        IFRS 9 Staging Framework
                    </h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                            <h4 class="font-semibold text-green-900 mb-2">Stage 1</h4>
                            <p class="text-sm text-green-700">12-month expected credit losses for financial instruments that have not had a significant increase in credit risk since initial recognition.</p>
                        </div>
                        <div class="bg-amber-50 rounded-lg p-4 border border-amber-200">
                            <h4 class="font-semibold text-amber-900 mb-2">Stage 2</h4>
                            <p class="text-sm text-amber-700">Lifetime expected credit losses for financial instruments that have had a significant increase in credit risk since initial recognition.</p>
                        </div>
                        <div class="bg-red-50 rounded-lg p-4 border border-red-200">
                            <h4 class="font-semibold text-red-900 mb-2">Stage 3</h4>
                            <p class="text-sm text-red-700">Lifetime expected credit losses for financial instruments that are credit-impaired.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <teleport to="head">
            <title>IFRS 9 Staging Rules - Thresholds</title>
        </teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetButton from '@/Jetstream/Button.vue'
import JetLabel from '@/Jetstream/Label.vue'

export default {
    props: {
        rule: Object,
    },
    components: { AppLayout, JetButton, JetLabel },
    data() {
        return {
            form: {
                institution_type: this.rule?.institution_type || 'default',
                stage_1_threshold: this.rule?.stage_1_threshold || 30,
                stage_3_threshold: this.rule?.stage_3_threshold || 90,
            },
            processing: false,
        }
    },
    computed: {
        isValid() {
            return this.form.institution_type && 
                   this.form.stage_1_threshold >= 0 && 
                   this.form.stage_3_threshold >= 0 &&
                   this.form.stage_1_threshold < this.form.stage_3_threshold
        }
    },
    methods: {
        save() {
            if (!this.isValid) return
            this.processing = true
            this.$inertia.post(this.route('stageing-rules.store'), this.form, { 
                onFinish: () => this.processing = false,
                onSuccess: () => {
                    this.$toast.success('Staging rules updated successfully!')
                },
                onError: (errors) => {
                    console.error('Validation errors:', errors)
                    this.$toast.error('Please check your input and try again.')
                }
            })
        }
    }
}
</script>

<style scoped>
.input-enhanced {
    @apply mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200;
}

.input-threshold {
    @apply block w-full px-3 py-2 border-2 border-transparent bg-white rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 font-medium;
}

.input-threshold:focus {
    @apply border-blue-300 bg-blue-50;
}

.bg-gradient-to-r {
    background-image: linear-gradient(to right, var(--tw-gradient-stops));
}
</style>
