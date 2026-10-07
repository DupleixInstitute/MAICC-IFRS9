<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import Icon from '@/Components/Shell/Icon.vue';
import { useTheme } from '@/composables/useTheme';

const props = defineProps({
    user:          { type: Object, default: null },
    currentPeriod: { type: Object, default: null },
});

defineEmits(['toggle-sidebar']);

/* Light / dark toggle (shared singleton; persists to localStorage). */
const { isDark, toggle: toggleTheme } = useTheme();

/* In-app notification unread count (shared prop from HandleInertiaRequests). */
const page = usePage();
const notifUnread = computed(() => page.props.notifications?.unread ?? 0);

/* Format a YYYYMM period code into "Mon YYYY". Pure display of a real prop. */
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const periodLabel = computed(() => {
    const code = props.currentPeriod?.reporting_period;
    if (!code || String(code).length < 6) return null;
    const s = String(code);
    const y = s.slice(0, 4);
    const m = parseInt(s.slice(4, 6), 10);
    if (!m || m < 1 || m > 12) return null;
    return `${MONTHS[m - 1]} ${y}`;
});
const periodOpen = computed(() => props.currentPeriod?.open_status === 'Open');

const roleLabel = computed(() => props.user?.role || 'User');
const userInitials = computed(() => {
    const name = props.user?.name || '';
    return name.split(' ').map((w) => w[0]).slice(0, 2).join('').toUpperCase() || '?';
});
</script>

<template>
    <header class="flex h-16 shrink-0 items-center gap-3 border-b border-gray-200 bg-white px-4 dark:border-slate-800 dark:bg-slate-900">
        <!-- Sidebar toggle -->
        <button
            type="button"
            class="flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
            title="Toggle navigation"
            @click="$emit('toggle-sidebar')"
        >
            <Icon name="menu" class="h-5 w-5" />
        </button>

        <!-- FDH brand -->
        <div class="flex min-w-0 items-center gap-2.5">
            <img src="/images/fdh-logo.webp" alt="FDH Bank" class="h-8 w-auto shrink-0 object-contain" />
            <span class="truncate border-l border-gray-200 pl-2.5 text-sm font-semibold text-gray-900 dark:border-slate-700 dark:text-slate-100">IFRS 9 ECL</span>
        </div>

        <div class="ml-auto flex items-center gap-2 sm:gap-3">
            <!-- Reporting-period badge -->
            <div
                v-if="periodLabel"
                class="hidden items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-medium sm:inline-flex"
                :class="periodOpen
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300'
                    : 'border-gray-200 bg-gray-50 text-gray-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'"
            >
                <Icon name="period" class="h-3.5 w-3.5" />
                <span>{{ periodLabel }}</span>
                <span class="rounded-full px-1.5 py-0.5 text-[10px] uppercase tracking-wide"
                    :class="periodOpen
                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300'
                        : 'bg-gray-200 text-gray-600 dark:bg-slate-700 dark:text-slate-300'">
                    {{ currentPeriod?.open_status || 'Open' }}
                </span>
            </div>
            <div
                v-else
                class="hidden items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300 sm:inline-flex"
            >
                <Icon name="alert" class="h-3.5 w-3.5" />
                <span>No ECL yet</span>
            </div>

            <!-- Notifications bell -->
            <Link
                :href="route('workspace.index', { tab: 'notifications' })"
                class="relative flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                title="Notifications"
                aria-label="Notifications"
            >
                <Icon name="bell" class="h-5 w-5" />
                <span
                    v-if="notifUnread > 0"
                    class="absolute -right-0.5 -top-0.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold leading-none text-white"
                >{{ notifUnread > 99 ? '99+' : notifUnread }}</span>
            </Link>

            <!-- Light / dark toggle -->
            <button
                type="button"
                class="flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                :title="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
                :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
                @click="toggleTheme"
            >
                <Icon :name="isDark ? 'sun' : 'moon'" class="h-5 w-5" />
            </button>

            <!-- Role badge -->
            <span class="hidden rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300 sm:inline-flex">
                {{ roleLabel }}
            </span>

            <!-- User menu -->
            <Dropdown align="right" width="48">
                <template #trigger>
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-full py-1 pl-1 pr-2 text-sm text-gray-600 transition-colors hover:bg-gray-100 dark:text-slate-300 dark:hover:bg-slate-800"
                    >
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-emerald-500 to-green-600 text-xs font-bold text-white">
                            {{ userInitials }}
                        </span>
                        <span class="hidden max-w-[120px] truncate font-medium md:inline">{{ user?.name || 'Account' }}</span>
                        <Icon name="chevron" class="h-4 w-4" />
                    </button>
                </template>
                <template #content>
                    <div class="border-b border-gray-100 px-4 py-3 dark:border-slate-700">
                        <p class="truncate text-sm font-semibold text-gray-900 dark:text-slate-100">{{ user?.name || '-' }}</p>
                        <p class="truncate text-xs text-gray-500 dark:text-slate-400">{{ user?.email || '' }}</p>
                    </div>
                    <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                    <DropdownLink :href="route('logout')" method="post" as="button">Log Out</DropdownLink>
                </template>
            </Dropdown>
        </div>
    </header>
</template>
