<template>
    <!-- Shared sidebar body: brand header, menu, collapse control. The parent
         supplies the gradient <aside>/panel container; this fills it, and only
         the menu between the brand and the collapse control scrolls.
         Dashboard, Workspace and Reports sit at the top with no heading,
         always shown. Then the four sections (config/menu.php 'heading'):
         one open at a time, the one holding the page in view always open, and
         otherwise the one the user last opened (remembered per user in this
         browser). Inside a section one group is open at a time, again the
         group of the page in view first. Collapsed (desktop only), the
         sidebar is an icon rail: every entry's icon, its name in a tooltip
         beside the rail, the sections drawn as thin rules, and a group opens
         the full sidebar on that group. Row styles: the .snav-* classes in
         resources/css/app.css; colours: 'sidenav' in tailwind.config.js. -->
    <div class="relative flex h-full min-h-0 flex-col">
        <!-- ambient glows (top green, bottom gold) -->
        <div class="pointer-events-none absolute inset-x-0 top-0 h-40 opacity-70"
             style="background: radial-gradient(ellipse at 50% 0%, rgba(34,197,94,0.30) 0%, transparent 72%)"/>
        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-40 opacity-40"
             style="background: radial-gradient(ellipse at 50% 100%, rgba(212,160,23,0.20) 0%, transparent 72%)"/>

        <!-- brand header: the height of the top bar beside it -->
        <div class="relative z-10 flex h-16 shrink-0 items-center border-b border-white/[0.06]"
             :class="collapsed ? 'justify-center px-2' : 'px-4'">
            <inertia-link :href="'/'" class="flex min-w-0 items-center gap-2.5 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-maiic-300/80"
                          :aria-label="collapsed ? $page.props.companyName : null">
                <img :src="collapsed && !smallLogoFailed ? ($page.props.smallLogoUrl || $page.props.logoUrl) : $page.props.logoUrl" :alt="$page.props.companyName"
                     @error="collapsed && (smallLogoFailed = true)"
                     class="w-auto object-contain" :class="collapsed ? 'h-8 max-w-[52px]' : 'h-9 max-w-[120px]'"/>
                <span v-if="!collapsed" class="flex min-w-0 flex-col">
                    <span class="whitespace-nowrap text-[11px] font-medium leading-none" style="color: rgba(212,160,23,0.85)">
                        IFRS 9 ECL &amp; EIR Platform
                    </span>
                </span>
            </inertia-link>
        </div>

        <!-- menu -->
        <nav ref="nav" class="sidebar-scroll relative z-10 min-h-0 flex-1 overflow-y-auto overflow-x-hidden py-2" aria-label="Main menu"
             @keydown="onKeydown" @scroll.passive="saveScroll"
             @mouseover="showTip" @mouseleave="tip = null" @focusin="showTip" @focusout="tip = null">
            <div v-for="(block, b) in blocks" :key="block.heading || b">

                <!-- ============ the icon rail ============ -->
                <template v-if="collapsed">
                    <div v-if="b > 0" class="mx-4 my-2 h-px bg-white/[0.08]" role="separator" :aria-label="block.heading"/>
                    <template v-for="item in block.items" :key="item.name">
                        <DropdownMenu v-if="item.dropdown" :item="item" collapsed @expand="openFromRail(block, item)"/>
                        <component v-else :is="item.download ? 'a' : (item.route ? 'Link' : 'div')"
                                   :href="item.route ? route(item.route) : undefined" :rel="item.download ? 'noopener' : undefined"
                                   :data-tip="item.name" :aria-label="item.name" :aria-current="isCurrent(item) ? 'page' : null"
                                   class="snav-rail" :class="{ 'snav-active': isCurrent(item) }">
                            <span v-if="isCurrent(item)" class="snav-bar" aria-hidden="true"/>
                            <font-awesome-icon :icon="item.icon || 'circle'" aria-hidden="true" class="snav-icon"/>
                        </component>
                    </template>
                </template>

                <!-- ============ the full sidebar ============ -->
                <template v-else>
                    <!-- the top entries (no heading): always shown, with their icons -->
                    <div v-if="block.fixed" class="mx-2 space-y-px">
                        <template v-for="item in block.items" :key="item.name">
                            <DropdownMenu v-if="item.dropdown" :item="item"/>
                            <component v-else :is="item.download ? 'a' : (item.route ? 'Link' : 'div')"
                                       :href="item.route ? route(item.route) : undefined" :rel="item.download ? 'noopener' : undefined"
                                       :aria-current="isCurrent(item) ? 'page' : null"
                                       class="snav-top" :class="{ 'snav-active': isCurrent(item) }">
                                <span v-if="isCurrent(item)" class="snav-bar" aria-hidden="true"/>
                                <font-awesome-icon :icon="item.icon || 'circle'" aria-hidden="true" class="snav-icon"/>
                                <span class="snav-label">{{ item.name }}</span>
                            </component>
                        </template>
                    </div>

                    <!-- a section: its heading folds it (one section open at a time) -->
                    <div v-else class="mx-2 mt-1.5 border-t border-white/[0.07] pt-1.5">
                        <button type="button" class="snav-heading" data-nav-section
                                @click="toggleSection(block.heading)"
                                :aria-expanded="sectionOpen(block) ? 'true' : 'false'"
                                :aria-controls="uid + '-section-' + b">
                            <font-awesome-icon :icon="block.icon" class="snav-icon" aria-hidden="true"/>
                            <span class="snav-label">{{ block.heading }}</span>
                            <font-awesome-icon icon="chevron-down" aria-hidden="true" class="snav-chevron"
                                               :class="sectionOpen(block) ? 'rotate-180' : 'rotate-0'"/>
                        </button>

                        <div v-show="sectionOpen(block)" :id="uid + '-section-' + b" class="space-y-px pb-1">
                            <template v-for="item in block.items" :key="item.name">
                                <DropdownMenu v-if="item.dropdown" :item="item" plain
                                              :open="groupOpen(item)" @toggle="toggleGroup(block, item)"/>

                                <component v-else :is="item.download ? 'a' : (item.route ? 'Link' : 'div')"
                                           :href="item.route ? route(item.route) : undefined" :rel="item.download ? 'noopener' : undefined"
                                           :aria-current="isCurrent(item) ? 'page' : null"
                                           class="snav-l2" :class="{ 'snav-active': isCurrent(item) }">
                                    <span v-if="isCurrent(item)" class="snav-bar" aria-hidden="true"/>
                                    <span class="snav-label">{{ item.name }}</span>
                                    <span v-if="item.tabs && item.tabs.length > 1" class="snav-badge"
                                          :title="item.tabs.map(t => t.name).join(', ')"
                                          :aria-label="item.tabs.length + ' tabs'">{{ item.tabs.length }}</span>
                                </component>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </nav>

        <!-- the name of a rail icon, beside the rail (fixed, so the scrolling menu does not clip it) -->
        <div v-if="collapsed && tip" class="snav-tip" role="tooltip" :style="{ top: tip.top + 'px', left: tip.left + 'px' }">{{ tip.text }}</div>

        <!-- collapse / expand (the desktop sidebar only), anchored at the bottom -->
        <div v-if="collapsible" class="relative z-10 shrink-0 border-t border-white/[0.06] p-2">
            <button type="button" @click="$emit('toggle')"
                    :aria-expanded="collapsed ? 'false' : 'true'"
                    :aria-label="collapsed ? 'Expand the menu' : 'Collapse the menu'"
                    :data-tip="collapsed ? 'Expand the menu' : null"
                    @mouseenter="collapsed && showTip($event)" @mouseleave="tip = null" @focus="collapsed && showTip($event)" @blur="tip = null"
                    class="snav-row py-2 text-[12.5px] font-medium text-sidenav-sub hover:text-white"
                    :class="collapsed ? 'justify-center' : 'px-2.5'">
                <font-awesome-icon :icon="collapsed ? 'chevron-right' : 'chevron-left'" aria-hidden="true" class="h-3 !w-5 flex-none"/>
                <span v-if="!collapsed" class="snav-label">Collapse menu</span>
            </button>
        </div>
    </div>
