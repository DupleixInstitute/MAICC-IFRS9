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
const draft = ref('')

const ringStyle = computed(() => {
    const pct = Math.max(0, Math.min(100, props.progress.percent || 0))
    return {
        background: `conic-gradient(#16a34a ${pct * 3.6}deg, #e5e7eb ${pct * 3.6}deg)`,
    }
})

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
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">IFRS 9 Period Workspace</h2>
                <!-- reporting period pill, matching the dashboard filter bar -->
                <div class="flex items-end gap-3 rounded-xl bg-maiic-600 px-4 py-2.5 shadow-md">
                    <div>
                        <label class="mb-0.5 block text-[10px] font-bold uppercase tracking-widest text-white/80">
                            Reporting Period
                        </label>
                        <span class="relative inline-block">
                        <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-maiic-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        <select :value="period" @change="changePeriod"
                                class="cursor-pointer rounded-lg border-0 bg-white py-1.5 pl-9 pr-8 text-sm font-bold text-maiic-800 shadow focus:ring-2 focus:ring-white">
                            <option v-for="p in periods" :key="p" :value="p">{{ p }}</option>
                        </select>
                        </span>
                    </div>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-6xl">

            <!-- hero: who + progress ring + outstanding nag -->
            <div class="mb-5 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="maiic-panel p-5">
                    <p class="maiic-kpi-label">Signed in as</p>
                    <p class="text-lg font-extrabold text-gray-900">{{ me.name }}</p>
                    <p class="text-sm text-gray-500">{{ me.email }}</p>
                    <span class="maiic-badge mt-3" :class="is_admin ? 'maiic-badge-green' : 'maiic-badge-grey'">
                        {{ is_admin ? 'Administrator: can tick manual steps' : 'Read-only view' }}
                    </span>
                </div>

                <div class="maiic-panel flex items-center gap-5 p-5 lg:col-span-2">
                    <div class="relative h-24 w-24 flex-none rounded-full" :style="ringStyle">
                        <div class="absolute inset-2 flex flex-col items-center justify-center rounded-full bg-white">
                            <span class="text-xl font-extrabold text-gray-900">{{ progress.percent }}%</span>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">complete</span>
                        </div>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-bold text-gray-900">Close progress for {{ period }}</p>
                        <p class="text-sm text-gray-500">{{ progress.done }} of {{ progress.total }} steps complete.
                            System-verified steps update automatically from the data.</p>
                        <div v-if="outstanding.length" class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
                            <p class="text-xs font-bold uppercase tracking-wider text-amber-800">
                                {{ outstanding.length }} step(s) outstanding
                            </p>
                            <p class="mt-0.5 truncate text-xs text-amber-700">{{ outstanding.join(' · ') }}</p>
                        </div>
                        <div v-else class="mt-3 rounded-lg border border-maiic-200 bg-maiic-50 px-3 py-2">
                            <p class="text-xs font-bold uppercase tracking-wider text-maiic-800">
                                All steps complete for this period
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- tabs -->
            <div class="mb-4 flex gap-1 border-b-2 border-gray-200">
                <button @click="activeTab = 'checklist'"
                        class="-mb-0.5 border-b-[3px] px-4 py-2.5 text-sm font-bold transition"
                        :class="activeTab === 'checklist' ? 'border-maiicgold-400 text-maiic-800' : 'border-transparent text-gray-400 hover:text-gray-600'">
                    IFRS 9 Close Checklist
                    <span class="ml-1.5 rounded-full px-2 py-0.5 text-[11px] font-extrabold"
                          :class="activeTab === 'checklist' ? 'bg-maiic-100 text-maiic-800' : 'bg-gray-100 text-gray-500'">
                        {{ progress.done }}/{{ progress.total }}
                    </span>
                </button>
                <button @click="activeTab = 'messages'"
                        class="-mb-0.5 border-b-[3px] px-4 py-2.5 text-sm font-bold transition"
                        :class="activeTab === 'messages' ? 'border-maiicgold-400 text-maiic-800' : 'border-transparent text-gray-400 hover:text-gray-600'">
                    Team Messages
                    <span class="ml-1.5 rounded-full px-2 py-0.5 text-[11px] font-extrabold"
                          :class="activeTab === 'messages' ? 'bg-maiic-100 text-maiic-800' : 'bg-gray-100 text-gray-500'">
                        {{ messages.length }}
                    </span>
                </button>
            </div>

            <!-- ===================== CHECKLIST TAB ===================== -->
            <div v-if="activeTab === 'checklist'" class="maiic-panel">
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
                            Ticked by {{ t.completed_by }}<span v-if="t.completed_at"> · {{ t.completed_at }}</span>
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
            <div v-else class="maiic-panel flex min-h-[420px] flex-col">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                    <p class="text-sm font-bold text-gray-700">Team conversation</p>
                    <p class="text-xs text-gray-400">Period {{ period }} · visible to everyone signed in</p>
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
    </AppLayout>
</template>
