import sys
p = r'c:\xampp\htdocs\MAICC-IFRS9\app\Http\Controllers\FliOverlayController.php'
s = open(p, encoding='utf-8').read()
def rep(a, b):
    global s
    if a not in s: sys.exit('NOT FOUND ' + a[:70])
    s = s.replace(a, b, 1)
rep("""            'users' => DB::table('users')->orderBy('name')->get(['id', 'name']),
            'canGovern'""", """            'users' => DB::table('users')->orderBy('name')->get(['id', 'name']),
            // the double-counting check (FLI module D-F9): the drivers an overlay can name, and the one the model in force already carries
            'drivers' => DB::table('macro_statistics')->orderBy('statistic_name')->get(['statistic_code', 'statistic_name']),
            'modelDriver' => (new \App\Services\Fli\FliModelRegistry())->inForce($period)?->statistic_code,
            'canGovern'""")
rep("""            'expiry_period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);""", """            'expiry_period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'risk_driver' => ['nullable', 'string', 'max:64'],
            'double_count_review' => ['nullable', 'string', 'max:2000'],
        ]);""")
rep("""'owner_id' => $data['owner_id'] ?? $request->user()->id, 'expiry_period' => $data['expiry_period']], $request->user()->id);""",
"""'owner_id' => $data['owner_id'] ?? $request->user()->id, 'expiry_period' => $data['expiry_period'],
                'risk_driver' => $data['risk_driver'] ?? null, 'double_count_review' => $data['double_count_review'] ?? null], $request->user()->id);""")
open(p, 'w', encoding='utf-8').write(s)
print('ok')
