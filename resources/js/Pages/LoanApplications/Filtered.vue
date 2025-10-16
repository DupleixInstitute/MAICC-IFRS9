<template>
    <app-layout>
        <template #header>
            <div class="mt-4 flex items-center space-x-4">
                <button @click="exportToExcel"
                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition duration-200 ease-in-out transform hover:-translate-y-1 hover:scale-110">
                    Export to Excel
                </button>
                <!-- You can add more buttons/icons here -->
            </div>
            <h2 class="font-semibold text-2xl text-gray-800 leading-tight mb-4 mt-4">
                <p>{{ scope.toUpperCase() }} Loan Applications from {{ startDate }} to {{ endDate }}</p>
            </h2>

        </template>
        <div>
            <form action=""></form>
        </div>
        <div class=" mx-auto">

            <div class="bg-white rounded shadow overflow-x-auto">
                <table class="display">
                    <thead class="bg-gray-50">
                        <tr class="text-left font-bold">
                            <th class="px-6 pt-4 pb-4 font-medium text-gray-500">ID</th>
                            <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Date</th>

                            <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Client</th>
                            <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Product</th>
                            <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Amount</th>
                            <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Score</th>
                            <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Status</th>
                            <th v-if="!hiddenColumns.show_branch == ''" class="px-6 pt-4 pb-4 font-medium text-gray-500">Branch</th>
                            <th v-if="!hiddenColumns.show_region == ''" class="px-6 pt-4 pb-4 font-medium text-gray-500">Region</th>


                            <!-- conditionally show the loan description -->
                            <th v-if="!hiddenColumns.loan_description == ''"
                                class="px-6 pt-4 pb-4 font-medium text-gray-500">Description</th>

                            <!-- conditionally show the loan officer -->
                            <th v-if="!hiddenColumns.user_id == ''"
                            class="px-6 pt-4 pb-4 font-medium text-gray-500">Loan Initiator</th>
                            <th v-if="!hiddenColumns.cif == ''"
                            class="px-6 pt-4 pb-4 font-medium text-gray-500">Client's CIF</th>
                            <th v-if="!hiddenColumns.show_loan_approver == ''"
                             class="px-6 pt-4 pb-4 font-medium text-gray-500">Approved By</th>






                            <!-- <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Action</th> -->
                        </tr>
                    </thead>
                    <tbody>
                        <!-- <tr v-if="!applications.data.length">
                        <td colspan="7" class="px-6 py-4 text-center">
                            No applications yet
                        </td>
                    </tr> -->
                        <tr v-for="application in applications.data" :key="application.id"
                            class="hover:bg-gray-100 focus-within:bg-gray-100">
                            <td class="border-t">
                                <inertia-link class="px-6 py-4 flex items-center"
                                    :href="route('loan_applications.show', application.id)" tabindex="-1">
                                    {{ application.id }}
                                </inertia-link>
                            </td>
                            <td class="border-t">
                                <span class="px-6 py-4 flex items-center">
                                    {{ application.date }}
                                </span>
                            </td>
                            <td class="border-t">
                                <inertia-link class="px-6 py-4 flex items-center focus:text-indigo-500"
                                    :href="route('clients.show', application.client_id)" v-if="application.client">
                                    <img v-if="application.client.profile_photo_url"
                                        class="block w-5 h-5 rounded-full mr-2 -my-2"
                                        :src="application.client.profile_photo_url">
                                    {{ application.client.name }}
                                </inertia-link>
                            </td>
                            <td class="border-t">
                                <span class="px-6 py-4 flex items-center" v-if="application.product">
                                    {{ application.product.name }}
                                </span>
                            </td>
                            <td class="border-t">
                                <span class="px-6 py-4 flex items-center">
                                    {{ $filters.formatNumber(application.amount) }}
                                </span>
                            </td>
                            <td class="border-t">
                                <span class="px-6 py-4 flex items-center">
                                    {{
                                        $filters.formatNumber(application.score)
                                    }} ({{ $filters.formatNumber(application.score_percentage) }}%)
                                </span>
                            </td>
                            <td class="border-t px-6 py-4">
                                <div v-if="application.current_linked_stage">
                                    <span v-if="application.current_linked_stage.stage">{{
                                        application.current_linked_stage.stage.name
                                        }} -</span>

                                    <span v-if="application.current_linked_stage.status == 'pending'"
                                        class="px-2 rounded-full bg-yellow-100 text-yellow-800">
                                        pending
                                    </span>
                                    <span v-if="application.current_linked_stage.status == 'returned'"
                                        class="px-2 rounded-full bg-yellow-100 text-yellow-800">
                                        returned
                                    </span>
                                    <span v-if="application.current_linked_stage.status == 'in_progress'"
                                        class="px-2 rounded-full bg-blue-100 text-blue-800">
                                        in progress
                                    </span>
                                    <span v-if="application.current_linked_stage.status == 'approved'"
                                        class="px-2 rounded-full bg-green-100 text-green-800">
                                        approved
                                    </span>
                                    <span v-if="application.current_linked_stage.status == 'done'"
                                        class="px-2 rounded-full bg-green-100 text-green-800">
                                        done
                                    </span>
                                    <span v-if="application.current_linked_stage.status == 'sent_back'"
                                        class="px-2 rounded-full bg-yellow-100 text-yellow-800">
                                        sent back
                                    </span>
                                    <span v-if="application.current_linked_stage.status == 'rejected'"
                                        class="px-2 rounded-full bg-red-100 text-red-800">
                                        rejected
                                    </span>
                                </div>
                            </td>

                            <td v-if="!hiddenColumns.show_branch == ''" class="border-t">
                                <span class="px-6 py-4 flex items-center" v-if="application.branch">
                                    {{ application.branch.name }}
                                </span>
                            </td>
                            <td v-if="!hiddenColumns.show_region == ''" class="border-t">
                                <span class="px-6 py-4 flex items-center" v-if="application.client">
                                    {{ application.client.province?.name }}
                                </span>
                            </td>

                            <td v-if="!hiddenColumns.loan_description == ''"
                            class="px-6 pt-4 pb-4 font-medium text-gray-500">
                            {{ application.description }}
                             </td>

                             <!-- <td v-if="!hiddenColumns.user_id == ''"
                             class="px-6 pt-4 pb-4 font-medium text-gray-500">
                             {{ application.createdBy.name }}
                              </td> -->
                              <td v-if="!hiddenColumns.user_id  == ''" class="border-t">
                                <span class="px-6 py-4 flex items-center" v-if="application.created_by">
                                    {{ application.created_by.name }}
                                </span>
                            </td>
                              <td v-if="!hiddenColumns.cif == ''"
                             class="px-6 pt-4 pb-4 font-medium text-gray-500">
                             {{ application.client.external_id }}
                              </td>
                              <td  v-if="!hiddenColumns.show_loan_approver == ''">
                                <span class="px-6 py-4 flex items-center">
                                    {{
                                        application.approver_name
                                    }}
                                </span>
                              </td>



                            <!-- <td class="border-t w-px pr-2">
                            <div class=" flex items-center gap-4">
                                <inertia-link :href="route('loan_applications.show', application.id)"
                                              tabindex="-1" class="text-green-600 hover:text-green-900" title="View">
                                    <font-awesome-icon icon="search"/>
                                </inertia-link>
                                <inertia-link v-if="can('loans.applications.update')"
                                              :href="route('loan_applications.edit', application.id)"
                                              tabindex="-1" class="text-indigo-600 hover:text-indigo-900" title="Edit">
                                    <font-awesome-icon icon="edit"/>
                                </inertia-link>
                                <a href="#" v-if="can('loans.applications.destroy')"
                                   @click="deleteAction(application.id)"
                                   class="text-red-600 hover:text-red-900" title="Delete">
                                    <font-awesome-icon icon="trash"/>
                                </a>
                            </div>
                        </td> -->
                        </tr>
                    </tbody>
                </table>

            </div>
            <!-- <pagination :links="applications.links" /> -->

        </div>

        <teleport to="head">
            <title>{{ pageTitle }}</title>
            <meta property="og:description" :content="pageDescription">
        </teleport>
    </app-layout>

