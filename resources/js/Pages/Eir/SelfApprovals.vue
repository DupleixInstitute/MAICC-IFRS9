<template>
    <app-layout title="Self-approvals" description="Every decision made without a second person under the maker-checker override (UAT mode), by workflow, person and date. Reviewed at handover.">
        <div class="w-full space-y-4">
            <KpiRow :cards="[
                { label: 'UAT mode on this server', value: inForce ? 'In force' : 'Not in force', accent: inForce ? '#d97706' : '#0f766e' },
                { label: 'Server environment', value: environment },
                { label: 'Self-approvals recorded', value: rows.length, accent: rows.length ? '#d97706' : '#0f766e' },
                { label: 'Workflows', value: Object.keys(byWorkflow).length },
            ]"/>

            <div class="maiic-panel p-4 text-sm text-gray-700">
                At handover every item below is reviewed. If a UAT database were ever promoted to production, each one must be approved again by two people first. Switching UAT mode off is an ordinary governed change with a second person (Governance Centre, "Administrator override of maker-checker"); setting APP_ENV=production on the server also stops it.
            </div>

            <div class="maiic-panel">
                <div class="flex items-center justify-between px-4 pt-3">
                    <div class="text-sm font-semibold text-gray-900">Decisions made under the override</div>
                    <a :href="route('eir-governance.self-approvals', { download: 'csv' })" class="secondary-btn">Export</a>
                </div>
                <div class="maiic-table-wrap">
                    <table class="maiic-table">
                        <thead><tr><th>When</th><th>Workflow</th><th>Record</th><th>Person</th><th>Environment</th></tr></thead>
                        <tbody>
                            <tr v-for="(r, i) in paged" :key="i">
                                <td class="whitespace-nowrap">{{ r.at }}</td>
                                <td>{{ r.workflow }}</td>
                                <td class="font-mono text-xs">{{ r.record }}</td>
                                <td>{{ r.name || ('user ' + r.person) }}</td>
                                <td class="text-xs">{{ r.environment }}</td>
                            </tr>
                            <tr v-if="!rows.length"><td colspan="5" class="text-center text-gray-500">No decision has been made under the override.</td></tr>
                        </tbody>
                    </table>
                </div>
                <RowPager v-model="page" :total="rows.length"/>
            </div>
        </div>
        <teleport to="head"><title>Self-approvals</title></teleport>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout.vue'
import KpiRow from '@/Components/Maiic/KpiRow.vue'
import RowPager from '@/Components/Maiic/RowPager.vue'

export default {
    components: { AppLayout, KpiRow, RowPager },
    props: {
        rows: { type: Array, default: () => [] },
        byWorkflow: { type: Object, default: () => ({}) },
        inForce: { type: Boolean, default: false },
        environment: { type: String, default: '' },
    },
    data() {
        return { page: 1 }
    },
    computed: {
        paged() {
            return this.rows.slice((this.page - 1) * 15, this.page * 15)
        },
    },
}
</script>
