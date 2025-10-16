<template>
    <div class="flex items-center">
        <div class="flex w-full bg-white shadow rounded">
            <jet-dropdown align="left" width="60"
                          class="">
                <template #trigger>
                    <button type="button"
                            class="inline-flex items-center px-3 py-4 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:bg-gray-50 hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
                        <span class="text-gray-700  md:inline">Filter</span>
                        <svg class="w-2 h-2 fill-gray-700 md:ml-2" xmlns="http://www.w3.org/2000/svg"
                             viewBox="0 0 961.243 599.998">
                            <path
                                d="M239.998 239.999L0 0h961.243L721.246 240c-131.999 132-240.28 240-240.624 239.999-.345-.001-108.625-108.001-240.624-240z"/>
                        </svg>
                    </button>
                </template>
                <template #content>
                        <slot></slot>
                </template>
            </jet-dropdown>
            <input
                class="relative w-full px-6 py-3 rounded-r focus:shadow-outline border-gray-300 focus:border-indigo-300"
                autocomplete="off" type="text"
                name="search" placeholder="Search…" :value="modelValue" autofocus
                @input="$emit('update:modelValue', $event.target.value)">
        </div>
        <button class="ml-3 text-sm text-gray-500 hover:text-gray-700 focus:text-indigo-500" type="button"
                @click="$emit('reset')">Reset
        </button>
    </div>
</template>

<script>
import JetDropdown from "@/Jetstream/Dropdown.vue";

export default {
    components: {
        JetDropdown,
    },
    emits: [
        'update:modelValue',
        'reset'
    ],
    props: {
        modelValue: String,
        maxWidth: {
            type: Number,
            default: 300,
        },
        contentClass:{
            type:String,
            default:''
        },
    },
    methods: {}
}
</script>

<style scoped>

</style>
