<template>
    <!-- File picker of an import screen: a dashed drop area showing the
         chosen file's name, with the field error under it. -->
    <div>
        <span v-if="label" class="maiic-flabel">{{ label }}</span>
        <label class="flex h-28 w-full flex-col items-center justify-center rounded-lg border-2 border-dashed transition"
               :class="disabled ? 'cursor-not-allowed border-gray-200 bg-gray-50' : 'cursor-pointer border-gray-300 hover:border-maiic-300 hover:bg-maiic-50'">
            <svg class="h-8 w-8 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm3 4a1 1 0 000 2h6a1 1 0 100-2H7zm0 4a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>
            <span class="mt-2 text-sm font-semibold text-gray-600">{{ fileName || placeholder }}</span>
            <span v-if="hint" class="mt-0.5 text-xs text-gray-400">{{ hint }}</span>
            <input type="file" class="hidden" :accept="accept" :disabled="disabled" @change="onChange"/>
        </label>
        <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
    </div>
</template>

<script setup>
import { ref } from 'vue';

defineProps({
    label: { type: String, default: 'File' },
    accept: { type: String, default: '.csv,.txt' },
    placeholder: { type: String, default: 'Choose a CSV file' },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});
const emit = defineEmits(['file']);
const fileName = ref('');

function onChange(e) {
    const file = e.target.files[0] || null;
    fileName.value = file ? file.name : '';
    emit('file', file);
    // Let the same file be chosen again after a failed upload.
    e.target.value = '';
}
</script>
