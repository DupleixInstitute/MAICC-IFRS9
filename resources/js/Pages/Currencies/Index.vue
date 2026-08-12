<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Currencies
            </h2>
        </template>
        <div class=" mx-auto mb-4 flex justify-between items-center">
            <filter-search v-model="form.search" class="w-full max-w-md mr-4" @reset="reset">
            </filter-search>
            <inertia-link v-if="can('currencies.create')" class="btn btn-blue" :href="route('currencies.create')">
                <span>Create </span>
                <span class="hidden md:inline">Currency</span>
            </inertia-link>
        </div>
        <div class=" mx-auto">
            <div class="maiic-panel maiic-table-wrap">
                <table class="maiic-table">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Symbol</th>
                        <th>Exchange Rate</th>
                        <th>Decimals</th>
                        <th>Active</th>
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="currency in currencies.data" :key="currency.id"
                        class="hover:bg-gray-100 focus-within:bg-gray-100">
                        <td class="!p-0">
                             <span class="px-4 py-2.5 flex items-center">
                                {{ currency.name }}({{ currency.code }})
                                 <label title="Default CoPayer" v-if="currency.is_default"
                                        class="text-xs font-semibold inline-block py-1 px-1 uppercase rounded text-maiic-600 bg-maiic-200 uppercase last:mr-0 ml-2">
                                            <font-awesome-icon icon="star"/>
                                 </label>
                            </span>
                        </td>
                        <td class="!p-0">
                           <span class="px-4 py-2.5 flex items-center">
                                {{ currency.symbol }}
                            </span>
                        </td>
                        <td class="!p-0">
                           <span class="px-4 py-2.5 flex items-center">
                                {{ currency.xrate }}
                            </span>
                        </td>
                        <td class="!p-0">
                            <span class="px-4 py-2.5 flex items-center">
                                {{ currency.decimals }}
                            </span>
                        </td>
                        <td class="!p-0">
                            <span class="px-4 py-2.5 flex items-center">
                                <span v-if="currency.active">Yes</span>
                                <span v-if="!currency.active">No</span>
                            </span>
                        </td>
                        <td class="w-px">
                            <row-actions :edit-href="can('currencies.update') ? route('currencies.edit', currency.id) : null"
                                         :deletable="can('currencies.destroy')"
                                         @delete="deleteAction(currency.id)"/>
                        </td>
                    </tr>
                    <tr v-if="currencies.data.length === 0">
                        <td class="maiic-empty" colspan="6">No currencies found.</td>
                    </tr>
                    </tbody>
                </table>

            </div>
            <pagination :links="currencies.links"/>
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
        <teleport to="head">
            <title>{{ pageTitle }}</title>
            <meta property="og:description" :content="pageDescription">
        </teleport>
    </app-layout>

</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import RowActions from '@/Shared/RowActions.vue'
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
    metaInfo: {title: 'Currencies'},
    components: {
        AppLayout,
        RowActions,
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
        currencies: Object,
        filters: Object,

    },
    data() {
        return {
            form: {
                search: this.filters.search,
                processing: false
            },
            confirmingDeletion: false,
            selectedRecord: null,
            pageTitle: "Currencies",
            pageDescription: "Manage Currencies",

        }
    },
    watch: {
        form: {
            handler: _.debounce(function () {
                let query = pickBy(this.form)
                this.$inertia.get(this.route('currencies.index', Object.keys(query).length ? query : {}))
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

            this.$inertia.delete(this.route('currencies.destroy', this.selectedRecord))
            this.confirmingDeletion = false
        },
    },
}
</script>

<style scoped>

</style>
