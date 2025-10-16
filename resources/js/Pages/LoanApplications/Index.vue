<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Loan Applications
            </h2>
        </template>
        <!-- <inertia-link class="text-indigo-400 hover:text-indigo-600 ml-2" :href="route('loan_applications.fixing')">
            Correct Loan Applications
        </inertia-link> -->
        <div class=" mx-auto  mb-4 flex justify-between items-center">

            <filter-search v-model="form.search" class="w-full max-w-md mr-4" @reset="reset">
                <div class="w-80 mt-2 px-4 py-6 shadow-xl bg-white rounded">
                    <div class="mb-2">
                        <jet-label for="status" value="Status"/>
                        <select v-model="form.status"
                                class="mt-1 w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm">
                            <option :value="null"/>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>

                        </select>
                    </div>
                    <div class="mb-2">
                        <jet-label for="loan_product_id" value="Product"/>
                        <Multiselect
                            id="loan_product_id"
                            v-model="form.loan_product_id"
                            :options="products"
                        />
                    </div>
                    <div class="mb-2">
                        <jet-label for="branch_id" value="Branch"/>
                        <Multiselect
                            id="branch_id"
                            v-model="form.branch_id"
                            :options="branches"
                        />
                    </div>
                </div>
            </filter-search>
            <inertia-link class="btn btn-blue" v-if="can('loans.applications.create')"
                          :href="route('loan_applications.create')">
                <span>Create </span>
                <span class="hidden md:inline">Application</span>
            </inertia-link>
        </div>
        <div class=" mx-auto">
            <div class="bg-white rounded shadow overflow-x-auto">
                <table class="w-full whitespace-no-wrap table-auto">
                    <thead class="bg-gray-50">
                    <tr class="text-left font-bold">


                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">ID</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Client</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Product</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Amount</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Score</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Status</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Date</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Branch</th>

                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-if="!applications.data.length">
                        <td colspan="7" class="px-6 py-4 text-center">
                            No applications yet
                        </td>
                    </tr>
                    <tr v-for="application in applications.data" :key="application.id"
                        class="hover:bg-gray-100 focus-within:bg-gray-100">
                        <td class="border-t">
                            <inertia-link class="px-6 py-4 flex items-center"
                                          :href="route('loan_applications.show', application.id)"
                                          tabindex="-1">
                                {{ application.id }}
                            </inertia-link>
                        </td>
                        <td class="border-t">
                            <inertia-link class="px-6 py-4 flex items-center focus:text-indigo-500"
                                          :href="route('clients.show', application.client_id)"
                                          v-if="application.client">
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
                                {{ $filters.formatNumber(application.score) }} ({{ $filters.formatNumber(application.score_percentage) }}%)
                            </span>
                            <span class="px-6 py-4 inline-block text-sm font-semibold text-green-800 bg-green-200 rounded-full">
                                {{ application.loan_application_band }}
                            </span>
                        </td>
                        <td class="border-t px-6 py-4">
                            <div v-if="application.current_linked_stage">
                                <span
                                    v-if="application.current_linked_stage.stage">{{
                                        application.current_linked_stage.stage.name
                                    }} -</span>

                                <span v-if="application.current_linked_stage.status=='pending'"
                                      class="px-2 rounded-full bg-yellow-100 text-yellow-800">
                                        pending
                                    </span>
                                <span v-if="application.current_linked_stage.status=='returned'"
                                      class="px-2 rounded-full bg-yellow-100 text-yellow-800">
                                        returned
                                    </span>
                                <span v-if="application.current_linked_stage.status=='in_progress'"
                                      class="px-2 rounded-full bg-blue-100 text-blue-800">
                                        in progress
                                    </span>
                                <span v-if="application.current_linked_stage.status=='approved'"
                                      class="px-2 rounded-full bg-green-100 text-green-800">
                                        approved
                                    </span>
                                <span v-if="application.current_linked_stage.status=='done'"
                                      class="px-2 rounded-full bg-green-100 text-green-800">
                                        done
                                    </span>
                                <span v-if="application.current_linked_stage.status=='sent_back'"
                                      class="px-2 rounded-full bg-yellow-100 text-yellow-800">
                                        pending,was sent back
                                    </span>
                                <span v-if="application.current_linked_stage.status=='rejected'"
                                      class="px-2 rounded-full bg-red-100 text-red-800">
                                        rejected
                                    </span>
                            </div>
                        </td>
                        <td class="border-t">
                            <span class="px-6 py-4 flex items-center">
                                 {{ application.date }}
                            </span>
                        </td>
                        <td class="border-t">
                            <span class="px-6 py-4 flex items-center" v-if="application.branch">
                                 {{ application.branch.name }}
                            </span>
                        </td>

                        <td class="border-t w-px pr-2">
                            <div class=" flex items-center gap-4">
                                <inertia-link :href="route('loan_applications.show', application.id)"
                                              tabindex="-1" class="text-green-600 hover:text-green-900" title="View">
                                    <font-awesome-icon icon="search"/>
                                </inertia-link>
                                <inertia-link v-if="can('loans.applications.update') && application.current_linked_stage?.status!='approved'  && application.current_linked_stage?.status!='rejected' "
                                              :href="route('loan_applications.edit', application.id)"
                                              tabindex="-1" class="text-indigo-600 hover:text-indigo-900" title="Edit">
                                    <font-awesome-icon icon="edit"/>
                                </inertia-link>

                                <inertia-link v-if="can('loans.applications.resend') && application.current_linked_stage?.status=='returned' || application.current_linked_stage?.status=='sent_back' && $attrs.user.id == application.created_by_id"
                                              :href="route('loan_applications.resend', application.id)"
                                              tabindex="-1" class="text-indigo-600 hover:text-indigo-900" title="Resend">
                                    <font-awesome-icon icon="share"/>
                                </inertia-link>

                                <a href="#" v-if="can('loans.applications.destroy')  && application.current_linked_stage?.status!='approved' && application.current_linked_stage?.status!='rejected'"
                                   @click="deleteAction(application.id)"
                                   class="text-red-600 hover:text-red-900" title="Delete">
                                    <font-awesome-icon icon="trash"/>
                                </a>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>

            </div>
            <pagination :links="applications.links"/>
        </div>
        <jet-confirmation-modal :show="confirmingLoanDeletion" @close="confirmingLoanDeletion = false">
            <template #title>
                Delete Record
            </template>

            <template #content>
                Are you sure you want to delete record? Once record is deleted, all of its resources and
                data will be permanently deleted.
            </template>

            <template #footer>
                <jet-secondary-button @click.native="confirmingLoanDeletion = false">
                    Nevermind
                </jet-secondary-button>

                <jet-danger-button class="ml-2" @click.native="destroy" :class="{ 'opacity-25': form.processing }"
                                   :disabled="form.processing">
                    Delete
                </jet-danger-button>
            </template>
        </jet-confirmation-modal>
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

    },
    data() {
        return {
            form: {
                search: this.filters.search,
                province_id: this.filters.province_id,
                branch_id: this.filters.branch_id,
                district_id: this.filters.district_id,
                ward_id: this.filters.ward_id,
                village_id: this.filters.village_id,
                loan_product_id: this.filters.loan_product_id,
                client_id: this.filters.client_id,
                status: this.filters.status,
                processing: false

            },
            confirmingLoanDeletion: false,
            selectedRecord: null,
            pageTitle: "Loan Applications",
            pageDescription: "Manage Loan Applications",

        }
    },
    watch: {
        form: {
            handler: _.debounce(function () {
                let query = pickBy(this.form)
                this.$inertia.get(this.route('loan_applications.index', Object.keys(query).length ? query : {}))
            }, 500),
            deep: true,
        },
    },
    methods: {
        reset() {
            this.form = mapValues(this.form, () => null)
        },
        deleteAction(id) {
            this.confirmingLoanDeletion = true
            this.selectedRecord = id
        },
        destroy() {
            this.$inertia.delete(this.route('loan_applications.destroy', this.selectedRecord))
            this.confirmingLoanDeletion = false
        },
    },
}
</script>

<style scoped>

</style>
