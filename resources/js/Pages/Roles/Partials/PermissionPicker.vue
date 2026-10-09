<template>
    <!-- Permissions by area: the areas on the left with how many are ticked,
         the chosen area's permissions on the right. Keeps a long list of
         permissions to one screen instead of one long scroll. -->
    <div class="grid grid-cols-1 overflow-hidden rounded-lg border border-gray-200 md:grid-cols-4">
        <nav class="max-h-[28rem] overflow-y-auto border-b border-gray-200 bg-gray-50 md:border-b-0 md:border-r" aria-label="Permission areas">
            <button v-for="m in modules" :key="m" type="button"
                    class="flex w-full items-center justify-between gap-2 border-l-4 px-4 py-2.5 text-left text-sm transition"
                    :class="active === m ? 'border-maiic-600 bg-white font-bold text-maiic-800' : 'border-transparent text-gray-600 hover:bg-white'"
                    @click="active = m">
                <span>{{ tidy(m) }}</span>
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="ticked(m) ? 'bg-maiic-100 text-maiic-800' : 'bg-gray-100 text-gray-500'">{{ ticked(m) }}/{{ permissions[m].length }}</span>
            </button>
        </nav>
        <div class="md:col-span-3">
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                <div class="text-sm font-bold text-gray-900">{{ tidy(active) }}</div>
                <div v-if="!readonly" class="flex gap-3 text-xs font-bold">
                    <button type="button" class="text-maiic-700 hover:underline" @click="setAll(true)">Tick all</button>
                    <button type="button" class="text-red-600 hover:underline" @click="setAll(false)">Clear all</button>
                </div>
                <div v-else class="text-xs text-gray-500">{{ selected.length }} permissions in total</div>
            </div>
            <div class="grid grid-cols-1 gap-x-6 p-4 sm:grid-cols-2">
                <label v-for="p in permissions[active] || []" :key="p.id" class="flex items-center gap-2 border-b border-gray-100 py-2 text-sm" :class="readonly ? '' : 'cursor-pointer'">
                    <input type="checkbox" class="rounded border-gray-300 text-maiic-600 focus:ring-maiic-500" :value="p.name"
                           :checked="selected.includes(p.name)" :disabled="readonly" @change="toggle(p.name, $event.target.checked)">
                    <span :class="selected.includes(p.name) ? 'text-gray-900' : 'text-gray-500'">{{ p.display_name || p.name }}</span>
                </label>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        permissions: { type: Object, required: true },
        modelValue: { type: Array, default: () => [] },
        readonly: { type: Boolean, default: false },
    },
    emits: ['update:modelValue'],
    data() {
        return { active: Object.keys(this.permissions)[0] }
    },
    computed: {
        modules() { return Object.keys(this.permissions) },
        selected() { return this.modelValue || [] },
    },
    methods: {
        // The stored area name "Stageing Rules" is shown as "Staging Rules".
        tidy(m) { return String(m || '').replace('Stageing', 'Staging') },
        ticked(m) { return (this.permissions[m] || []).filter(p => this.selected.includes(p.name)).length },
        toggle(name, on) {
            const next = this.selected.filter(n => n !== name)
            if (on) next.push(name)
            this.$emit('update:modelValue', next)
        },
        setAll(on) {
            const names = (this.permissions[this.active] || []).map(p => p.name)
            const rest = this.selected.filter(n => !names.includes(n))
            this.$emit('update:modelValue', on ? rest.concat(names) : rest)
        },
    },
}
</script>
