<template>
    <div>
        <!-- A group of the sidebar: a row with a chevron, its pages hung off a
             guide line one step in. In a section (plain) it is a level 2
             entry, text only; nested in another group it is a page-sized row.
             On the collapsed icon rail it is its icon alone, and a click opens
             the full sidebar with this group open. Row styles: the .snav-*
             classes in resources/css/app.css. -->
        <button type="button" @click="toggle"
                :class="[rowClass, lit ? 'snav-active' : (active && !rail ? 'snav-ancestor' : '')]"
                :data-tip="rail ? item.name : null"
                :aria-label="rail ? item.name + ', opens the menu' : null"
                :aria-expanded="rail ? null : (isOpen ? 'true' : 'false')"
                :aria-controls="rail ? null : panelId">
            <span v-if="lit" class="snav-bar" aria-hidden="true"/>
            <font-awesome-icon v-if="item.icon && (rail || (depth === 0 && !plain))" :icon="item.icon" aria-hidden="true" class="snav-icon"/>
            <template v-if="!rail">
                <span class="snav-label">{{ item.name }}</span>
                <font-awesome-icon icon="chevron-down" aria-hidden="true" class="snav-chevron"
                                   :class="isOpen ? 'rotate-180' : 'rotate-0'"/>
            </template>
        </button>

        <!-- the pages: one step further in, on their own guide line -->
        <div v-if="!rail" v-show="isOpen" :id="panelId" class="snav-guide"
             :class="depth === 0 ? 'ml-[42px]' : 'ml-3'">
            <template v-for="child in (item.children || [])" :key="child.name">
                <DropdownMenu v-if="child.dropdown && (child.children || []).length"
                              :item="child" :depth="depth + 1"/>

                <a v-else-if="child.download && child.route"
                   :href="route(child.route)" rel="noopener" class="snav-l3">
                    <span class="snav-label">{{ child.name }}</span>
                </a>

                <Link v-else-if="child.route"
                      :href="route(child.route)"
                      class="snav-l3" :class="{ 'snav-active': current(child) }"
                      :aria-current="current(child) ? 'page' : null">
                    <!-- the page in view: a light tint, white text and its piece of the guide line in gold -->
                    <span v-if="current(child)" class="snav-bar" aria-hidden="true"/>
                    <span class="snav-label">{{ child.name }}</span>
                    <span v-if="child.tabs && child.tabs.length > 1" class="snav-badge"
                          :title="child.tabs.map(t => t.name).join(', ')"
                          :aria-label="child.tabs.length + ' tabs'">{{ child.tabs.length }}</span>
                </Link>
            </template>
        </div>
    </div>
</template>

<script>
import { Link } from '@inertiajs/vue3'
import { isCurrentAmong, containsCurrent } from '@/Jetstream/menuMatch'

// Which nested groups the user left open, per user in this browser. The
// groups of a section are opened and closed by SidebarNav (one at a time,
// passed in as `open`); a group nested in another keeps its own state here.
// The group holding the page in view always opens.
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

let uid = 0

export default {
    name: 'DropdownMenu',
    components: { Link },
    props: {
        item: { type: [Array, Object], required: true },
        depth: { type: Number, default: 0 },
        // the sidebar is the collapsed icon rail
        collapsed: { type: Boolean, default: false },
        // text only: a level 2 entry inside a section of the sidebar
        plain: { type: Boolean, default: false },
        // set by the sidebar for the groups of a section (one open at a time);
        // left unset, the group keeps its own state
        open: { type: Boolean, default: null },
    },
    emits: ['expand', 'toggle'],
    data() {
        uid += 1
        return {
            ownOpen: containsCurrent(this.item) || readGroups(this.$page.props.user)[this.item.name] === true,
            panelId: 'snav-group-' + uid,
        }
    },
    computed: {
        active() {
            return containsCurrent(this.item)
        },
        rail() {
            return this.collapsed && this.depth === 0
        },
        controlled() {
            return this.open !== null
        },
        isOpen() {
            return this.controlled ? this.open : this.ownOpen
        },
        // the group's own row is lit when its page is out of sight
        lit() {
            return this.active && (this.rail || !this.isOpen)
        },
        rowClass() {
            if (this.rail) return 'snav-rail'
            if (this.depth > 0) return 'snav-l3'
            return this.plain ? 'snav-l2' : 'snav-top'
        },
    },
    methods: {
        // of the group's pages, the one in view (the most specific match)
        current(node) {
            return isCurrentAmong(node, this.item.children)
        },
        toggle() {
            if (this.rail) {
                this.$emit('expand')
                return
            }
            if (this.controlled) {
                this.$emit('toggle')
                return
            }
            this.ownOpen = !this.ownOpen
            writeGroup(this.$page.props.user, this.item.name, this.ownOpen)
        },
    },
}
</script>
