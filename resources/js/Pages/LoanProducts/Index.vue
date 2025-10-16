<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Transition Profiles
            </h2>
        </template>
        <div class=" mx-auto  mb-4 flex justify-between items-center">
            <filter-search v-model="form.search" class="w-full max-w-md mr-4" @reset="reset">
                <div class="w-80 mt-2 px-4 py-6 shadow-xl bg-white rounded">
                    <div class="mb-2">
                        <jet-label for="aggregation_criteria" value="Aggregation Criteria"/>
                        <select v-model="form.aggregation_criteria" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">All</option>
                            <option value="count">Count</option>
                            <option value="balance">Balance</option>
                        </select>
                    </div>
                </div>
            </filter-search>
            <inertia-link class="btn btn-blue" v-if="can('loans.products.create')" :href="route('loan_products.create')">
                <span>Create </span>
                <span class="hidden md:inline">Transition Profile</span>
            </inertia-link>
        </div>
        <div class=" mx-auto">
            <div class="bg-white rounded shadow overflow-x-auto">
                <table class="w-full whitespace-no-wrap table-auto">
                    <thead class="bg-gray-50">
                    <tr class="text-left font-bold">
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">ID</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Profile Name</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Aggregation</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">End Profile</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Default</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Settings</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-if="!products.data.length">
                        <td colspan="7" class="px-6 py-4 text-center">
                            No Transition Profiles yet
                        </td>
                    </tr>
                    <tr v-for="product in products.data" :key="product.id"
                        class="hover:bg-gray-100 focus-within:bg-gray-100">
                        <td class="border-t">
                            <inertia-link class="px-6 py-4 flex items-center" :href="route('loan_products.show', product.id)"
                                          tabindex="-1">
                                {{ product.id }}
                            </inertia-link>
                        </td>
                        <td class="border-t">
                            <inertia-link class="px-6 py-4 flex items-center" :href="route('loan_products.show', product.id)"
                                          tabindex="-1">
                                {{ product.name }}
                            </inertia-link>
                        </td>
                        <td class="border-t">
                            <span class="px-6 py-4 flex items-center">
                                <span class="px-2 rounded-full" :class="{
                                    'bg-blue-100 text-blue-800': product.aggregation_criteria === 'balance',
                                    'bg-green-100 text-green-800': product.aggregation_criteria === 'count'
                                }">
                                    {{ product.aggregation_criteria }}
                                </span>
                            </span>
                        </td>
                        <td class="border-t">
                            <span class="px-6 py-4 flex items-center">
                                {{ product.end_transition_profile?.name || 'None' }}
                            </span>
                        </td>
                        <td class="border-t">
                            <span class="px-6 py-4 flex items-center">
                                <span v-if="product.is_default"
                                      class="px-2 rounded-full bg-green-100 text-green-800">
                                    Yes
                                </span>
                                <span v-else
                                      class="px-2 rounded-full bg-gray-100 text-gray-800">
                                    No
                                </span>
                            </span>
                        </td>
                        <td class="border-t">
                            <span class="px-6 py-4 flex gap-2">
                                <span v-if="product.is_per_portfolio" class="px-2 rounded-full bg-blue-100 text-blue-800 text-xs">Portfolio</span>
                                <span v-if="product.is_paid" class="px-2 rounded-full bg-green-100 text-green-800 text-xs">Paid</span>
                                <span v-if="product.is_lgd" class="px-2 rounded-full bg-purple-100 text-purple-800 text-xs">LGD</span>
                            </span>
                        </td>
                        <td class="border-t w-px pr-2">
                            <div class="flex items-center gap-4">
                                <inertia-link :href="route('loan_products.show', product.id)"
                                              tabindex="-1" class="text-green-600 hover:text-green-900" title="View">
                                    <font-awesome-icon icon="search"/>
                                </inertia-link>
                                <inertia-link v-if="can('loans.products.update')"
                                              :href="route('loan_products.edit', product.id)"
                                              tabindex="-1" class="text-indigo-600 hover:text-indigo-900" title="Edit">
                                    <font-awesome-icon icon="edit"/>
                                </inertia-link>
                                <a href="#" v-if="can('loans.products.destroy')" @click="deleteAction(product.id)"
                                   class="text-red-600 hover:text-red-900" title="Delete">
                                    <font-awesome-icon icon="trash"/>
                                </a>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <pagination :links="products.links"/>
        </div>
        <jet-confirmation-modal :show="confirmingProductDeletion" @close="confirmingProductDeletion = false">
            <template #title>
                Delete Record
            </template>

            <template #content>
                Are you sure you want to delete ths record? Once record is deleted, all of its resources and
                data will be permanently deleted.
            </template>

            <template #footer>
                <jet-secondary-button @click.native="confirmingProductDeletion = false">
                    Nevermind
                </jet-secondary-button>

                <jet-danger-button class="ml-2" @click.native="destroy" :class="{ 'opacity-25': form.processing }"
                                   :disabled="form.processing">
                    Delete Account
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
        products: Object,
        filters: Object,
    },
    data() {
        return {
            form: {
                search: this.filters.search,
                aggregation_criteria: this.filters.aggregation_criteria,
                processing: false
            },
            confirmingProductDeletion: false,
            selectedRecord: null,
            pageTitle: "Transition Profiles",
            pageDescription: "Manage Transition Profiles",
        }
    },
    watch: {
        form: {
            handler: _.debounce(function () {
                let query = pickBy(this.form)
                this.$inertia.get(this.route('loan_products.index', Object.keys(query).length ? query : {}))
            }, 500),
            deep: true,
        },
    },
    methods: {
        reset() {
            this.form = mapValues(this.form, () => null)
        },
        deleteAction(id) {
            this.confirmingProductDeletion = true
            this.selectedRecord = id
        },
        destroy() {
            this.$inertia.delete(this.route('loan_products.destroy', this.selectedRecord))
            this.confirmingProductDeletion = false
        },
    },
}
</script>

<style scoped>

</style>
