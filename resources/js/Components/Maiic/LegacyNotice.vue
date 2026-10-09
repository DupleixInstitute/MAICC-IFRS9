<template>
    <!-- One line on every Legacy FLI tools screen: these screens are kept for reference and do not reach the ECL. -->
    <div class="flex items-center gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
        <svg class="h-4 w-4 flex-none" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
        <span><span class="font-semibold">Legacy tool, not used by the ECL:</span> the forward-looking adjustment is set on
            <Link :href="route('fli-adjustments.index')" class="font-semibold underline hover:text-amber-900">Regression (FLI Adjustments)</Link> and
            <Link :href="route('scenario-sets.index')" class="font-semibold underline hover:text-amber-900">Scenario Sets</Link>.</span>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
</script>
