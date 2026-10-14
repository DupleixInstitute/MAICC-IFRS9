<template>
    <!-- Shared sidebar body: brand header + menu. The parent supplies the
         gradient <aside>/panel container; this fills it.
         Dashboard, Workspace and Reports sit at the top with no heading,
         always shown, their icons in soft gold. Then four blocks
         (config/menu.php 'heading'), each heading with its icon in its own
         soft colour; they fold from their heading; their entries are text, hung off a thin guide line in the
         block's colour, and the active entry turns its piece of the line into
         a solid bar with a light tint behind it. Collapsed (desktop only), the
         sidebar is an icon rail: every entry's icon with its name as tooltip,
         the blocks drawn as thin rules, and a group opens the full sidebar on
         that group. -->
    <div class="relative flex h-full min-h-0 flex-col">
        <!-- ambient glows (top green, bottom gold) -->
        <div class="pointer-events-none absolute inset-x-0 top-0 h-40 opacity-70"
             style="background: radial-gradient(ellipse at 50% 0%, rgba(34,197,94,0.30) 0%, transparent 72%)"/>
        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-40 opacity-40"
             style="background: radial-gradient(ellipse at 50% 100%, rgba(212,160,23,0.20) 0%, transparent 72%)"/>

        <!-- brand header -->
        <div class="relative z-10 flex h-[68px] shrink-0 items-center gap-3"
             :class="collapsed ? 'justify-center px-2' : 'px-4'"
             style="border-bottom: 1px solid rgba(255,255,255,0.055);">
            <inertia-link :href="'/'" class="flex min-w-0 items-center gap-3" :title="collapsed ? $page.props.companyName : null">
                <img :src="collapsed ? ($page.props.smallLogoUrl || $page.props.logoUrl) : $page.props.logoUrl" :alt="$page.props.companyName"
                     class="w-auto object-contain" :class="collapsed ? 'h-8 max-w-[52px]' : 'h-10 max-w-[150px]'"/>
                <span v-if="!collapsed" class="flex min-w-0 flex-col">
                    <span class="truncate text-[11px] font-medium leading-none" style="color: rgba(212,160,23,0.85)">
                        IFRS 9 ECL &amp; EIR Platform
                    </span>
                </span>
            </inertia-link>
        </div>

        <!-- menu -->
        <nav class="sidebar-scroll relative z-10 min-h-0 flex-1 overflow-y-auto overflow-x-hidden py-3" aria-label="Main menu">
            <div v-for="(block, b) in blocks" :key="block.heading || b">

                <!-- ============ the icon rail ============ -->
                <template v-if="collapsed">
                    <div v-if="b > 0" class="mx-4 my-3 h-px bg-white/10" role="separator" :aria-label="block.heading"/>
                    <template v-for="item in block.items" :key="item.name">
                        <DropdownMenu v-if="item.dropdown" :item="item" collapsed @expand="openFromRail(block, item)"/>
                        <component v-else :is="item.download ? 'a' : (item.route ? 'Link' : 'div')"
                                   :href="item.route ? route(item.route) : undefined" :rel="item.download ? 'noopener' : undefined"
                                   :title="item.name" :aria-label="item.name" :aria-current="isCurrent(item) ? 'page' : null"
                                   class="group relative mx-2 my-[2px] flex items-center justify-center rounded-xl py-2.5 transition-colors duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-maiic-400/70"
                                   :class="isCurrent(item) ? 'bg-white/10' : 'hover:bg-white/5'">
                            <span v-if="isCurrent(item)" class="absolute inset-y-1.5 left-0 w-[3px] rounded-r-full bg-maiic-400"/>
                            <font-awesome-icon v-if="item.icon" :icon="item.icon" aria-hidden="true"
                                               class="h-4 !w-5 flex-shrink-0 transition-colors duration-150"
                                               :class="isCurrent(item) ? 'text-maiic-400' : 'text-white/60 group-hover:text-white/80'"/>
                        </component>
                    </template>
                </template>

                <!-- ============ the full sidebar ============ -->
                <template v-else>
                    <!-- block heading: icon and label in the block's colour; a chevron folds every block but Overview -->
                    <div v-if="block.heading" :class="b === 0 ? 'mt-1' : 'mx-3 mt-6 border-t border-white/10 pt-4'">
                        <component :is="block.fixed ? 'div' : 'button'" :type="block.fixed ? undefined : 'button'"
                                   @click="block.fixed ? null : toggleBlock(block.heading)"
                                   class="mb-1.5 flex w-full items-center gap-2 rounded-lg py-1 text-left text-[13px] font-semibold uppercase leading-4 tracking-wide"
                                   :class="[b === 0 ? 'px-5' : 'px-2', block.fixed ? '' : 'transition-opacity duration-150 hover:opacity-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-white/40']"
                                   :style="{ color: block.colour, opacity: block.fixed || blockOpen(block) ? 1 : 0.85 }"
                                   :aria-expanded="block.fixed ? undefined : (blockOpen(block) ? 'true' : 'false')"
                                   :aria-controls="block.fixed ? undefined : 'menu-block-' + b">
                            <font-awesome-icon :icon="block.icon" class="h-3.5 !w-4 flex-none" aria-hidden="true"/>
                            <span class="min-w-0 flex-1">{{ block.heading }}</span>
                            <font-awesome-icon v-if="!block.fixed" icon="chevron-down" aria-hidden="true"
                                               class="h-3 w-3 flex-none opacity-70 transition-transform duration-200"
                                               :class="blockOpen(block) ? 'rotate-180' : 'rotate-0'"/>
                        </component>
                    </div>

                    <!-- the top entries (no heading): always shown, with their icons -->
                    <template v-if="block.fixed">
                        <template v-for="item in block.items" :key="item.name">
                            <DropdownMenu v-if="item.dropdown" :item="item"/>
                            <component v-else :is="item.download ? 'a' : (item.route ? 'Link' : 'div')"
                                       :href="item.route ? route(item.route) : undefined" :rel="item.download ? 'noopener' : undefined"
                                       :aria-current="isCurrent(item) ? 'page' : null"
                                       class="group relative mx-2 my-[2px] flex items-center gap-3 rounded-xl px-3 py-2.5 text-[14px] font-normal transition-colors duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-maiic-400/70"
                                       :class="isCurrent(item) ? 'bg-white/10 text-white' : 'text-white/85 hover:bg-white/5 hover:text-white'">
                                <span v-if="isCurrent(item)" class="absolute inset-y-1.5 left-0 w-[3px] rounded-r-full" :style="{ backgroundColor: block.colour }"/>
                                <font-awesome-icon v-if="item.icon" :icon="item.icon" aria-hidden="true"
                                                   class="h-4 !w-5 flex-shrink-0 transition-opacity duration-150"
                                                   :class="isCurrent(item) ? 'opacity-100' : 'opacity-75 group-hover:opacity-100'"
                                                   :style="{ color: block.colour }"/>
                                <span class="truncate leading-snug">{{ item.name }}</span>
                            </component>
                        </template>
                    </template>

                    <!-- a foldable block: text entries hung off a thin guide line in the block's colour -->
                    <div v-else v-show="blockOpen(block)" :id="'menu-block-' + b"
                         class="ml-7 mr-2 border-l" :style="{ borderColor: tint(block.colour, 0.3) }">
                        <template v-for="item in block.items" :key="item.name">

                            <DropdownMenu v-if="item.dropdown" :item="item" plain :accent="block.colour" :start-open="railOpened === item.name"/>

                            <component v-else :is="item.download ? 'a' : (item.route ? 'Link' : 'div')"
                                       :href="item.route ? route(item.route) : undefined" :rel="item.download ? 'noopener' : undefined"
                                       :aria-current="isCurrent(item) ? 'page' : null"
                                       class="group relative my-[2px] flex items-center gap-2 rounded-r-lg py-2 pl-[15px] pr-3 text-[14px] font-normal transition-colors duration-150 focus:outline-none focus-visible:ring-2 focus-visible:ring-maiic-400/70"
                                       :class="isCurrent(item) ? 'bg-white/10 text-white' : 'text-white/85 hover:bg-white/5 hover:text-white'">
                                <!-- the active entry: its piece of the guide line becomes a solid bar -->
                                <span v-if="isCurrent(item)" class="absolute inset-y-0 -left-px w-[2px]" :style="{ backgroundColor: block.colour }"/>
                                <span class="truncate leading-snug">{{ item.name }}</span>
                                <span v-if="item.tabs && item.tabs.length > 1"
                                      class="ml-auto flex-none rounded-full bg-white/10 px-1.5 text-[10px] font-semibold text-white/70"
                                      :title="item.tabs.map(t => t.name).join(', ')">{{ item.tabs.length }}</span>
                            </component>
                        </template>
                    </div>
                </template>
            </div>
        </nav>

        <!-- collapse / expand (the desktop sidebar only) -->
        <div v-if="collapsible" class="relative z-10 shrink-0 p-2" style="border-top: 1px solid rgba(255,255,255,0.055);">
            <button type="button" @click="$emit('toggle')"
                    :aria-expanded="collapsed ? 'false' : 'true'"
                    :aria-label="collapsed ? 'Expand the menu' : 'Collapse the menu'"
                    :title="collapsed ? 'Expand the menu' : 'Collapse the menu'"
                    class="flex w-full items-center gap-3 rounded-xl py-2.5 text-[13px] font-normal text-white/65 transition-colors duration-150 hover:bg-white/5 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-maiic-400/70"
                    :class="collapsed ? 'justify-center px-0' : 'px-3'">
                <font-awesome-icon :icon="collapsed ? 'chevron-right' : 'chevron-left'" aria-hidden="true" class="h-3.5 w-3.5 flex-shrink-0"/>
                <span v-if="!collapsed" class="truncate">Collapse menu</span>
            </button>
        </div>
    </div>
