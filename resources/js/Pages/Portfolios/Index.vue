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

                        <div class="bg-white rounded shadow overflow-x-auto">
                            <table class="w-full whitespace-nowrap">
                                <tr class="text-left font-bold">
                                    <th class="px-6 pt-6 pb-4">Name</th>
                                    <th class="px-6 pt-6 pb-4">Description</th>
                                    <th class="px-6 pt-6 pb-4">Status</th>
                                    <th class="px-6 pt-6 pb-4">Created By</th>
                                    <th class="px-6 pt-6 pb-4">Created At</th>
                                    <th class="px-6 pt-6 pb-4"></th>
                                </tr>
                                <tr v-for="portfolio in portfolios.data" :key="portfolio.id" class="hover:bg-gray-100">
                                    <td class="border-t">
                                        <span class="px-6 py-4 flex items-center">
                                            {{ portfolio.name }}
                                        </span>
                                    </td>
                                    <td class="border-t">
                                        <span class="px-6 py-4 flex items-center">
                                            {{ portfolio.description }}
                                        </span>
                                    </td>
                                    <td class="border-t">
                                        <span class="px-6 py-4 flex items-center">
                                            <span :class="portfolio.active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                            >
                                                {{ portfolio.active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </span>
                                    </td>
                                    <td class="border-t">
                                        <span class="px-6 py-4 flex items-center">
                                            {{ portfolio.created_by?.name }}
                                        </span>
                                    </td>
                                    <td class="border-t">
                                        <span class="px-6 py-4 flex items-center">
                                            {{ portfolio.created_at }}
                                        </span>
                                    </td>
                                    <td class="border-t w-px">
                                        <div class="px-4 flex items-center gap-2">
                                            <Link
                                                :href="route('portfolios.edit', portfolio.id)"
                                                class="text-indigo-600 hover:text-indigo-900"
                                            >
                                                Edit
                                            </Link>
                                            <button
                                                @click="destroy(portfolio)"
                                                class="text-red-600 hover:text-red-900"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="portfolios.data.length === 0">
                                    <td class="border-t px-6 py-4" colspan="6">No portfolios found.</td>
                                </tr>
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
import pickBy from 'lodash/pickBy'
import throttle from 'lodash/throttle'
import mapValues from 'lodash/mapValues'
import HelpManual from '../../Components/HelpManual.vue';

export default {
    components: {
        AppLayout,
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
    @apply px-6 py-3 bg-indigo-600 text-white text-sm font-semibold rounded-md shadow-sm hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2;
}
</style>
