<template>
    <!-- One pager for every list (15 rows a page): numbered pages, the current
         page in MAIIC green, Previous / Next at the ends. Hidden on one page. -->
    <nav v-if="links && links.length > 3" class="mt-4 flex flex-wrap items-center gap-1" aria-label="Pages">
        <template v-for="(link, key) in links" :key="key">
            <span v-if="link.url === null"
                  class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-400"
                  v-html="link.label"/>
            <Link v-else
                  :href="link.url"
                  preserve-scroll
                  class="rounded-lg border px-3 py-1.5 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-maiic-300"
                  :class="link.active
                      ? 'border-maiic-600 bg-maiic-600 text-white'
                      : 'border-gray-200 bg-white text-gray-700 hover:border-maiic-300 hover:bg-maiic-50 hover:text-maiic-800'"
                  v-html="link.label"/>
        </template>
    </nav>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    links: {
        type: Array,
        default: () => [],
    },
});
</script>
