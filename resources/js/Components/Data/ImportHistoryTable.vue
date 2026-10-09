<template>
    <!-- The latest rows of the imports log (every upload writes one), for
         the History tab of an import screen. The full log is on Imports. -->
    <div>
        <div class="maiic-table-wrap">
            <table class="maiic-table">
                <thead>
                <tr>
                    <th>File name</th>
                    <th>Status</th>
                    <th>Uploaded</th>
                    <th class="num">Records inserted</th>
                    <th class="num">Failed rows</th>
                    <th class="text-right">Actions</th>
                </tr>
                </thead>
                <tbody>
                <tr v-for="row in imports" :key="row.id">
                    <td class="min-w-[16rem] font-semibold text-gray-900">{{ row.name }}</td>
                    <td><span class="maiic-badge capitalize" :class="badge(row.status)">{{ row.status }}</span></td>
                    <td class="whitespace-nowrap">{{ String(row.created_at || '').replace('T', ' ').slice(0, 16) }}</td>
                    <td class="num">{{ count(row.records) }}</td>
                    <td class="num"><span :class="row.failed_records > 0 ? 'font-semibold text-red-600' : ''">{{ count(row.failed_records) }}</span></td>
                    <td class="text-right">
                        <a v-if="row.failed_records > 0 && row.failed_file_path" :href="route('imports.failed-download', row.id)" target="_blank"
                           class="maiic-action maiic-action-neutral" title="Download the failed rows">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L6.295 8.235a.75.75 0 10-1.09 1.03l4.25 4.5a.75.75 0 001.09 0l4.25-4.5a.75.75 0 00-1.09-1.03l-2.955 3.129V2.75z"/><path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z"/></svg>
                        </a>
                    </td>
                </tr>
                <tr v-if="!imports.length">
                    <td colspan="6" class="maiic-empty">{{ emptyText }}</td>
                </tr>
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between border-t border-gray-200 px-4 py-3 text-sm">
            <span class="text-gray-500">{{ caption || `The ${imports.length} most recent uploads of every kind.` }}</span>
            <Link :href="route('imports.index')" class="font-semibold text-maiic-700 hover:text-maiic-900">Open the full import history</Link>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    imports: { type: Array, default: () => [] },
    // Optional: a screen that shows only its own uploads says so here.
    caption: { type: String, default: '' },
    emptyText: { type: String, default: 'No file has been imported yet. Use the Upload tab to import the first one.' },
});

function badge(status) {
    return { completed: 'maiic-badge-green', processing: 'maiic-badge-gold', pending: 'maiic-badge-grey', failed: 'maiic-badge-red' }[status] || 'maiic-badge-grey';
}
function count(n) {
    return n === null || n === undefined ? '' : Number(n).toLocaleString();
}
</script>
