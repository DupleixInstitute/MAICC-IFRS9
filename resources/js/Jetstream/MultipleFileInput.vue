<template>
    <div>
        <div class="form-input border-2 border-gray-300  rounded-md shadow-sm p-0">
            <input ref="file" type="file" :accept="accept" class="hidden" multiple @change="change">
            <div v-if="!modelValue" class="p-2">
                <button type="button"
                        class="px-4 py-1 bg-gray-500 hover:bg-gray-700 rounded-sm text-xs font-medium text-white"
                        @click="browse">
                    Browse
                </button>
            </div>
            <div v-else class="flex items-center justify-between p-2">
                <div v-for="item in modelValue">
                    <div class="flex-1 pr-1">{{ item.name }} <span
                        class="text-gray-500 text-xs">({{ filesize(item.size) }})</span></div>

                </div>
                <button type="button"
                        class="px-4 py-1 bg-gray-500 hover:bg-gray-700 rounded-sm text-xs font-medium text-white"
                        @click="remove">
                    Remove
                </button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        modelValue: FileList,
        label: String,
        accept: String,
    },
    emits: ['update:modelValue'],
    watch: {
        modelValue(value) {
            if (!value) {
                this.$refs.file.value = ''
            }
        },
    },
    methods: {
        filesize(size) {
            var i = Math.floor(Math.log(size) / Math.log(1024))
            return (size / Math.pow(1024, i)).toFixed(2) * 1 + ' ' + ['B', 'kB', 'MB', 'GB', 'TB'][i]
        },
        browse() {
            this.$refs.file.click()
        },
        change(e) {
            this.$emit('update:modelValue', e.target.files)
        },
        remove() {
            this.$emit('update:modelValue', null)
        },
    },
}
</script>
