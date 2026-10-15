def edit(p, pairs):
    s = open(p, encoding='utf-8', newline='').read()
    nl = '\r\n' if '\r\n' in s else '\n'
    s = s.replace('\r\n', '\n')
    for old, new in pairs:
        assert old in s, (p, old[:80])
        s = s.replace(old, new, 1)
    open(p, 'w', encoding='utf-8', newline='').write(s.replace('\n', nl))


edit(r'C:\xampp\htdocs\MAICC-IFRS9\routes\web.php', [
("""// (system audit of 9 October 2026, finding M14).
Route::post('/api/ebanker-feed/pack', function (\\Illuminate\\Http\\Request $request) {
    $token = (string) config('services.ebanker_feed.api_token');
    abort_unless($token !== '' && hash_equals($token, (string) $request->bearerToken()), 401, 'A valid feed token is required.');""",
"""// (system audit of 9 October 2026, finding M14). The intake is open only while route 3 is the governed route in force,
// or the setting opens routes 1 to 3 together (FeedRoute, 14 October 2026); the inbox is the database's own (DatabaseScope).
Route::post('/api/ebanker-feed/pack', function (\\Illuminate\\Http\\Request $request) {
    $token = (string) config('services.ebanker_feed.api_token');
    abort_unless($token !== '' && hash_equals($token, (string) $request->bearerToken()), 401, 'A valid feed token is required.');
    $route = \\App\\Services\\Ebanker\\FeedRoute::check(3);
    if (! $route['allowed']) {
        \\Illuminate\\Support\\Facades\\Log::warning('E-Banker pack refused at the API: ' . $route['reason'], ['ip' => $request->ip()]);

        return response()->json(['refused' => $route['reason']], 409);
    }"""),
("""    $dir = storage_path('app/ebanker-inbox/' . now()->format('Ymd-His') . '-' . substr(hash_file('sha256', $request->file('pack')->getRealPath()), 0, 8));""",
"""    $dir = \\App\\Support\\DatabaseScope::storagePath('ebanker-inbox') . '/' . now()->format('Ymd-His') . '-' . substr(hash_file('sha256', $request->file('pack')->getRealPath()), 0, 8);"""),
])
print('ok')
