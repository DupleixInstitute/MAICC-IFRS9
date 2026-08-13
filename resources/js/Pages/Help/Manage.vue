<script setup>
import { ref, reactive } from 'vue'
import { router, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import RichTextEditor from '@/Components/RichTextEditor.vue'

const props = defineProps({
    categories: { type: Array, default: () => [] },
    routeNames: { type: Array, default: () => [] },
})

const editing = ref(null) // article being edited (null = none)
const newChapter = ref('')

const blank = () => ({
    id: null,
    help_category_id: props.categories[0]?.id || null,
    title: '',
    body: '',
    order: 0,
    status: 'published',
    steps: [],
    routes: [],
})
const form = reactive(blank())

function startNew(categoryId) {
    Object.assign(form, blank(), { help_category_id: categoryId })
    editing.value = 'new'
}

function startEdit(article) {
    Object.assign(form, {
        id: article.id,
        help_category_id: article.help_category_id,
        title: article.title,
        body: article.body || '',
        order: article.order,
        status: article.status,
        steps: article.steps.map(s => s.text),
        routes: article.routes.map(r => r.route_name),
    })
    editing.value = article
}

function save() {
    const payload = { ...form }
    const opts = { preserveScroll: true, onSuccess: () => { editing.value = null } }
    if (form.id) {
        router.put(route('help.manage.articles.update', form.id), payload, opts)
    } else {
        router.post(route('help.manage.articles.store'), payload, opts)
    }
}

function removeArticle(article) {
    if (!confirm(`Delete article "${article.title}"?`)) return
    router.delete(route('help.manage.articles.destroy', article.id), { preserveScroll: true })
}

function addChapter() {
    if (!newChapter.value.trim()) return
    router.post(route('help.manage.categories.store'), { title: newChapter.value }, {
        preserveScroll: true, onSuccess: () => { newChapter.value = '' },
    })
}

function removeChapter(category) {
    if (!confirm(`Delete chapter "${category.title}" AND all its articles?`)) return
    router.delete(route('help.manage.categories.destroy', category.id), { preserveScroll: true })
}

// figure upload for the article currently being edited
const imageFile = ref(null)
const imageCaption = ref('')
function uploadImage() {
    if (!form.id || !imageFile.value) return
    router.post(route('help.manage.images.store', form.id),
        { image: imageFile.value, caption: imageCaption.value },
        { forceFormData: true, preserveScroll: true, onSuccess: () => { imageFile.value = null; imageCaption.value = '' } })
}

function removeImage(image) {
    router.delete(route('help.manage.images.destroy', [form.id, image.id]), { preserveScroll: true })
}

// live child rows of the article being edited (for the figures list)
function currentImages() {
    for (const c of props.categories) {
        const a = c.articles.find(x => x.id === form.id)
        if (a) return a.images
    }
    return []
}
</script>

<template>
    <AppLayout title="Manage Manual">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Manage User Manual</h2>
                <Link :href="route('help.index')" class="text-sm font-medium text-maiic-600 hover:underline">
                    View manual &rarr;
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-6 lg:flex-row">

                    <!-- Chapters + articles tree -->
                    <aside class="w-full flex-none lg:w-80">
                        <div class="maiic-panel p-4">
                            <div class="mb-4 flex gap-2">
                                <input v-model="newChapter" type="text" placeholder="New chapter title..."
                                       class="maiic-input" @keyup.enter="addChapter"/>
                                <button @click="addChapter" class="btn btn-green flex-none text-sm">Add</button>
                            </div>
                            <div v-for="c in categories" :key="c.id" class="mb-4">
                                <div class="flex items-center justify-between">
                                    <p class="text-[11px] font-extrabold uppercase tracking-wider text-maiic-800">{{ c.title }}</p>
                                    <div class="flex items-center gap-1">
                                        <button @click="startNew(c.id)" class="maiic-action maiic-action-view" title="New article in this chapter">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
                                        </button>
                                        <button @click="removeChapter(c)" class="maiic-action maiic-action-delete" title="Delete chapter">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
                                        </button>
                                    </div>
                                </div>
                                <ul class="mt-1.5 space-y-1">
                                    <li v-for="a in c.articles" :key="a.id"
                                        class="flex items-center justify-between gap-2 rounded px-2 py-1 hover:bg-maiic-50">
                                        <button @click="startEdit(a)" class="min-w-0 flex-1 truncate text-left text-sm text-gray-700 hover:text-maiic-800">
                                            {{ a.title }}
                                        </button>
                                        <span v-if="a.status === 'draft'" class="maiic-badge maiic-badge-gold flex-none">Draft</span>
                                        <button @click="removeArticle(a)" class="maiic-action maiic-action-delete !h-6 !w-6 flex-none" title="Delete article">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </aside>

                    <!-- Editor -->
                    <div class="min-w-0 flex-1">
                        <div v-if="!editing" class="maiic-panel p-10 text-center font-semibold text-gray-400">
                            Pick an article on the left to edit it, or use + on a chapter to write a new one.
                        </div>

                        <div v-else class="maiic-panel space-y-5 p-6">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                                <div class="md:col-span-2">
                                    <label class="maiic-flabel">Title</label>
                                    <input v-model="form.title" type="text" class="maiic-input"/>
                                </div>
                                <div>
                                    <label class="maiic-flabel">Chapter</label>
                                    <select v-model="form.help_category_id" class="maiic-select">
                                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.title }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="maiic-flabel">Status</label>
                                    <select v-model="form.status" class="maiic-select">
                                        <option value="published">Published</option>
                                        <option value="draft">Draft</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="maiic-flabel">Body</label>
                                <RichTextEditor v-model="form.body"/>
                            </div>

                            <div>
                                <div class="mb-1 flex items-center justify-between">
                                    <label class="maiic-flabel !mb-0">Numbered steps</label>
                                    <button @click="form.steps.push('')" class="text-sm font-medium text-maiic-600 hover:underline">+ Add step</button>
                                </div>
                                <div v-for="(s, i) in form.steps" :key="i" class="mb-2 flex items-center gap-2">
                                    <span class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-maiic-600 text-xs font-bold text-white">{{ i + 1 }}</span>
                                    <input v-model="form.steps[i]" type="text" class="maiic-input"/>
                                    <button @click="form.steps.splice(i, 1)" class="maiic-action maiic-action-delete flex-none" title="Remove step">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <div class="mb-1 flex items-center justify-between">
                                    <label class="maiic-flabel !mb-0">Shown on pages (route names)</label>
                                    <button @click="form.routes.push('')" class="text-sm font-medium text-maiic-600 hover:underline">+ Map a page</button>
                                </div>
                                <div v-for="(r, i) in form.routes" :key="i" class="mb-2 flex items-center gap-2">
                                    <select v-model="form.routes[i]" class="maiic-select">
                                        <option value="">Choose a page...</option>
                                        <option v-for="n in routeNames" :key="n" :value="n">{{ n }}</option>
                                    </select>
                                    <button @click="form.routes.splice(i, 1)" class="maiic-action maiic-action-delete flex-none" title="Remove mapping">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Figures (existing article only) -->
                            <div v-if="form.id">
                                <label class="maiic-flabel">Figures</label>
                                <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                                    <div v-for="img in currentImages()" :key="img.id" class="relative">
                                        <img :src="'/' + img.path.replace(/^\//, '')" class="h-24 w-full rounded-lg border border-gray-200 object-cover"/>
                                        <button @click="removeImage(img)"
                                                class="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-red-600 text-white shadow hover:bg-red-700"
                                                title="Remove figure">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                                        </button>
                                        <p class="mt-1 truncate text-[11px] text-gray-500">{{ img.caption }}</p>
                                    </div>
                                </div>
                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <input type="file" accept="image/*" class="text-sm"
                                           @change="e => imageFile = e.target.files[0]"/>
                                    <input v-model="imageCaption" type="text" placeholder="Caption..." class="maiic-input !w-64"/>
                                    <button @click="uploadImage" :disabled="!imageFile"
                                            class="btn btn-green text-sm disabled:opacity-50">Upload figure</button>
                                </div>
                                <p class="mt-1 text-xs text-gray-400">
                                    Screenshots refreshed by <code>php artisan manual:screenshots</code> can also be attached by editing the seeded articles.
                                </p>
                            </div>
                            <p v-else class="text-xs text-gray-400">Save the article first, then figures can be attached.</p>

                            <div class="flex items-center gap-3 border-t border-gray-100 pt-4">
                                <button @click="save" class="btn btn-green">{{ form.id ? 'Save changes' : 'Create article' }}</button>
                                <button @click="editing = null" class="btn bg-gray-200 !text-gray-700 hover:bg-gray-300">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
