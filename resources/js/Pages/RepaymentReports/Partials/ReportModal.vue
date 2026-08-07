<template>
    <div v-if="show" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">
        <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
            <h2 class="text-lg font-bold mb-4">Generate LGD Payment Report</h2>

            <!-- Portfolio -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Portfolio</label>
                <select v-model="form.portfolio_id" class="w-full border-gray-300 rounded-md shadow-sm">
                    <option value="">Select Portfolio</option>
                    <option v-for="p in portfolios" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
            </div>

            <!-- Date Range -->
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Start Period</label>
                    <input type="month" v-model="form.start_period" class="w-full border-gray-300 rounded-md shadow-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">End Period</label>
                    <input type="month" v-model="form.end_period" class="w-full border-gray-300 rounded-md shadow-sm">
                </div>
            </div>

            <!-- Format -->
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Format</label>
                <select v-model="form.format" class="w-full border-gray-300 rounded-md shadow-sm">
                    <option value="csv">CSV Download</option>
                    <option value="excel">Excel Download</option>
                </select>
            </div>

            <!-- Advanced Options Toggle -->
            <button @click="showAdvanced = !showAdvanced" class="text-sm text-maiic-600 hover:text-maiic-800 mb-4">
                {{ showAdvanced ? 'Hide' : 'Show' }} Advanced Options
            </button>

            <!-- Advanced Options -->
            <div v-if="showAdvanced" class="mb-4 p-4 bg-gray-50 rounded-lg">
                <!-- Specific Calculation -->
                <!-- <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Use Specific Calculation</label>
                    <select v-model="form.calculation_id" class="w-full border-gray-300 rounded-md shadow-sm">
                        <option value="">Latest Calculation</option>
                        <option v-for="calc in recentCalculations" :key="calc.id" :value="calc.id">
                            {{ calc.label }}
                        </option>
                    </select>
                </div> -->

                <!-- Specific Contract -->
                <!-- <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Specific Contract ID</label>
                    <input type="text" v-model="form.contract_id"
                           placeholder="e.g., CONTRACT001"
                           class="w-full border-gray-300 rounded-md shadow-sm">
                </div> -->

                <!-- Exclude Zero Payments -->
                <div>
                    <label class="flex items-center">
                        <input type="checkbox" v-model="form.exclude_zero_payments" class="rounded border-gray-300">
                        <span class="ml-2 text-sm text-gray-600">Exclude periods with zero payments</span>
                    </label>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex justify-end space-x-2">
                <button @click="$emit('close')"
                        class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">
                    Cancel
                </button>

                <button @click="generate"
                        :disabled="loading"
                        class="px-4 py-2 bg-maiic-600 text-white rounded hover:bg-maiic-700 disabled:opacity-50">
                    <span v-if="loading">Generating...</span>
                    <span v-else>Download</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
import { ref, reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';

export default {
    props: {
        show: {
            type: Boolean,
            required: true
        },
        portfolios: {
            type: Array,
            required: true
        },
        recentCalculations: {
            type: Array,
            default: () => []
        },
        defaultPortfolio: {
            type: [Number, String],
            default: null
        },
        defaultStart: {
            type: [String, Date],
            default: null
        },
        defaultEnd: {
            type: [String, Date],
            default: null
        },
        calculationId: {
            type: [Number, String],
            default: null
        }
    },
    emits: ['close', 'generate'],
    setup(props, { emit }) {
        const loading = ref(false);
        const showAdvanced = ref(false);

        // Helper to format date for month input
        function formatDateForInput(date) {
            const d = new Date(date);
            return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
        }

        const form = reactive({
            portfolio_id: props.defaultPortfolio || '',
            start_period: props.defaultStart ? formatDateForInput(props.defaultStart) : '',
            end_period: props.defaultEnd ? formatDateForInput(props.defaultEnd) : '',
            format: 'csv',
            calculation_id: props.calculationId || '',
            contract_id: '',
            exclude_zero_payments: false
        });

        const generate = () => {
            if (!form.portfolio_id || !form.start_period || !form.end_period) {
                alert('Please select portfolio and period range');
                return;
            }

            if (form.start_period > form.end_period) {
                alert('Start period cannot be after end period');
                return;
            }

            loading.value = true;

            // Create a form for file download
            const formElement = document.createElement('form');
            formElement.method = 'POST';
            formElement.action = route('lgd-payment-report.generate');
            formElement.target = '_blank'; // Open in new tab to avoid blocking

            // Add CSRF token
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            if (csrfToken) {
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_token';
                csrfInput.value = csrfToken.getAttribute('content');
                formElement.appendChild(csrfInput);
            }

            // Add form data
            Object.keys(form).forEach(key => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                // Handle boolean values properly
                if (typeof form[key] === 'boolean') {
                    input.value = form[key] ? '1' : '0';
                } else {
                    input.value = form[key];
                }
                formElement.appendChild(input);
            });

            // Submit form and handle response
            document.body.appendChild(formElement);
            formElement.submit();
            document.body.removeChild(formElement);

            // Show progress feedback
            const progressMessage = `Generating ${form.format.toUpperCase()} report... This may take a while for large datasets.`;

            // Reset loading after a reasonable delay for large exports
            setTimeout(() => {
                loading.value = false;
                emit('generate');
                emit('close');

                // Show completion message
                const completionDiv = document.createElement('div');
                completionDiv.className = 'fixed top-4 right-4 bg-maiic-100 border border-maiic-400 text-maiic-700 px-4 py-3 rounded z-50';
                completionDiv.innerHTML = `
                    <strong>Export Started!</strong><br>
                    ${progressMessage}<br>
                    <small>The download will begin automatically.</small>
                `;
                document.body.appendChild(completionDiv);

                // Remove message after 5 seconds
                setTimeout(() => {
                    if (document.body.contains(completionDiv)) {
                        document.body.removeChild(completionDiv);
                    }
                }, 5000);
            }, 2000);
        };

        return {
            loading,
            showAdvanced,
            form,
            generate
        };
    }
};
</script>