</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import Icon from '@/Jetstream/Icon.vue'
import Pagination from '@/Jetstream/Pagination.vue'
import FilterSearch from '@/Jetstream/FilterSearch.vue'
import mapValues from 'lodash/mapValues'
import pickBy from 'lodash/pickBy'
import throttle from 'lodash/throttle'
import JetLabel from '@/Jetstream/Label.vue'
import SelectInput from '@/Jetstream/SelectInput.vue'
import JetConfirmationModal from '@/Jetstream/ConfirmationModal.vue'
import JetDangerButton from '@/Jetstream/DangerButton.vue'
import JetSecondaryButton from '@/Jetstream/SecondaryButton.vue'


export default {
    components: {
        AppLayout,
        Icon,
        Pagination,
        FilterSearch,
        JetLabel,
        SelectInput,
        JetConfirmationModal,
        JetDangerButton,
        JetSecondaryButton,
    },
    props: {
        applications: Object,
        filters: Object,
        products: Object,
        branches: Object,
        startDate: String,
        endDate: String,
        scope: String,
        hiddenColumns : Object

    },
    data() {
        return {
            confirmingLoanDeletion: false,
            selectedRecord: null,
            pageTitle: "Loan Applications",
            pageDescription: "Manage Loan Applications",
            dataTableReady: false // Flag to indicate if DataTable is ready

        }
    },
    methods: {
        exportToExcel() {
    // Get CSRF token value from the meta tag
    let csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    console.log(csrfToken);

    let form = document.createElement('form');
    form.method = 'POST';
    form.action = route('export.excel');
    form.target = '_blank';

    // Create hidden input field for CSRF token
    let csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '_token';
    csrfInput.value = csrfToken;

    // Create hidden input field for applications data
    let applicationsInput = document.createElement('input');
    applicationsInput.type = 'hidden';
    applicationsInput.name = 'applications';
    applicationsInput.value = JSON.stringify(this.applications);

    let extraColumnsInput = document.createElement('input');
    extraColumnsInput.type = 'hidden';
    extraColumnsInput.name = 'hiddenColumns';
    extraColumnsInput.value = JSON.stringify(this.hiddenColumns);

    // Append inputs to form
    form.appendChild(csrfInput);
    form.appendChild(applicationsInput);
    form.appendChild(extraColumnsInput);

    // Append form to document body
    document.body.appendChild(form);

    // Submit form
    form.submit();

    // Clean up: Remove form from document body
    // document.body.removeChild(form);
},
        exportToPDF() {
            // Access the DataTable instance using $refs
            const table = this.$refs.dataTable.$el.querySelector('table').DataTable();

            // Convert the DataTable instance to a PDF format
            const doc = new jsPDF();
            doc.autoTable({
                head: [table.columns().header().toArray().map(header => header.textContent.trim())],
                body: table.rows().data().toArray()
            });

            // Save the PDF file
            doc.save('table.pdf');
        }
    },

    mounted() {
        this.dataTableReady = true;
        console.log('Scope prop:', this.hiddenColumns);
        // console.log('province prop:', this.provinces);
    },

}
</script>

<style scoped></style>
