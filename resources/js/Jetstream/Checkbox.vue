<template>
    <input type="checkbox" :value="value" v-model="proxyChecked"
           class="rounded border-gray-300 text-maiic-600 shadow-sm focus:border-maiic-500 focus:ring focus:ring-maiic-200 focus:ring-opacity-50 disabled:bg-slate-50 disabled:text-slate-500 disabled:border-slate-200 disabled:shadow-none">
</template>

<script>
export default {
    emits: ['update:checked'],

    props: {
        value: { type: [String, Boolean,Array,Number] },
        checked: {
            type: [Array, Boolean],
            default: false,
        },
        modelValue: {
            default: null,
        },
    },

    computed: {
        proxyChecked: {
            get() {
                return this.checked;
            },

            set(val) {
                this.$emit("update:checked", val);
            },
        },
    },
}
</script>
