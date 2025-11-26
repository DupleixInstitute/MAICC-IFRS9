<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('fli.scenarios.index')">Economic Scenario Sets
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> Edit
            </h2>
        </template>
        <div class="mx-auto">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">
                <form @submit.prevent="submit">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <jet-label for="name" value="Scenario Set Name"/>
                            <jet-input id="name" type="text" class="block w-full"
                                       v-model="form.name"
                                       required/>
                            <jet-input-error :message="form.errors.name" class="mt-2"/>
                        </div>
                        <div>
                            <jet-label for="description" value="Description"/>
                            <textarea id="description" class="block w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm"
                                      v-model="form.description"></textarea>
                            <jet-input-error :message="form.errors.description" class="mt-2"/>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="flex items-center">
                            <jet-checkbox name="is_active" id="is_active" v-model:checked="form.is_active"/>
                            <jet-label for="is_active" class="ml-2" value="Active Status"/>
                        </div>
                        <jet-input-error :message="form.errors.is_active" class="mt-2"/>
                    </div>

                    <div class="mt-6 border-t pt-4">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Scenarios</h3>
                            <button type="button" @click="addScenario" class="text-sm text-indigo-600 hover:text-indigo-900 font-semibold">
                                + Add Scenario
                            </button>
                        </div>
                        
                        <div class="bg-gray-50 rounded p-4">
                            <div v-if="form.scenarios.length === 0" class="text-center text-gray-500 py-4">
                                No scenarios added. Click "+ Add Scenario" to begin.
                            </div>
                            <div v-for="(scenario, index) in form.scenarios" :key="index" class="flex items-start space-x-4 mb-4">
                                <div class="flex-1">
                                    <jet-label :for="'scenario_name_' + index" value="Scenario Name"/>
                                    <jet-input :id="'scenario_name_' + index" type="text" class="block w-full"
                                               v-model="scenario.scenario_name"
                                               placeholder="e.g. Base Case, Upside, Downside"
                                               required/>
                                </div>
                                <div class="w-32">
                                    <jet-label :for="'probability_' + index" value="Probability (%)"/>
                                    <jet-input :id="'probability_' + index" type="number" step="0.01" min="0" max="100" class="block w-full"
                                               v-model="scenario.probability"
                                               required/>
                                </div>
                                <div class="pt-6">
                                    <button type="button" @click="removeScenario(index)" class="text-red-600 hover:text-red-900">
                                        <font-awesome-icon icon="trash"/>
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-2 flex justify-end items-center">
                            <div class="mr-4 font-medium" :class="{'text-green-600': totalProbability === 100, 'text-red-600': totalProbability !== 100}">
                                Total Probability: {{ totalProbability }}%
                            </div>
                        </div>
                        <jet-input-error :message="form.errors.scenarios" class="mt-2"/>
                    </div>

                    <div class="flex items-center justify-end mt-4">
                        <jet-button class="ml-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing || totalProbability !== 100">
                            Update Scenario Set
                        </jet-button>
                    </div>
                </form>
            </div>
        </div>
        <teleport to="head">
            <title>{{ pageTitle }}</title>
            <meta property="og:description" :content="pageDescription">
        </teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import JetButton from "@/Jetstream/Button.vue"
import JetInput from "@/Jetstream/Input.vue"
import JetInputError from "@/Jetstream/InputError.vue"
import JetCheckbox from "@/Jetstream/Checkbox.vue"
import JetLabel from "@/Jetstream/Label.vue"

export default {
    props: {
        scenarioSet: Object
    },
    components: {
        AppLayout,
        JetButton,
        JetInput,
        JetCheckbox,
        JetLabel,
        JetInputError
    },
    data() {
        return {
            form: this.$inertia.form({
                name: this.scenarioSet.name,
                description: this.scenarioSet.description,
                is_active: Boolean(this.scenarioSet.is_active),
                scenarios: (this.scenarioSet.probabilities || []).map(s => ({
                    scenario_name: s.scenario_name,
                    probability: parseFloat(s.probability)
                }))
            }),
            pageTitle: "Edit Scenario Set",
            pageDescription: "Edit Economic Scenario Set",
        }
    },
    computed: {
        totalProbability() {
            return this.form.scenarios.reduce((sum, scenario) => {
                return sum + (parseFloat(scenario.probability) || 0);
            }, 0);
        }
    },
    methods: {
        addScenario() {
            this.form.scenarios.push({ scenario_name: '', probability: 0 });
        },
        removeScenario(index) {
            this.form.scenarios.splice(index, 1);
        },
        submit() {
            if (Math.abs(this.totalProbability - 100) > 0.01) {
                this.$toast?.error('Total probability must sum to 100%. Current total: ' + this.totalProbability.toFixed(2) + '%');
                return;
            }
            this.form.put(this.route('fli.scenarios.update', this.scenarioSet.id), {
                onSuccess: () => {
                    this.$toast?.success('Scenario set updated successfully!');
                },
                onError: () => {
                    this.$toast?.error('Failed to update scenario set. Please check your inputs.');
                }
            })
        },
    }
}
</script>
