<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Sidebar from '@/Components/Shell/Sidebar.vue';
import Topbar from '@/Components/Shell/Topbar.vue';
import Icon from '@/Components/Shell/Icon.vue';
import CollapsibleHelp from '@/Components/CollapsibleHelp.vue';
import ManualHelpButton from '@/Components/ManualHelpButton.vue';
import { topItems, navGroups } from '@/nav';
import { guideForComponent } from '@/manualGuides';

const props = defineProps({
    /* Optional overrides; when omitted the header is derived from the route. */
    title:       { type: String, default: '' },
    description: { type: String, default: '' },
    icon:        { type: String, default: '' },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const currentPeriod = computed(() => page.props.currentPeriod ?? null);

/* Per-page help guide (drives the auto collapsible panel + the floating Help button). */
const pageGuide = computed(() => guideForComponent(page.component));

/* Desktop icon-rail collapse + mobile off-canvas drawer. */
const collapsed = ref(false);
const mobileOpen = ref(false);
onMounted(() => {
    try { collapsed.value = localStorage.getItem('fdh.sidebar.collapsed') === '1'; } catch (e) { /* ignore */ }
});
function handleToggle() {
    const isDesktop = typeof window !== 'undefined' && window.matchMedia('(min-width: 768px)').matches;
    if (isDesktop) {
        collapsed.value = !collapsed.value;
        try { localStorage.setItem('fdh.sidebar.collapsed', collapsed.value ? '1' : '0'); } catch (e) { /* ignore */ }
    } else {
        mobileOpen.value = !mobileOpen.value;
    }
}

/* -- Global busy indicator ------------------------------------------------
 * A small floating pill that appears whenever an Inertia navigation or form
 * submit is in flight: an amber spinner ("Processing...") while the request is
 * running, then a brief green "Done" flash. Replaces Inertia's stock top
 * progress bar (disabled in app.js). Ported from the ZNBS/BBS busy indicator. */
const busyPhase = ref('idle'); // 'idle' | 'busy' | 'done'
let busyShowAfter = null;
let busyClearAfter = null;
function cancelBusyTimers() {
    if (busyShowAfter !== null) { clearTimeout(busyShowAfter); busyShowAfter = null; }
    if (busyClearAfter !== null) { clearTimeout(busyClearAfter); busyClearAfter = null; }
}
const offBusyStart = router.on('start', () => {
    cancelBusyTimers();
    // Small delay so instant navigations do not flash a pointless pill.
    busyShowAfter = setTimeout(() => { busyPhase.value = 'busy'; }, 120);
});
const offBusyFinish = router.on('finish', (event) => {
    if (busyShowAfter !== null) { clearTimeout(busyShowAfter); busyShowAfter = null; }
    if (event.detail.visit.completed) {
        busyPhase.value = 'done';
        busyClearAfter = setTimeout(() => { busyPhase.value = 'idle'; }, 700);
    } else {
        busyPhase.value = 'idle';
    }
});
onBeforeUnmount(() => {
    cancelBusyTimers();
    offBusyStart();
    offBusyFinish();
});

function isActive(name) {
    try { return route().current(name); } catch (e) { return false; }
}

/* Derive the page header (icon + title + breadcrumb) from the active route. */
const derived = computed(() => {
    for (const it of topItems) {
        if (isActive(it.route)) {
            return { icon: it.icon, title: it.label, crumbs: [it.label] };
        }
    }
    for (const g of navGroups) {
        for (const it of g.items) {
            if (isActive(it.route)) {
                return { icon: it.icon, title: it.label, crumbs: [g.label, it.label] };
            }
        }
    }
    return { icon: 'dashboard', title: props.title || 'FDH IFRS 9 ECL', crumbs: [props.title || 'FDH IFRS 9 ECL'] };
});

const headerTitle = computed(() => props.title || derived.value.title);
const headerIcon = computed(() => props.icon || derived.value.icon);
const crumbs = computed(() => derived.value.crumbs);
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-gray-50 font-sans text-gray-900 dark:bg-slate-950 dark:text-slate-100">
        <!-- Sidebar: always visible on the app shell (collapse to an icon-rail via the hamburger) -->
        <div class="block">
            <Sidebar :collapsed="collapsed" />
        </div>

        <!-- Mobile drawer -->
        <div v-if="mobileOpen" class="fixed inset-0 z-40 md:hidden">
            <div class="absolute inset-0 bg-slate-950/60" @click="mobileOpen = false" />
            <div class="absolute inset-y-0 left-0">
                <Sidebar :collapsed="false" />
            </div>
        </div>

        <!-- Main column -->
        <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
            <Topbar
                :user="user"
                :current-period="currentPeriod"
                @toggle-sidebar="handleToggle"
            />

            <main class="min-h-0 flex-1 overflow-y-auto">
                <!-- Page header: icon + title + breadcrumb -->
                <div class="border-b border-gray-200 bg-white px-6 py-5 dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-start gap-4">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 ring-1 ring-emerald-100 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20">
                            <Icon :name="headerIcon" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <nav class="flex flex-wrap items-center gap-1.5 text-xs text-gray-400 dark:text-slate-500">
                                <template v-for="(c, i) in crumbs" :key="i">
                                    <span :class="i === crumbs.length - 1 ? 'font-medium text-gray-600 dark:text-slate-300' : ''">{{ c }}</span>
                                    <Icon v-if="i < crumbs.length - 1" name="chevron" class="h-3 w-3 -rotate-90" />
                                </template>
                            </nav>
                            <h1 class="mt-1 text-xl font-semibold tracking-tight text-gray-900 dark:text-slate-100">{{ headerTitle }}</h1>
                            <p v-if="description" class="mt-1 text-sm text-gray-500 dark:text-slate-400">{{ description }}</p>
                        </div>
                        <div class="ml-auto hidden items-center gap-2 sm:flex">
                            <slot name="actions" />
                        </div>
                    </div>
                </div>

                <!-- Page content -->
                <div class="p-6">
                    <!-- Auto per-page instructions (ZNBS-style), driven by manualGuides -->
                    <CollapsibleHelp v-if="pageGuide" :title="pageGuide.title" class="mb-6">
                        <p v-if="pageGuide.intro" class="mb-2">{{ pageGuide.intro }}</p>
                        <ol v-if="pageGuide.steps && pageGuide.steps.length" class="ml-4 list-decimal space-y-1.5">
                            <li v-for="(s, i) in pageGuide.steps" :key="i">{{ s }}</li>
                        </ol>
                    </CollapsibleHelp>

                    <slot />
                </div>
            </main>
        </div>

        <!-- Global busy indicator: floating "Processing" pill (replaces the stock Inertia top bar) -->
        <Transition
            enter-from-class="opacity-0 -translate-y-2"
            enter-active-class="transition duration-200 ease-out"
            enter-to-class="opacity-100 translate-y-0"
            leave-from-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-300 ease-in"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div
                v-if="busyPhase !== 'idle'"
                class="pointer-events-none fixed left-1/2 top-24 z-[60] flex -translate-x-1/2 items-center gap-2.5 rounded-full border px-5 py-2.5 text-[13px] font-bold uppercase tracking-[0.12em] shadow-2xl"
                :class="busyPhase === 'busy'
                    ? 'border-amber-300 bg-amber-400 text-slate-950 shadow-amber-500/40'
                    : 'border-emerald-300 bg-emerald-500 text-white shadow-emerald-500/40'"
                role="status"
                aria-live="polite"
            >
                <template v-if="busyPhase === 'busy'">
                    <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity="0.3" stroke-width="3" />
                        <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                    </svg>
                    <span>Processing...</span>
                </template>
                <template v-else>
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M5 12.5l4.5 4.5L19 7" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <span>Done</span>
                </template>
            </div>
        </Transition>

        <!-- Global floating "Help" button (per-page guide from manualGuides) -->
        <ManualHelpButton />
    </div>
</template>
