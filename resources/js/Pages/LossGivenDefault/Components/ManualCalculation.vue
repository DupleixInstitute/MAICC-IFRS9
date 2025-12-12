<template>
    <div v-if="show" class="fixed inset-0 flex items-center justify-center bg-gray-800 bg-opacity-75">
        <div class="bg-white p-6 rounded-lg shadow-lg w-96">
            <h2 class="text-lg font-semibold">Manual LGD Calculation</h2>

            <!-- Toggle between Modes -->
            <div class="mt-4">
                <label class="block font-medium text-sm text-gray-700">Calculation Mode</label>
                <select v-model="mode" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    <option value="amount">By Amount</option>
                    <option value="percentage">By Percentage</option>
                </select>
            </div>

            <!-- Amount-based Inputs -->
            <div v-if="mode === 'amount'" class="space-y-4 mt-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <jet-label for="start_total_stage3" value="Start Total Stage 3" />
                        <input type="number" v-model="fields.start_total_stage3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <jet-label for="end_total_stage3" value="End Total Stage 3" />
                        <input type="number" v-model="fields.end_total_stage3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <jet-label for="cure_amount_stage1" value="Cured Amount (1)" />
                        <input type="number" v-model="fields.cure_amount_stage1"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <jet-label for="cure_amount_stage2" value="Cured Amount (2)" />
                        <input type="number" v-model="fields.cure_amount_stage2"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <jet-label for="partially_recovered-amount" value="Partially Recovered" />
                        <input type="number" v-model="fields.partially_recovered_amount"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <jet-label for="fully_recovered-amount" value="Fully Recovered" />
                        <input type="number" v-model="fields.fully_recovered_amount"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                    <div>
                        <jet-label for="total_disbursments" value="Disbursed Amount" />
                        <input type="number" v-model="fields.total_disbursments"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    </div>
                </div>
            </div>

            <!-- Percentage-based Inputs -->
            <div v-else class="space-y-4 mt-4">
                <div>
                    <jet-label for="cure_rate" value="Cure Rate (%)" />
                    <input type="number" step="0.01" v-model="fields.cure_rate"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div>
                    <jet-label for="recovery_rate" value="Recovery Rate (%)" />
                    <input type="number" step="0.01" v-model="fields.recovery_rate"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="flex justify-end mt-6">
                <jet-button @click="submitManualCalculation">Submit</jet-button>
                <Link :href="route('loss-given-default.create')" class="ml-2">Close</Link>
            </div>
        </div>
    </div>
</template>

<script>
import JetButton from '@/Jetstream/Button.vue';
import JetLabel from '@/Jetstream/Label.vue';
import { route } from 'ziggy-js';

export default {
    components: {
        JetButton,
        JetLabel,
    },
    props: {
        show: Boolean,
        startPeriod: String,
        reportingPeriod: String,

        lgdCalculationLevel: String,
        portfolioGroup: [String, Number], 
        sectorCode: String,          

        defaultValues: Object,
        isUpdate: Boolean,
    },

    data() {
        return {
            mode: 'amount',
            fields: {
                start_total_stage3: '',
                end_total_stage3: '',
                cure_amount_stage1: '',
                cure_amount_stage2: '',
                partially_recovered_amount: '',
                fully_recovered_amount: '',
                total_disbursments: '',
                cure_rate: '',
                recovery_rate: '',
            },
        };
    },
    mounted() {
        this.fields = { ...this.defaultValues };
    },
    methods: {
       submitManualCalculation() {
                const basePayload = {
                    start_period: this.startPeriod,
                    reporting_period: this.reportingPeriod,

                    lgd_calculation_level: this.lgdCalculationLevel,
                    lgd_calculation_id: this.lgdCalculationLevel === 'portfolio' ? this.portfolioGroup : null,
                    lgd_calculation_code: this.lgdCalculationLevel === 'sector' ? this.sectorCode : null,
                };

                if (this.mode === 'amount') {

                    const start = parseFloat(this.fields.start_total_stage3);
                    const cured = parseFloat(this.fields.cure_amount_stage1) + parseFloat(this.fields.cure_amount_stage2);
                    const recovered = (parseFloat(this.fields.partially_recovered_amount) + parseFloat(this.fields.fully_recovered_amount))
                                        - parseFloat(this.fields.total_disbursments);

                    const cureRate = start > 0 ? cured / start : 0;
                    const recoveryRate = start > 0 ? recovered / start : 0;

                    let lgd = (1 - cureRate) * (1 - recoveryRate);

                   
                    lgd = Math.max(0, Math.min(1, lgd));

                    this.$inertia.post(route('loss-given-default.storeManual'), {
                        ...this.fields,
                        ...basePayload,

                        cure_rate: cureRate,
                        recovery_rate: recoveryRate,
                        cured_amount: cured,
                        recovered_amount: recovered,
                        loss_given_default_percentage: lgd,

                        mode: 'amount',
                    });

                } else {

                    const cureRate = parseFloat(this.fields.cure_rate) / 100;
                    const recoveryRate = parseFloat(this.fields.recovery_rate) / 100;

                    let lgd = (1 - cureRate) * (1 - recoveryRate);

                    lgd = Math.max(0, Math.min(1, lgd));

                    this.$inertia.post(route('loss-given-default.storeManual'), {
                        ...this.fields,
                        ...basePayload,

                        cure_rate: cureRate,
                        recovery_rate: recoveryRate,
                        loss_given_default_percentage: lgd,

                        mode: 'percentage',
                    });
                }

                this.$emit('close');
            }
    }
};
</script>
