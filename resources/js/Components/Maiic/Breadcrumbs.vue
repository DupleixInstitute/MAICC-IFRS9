<template>
    <!-- The page's place in the menu (App\Support\Breadcrumbs, shared as
         props.breadcrumbs): block > entry > sub-entry > tab, every crumb but
         the last a link. A detail page adds its own crumbs (extra); a page the
         menu does not hold reads Dashboard > its title. On a phone the middle
         crumbs fold into "...". -->
    <nav v-if="crumbs.length" aria-label="Breadcrumb" class="mb-2 min-w-0">
        <ol class="flex min-w-0 flex-nowrap items-center gap-1.5 text-[12.5px] leading-5 text-gray-500">
            <template v-for="(c, i) in crumbs" :key="i + c.label">
                <!-- phone width: the middle crumbs fold into one ellipsis after the first -->
                <li v-if="i === 1 && crumbs.length > 2" class="flex flex-none items-center gap-1.5 sm:hidden" aria-hidden="true">
                    <font-awesome-icon icon="chevron-right" class="h-2.5 w-2.5 flex-none text-gray-400"/>
                    <span>...</span>
                </li>
                <li class="flex min-w-0 items-center gap-1.5"
                    :class="i > 0 && i < last ? 'hidden sm:flex' : (i === last ? 'flex-1' : 'flex-none')">
                    <font-awesome-icon v-if="i > 0" icon="chevron-right" class="h-2.5 w-2.5 flex-none text-gray-400" aria-hidden="true"/>
                    <Link v-if="i < last && c.href" :href="c.href"
                          class="max-w-[14rem] truncate transition-colors duration-150 hover:text-maiic-700 focus:outline-none focus-visible:underline dark:hover:text-maiic-300">{{ c.label }}</Link>
                    <span v-else-if="i < last" class="max-w-[14rem] truncate">{{ c.label }}</span>
                    <span v-else class="min-w-0 truncate font-medium text-maiic-700" aria-current="page">{{ c.label }}</span>
                </li>
            </template>
        </ol>
    </nav>
</template>

<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const props = defineProps({
    // the page title (AppLayout title): the last crumb when the menu trail stops above the page
    title: { type: String, default: '' },
    // a detail page's own crumbs after the menu trail: [{ label, href? }], the last is the page
    extra: { type: Array, default: () => [] },
})

const page = usePage()

const crumbs = computed(() => {
    const shared = page.props.breadcrumbs || { crumbs: [], exact: false }
    const extra = (props.extra || []).filter(c => c && c.label)
    let out = (shared.crumbs || []).map(c => ({ label: c.label, href: c.href || null }))
    if (!out.length) {
        // not on the menu (a detail page, help, the profile): Dashboard > the page
        const home = page.props.route_name === 'dashboard'
        let dashboard = null
        try { dashboard = route('dashboard') } catch (e) { /* no route: plain text */ }
        out = home ? [] : [{ label: 'Dashboard', href: dashboard }]
        if (extra.length) return [...out, ...extra]
        return props.title && !home ? [...out, { label: props.title, href: null }] : []
    }
    if (extra.length) return [...out, ...extra]
    // a page under its menu entry (an edit screen, a report under the hub): the title closes the trail
    const lastLabel = out[out.length - 1].label
    if (!shared.exact && props.title && props.title !== lastLabel) out.push({ label: props.title, href: null })
    return out
})

const last = computed(() => crumbs.value.length - 1)
</script>
