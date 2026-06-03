<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Reports
            </h2>
        </template>
        <div class=" mx-auto">
            <div v-for="category in reports" :key="category.symbol" class="mb-6">
                <h3 class="font-semibold text-gray-700 mb-2">
                    {{ category.name }}
                </h3>
                <div class="bg-white rounded shadow overflow-x-auto">
                    <inertia-link v-for="report in category.reports"
                                  :key="report.route"
                                  v-show="can(report.permission)"
                                  class="w-full border-t-2 border-gray-100 font-medium text-gray-600 py-2 px-4 block hover:bg-gray-100 transition duration-150"
                                  :href="route(report.route)">
                        <font-awesome-icon :icon="reportIcon(category)" class="w-4 h-4 mr-2"></font-awesome-icon>
                        {{ report.name }}
                    </inertia-link>
                </div>
            </div>
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
        reports: Object,
    },
    data() {
        return {
            confirmingDeletion: false,
            selectedRecord: null,
            pageTitle: "Reports",
            pageDescription: "Manage Reports",
        }
    },

    methods: {
        reportIcon(category) {
            return /export/i.test(category.name) ? 'file-export' : 'balance-scale'
        },
        reset() {
            this.form = mapValues(this.form, () => null)
        },
        deleteAction(id) {
            this.confirmingDeletion = true
            this.selectedRecord = id
        },
        destroy() {

            this.$inertia.delete(this.route('tariffs.destroy', this.selectedRecord))
            this.confirmingDeletion = false
        },
    },
}
</script>

<style scoped>

</style>