</template>

<script>
import { Link } from '@inertiajs/vue3'
import DropdownMenu from '@/Jetstream/DropdownMenu.vue'
import { isCurrentAmong, containsCurrent } from '@/Jetstream/menuMatch'

// The section headings of config/menu.php ('heading') and their icons. All
// four are drawn in the one muted green (the sidebar keeps its dark gradient
// in both themes); only the page in view carries the gold accent.
const ALWAYS_OPEN = 'Overview'
const SECTION_ICONS = {
    'Overview': 'th-large',
    'Data foundation': 'layer-group',
    'Financial modelling': 'project-diagram',
    'Risk & regulatory': 'balance-scale',
    'Administration & support': 'tools',
}

// Per user in this browser: the section last open ({ heading: true }, the
// key the sidebar has always used) and the groups left open.
function storeKey(kind, user) {
    return 'maiic.sidebar.' + kind + '.' + ((user && user.id) || 'guest')
}
function readStore(kind, user) {
    try {
        const v = JSON.parse(localStorage.getItem(storeKey(kind, user)) || '{}')
        return v && typeof v === 'object' ? v : {}
    } catch (e) {
        return {}
    }
}
function writeStore(kind, user, state) {
    try {
        localStorage.setItem(storeKey(kind, user), JSON.stringify(state))
    } catch (e) {
        /* storage unavailable: the menu keeps its state for this page only */
    }
}
// the menu's scroll position, kept while the user moves from page to page
const SCROLL_KEY = 'maiic.sidebar.scroll'

