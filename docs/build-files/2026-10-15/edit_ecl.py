import io
def edit(p, pairs):
    s = open(p, encoding='utf-8', newline='').read()
    nl = '\r\n' if '\r\n' in s else '\n'
    s = s.replace('\r\n', '\n')
    for old, new in pairs:
        assert old in s, (p, old[:80])
        s = s.replace(old, new, 1)
    open(p, 'w', encoding='utf-8', newline='').write(s.replace('\n', nl))
edit(r'C:\xampp\htdocs\MAICC-IFRS9\resources\js\Pages\ExpectedCreditLoss\Index.vue', [
("""        <div class="space-y-5">
            <!-- One line of figures""", """        <div class="space-y-5">
            <!-- a month-end stopped part-way: the ECL shown predates a step it depends on (MonthEndCurrency) -->
            <div v-if="midRun && midRun.mid_run" role="alert" class="maiic-panel border-l-4 !border-l-red-600 bg-red-50 px-5 py-3 text-sm text-red-800 dark:bg-red-900/20 dark:text-red-200">
                <span class="font-semibold">The month is mid-run: the ECL below is not current.</span>&nbsp;{{ midRun.message }}
            </div>
            <!-- One line of figures"""),
("""    runInfo: { type: Object, default: () => ({ period: null, rows: [] }) },""", """    runInfo: { type: Object, default: () => ({ period: null, rows: [] }) },
    midRun: { type: Object, default: null },"""),
])
edit(r'C:\xampp\htdocs\MAICC-IFRS9\app\Http\Controllers\ExpectedCreditLossController.php', [
("""                'runInfo'     => $this->runInfo($request, $latestPeriod),""", """                'runInfo'     => $this->runInfo($request, $latestPeriod),
                // a month-end stopped part-way leaves these rows older than a step they depend on: said so, not shown as current
                'midRun'      => $viewPeriod ? \App\Services\Fli\MonthEndCurrency::state((string) $viewPeriod) : null,"""),
])
print('ok')
