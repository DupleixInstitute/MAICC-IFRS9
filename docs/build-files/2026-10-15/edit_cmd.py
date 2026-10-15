p = r'C:\xampp\htdocs\MAICC-IFRS9\app\Console\Commands\FliMonthEndCommand.php'
s = open(p, encoding='utf-8').read()
def rep(old, new):
    global s
    assert old in s, old[:80]
    s = s.replace(old, new)
rep("""use App\Services\Fli\FliRouteService;""", """use App\Services\Fli\FliRouteService;
use App\Services\Fli\FliWithoutModel;""")
rep("""            if ($this->option('project')) {
                $this->project($projection, $period, $user);""", """            if ($this->option('project')) {
                $this->project($projection, $period, $user, app(FliWithoutModel::class));""")
rep("""            $this->line("<info>ok</info>  {$s['title']}: {$s['status']}");
        }""", """            $this->line(($k === 'model' && ! empty($s['without_model']) ? '<comment>warn</comment>' : '<info>ok</info>  ') . "{$s['title']}: {$s['status']}");
        }""")
rep("""        $this->project($projection, $period, $user);
        $this->line('<info>run</info> ECL on the post-FLI PD ...');""", """        $this->project($projection, $period, $user, app(FliWithoutModel::class));
        $this->line('<info>run</info> ECL on the post-FLI PD ...');""")
rep("""    private function project(FliProjectionService $projection, string $period, ?int $user): void
    {
        $p = $projection->run($period, $user);""", """    private function project(FliProjectionService $projection, string $period, ?int $user, FliWithoutModel $withoutModel): void
    {
        // no model in force: under fli_without_model (Proceed) there is nothing to project, the PD holds;
        // under Refuse, or with no approved value, the month stops here with the reason
        $d = $withoutModel->assertMayRun($period);
        if (! $d['in_force']) {
            $this->line('<comment>skip</comment> Projection: ' . FliWithoutModel::PROJECTION_SKIPPED);

            return;
        }
        $p = $projection->run($period, $user);""")
open(p, 'w', encoding='utf-8').write(s)
print('ok')
