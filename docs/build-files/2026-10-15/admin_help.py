import io
p = r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/Help/Index.vue'
s = io.open(p, encoding='utf-8').read().replace('\r\n', '\n')

# --- script: one chapter at a time ---
s = s.replace("""const search = ref('')
const zoomed = ref(null)
""", """const search = ref('')
const zoomed = ref(null)
// One chapter on screen at a time (the cover first, when there is one),
// so the manual reads like a book instead of one very long page.
const chapter = ref(props.front ? 'cover' : (props.categories[0]?.id ?? null))
const chapterIndex = computed(() => props.categories.findIndex(c => c.id === chapter.value))
const current = computed(() => props.categories[chapterIndex.value] || null)
const articleCount = computed(() => props.categories.reduce((n, c) => n + c.articles.length, 0))
""")
s = s.replace("""function jump(slug) {
    const el = document.getElementById('article-' + slug)
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}""", """function openChapter(id) {
    chapter.value = id
    window.scrollTo({ top: 0, behavior: 'smooth' })
}

function jump(c, slug) {
    search.value = ''
    chapter.value = c.id
    setTimeout(() => {
        const el = document.getElementById('article-' + slug)
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }, 50)
}

function step(delta) {
    const i = chapterIndex.value + delta
    if (i < 0) openChapter(props.front ? 'cover' : props.categories[0]?.id)
    else if (i < props.categories.length) openChapter(props.categories[i].id)
}""")

# --- header -> description + actions top right ---
a = s.index('        <template #header>')
b = s.index('        <div class="py-8">')
s = s[:a] + '''        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ title }}</h2>
                <p class="mt-0.5 text-sm text-gray-500">{{ subtitle }}<span v-if="categories.length">. {{ categories.length }} chapters, {{ articleCount }} topics</span></p>
            </div>
        </template>
        <template #actions>
            <Link v-if="canManage" :href="route('help.manage.index', { manual })"
                  class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50">
                <font-awesome-icon icon="pen"/> Edit manual
            </Link>
            <a :href="pdfRoute || route('help.pdf')"
               class="inline-flex items-center gap-2 rounded-lg bg-maiic-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-maiic-700">
                <font-awesome-icon icon="file-pdf"/> Download PDF
            </a>
        </template>

''' + s[b:]

s = s.replace('        <div class="py-8">\n            <div class="w-full px-4">\n                <div class="flex gap-8">', '        <div>\n            <div class="w-full">\n                <div class="flex gap-6">')

# --- TOC rail: chapters, with the open chapter's topics ---
a = s.index('                            <nav class="min-h-0 flex-1 space-y-4 overflow-y-auto pr-1">')
b = s.index('                            </nav>', a) + len('                            </nav>')
s = s[:a] + '''                            <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto pr-1" aria-label="Chapters">
                                <button v-if="front && !search" type="button" @click="openChapter('cover')"
                                        class="w-full rounded-lg border-l-4 px-3 py-2 text-left text-sm transition"
                                        :class="chapter === 'cover' ? 'border-maiic-600 bg-maiic-50 font-bold text-maiic-800' : 'border-transparent text-gray-600 hover:bg-gray-50'">
                                    Cover and contents
                                </button>
                                <div v-for="(c, ci) in filtered" :key="c.id">
                                    <button type="button" @click="search ? jump(c, c.articles[0]?.slug) : openChapter(c.id)"
                                            class="flex w-full items-center justify-between gap-2 rounded-lg border-l-4 px-3 py-2 text-left text-sm transition"
                                            :class="!search && chapter === c.id ? 'border-maiic-600 bg-maiic-50 font-bold text-maiic-800' : 'border-transparent text-gray-700 hover:bg-gray-50'">
                                        <span>{{ c.title }}</span>
                                        <span class="flex-none rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-500">{{ c.articles.length }}</span>
                                    </button>
                                    <ul v-if="search || chapter === c.id" class="mb-2 ml-4 mt-1 space-y-0.5 border-l border-gray-200 pl-2">
                                        <li v-for="a in c.articles" :key="a.id">
                                            <button type="button" @click="jump(c, a.slug)"
                                                    class="w-full rounded px-2 py-1 text-left text-xs text-gray-600 hover:bg-maiic-50 hover:text-maiic-800">
                                                {{ a.title }}
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                                <p v-if="search && !filtered.length" class="px-3 py-2 text-xs text-gray-500">Nothing in this manual matches "{{ search }}".</p>
                            </nav>''' + s[b:]

# --- content: cover or the one open chapter ---
a = s.index('                        <DocumentCover v-if="front" :front="front"/>')
b = s.index('                        <section v-for="c in filtered" :key="c.id">')
s = s[:a] + '''                        <!-- small screens: chapter picker in place of the rail -->
                        <select v-if="categories.length" class="maiic-select lg:hidden" :value="chapter" aria-label="Chapter" @change="openChapter($event.target.value === 'cover' ? 'cover' : Number($event.target.value))">
                            <option v-if="front" value="cover">Cover and contents</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.title }}</option>
                        </select>
                        <template v-if="chapter === 'cover' && front">
                            <DocumentCover :front="front"/>
                            <div v-if="categories.length" class="maiic-panel p-6">
                                <h3 class="maiic-section-title mt-0">Contents</h3>
                                <ol class="grid grid-cols-1 gap-x-8 gap-y-1 sm:grid-cols-2">
                                    <li v-for="(c, ci) in categories" :key="c.id">
                                        <button type="button" class="flex w-full justify-between gap-3 rounded px-2 py-1.5 text-left text-sm hover:bg-maiic-50" @click="openChapter(c.id)">
                                            <span><span class="mr-2 font-bold text-maiic-700">{{ ci + 1 }}</span>{{ c.title }}</span>
                                            <span class="text-xs text-gray-400">{{ c.articles.length }} topics</span>
                                        </button>
                                    </li>
                                </ol>
                            </div>
                        </template>
                        <div v-if="!categories.length" class="maiic-panel p-10 text-center">
                            <div class="text-base font-bold text-gray-800">This manual has no published content yet</div>
                            <p class="mt-1 text-sm text-gray-500">An administrator adds chapters and topics with Edit manual.</p>
                        </div>

''' + s[b:]
s = s.replace('                        <section v-for="c in filtered" :key="c.id">',
              '                        <section v-for="c in (search ? filtered : (current ? [current] : []))" :key="c.id">')
# prev / next after the section loop
a = s.index('                    </article>')
s = s[:a] + '''                        <div v-if="!search && categories.length && chapter !== 'cover'" class="flex items-center justify-between gap-3 border-t border-gray-200 pt-4">
                            <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-40" :disabled="chapterIndex <= 0 && !front" @click="step(-1)">&laquo; {{ chapterIndex > 0 ? categories[chapterIndex - 1].title : 'Cover' }}</button>
                            <span class="text-xs text-gray-500">Chapter {{ chapterIndex + 1 }} of {{ categories.length }}</span>
                            <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-maiic-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-maiic-700 disabled:opacity-40" :disabled="chapterIndex >= categories.length - 1" @click="step(1)">{{ chapterIndex < categories.length - 1 ? categories[chapterIndex + 1].title : 'End' }} &raquo;</button>
                        </div>
''' + s[a:]
s = s.replace('<article class="min-w-0 flex-1 space-y-10">', '<article class="min-w-0 flex-1 space-y-6">')
io.open(p, 'w', encoding='utf-8').write(s)
print('ok')