</template>

<script>
import { Link } from '@inertiajs/vue3'
import DropdownMenu from '@/Jetstream/DropdownMenu.vue'
import { isCurrent, containsCurrent } from '@/Jetstream/menuMatch'

// The block headings of config/menu.php ('heading'), as the Stress Testing
// suite draws them: an icon before each, Overview always open, every other
// block folds from its heading and opens by itself on the page in view. Each
// block has its own soft colour on the dark green (the sidebar keeps its dark
// gradient in both themes).
const ALWAYS_OPEN = 'Overview'
const BLOCKS = {
    'Overview': { icon: 'th-large', colour: '#fcd34d' },                    // amber-300
    'Data foundation': { icon: 'layer-group', colour: '#7dd3fc' },          // sky-300
    'Financial modelling': { icon: 'project-diagram', colour: '#6ee7b7' },  // emerald-300
    'Risk & regulatory': { icon: 'balance-scale', colour: '#fda4af' },      // rose-300
    'Administration & support': { icon: 'tools', colour: '#c4b5fd' },       // violet-300
}
function blocksKey(user) {
    return 'maiic.sidebar.blocks.' + ((user && user.id) || 'guest')
}
function readOpenBlocks(user) {
    try {
        const v = JSON.parse(localStorage.getItem(blocksKey(user)) || '{}')
        return v && typeof v === 'object' ? v : {}
    } catch (e) {
        return {}
    }
}
function writeOpenBlocks(user, state) {
    try {
        localStorage.setItem(blocksKey(user), JSON.stringify(state))
    } catch (e) {
        /* storage unavailable: the blocks fold for this page only */
    }
}

