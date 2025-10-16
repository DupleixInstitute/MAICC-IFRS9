<template>
    <div ref="dropRef" class="p-2 border cursor-pointer bg-white shadow-sm">
        <div ref="dragRef" class="p-2 border cursor-pointer bg-white shadow-sm">
            {{ category.category_name }}
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useDrag, useDrop } from 'react-dnd';

const props = defineProps({
    category: {
        type: Object,
        required: true,
    },
    index: {
        type: Number,
        required: true,
    },
    moveCategory: {
        type: Function,
        required: true,
    },
});

const [, dragRef] = useDrag(() => ({
    type: "category",
    item: { index: props.index },
}));

const [, dropRef] = useDrop({
    accept: "category",
    hover: (item) => {
        if (item.index !== props.index) {
            props.moveCategory(item.index, props.index);
            item.index = props.index;
        }
    },
});
</script>
