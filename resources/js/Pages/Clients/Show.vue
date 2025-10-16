<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('clients.index')">Clients
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> {{ client.name }}
            </h2>
        </template>
        <div class="mx-auto">
            <div class="md:flex md:items-start">
                <div class="bg-white relative shadow-xl mb-4 mt-20 w-full md:w-3/12">
                    <div class="col-span-12 lg:col-span-4 xxl:col-span-3 flex lg:block flex-col-reverse">
                        <div class="intro-y box mt-5 lg:mt-0">
                            <client-menu :client="client"></client-menu>
                        </div>
                    </div>

                </div>
                <div class="w-full md:w-9/12 md:ml-4 bg-white sm:mt-4">
                    <table class="border-collapse w-full border border-gray-400 bg-white text-sm shadow-sm">
                        <tbody>
                        <tr>
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Type</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500 capitalize">{{ client.type }}</td>
                        </tr>
                        <tr>
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Branch</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{ client.branch?.name }}</td>
                        </tr>
                        <tr>
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Status</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">
                                 <span
                                     class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded text-yellow-600 bg-yellow-200 uppercase"
                                     v-if="client.status=='pending'">
                                        Pending
                                 </span>
                                <span
                                    class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded text-yellow-600 bg-yellow-200 uppercase"
                                    v-if="client.status=='inactive'">
                                        Inactive
                                </span>
                                <span
                                    class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded text-blue-600 bg-blue-200 uppercase"
                                    v-if="client.status=='archived'">
                                        Archived
                                </span>
                                <span
                                    class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded text-green-600 bg-green-200 uppercase"
                                    v-if="client.status=='active'">
                                        Active
                                    </span>
                                <span
                                    class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded text-red-600 bg-red-200 uppercase"
                                    v-if="client.status=='deceased'">
                                        Deceased
                                </span>
                                <span
                                    class="text-xs font-semibold inline-block py-1 px-2 uppercase rounded text-red-600 bg-red-200 uppercase"
                                    v-if="client.status=='deceased'">
                                        Deceased
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">CIF</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{ client.external_id }}</td>
                        </tr>
                        <tr v-if="client.type==='corporate'">
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Trading Name</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{ client.trading_name }}</td>
                        </tr>
                        <tr v-if="client.type==='corporate'">
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Customer Legal
                                Type
                            </td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{
                                    client.legal_type?.name
                                }}
                            </td>
                        </tr>
                        <tr v-if="client.type==='corporate'">
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Certificate Of
                                Registration No
                            </td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{
                                    client.registration_number
                                }}
                            </td>
                        </tr>
                        <tr v-if="client.type==='corporate'">
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Year Of Registration
                            </td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{
                                    client.registration_year
                                }}
                            </td>
                        </tr>
                        <tr v-if="client.type==='corporate'">
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Years In Business
                            </td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{
                                    client.years_in_business
                                }}
                            </td>
                        </tr>
                        <tr v-if="client.type==='corporate'">
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Country Of Registration
                            </td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{
                                    client.registration_country?.name
                                }}
                            </td>
                        </tr>
                        <tr v-if="client.type==='corporate'">
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Audit Status
                            </td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{
                                    client.audit_status
                                }}
                            </td>
                        </tr>
                        <tr v-if="client.type==='corporate'">
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Annual Inflation Rate - Real
                            </td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{
                                    client.real_annual_inflation_rate
                                }}
                            </td>
                        </tr>
                        <tr v-if="client.type==='corporate'">
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">
                                Annual Inflation Rate - Norminal
                            </td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{
                                    client.nominal_annual_inflation_rate
                                }}
                            </td>
                        </tr>
                        <tr v-if="client.type==='corporate'">
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">
                                Industrial Sector
                            </td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{
                                    client.industry_type?.name
                                }}
                            </td>
                        </tr>
                        <tr v-if="client.type==='corporate'">
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">
                                Years At Present Address
                            </td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{
                                    client.years_at_present_address
                                }}
                            </td>
                        </tr>
                        <tr v-if="client.type==='corporate'">
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">
                                Main Bank
                            </td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{
                                    client.main_bank?.name
                                }}
                            </td>
                        </tr>
                        <tr v-if="client.type==='corporate'">
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">
                                Second Bank
                            </td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{
                                    client.second_bank?.name
                                }}
                            </td>
                        </tr>
                        <tr v-if="client.type==='corporate'">
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">
                                Third Bank
                            </td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{
                                    client.third_bank?.name
                                }}
                            </td>
                        </tr>
                        <tr>
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Mobile</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{ client.mobile }}</td>
                        </tr>
                        <tr>
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Tel</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{ client.tel }}</td>
                        </tr>
                        <tr v-if="client.type==='individual'">
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Date of Birth</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{ client.dob }}</td>
                        </tr>

                        <tr>
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Email</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{ client.email }}</td>
                        </tr>
                        <tr v-if="client.type==='individual'">
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Marital Status</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500 capitalize">
                                {{ client.marital_status }}
                            </td>
                        </tr>
                        <tr>
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">ID Number</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500 capitalize">
                                {{ client.id_number }}
                                <span v-if="client.id_number" class="capitalize">({{ client.id_number }})</span>
                            </td>
                        </tr>
                        <tr>
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Zip</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{ client.zip }}</td>
                        </tr>
                        <tr>
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Country</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{ client.country?.name }}</td>
                        </tr>
                        <tr>
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Region</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{ client.province?.name }}</td>
                        </tr>
                        <tr>
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Inkhundla</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{ client.district?.name }}</td>
                        </tr>
                        <tr class="hidden">
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Ward</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{ client.ward?.name }}</td>
                        </tr>
                        <tr class="hidden">
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Village</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{ client.village?.name }}</td>
                        </tr>

                        <tr>
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Address</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{ client.address }}</td>
                        </tr>
                        <tr>
                            <td class="w-1/2 border border-gray-300 font-semibold p-4 text-gray-900">Postal Address</td>
                            <td class="w-1/2 border border-gray-300 p-4 text-gray-500">{{ client.postal_address }}</td>
                        </tr>
                        </tbody>
                    </table>
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
import FilterSearch from '@/Jetstream/FilterSearch.vue'
import mapValues from 'lodash/mapValues'
import pickBy from 'lodash/pickBy'
import throttle from 'lodash/throttle'
import JetLabel from '@/Jetstream/Label.vue'
import SelectInput from '@/Jetstream/SelectInput.vue'
import JetConfirmationModal from '@/Jetstream/ConfirmationModal.vue'
import JetDangerButton from '@/Jetstream/DangerButton.vue'
import JetSecondaryButton from '@/Jetstream/SecondaryButton.vue'
import ClientMenu from '@/Pages/Clients/ClientMenu.vue'

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
        ClientMenu,
    },
    props: {
        client: Object,
        filters: Object,
        roles: Object,

    },
    data() {
        return {

            confirmingClientDeletion: false,
            selectedRecord: null,
            pageTitle: "Clients",
            pageDescription: "Manage Clients",

        }
    },
    watch: {
        form: {
            handler: _.debounce(function () {
                let query = pickBy(this.form)
                this.$inertia.get(this.route('clients.index', Object.keys(query).length ? query : {}))
            }, 500),
            deep: true,
        },
    },
    methods: {
        reset() {
            this.form = mapValues(this.form, () => null)
        },
        deleteAction(id) {
            this.confirmingClientDeletion = true
            this.selectedRecord = id
        },
        destroy() {

            this.$inertia.delete(this.route('clients.destroy', this.selectedRecord))
            this.confirmingClientDeletion = false
        },
    },
}
</script>

<style scoped>

</style>
