    <template>
        <app-layout>
            <template #header>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Product Groups
                </h2>
            </template>
            <div class=" mx-auto mb-4 flex justify-between items-center">
                <filter-search v-model="form.search" class="w-full max-w-md mr-4" @reset="reset">
                    <div class="w-80 mt-2 px-4 py-6 shadow-xl bg-white rounded">
                    </div>
                </filter-search>
                <inertia-link v-if="can('loans.products.create')" class="btn btn-blue" :href="route('groups.create')">
                    <span>Create </span>
                    <span class="hidden md:inline">Product Group</span>
                </inertia-link>
            </div>
            <div class=" mx-auto">
                <div class="maiic-panel maiic-table-wrap">
                    <table class="maiic-table">
                        <thead>
                        <tr>
                            <th>Name</th>
                            <th>Description</th>
                            <th class="text-right">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="group in (groups?.data || [])" :key="group.id">
                            <td class="!p-0">
                                <span class="px-4 py-2.5 flex items-center">
                                    {{ group.name }}
                                </span>
                            </td>
                            <td class="!p-0">
                                <span class="px-4 py-2.5 flex items-center">
                                    {{ group.description }}
                                </span>
                            </td>
                            <td class="border-t w-px pr-2">
                                <row-actions :edit-href="route('groups.edit', group.id)"
                                             :deletable="true"
                                             @delete="deleteAction(group.id)"/>
                            </td>
                        </tr>
                        <tr v-if="groups.data.length === 0">
                            <td class="border-t px-6 py-4 text-center" colspan="3">No groups found.</td>
                        </tr>
                        </tbody>
                    </table>

                </div>
                <pagination :links="groups.links"/>
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
        metaInfo: {title: 'Provinces'},
        components: {
            AppLayout,
        RowActions,
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
            groups: Object,
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
                pageTitle: "Loan Product Type",
                pageDescription: "Manage Product Types",

            }
        },
        watch: {
            form: {
                handler: _.debounce(function () {
                    let query = pickBy(this.form)
                    this.$inertia.get(this.route('groups.index', Object.keys(query).length ? query : {}))
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

                this.$inertia.delete(this.route('groups.destroy', this.selectedRecord))
                this.confirmingDeletion = false
            },
        },
    }
    </script>

    <style scoped>

    </style>
