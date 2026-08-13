<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    company: { type: String, default: 'MAIIC' },
    categories: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
})

const search = ref('')
const zoomed = ref(null)

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

function jump(slug) {
    const el = document.getElementById('article-' + slug)
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
}
</script>

<template>
    <AppLayout title="User Manual">
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">User Manual</h2>
                <div class="flex items-center gap-2">
                    <Link v-if="canManage" :href="route('help.manage.index')"
                          class="inline-flex items-center gap-1.5 rounded-lg border border-maiic-300 bg-maiic-50 px-3 py-1.5 text-sm font-medium text-maiic-700 hover:bg-maiic-100">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                        Edit manual
                    </Link>
                    <a :href="route('help.pdf')"
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

                    <!-- TOC rail -->
                    <aside class="hidden w-64 flex-none lg:block">
                        <div class="sticky top-6 maiic-panel p-4">
                            <input v-model="search" type="text" placeholder="Search the manual..."
                                   class="maiic-input mb-4"/>
                            <nav class="space-y-4 max-h-[70vh] overflow-y-auto pr-1">
                                <div v-for="c in filtered" :key="c.id">
                                    <p class="text-[11px] font-extrabold uppercase tracking-wider text-maiic-800">{{ c.title }}</p>
                                    <ul class="mt-1.5 space-y-1">
                                        <li v-for="a in c.articles" :key="a.id">
                                            <button @click="jump(a.slug)"
                                                    class="w-full rounded px-2 py-1 text-left text-sm text-gray-600 hover:bg-maiic-50 hover:text-maiic-800">
                                                {{ a.title }}
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </nav>
                        </div>
                    </aside>

                    <!-- Content -->
                    <article class="min-w-0 flex-1 space-y-10">
                        <div v-if="!categories.length" class="maiic-panel p-10 text-center text-gray-400 font-semibold">
                            The manual has no published content yet. Use "Edit manual" to add chapters and articles.
                        </div>

                        <section v-for="c in filtered" :key="c.id">
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
