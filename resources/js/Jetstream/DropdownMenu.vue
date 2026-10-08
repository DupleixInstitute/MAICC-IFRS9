<template>
    <div>
        <!-- Rail: the group's icon tile, then the icons of its leaves (sub-groups flattened) -->
        <template v-if="collapsed && depth === 0">
            <div class="mx-auto mt-2 flex h-8 w-8 items-center justify-center rounded-xl border" :class="[acc.tile, (active ? 'opacity-100' : 'opacity-70')]" :title="item.name">
                <font-awesome-icon v-if="item.icon" :icon="item.icon" aria-hidden="true" class="h-[15px] w-[15px]" :class="acc.icon"/>
            </div>
            <div class="mb-1 flex flex-col items-center gap-0.5 py-1">
                <template v-for="leaf in leaves(item)" :key="leaf.route || leaf.name">
                    <a v-if="leaf.download" :href="route(leaf.route)" rel="noopener" :title="leaf.name"
                       class="flex h-8 w-8 items-center justify-center rounded-lg text-maiic-100/60 hover:bg-white/[0.06] hover:text-white">
                        <font-awesome-icon :icon="leaf.icon && leaf.icon !== 'circle' ? leaf.icon : 'circle'" class="h-3.5 w-3.5"/>
                    </a>
                    <Link v-else :href="route(leaf.route)" :title="leaf.name"
                          class="relative flex h-8 w-8 items-center justify-center rounded-lg transition-colors"
                          :class="isCurrent(leaf) ? 'bg-white/[0.10] text-white' : 'text-maiic-100/60 hover:bg-white/[0.06] hover:text-white'">
                        <span v-if="isCurrent(leaf)" class="absolute left-0 top-1/2 h-4 w-[3px] -translate-y-1/2 rounded-r-full" :class="acc.bar"/>
                        <font-awesome-icon v-if="leaf.icon && leaf.icon !== 'circle'" :icon="leaf.icon" class="h-3.5 w-3.5"/>
                        <span v-else class="h-1.5 w-1.5 rounded-full" :class="isCurrent(leaf) ? acc.bar : 'bg-maiic-300/50'"/>
                    </Link>
                </template>
            </div>
        </template>

        <template v-else>
            <!-- depth 0: group header with its colour tile -->
            <a v-if="depth === 0" @click="toggle"
               class="group/hdr relative flex w-full cursor-pointer items-center gap-3 px-4 py-2 text-left transition-all duration-150"
               :class="(active || open) ? 'text-white' : 'text-maiic-100/70 hover:text-white'">
                <span v-if="active || open" class="absolute left-0 top-1/2 h-6 w-[3px] -translate-y-1/2 rounded-r-full" :class="acc.bar"/>
                <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-xl border transition-all duration-150"
                      :class="[acc.tile, (active || open) ? 'opacity-100' : 'opacity-75 group-hover/hdr:opacity-100']">
                    <font-awesome-icon v-if="item.icon" :icon="item.icon" aria-hidden="true" class="h-[15px] w-[15px]" :class="acc.icon"/>
                </span>
                <span class="flex-1 text-[10.5px] font-bold uppercase tracking-[0.17em]" :class="(active || open) ? acc.text : ''">{{ item.name }}</span>
                <font-awesome-icon icon="chevron-down" class="h-3 w-3 flex-shrink-0 transition-transform duration-200"
                                   :class="[open ? 'rotate-180' : 'rotate-0', (active || open) ? acc.text : 'text-maiic-100/40']"/>
            </a>

            <!-- depth >= 1: sub-group header -->
            <a v-else @click="toggle"
               class="group/sub relative mx-2 flex cursor-pointer items-center gap-2.5 rounded-lg px-3 py-2 transition-all duration-150"
               :class="(active || open) ? 'text-maiic-100' : 'text-maiic-100/60 hover:text-maiic-100'"
               :style="{ marginLeft: (depth * 10 + 8) + 'px' }">
                <font-awesome-icon v-if="item.icon && item.icon !== 'circle'" :icon="item.icon" aria-hidden="true" class="h-3.5 w-3.5 flex-shrink-0 text-maiic-300/70"/>
                <span class="flex-1 text-[11px] font-bold uppercase tracking-[0.13em]">{{ item.name }}</span>
                <font-awesome-icon icon="chevron-down" class="h-2.5 w-2.5 flex-shrink-0 transition-transform duration-200" :class="[open ? 'rotate-180' : 'rotate-0', 'text-maiic-100/40']"/>
            </a>

            <div v-show="open" class="mt-0.5 space-y-0.5" :class="depth === 0 ? 'pb-1' : ''">
                <template v-for="child in (item.children || [])" :key="child.name">
                    <DropdownMenu v-if="child.dropdown && (child.children || []).length" :item="child" :depth="depth + 1" :accent-key="accentKey || item.accent"/>

                    <a v-else-if="child.download && child.route" :href="route(child.route)" rel="noopener"
                       class="group/item relative mr-2 flex items-center gap-2.5 rounded-xl px-3 py-2 text-[13.5px] font-medium text-maiic-100/60 transition-all duration-150 hover:bg-white/[0.05] hover:text-white"
                       :style="{ marginLeft: ((depth + 1) * 10 + 8) + 'px' }">
                        <font-awesome-icon v-if="child.icon && child.icon !== 'circle'" :icon="child.icon" aria-hidden="true" class="h-3.5 w-3.5 flex-shrink-0 text-maiic-300/70"/>
                        <span v-else class="h-1 w-1 flex-shrink-0 rounded-full bg-maiic-300/40"/>
                        <span class="truncate leading-snug">{{ child.name }}</span>
                    </a>

                    <Link v-else-if="child.route" :href="route(child.route)"
                          class="group/item relative mr-2 flex items-center gap-2.5 rounded-xl px-3 py-2 text-[13.5px] font-medium transition-all duration-150"
                          :class="isCurrent(child) ? 'bg-white/[0.08] text-white shadow-sm' : 'text-maiic-100/60 hover:bg-white/[0.05] hover:text-white'"
                          :style="{ marginLeft: ((depth + 1) * 10 + 8) + 'px' }">
                        <span v-if="isCurrent(child)" class="absolute left-0 top-1/2 h-4 w-[3px] -translate-y-1/2 rounded-r-full" :class="acc.bar"/>
                        <font-awesome-icon v-if="child.icon && child.icon !== 'circle'" :icon="child.icon" aria-hidden="true" class="h-3.5 w-3.5 flex-shrink-0" :class="isCurrent(child) ? acc.icon : 'text-maiic-300/70'"/>
                        <span v-else class="h-1 w-1 flex-shrink-0 rounded-full transition-colors duration-150" :class="isCurrent(child) ? acc.bar : 'bg-maiic-300/40 group-hover/item:bg-maiic-200'"/>
                        <span class="truncate leading-snug">{{ child.name }}</span>
                    </Link>
                </template>
            </div>
        </template>
    </div>
