base = r'C:\Users\DELL\AppData\Local\Temp\claude\c--xampp-htdocs-Stress-Testing-App\c9a0f915-2064-4f18-9b98-77857ef71421\scratchpad\book'
p = r'C:\xampp\htdocs\MAICC-IFRS9\resources\js\Pages\Dashboard.vue'
s = open(p, encoding='utf-8').read()
def rep(old, new):
    global s
    assert old in s, old
    s = s.replace(old, new, 1)
panel = open(base + r'\panel.vue', encoding='utf-8').read()
js = open(base + r'\panel.js', encoding='utf-8').read()
rep('            <!-- ============================ CHARTS ============================ -->', panel + '            <!-- ============================ CHARTS ============================ -->')
rep('''    recentImports: { type: Array, default: () => [] },
    error: String,
})''', '''    recentImports: { type: Array, default: () => [] },
    eclBuildUp: Object,
    eclBreakdown: Object,
    error: String,
})''')
rep('const monthEndSteps = computed(() => {', js.lstrip('\n') + 'const monthEndSteps = computed(() => {')
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
