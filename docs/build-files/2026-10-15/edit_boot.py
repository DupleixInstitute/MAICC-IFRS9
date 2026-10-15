p = r'C:\xampp\htdocs\MAICC-IFRS9\app\Console\Commands\Bootstrap.php'
s = open(p, encoding='utf-8', newline='').read()
nl = '\r\n' if '\r\n' in s else '\n'
s = s.replace('\r\n', '\n')
def rep(old, new):
    global s
    assert old in s, old[:80]
    s = s.replace(old, new)
rep("""        // fli:month-end --all itself is not called: it stops at a month with no
        // model version in force, which a clean install never has (no fit is
        // approved by the bootstrap, audit C9); its steps are run here instead.""",
"""        // fli:month-end --all itself is not called; its steps are run here. A
        // clean install has no model version in force (no fit is approved by
        // the bootstrap, audit C9), and since 14 October 2026 the bootstrap and
        // the month-end read the same governed setting for that case
        // (fli_without_model, FLI module D-F1 note): under "Proceed" (the seeded
        // technical default) the route holds the PD, the projection is skipped
        // and the ECL and the record run, the record saying so; under "Refuse"
        // (or with no approved value) the bootstrap stops at the first month
        // with no model, naming the reason, as the month-end does.""")
rep("""        $routeSvc = app(\App\Services\Fli\FliRouteService::class);
        foreach ($periods as $p) {
            $ok = [];
            $why = [];
            foreach (self::periodChain() as [$key, $label, , $needs]) {
                $blocked = array_values(array_filter($needs, fn ($n) => ! ($ok[$n] ?? false)));
                if ($blocked !== []) {
                    $ok[$key] = false;
                    $why[$key] = 'not run: ' . implode(', ', $blocked) . ' did not run for the month';
                    continue;
                }
                try {""", """        $routeSvc = app(\App\Services\Fli\FliRouteService::class);
        $withoutModel = app(\App\Services\Fli\FliWithoutModel::class);
        foreach ($periods as $p) {
            $ok = [];
            $why = [];
            $decision = null;
            foreach (self::periodChain() as [$key, $label, , $needs]) {
                $blocked = array_values(array_filter($needs, fn ($n) => ! ($ok[$n] ?? false)));
                if ($blocked !== []) {
                    $ok[$key] = false;
                    $why[$key] = 'not run: ' . implode(', ', $blocked) . ' did not run for the month';
                    continue;
                }
                if ($key === 'fli-route') {
                    // the governed answer for a month with no model version in force: proceed with the PD
                    // held, or stop the bootstrap here with the reason (fli_without_model), as the month-end does
                    $decision = $withoutModel->decide($p);
                    if (! $decision['proceed']) {
                        throw new \RuntimeException("{$p}: " . $decision['message']);
                    }
                }
                try {""")
rep("""                        'fli-projection' => app(\App\Services\Fli\FliProjectionService::class)->run($p, $user),""",
"""                        'fli-projection' => ($decision['in_force'] ?? false) ? app(\App\Services\Fli\FliProjectionService::class)->run($p, $user) : \App\Services\Fli\FliWithoutModel::PROJECTION_SKIPPED,""")
rep("""            $chain['projection'][$p] = $ok['fli-projection'];""", """            $chain['projection'][$p] = $ok['fli-projection'] && is_array($why['fli-projection']);
            $chain['no_model'][$p] = $ok['fli-route'] && is_array($why['fli-route']) && empty($why['fli-route']['model_version']);""")
rep("""        $chain = ['set' => [], 'route' => [], 'projection' => [], 'record' => []];""", """        $chain = ['set' => [], 'route' => [], 'projection' => [], 'record' => [], 'no_model' => []];""")
rep("""        $this->note('6.6 forward-looking chain by period', sprintf('of %d months with a PD: scenario set approved for %d, route applied for %d, projection run for %d, month recorded for %d',
            count($chain['route']), count(array_filter($chain['set'])), count(array_filter($chain['route'])), count(array_filter($chain['projection'])), count(array_filter($chain['record']))));""",
"""        $this->note('6.6 forward-looking chain by period', sprintf('of %d months with a PD: scenario set approved for %d, route applied for %d (%d with no model version in force: the PD held under fli_without_model), projection run for %d, month recorded for %d',
            count($chain['route']), count(array_filter($chain['set'])), count(array_filter($chain['route'])), count(array_filter($chain['no_model'])), count(array_filter($chain['projection'])), count(array_filter($chain['record']))));""")
rep("""            . '; projection ' . ($ok['fli-projection'] ? count($proj['segments'] ?? []) . ' portfolios' : $proj)""",
"""            . '; projection ' . ($ok['fli-projection'] ? (is_array($proj) ? count($proj['segments'] ?? []) . ' portfolios' : $proj) : $proj)""")
s = s.replace('\n', nl)
open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok')
