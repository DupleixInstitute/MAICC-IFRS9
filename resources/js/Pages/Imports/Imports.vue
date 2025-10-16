<template>
    <app-layout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Imports
            </h2>
        </template>
        <div class=" mx-auto mb-4 flex justify-between items-center">
            <filter-search v-model="form.search" class="w-full max-w-md mr-4" @reset="reset">
                <div class="w-80 mt-2 px-4 py-6 shadow-xl bg-white rounded">
                </div>
            </filter-search>
        </div>
        <div class=" mx-auto">
            <div class="bg-white rounded shadow overflow-x-auto">
                <table class="w-full whitespace-no-wrap">
                    <thead class="bg-gray-50">
                    <tr class="text-left font-bold">
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Name</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Status</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Date</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Inserted</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Exception Records</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Start</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Completed</th>
                        <th class="px-6 pt-4 pb-4 font-medium text-gray-500">Duration</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="result in results.data" :key="result.id"
                        class="hover:bg-gray-100 focus-within:bg-gray-100">
                        <td class="border-t">
                             <span class="px-6 py-4 flex items-center">
                                {{ result.name }}
                            </span>
                        </td>
                        <td class="border-t">
                            <span v-if="result.status=='pending'"
                                  class="px-2 rounded-full bg-yellow-100 text-yellow-800">
                                        pending
                                    </span>
                            <span v-if="result.status=='processing'"
                                  class="px-2 rounded-full bg-blue-100 text-blue-800">
                                        processing
                                    </span>

                            <span v-if="result.status=='completed'"
                                  class="px-2 rounded-full bg-green-100 text-green-800">
                                        completed
                                    </span>
                            <span v-if="result.status=='failed'"
                                  class="px-2 rounded-full bg-red-100 text-red-800">
                                        failed
                                    </span>
                        </td>
                        <td class="border-t">
                             <span class="px-6 py-4 flex items-center" v-if="result.status">
                                {{ $filters.time(result.created_at) }}
                            </span>
                        </td>
                        <td class="border-t">
                             <span class="px-6 py-4 flex items-center">
                                {{ result.records }}
                            </span>
                        </td>
                        <td class="border-t px-6 py-10 flex items-center">
                            {{ result.failed_records }}
                            <button
                                v-if="result.failed_records > 0 && result.failed_file_path"
                                @click="downloadFailedFile(result.id)"
                                class="text-blue-600 hover:underline"
                            >
                                <font-awesome-icon icon="download" class="ml-2" />
                            </button>
                        </td>
                        <td class="border-t">
                             <span class="px-6 py-4 flex items-center" v-if="result.status">
                                {{ result.started_at ? $filters.time(result.started_at) : '' }}
                            </span>
                        </td>
                        <td class="border-t">
                             <span class="px-6 py-4 flex items-center" v-if="result.status">
                                {{ result.completed_at ? $filters.time(result.completed_at) : '' }}
                            </span>
                        </td>
                        <td class="border-t">
                            <span class="px-6 py-4 flex items-center" v-if="result.started_at && result.completed_at">
                                {{ calculateDuration(result.started_at, result.completed_at) }}
                            </span>
                        </td>
                    </tr>
                    <tr v-if="results.data.length === 0">
                        <td class="border-t px-6 py-4 text-center" colspan="3">No records found.</td>
                    </tr>
                    </tbody>
                </table>

            </div>
            <pagination :links="results.links"/>
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
        <HelpManual />
    </app-layout>

</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
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
import HelpManual from '../../Components/HelpManual.vue';

export default {
    components: {
        AppLayout,
        Pagination,
        FilterSearch,
        JetLabel,
        SelectInput,
        JetConfirmationModal,
        JetDangerButton,
        JetSecondaryButton,
        HelpManual
    },
    props: {
        results: Object,
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
            pageTitle: "Imports",
            pageDescription: "Manage Imports",

        }
    },
    watch: {
        form: {
            handler: _.debounce(function () {
                let query = pickBy(this.form)
                this.$inertia.get(this.route('imports.index', Object.keys(query).length ? query : {}))
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

            this.$inertia.delete(this.route('imports.destroy', this.selectedRecord))
            this.confirmingDeletion = false
        },
        downloadFailedFile(importId) {
            const url = this.route('imports.failed-download', importId);
            window.open(url, '_blank');
        },
        calculateDuration(start, end) {
            const startTime = new Date(start);
            const endTime = new Date(end);
            const diffMs = endTime - startTime;

            const totalSeconds = Math.floor(diffMs / 1000);
            const hours = Math.floor(totalSeconds / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const seconds = totalSeconds % 60;

            let duration = '';
            if (hours > 0) {
                duration += `${hours}h `;
            }
            if (minutes > 0 || hours > 0) {
                duration += `${minutes}m `;
            }
            duration += `${seconds}s`;

            return duration.trim();
        },
    },
}
</script>

<style scoped>

</style>
