import io
p = r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/Workspace/Index.vue'
s = io.open(p, encoding='utf-8').read().replace('\r\n', '\n')

a = s.index('        <template #header>')
b = s.index('            <!-- ===================== CHECKLIST TAB ===================== -->')
new = '''        <template #header>
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

'''
s = s[:a] + new + s[b:]

s = s.replace('<div v-if="activeTab === \'checklist\'" class="maiic-panel">', '<div v-if="activeTab === \'checklist\'">')
s = s.replace('<div v-else class="maiic-panel flex min-h-[420px] flex-col">', '<div v-else class="flex min-h-[420px] flex-col">')
# close the new tab wrapper before the outer div ends
s = s.replace('''                </div>
            </div>
        </div>
    </AppLayout>''', '''                </div>
            </div>
            </div>
        </div>
    </AppLayout>''')
s = s.replace("<p class=\"text-xs text-gray-400\">Period {{ period }} · visible to everyone signed in</p>", "<p class=\"text-xs text-gray-400\">Period {{ period }}, visible to everyone signed in</p>")
s = s.replace("Ticked by {{ t.completed_by }}<span v-if=\"t.completed_at\"> · {{ t.completed_at }}</span>", "Ticked by {{ t.completed_by }}<span v-if=\"t.completed_at\">, {{ t.completed_at }}</span>")

s = s.replace("const activeTab = ref('checklist')", """const activeTab = ref('checklist')
const tabs = computed(() => [
    { key: 'checklist', label: 'IFRS 9 close checklist', count: props.progress.done + '/' + props.progress.total },
    { key: 'messages', label: 'Team messages', count: props.messages.length },
])""")
# ring style no longer used
a = s.index('const ringStyle = computed(() => {')
b = s.index('})\n', a) + 3
s = s[:a] + s[b:]
io.open(p, 'w', encoding='utf-8').write(s)
print('ok')
