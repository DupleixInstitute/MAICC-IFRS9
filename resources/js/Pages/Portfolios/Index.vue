<template>
    <app-layout title="Loan Portfolios">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Loan Portfolios
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <div class="flex items-center justify-between mb-6">
                            <div class="flex items-center w-full max-w-md mr-4">
                                <div class="relative flex-1">
                                    <input
                                        v-model="form.search"
                                        type="text"
                                        class="w-full pl-8 pr-4 py-2 border rounded-lg"
                                        placeholder="Search..."
                                    />
                                </div>
                                <select
                                    v-model="form.status"
                                    class="ml-3 border rounded-lg px-4 py-2"
                                >
                                    <option :value="null">All Status</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <Link
                                :href="route('portfolios.create')"
                                class="btn-indigo"
                            >
                                <span>Create Portfolio</span>
                            </Link>
                        </div>

                        <div class="maiic-panel maiic-table-wrap">
                            <table class="maiic-table">
                                <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Created At</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                <tr v-for="portfolio in portfolios.data" :key="portfolio.id">
                                    <td class="!p-0">
                                        <span class="px-4 py-2.5 flex items-center">
                                            {{ portfolio.name }}
                                        </span>
                                    </td>
                                    <td class="!p-0">
                                        <span class="px-4 py-2.5 flex items-center">
                                            {{ portfolio.description }}
                                        </span>
                                    </td>
                                    <td class="!p-0">
                                        <span class="px-4 py-2.5 flex items-center">
                                            <span :class="portfolio.active ? 'bg-maiic-100 text-maiic-800' : 'bg-red-100 text-red-800'"
                                                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                            >
                                                {{ portfolio.active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </span>
                                    </td>
                                    <td class="!p-0">
                                        <span class="px-4 py-2.5 flex items-center">
                                            {{ portfolio.created_by?.name }}
                                        </span>
                                    </td>
                                    <td class="!p-0">
                                        <span class="px-4 py-2.5 flex items-center">
                                            {{ portfolio.created_at }}
                                        </span>
                                    </td>
                                    <td class="w-px">
                                        <row-actions :edit-href="route('portfolios.edit', portfolio.id)"
                                                     :deletable="true"
                                                     @delete="destroy(portfolio)"/>
                                    </td>
                                </tr>
                                <tr v-if="portfolios.data.length === 0">
                                    <td class="maiic-empty" colspan="6">No portfolios found.</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                        <pagination
                            v-if="portfolios.links.length > 3"
                            :links="portfolios.links"
                            class="mt-6"
                        />
                    </div>
                </div>
            </div>
        </div>
        <HelpManual />
    </app-layout>
</template>

<script>
import { ref, watch } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import RowActions from '@/Shared/RowActions.vue'
import pickBy from 'lodash/pickBy'
import throttle from 'lodash/throttle'
import mapValues from 'lodash/mapValues'
import HelpManual from '../../Components/HelpManual.vue';

export default {
    components: {
        AppLayout,
        RowActions,
        Link,
        HelpManual,
    },
    props: {
        filters: Object,
        portfolios: Object,
    },
    setup(props) {
        const form = ref({
            search: props.filters.search,
            status: props.filters.status,
        })

        const performSearch = throttle((searchQuery, statusFilter) => {
            router.get(route('portfolios.index'), 
                { 
                    search: searchQuery,
                    status: statusFilter,
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true
                }
            )
        }, 300)

        watch(() => form.value.search, (newValue) => {
            performSearch(newValue, form.value.status)
        })

        watch(() => form.value.status, (newValue) => {
            performSearch(form.value.search, newValue)
        })

        function destroy(portfolio) {
            if (confirm('Are you sure you want to delete this portfolio?')) {
                router.delete(route('portfolios.destroy', portfolio.id), {}, {
                    preserveScroll: true,
                    preserveState: true,
                    onSuccess: () => {
                        // Optional: Show success message
                    },
                    onError: (errors) => {
                        // Optional: Handle errors
                    }
                })
            }
        }

        return { form, destroy }
    }
}
</script>

<style scoped>
.btn-indigo {
    @apply px-6 py-3 bg-maiic-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-maiic-500 focus:outline-none focus:ring-2 focus:ring-maiic-500 focus:ring-offset-2;
}
</style>
