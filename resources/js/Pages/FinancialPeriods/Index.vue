<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Financial Periods
            </h2>
        </template>
        <div class=" mx-auto mb-4 flex justify-between items-center">
            <filter-search v-model="form.search" class="w-full max-w-md mr-4" @reset="reset">
                <div class="w-80 mt-2 px-4 py-6 shadow-xl bg-white rounded">
                </div>
            </filter-search>
            <inertia-link v-if="can('accounting.financial_periods.create')" class="btn btn-blue" :href="route('accounting.financial_periods.create')">
                <span>Create </span>
                <span class="hidden md:inline">Period</span>
            </inertia-link>
        </div>
        <div class=" mx-auto">
            <div class="bg-white rounded shadow overflow-x-auto">
                <table class="w-full whitespace-no-wrap">
                    <thead class="bg-gray-50">
                    <tr class="text-left font-bold">
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">ID</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Name</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Date Created</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Date Closed</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Active</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="financialPeriod in financialPeriods.data" :key="financialPeriod.id"
                        class="hover:bg-gray-100 focus-within:bg-gray-100">
                        <td class="border-t">
                            <span class="px-6 py-4 flex items-center">
                                {{ financialPeriod.id }}
                            </span>
                        </td>
                        <td class="border-t">
                             <span class="px-6 py-4 flex items-center">
                                {{ financialPeriod.name }}
                            </span>
                        </td>
                        <td class="border-t">
                             <span class="px-6 py-4 flex items-center">
                                {{ financialPeriod.start_date }}
                            </span>
                        </td>
                        <td class="border-t">
                             <span class="px-6 py-4 flex items-center">
                                {{ financialPeriod.end_date }}
                            </span>
                        </td>
                        <td class="border-t">
                            <span class="px-6 py-4 flex items-center">
                                <span v-if="!financialPeriod.closed">Yes</span>
                                <span v-if="financialPeriod.closed">No</span>
                            </span>
                        </td>
                        <td class="border-t w-px pr-2">
                            <div class=" flex items-center space-x-2">
                                <a href="#" v-if="can('accounting.financial_periods.update') && !financialPeriod.closed"
                                   @click="closeAction(financialPeriod.id)" class="text-red-600 hover:text-red-900">Close</a>
                                <inertia-link v-if="can('accounting.financial_periods.update')"
                                              :href="route('accounting.financial_periods.edit', financialPeriod.id)"
                                              tabindex="-1" class="text-indigo-600 hover:text-indigo-900">
                                    Edit
                                </inertia-link>
                                <a href="#" v-if="can('accounting.financial_periods.destroy')"
                                   @click="deleteAction(financialPeriod.id)" class="text-red-600 hover:text-red-900">Delete</a>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="financialPeriods.data.length === 0">
                        <td class="border-t px-6 py-4" colspan="7">No Financial Periods found.</td>
                    </tr>
                    </tbody>
                </table>

            </div>
            <pagination :links="financialPeriods.links"/>
        </div>
        <jet-confirmation-modal :show="confirmingDeletion" @close="confirmingDeletion = false">
            <template #title>
                Delete Record
            </template>

            <template #content>
                Are you sure you want to delete record?
            </template>

            <template #footer>
                <jet-secondary-button @click.native="confirmingDeletion = false">
                    Nevermind
                </jet-secondary-button>

                <jet-danger-button class="ml-2" @click.native="destroy" :class="{ 'opacity-25': form.processing }"
                                   :disabled="form.processing">
                    Delete Record
                </jet-danger-button>
            </template>
        </jet-confirmation-modal>
        <jet-confirmation-modal :show="confirmingClosing" @close="confirmingClosing = false">
            <template #title>
                Close Period
            </template>

            <template #content>
                Are you sure you want to close this period?
            </template>

            <template #footer>
                <jet-secondary-button @click.native="confirmingClosing = false">
                    Nevermind
                </jet-secondary-button>

                <jet-danger-button class="ml-2" @click.native="closePeriod" :class="{ 'opacity-25': form.processing }"
                                   :disabled="form.processing">
                    Close Period
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
import SearchFilter from '@/Jetstream/SearchFilter.vue'
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
        SearchFilter,
        FilterSearch,
        JetLabel,
        SelectInput,
        JetConfirmationModal,
        JetDangerButton,
        JetSecondaryButton,
    },
    props: {
        financialPeriods: Object,
        filters: Object,

    },
    data() {
        return {
            form: {
                search: this.filters.search,
                account_type: this.filters.account_type,
                processing: false
            },
            confirmingDeletion: false,
            confirmingClosing: false,
            selectedRecord: null,
            pageTitle: "Financial Periods",
            pageDescription: "Manage Financial Periods",

        }
    },
    watch: {
        form: {
            handler: _.debounce(function () {
                let query = pickBy(this.form)
                this.$inertia.get(this.route('accounting.financial_periods.index', Object.keys(query).length ? query : {}))
            }, 500),
            deep: true,
        },
    },
    methods: {
        reset() {
            this.form = mapValues(this.form, () => null)
        },
        deleteAction(id) {
            this.confirmingDeletion = true
            this.selectedRecord = id
        },
        destroy() {

            this.$inertia.delete(this.route('accounting.financial_periods.destroy', this.selectedRecord))
            this.confirmingDeletion = false
        },
        closeAction(id) {
            this.confirmingClosing = true
            this.selectedRecord = id
        },
        closePeriod() {

            this.$inertia.put(this.route('accounting.financial_periods.close', this.selectedRecord))
            this.confirmingClosing = false
        },
    },
}
</script>

<style scoped>

</style>
