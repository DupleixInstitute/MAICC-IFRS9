import io
p = r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/Docs/Show.vue'
s = io.open(p, encoding='utf-8').read().replace('\r\n', '\n')

s = s.replace("import DocumentCover from '@/Shared/DocumentCover.vue'\n", "import DocumentCover from '@/Shared/DocumentCover.vue'\nimport ClientPager from '@/Components/ClientPager.vue'\n")
s = s.replace("""const search = ref('')
const tableFilter = ref('')
const openTable = ref(null)
""", """const search = ref('')
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
""")
s = s.replace("""function jump(id) {
    const el = document.getElementById(id)
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}""", """function jump(chapterSlug, id) {
    search.value = ''
    chapter.value = chapterSlug
    setTimeout(() => {
        const el = document.getElementById(id)
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }, 50)
}
const pagedTables = computed(() => filteredTables.value.slice((tablePage.value - 1) * 15, tablePage.value * 15))""")

# header
a = s.index('        <template #header>')
b = s.index('        <div class="py-8">')
s = s[:a] + '''        <template #header>
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

''' + s[b:]
s = s.replace('        <div class="py-8">\n            <div class="w-full px-4">\n                <div class="flex gap-8">', '        <div>\n            <div class="w-full">\n                <div class="flex gap-6">')

# rail
a = s.index('                            <nav class="min-h-0 flex-1 space-y-3 overflow-y-auto pr-1">')
b = s.index('                            </nav>', a) + len('                            </nav>')
s = s[:a] + '''                            <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto pr-1" aria-label="Chapters">
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
                            </nav>''' + s[b:]

# body
a = s.index('                        <DocumentCover v-if="front" :front="front"/>')
b = s.index('                        <!-- Live schema appendix (Technical Manual only) -->')
s = s[:a] + '''                        <select v-if="order.length > 1" class="maiic-select lg:hidden" :value="chapter" aria-label="Chapter" @change="openChapter($event.target.value)">
                            <option v-for="k in order" :key="k" :value="k">{{ label(k) }}</option>
                        </select>
                        <template v-if="chapter === 'cover' && front && !search">
                            <DocumentCover :front="front"/>
                            <div v-if="chapters.length" class="maiic-panel p-6">
                                <h3 class="maiic-section-title mt-0">Contents</h3>
                                <ol class="grid grid-cols-1 gap-x-8 gap-y-1 sm:grid-cols-2">
                                    <li v-for="(k, i) in order.filter(k => k !== 'cover')" :key="k">
                                        <button type="button" class="w-full rounded px-2 py-1.5 text-left text-sm hover:bg-maiic-50" @click="openChapter(k)"><span class="mr-2 font-bold text-maiic-700">{{ i + 1 }}</span>{{ label(k) }}</button>
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

''' + s[b:]
s = s.replace('<section v-if="schema.length" id="appendix-schema" class="maiic-panel scroll-mt-24 p-6">', '<section v-if="schema.length && chapter === \'schema\' && !search" id="appendix-schema" class="maiic-panel scroll-mt-24 p-6">')
s = s.replace('<h2 class="mb-1 text-xl font-bold text-gray-900">Appendix A. Live database schema</h2>', '<h2 class="mb-1 text-xl font-bold text-gray-900">Appendix A. Database tables</h2>')
s = s.replace('{{ schema.length }} tables read from the connected database at render time. Click a table to see its columns.', '{{ schema.length }} tables, read from the connected database when this page opened. Click a table to see its columns.')
s = s.replace('<input v-model="tableFilter" type="text" placeholder="Filter tables or columns..." class="maiic-input mb-4 max-w-md"/>', '<input v-model="tableFilter" type="text" placeholder="Filter tables or columns" class="maiic-input mb-4 max-w-md" @input="tablePage = 1"/>')
s = s.replace('<template v-for="t in filteredTables" :key="t.name">', '<template v-for="t in pagedTables" :key="t.name">')
s = s.replace("<td class=\"num\">{{ t.row_count.toLocaleString() }}</td>", "<td class=\"num\">{{ t.row_count.toLocaleString('en-GB') }}</td>")
# pager after the schema table wrap; and prev/next before </article>
a = s.index('                        </section>\n                    </article>')
s = s[:a] + '''                            <div v-if="filteredTables.length > 15" class="mt-4"><ClientPager v-model="tablePage" :total="filteredTables.length"/></div>
''' + s[a:]
a = s.index('                    </article>')
s = s[:a] + '''                        <div v-if="!search && order.length > 1 && chapter !== 'cover'" class="flex items-center justify-between gap-3 border-t border-gray-200 pt-4">
                            <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-40" :disabled="position <= 0" @click="openChapter(order[position - 1])">&laquo; {{ position > 0 ? label(order[position - 1]) : '' }}</button>
                            <span class="text-xs text-gray-500">{{ position + 1 }} of {{ order.length }}</span>
                            <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-maiic-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-maiic-700 disabled:opacity-40" :disabled="position >= order.length - 1" @click="openChapter(order[position + 1])">{{ position < order.length - 1 ? label(order[position + 1]) : 'End' }} &raquo;</button>
                        </div>
''' + s[a:]
s = s.replace('<article class="min-w-0 flex-1 space-y-8">', '<article class="min-w-0 flex-1 space-y-6">')
io.open(p, 'w', encoding='utf-8').write(s)
print('ok')