let uid = 0

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
        uid += 1
        // smallLogoFailed: the rail's small logo did not load (the file is
        // missing): the rail shows the full logo, scaled down, instead
        return { openSection: null, openGroups: {}, tip: null, uid: 'snav' + uid, smallLogoFailed: false }
    },
    created() {
        // Each page load (Inertia renders the layout afresh) opens the section
        // and the group that hold the page in view; elsewhere (Dashboard,
        // Workspace, Reports) the section the user last opened stays open.
        const user = this.$page.props.user
        const sections = this.blocks.filter(bl => !bl.fixed)
        const active = sections.find(bl => bl.active)
        const stored = readStore('blocks', user)
        const remembered = sections.find(bl => stored[bl.heading] === true)
        this.openSection = active ? active.heading : (remembered ? remembered.heading : null)

        // one group open per section: the group of the page in view, else the first one left open
        const groups = readStore('groups', user)
        for (const bl of sections) {
            const own = bl.items.filter(it => it.dropdown)
            const keep = own.find(it => containsCurrent(it)) || own.find(it => groups[it.name] === true)
            for (const it of own) groups[it.name] = it === keep
        }
        this.openGroups = groups
        this.remember()
    },
    mounted() {
        // keep the menu where the user left it, and the page in view in sight
        const nav = this.$refs.nav
        if (!nav || this.collapsed) return
        try {
            const y = Number(sessionStorage.getItem(SCROLL_KEY))
            if (y > 0) nav.scrollTop = y
        } catch (e) { /* no session storage: the menu starts at the top */ }
        this.$nextTick(() => {
            const cur = nav.querySelector('[aria-current="page"]')
            if (!cur) return
            const n = nav.getBoundingClientRect()
            const r = cur.getBoundingClientRect()
            // the menu alone scrolls (never the page behind it)
            if (r.top < n.top) nav.scrollTop -= n.top - r.top + 8
            else if (r.bottom > n.bottom) nav.scrollTop += r.bottom - n.bottom + 8
        })
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
                icon: SECTION_ICONS[bl.heading] || 'circle',
                // the untitled top block (Dashboard, Workspace, Reports) always shows
                fixed: !bl.heading || bl.heading === ALWAYS_OPEN,
                active: bl.items.some(it => containsCurrent(it)),
            }))
        },
    },
    methods: {
        // the entry that is the page in view (of its siblings, the most specific match)
        isCurrent(item) {
            return isCurrentAmong(item, this.menu)
        },
        sectionOpen(block) {
            return block.fixed || this.openSection === block.heading
        },
        groupOpen(item) {
            return this.openGroups[item.name] === true
        },
        remember() {
            const user = this.$page.props.user
            writeStore('blocks', user, this.openSection ? { [this.openSection]: true } : {})
            writeStore('groups', user, this.openGroups)
        },
        // the accordion: opening a section closes the one that was open
        toggleSection(heading) {
            this.openSection = this.openSection === heading ? null : heading
            this.remember()
        },
        // one group open per section
        toggleGroup(block, item) {
            const opening = !this.groupOpen(item)
            const next = { ...this.openGroups }
            for (const it of block.items) if (it.dropdown) next[it.name] = false
            next[item.name] = opening
            this.openGroups = next
            this.remember()
        },
        // a group clicked on the icon rail: the full sidebar opens on it
        openFromRail(block, item) {
            if (!block.fixed) {
                this.openSection = block.heading
                const next = { ...this.openGroups }
                for (const it of block.items) if (it.dropdown) next[it.name] = false
                next[item.name] = true
                this.openGroups = next
            } else {
                this.openGroups = { ...this.openGroups, [item.name]: true }
            }
            this.remember()
            this.$emit('expand')
        },
        saveScroll(e) {
            if (this.collapsed) return
            try {
                sessionStorage.setItem(SCROLL_KEY, String(Math.round(e.target.scrollTop)))
            } catch (err) { /* no session storage */ }
        },
        // the rail's tooltip: the entry's name, beside the rail at its height
        showTip(e) {
            if (!this.collapsed) return
            const el = e.target && e.target.closest ? e.target.closest('[data-tip]') : null
            if (!el) {
                this.tip = null
                return
            }
            const r = el.getBoundingClientRect()
            const rail = this.$el.getBoundingClientRect()
            this.tip = { text: el.getAttribute('data-tip'), top: Math.round(r.top + r.height / 2), left: Math.round(rail.right + 8) }
        },
        // Arrow keys move through the visible entries; Right opens a folded
        // section or group, Left folds it; Home and End go to the ends.
        onKeydown(e) {
            const keys = ['ArrowDown', 'ArrowUp', 'Home', 'End', 'ArrowRight', 'ArrowLeft']
            if (!keys.includes(e.key) || e.altKey || e.ctrlKey || e.metaKey) return
            const nav = this.$refs.nav
            const items = [...nav.querySelectorAll('a[href], button')].filter(el => el.offsetParent !== null)
            const at = items.indexOf(document.activeElement)
            if (at < 0) return
            const el = items[at]
            if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                const exp = el.getAttribute('aria-expanded')
                if ((e.key === 'ArrowRight' && exp === 'false') || (e.key === 'ArrowLeft' && exp === 'true')) {
                    e.preventDefault()
                    el.click()
                }
                return
            }
            e.preventDefault()
            let next = at
            if (e.key === 'ArrowDown') next = Math.min(items.length - 1, at + 1)
            if (e.key === 'ArrowUp') next = Math.max(0, at - 1)
            if (e.key === 'Home') next = 0
            if (e.key === 'End') next = items.length - 1
            items[next].focus()
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
