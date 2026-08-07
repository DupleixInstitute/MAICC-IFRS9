<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Create Cummulative Transition Matrix
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <!-- Main Form -->
                    <form @submit.prevent="submitForm">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Transition Profile Selection -->
                            <div>
                                <jet-label for="transition_profile_id" value="Transition Profile" />
                                <select v-model="form.transition_profile_id" 
                                        class="mt-1 block w-full border-gray-300 focus:border-maiic-300 focus:ring focus:ring-maiic-200 focus:ring-opacity-50 rounded-md shadow-sm">
                                    <option value="">Select Profile</option>
                                    <option v-for="profile in profiles" :key="profile.id" :value="profile.id">
                                        {{ profile.profile_code }} - {{ profile.short_name }}
                                    </option>
                                </select>
                                <jet-input-error :message="form.errors.transition_profile_id" class="mt-2" />
                            </div>

                            <!-- Start Reporting Period -->
                            <div>
                                <jet-label for="start_date" value="Start Period" />
                                <input type="month" v-model="form.start_period" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-maiic-500 focus:ring-maiic-500">
                                <jet-input-error :message="form.errors.start_period" class="mt-2" />
                            </div>

                            <!-- End Reporting Period -->
                            <div>
                                <jet-label for="end_date" value="End Period" />
                                <input type="month" v-model="form.end_period" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-maiic-500 focus:ring-maiic-500">
                                <jet-input-error :message="form.errors.end_period" class="mt-2" />
                            </div>

                            <!-- PD Start Stage Type -->
                            <!-- <div>
                                <jet-label for="pd_start_stage_total_type" value="PD Start Stage Total Type" />
                                <select v-model="form.pd_start_stage_total_type" class="mt-1 block w-full border-gray-300 focus:border-maiic-300 focus:ring focus:ring-maiic-200 focus:ring-opacity-50 rounded-md shadow-sm">
                                    <option value="1">Include Settled Accounts</option>
                                    <option value="0">Exclude Settled Accounts</option>
                                </select>
                                <jet-input-error :message="form.errors.pd_start_stage_total_type" class="mt-2" />
                            </div> -->

                            <!-- File Upload -->
                            <!-- <div>
                                <jet-label for="file" value="Supporting Document" />
                                <input type="file" 
                                       @change="handleFileUpload"
                                       class="mt-1 block w-full" />
                                <jet-input-error :message="form.errors.external_file_path" class="mt-2" />
                            </div> -->

                            <!-- Description -->
                            <!-- <div class="md:col-span-2">
                                <jet-label for="description" value="Description" />
                                <textarea v-model="form.description"
                                          class="mt-1 block w-full border-gray-300 focus:border-maiic-300 focus:ring focus:ring-maiic-200 focus:ring-opacity-50 rounded-md shadow-sm"
                                          rows="3"></textarea>
                                <jet-input-error :message="form.errors.description" class="mt-2" />
                            </div> -->

                         <!-- PD Calculation Level -->
                            <div>
                                <jet-label value="PD Calculation Level" />
                                <select v-model="form.pd_calculation_level"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="">Select Level</option>
                                    <option value="portfolio">Portfolio</option>
                                    <option value="sector">Sector</option>
                                </select>
                                <jet-input-error :message="form.errors.pd_calculation_level" class="mt-2" />
                            </div>

                            <!-- Calculation Level -->
                            <div v-if="form.pd_calculation_level">
                                <jet-label value="PD Element" />

                                <!-- Portfolio -->
                                <select v-if="form.pd_calculation_level === 'portfolio'"
                                    v-model="form.pd_calculation_id"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="">Select Portfolio</option>
                                    <option v-for="item in portfolios" :key="item.id" :value="item.id">
                                        {{ item.name }}
                                    </option>
                                </select>


                                <!-- Sector -->
                                <select v-if="form.pd_calculation_level === 'sector'"
                                    v-model="form.pd_calculation_code"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                                    <option value="">Select Sector</option>
                                    <option v-for="item in sectors" :key="item.code" :value="item.code">
                                        {{ item.code }} - {{ item.name }}
                                    </option>
                                </select>

                               <jet-input-error :message="form.errors.pd_calculation_level === 'sector' 
                               ? form.errors.pd_calculation_code  : form.errors.pd_calculation_id"  class="mt-2" />

                            </div>

                            <!-- Calculation Source -->
                            <div>
                                <jet-label for="calculation_source" value="Calculation Source" />
                                <select v-model="form.calculation_source" class="mt-1 block w-full border-gray-300 focus:border-maiic-300 focus:ring focus:ring-maiic-200 focus:ring-opacity-50 rounded-md shadow-sm">
                                    <option value="manual">Manual</option>
                                    <option value="system">System</option>
                                </select>
                                <jet-input-error :message="form.errors.calculation_source" class="mt-2" />
                            </div>
                        </div>

                        <!-- Proceed Button -->
                        <div class="flex justify-end mt-6 gap-4">
                            <jet-button :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                Proceed to Matrix Entry
                            </jet-button>

                            <Link href="/transition-matrix-cummulative" class="inline-flex items-center px-4 py-2 bg-maiic-600 hover:bg-maiic-400 text-black-700 rounded-md">
                                Back
                            </Link>
                        </div>
                    </form>

                    <!-- Matrix Entry Table (shown after form submission) -->
                    <!--<transition-matrix-table
                        v-if="showMatrixTable"
                        :matrix="currentMatrix"
                        :profile="selectedProfile"
                        @update:matrix="handleMatrixUpdate"
                    />-->

                    <!-- Update Loan Book Button -->
                    <!--<div v-if="showMatrixTable" class="mt-6 flex justify-end">
                        <jet-button @click="showUpdateLoanBookModal = true">
                            Update Loan Book
                        </jet-button>
                    </div>-->

                    <!-- Update Loan Book Modal -->
                    <!--<update-loan-book-modal
                        v-if="showUpdateLoanBookModal"
                        :matrix="currentMatrix"
                        @close="showUpdateLoanBookModal = false"
                        @updated="handleLoanBookUpdate"
                    />-->
                </div>
            </div>
        </div>
    </app-layout>
