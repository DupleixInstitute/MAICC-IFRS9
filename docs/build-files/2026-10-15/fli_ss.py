p = r'C:/xampp/htdocs/MAICC-IFRS9/resources/js/Pages/Governance/ScenarioSets.vue'
s = open(p, encoding='utf-8').read()
def rep(a, b):
    global s
    assert a in s, a
    s = s.replace(a, b)
start = s.index('        <div class="grid grid-cols-1 gap-6 p-5 lg:grid-cols-2">')
end = s.index('      </div>\n    </div>\n  </app-layout>')
body = open(r'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/fli_ss_body.html', encoding='utf-8').read()
s = s[:start] + body + s[end:]
# overlays: one line, not a box
rep('''      <div v-if="overlays && overlays.length" class="maiic-panel p-4 text-sm">''', '''      <div v-if="overlays && overlays.length" class="-mt-2 text-xs text-gray-600">''')
rep('''<p class="text-sm text-gray-500">{{ s.narrative }}</p>''', '''<p class="text-sm text-gray-500">{{ plain(s.narrative) }}</p>''')
rep("props: { period: String, sets: Array, rules: Object, periods: Array, overlays: Array, canGovern: Boolean },",
    "props: { period: String, sets: Array, rules: Object, periods: Array, overlays: Array, canGovern: Boolean, seriesNames: { type: Object, default: () => ({}) } },")
rep("    fmt(v) {", """    seriesName(code) { return this.seriesNames[code] || code },
    // the scenarios other than the base: the base column is the base path itself
    otherScenarios(s) { const base = (s.scenarios || []).filter(x => x.is_base).map(x => x.name); return Object.keys((s.paths && s.paths.scenarios) || {}).filter(n => !base.includes(n)) },
    weightOf(s, name) { const x = (s.scenarios || []).find(y => y.name === name); return x ? Number(x.weight).toFixed(0) + '%' : '' },
    shock(sh) {
      const v = Number(sh.value); const sign = v > 0 ? '+' : ''; const num = v.toLocaleString('en-GB', { maximumFractionDigits: 4 })
      const t = sh.kind === 'pct' ? sign + num + '%' : (sh.kind === 'abs' ? sign + num + ' points' : (sh.kind === 'replace' ? 'set to ' + num : (sh.kind === 'mult' ? 'times ' + num : sh.kind + ' ' + num)))
      return Number(sh.year_offset || 0) > 0 ? t + ' (year ' + (Number(sh.year_offset) + 1) + ')' : t
    },
    // specification references are for the build team, not the screen
    plain(t) { return String(t || '').replace(/\\s*\\((?:spec(?:ification)? v\\d+ section [\\d.]*\\d[^)]*|O\\d+|decision D\\d+)\\)/gi, '').replace(/\\s+(?:of\\s+)?(?:the\\s+)?spec(?:ification)? v\\d+ section [\\d.]*\\d/gi, '').replace(/\\s+of section [\\d.]*\\d/g, '') },
    fmt(v) {""")
open(p, 'w', encoding='utf-8', newline='').write(s)

p = r'C:/xampp/htdocs/MAICC-IFRS9/app/Http/Controllers/ScenarioSetController.php'
s = open(p, encoding='utf-8').read()
rep("use App\\Services\\Scenario\\ScenarioSetService;\n", "use App\\Services\\Scenario\\ScenarioSetService;\nuse App\\Support\\Fli\\FliPlainLanguage;\n")
rep("""        return Inertia::render('Governance/ScenarioSets', [""", """        // the series' full names for the screen (cards, paths, back-test), from the macro definitions
        $plain = new FliPlainLanguage();
        $codes = [];
        foreach ($sets as $s) {
            $codes = array_merge($codes, array_keys($s['paths']['base'] ?? []), array_column(array_merge(...array_map(fn ($x) => $x['shocks'] ?? [], $s['scenarios'] ?: [[]])), 'statistic_code'), array_column($s['backtest']['rows'] ?? [], 'series'));
        }
        $seriesNames = [];
        foreach (array_unique(array_filter($codes, 'is_string')) as $code) {
            $seriesNames[$code] = $plain->driver($code);
        }

        return Inertia::render('Governance/ScenarioSets', [""")
rep("""            'canGovern' => (bool) (auth()->user()?->can('eir.govern') ?? false),
        ]);
    }

    /** The editor saves""", """            'canGovern' => (bool) (auth()->user()?->can('eir.govern') ?? false), 'seriesNames' => (object) $seriesNames,
        ]);
    }

    /** The editor saves""")
open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok')
