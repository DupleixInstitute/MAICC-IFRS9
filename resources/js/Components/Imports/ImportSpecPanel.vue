<template>
    <!-- The instructions panel of every import (App\Support\Imports\ImportSpecs):
         the steps, the column table (name, type, required, format and allowed
         values, description, example), the Download sample file and column guide
         buttons, an optional Check file button, and the row-by-row result of the
         last check or refused import. The actions sit right-aligned directly
         above the table. The steps carry an X (UI rule of 15 October 2026: every
         notice can be closed): closed stays closed in this browser until the
         steps change, and a link brings them back. The buttons and the column
         table stay. Light and dark mode. -->
    <section class="rounded-lg border border-maiic-100 bg-maiic-50/50 text-sm text-gray-700 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-200">
        <div v-if="loadError" class="px-4 py-3 text-xs text-red-700 dark:text-red-300">{{ loadError }}</div>
        <template v-else-if="spec">
            <div class="flex items-start justify-between gap-3 px-4 pt-3">
                <div class="min-w-0">
                    <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-maiic-700 dark:text-maiic-300">How to import</div>
                    <h3 class="mt-0.5 text-base font-semibold text-gray-900 dark:text-slate-100">{{ title || spec.title }}</h3>
                    <p v-if="!stepsClosed" class="mt-1 max-w-4xl text-xs leading-relaxed text-gray-600 dark:text-slate-300">{{ spec.summary }}</p>
                    <button v-else type="button" class="mt-0.5 text-xs font-semibold text-maiic-700 hover:underline dark:text-maiic-300" @click="openSteps">Show the steps</button>
                </div>
                <button v-if="!stepsClosed" type="button" class="flex-none rounded-md p-1 text-maiic-600 transition hover:bg-maiic-100 hover:text-maiic-900 dark:text-maiic-300 dark:hover:bg-slate-700 dark:hover:text-white"
                        title="Close the steps" aria-label="Close the steps" @click="closeSteps">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>

            <template v-if="!stepsClosed">
                <ol class="mt-3 space-y-1.5 px-4">
                    <li v-for="(step, i) in allSteps" :key="i" class="flex gap-2.5">
                        <span class="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-maiic-600 text-[11px] font-bold text-white dark:bg-maiic-500">{{ i + 1 }}</span>
                        <span class="leading-relaxed">{{ step }}</span>
                    </li>
                </ol>
                <ul v-if="spec.notes && spec.notes.length" class="mt-2 list-disc space-y-1 px-4 pl-11 text-xs text-gray-500 dark:text-slate-400">
                    <li v-for="(note, i) in spec.notes" :key="i">{{ note }}</li>
                </ul>
            </template>
            <slot name="after"/>

            <!-- actions, right-aligned above the table -->
            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-maiic-100 px-4 py-2.5 dark:border-slate-700">
                <button type="button" class="inline-flex items-center gap-1.5 text-xs font-semibold text-maiic-700 hover:text-maiic-900 dark:text-maiic-300 dark:hover:text-maiic-100"
                        :aria-expanded="showTable ? 'true' : 'false'" @click="showTable = !showTable">
                    <svg class="h-3.5 w-3.5 transition" :class="showTable ? 'rotate-90' : ''" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/></svg>
                    {{ tableLabel }}
                </button>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <slot name="actions"/>
                    <button v-if="canCheck" type="button" :class="btnClass" :disabled="checking" title="Read the file and list every problem by row. Nothing is saved." @click="runCheck">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                        {{ checking ? 'Checking...' : 'Check file' }}
                    </button>
                    <a v-if="spec.template_url" :href="spec.template_url" :class="btnClass" :title="'The sample file in exactly the format this import reads: ' + spec.template_name">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L6.295 8.235a.75.75 0 10-1.09 1.03l4.25 4.5a.75.75 0 001.09 0l4.25-4.5a.75.75 0 00-1.09-1.03l-2.955 3.129V2.75z"/><path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z"/></svg>
                        {{ spec.template === 'manifest' ? 'Download sample manifest' : 'Download sample file' }}
                    </a>
                    <a :href="spec.guide_url" :class="btnClass" :title="'Every column with its type, whether it is required, its format and allowed values: ' + spec.guide_name">
                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4.5 2A1.5 1.5 0 003 3.5v13A1.5 1.5 0 004.5 18h11a1.5 1.5 0 001.5-1.5V7.621a1.5 1.5 0 00-.44-1.06l-4.12-4.122A1.5 1.5 0 0011.378 2H4.5zm2.25 8.5a.75.75 0 000 1.5h6.5a.75.75 0 000-1.5h-6.5zm0 3a.75.75 0 000 1.5h6.5a.75.75 0 000-1.5h-6.5z" clip-rule="evenodd"/></svg>
                        Download column guide
                    </a>
                </div>
            </div>

            <!-- the columns (or, for a machine-generated pack, the files and sheets) -->
            <div v-if="showTable" class="max-h-96 overflow-auto border-t border-maiic-100 bg-white dark:border-slate-700 dark:bg-slate-900/40">
                <table v-if="spec.columns.length" class="w-full text-left text-xs">
                    <thead class="sticky top-0 z-[1] bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500 dark:bg-slate-900 dark:text-slate-400">
                        <tr>
                            <th v-if="positional" class="px-3 py-2 font-semibold">Position</th>
                            <th class="px-3 py-2 font-semibold">Column</th>
                            <th class="px-3 py-2 font-semibold">Type</th>
                            <th class="px-3 py-2 font-semibold">Required</th>
                            <th class="px-3 py-2 font-semibold">Format and allowed values</th>
                            <th class="px-3 py-2 font-semibold">Description</th>
                            <th class="px-3 py-2 font-semibold">Example</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-slate-700/70">
                        <tr v-for="c in spec.columns" :key="c.name" class="align-top">
                            <td v-if="positional" class="whitespace-nowrap px-3 py-2 font-mono text-gray-500 dark:text-slate-400">{{ c.position || '' }}</td>
                            <td class="whitespace-nowrap px-3 py-2 font-mono font-semibold text-gray-900 dark:text-slate-100">{{ c.name }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-gray-700 dark:text-slate-300">{{ c.type }}</td>
                            <td class="whitespace-nowrap px-3 py-2">
                                <span v-if="c.required" class="rounded bg-maiic-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-maiic-800 dark:bg-maiic-900/50 dark:text-maiic-200">Required</span>
                                <span v-else class="text-gray-400 dark:text-slate-500">Optional</span>
                            </td>
                            <td class="min-w-[14rem] px-3 py-2 text-gray-600 dark:text-slate-300">{{ c.format }}</td>
                            <td class="min-w-[16rem] px-3 py-2 text-gray-600 dark:text-slate-300">{{ c.description }}</td>
                            <td class="whitespace-nowrap px-3 py-2 font-mono text-gray-500 dark:text-slate-400">{{ c.example }}</td>
                        </tr>
                    </tbody>
                </table>
                <table v-else-if="spec.files.length" class="w-full text-left text-xs">
                    <thead class="sticky top-0 z-[1] bg-gray-50 text-[11px] uppercase tracking-wide text-gray-500 dark:bg-slate-900 dark:text-slate-400">
                        <tr>
                            <th class="px-3 py-2 font-semibold">File or sheet</th>
                            <th class="px-3 py-2 font-semibold">Required</th>
                            <th class="px-3 py-2 font-semibold">What it holds</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-slate-700/70">
                        <tr v-for="f in spec.files" :key="f.name" class="align-top">
                            <td class="px-3 py-2 font-mono font-semibold text-gray-900 dark:text-slate-100">{{ f.name }}</td>
                            <td class="whitespace-nowrap px-3 py-2">
                                <span v-if="f.required" class="rounded bg-maiic-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-maiic-800 dark:bg-maiic-900/50 dark:text-maiic-200">Required</span>
                                <span v-else class="text-gray-400 dark:text-slate-500">When delivered</span>
                            </td>
                            <td class="px-3 py-2 text-gray-600 dark:text-slate-300">{{ f.description }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="checkError" class="border-t border-red-200 bg-red-50 px-4 py-2 text-xs text-red-700 dark:border-red-800/60 dark:bg-red-950/40 dark:text-red-300">{{ checkError }}</div>
            <div v-if="activeCheck" class="border-t border-maiic-100 p-3 dark:border-slate-700">
                <ImportCheckResult :check="activeCheck" :check-only="activeIsLocal" @dismiss="dismiss"/>
            </div>
        </template>
        <div v-else class="px-4 py-3 text-xs text-gray-500 dark:text-slate-400">Loading the import instructions...</div>
    </section>
</template>

<script>
import axios from 'axios'
import ImportCheckResult from '@/Components/Imports/ImportCheckResult.vue'

export default {
    components: { ImportCheckResult },

    props: {
        // the key in App\Support\Imports\ImportSpecs, e.g. loan-book
        specKey: { type: String, required: true },
        title: { type: String, default: '' },
        // steps particular to the screen, after the spec's own
        extraSteps: { type: Array, default: () => [] },
        // the chosen file: shows Check file when the spec can be checked on its own
        file: { type: [File, Object], default: null },
        // extra form fields sent with Check file (mapping, period, sheet)
        checkData: { type: Object, default: () => ({}) },
        // a check result the screen already holds (the EIR intake's analysis)
        check: { type: Object, default: null },
        // other keys whose refused imports this panel shows (a screen with two layouts)
        alsoKeys: { type: Array, default: () => [] },
        collapsed: { type: Boolean, default: null },
    },

    emits: ['checked'],

    data() {
        return {
            spec: null,
            loadError: '',
            showTable: true,
            checking: false,
            checkError: '',
            localCheck: null,
            dismissedAt: null,
            stepsClosed: false,
        }
    },

    computed: {
        btnClass() {
            return 'inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition hover:border-maiic-300 hover:bg-maiic-50 hover:text-maiic-800 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-maiic-500 dark:hover:bg-slate-700 dark:hover:text-white'
        },
        positional() {
            return this.spec && this.spec.headers === 'positional'
        },
        allSteps() {
            return [...(this.spec?.steps || []), ...this.extraSteps]
        },
        tableLabel() {
            if (!this.spec) return ''
            const s = this.spec
            if (s.columns.length) {
                const req = s.columns.filter(c => c.required).length
                return (this.showTable ? 'Hide' : 'Show') + ' the ' + s.columns.length + ' columns (' + req + ' required)'
            }
            return (this.showTable ? 'Hide' : 'Show') + ' the ' + s.files.length + ' files and sheets read'
        },
        canCheck() {
            return !!(this.spec && this.spec.check_url && this.file)
        },
        flashCheck() {
            const c = this.$page?.props?.flash?.import_check
            if (!c || ![this.specKey, ...this.alsoKeys].includes(c.key)) return null
            return c
        },
        activeCheck() {
            const c = this.localCheck || this.check || this.flashCheck
            if (!c || (this.dismissedAt && this.dismissedAt === c.checked_at)) return null
            return c
        },
        // closing the steps is remembered against their text, so changed steps show again
        stepsStorageKey() {
            return 'maiic.notice.import-spec.' + this.specKey
        },
        stepsFingerprint() {
            const text = [this.spec?.summary || '', ...this.allSteps, ...(this.spec?.notes || [])].join(' ')
            let h = 0
            for (let i = 0; i < text.length; i++) h = (h * 31 + text.charCodeAt(i)) | 0
            return String(h)
        },
        activeIsLocal() {
            return !!this.localCheck && this.activeCheck === this.localCheck
        },
    },

    watch: {
        specKey: { immediate: true, handler() { this.load() } },
        file() { this.localCheck = null; this.checkError = '' },
    },

    methods: {
        async load() {
            this.spec = null
            this.loadError = ''
            this.localCheck = null
            try {
                const { data } = await axios.get(this.route('import-templates.spec', this.specKey))
                this.spec = data
                try {
                    this.stepsClosed = localStorage.getItem(this.stepsStorageKey) === this.stepsFingerprint
                } catch (e) {
                    this.stepsClosed = false
                }
                this.showTable = this.collapsed === null ? data.columns.length <= 14 : !this.collapsed
            } catch (e) {
                this.loadError = 'The import instructions could not be loaded. Refresh the page to try again.'
            }
        },
        async runCheck() {
            if (!this.canCheck) return
            this.checking = true
            this.checkError = ''
            try {
                const fd = new FormData()
                fd.append('file', this.file)
                for (const [k, v] of Object.entries(this.checkData || {})) {
                    if (v === null || v === undefined || v === '') continue
                    fd.append(k, typeof v === 'object' ? JSON.stringify(v) : v)
                }
                const { data } = await axios.post(this.spec.check_url, fd)
                this.localCheck = data
                this.dismissedAt = null
                this.$emit('checked', data)
            } catch (e) {
                this.checkError = e.response?.data?.message || 'The file could not be checked.'
            } finally {
                this.checking = false
            }
        },
        closeSteps() {
            this.stepsClosed = true
            try {
                localStorage.setItem(this.stepsStorageKey, this.stepsFingerprint)
            } catch (e) {
                /* storage unavailable: closed for this visit only */
            }
        },
        openSteps() {
            this.stepsClosed = false
            try {
                localStorage.removeItem(this.stepsStorageKey)
            } catch (e) {
                /* storage unavailable */
            }
        },
        dismiss() {
            this.dismissedAt = this.activeCheck?.checked_at || 'x'
            this.localCheck = null
        },
    },
}
</script>