export default {
    name: 'SidebarNav',
    components: { Link, DropdownMenu },
    props: {
        // the icon rail: icons only, names as tooltips
        collapsed: { type: Boolean, default: false },
        // shows the collapse / expand button (the desktop sidebar, not the phone drawer)
        collapsible: { type: Boolean, default: false },
    },
    emits: ['toggle', 'expand'],
    data() {
        // railOpened: the group clicked on the icon rail, open in the full sidebar
        return { openBlocks: readOpenBlocks(this.$page.props.user), railOpened: null }
    },
    created() {
        // each page load (Inertia renders the layout afresh) opens the block
        // that holds the page in view; the user can still fold it
        const active = this.blocks.find(bl => bl.active && !bl.fixed)
        if (active && !this.openBlocks[active.heading]) {
            this.openBlocks = { ...this.openBlocks, [active.heading]: true }
            writeOpenBlocks(this.$page.props.user, this.openBlocks)
        }
    },
    computed: {
        menu() {
            return this.$page.props.menu || []
        },
        // the menu in its blocks (consecutive entries with the same 'heading')
        blocks() {
            const out = []
            for (const item of this.menu) {
                const last = out[out.length - 1]
                if (last && last.heading === (item.heading || null)) last.items.push(item)
                else out.push({ heading: item.heading || null, items: [item] })
            }
            return out.map(bl => ({
                ...bl,
                icon: (BLOCKS[bl.heading] || {}).icon || 'circle',
                // the untitled top block (Dashboard, Workspace, Reports) keeps the soft gold
                colour: (BLOCKS[bl.heading] || {}).colour || BLOCKS[ALWAYS_OPEN].colour,
                // Overview always shows; an untitled block cannot fold either
                fixed: !bl.heading || bl.heading === ALWAYS_OPEN,
                active: bl.items.some(it => containsCurrent(it)),
            }))
        },
    },
    methods: {
        isCurrent(item) {
            return isCurrent(item)
        },
        // a block is open when it cannot fold, or the user left it open
        // (remembered per user in this browser); the block holding the page
        // in view opens as the page loads (created)
        blockOpen(block) {
            return block.fixed || !!this.openBlocks[block.heading]
        },
        // a group clicked on the icon rail: the full sidebar opens on it
        openFromRail(block, item) {
            this.railOpened = item.name
            if (!block.fixed && !this.openBlocks[block.heading]) {
                this.openBlocks = { ...this.openBlocks, [block.heading]: true }
                writeOpenBlocks(this.$page.props.user, this.openBlocks)
            }
            this.$emit('expand')
        },
        toggleBlock(heading) {
            this.openBlocks = { ...this.openBlocks, [heading]: !this.openBlocks[heading] }
            writeOpenBlocks(this.$page.props.user, this.openBlocks)
        },
        // a hex colour at an opacity, for the guide lines
        tint(hex, alpha) {
            const m = /^#([0-9a-f]{6})$/i.exec(hex || '')
            if (!m) return 'rgba(255,255,255,' + alpha + ')'
            const n = parseInt(m[1], 16)
            return `rgba(${(n >> 16) & 255}, ${(n >> 8) & 255}, ${n & 255}, ${alpha})`
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
