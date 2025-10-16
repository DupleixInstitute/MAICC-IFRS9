<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('dashboard')">Dashboard
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> Create Filter
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-4">
                    <form @submit.prevent="submit">

                        <input type="hidden" v-model="form.scope">



                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                            <!-- Loan Start Date -->
                            <div>
                                <jet-label for="loan_start_date" value="Loan Start Date" />
                                <jet-input id="loan_start_date" type="date" class="mt-1 block w-full"
                                    v-model="form.loan_start_date" required />
                                <jet-input-error :message="form.errors.loan_start_date" class="mt-2" />
                            </div>
                            <!-- Loan End Date -->
                            <div>
                                <jet-label for="loan_end_date" value="Loan End Date" />
                                <div class="flex items-center">

                                    <jet-input id="loan_end_date" type="date" class="mt-1 block w-full"
                                        v-model="form.loan_end_date" />
                                </div>
                                <div class="flex justify-between">
                                    <jet-input-error :message="form.errors.loan_end_date_start" class="mt-2" />
                                    <jet-input-error :message="form.errors.loan_end_date_end" class="mt-2" />
                                </div>
                            </div>
                            <!-- Select Region -->
                            <div>
                                <jet-label for="region" value="Select Region" />
                                <!-- <select id="region" v-model="form.region" class="mt-1 block w-full">
                                    <option value="">Select Region</option>
                                    <option v-for="region in provinces" :key="region.id" :value="region.id">{{
                                        region.name }}
                                    </option>
                                </select> -->
                                <Multiselect
                                v-model="form.region"
                                :options="provinces"
                                mode="tags"

                            />
                                <jet-input-error :message="form.errors.region" class="mt-2" />
                            </div>
                            <!-- Select Product -->
                            <div>
                                <jet-label for="product" value="Select Product" />
                                <select id="product" v-model="form.product" class="mt-1 block w-full">
                                    <option value="">Select Product</option>
                                    <option v-for="product in products" :key="product.id" :value="product.id">{{
                                        product.name }}
                                    </option>
                                </select>
                                <jet-input-error :message="form.errors.product" class="mt-2" />
                            </div>
                            <!-- Loan Amount -->
                            <div>
                                <jet-label for="loan_amount" value="Loan Amount" />
                                <div class="flex items-center">
                                    <select id="loan_amount_operator" v-model="form.loan_amount_operator"
                                        class="mt-1 block w-1/3">
                                        <option value="greater">Greater Than</option>
                                        <option value="less">Less Than</option>
                                        <option value="equal">Equal To</option>
                                    </select>
                                    <jet-input id="loan_amount" type="number" class="mt-1 block w-2/3"
                                        v-model="form.loan_amount" min="0" required />
                                </div>
                                <jet-input-error :message="form.errors.loan_amount" class="mt-2" />
                            </div>
                            <!-- Select Branch -->
                            <div>
                                <jet-label for="branch" value="Select Branch" />
                                <Multiselect
                                v-model="form.branch"
                                :options="branches"
                                mode="tags"

                            />

                                <jet-input-error :message="form.errors.branch" class="mt-2" />
                            </div>

                            <div>
                                <jet-label for="loan_initiator" value="Loan Initiator" />
                                <select id="loan_initiator" v-model="form.loan_initiator_id" class="mt-1 block w-full">
                                    <option value="">Select User</option>
                                    <option v-for="user in users" :key="user.id" :value="user.id">{{
                                        user.name }}
                                    </option>
                                </select>
                                <jet-input-error :message="form.errors.loan_initiator_id" class="mt-2" />
                            </div>
                            <div>
                                <jet-label for="loan_approver" value="Loan Approver" />
                                <select id="loan_approver" v-model="form.loan_approver_id" class="mt-1 block w-full">
                                    <option value="">Select User</option>
                                    <option v-for="user in users" :key="user.id" :value="user.id">{{
                                        user.name }}
                                    </option>
                                </select>
                                <jet-input-error :message="form.errors.loan_approver_id" class="mt-2" />
                            </div>

                            <h2 class="extra-column-header">Select Extra Columns To Appear On Results</h2>

                            <div class="checkbox-container">
                                <input type="checkbox" id="loan_description" v-model="form.loan_description" />
                                <label for="loan_description">Loan Description</label>
                            </div>

                            <div class="checkbox-container">
                                <input type="checkbox" id="cif" v-model="form.cif" />
                                <label for="cif">Client's CIF</label>
                            </div>

                            <div class="checkbox-container">
                                <input type="checkbox" id="user_id" v-model="form.user_id" />
                                <label for="user_id">Loan Initiator</label>
                            </div>
                            <div class="checkbox-container">
                                <input type="checkbox" id="show_loan_approver" v-model="form.show_loan_approver" />
                                <label for="show_loan_approver">Loan Approver</label>
                            </div>
                            <div class="checkbox-container">
                                <input type="checkbox" id="show_branch" v-model="form.show_branch" />
                                <label for="show_branch">Branch</label>
                            </div>
                            <div class="checkbox-container">
                                <input type="checkbox" id="show_region" v-model="form.show_region" />
                                <label for="show_region">Region</label>
                            </div>

                        </div>
                        <div class="flex items-center justify-end mt-6">

                            <inertia-link class="text-indigo-400 hover:text-indigo-600"
                                :href="route('dashboard')">Cancel
                            </inertia-link>
                            <jet-button class="ml-4" :class="{ 'opacity-25': form.processing }"
                                :disabled="form.processing">
                                Apply Filter
                            </jet-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <teleport to="head">
            <title>{{ pageTitle }}</title>
            <meta property="og:description" :content="pageDescription" />
        </teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue';
import JetButton from "@/Jetstream/Button.vue";
import JetInput from "@/Jetstream/Input.vue";
import JetInputError from "@/Jetstream/InputError.vue";
import JetLabel from "@/Jetstream/Label.vue";

export default {
    props: {
        scope: String,
        provinces: Object,
        products: Object,
        branches: Object,
        users: Object
    },
    components: {
        AppLayout,
        JetButton,
        JetInput,
        JetLabel,
        JetInputError
    },
    data() {
        // console.log('Scope prop:', this.scope);
        return {
            form: this.$inertia.form({
                loan_start_date: null,
                loan_end_date: null,
                region: [],
                product: null,
                loan_amount_operator: 'greater',
                loan_amount: 0,
                branch: [],
                //take the scope which was passed as a prop
                scope: this.scope,
                loan_description: null,
                cif: null,
                user_id: null,
                loan_initiator_id: null,
                loan_approver_id: null,
                show_branch : null,
                show_region :null,
                show_loan_approver : null,
            }),
            pageTitle: "Create Filter",
            pageDescription: "Create Filter",

        }
    },
    mounted() {
        // console.log('Scope prop:', this.branches);

        // console.log('province prop:', this.provinces);
    },

    methods: {
        async submit() {
            try {
                // Submit the form data
                const response = await this.form.get(this.route('dashboard.filter-results'), {});

                // Handle success response
                // For example, redirect to a new page
                if (response) {
                    this.$inertia.visit(response.redirect_url);
                }
            } catch (error) {
                // Handle error response
                // For example, show error message to the user
                console.error('Form submission error:', error);
            }
        },

    }
}
</script>

<style scoped>
/* Add your custom styling here */
.checkbox-container {
    margin-bottom: 10px;

}
.extra-column-header {

    grid-column: span 2;
    font-weight: bold;
    font-size: 1.2rem;
    margin-top: 1rem;
    margin-bottom: 1rem;
    color: #4a5568;
}


</style>
