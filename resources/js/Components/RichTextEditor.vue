<template>
  <div ref="editorContainer" class="bg-white border border-gray-300 rounded-md"></div>
</template>

<script setup>
import { onMounted, ref, watch, onBeforeUnmount } from 'vue'
import Quill from 'quill'
import 'quill/dist/quill.snow.css'

const props = defineProps({
  modelValue: String
})

const emit = defineEmits(['update:modelValue'])

const editorContainer = ref(null)
let quill

onMounted(() => {
  quill = new Quill(editorContainer.value, {
    theme: 'snow',
    placeholder: 'Write manual content here...',
    modules: {
      toolbar: [
        [{ header: [1, 2, false] }],
        ['bold', 'italic', 'underline'],
        ['link', 'blockquote', 'code-block', 'image'],
        [{ list: 'ordered' }, { list: 'bullet' }]
      ]
    }
  })

  quill.root.innerHTML = props.modelValue || ''

  quill.on('text-change', () => {
    emit('update:modelValue', quill.root.innerHTML)
  })
})

watch(() => props.modelValue, (newVal) => {
  if (quill && quill.root.innerHTML !== newVal) {
    quill.root.innerHTML = newVal
  }
})

onBeforeUnmount(() => {
  if (quill) {
    quill.off('text-change')
  }
})
</script>

<style scoped>
.ql-editor {
  min-height: 200px;
}
</style>
