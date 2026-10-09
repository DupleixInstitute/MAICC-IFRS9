<template>
    <!-- Shared sidebar body: brand header + menu. The parent supplies the
         gradient <aside>/panel container; this fills it. -->
    <div class="relative flex h-full min-h-0 flex-col">
        <!-- ambient glows (top green, bottom gold) -->
        <div class="pointer-events-none absolute inset-x-0 top-0 h-40 opacity-70"
             style="background: radial-gradient(ellipse at 50% 0%, rgba(34,197,94,0.30) 0%, transparent 72%)"/>
        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-40 opacity-40"
             style="background: radial-gradient(ellipse at 50% 100%, rgba(212,160,23,0.20) 0%, transparent 72%)"/>

        <!-- brand header -->
        <div class="relative z-10 flex h-[68px] shrink-0 items-center gap-3 px-4"
             style="border-bottom: 1px solid rgba(255,255,255,0.055);">
            <inertia-link :href="'/'" class="flex min-w-0 items-center gap-3">
                <img :src="$page.props.logoUrl" :alt="$page.props.companyName" class="h-10 w-auto max-w-[150px] object-contain"/>
                <span class="flex min-w-0 flex-col">
                    <span class="truncate text-[11px] font-medium leading-none" style="color: rgba(212,160,23,0.85)">
                        IFRS 9 ECL &amp; EIR Platform
                    </span>
                </span>
            </inertia-link>
        </div>

        <!-- menu -->
        <nav class="sidebar-scroll relative z-10 min-h-0 flex-1 overflow-y-auto py-3">
            <div v-for="item in $page.props.menu" :key="item.name">
                <DropdownMenu v-if="item.dropdown" :item="item"/>

                <a v-else-if="item.download && item.route" :href="route(item.route)" rel="noopener"
                   class="group relative mx-2 my-[2px] flex items-center gap-3 rounded-xl px-3 py-2.5 text-[14px] font-medium text-maiic-100/60 transition-all duration-150 hover:bg-white/[0.05] hover:text-white">
                    <font-awesome-icon v-if="item.icon" :icon="item.icon" aria-hidden="true"
                                       class="h-4 w-4 flex-shrink-0 text-maiic-300/70 group-hover:text-maiic-200"/>
                    <span class="truncate leading-snug">{{ item.name }}</span>
                </a>

                <Link v-else-if="item.route" :href="route(item.route)"
                      class="group relative mx-2 my-[2px] flex items-center gap-3 rounded-xl px-3 py-2.5 text-[14px] font-medium transition-all duration-150"
                      :class="isCurrent(item) ? 'bg-maiic-500/20 text-white shadow-sm' : 'text-maiic-100/60 hover:bg-white/[0.05] hover:text-white'">
                    <span v-if="isCurrent(item)"
                          class="absolute left-0 top-1/2 h-5 w-[3px] -translate-y-1/2 rounded-r-full bg-maiicgold-400"/>
                    <font-awesome-icon v-if="item.icon" :icon="item.icon" aria-hidden="true"
                                       class="h-4 w-4 flex-shrink-0 transition-colors duration-150"
                                       :class="isCurrent(item) ? 'text-maiicgold-400' : 'text-maiic-300/70 group-hover:text-maiic-200'"/>
                    <span class="truncate leading-snug">{{ item.name }}</span>
                </Link>

                <div v-else
                     class="mx-2 my-[2px] flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-2.5 text-[14px] font-medium text-maiic-100/30">
                    <font-awesome-icon v-if="item.icon" :icon="item.icon" aria-hidden="true" class="h-4 w-4 flex-shrink-0"/>
                    <span class="truncate">{{ item.name }}</span>
                </div>
            </div>
        </nav>
    </div>
</template>

<script>
import { Link } from '@inertiajs/vue3'
import DropdownMenu from '@/Jetstream/DropdownMenu.vue'

export default {
    name: 'SidebarNav',
    components: { Link, DropdownMenu },
    methods: {
        isCurrent(item) {
            try {
                return route().current(item.route) ||
                    (item.route_check && route().current(item.route_check))
            } catch (e) {
                return false
            }
        },
    },
}
</script>

<style scoped>
.sidebar-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgba(148, 163, 184, 0.15) transparent;
}
.sidebar-scroll::-webkit-scrollbar {
    width: 6px;
}
.sidebar-scroll::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.15);
    border-radius: 3px;
}
</style>
