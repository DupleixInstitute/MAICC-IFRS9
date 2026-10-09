<script setup>
import { Link } from '@inertiajs/vue3'
import ReportIcon from './ReportIcon.vue'

// The one way back to the Reports hub from any report page. `tab` opens the
// hub on the report's own group.
const props = defineProps({ tab: { type: String, default: '' } })
</script>

<template>
    <Link :href="route('ifrs9-reports.index', props.tab ? { tab: props.tab } : {})"
          class="mb-1 inline-flex items-center gap-1.5 text-xs font-semibold text-maiic-700 hover:text-maiic-900 hover:underline">
        <ReportIcon name="back" class="h-3.5 w-3.5"/> Back to reports
    </Link>
</template>
