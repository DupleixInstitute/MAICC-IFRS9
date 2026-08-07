<template>
    <div>
        <!-- depth 0: group header (uppercase micro-caps + icon tile) -->
        <a v-if="depth === 0" @click="toggle"
           class="group/hdr relative flex w-full cursor-pointer items-center gap-3 px-4 py-2 text-left transition-all duration-150"
           :class="(active || open) ? 'text-white' : 'text-maiic-100/70 hover:text-white'">
            <span v-if="active || open"
                  class="absolute left-0 top-1/2 h-6 w-[3px] -translate-y-1/2 rounded-r-full bg-maiicgold-400"/>
            <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-xl border transition-all duration-150"
                  :class="(active || open)
                      ? 'border-maiic-400/50 bg-maiic-500/20 opacity-100'
                      : 'border-maiic-400/25 bg-maiic-500/10 opacity-75 group-hover/hdr:opacity-100'">
                <font-awesome-icon v-if="item.icon" :icon="item.icon" aria-hidden="true"
                                   class="h-[15px] w-[15px]"
                                   :class="(active || open) ? 'text-maiic-300' : 'text-maiic-300/80'"/>
            </span>
            <span class="flex-1 text-[10.5px] font-bold uppercase tracking-[0.17em]">{{ item.name }}</span>
            <font-awesome-icon icon="chevron-down"
                               class="h-3 w-3 flex-shrink-0 transition-transform duration-200"
                               :class="[open ? 'rotate-180' : 'rotate-0', (active || open) ? 'text-maiicgold-400' : 'text-maiic-100/40']"/>
        </a>

        <!-- depth >= 1: sub-group header -->
        <a v-else @click="toggle"
           class="group/sub relative mx-2 flex cursor-pointer items-center gap-2.5 rounded-lg px-3 py-2 transition-all duration-150"
           :class="(active || open) ? 'text-maiic-100' : 'text-maiic-100/60 hover:text-maiic-100'"
           :style="{ marginLeft: (depth * 10 + 8) + 'px' }">
            <font-awesome-icon v-if="item.icon && item.icon !== 'circle'" :icon="item.icon" aria-hidden="true"
                               class="h-3.5 w-3.5 flex-shrink-0 text-maiic-300/70"/>
            <span class="flex-1 text-[11px] font-bold uppercase tracking-[0.13em]">{{ item.name }}</span>
            <font-awesome-icon icon="chevron-down"
                               class="h-2.5 w-2.5 flex-shrink-0 transition-transform duration-200"
                               :class="[open ? 'rotate-180' : 'rotate-0', 'text-maiic-100/40']"/>
        </a>

        <div v-show="open" class="mt-0.5 space-y-0.5" :class="depth === 0 ? 'pb-1' : ''">
            <template v-for="child in (item.children || [])" :key="child.name">
                <DropdownMenu v-if="child.dropdown && (child.children || []).length"
                              :item="child" :depth="depth + 1"/>

                <a v-else-if="child.download && child.route"
                   :href="route(child.route)" rel="noopener"
                   class="group/item relative mr-2 flex items-center gap-2.5 rounded-xl px-3 py-2 text-[13.5px] font-medium text-maiic-100/60 transition-all duration-150 hover:bg-white/[0.05] hover:text-white"
                   :style="{ marginLeft: ((depth + 1) * 10 + 8) + 'px' }">
                    <font-awesome-icon v-if="child.icon && child.icon !== 'circle'" :icon="child.icon" aria-hidden="true"
                                       class="h-3.5 w-3.5 flex-shrink-0 text-maiic-300/70"/>
                    <span v-else class="h-1 w-1 flex-shrink-0 rounded-full bg-maiic-300/40"/>
                    <span class="truncate leading-snug">{{ child.name }}</span>
                </a>

                <Link v-else-if="child.route"
                      :href="route(child.route)"
                      class="group/item relative mr-2 flex items-center gap-2.5 rounded-xl px-3 py-2 text-[13.5px] font-medium transition-all duration-150"
                      :class="isCurrent(child) ? 'bg-maiic-500/20 text-white shadow-sm' : 'text-maiic-100/60 hover:bg-white/[0.05] hover:text-white'"
                      :style="{ marginLeft: ((depth + 1) * 10 + 8) + 'px' }">
                    <span v-if="isCurrent(child)"
                          class="absolute left-0 top-1/2 h-4 w-[3px] -translate-y-1/2 rounded-r-full bg-maiicgold-400"/>
                    <font-awesome-icon v-if="child.icon && child.icon !== 'circle'" :icon="child.icon" aria-hidden="true"
                                       class="h-3.5 w-3.5 flex-shrink-0"
                                       :class="isCurrent(child) ? 'text-maiicgold-400' : 'text-maiic-300/70'"/>
                    <span v-else class="h-1 w-1 flex-shrink-0 rounded-full transition-colors duration-150"
                          :class="isCurrent(child) ? 'bg-maiicgold-400' : 'bg-maiic-300/40 group-hover/item:bg-maiic-200'"/>
                    <span class="truncate leading-snug">{{ child.name }}</span>
                </Link>
            </template>
        </div>
    </div>
</template>

<script>
import { Link } from '@inertiajs/vue3'

export default {
    name: 'DropdownMenu',
    components: { Link },
    props: {
        item: { type: [Array, Object], required: true },
        depth: { type: Number, default: 0 },
        opened: { type: Boolean, default: false },
    },
    data() {
        return { open: this.opened }
    },
    computed: {
        active() {
            return this.containsCurrent(this.item)
        },
    },
    methods: {
        toggle() {
            this.open = !this.open
        },
        isCurrent(node) {
            if (!node || !node.route) return false
            try {
                return route().current(node.route) ||
                    (node.route_check && route().current(node.route_check))
            } catch (e) {
                return false
            }
        },
        containsCurrent(node) {
            if (this.isCurrent(node)) return true
            return (node.children || []).some(c => this.containsCurrent(c))
        },
    },
    mounted() {
        if (this.containsCurrent(this.item)) this.open = true
    },
}
</script>
