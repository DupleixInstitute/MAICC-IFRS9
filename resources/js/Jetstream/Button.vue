<template>
    <button :type="type" class="inline-flex items-center px-4 py-2 bg-maiic-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-maiic-700 active:bg-maiic-800 focus:outline-none focus:border-maiic-800 focus:shadow-outline-gray transition ease-in-out duration-150 disabled:bg-slate-50 disabled:text-slate-500 disabled:border-slate-200 disabled:shadow-none">
        <slot></slot>
    </button>
</template>

<script>
    export default {
        props: {
            type: {
                type: String,
                default: 'submit',
            },
        }
    }
</script>
