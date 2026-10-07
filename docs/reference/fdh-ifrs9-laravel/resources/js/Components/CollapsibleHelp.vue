<script setup>
/*
 * Reusable collapsible "How this works" instructions panel.
 *
 * Ported from the ZNBS/BBS CollapsibleHelp: a header button toggles a smooth
 * height transition on the body slot, with a rotating chevron. Defaults to
 * collapsed so explanatory copy never crowds the page. Light- and dark-mode
 * aware, FDH-emerald accented. Usage:
 *
 *   <CollapsibleHelp title="What is Bulk Import?">
 *     ...instructions markup...
 *   </CollapsibleHelp>
 */
import { ref } from 'vue';

const props = defineProps({
    title: { type: String, default: 'How this works' },
    defaultOpen: { type: Boolean, default: false },
});

const open = ref(props.defaultOpen);
function toggle() { open.value = !open.value; }

// Stable-per-instance id for aria-controls (avoids a Vue-version dependency on useId).
let _seq = 0;
const panelId = 'collapsible-help-' + (++_seq) + '-' + Math.floor(Date.now() % 100000);
</script>

<template>
    <div class="overflow-hidden rounded-xl border border-emerald-200 bg-emerald-50/60 shadow-sm dark:border-emerald-500/25 dark:bg-emerald-500/[0.06]">
        <button
            type="button"
            class="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-emerald-100/50 focus:outline-none focus-visible:ring-1 focus-visible:ring-emerald-400/60 dark:hover:bg-white/[0.04]"
            :aria-expanded="open"
            :aria-controls="panelId"
            @click="toggle"
        >
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-600 ring-1 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/25">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M9 18h6" />
                    <path d="M10 22h4" />
                    <path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.1V17h6v-2.2c0-.8.4-1.6 1-2.1A7 7 0 0 0 12 2z" />
                </svg>
            </span>
            <span class="flex-1 text-xs font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-200">
                <slot name="title">{{ title }}</slot>
            </span>
            <svg
                class="h-4 w-4 shrink-0 text-gray-400 transition-transform duration-300 ease-in-out dark:text-slate-300"
                :class="open ? 'rotate-180' : ''"
                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
            >
                <polyline points="6 9 12 15 18 9" />
            </svg>
        </button>
        <div
            :id="panelId"
            class="grid transition-[grid-template-rows] duration-300 ease-in-out"
            :style="{ gridTemplateRows: open ? '1fr' : '0fr' }"
        >
            <div class="overflow-hidden">
                <div class="border-t border-emerald-200/70 px-4 pb-4 pt-3 text-xs leading-relaxed text-gray-600 dark:border-emerald-500/10 dark:text-slate-300">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
