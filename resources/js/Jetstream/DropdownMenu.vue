<template>
    <div>
        <!-- Group header: same row shape as a top-level link, plus a chevron.
             On the collapsed icon rail it is the icon alone; a click opens the
             full sidebar with this group open. Plain (a foldable block of the
             sidebar): text only, hung off the block's guide line. -->
        <button type="button" @click="toggle"
                class="group/hdr relative flex items-center gap-3 text-left font-normal transition-colors duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-maiic-400/70"
                :class="[headerShape,
                         active && showBar ? 'bg-white/10 text-white' : (active || open ? 'text-white hover:bg-white/5' : 'text-white/85 hover:bg-white/5 hover:text-white')]"
                :title="rail ? item.name : null"
                :aria-label="rail ? item.name + ', opens the menu' : null"
                :aria-expanded="open && !rail ? 'true' : 'false'">
            <span v-if="active && depth === 0 && showBar && plain" class="absolute inset-y-0 -left-px w-[2px]" :style="{ backgroundColor: accent }"/>
            <span v-else-if="active && depth === 0 && showBar" class="absolute inset-y-1.5 left-0 w-[3px] rounded-r-full bg-maiic-400"/>
            <font-awesome-icon v-if="depth === 0 && item.icon && !plain" :icon="item.icon" aria-hidden="true"
                               class="h-4 !w-5 flex-shrink-0 transition-colors duration-150"
                               :class="active ? 'text-maiic-400' : 'text-white/60 group-hover/hdr:text-white/80'"/>
            <template v-if="!rail">
                <span class="flex-1 truncate leading-snug">{{ item.name }}</span>
                <font-awesome-icon icon="chevron-down" aria-hidden="true"
                                   class="h-3 w-3 flex-shrink-0 text-white/40 transition-transform duration-200"
                                   :class="open ? 'rotate-180' : 'rotate-0'"/>
            </template>
        </button>

        <!-- Children: one step further in, smaller and lighter, on their own guide line -->
        <div v-show="open && !rail" class="relative mb-1 mt-0.5 space-y-px border-l border-white/10 pl-2"
             :class="depth === 0 ? (plain ? 'ml-[15px]' : 'ml-[1.6rem] mr-2') : 'ml-4 mr-1'">
            <template v-for="child in (item.children || [])" :key="child.name">
                <DropdownMenu v-if="child.dropdown && (child.children || []).length"
                              :item="child" :depth="depth + 1" :accent="accent"/>

                <a v-else-if="child.download && child.route"
                   :href="route(child.route)" rel="noopener"
                   class="relative flex items-center rounded-lg px-3 py-[7px] text-[13px] text-white/65 transition-colors duration-150 hover:bg-white/5 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-maiic-400/70">
                    <span class="truncate leading-snug">{{ child.name }}</span>
                </a>

                <Link v-else-if="child.route"
                      :href="route(child.route)"
                      class="relative flex items-center gap-2 rounded-lg px-3 py-[7px] text-[13px] transition-colors duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-maiic-400/70"
                      :class="current(child) ? 'bg-white/10 text-white' : 'text-white/65 hover:bg-white/5 hover:text-white'"
                      :aria-current="current(child) ? 'page' : null">
                    <!-- the active entry: a light tint, white text and a thin bar on the guide line -->
                    <span v-if="current(child)"
                          class="absolute -left-[10px] inset-y-1 w-[2px] rounded-full"
                          :style="{ backgroundColor: accent || '#4ade80' }"/>
                    <span class="truncate leading-snug">{{ child.name }}</span>
                    <span v-if="child.tabs && child.tabs.length > 1"
                          class="ml-auto flex-none rounded-full bg-white/10 px-1.5 text-[10px] font-semibold text-white/70"
                          :title="child.tabs.map(t => t.name).join(', ')">{{ child.tabs.length }}</span>
                </Link>
            </template>
        </div>
    </div>
</template>

<script>
import { Link } from '@inertiajs/vue3'
import { isCurrent, containsCurrent } from '@/Jetstream/menuMatch'

// Which groups the user left open, per user in this browser (the blocks of
// SidebarNav are kept the same way). The group holding the page in view
// opens on every page load.
function groupsKey(user) {
    return 'maiic.sidebar.groups.' + ((user && user.id) || 'guest')
}
function readGroups(user) {
    try {
        const v = JSON.parse(localStorage.getItem(groupsKey(user)) || '{}')
        return v && typeof v === 'object' ? v : {}
    } catch (e) {
        return {}
    }
}
function writeGroup(user, name, open) {
    try {
        localStorage.setItem(groupsKey(user), JSON.stringify({ ...readGroups(user), [name]: open }))
    } catch (e) {
        /* storage unavailable: the group folds for this page only */
    }
}

export default {
    name: 'DropdownMenu',
    components: { Link },
    props: {
        item: { type: [Array, Object], required: true },
        depth: { type: Number, default: 0 },
        // the sidebar is the collapsed icon rail
        collapsed: { type: Boolean, default: false },
        // text only, hung off the guide line of a foldable sidebar block
        plain: { type: Boolean, default: false },
        // the block's colour, for the active bar
        accent: { type: String, default: null },
        // opens with the group open (a click on it on the icon rail)
        startOpen: { type: Boolean, default: false },
    },
    emits: ['expand'],
    data() {
        return { open: this.startOpen || containsCurrent(this.item) || readGroups(this.$page.props.user)[this.item.name] === true }
    },
    computed: {
        active() {
            return containsCurrent(this.item)
        },
        rail() {
            return this.collapsed && this.depth === 0
        },
        // the group's own bar shows when its active child is out of sight
        showBar() {
            return this.rail || !this.open
        },
        headerShape() {
            if (this.rail) return 'mx-2 my-[2px] w-[calc(100%-1rem)] justify-center rounded-xl px-0 py-2.5 text-[14px]'
            if (this.depth > 0) return 'ml-1 w-[calc(100%-0.5rem)] rounded-xl px-3 py-2 text-[13px]'
            if (this.plain) return 'my-[2px] w-full rounded-r-lg py-2 pl-[15px] pr-3 text-[14px]'
            return 'mx-2 my-[2px] w-[calc(100%-1rem)] rounded-xl px-3 py-2.5 text-[14px]'
        },
    },
    methods: {
        current(node) {
            return isCurrent(node)
        },
        toggle() {
            if (this.rail) {
                this.open = true
                this.$emit('expand')
                return
            }
            this.open = !this.open
            writeGroup(this.$page.props.user, this.item.name, this.open)
        },
    },
}
</script>
