<template>
  <!-- The help button on a screen: opens the User Manual at the article written for this screen
       (help_article_routes), or the manual's contents when no article names the screen. -->
  <button type="button" @click="open"
          class="fixed bottom-6 right-6 z-40 flex h-11 w-11 items-center justify-center rounded-full bg-maiic-600 text-white shadow-lg transition hover:bg-maiic-700"
          title="Help for this screen" aria-label="Help for this screen">
    <font-awesome-icon icon="question" />
  </button>
</template>

<script setup>
import { usePage, router } from '@inertiajs/vue3'

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
