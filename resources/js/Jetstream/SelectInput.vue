<template>
    <div>
        <select :id="id" ref="input" :value="modelValue" @input="$emit('update:modelValue', $event.target.value)"
                v-bind="$attrs"
                class="w-full border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm disabled:bg-slate-50 disabled:text-slate-500 disabled:border-slate-200 disabled:shadow-none"
                :class="{ error: error }">
            <slot/>
        </select>
    </div>
</template>

<script>
export default {
    inheritAttrs: false,
    props: {
        id: {
            type: String,
            default() {

            },
        },
        modelValue: [String, Number, Boolean],
        label: String,
        error: String,
    },
    emits: ['update:modelValue'],
    data() {
        return {
            selected: this.value,
        }
    },
    watch: {
        selected(selected) {
            this.$emit('update:modelValue', selected)
        },
    },
    methods: {
        focus() {
            this.$refs.input.focus()
        },
        select() {
            this.$refs.input.select()
        },
    },
}
</script>
