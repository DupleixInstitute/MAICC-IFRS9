<template>
  <!-- The help button on a screen: opens the User Manual at the article written for this screen
       (help_article_routes), or the manual's contents when no article names the screen.
       inline: a small round button in the flow (beside a page title) instead of the
       floating one, so it never sits over a chart or a table. -->
  <button type="button" @click="open"
          :class="inline
            ? 'inline-flex h-7 w-7 flex-none items-center justify-center rounded-full bg-maiic-600 text-xs text-white shadow-sm transition hover:bg-maiic-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-maiic-400'
            : 'fixed bottom-6 right-6 z-40 flex h-11 w-11 items-center justify-center rounded-full bg-maiic-600 text-white shadow-lg transition hover:bg-maiic-700'"
          title="Help for this screen" aria-label="Help for this screen">
    <font-awesome-icon icon="question" />
  </button>
</template>

<script setup>
import { usePage, router } from '@inertiajs/vue3'

defineProps({ inline: { type: Boolean, default: false } })

const page = usePage()

async function open() {
  const name = page.props.route_name
  let slug = null
  if (name) {
    try {
      const res = await fetch(route('help.for-route', name), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      if (res.ok) slug = (await res.json()).slug
    } catch (e) { slug = null }
  }
  router.get(route('help.index'), slug ? { article: slug } : {})
}
</script>
