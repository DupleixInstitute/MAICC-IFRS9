<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-maiic-500 hover:text-maiic-600" :href="route('credit-loss-data.index')">
                    Credit Loss Data
                </inertia-link>
                <span class="text-maiic-500 font-medium">/</span> Create Definition
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6">
                        <!-- Form Header -->
                        <div class="mb-6">
                            <h3 class="text-lg font-medium text-gray-900">Create Credit Loss Definition</h3>
                            <p class="mt-1 text-sm text-gray-600">
                                Create a new credit loss metric definition that can be used for data entry.
                            </p>
                        </div>

                        <form @submit.prevent="submit">
                            <!-- Code Field -->
                            <div class="mb-6">
                                <jet-label for="code" value="Metric Code *" />
                                <jet-input
                                    id="code"
                                    type="text"
                                    v-model="form.code"
                                    class="mt-1 block w-full"
                                    :class="{ 'border-red-300 focus:border-red-500 focus:ring-red-500': form.errors.code }"
                                    placeholder="e.g., ECL, PD, LGD"
                                />
                                <jet-input-error :message="form.errors.code" class="mt-2" />
                                <p class="mt-1 text-xs text-gray-500">
                                    Unique code for the metric (e.g., ECL for Expected Credit Loss, PD for Probability of Default)
                                </p>
                            </div>

                            <!-- Name Field -->
                            <div class="mb-6">
                                <jet-label for="name" value="Metric Name *" />
                                <jet-input
                                    id="name"
                                    type="text"
                                    v-model="form.name"
                                    class="mt-1 block w-full"
                                    :class="{ 'border-red-300 focus:border-red-500 focus:ring-red-500': form.errors.name }"
                                    placeholder="e.g., Expected Credit Loss, Probability of Default"
                                />
                                <jet-input-error :message="form.errors.name" class="mt-2" />
                                <p class="mt-1 text-xs text-gray-500">
                                    Descriptive name for the metric
                                </p>
                            </div>

                            <!-- Description Field -->
                            <div class="mb-6">
                                <jet-label for="description" value="Description" />
                                <textarea
                                    id="description"
                                    v-model="form.description"
                                    rows="4"
                                    class="mt-1 block w-full border-gray-300 focus:border-maiic-500 focus:ring-maiic-500 rounded-md shadow-sm"
                                    placeholder="Optional description of what this metric represents..."
                                ></textarea>
                                <jet-input-error :message="form.errors.description" class="mt-2" />
                            </div>

                            <!-- Common Definitions Preview -->
                            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                                <h4 class="text-sm font-medium text-gray-900 mb-3">Common Credit Loss Definitions</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                                    <div v-for="commonDef in commonDefinitions" :key="commonDef.code" 
                                         class="p-2 bg-white rounded border cursor-pointer hover:bg-gray-50"
                                         @click="fillCommonDefinition(commonDef)">
                                        <div class="font-medium text-gray-900">{{ commonDef.code }}</div>
                                        <div class="text-gray-600">{{ commonDef.name }}</div>
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
                                    Create Definition
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
    data() {
        return {
            commonDefinitions: [
                { code: 'ECL', name: 'Expected Credit Loss', description: 'Total expected credit loss amount' },
                { code: 'PD', name: 'Probability of Default', description: 'Probability of default (0-1)' },
                { code: 'LGD', name: 'Loss Given Default', description: 'Loss given default (0-1)' },
                { code: 'EAD', name: 'Exposure at Default', description: 'Total exposure at default' },
                { code: 'NPL', name: 'Non-Performing Loans', description: 'Non-performing loans amount' },
                { code: 'STAGE', name: 'IFRS 9 Stage', description: 'Credit stage classification (1, 2, 3)' },
                { code: 'CREDIT_RATING', name: 'Credit Rating', description: 'Internal credit rating' },
            ],
            form: useForm({
                code: '',
                name: '',
                description: '',
            })
        }
    },
    methods: {
        submit() {
            this.form.post(route('credit-loss-data.storeDefinition'), {
                onSuccess: () => {
                    // Form will automatically redirect on success
                },
            })
        },
        
        fillCommonDefinition(definition) {
            this.form.code = definition.code;
            this.form.name = definition.name;
            this.form.description = definition.description;
        }
    }
}
</script>