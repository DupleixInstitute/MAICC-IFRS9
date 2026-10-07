<script setup>
import { ref, computed, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Icon from '@/Components/Shell/Icon.vue';
import { topItems, navGroups, accentClasses } from '@/nav';

const props = defineProps({
    collapsed: { type: Boolean, default: false },
});

/* Role for adminOnly nav gating (mirrors the server-side role:Admin guard). */
const page = usePage();
const isAdmin = computed(() => (page.props.auth?.user?.role ?? '') === 'Admin');
/* Drop adminOnly items for non-admins so the link never 403s on click. */
function visibleItems(group) {
    return group.items.filter((it) => !it.adminOnly || isAdmin.value);
}

/* `route()` is a Ziggy global injected by @routes in the blade root. */
function routeExists(name) {
    try { route(name); return true; } catch (e) { return false; }
}
function isActive(name) {
    try { return route().current(name); } catch (e) { return false; }
}
function accent(group) {
    return accentClasses[group.accent] || accentClasses.indigo;
}

/* Which group contains the active page (drives auto-open + header highlight). */
const activeGroupId = computed(() => {
    const g = navGroups.find((grp) => grp.items.some((it) => isActive(it.route)));
    return g ? g.id : null;
});

/* Only one group open at a time; the active group opens by default. */
const openGroups = ref([]);
function isGroupOpen(id) { return openGroups.value.includes(id); }
function toggleGroup(id) {
    openGroups.value = isGroupOpen(id) ? [] : [id];
}
watch(activeGroupId, (id) => {
    if (id && !isGroupOpen(id)) openGroups.value = [id];
}, { immediate: true });
</script>

<template>
    <aside
        class="flex h-full shrink-0 flex-col border-r border-slate-800 transition-[width] duration-300 ease-in-out"
        :class="collapsed ? 'w-[68px]' : 'w-64'"
        style="background: linear-gradient(180deg, #0e2019 0%, #0a1913 55%, #06110c 100%);"
    >
        <!-- Brand -->
        <div
            class="flex h-16 shrink-0 items-center gap-3 border-b border-white/5 px-4"
            :class="collapsed ? 'justify-center px-0' : ''"
        >
            <div class="flex h-9 shrink-0 items-center justify-center rounded-lg bg-white px-1.5 shadow-lg shadow-black/20">
                <img src="/images/fdh-logo.webp" alt="FDH Bank" class="h-6 w-auto object-contain" />
            </div>
            <div v-if="!collapsed" class="flex min-w-0 flex-col leading-tight">
                <span class="truncate text-[13.5px] font-bold tracking-tight text-white">IFRS 9 ECL</span>
                <span class="truncate text-[10.5px] font-medium text-emerald-300/80">FDH Bank</span>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden px-2 py-3" style="scrollbar-width: thin;">
            <!-- Top-level links -->
            <Link
                v-for="item in topItems"
                :key="item.route"
                :href="route(item.route)"
                class="group relative my-0.5 flex items-center gap-3 rounded-xl px-3 py-2.5 text-[13.5px] font-medium transition-colors duration-150"
                :class="[
                    collapsed ? 'justify-center px-0' : '',
                    isActive(item.route)
                        ? 'bg-emerald-400/10 text-emerald-200'
                        : 'text-slate-400 hover:bg-white/5 hover:text-slate-100',
                ]"
                :title="collapsed ? item.label : ''"
            >
                <span
                    v-if="isActive(item.route) && !collapsed"
                    class="absolute left-0 top-1/2 h-5 w-[3px] -translate-y-1/2 rounded-r-full bg-emerald-400"
                />
                <Icon :name="item.icon" class="h-[18px] w-[18px] shrink-0" />
                <span v-if="!collapsed" class="truncate">{{ item.label }}</span>
            </Link>

            <div class="my-2 h-px bg-white/5" />

            <!-- Grouped navigation -->
            <div v-for="group in navGroups" :key="group.id" class="mb-0.5">
                <!-- Group header -->
                <button
                    type="button"
                    class="group relative flex w-full items-center gap-3 rounded-xl py-2 text-left transition-colors duration-150"
                    :class="[
                        collapsed ? 'justify-center px-0' : 'px-3',
                        (activeGroupId === group.id || isGroupOpen(group.id))
                            ? accent(group).text
                            : 'text-slate-300 hover:text-white',
                    ]"
                    :title="collapsed ? group.label : ''"
                    @click="toggleGroup(group.id)"
                >
                    <span
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg ring-1"
                        :class="accent(group).tile"
                    >
                        <Icon :name="group.icon" class="h-[16px] w-[16px]" />
                    </span>
                    <span
                        v-if="!collapsed"
                        class="flex-1 truncate text-[10.5px] font-bold uppercase tracking-[0.14em]"
                    >{{ group.label }}</span>
                    <Icon
                        v-if="!collapsed"
                        name="chevron"
                        class="h-3.5 w-3.5 shrink-0 transition-transform duration-200"
                        :class="isGroupOpen(group.id) ? 'rotate-180' : ''"
                    />
                </button>

                <!-- Group items -->
                <div v-show="isGroupOpen(group.id) && !collapsed" class="mt-0.5 space-y-0.5">
                    <template v-for="item in visibleItems(group)" :key="item.route">
                        <Link
                            v-if="routeExists(item.route)"
                            :href="route(item.route)"
                            class="group relative ml-3 flex items-center gap-3 rounded-lg px-3 py-2 text-[13px] font-medium transition-colors duration-150"
                            :class="isActive(item.route)
                                ? [accent(group).activeBg, accent(group).text]
                                : 'text-slate-400 hover:bg-white/5 hover:text-slate-200'"
                        >
                            <span
                                v-if="isActive(item.route)"
                                class="absolute left-0 top-1/2 h-4 w-[3px] -translate-y-1/2 rounded-r-full"
                                :class="accent(group).bar"
                            />
                            <Icon :name="item.icon" class="h-[15px] w-[15px] shrink-0" />
                            <span class="truncate">{{ item.label }}</span>
                        </Link>
                    </template>
                </div>

                <!-- Collapsed: flat icon stack -->
                <div v-if="collapsed" class="mt-0.5 flex flex-col items-center gap-0.5">
                    <Link
                        v-for="item in visibleItems(group)"
                        :key="item.route + '-c'"
                        :href="route(item.route)"
                        :title="item.label"
                        class="flex h-9 w-9 items-center justify-center rounded-lg transition-colors duration-150"
                        :class="isActive(item.route)
                            ? [accent(group).activeBg, accent(group).text]
                            : 'text-slate-500 hover:bg-white/5 hover:text-slate-200'"
                    >
                        <Icon :name="item.icon" class="h-[16px] w-[16px]" />
                    </Link>
                </div>
            </div>
        </nav>

        <!-- Footer -->
        <div class="shrink-0 border-t border-white/5 px-3 py-3">
            <p v-if="!collapsed" class="text-[10px] leading-relaxed text-slate-500">
                Dupleix Studio &middot; FDH Bank<br />IFRS 9 ECL Platform
            </p>
            <p v-else class="text-center text-[10px] text-slate-600">v1</p>
        </div>
    </aside>
</template>
