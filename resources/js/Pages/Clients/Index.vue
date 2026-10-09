<template>
    <app-layout title="Clients" description="The borrowers loans are booked to, created by the loan book import or added here">
        <template #actions>
            <input v-model="form.search" type="text" class="maiic-input w-64" placeholder="Search ID, name or phone" aria-label="Search clients"/>
            <Link v-if="can.create" :href="route('clients.import.create')" class="secondary-btn">Import clients</Link>
            <Link v-if="can.create" :href="route('clients.create')" class="primary-btn">New client</Link>
        </template>

        <div class="maiic-panel">
            <div class="maiic-table-wrap">
                <table class="maiic-table">
                    <thead>
                    <tr>
                        <th>Customer ID</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Updated</th>
                        <th class="text-right">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr v-for="client in (clients?.data || [])" :key="client.id">
                        <td class="whitespace-nowrap font-mono text-xs">{{ client.external_id }}</td>
                        <td class="font-semibold text-gray-900">{{ client.name }}</td>
                        <td class="whitespace-nowrap">{{ client.mobile || '-' }}</td>
                        <td class="whitespace-nowrap">{{ client.updated_at }}</td>
                        <td class="w-px">
                            <row-actions :view-href="route('clients.show', client.id)"
                                         :edit-href="can.edit ? route('clients.edit', client.id) : null"/>
                        </td>
                    </tr>
                    <tr v-if="!(clients?.data || []).length">
                        <td colspan="5" class="maiic-empty">{{ form.search ? 'No client matches the search.' : 'No clients yet. Import a loan book or a client file, or add a client with New client.' }}</td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <pagination v-if="clients?.links" :links="clients.links"/>
        <HelpManual/>
    </app-layout>
</template>

<script>
import { ref, watch } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import RowActions from '@/Shared/RowActions.vue'
import HelpManual from '@/Components/HelpManual.vue'
import Pagination from '@/Components/Pagination.vue'
import { Link, router } from '@inertiajs/vue3'
import pickBy from 'lodash/pickBy'
import debounce from 'lodash/debounce'

export default {
    components: { AppLayout, RowActions, Pagination, Link, HelpManual },

    props: {
        clients: { type: Object, default: () => ({}) },
        filters: Object,
        can: Object,
    },

    setup(props) {
        const form = ref({ search: props.filters.search || null })
        watch(form, debounce(() => {
            router.get(route('clients.index'), pickBy(form.value), { preserveState: true, preserveScroll: true, replace: true })
        }, 400), { deep: true })

        return { form }
    },
}
</script>
