<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Organisation Settings
            </h2>
        </template>
        <div class=" mx-auto">
            <div class="bg-white rounded shadow overflow-x-auto">

                <inertia-link v-if="can('loans.approval_stages.index')"
                              class="w-full border-t-2 border-gray-100 font-medium text-gray-600 py-2 px-4 w-full block hover:bg-gray-100 transition duration-150"
                              :href="route('loan_approval_stages.index')">
                    <font-awesome-icon icon="cogs" class="w-4 h-4 mr-2"></font-awesome-icon>
                    Manage Approval Stages
                </inertia-link>
                <inertia-link v-if="can('banks')"
                              class="w-full border-t-2 border-gray-100 font-medium text-gray-600 py-2 px-4 w-full block hover:bg-gray-100 transition duration-150"
                              :href="route('banks.index')">
                    <font-awesome-icon icon="building" class="w-4 h-4 mr-2"></font-awesome-icon>
                    Manage Banks
                </inertia-link>
                <inertia-link v-if="can('legal_types')"
                              class="w-full border-t-2 border-gray-100 font-medium text-gray-600 py-2 px-4 w-full block hover:bg-gray-100 transition duration-150"
                              :href="route('legal_types.index')">
                    <font-awesome-icon icon="user" class="w-4 h-4 mr-2"></font-awesome-icon>
                    Manage Legal Types
                </inertia-link>
                <inertia-link v-if="can('chart_of_accounts')"
                              class="w-full border-t-2 border-gray-100 font-medium text-gray-600 py-2 px-4 w-full block hover:bg-gray-100 transition duration-150"
                              :href="route('chart_of_accounts.index')">
                    <font-awesome-icon icon="database" class="w-4 h-4 mr-2"></font-awesome-icon>
                    Manage Chart Of Accounts
                </inertia-link>
                <inertia-link v-if="can('industry_types')"
                              class="w-full border-t-2 border-gray-100 font-medium text-gray-600 py-2 px-4 w-full block hover:bg-gray-100 transition duration-150"
                              :href="route('industry_types.index')">
                    <font-awesome-icon icon="user" class="w-4 h-4 mr-2"></font-awesome-icon>
                    Manage Industry Types
                </inertia-link>
                <inertia-link v-if="can('currencies')"
                              class="w-full border-t-2 border-gray-100 font-medium text-gray-600 py-2 px-4 w-full block hover:bg-gray-100 transition duration-150"
                              :href="route('currencies.index')">
                    <font-awesome-icon icon="dollar-sign" class="w-4 h-4 mr-2"></font-awesome-icon>
                    Manage Currencies
                </inertia-link>
                <inertia-link v-if="can('branches')"
                              class="w-full border-t-2 border-gray-100 font-medium text-gray-600 py-2 px-4 w-full block hover:bg-gray-100 transition duration-150"
                              :href="route('branches.index')">
                    <font-awesome-icon icon="building" class="w-4 h-4 mr-2"></font-awesome-icon>
                    Manage Branches
                </inertia-link>
                <inertia-link v-if="can('users.roles')"
                              class="w-full border-t-2 border-gray-100 font-medium text-gray-600 py-2 px-4 w-full block hover:bg-gray-100 transition duration-150"
                              :href="route('users.roles.index')">
                    <font-awesome-icon icon="users" class="w-4 h-4 mr-2"></font-awesome-icon>
                    Manage Roles
                </inertia-link>
                <inertia-link v-if="can('communication.sms_gateways.index')"
                              class="w-full border-t-2 border-gray-100 font-medium text-gray-600 py-2 px-4 w-full block hover:bg-gray-100 transition duration-150"
                              :href="route('communication.sms_gateways.index')">
                    <font-awesome-icon icon="envelope" class="w-4 h-4 mr-2"></font-awesome-icon>
                    Manage SMS Gateways
                </inertia-link>

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
    props: {},
    data() {
        return {
            confirmingDeletion: false,
            selectedRecord: null,
            pageTitle: "Organisation Settings",
            pageDescription: "Manage Settings",
        }
    },

    methods: {},
}
</script>

<style scoped>

</style>
