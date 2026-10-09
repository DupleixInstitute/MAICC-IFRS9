<template>
    <Pagination :links="links"/>
</template>

<script setup>
// The one pager of the system lives in Components/Pagination.vue.
import Pagination from '@/Components/Pagination.vue';

defineProps({
    links: Array,
});
</script>
