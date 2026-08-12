<template>
    <div class="flex items-center justify-end gap-1.5">
        <inertia-link v-if="viewHref" :href="viewHref" class="maiic-action maiic-action-view" title="View">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/>
                <circle cx="12" cy="12" r="3"/>
            </svg>
        </inertia-link>
        <inertia-link v-if="editHref" :href="editHref" class="maiic-action maiic-action-edit" title="Edit">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/>
                <path d="m15 5 4 4"/>
            </svg>
        </inertia-link>
        <button v-if="deletable" type="button" class="maiic-action maiic-action-delete" title="Delete"
                @click="$emit('delete')">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 6h18"/>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>
                <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                <path d="M10 11v6M14 11v6"/>
            </svg>
        </button>
        <slot/>
    </div>
</template>

<script>
// Standard MAIIC row actions: green eye = view, gold pencil = edit,
// red bin = delete. Pass null/false to hide any of them.
export default {
    props: {
        viewHref: { type: String, default: null },
        editHref: { type: String, default: null },
        deletable: { type: Boolean, default: false },
    },
    emits: ['delete'],
}
</script>
