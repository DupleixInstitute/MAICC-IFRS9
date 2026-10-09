<template>
    <div>
        <!-- Group header: same row shape as a top-level link, plus a chevron -->
        <button type="button" @click="open = !open"
                class="group/hdr relative flex items-center gap-3 rounded-xl px-3 text-left font-medium transition-colors duration-150"
                :class="[depth === 0 ? 'mx-2 my-[2px] w-[calc(100%-1rem)] py-2.5 text-[14px]' : 'ml-1 w-[calc(100%-0.5rem)] py-2 text-[13px]',
                         active ? 'text-white' : (open ? 'text-white/95' : 'text-maiic-100/70 hover:bg-white/[0.05] hover:text-white')]"
                :aria-expanded="open ? 'true' : 'false'">
            <span v-if="active && depth === 0" class="absolute left-0 top-1/2 h-5 w-[3px] -translate-y-1/2 rounded-r-full bg-maiicgold-400"/>
            <font-awesome-icon v-if="depth === 0 && item.icon" :icon="item.icon" aria-hidden="true"
                               class="h-4 w-4 flex-shrink-0 transition-colors duration-150"
                               :class="item.color ? '' : (active ? 'text-maiicgold-400' : 'text-maiic-300/80')"
                               :style="item.color ? { color: item.color } : null"/>
            <span class="flex-1 truncate leading-snug">{{ item.name }}</span>
            <font-awesome-icon icon="chevron-down" aria-hidden="true"
                               class="h-3 w-3 flex-shrink-0 transition-transform duration-200"
                               :class="[open ? 'rotate-180' : 'rotate-0', active ? 'text-maiicgold-400/80' : 'text-maiic-100/40']"/>
        </button>

        <!-- Children hang off a thin guide line under the group icon -->
        <div v-show="open" class="relative mb-1 mt-0.5 space-y-px border-l border-white/10 pl-2"
             :class="depth === 0 ? 'ml-[1.6rem] mr-2' : 'ml-4 mr-1'">
            <template v-for="child in (item.children || [])" :key="child.name">
                <DropdownMenu v-if="child.dropdown && (child.children || []).length"
                              :item="child" :depth="depth + 1"/>

                <a v-else-if="child.download && child.route"
                   :href="route(child.route)" rel="noopener"
                   class="relative flex items-center rounded-lg px-3 py-[7px] text-[13px] text-maiic-100/65 transition-colors duration-150 hover:bg-white/[0.05] hover:text-white">
                    <span class="truncate leading-snug">{{ child.name }}</span>
                </a>

                <Link v-else-if="child.route"
                      :href="route(child.route)"
                      class="relative flex items-center gap-2 rounded-lg px-3 py-[7px] text-[13px] transition-colors duration-150"
                      :class="current(child) ? 'bg-white/[0.09] font-semibold text-white' : 'text-maiic-100/65 hover:bg-white/[0.05] hover:text-white'"
                      :aria-current="current(child) ? 'page' : null">
                    <!-- marker sits on the guide line -->
                    <span v-if="current(child)"
                          class="absolute -left-[10px] top-1/2 h-5 w-[3px] -translate-y-1/2 rounded-full bg-maiicgold-400"/>
                    <span class="truncate leading-snug">{{ child.name }}</span>
                    <span v-if="child.tabs && child.tabs.length > 1"
                          class="ml-auto flex-none rounded-full bg-white/[0.07] px-1.5 text-[10px] font-semibold text-maiic-100/60"
                          :title="child.tabs.map(t => t.name).join(', ')">{{ child.tabs.length }}</span>
                </Link>
            </template>
        </div>
    </div>
</template>

<script>
import { Link } from '@inertiajs/vue3'
import { isCurrent, containsCurrent } from '@/Jetstream/menuMatch'

export default {
    name: 'DropdownMenu',
    components: { Link },
    props: {
        item: { type: [Array, Object], required: true },
        depth: { type: Number, default: 0 },
    },
    data() {
        return { open: containsCurrent(this.item) }
    },
    computed: {
        active() {
            return containsCurrent(this.item)
        },
    },
    methods: {
        current(node) {
            return isCurrent(node)
        },
    },
}
</script>
