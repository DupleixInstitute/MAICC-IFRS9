<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Economic Scenario Sets
            </h2>
        </template>
        <div class="mx-auto mb-4 flex justify-between items-center">
            <filter-search v-model="form.search" class="w-full max-w-md mr-4" @reset="reset">
                <div class="w-80 mt-2 px-4 py-6 shadow-xl bg-white rounded">
                    <!-- Additional filters can go here -->
                </div>
            </filter-search>
            <inertia-link class="btn btn-blue" :href="route('fli.scenarios.create')">
                <span>Create </span>
                <span class="hidden md:inline">Scenario Set</span>
            </inertia-link>
        </div>
        <div class="mx-auto">
            <div class="maiic-panel maiic-table-wrap">
                <table class="w-full whitespace-no-wrap table-auto">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Created By</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-if="!scenarioSets.data.length">
                        <td colspan="6" class="px-6 py-4 text-center">
                            No Scenario Sets found.
                        </td>
                    </tr>
                    <tr v-for="set in scenarioSets.data" :key="set.id"
                        class="hover:bg-gray-100 focus-within:bg-gray-100">
                        <td class="!p-0">
                            <inertia-link class="px-4 py-2.5 flex items-center" :href="route('fli.scenarios.edit', set.id)"
                                          tabindex="-1">
                                {{ set.id }}
                            </inertia-link>
                        </td>
                        <td class="!p-0">
                            <inertia-link class="px-4 py-2.5 flex items-center" :href="route('fli.scenarios.edit', set.id)"
                                          tabindex="-1">
                                {{ set.name }}
                            </inertia-link>
                        </td>
                        <td class="!p-0">
                            <span class="px-4 py-2.5 flex items-center">
                                {{ set.description }}
                            </span>
                        </td>
                        <td class="!p-0">
                            <span class="px-4 py-2.5 flex items-center">
                                <span v-if="set.is_active"
                                      class="px-2 rounded-full bg-maiic-100 text-maiic-800">
                                    Active
                                </span>
                                <span v-else
                                      class="px-2 rounded-full bg-gray-100 text-gray-800">
                                    Inactive
                                </span>
                            </span>
                        </td>
                        <td class="!p-0">
                            <span class="px-4 py-2.5 flex items-center">
                                {{ set.creator?.name || '-' }}
                            </span>
                        </td>
                        <td class="border-t w-px pr-2">
                            <div class="flex items-center gap-4">
                                <inertia-link :href="route('fli.scenarios.edit', set.id)"
                                              tabindex="-1" class="text-maiic-600 hover:text-maiic-900" title="Edit">
                                    <font-awesome-icon icon="edit"/>
                                </inertia-link>
                                <a href="#" @click="deleteAction(set.id)"
                                   class="text-red-600 hover:text-red-900" title="Delete">
                                    <font-awesome-icon icon="trash"/>
                                </a>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <pagination :links="scenarioSets.links"/>
        </div>
        <jet-confirmation-modal :show="confirmingDeletion" @close="confirmingDeletion = false">
            <template #title>
                Delete Scenario Set
            </template>

            <template #content>
                Are you sure you want to delete this scenario set? This action cannot be undone.
            </template>

            <template #footer>
                <jet-secondary-button @click.native="confirmingDeletion = false">
                    Cancel
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
        scenarioSets: Object,
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
            pageTitle: "Economic Scenario Sets",
            pageDescription: "Manage Economic Scenario Sets",
        }
    },
    watch: {
        form: {
            handler: _.debounce(function () {
                let query = pickBy(this.form)
                this.$inertia.get(this.route('fli.scenarios.index', Object.keys(query).length ? query : {}))
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
            this.$inertia.delete(this.route('fli.scenarios.destroy', this.selectedRecord))
            this.confirmingDeletion = false
        },
    },
}
</script>
