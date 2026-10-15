import sys
T = r'C:/Users/DELL/AppData/Local/Temp/claude/c--xampp-htdocs-Stress-Testing-App/c9a0f915-2064-4f18-9b98-77857ef71421/scratchpad/tools/'
p = r'C:/xampp/htdocs/MAICC-IFRS9/app/Http/Controllers/MacroStatisticsController.php'
s = open(p, encoding='utf-8').read()
add = open(T + 'macro_add.php.txt', encoding='utf-8').read()

def rep(a, b):
    global s
    if a not in s:
        sys.exit('NOT FOUND: ' + a[:70])
    s = s.replace(a, b, 1)

rep("use App\\Services\\Macro\\WorldBankFetcherService;", "use App\\Services\\Macro\\WorldBankFetcherService;\nuse App\\Services\\AuditLoggerService;\nuse App\\Services\\Scenario\\ScenarioSetService;\nuse Illuminate\\Http\\JsonResponse;")
rep("->only(['commit', 'manual']);", "->only(['commit', 'manual', 'variableStore', 'variableUpdate', 'variableDestroy', 'observationUpdate', 'observationDestroy', 'csvPreview', 'csvCommit', 'assumptionSave']);")
rep("->get(['d.id', 'd.period', 'd.value', 'd.is_forecast', 'd.source', 'd.notes', 'b.source as batch_source', 'b.fetched_at']) : collect();",
    "->get(['d.id', 'd.period', 'd.value', 'd.is_forecast', 'd.source', 'd.notes', 'd.published_at', 'b.source as batch_source', 'b.fetched_at']) : collect();")
rep("""            'series' => $series, 'selected' => $selected, 'observations' => $observations, 'batches' => $batches, 'sets' => $sets,""",
    """            'series' => $series, 'selected' => $selected, 'observations' => $observations, 'batches' => $batches, 'sets' => $sets,
            'assumptions' => $this->assumptions((string) $request->query('aperiod', '')),""")
rep("""'n' => (int) $agg->n, 'first' => $agg->first,""", """'description' => $s->statistic_description, 'website' => $s->website_link,
                'n' => (int) $agg->n, 'first' => $agg->first,""")
i = s.rindex("    private function rbmPreview(")
s = s[:i] + add + s[i:]
open(p, 'w', encoding='utf-8').write(s)
print('ok')
