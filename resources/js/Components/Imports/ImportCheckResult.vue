<template>
    <!-- The row-by-row result of an import check (App\Support\Imports\ImportSpecValidator):
         refused files list every problem with its row, column, value and the fix.
         Works in light and dark mode. -->
    <div v-if="check" class="overflow-hidden rounded-lg border" :class="tone.border" role="status">
        <div class="flex flex-wrap items-start justify-between gap-3 px-4 py-3" :class="tone.head">
            <div class="flex min-w-0 items-start gap-2">
                <span class="mt-0.5 flex h-6 w-6 flex-none items-center justify-center rounded-full text-xs font-bold" :class="tone.chip">{{ tone.icon }}</span>
                <div class="min-w-0">
                    <div class="text-sm font-semibold" :class="tone.title">{{ heading }}</div>
                    <p class="mt-0.5 text-xs text-gray-600 dark:text-slate-300">
                        <span v-if="check.file" class="font-medium">{{ check.file }}</span>
                        <span v-if="check.file"> - </span>{{ Number(check.rows || 0).toLocaleString() }} rows read,
                        {{ check.error_count }} error{{ check.error_count === 1 ? '' : 's' }},
                        {{ check.warning_count }} warning{{ check.warning_count === 1 ? '' : 's' }}.
                        <template v-if="!check.ok && !checkOnly">Nothing was saved.</template><template v-else-if="checkOnly">Check only: nothing was saved.</template>
                    </p>
                    <p v-if="check.ignored_columns && check.ignored_columns.length" class="mt-0.5 text-xs text-gray-500 dark:text-slate-400">
                        Columns not read: {{ check.ignored_columns.join(', ') }}
                    </p>
                </div>
            </div>
            <button v-if="dismissible" type="button" class="rounded-md px-2 py-1 text-xs font-semibold text-gray-500 hover:bg-black/5 hover:text-gray-800 dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-slate-100" @click="$emit('dismiss')">Close</button>
        </div>

        <div v-if="check.issues && check.issues.length" class="max-h-80 overflow-auto border-t border-gray-200 dark:border-slate-700">
            <table class="w-full text-left text-xs">
                <thead class="sticky top-0 bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500 dark:bg-slate-900 dark:text-slate-400">
                    <tr>
                        <th class="px-3 py-2 font-semibold">Row</th>
                        <th class="px-3 py-2 font-semibold">Column</th>
                        <th class="px-3 py-2 font-semibold">Problem</th>
                        <th class="px-3 py-2 font-semibold">How to fix it</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-700/70">
                    <tr v-for="(issue, i) in pagedIssues" :key="(page - 1) * 15 + i" class="bg-white dark:bg-slate-800">
                        <td class="whitespace-nowrap px-3 py-2 align-top font-mono text-gray-600 dark:text-slate-300">{{ issue.row || '-' }}</td>
                        <td class="px-3 py-2 align-top">
                            <span class="font-medium text-gray-800 dark:text-slate-100">{{ issue.column || '-' }}</span>
                        </td>
                        <td class="px-3 py-2 align-top text-gray-800 dark:text-slate-100">
                            <span class="mr-1 inline-block rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase"
                                  :class="issue.severity === 'warning' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200' : 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200'">{{ issue.severity === 'warning' ? 'Warning' : 'Error' }}</span>
                            {{ issue.message }}
                        </td>
                        <td class="px-3 py-2 align-top text-gray-600 dark:text-slate-300">{{ issue.fix || '-' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <RowPager v-if="check.issues && check.issues.length" v-model="page" :total="check.issues.length" class="border-t border-gray-200 dark:border-slate-700" />
        <p v-if="check.truncated" class="border-t border-gray-200 px-4 py-2 text-xs text-gray-500 dark:border-slate-700 dark:text-slate-400">
            The first {{ check.issues.length }} problems are listed. Fix these and check the file again to see the rest.
        </p>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import RowPager from '@/Components/Maiic/RowPager.vue'

const props = defineProps({
    check: { type: Object, default: null },
    dismissible: { type: Boolean, default: true },
    // a Check file result (nothing was to be saved) rather than a refused import
    checkOnly: { type: Boolean, default: false },
})
defineEmits(['dismiss'])

// fifteen problems a page; a new check starts at the first
const page = ref(1)
watch(() => props.check, () => { page.value = 1 })
const pagedIssues = computed(() => (props.check?.issues || []).slice((page.value - 1) * 15, page.value * 15))

const tone = computed(() => {
    if (!props.check) return {}
    if (!props.check.ok) {
        return { border: 'border-red-200 dark:border-red-800/60', head: 'bg-red-50 dark:bg-red-950/40', chip: 'bg-red-600 text-white', title: 'text-red-800 dark:text-red-200', icon: '!' }
    }
    if (props.check.warning_count) {
        return { border: 'border-amber-200 dark:border-amber-800/60', head: 'bg-amber-50 dark:bg-amber-950/30', chip: 'bg-amber-500 text-white', title: 'text-amber-800 dark:text-amber-200', icon: '!' }
    }
    return { border: 'border-maiic-200 dark:border-maiic-800/60', head: 'bg-maiic-50 dark:bg-maiic-900/30', chip: 'bg-maiic-600 text-white', title: 'text-maiic-800 dark:text-maiic-200', icon: String.fromCharCode(10003) }
})

const heading = computed(() => {
    const c = props.check
    if (!c) return ''
    if (!c.ok) return props.checkOnly ? 'The file has problems: fix the rows below before importing' : 'The file was refused: fix the rows below and choose it again'
    if (c.warning_count) return 'The file passed the check, with warnings'
    return 'The file passed the check'
})
</script>
