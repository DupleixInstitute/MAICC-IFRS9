<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <inertia-link class="text-indigo-400 hover:text-indigo-600" :href="route('users.index')">Users
                </inertia-link>
                <span class="text-indigo-400 font-medium">/</span> {{ profile.name }}
            </h2>
        </template>
        <div class="mx-auto">
            <div class="md:flex md:items-start">
                <div class="bg-white relative shadow-xl mb-4 mt-20 w-full md:w-3/12">
                    <div class="col-span-12 lg:col-span-4 xxl:col-span-3 flex lg:block flex-col-reverse">
                        <div class="intro-y box mt-5 lg:mt-0">
                            <user-menu :profile="profile"></user-menu>
                        </div>
                    </div>
                </div>
                <div class="w-full md:w-9/12 md:ml-4 bg-white sm:mt-4">
                    <div class="bg-white rounded shadow overflow-x-auto p-4">
                        <div class="flex justify-between ">
                            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Availability Schedule</h2>
                            <button class="btn btn-blue" @click="showAddAvailabilityModal=true">
                                <span>Add Availability</span>
                            </button>
                        </div>
                        <table class="w-full whitespace-no-wrap  mt-4">
                            <thead class="bg-gray-50">
                            <tr class="text-left font-bold">
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Monday</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Tuesday</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Wednesday</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Thursday</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Friday</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Saturday</th>
                                <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Sunday</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td>
                                    <span class="block" v-for="item in monday">
                                        {{  (item.start_time).substr(0,5) }} to {{  (item.end_time).substr(0,5) }}
                                        <button type="button" @click="deleteAction(item.id)" class="p-2 text-red-600 ml-2">
                                            <font-awesome-icon icon="trash"></font-awesome-icon>
                                        </button>
                                    </span>
                                </td>
                                <td>
                                    <span class="block" v-for="item in tuesday">
                                       {{  (item.start_time).substr(0,5) }} to {{  (item.end_time).substr(0,5) }}
                                        <button type="button" @click="deleteAction(item.id)" class="p-2 text-red-600 ml-2">
                                            <font-awesome-icon icon="trash"></font-awesome-icon>
                                        </button>
                                    </span>
                                </td>
                                <td>
                                    <span class="block" v-for="item in wednesday">
                                        {{  (item.start_time).substr(0,5) }} to {{  (item.end_time).substr(0,5) }}
                                        <button type="button" @click="deleteAction(item.id)" class="p-2 text-red-600 ml-2">
                                            <font-awesome-icon icon="trash"></font-awesome-icon>
                                        </button>
                                    </span>
                                </td>
                                <td>
                                    <span class="block" v-for="item in thursday">
                                       {{  (item.start_time).substr(0,5) }} to {{  (item.end_time).substr(0,5) }}
                                        <button type="button" @click="deleteAction(item.id)" class="p-2 text-red-600 ml-2">
                                            <font-awesome-icon icon="trash"></font-awesome-icon>
                                        </button>
                                    </span>
                                </td>
                                <td>
                                    <span class="block" v-for="item in friday">
                                        {{  (item.start_time).substr(0,5) }} to {{  (item.end_time).substr(0,5) }}
                                        <button type="button" @click="deleteAction(item.id)" class="p-2 text-red-600 ml-2">
                                            <font-awesome-icon icon="trash"></font-awesome-icon>
                                        </button>
                                    </span>
                                </td>
                                <td>
                                    <span class="block" v-for="item in saturday">
                                        {{  (item.start_time).substr(0,5) }} to {{  (item.end_time).substr(0,5) }}
                                        <button type="button" @click="deleteAction(item.id)" class="p-2 text-red-600 ml-2">
                                            <font-awesome-icon icon="trash"></font-awesome-icon>
                                        </button>
                                    </span>
                                </td>
                                <td>
                                    <span class="block" v-for="item in sunday">
                                        {{  (item.start_time).substr(0,5) }} to {{  (item.end_time).substr(0,5) }}
                                        <button type="button" @click="deleteAction(item.id)" class="p-2 text-red-600 ml-2">
                                            <font-awesome-icon icon="trash"></font-awesome-icon>
                                        </button>
                                    </span>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <jet-dialog-modal :show="showAddAvailabilityModal" @close="showAddAvailabilityModal = false">
            <template #title>
                Add Availability
            </template>
            <template #content>

                <div class="grid grid-cols-1 gap-4 mt-4">
                    <div>
                        <jet-label for="day" value="Day"/>
                        <select
                            class="border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm w-full"
                            name="day" v-model="form.day" id="day"
                            required>
                            <option value="monday">Monday</option>
                            <option value="tuesday">Tuesday</option>
                            <option value="wednesday">Wednesday</option>
                            <option value="thursday">Thursday</option>
                            <option value="friday">Friday</option>
                            <option value="saturday">Saturday</option>
                            <option value="sunday">Sunday</option>
                        </select>
                        <jet-input-error :message="form.errors.day" class="mt-2"/>
                    </div>
                    <div>
                        <jet-label for="start_time" value="Start Time"/>
                        <flat-pickr
                            v-model="form.start_time"
                            class="form-control w-full"
                            placeholder="Select time"
                            :config="{time_24hr:true,noCalendar:true,enableTime:true,dateFormat:'H:i'}"
                            id="start_time"
                            name="start_time">
                        </flat-pickr>
                        <jet-input-error :message="form.errors.start_time" class="mt-2"/>

                    </div>
                    <div>
                        <jet-label for="end_time" value="End Time"/>
                        <flat-pickr
                            v-model="form.end_time"
                            class="form-control w-full"
                            placeholder="Select time"
                            :config="{time_24hr:true,noCalendar:true,enableTime:true,dateFormat:'H:i'}"
                            id="end_time"
                            name="end_time">
                        </flat-pickr>
                        <jet-input-error :message="form.errors.end_time" class="mt-2"/>
                    </div>
                </div>


            </template>

            <template #footer>
                <jet-secondary-button @click.native="showAddAvailabilityModal = false">
                    Cancel
                </jet-secondary-button>

                <jet-secondary-button class="ml-2" @click.native="submit"
                                      :class="{ 'opacity-25': form.processing }"
                                      :disabled="form.processing">
                    Save
                </jet-secondary-button>
            </template>

        </jet-dialog-modal>
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
import UserMenu from '@/Pages/Users/UserMenu.vue'
import Button from "@/Jetstream/Button.vue";
import JetDialogModal from "@/Jetstream/DialogModal.vue";
import JetInputError from "@/Jetstream/InputError.vue";

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
        UserMenu,
        JetDialogModal,
        JetInputError,
    },
    props: {
        profile: Object,
        monday: Object,
        tuesday: Object,
        wednesday: Object,
        thursday: Object,
        friday: Object,
        saturday: Object,
        sunday: Object,

    },
    data() {
        return {
            form: this.$inertia.form({
                day: '',
                start_time: '',
                end_time: '',
                description: '',
            }),
            confirmingDeletion: false,
            showAddAvailabilityModal: false,
            selectedRecord: null,
            pageTitle: "Availability Schedule",
            pageDescription: "Availability Schedule",

        }
    },
    mounted() {
        this.sortSchedule();
    },
    watch: {},
    methods: {
        submit() {
            this.form.post(this.route('users.availability_schedule.store', this.profile.id), {
                preserveState: false,
                onSuccess: () => {
                    this.showAddAvailabilityModal = false
                    this.$inertia.reload();
                }
            })

        },
        sortSchedule() {

        },
        deleteAction(id) {
            this.confirmingDeletion = true
            this.selectedRecord = id
        },
        destroy() {
            this.$inertia.delete(this.route('users.availability_schedule.destroy', this.selectedRecord), {
                preserveState: false,
                onSuccess: () => {
                    this.confirmingUserDeletion = false
                    this.$inertia.reload();
                }
            })

        },
    },
}
</script>

<style scoped>

</style>
