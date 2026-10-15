import re
p = 'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/Dashboard.vue'
s = open(p, encoding='utf-8').read()
newt = open('C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/new_template.html', encoding='utf-8').read()

def rep(old, new, count=1):
    global s
    assert s.count(old) == count, (s.count(old), old[:80])
    s = s.replace(old, new)

# 1. remove the operations row, insert the new charts at the end of the !error block
i = s.index('        <!-- ============================ OPERATIONS ROW')
j = s.index('    </app-layout>')
s = s[:i] + s[j:]
rep('''                </div>
            </div>
        </template>

    </app-layout>''', '''                </div>
            </div>
''' + newt + '''        </template>
    </app-layout>''')

# 2. toggles dark-aware
s = s.replace(":class=\"pieView === 'chart' ? 'bg-maiic-600 text-white' : 'bg-white text-gray-600'\"", ":class=\"tgl(pieView === 'chart')\"")
s = s.replace(":class=\"pieView === 'table' ? 'bg-maiic-600 text-white' : 'bg-white text-gray-600'\"", ":class=\"tgl(pieView === 'table')\"")
s = s.replace(":class=\"trendView === 'chart' ? 'bg-maiic-600 text-white' : 'bg-white text-gray-600'\"", ":class=\"tgl(trendView === 'chart')\"")
s = s.replace(":class=\"trendView === 'table' ? 'bg-maiic-600 text-white' : 'bg-white text-gray-600'\"", ":class=\"tgl(trendView === 'table')\"")
rep(''':class="breakdownView === o.key ? 'bg-maiic-600 text-white' : 'bg-white text-gray-600'" class="px-3 py-1">{{ o.label }}</button>''',
    ''':class="tgl(breakdownView === o.key)" class="px-3 py-1">{{ o.label }}</button>''')
s = s.replace('class="rounded-md border border-maiic-200 bg-maiic-50 py-1 pl-2 pr-7 text-xs font-semibold text-maiic-800 focus:border-maiic-500 focus:ring-maiic-500"',
              'class="rounded-md border border-maiic-200 bg-maiic-50 py-1 pl-2 pr-7 text-xs font-semibold text-maiic-800 focus:border-maiic-500 focus:ring-maiic-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"')

# 3. breakdown: chart/table toggle + chart
rep('''                        <div class="flex overflow-hidden rounded-lg border border-gray-200 text-xs dark:border-slate-600">
                            <button v-for="o in breakdownViews"''', '''                        <div class="flex flex-wrap items-center gap-2">
                        <div class="flex overflow-hidden rounded-lg border border-gray-200 text-xs dark:border-slate-600">
                            <button @click="breakdownMode = 'table'" :class="tgl(breakdownMode === 'table')" class="px-3 py-1">Table</button>
                            <button @click="breakdownMode = 'chart'" :class="tgl(breakdownMode === 'chart')" class="px-3 py-1">Chart</button>
                        </div>
                        <div class="flex overflow-hidden rounded-lg border border-gray-200 text-xs dark:border-slate-600">
                            <button v-for="o in breakdownViews"''')
rep('''{{ o.label }}</button>
                        </div>
                    </div>
                    <div class="maiic-table-wrap">''', '''{{ o.label }}</button>
                        </div>
                        </div>
                    </div>
                    <div v-show="breakdownMode === 'chart'" class="px-4 py-3">
                        <div class="relative w-full" :style="{ height: breakdownChartHeight + 'px' }"><canvas ref="breakdownChart"></canvas></div>
                    </div>
                    <div v-if="breakdownMode === 'table'" class="maiic-table-wrap">''')

# 4. script: props
rep('''    monthEnd: Object,
    loanBookSnapshot: Object,
    recentImports: { type: Array, default: () => [] },
''', '''    eclMixTrend: Object,
    scenarioEcl: Object,
    defaultRates: Object,
''')

# 5. remove unused helpers
rep('''function formatDate(d) {
    if (!d) return ''
    const dt = new Date(d)
    return isNaN(dt) ? d : dt.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
}
''', '')
rep('''const stageBar = ['bg-maiic-600', 'bg-amber-500', 'bg-red-600']
const stageBadge = ['maiic-badge-green', 'maiic-badge-gold', 'maiic-badge-red']
function importBadge(status) {
    return { completed: 'maiic-badge-green', processing: 'maiic-badge-gold', failed: 'maiic-badge-red' }[status] || 'maiic-badge-grey'
}
''', '''// a Chart/Table (or view) toggle button, readable in light and dark
function tgl(active) {
    return active ? 'bg-maiic-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'
}
''')
i = s.index('const monthEndSteps = computed(() => {')
j = s.index('const pieView = ref(')
s = s[:i] + s[j:]

open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
