<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import DocumentCover from '@/Shared/DocumentCover.vue'

// Repository-authored documentation reader (Ticket #011): the Technical
// Manual and the Installation Guide. Chapters arrive as pre-rendered HTML
// with anchored headings; the Technical Manual also carries a live schema
// appendix read from the connected database.
const props = defineProps({
    doc: { type: String, default: 'technical' },
    front: { type: Object, default: null },
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    deliverable: { type: String, default: '' },
    company: { type: String, default: 'MAIIC' },
    pdfRoute: { type: String, default: '' },
    chapters: { type: Array, default: () => [] },
    schema: { type: Array, default: () => [] },
    lastRevised: { type: String, default: null },
})

const search = ref('')
const tableFilter = ref('')
const openTable = ref(null)

// Client-side filter over chapter text (titles and body).
const filteredChapters = computed(() => {
    const q = search.value.trim().toLowerCase()
    if (!q) return props.chapters
    return props.chapters.filter(c => (c.title + ' ' + c.html).toLowerCase().includes(q))
})

const filteredTables = computed(() => {
    const q = tableFilter.value.trim().toLowerCase()
    if (!q) return props.schema
    return props.schema.filter(t => t.name.toLowerCase().includes(q) || t.columns.some(c => c.name.toLowerCase().includes(q)))
})

function jump(id) {
    const el = document.getElementById(id)
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}
</script>

<template>
    <AppLayout :title="title">
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ title }}</h2>
                    <p v-if="subtitle" class="text-sm text-gray-500">{{ subtitle }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span v-if="lastRevised" class="maiic-badge maiic-badge-grey">Revised {{ lastRevised }}</span>
                    <a :href="pdfRoute"
                       class="inline-flex items-center gap-1.5 rounded-lg bg-maiic-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-maiic-700">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 10v6m0 0-3-3m3 3 3-3"/><path d="M20 21H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h9l7 7v10a1 1 0 0 1-1 1Z"/></svg>
                        Download PDF
                    </a>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex gap-8">

                    <!-- Contents rail -->
                    <aside class="hidden w-72 flex-none lg:block">
                        <div class="sticky top-6 maiic-panel p-4">
                            <input v-model="search" type="text" placeholder="Search this document..." class="maiic-input mb-4"/>
                            <nav class="max-h-[70vh] space-y-3 overflow-y-auto pr-1">
                                <div v-for="c in filteredChapters" :key="c.slug">
                                    <button @click="jump(c.slug)"
                                            class="w-full text-left text-[11px] font-extrabold uppercase tracking-wider text-maiic-800 hover:text-maiic-600">
                                        {{ c.title }}
                                    </button>
                                    <ul class="mt-1 space-y-0.5">
                                        <li v-for="s in c.sections" :key="s.id">
                                            <button @click="jump(s.id)"
                                                    class="w-full rounded px-2 py-0.5 text-left text-sm text-gray-600 hover:bg-maiic-50 hover:text-maiic-800">
                                                {{ s.title }}
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                                <div v-if="schema.length">
                                    <button @click="jump('appendix-schema')"
                                            class="w-full text-left text-[11px] font-extrabold uppercase tracking-wider text-maiic-800 hover:text-maiic-600">
                                        Appendix A. Live database schema
                                    </button>
                                </div>
                            </nav>
                        </div>
                    </aside>

                    <!-- Document body -->
                    <article class="min-w-0 flex-1 space-y-8">
                        <DocumentCover v-if="front" :front="front"/>
                        <div v-if="!chapters.length" class="maiic-panel p-10 text-center font-semibold text-gray-400">
                            This document has no chapters yet. Developers add Markdown files under docs/manuals.
                        </div>

                        <section v-for="c in filteredChapters" :key="c.slug" class="maiic-panel scroll-mt-24 p-6">
                            <div class="docs-prose prose prose-sm max-w-none text-gray-700" v-html="c.html"></div>
                        </section>

                        <!-- Live schema appendix (Technical Manual only) -->
                        <section v-if="schema.length" id="appendix-schema" class="maiic-panel scroll-mt-24 p-6">
                            <h2 class="mb-1 text-xl font-bold text-gray-900">Appendix A. Live database schema</h2>
                            <p class="mb-4 text-sm text-gray-500">
                                {{ schema.length }} tables read from the connected database at render time. Click a table to see its columns.
                            </p>
                            <input v-model="tableFilter" type="text" placeholder="Filter tables or columns..." class="maiic-input mb-4 max-w-md"/>
                            <div class="maiic-table-wrap">
                                <table class="maiic-table">
                                    <thead>
                                        <tr>
                                            <th>Table</th>
                                            <th class="num">Columns</th>
                                            <th class="num">Rows</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template v-for="t in filteredTables" :key="t.name">
                                            <tr class="cursor-pointer" @click="openTable = openTable === t.name ? null : t.name">
                                                <td class="font-mono text-xs font-semibold text-maiic-800">{{ t.name }}</td>
                                                <td class="num">{{ t.columns.length }}</td>
                                                <td class="num">{{ t.row_count.toLocaleString() }}</td>
                                            </tr>
                                            <tr v-if="openTable === t.name">
                                                <td colspan="3" class="bg-maiic-50/40 p-0">
                                                    <table class="w-full text-xs">
                                                        <tbody>
                                                            <tr v-for="col in t.columns" :key="col.name" class="border-t border-gray-100">
                                                                <td class="w-1/3 px-4 py-1 font-mono">{{ col.name }}</td>
                                                                <td class="w-1/3 px-4 py-1 text-gray-600">{{ col.type }}</td>
                                                                <td class="px-4 py-1 text-gray-500">
                                                                    <span v-if="col.key === 'PRI'" class="maiic-badge maiic-badge-green">primary key</span>
                                                                    <span v-else-if="col.key === 'MUL'" class="maiic-badge maiic-badge-gold">indexed</span>
                                                                    <span v-else-if="col.key === 'UNI'" class="maiic-badge maiic-badge-grey">unique</span>
                                                                    <span v-if="col.nullable" class="ml-1 text-gray-400">nullable</span>
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </article>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
/* Markdown output styling on top of the typography plugin: brand headings,
   readable tables and code, all within the green, gold, red, grey system. */
.docs-prose h2 { border-left: 4px solid #16a34a; padding-left: 0.75rem; color: #111827; font-size: 1.35rem; margin-top: 0; }
.docs-prose h3 { color: #166534; margin-top: 1.5rem; }
.docs-prose table { font-size: 0.8rem; }
.docs-prose thead th { background: #15803d; color: #fff; text-transform: uppercase; font-size: 0.68rem; letter-spacing: 0.05em; padding: 0.5rem 0.75rem; }
.docs-prose tbody td { padding: 0.4rem 0.75rem; vertical-align: top; }
.docs-prose tbody tr:nth-child(even) { background: #f9fafb; }
.docs-prose code { color: #92400e; background: #fffbeb; padding: 0.1rem 0.3rem; border-radius: 0.25rem; font-weight: 500; }
.docs-prose pre { background: #f8fafc; border-left: 3px solid #f59e0b; color: #1f2937; }
.docs-prose pre code { background: transparent; color: inherit; padding: 0; }
.docs-prose a { color: #15803d; }
</style>
