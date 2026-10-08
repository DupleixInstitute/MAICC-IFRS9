<template>
    <!-- Shared sidebar body: brand header + menu. The parent supplies the
         gradient container; this fills it. In the rail (collapsed) each
         group shows its icon tile and, under it, the icons of its leaves
         with the label as a tooltip (spec v4 section 11.4). -->
    <div class="relative flex h-full min-h-0 flex-col">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-40 opacity-70" style="background: radial-gradient(ellipse at 50% 0%, rgba(34,197,94,0.30) 0%, transparent 72%)"/>
        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-40 opacity-40" style="background: radial-gradient(ellipse at 50% 100%, rgba(212,160,23,0.20) 0%, transparent 72%)"/>

        <div class="relative z-10 flex h-[68px] shrink-0 items-center gap-3" :class="collapsed ? 'justify-center px-2' : 'px-4'" style="border-bottom: 1px solid rgba(255,255,255,0.055);">
            <inertia-link :href="'/'" class="flex min-w-0 items-center gap-3">
                <img :src="collapsed ? ($page.props.smallLogoUrl || $page.props.logoUrl) : $page.props.logoUrl" :alt="$page.props.companyName" class="h-9 w-auto object-contain" :class="collapsed ? 'max-w-[44px]' : 'max-w-[150px]'"/>
                <span v-if="!collapsed" class="flex min-w-0 flex-col">
                    <span class="truncate text-[11px] font-medium leading-none" style="color: rgba(212,160,23,0.85)">IFRS 9 ECL &amp; EIR Platform</span>
                </span>
            </inertia-link>
        </div>

        <nav class="sidebar-scroll relative z-10 min-h-0 flex-1 overflow-y-auto py-3">
            <div v-for="item in $page.props.menu" :key="item.name">
                <DropdownMenu v-if="item.dropdown" :item="item" :collapsed="collapsed" :open-name="openGroup" @open="openGroup = $event"/>

                <a v-else-if="item.download && item.route" :href="route(item.route)" rel="noopener" :title="item.name"
                   class="group relative mx-2 my-[2px] flex items-center gap-3 rounded-xl py-2.5 text-[14px] font-medium text-maiic-100/60 transition-all duration-150 hover:bg-white/[0.05] hover:text-white"
                   :class="collapsed ? 'justify-center px-0' : 'px-3'">
                    <font-awesome-icon v-if="item.icon" :icon="item.icon" aria-hidden="true" class="h-4 w-4 flex-shrink-0 text-maiic-300/70 group-hover:text-maiic-200"/>
                    <span v-if="!collapsed" class="truncate leading-snug">{{ item.name }}</span>
                </a>

                <Link v-else-if="item.route" :href="route(item.route)" :title="item.name"
                      class="group relative mx-2 my-[2px] flex items-center gap-3 rounded-xl py-2.5 text-[14px] font-medium transition-all duration-150"
                      :class="[collapsed ? 'justify-center px-0' : 'px-3', isCurrent(item) ? 'bg-maiic-500/20 text-white shadow-sm' : 'text-maiic-100/60 hover:bg-white/[0.05] hover:text-white']">
                    <span v-if="isCurrent(item)" class="absolute left-0 top-1/2 h-5 w-[3px] -translate-y-1/2 rounded-r-full bg-maiicgold-400"/>
                    <font-awesome-icon v-if="item.icon" :icon="item.icon" aria-hidden="true" class="h-4 w-4 flex-shrink-0 transition-colors duration-150"
                                       :class="isCurrent(item) ? 'text-maiicgold-400' : 'text-maiic-300/70 group-hover:text-maiic-200'"/>
                    <span v-if="!collapsed" class="truncate leading-snug">{{ item.name }}</span>
                </Link>
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
    props: { collapsed: { type: Boolean, default: false } },
    data() {
        return { openGroup: null }
    },
    methods: {
        isCurrent(item) {
            try {
                return route().current(item.route) || (item.route_check && route().current(item.route_check))
            } catch (e) {
                return false
            }
        },
    },
}
</script>

<style scoped>
.sidebar-scroll { scrollbar-width: thin; scrollbar-color: rgba(148, 163, 184, 0.15) transparent; }
.sidebar-scroll::-webkit-scrollbar { width: 6px; }
.sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.15); border-radius: 3px; }
</style>
