<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    periods: { type: Array, default: () => [] },
    period: { type: String, default: '' },
    tasks: { type: Array, default: () => [] },
    progress: { type: Object, default: () => ({ done: 0, total: 0, percent: 0 }) },
    outstanding: { type: Array, default: () => [] },
    me: { type: Object, default: () => ({}) },
    is_admin: { type: Boolean, default: false },
    messages: { type: Array, default: () => [] },
})

const activeTab = ref('checklist')
const tabs = computed(() => [
    { key: 'checklist', label: 'IFRS 9 close checklist', count: props.progress.done + '/' + props.progress.total },
    { key: 'messages', label: 'Team messages', count: props.messages.length },
])
const draft = ref('')


function sendMessage() {
    const body = draft.value.trim()
    if (!body) return
    router.post(route('workspace.message'),
        { period: props.period, body },
        { preserveScroll: true, onSuccess: () => { draft.value = '' } })
}

function changePeriod(e) {
    router.get(route('workspace.index'), { period: e.target.value }, { preserveScroll: true })
}

function toggle(t) {
    if (!props.is_admin || t.auto) return
    router.post(route('workspace.toggle'),
        { period: props.period, task_key: t.key },
        { preserveScroll: true })
}
</script>

<template>
    <AppLayout title="Workspace">
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Workspace</h2>
                <p class="mt-0.5 text-sm text-gray-500">The month-end IFRS 9 close for one reporting period: each step, who did it, and the team's notes</p>
            </div>
        </template>
        <template #actions>
            <label for="ws-period" class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Reporting period</label>
            <select id="ws-period" :value="period" class="maiic-select w-36" @change="changePeriod">
                <option v-for="p in periods" :key="p" :value="p">{{ p }}</option>
            </select>
        </template>

        <div class="w-full space-y-4">
            <!-- One strip of compact figures -->
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="maiic-kpi" style="--accent:#15803d">
                    <div class="maiic-kpi-label">Close progress, {{ period }}</div>
                    <div class="flex items-center gap-3">
                        <div class="maiic-kpi-value text-xl">{{ progress.percent }}%</div>
                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-maiic-500" :style="{ width: Math.max(0, Math.min(100, progress.percent || 0)) + '%' }"></div></div>
                    </div>
                </div>
                <div class="maiic-kpi" style="--accent:#0e7490"><div class="maiic-kpi-label">Steps done</div><div class="maiic-kpi-value text-xl">{{ progress.done }} of {{ progress.total }}</div></div>
                <div class="maiic-kpi" :style="{ '--accent': outstanding.length ? '#d97706' : '#15803d' }"><div class="maiic-kpi-label">Outstanding</div><div class="maiic-kpi-value text-xl" :title="outstanding.join(', ')">{{ outstanding.length }}</div></div>
                <div class="maiic-kpi" style="--accent:#6b7280">
                    <div class="maiic-kpi-label">Signed in as</div>
                    <div class="truncate text-base font-extrabold text-gray-900" :title="me.email">{{ me.name }}</div>
                    <div class="text-[11px] text-gray-500">{{ is_admin ? 'Administrator: can tick manual steps' : 'Read-only view' }}</div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-5 pt-4">
                    <nav class="flex gap-6 overflow-x-auto">
                        <button v-for="t in tabs" :key="t.key" type="button" class="whitespace-nowrap border-b-2 px-1 pb-3 text-sm font-semibold"
                                :class="activeTab === t.key ? 'border-maiic-600 text-maiic-700' : 'border-transparent text-gray-500 hover:text-gray-800'"
                                @click="activeTab = t.key">
                            {{ t.label }}
                            <span class="ml-1 rounded-full px-2 py-0.5 text-xs" :class="activeTab === t.key ? 'bg-maiic-100 text-maiic-800' : 'bg-gray-100 text-gray-600'">{{ t.count }}</span>
                        </button>
                    </nav>
                </div>
                <p v-if="activeTab === 'checklist'" class="border-b border-gray-200 px-5 py-2 text-xs text-gray-500">
                    Steps marked System are checked automatically from the data; an administrator ticks the others.
                    <span v-if="outstanding.length" class="font-semibold text-amber-700">Still to do: {{ outstanding.join(', ') }}.</span>
                    <span v-else class="font-semibold text-maiic-700">All steps complete for this period.</span>
                </p>

            <!-- ===================== CHECKLIST TAB ===================== -->
            <div v-if="activeTab === 'checklist'">
                <div v-for="(t, i) in tasks" :key="t.key"
                     class="flex items-center gap-4 border-b border-gray-100 px-5 py-4 last:border-0"
                     :class="t.status === 'done' ? 'bg-maiic-50/40' : ''">
                    <!-- step marker -->
                    <div class="flex h-9 w-9 flex-none items-center justify-center rounded-full border-2 text-sm font-extrabold"
                         :class="t.status === 'done' ? 'border-maiic-500 bg-maiic-500 text-white' : 'border-gray-300 bg-white text-gray-400'">
                        <svg v-if="t.status === 'done'" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7"/></svg>
                        <span v-else>{{ i + 1 }}</span>
                    </div>

                    <!-- label + detail -->
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold" :class="t.status === 'done' ? 'text-gray-500 line-through decoration-maiic-400/60' : 'text-gray-900'">
                            {{ t.label }}
                        </p>
                        <p v-if="t.detail" class="mt-0.5 text-xs text-gray-500">{{ t.detail }}</p>
                        <p v-else-if="t.completed_by && t.status === 'done'" class="mt-0.5 text-xs text-gray-400">
                            Ticked by {{ t.completed_by }}<span v-if="t.completed_at">, {{ t.completed_at }}</span>
                        </p>
                    </div>

                    <!-- badges + actions -->
                    <div class="flex flex-none items-center gap-2">
                        <span v-if="t.auto" class="maiic-badge maiic-badge-grey" title="Verified automatically from the database">
                            <svg class="mr-1 h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            System
                        </span>
                        <span class="maiic-badge" :class="t.status === 'done' ? 'maiic-badge-solid-green' : 'maiic-badge-gold'">
                            {{ t.status === 'done' ? 'Done' : 'Pending' }}
                        </span>
                        <a v-if="t.href" :href="t.href"
                           class="maiic-action maiic-action-view" title="Open this screen">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </a>
                        <button v-if="is_admin && !t.auto" @click="toggle(t)"
                                class="maiic-action" :class="t.status === 'done' ? 'maiic-action-neutral' : 'maiic-action-edit'"
                                :title="t.status === 'done' ? 'Reopen this step' : 'Mark as done'">
                            <svg v-if="t.status !== 'done'" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7"/></svg>
                            <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M21 3v5h-5"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ===================== MESSAGES TAB ===================== -->
            <div v-else class="flex min-h-[420px] flex-col">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                    <p class="text-sm font-bold text-gray-700">Team conversation</p>
                    <p class="text-xs text-gray-400">Period {{ period }}, visible to everyone signed in</p>
                </div>
                <div class="flex-1 space-y-3 overflow-y-auto px-5 py-4">
                    <p v-if="!messages.length" class="py-10 text-center text-sm text-gray-400">
                        No messages yet. Start the conversation for this period.
                    </p>
                    <div v-for="(m, i) in messages" :key="i" class="flex" :class="m.mine ? 'justify-end' : 'justify-start'">
                        <div class="max-w-[75%] rounded-2xl px-4 py-2.5 shadow-sm"
                             :class="m.mine ? 'rounded-br-sm bg-maiic-600 text-white' : 'rounded-bl-sm border border-gray-100 bg-gray-50 text-gray-800'">
                            <p class="text-[11px] font-bold" :class="m.mine ? 'text-maiic-100' : 'text-maiic-700'">{{ m.user_name }}</p>
                            <p class="whitespace-pre-line text-sm leading-relaxed">{{ m.body }}</p>
                            <p class="mt-1 text-right text-[10px]" :class="m.mine ? 'text-maiic-200' : 'text-gray-400'">{{ m.when }}</p>
                        </div>
                    </div>
                </div>
                <div class="flex items-end gap-2 border-t border-gray-100 px-5 py-3">
                    <textarea v-model="draft" rows="1" placeholder="Message the team about this period..."
                              @keydown.enter.exact.prevent="sendMessage"
                              class="maiic-input resize-none"></textarea>
                    <button @click="sendMessage" :disabled="!draft.trim()"
                            class="flex h-10 flex-none items-center gap-1.5 rounded-lg bg-maiic-600 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-maiic-700 disabled:opacity-40">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                        Send
                    </button>
                </div>
            </div>
            </div>
        </div>
    </AppLayout>
</template>
