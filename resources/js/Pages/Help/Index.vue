<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DocumentCover from '@/Shared/DocumentCover.vue'

const props = defineProps({
    company: { type: String, default: 'MAIIC' },
    front: { type: Object, default: null },
    manual: { type: String, default: 'user' },
    title: { type: String, default: 'User Manual' },
    subtitle: { type: String, default: '' },
    pdfRoute: { type: String, default: '' },
    categories: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
})

const search = ref('')
const zoomed = ref(null)
// One chapter on screen at a time (the cover first, when there is one),
// so the manual reads like a book instead of one very long page.
const chapter = ref(props.front ? 'cover' : (props.categories[0]?.id ?? null))
const chapterIndex = computed(() => props.categories.findIndex(c => c.id === chapter.value))
const current = computed(() => props.categories[chapterIndex.value] || null)
const articleCount = computed(() => props.categories.reduce((n, c) => n + c.articles.length, 0))

// Client-side filter: a category stays visible while any of its articles
// matches the search in title, body or steps.
const filtered = computed(() => {
    const q = search.value.trim().toLowerCase()
    if (!q) return props.categories
    return props.categories
        .map(c => ({
            ...c,
            articles: c.articles.filter(a =>
                (a.title + ' ' + (a.body || '') + ' ' + a.steps.join(' ')).toLowerCase().includes(q)),
        }))
        .filter(c => c.articles.length)
})

function openChapter(id) {
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
}
</script>

<template>
    <AppLayout :title="title">
        <template #header>
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

        <div>
            <div class="w-full">
                <div class="flex gap-6">

                    <!-- TOC rail -->
                    <aside class="hidden w-64 flex-none lg:block">
                        <div class="sticky top-[4.5rem] flex h-[calc(100vh-5rem)] flex-col maiic-panel p-4">
                            <input v-model="search" type="text" placeholder="Search this manual..."
                                   class="maiic-input mb-4"/>
                            <nav class="min-h-0 flex-1 space-y-1 overflow-y-auto pr-1" aria-label="Chapters">
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
                            </nav>
                        </div>
                    </aside>

                    <!-- Content -->
                    <article class="min-w-0 flex-1 space-y-6">
                        <!-- small screens: chapter picker in place of the rail -->
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

                        <section v-for="c in (search ? filtered : (current ? [current] : []))" :key="c.id">
                            <h2 class="mb-5 border-l-4 border-maiic-600 pl-3 text-2xl font-bold text-gray-900">{{ c.title }}</h2>
                            <div class="space-y-8">
                                <div v-for="a in c.articles" :key="a.id" :id="'article-' + a.slug"
                                     class="maiic-panel scroll-mt-24 p-6">
                                    <div class="flex items-start justify-between gap-3">
                                        <h3 class="text-lg font-bold text-gray-900">{{ a.title }}</h3>
                                        <span v-if="a.updated_at" class="whitespace-nowrap text-xs text-gray-400">Updated {{ a.updated_at }}</span>
                                    </div>
                                    <div v-if="a.body" class="prose prose-sm mt-3 max-w-none text-gray-700" v-html="a.body"></div>

                                    <ol v-if="a.steps.length" class="mt-4 space-y-2">
                                        <li v-for="(s, i) in a.steps" :key="i" class="flex gap-3">
                                            <span class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-maiic-600 text-xs font-bold text-white">{{ i + 1 }}</span>
                                            <span class="text-sm text-gray-700">{{ s }}</span>
                                        </li>
                                    </ol>

                                    <figure v-for="f in a.images" :key="f.src" class="mt-5">
                                        <img :src="f.src" :alt="f.caption"
                                             class="w-full cursor-zoom-in rounded-xl border border-gray-200 shadow-sm"
                                             loading="lazy" @click="zoomed = f"/>
                                        <figcaption class="mt-1.5 text-xs italic text-gray-500">{{ f.caption }}</figcaption>
                                    </figure>
                                </div>
                            </div>
                        </section>
                        <div v-if="!search && categories.length && chapter !== 'cover'" class="flex items-center justify-between gap-3 border-t border-gray-200 pt-4">
                            <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-40" :disabled="chapterIndex <= 0 && !front" @click="step(-1)">&laquo; {{ chapterIndex > 0 ? categories[chapterIndex - 1].title : 'Cover' }}</button>
                            <span class="text-xs text-gray-500">Chapter {{ chapterIndex + 1 }} of {{ categories.length }}</span>
                            <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-maiic-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-maiic-700 disabled:opacity-40" :disabled="chapterIndex >= categories.length - 1" @click="step(1)">{{ chapterIndex < categories.length - 1 ? categories[chapterIndex + 1].title : 'End' }} &raquo;</button>
                        </div>
                    </article>
                </div>
            </div>
        </div>

        <!-- lightbox -->
        <div v-if="zoomed" class="fixed inset-0 z-50 flex cursor-zoom-out items-center justify-center bg-black/80 p-6"
             @click="zoomed = null">
            <figure class="max-h-full max-w-6xl">
                <img :src="zoomed.src" :alt="zoomed.caption" class="mx-auto max-h-[85vh] w-auto rounded-lg shadow-2xl"/>
                <figcaption class="mt-2 text-center text-sm text-white/90">{{ zoomed.caption }}</figcaption>
            </figure>
        </div>
    </AppLayout>
</template>
