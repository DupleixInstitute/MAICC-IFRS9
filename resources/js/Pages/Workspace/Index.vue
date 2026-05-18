<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    periods: { type: Array, default: () => [] },
    period: { type: String, default: '' },
    tasks: { type: Array, default: () => [] },
    progress: { type: Object, default: () => ({ done: 0, total: 0, percent: 0 }) },
    me: { type: Object, default: () => ({}) },
    is_admin: { type: Boolean, default: false },
    messages: { type: Array, default: () => [] },
})

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
    if (!props.is_admin) return
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
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Reporting Period</label>
                    <select :value="period" @change="changePeriod"
                            class="rounded-lg border-gray-300 text-sm py-2 px-3 shadow-sm focus:ring-maiic-500 focus:border-maiic-500">
                        <option v-for="p in periods" :key="p" :value="p">{{ p }}</option>
                    </select>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="w-full px-4 sm:px-6 lg:px-10 space-y-6">

                <!-- Who am I + progress -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <p class="text-xs uppercase tracking-wider text-gray-400">Signed in as</p>
                        <p class="text-lg font-bold text-gray-900 mt-1">{{ me.name || '—' }}</p>
                        <p class="text-sm text-gray-500">{{ me.email }}</p>
                        <span :class="['inline-block mt-3 text-xs px-2 py-1 rounded-full',
                                       is_admin ? 'bg-maiic-100 text-maiic-800' : 'bg-gray-100 text-gray-600']">
                            {{ is_admin ? 'Administrator — can update tasks' : 'Read-only view' }}
                        </span>
                    </div>
                    <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-semibold text-gray-900">Close progress — {{ period }}</h3>
                            <span class="text-sm font-semibold text-maiic-700">
                                {{ progress.done }} / {{ progress.total }} ({{ progress.percent }}%)
                            </span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-3 overflow-hidden">
                            <div class="bg-maiic-600 h-3 transition-all" :style="{ width: progress.percent + '%' }"></div>
                        </div>
                        <p v-if="progress.percent === 100" class="text-sm text-maiic-700 mt-3 font-medium">
                            ✓ All steps complete for {{ period }}.
                        </p>
                    </div>
                </div>

                <!-- Checklist -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-l-4 border-maiic-600">
                        <h3 class="font-semibold text-gray-900">IFRS 9 Close Checklist</h3>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        <li v-for="(t, i) in tasks" :key="t.key"
                            class="flex items-center gap-4 px-6 py-4" :class="i % 2 ? 'bg-maiic-50/30' : ''">
                            <button @click="toggle(t)" :disabled="!is_admin"
                                    :class="['flex-shrink-0 w-7 h-7 rounded-full border-2 flex items-center justify-center transition',
                                             t.status === 'done'
                                                ? 'bg-maiic-600 border-maiic-600 text-white'
                                                : 'border-gray-300 text-transparent',
                                             is_admin ? 'cursor-pointer hover:border-maiic-500' : 'cursor-default']">
                                ✓
                            </button>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-gray-900">
                                    <span class="text-gray-400 mr-2">{{ i + 1 }}.</span>{{ t.label }}
                                </p>
                                <p v-if="t.status === 'done' && t.completed_by" class="text-xs text-gray-500 mt-0.5">
                                    Done by {{ t.completed_by }}<span v-if="t.completed_at"> · {{ t.completed_at }}</span>
                                </p>
                            </div>
                            <a v-if="t.href" :href="t.href"
                               class="text-sm text-maiic-600 hover:underline whitespace-nowrap">Open →</a>
                            <span :class="['text-xs px-2 py-1 rounded-full whitespace-nowrap',
                                           t.status === 'done' ? 'bg-maiic-100 text-maiic-800' : 'bg-amber-100 text-amber-800']">
                                {{ t.status === 'done' ? 'Done' : 'Pending' }}
                            </span>
                        </li>
                    </ul>
                </div>

                <p v-if="!is_admin" class="text-xs text-gray-400">
                    You are viewing the workspace in read-only mode. Ask an administrator to mark steps complete.
                </p>

                <!-- Team message board -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-l-4 border-maiic-600 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-900">Team Messages</h3>
                        <span class="text-xs text-gray-400">Period {{ period }} · visible to everyone signed in</span>
                    </div>

                    <div class="max-h-80 overflow-y-auto px-6 py-4 space-y-3 bg-gray-50/50">
                        <p v-if="!messages.length" class="text-sm text-gray-400 italic text-center py-6">
                            No messages yet. Start the conversation for this period.
                        </p>
                        <div v-for="(m, i) in messages" :key="i"
                             :class="['flex', m.mine ? 'justify-end' : 'justify-start']">
                            <div :class="['max-w-lg rounded-2xl px-4 py-2 shadow-sm',
                                          m.mine ? 'bg-maiic-600 text-white' : 'bg-white border border-gray-100 text-gray-800']">
                                <p :class="['text-xs font-semibold mb-0.5', m.mine ? 'text-white/80' : 'text-maiic-700']">
                                    {{ m.mine ? 'You' : m.user_name }}
                                    <span :class="['font-normal ml-2', m.mine ? 'text-white/60' : 'text-gray-400']">{{ m.when }}</span>
                                </p>
                                <p class="text-sm whitespace-pre-wrap break-words">{{ m.body }}</p>
                            </div>
                        </div>
                    </div>

                    <form @submit.prevent="sendMessage" class="flex items-center gap-3 border-t border-gray-100 p-4">
                        <input v-model="draft" type="text" maxlength="2000"
                               placeholder="Message the team about this period…"
                               class="flex-1 rounded-lg border-gray-300 text-sm py-2.5 px-3 focus:ring-maiic-500 focus:border-maiic-500"/>
                        <button type="submit" :disabled="!draft.trim()"
                                class="px-5 py-2.5 bg-maiic-600 hover:bg-maiic-700 text-white text-sm font-medium rounded-lg shadow disabled:opacity-50">
                            Send
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
