<script setup>
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import DocumentCover from '@/Shared/DocumentCover.vue'
import ClientPager from '@/Components/ClientPager.vue'

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
const tablePage = ref(1)
// One chapter on screen at a time (cover first, schema appendix last).
const chapter = ref(props.front ? 'cover' : (props.chapters[0]?.slug ?? null))
const order = computed(() => [...(props.front ? ['cover'] : []), ...props.chapters.map(c => c.slug), ...(props.schema.length ? ['schema'] : [])])
const position = computed(() => order.value.indexOf(chapter.value))
const current = computed(() => props.chapters.find(c => c.slug === chapter.value) || null)
function label(key) {
    if (key === 'cover') return 'Cover and contents'
    if (key === 'schema') return 'Appendix A. Database tables'
    return props.chapters.find(c => c.slug === key)?.title || ''
}
function openChapter(key) {
    chapter.value = key
    window.scrollTo({ top: 0, behavior: 'smooth' })
}

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

function jump(chapterSlug, id) {
    search.value = ''
    chapter.value = chapterSlug
    setTimeout(() => {
        const el = document.getElementById(id)
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }, 50)
}
const pagedTables = computed(() => filteredTables.value.slice((tablePage.value - 1) * 15, tablePage.value * 15))
</script>

<template>
    <AppLayout :title="title">
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ title }}</h2>
                <p class="mt-0.5 text-sm text-gray-500">{{ subtitle }}<span v-if="chapters.length">. {{ chapters.length }} chapters</span><span v-if="lastRevised">, revised {{ lastRevised }}</span></p>
            </div>
        </template>
        <template #actions>
            <a :href="pdfRoute" class="inline-flex items-center gap-2 rounded-lg bg-maiic-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-maiic-700">
                <font-awesome-icon icon="file-pdf"/> Download PDF
            </a>
        </template>

        <div>
            <div class="w-full">
                <div class="flex gap-6">

                    <!-- Contents rail -->
                    <aside class="hidden w-72 flex-none lg:block">
                        <div class="sticky top-[4.5rem] flex h-[calc(100vh-5rem)] flex-col maiic-panel p-4">
                            <input v-model="search" type="text" placeholder="Search this document..." class="maiic-input mb-4"/>
                            <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto pr-1" aria-label="Chapters">
                                <button v-if="front && !search" type="button" @click="openChapter('cover')"
                                        class="w-full rounded-lg border-l-4 px-3 py-2 text-left text-sm transition"
                                        :class="chapter === 'cover' ? 'border-maiic-600 bg-maiic-50 font-bold text-maiic-800' : 'border-transparent text-gray-600 hover:bg-gray-50'">Cover and contents</button>
                                <div v-for="c in filteredChapters" :key="c.slug">
                                    <button type="button" @click="search ? jump(c.slug, c.slug) : openChapter(c.slug)"
                                            class="w-full rounded-lg border-l-4 px-3 py-2 text-left text-sm transition"
                                            :class="!search && chapter === c.slug ? 'border-maiic-600 bg-maiic-50 font-bold text-maiic-800' : 'border-transparent text-gray-700 hover:bg-gray-50'">{{ c.title }}</button>
                                    <ul v-if="(search || chapter === c.slug) && c.sections.length" class="mb-2 ml-4 mt-1 space-y-0.5 border-l border-gray-200 pl-2">
                                        <li v-for="sec in c.sections" :key="sec.id">
                                            <button type="button" @click="jump(c.slug, sec.id)" class="w-full rounded px-2 py-1 text-left text-xs text-gray-600 hover:bg-maiic-50 hover:text-maiic-800">{{ sec.title }}</button>
                                        </li>
                                    </ul>
                                </div>
                                <button v-if="schema.length && !search" type="button" @click="openChapter('schema')"
                                        class="w-full rounded-lg border-l-4 px-3 py-2 text-left text-sm transition"
                                        :class="chapter === 'schema' ? 'border-maiic-600 bg-maiic-50 font-bold text-maiic-800' : 'border-transparent text-gray-700 hover:bg-gray-50'">Appendix A. Database tables</button>
                                <p v-if="search && !filteredChapters.length" class="px-3 py-2 text-xs text-gray-500">Nothing in this document matches "{{ search }}".</p>
                            </nav>
                        </div>
                    </aside>

                    <!-- Document body -->
                    <article class="min-w-0 flex-1 space-y-6">
                        <select v-if="order.length > 1" class="maiic-select lg:hidden" :value="chapter" aria-label="Chapter" @change="openChapter($event.target.value)">
                            <option v-for="k in order" :key="k" :value="k">{{ label(k) }}</option>
                        </select>
                        <template v-if="chapter === 'cover' && front && !search">
                            <DocumentCover :front="front"/>
                            <div v-if="chapters.length" class="maiic-panel p-6">
                                <h3 class="maiic-section-title mt-0">Contents</h3>
                                <ol class="grid grid-cols-1 gap-x-8 gap-y-1 sm:grid-cols-2">
                                    <li v-for="(k, i) in order.filter(k => k !== 'cover')" :key="k">
                                        <button type="button" class="w-full rounded px-2 py-1.5 text-left text-sm hover:bg-maiic-50" @click="openChapter(k)">{{ label(k) }}</button>
                                    </li>
                                </ol>
                            </div>
                        </template>
                        <div v-if="!chapters.length" class="maiic-panel p-10 text-center">
                            <div class="text-base font-bold text-gray-800">This document has no chapters yet</div>
                            <p class="mt-1 text-sm text-gray-500">Its chapters are written by the system's developers and appear here once added.</p>
                        </div>

                        <section v-for="c in (search ? filteredChapters : (current ? [current] : []))" :key="c.slug" class="maiic-panel scroll-mt-24 p-6">
                            <div class="docs-prose prose prose-sm max-w-none text-gray-700" v-html="c.html"></div>
                        </section>

                        <!-- Live schema appendix (Technical Manual only) -->
                        <section v-if="schema.length && chapter === 'schema' && !search" id="appendix-schema" class="maiic-panel scroll-mt-24 p-6">
                            <h2 class="mb-1 text-xl font-bold text-gray-900">Appendix A. Database tables</h2>
                            <p class="mb-4 text-sm text-gray-500">
                                {{ schema.length }} tables, read from the connected database when this page opened. Click a table to see its columns.
                            </p>
                            <input v-model="tableFilter" type="text" placeholder="Filter tables or columns" class="maiic-input mb-4 max-w-md" @input="tablePage = 1"/>
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
                                        <template v-for="t in pagedTables" :key="t.name">
                                            <tr class="cursor-pointer" @click="openTable = openTable === t.name ? null : t.name">
                                                <td class="font-mono text-xs font-semibold text-maiic-800">{{ t.name }}</td>
                                                <td class="num">{{ t.columns.length }}</td>
                                                <td class="num">{{ t.row_count.toLocaleString('en-GB') }}</td>
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
                            <div v-if="filteredTables.length > 15" class="mt-4"><ClientPager v-model="tablePage" :total="filteredTables.length"/></div>
                        </section>
                        <div v-if="!search && order.length > 1 && chapter !== 'cover'" class="flex items-center justify-between gap-3 border-t border-gray-200 pt-4">
                            <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-40" :disabled="position <= 0" @click="openChapter(order[position - 1])">&laquo; {{ position > 0 ? label(order[position - 1]) : '' }}</button>
                            <span class="text-xs text-gray-500">{{ position + 1 }} of {{ order.length }}</span>
                            <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-maiic-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-maiic-700 disabled:opacity-40" :disabled="position >= order.length - 1" @click="openChapter(order[position + 1])">{{ position < order.length - 1 ? label(order[position + 1]) : 'End' }} &raquo;</button>
                        </div>
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
.docs-prose code::before, .docs-prose code::after { content: none; } /* the typography plugin adds literal backticks */
.docs-prose pre { background: #f8fafc; border-left: 3px solid #f59e0b; color: #1f2937; }
.docs-prose pre code { background: transparent; color: inherit; padding: 0; }
.docs-prose a { color: #15803d; }
</style>