</template>

<script>
import { Link } from '@inertiajs/vue3'
import { accent } from '@/navAccents'

export default {
    name: 'DropdownMenu',
    components: { Link },
    props: {
        item: { type: [Array, Object], required: true },
        depth: { type: Number, default: 0 },
        collapsed: { type: Boolean, default: false },
        accentKey: { type: String, default: '' },
        openName: { type: String, default: null },
    },
    emits: ['open'],
    data() {
        return { open: false }
    },
    computed: {
        acc() {
            return accent(this.accentKey || this.item.accent)
        },
        active() {
            return this.containsCurrent(this.item)
        },
    },
    watch: {
        // one top-level group open at a time: another opening closes this one
        openName(name) {
            if (this.depth === 0 && name !== this.item.name) this.open = false
        },
    },
    methods: {
        toggle() {
            this.open = !this.open
            if (this.open && this.depth === 0) this.$emit('open', this.item.name)
        },
        leaves(node) {
            const out = []
            for (const c of node.children || []) {
                if (c.dropdown) out.push(...this.leaves(c))
                else if (c.route) out.push(c)
            }
            return out
        },
        isCurrent(node) {
            if (!node || !node.route) return false
            try {
                return route().current(node.route) || (node.route_check && route().current(node.route_check))
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
        if (this.containsCurrent(this.item)) {
            this.open = true
            if (this.depth === 0) this.$emit('open', this.item.name)
        }
    },
}
</script>
