<template>

    <div>
        <a @click="open = ! open"
           :class="[item.current ? 'bg-indigo-800 text-white' : 'text-indigo-100 hover:bg-indigo-600', 'group flex items-center px-2 py-2 text-sm font-medium rounded-md cursor-pointer']">

            <font-awesome-icon class="mr-3 h-6 w-6 flex-shrink-0 text-indigo-300" aria-hidden="true" v-if="item.icon"
                               :icon="item.icon"/>
            {{ item.name }}
            <div class="flex grow justify-end">
                <font-awesome-icon class=" h-4 w-4" icon="chevron-down" v-if="open"/>
                <font-awesome-icon class=" h-4 w-4" icon="chevron-right" v-if="!open"/>
            </div>

        </a>
        <div class="ml-2" v-for="child in item.children" v-if="open"
             :key="child.name">
            <Link :href="route(child.route)"
                  :class="[(route().current(child.route)||(child.route_check && route().current(child.route_check))) ? 'bg-indigo-800 text-white' : 'text-indigo-100 hover:bg-indigo-600', 'group flex items-center px-2 py-2 text-sm font-medium rounded-md']">
                <font-awesome-icon class="mr-3 h-6 w-6 flex-shrink-0 text-indigo-300" aria-hidden="true"
                                   v-if="child.icon" :icon="child.icon"/>
                {{ child.name }}
            </Link>
        </div>
    </div>
</template>

<script>
import {Link} from '@inertiajs/vue3'

export default {
    components: {Link},
    props: {
        opened: {
            type: Boolean,
            default: false
        },
        item: {
            type: [Array, Object]
        },
    },
    data() {
        return {
            open: this.opened,
        }
    },
    computed: {
        classes() {
            return this.open
                ? 'inline-flex items-center px-1 pt-1 border-b-2 border-indigo-400 text-sm font-medium leading-5 text-gray-900 focus:outline-none focus:border-indigo-700 transition duration-150 ease-in-out'
                : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out'
        }
    },
    mounted(){
        this.item.children.forEach(item=>{
            if(route().current(item.route)||(item.route_check && route().current(item.route_check))){
                this.open=true
            }
        })
    }
}
</script>
