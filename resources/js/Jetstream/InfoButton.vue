<template>
    <button :type="type"
            class="inline-flex items-center justify-center px-4 py-2 bg-maiic-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-maiic-700 focus:outline-none focus:border-maiic-800 focus:ring focus:ring-maiic-200 active:bg-maiic-700 transition ease-in-out duration-150">
        <slot></slot>
    </button>
</template>

<script>
export default {
    props: {
        type: {
            type: String,
            default: 'button',
        },
    }
}
</script>
