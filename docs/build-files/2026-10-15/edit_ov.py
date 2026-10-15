p = r'C:\xampp\htdocs\MAICC-IFRS9\app\Http\Controllers\FliModuleController.php'
s = open(p, encoding='utf-8', newline='').read()
nl = '\r\n' if '\r\n' in s else '\n'
s = s.replace('\r\n', '\n')
def rep(old, new):
    global s
    assert old in s, old[:80]
    s = s.replace(old, new)
rep("""            'snapshot' => $snap, 'tolerance' => $close->tolerance($period), 'recent' => array_slice($recent, 0, 15), 'running' => $runner->running(),""",
"""            'snapshot' => $snap, 'tolerance' => $close->tolerance($period), 'recent' => array_slice($recent, 0, 15), 'running' => $runner->running(),
            // no model in force (fli_without_model) and a month-end stopped part-way: each its own banner
            'withoutModel' => collect($steps)->firstWhere('key', 'model')['without_model'] ?? false ? collect($steps)->firstWhere('key', 'model')['status'] : null,
            'currency' => \App\Services\Fli\MonthEndCurrency::state($period),""")
s = s.replace('\n', nl)
open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok')
