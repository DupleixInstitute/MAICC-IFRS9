<template>
    <div>
        <a @click="toggle"
           :class="[active ? 'bg-maiic-800 text-white' : 'text-maiic-100 hover:bg-maiic-600',
                    'group flex items-center px-2 py-2 text-sm font-medium rounded-md cursor-pointer']"
           :style="{ paddingLeft: (depth * 12 + 8) + 'px' }">
            <font-awesome-icon class="mr-3 h-5 w-5 flex-shrink-0 text-maiic-300" aria-hidden="true"
                               v-if="item.icon" :icon="item.icon"/>
            <span class="flex-1">{{ item.name }}</span>
            <font-awesome-icon class="h-3 w-3" :icon="open ? 'chevron-down' : 'chevron-right'"/>
        </a>

        <div v-show="open" class="mt-1 space-y-1">
            <template v-for="child in (item.children || [])" :key="child.name">
                <DropdownMenu v-if="child.dropdown && (child.children || []).length"
                              :item="child" :depth="depth + 1"/>
                <a v-else-if="child.download && child.route"
                   :href="route(child.route)"
                   rel="noopener"
                   :class="['text-maiic-100 hover:bg-maiic-600',
                            'group flex items-center px-2 py-2 text-sm font-medium rounded-md']"
                   :style="{ paddingLeft: ((depth + 1) * 12 + 8) + 'px' }">
                    <font-awesome-icon class="mr-3 h-4 w-4 flex-shrink-0 text-maiic-300" aria-hidden="true"
                                       v-if="child.icon" :icon="child.icon"/>
                    {{ child.name }}
                </a>
                <Link v-else-if="child.route"
                      :href="route(child.route)"
                      :class="[isCurrent(child) ? 'bg-maiic-800 text-white' : 'text-maiic-100 hover:bg-maiic-600',
                               'group flex items-center px-2 py-2 text-sm font-medium rounded-md']"
                      :style="{ paddingLeft: ((depth + 1) * 12 + 8) + 'px' }">
                    <font-awesome-icon class="mr-3 h-4 w-4 flex-shrink-0 text-maiic-300" aria-hidden="true"
                                       v-if="child.icon" :icon="child.icon"/>
                    {{ child.name }}
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