</template>

<script>
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import JetButton from '@/Jetstream/Button.vue'
import JetLabel from '@/Jetstream/Label.vue'
import JetInputError from '@/Jetstream/InputError.vue'
import TransitionMatrixTable from './Components/TransitionMatrixTable.vue'
import UpdateLoanBookModal from './Components/UpdateLoanBookModal.vue'
import flatPickr from 'vue-flatpickr-component'
import 'flatpickr/dist/flatpickr.css'
import { useForm } from '@inertiajs/vue3'

export default {
    components: {
        AppLayout,
        JetButton,
        JetLabel,
        JetInputError,
        TransitionMatrixTable,
        UpdateLoanBookModal,
        flatPickr
    },

    props: {
        profiles: {
            type: Array,
            required: true
        },
        portfolios: {
            type: Array,
            required: true
        },
        sectors: {
            type: Array,
            required: true
        }

    },

    setup() {
        const form = useForm({
            transition_profile_id: '',      
            pd_calculation_level: '',
            pd_calculation_id: null,
            pd_calculation_code: null,
            start_period: '',
            end_period: '',
            //description: '',
            external_file_path: null,
            // pd_start_stage_total_type: '1',
            portfolio_group: '',
            calculation_source: 'system',
        });

        const showMatrixTable = ref(false);
        const loading = ref(null);
        const currentMatrix = ref(null);
        const showUpdateLoanBookModal = ref(false);
        const selectedProfile = ref(null);

        const dateConfig = {
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'F j, Y',
            wrap: true
        };

        const handleFileUpload = (e) => {
            const file = e.target.files[0];
            form.external_file_path = file;
        };

        const submitForm = () => {
            form.post(route('transition-matrix-cummulative.store'), {
                preserveScroll: true,
                onSuccess: () => {
                      Inertia.reload('transition-matrix-cummulative.index');
                },
                onError: (errors) => {
                    console.error('Failed to create matrix:', errors);
                }
            });
        };

        const handleMatrixUpdate = (updatedMatrix) => {
            currentMatrix.value = updatedMatrix;
        };

        const handleLoanBookUpdate = () => {
            showUpdateLoanBookModal.value = false;
        };

        return {
            form,
            showMatrixTable,
            currentMatrix,
            showUpdateLoanBookModal,
            selectedProfile,
            dateConfig,
            handleFileUpload,
            submitForm,
            handleMatrixUpdate,
            handleLoanBookUpdate
        };
    }
}
</script>
