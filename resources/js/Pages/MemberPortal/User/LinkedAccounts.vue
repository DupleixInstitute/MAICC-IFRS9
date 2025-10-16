<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Linked Member Accounts
            </h2>
        </template>
        <div class=" mx-auto">
            <div class="bg-white rounded shadow overflow-x-auto">
                <table class="w-full whitespace-no-wrap">
                    <thead class="bg-gray-50">
                    <tr class="text-left font-bold">
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Name</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Gender</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Age</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="account in accounts" :key="account.id"
                        class="hover:bg-gray-100 focus-within:bg-gray-100">
                        <td class="border-t">
                            <span class="px-6 py-4 flex items-center" v-if="account.member">
                                    {{ account.member.name }}
                            </span>
                        </td>
                        <td class="border-t">
                            <span class="px-6 py-4 flex items-center" v-if="account.member">
                                <span v-if="account.member.gender=='male'">Male</span>
                                <span v-if="account.member.gender=='female'">Female</span>
                                <span v-if="account.member.gender=='unspecified'">Unspecified</span>
                            </span>
                        </td>
                        <td class="border-t">
                            <span class="px-6 py-4 flex items-center" v-if="account.member">
                                 {{ account.member.age }}
                            </span>
                        </td>
                        <td class="border-t w-px pr-2">
                            <div class=" flex items-center">
                                <span v-if="this.selectMemberID===account.member_id" class="text-xs font-semibold inline-block py-1 px-2  rounded text-green-600 bg-green-200 ">
                                    Selected
                                </span>
                                <span v-else>
                                    <button class="btn btn-blue" @click="selectMember(account.member_id)">
                                        <span>Select</span>
                                    </button>
                                </span>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="accounts.length === 0">
                        <td class="border-t px-6 py-4" colspan="4">No linked accounts found.</td>
                    </tr>
                    </tbody>
                </table>

            </div>
        </div>
        <teleport to="head">
            <title>{{ pageTitle }}</title>
            <meta property="og:description" :content="pageDescription">
        </teleport>
    </app-layout>

</template>

<script>


import AppLayout from '@/Pages/MemberPortal/Layouts/AppLayout.vue'
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
import Button from "@/Jetstream/Button.vue";

export default {
    components: {
        Button,
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
        accounts: Object,
        selectMemberID: String,

    },
    data() {
        return {
            form: {
                member_id: this.selectMemberID,
                processing: false
            },
            confirmingDeletion: false,
            selectedRecord: null,
            pageTitle: "Linked Member Accounts",
            pageDescription: "Linked Member Accounts",

        }
    },
    watch: {},
    methods: {
        selectMember(id) {

            this.$inertia.post(this.route('portal.users.linked_accounts.select'),{
                member_id:id
            })
            this.$inertia.refresh();
        },
    },
}
</script>

<style scoped>

</style>
