<template>
    <app-layout>
        <template #header>
            <div>
                <div class="mb-1 flex items-center gap-2 text-xs text-gray-500">
                    <span>Administration</span><span>/</span><span>Legacy FLI tools</span><span>/</span><span class="font-medium text-maiic-700">Economic Scenarios</span>
                </div>
                <h2 class="text-xl font-semibold text-gray-800">Economic Scenarios</h2>
                <p class="mt-1 text-sm text-gray-600">Sets of economic scenarios and their macro paths, used by the external calculation</p>
            </div>
        </template>
        <template #actions>
            <Link :href="route('fli.scenarios.create')" class="primary-btn">Create scenario set</Link>
        </template>

        <LegacyNotice class="mb-4" />
        <div class="w-full space-y-4">
            <div class="maiic-panel">
                <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h3 class="font-semibold text-gray-900">Scenario sets</h3>
                        <p class="text-xs text-gray-500">{{ scenarioSets.total ?? scenarioSets.data.length }} set(s)</p>
                    </div>
                    <div class="flex items-end gap-2">
                        <label class="block"><span class="maiic-flabel">Search</span><input v-model="form.search" type="text" class="maiic-input !w-64" placeholder="Name or description" /></label>
                        <button v-if="form.search" type="button" class="secondary-btn" @click="reset">Clear</button>
                    </div>
                </div>
                <div class="maiic-table-wrap overflow-x-auto">
                    <table class="maiic-table">
                        <thead>
                            <tr><th>Name</th><th>Description</th><th>Status</th><th>Created by</th><th class="text-right">Actions</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="set in scenarioSets.data" :key="set.id">
                                <td class="font-semibold text-gray-900"><Link :href="route('fli.scenarios.edit', set.id)" class="hover:underline">{{ set.name }}</Link><div class="text-xs font-normal text-gray-500">#{{ set.id }}</div></td>
                                <td>{{ set.description || '-' }}</td>
                                <td><span class="maiic-badge" :class="set.is_active ? 'maiic-badge-green' : 'maiic-badge-grey'">{{ set.is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td>{{ set.creator?.name || '-' }}</td>
                                <td>
                                    <div class="flex justify-end gap-1.5">
                                        <Link :href="route('fli.scenarios.edit', set.id)" class="maiic-action maiic-action-edit" title="Edit scenario set"><font-awesome-icon icon="pen" /></Link>
                                        <button type="button" class="maiic-action maiic-action-delete" title="Delete scenario set" @click="deleteAction(set.id)"><font-awesome-icon icon="trash" /></button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!scenarioSets.data.length">
                                <td colspan="5" class="maiic-empty">{{ form.search ? 'No scenario sets match the search.' : 'No scenario sets yet. Use Create scenario set at the top right.' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="scenarioSets.links && scenarioSets.links.length > 3" class="border-t border-gray-100 px-4 pb-4">
                    <pagination :links="scenarioSets.links"/>
                </div>
            </div>
        </div>
        <jet-confirmation-modal :show="confirmingDeletion" @close="confirmingDeletion = false">
            <template #title>
                Delete this scenario set?
            </template>

            <template #content>
                The scenario set and its scenarios are removed. This cannot be undone.
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
import LegacyNotice from '@/Components/Maiic/LegacyNotice.vue'
import { Link } from '@inertiajs/vue3'
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
        LegacyNotice,
        Link,
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
