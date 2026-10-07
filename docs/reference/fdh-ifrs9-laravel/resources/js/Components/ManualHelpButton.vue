<script setup>
/*
 * Global floating "Help" button (ported from the ZNBS/BBS ManualHelpButton).
 *
 * A fixed FAB (bottom-right) that opens a small modal with the "how to use this
 * page" guide for the CURRENT Inertia page, read from the manualGuides registry,
 * plus a link into the full User Manual (graceful if that route is absent).
 * Hidden on the manual pages themselves. FDH emerald branding, light/dark aware.
 */
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { guideForComponent } from '@/manualGuides';

const page = usePage();
const open = ref(false);

const component = computed(() => page.component || '');
const guide = computed(() => guideForComponent(component.value));
// Never show the helper on the manual pages themselves.
const isManualPage = computed(() => /Manual/i.test(component.value));

/* Deep-link into the full manual (+ section anchor) when the route exists. */
const manualHref = computed(() => {
    try {
        const base = route('system.manual');
        return guide.value?.section ? base + '#' + guide.value.section : base;
    } catch (e) {
        return null;
    }
});

function goManual() {
    const url = manualHref.value;
    open.value = false;
    if (url) router.visit(url);
}
function onKey(e) { if (e.key === 'Escape') open.value = false; }
onMounted(() => document.addEventListener('keydown', onKey));
onUnmounted(() => document.removeEventListener('keydown', onKey));
</script>

<template>
    <div v-if="!isManualPage">
        <!-- Floating trigger -->
        <button
            type="button"
            class="fixed bottom-5 right-5 z-[55] inline-flex items-center gap-2 rounded-full bg-gradient-to-br from-emerald-500 to-green-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-emerald-900/30 transition-transform hover:-translate-y-0.5 hover:shadow-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400"
            title="How to use this page"
            aria-label="How to use this page"
            @click="open = true"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20" />
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" />
                <path d="M9 7h7M9 11h7" />
            </svg>
            <span>Help</span>
        </button>

        <Teleport to="body">
            <Transition
                enter-from-class="opacity-0"
                enter-active-class="transition duration-150 ease-out"
                enter-to-class="opacity-100"
                leave-from-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="open"
                    class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm"
                    @click.self="open = false"
                >
                    <div class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-slate-900" role="dialog" aria-modal="true" aria-label="How to use this page">
                        <!-- Header -->
                        <div class="flex items-center gap-3 bg-gradient-to-br from-emerald-600 to-green-700 px-5 py-4 text-white">
                            <span class="inline-flex items-center gap-1.5 rounded-md bg-white/15 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider ring-1 ring-white/20">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z" />
                                </svg>
                                Manual
                            </span>
                            <h2 class="flex-1 truncate text-base font-bold">{{ guide?.title || 'How to use this page' }}</h2>
                            <button class="shrink-0 text-white/80 transition-colors hover:text-white" aria-label="Close" @click="open = false">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M6 6l12 12M18 6L6 18" />
                                </svg>
                            </button>
                        </div>

                        <!-- Body -->
                        <div class="max-h-[60vh] overflow-y-auto px-5 py-4">
                            <template v-if="guide && guide.steps && guide.steps.length">
                                <p v-if="guide.intro" class="mb-3 text-sm text-gray-600 dark:text-slate-300">{{ guide.intro }}</p>
                                <ol class="space-y-2.5">
                                    <li v-for="(s, i) in guide.steps" :key="i" class="flex gap-3 text-sm leading-relaxed text-gray-700 dark:text-slate-200">
                                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-[11px] font-bold text-white">{{ i + 1 }}</span>
                                        <span>{{ s }}</span>
                                    </li>
                                </ol>
                            </template>
                            <p v-else class="text-sm text-gray-600 dark:text-slate-300">
                                Open the full manual for step-by-step instructions for this page.
                            </p>
                        </div>

                        <!-- Footer -->
                        <div class="flex items-center justify-end gap-2 border-t border-gray-100 bg-gray-50 px-5 py-3 dark:border-slate-800 dark:bg-slate-950/50">
                            <button
                                v-if="manualHref"
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-sm font-semibold text-white transition-colors hover:bg-emerald-500"
                                @click="goManual"
                            >
                                Open the full manual
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M5 12h14M13 6l6 6-6 6" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                class="rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                                @click="open = false"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
