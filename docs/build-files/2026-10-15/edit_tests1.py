def edit(p, pairs):
    s = open(p, encoding='utf-8', newline='').read()
    nl = '\r\n' if '\r\n' in s else '\n'
    s = s.replace('\r\n', '\n')
    for old, new in pairs:
        assert old in s, (p, old[:80])
        s = s.replace(old, new, 1)
    open(p, 'w', encoding='utf-8', newline='').write(s.replace('\n', nl))


edit(r'C:\xampp\htdocs\MAICC-IFRS9\app\Services\Fli\FliCloseService.php', [
("""        $commentary = trim((string) ($commentary ?? $s->commentary ?? ''));
        if (! empty((json_decode((string) ($s->inputs ?? ''), true) ?: [])['without_model']) && $commentary === '') {
            throw new RuntimeException(FliWithoutModel::NOTE . " for {$s->reporting_period}: the PD held with no forward-looking adjustment (fli_without_model). Say so in the commentary, with why no model version is in force, before signing off.");
        }
        if ($s->needs_commentary && $commentary === '') {""", """        $commentary = trim((string) ($commentary ?? $s->commentary ?? ''));
        if ($s->needs_commentary && $commentary === '') {"""),
("""        if ($label === null && $userId !== null && (int) $s->created_by === $userId) {""", """        if (! empty((json_decode((string) ($s->inputs ?? ''), true) ?: [])['without_model']) && $commentary === '') {
            // fli_without_model, Proceed: the month is signed off only with a commentary saying so
            throw new RuntimeException(FliWithoutModel::NOTE . " for {$s->reporting_period}: the PD held with no forward-looking adjustment (fli_without_model). Say so in the commentary, with why no model version is in force, before signing off.");
        }
        if ($label === null && $userId !== null && (int) $s->created_by === $userId) {"""),
])

edit(r'C:\xampp\htdocs\MAICC-IFRS9\tests\Feature\Eir\GovernanceServiceTest.php', [
("""        // plus fee_source_precedence and takeon_eir_plausibility (14 Oct 2026, results review E2 and E3).
        $this->assertSame(61, GovernanceSetting::count());
        $this->assertSame(61, GovernanceSetting::where('status', 'APPROVED')->count());""",
"""        // plus fee_source_precedence and takeon_eir_plausibility (14 Oct 2026, results review E2 and E3),
        // plus fli_without_model (14 Oct 2026, FLI module D-F1 note).
        $this->assertSame(62, GovernanceSetting::count());
        $this->assertSame(62, GovernanceSetting::where('status', 'APPROVED')->count());"""),
])

edit(r'C:\xampp\htdocs\MAICC-IFRS9\tests\Feature\FLI\BootstrapMonthEndChainTest.php', [
("""\\$why = \\[\\];\\s*foreach \\(self::periodChain\\(\\) as/'""", """\\$why = \\[\\];\\s*(\\$decision = null;\\s*)?foreach \\(self::periodChain\\(\\) as/'"""),
])

edit(r'C:\xampp\htdocs\MAICC-IFRS9\tests\Feature\FLI\FliCloseDiscountedRecordTest.php', [
("""use Tests\\TestCase;""", """use Tests\\Concerns\\SeedsGovernedDefaults;
use Tests\\TestCase;"""),
("""class FliCloseDiscountedRecordTest extends TestCase
{
    protected $seed = false;""", """class FliCloseDiscountedRecordTest extends TestCase
{
    use SeedsGovernedDefaults;

    protected $seed = false;"""),
("""        Schema::create('fli_model_versions', function (Blueprint $t) { $t->increments('id'); });""",
"""        // the registry: no version is published, so the month has no model in force (fli_without_model decides)
        Schema::create('fli_models', function (Blueprint $t) { $t->increments('id'); $t->string('code'); $t->string('name')->nullable(); });
        Schema::create('fli_model_versions', function (Blueprint $t) { $t->increments('id'); $t->integer('model_id')->nullable(); $t->integer('version')->nullable(); $t->string('status')->nullable(); $t->string('effective_from')->nullable(); $t->integer('fli_fit_id')->nullable(); });
        $this->seedGovernedDefaults(['fli_without_model', 'fli_movement_commentary', 'scenario_weighting_method']);"""),
("""'fli_set_id' => 1, 'fli_route' => 'Regression', 'ecl_calculated_at' => '2026-10-14 10:00:00', 'ecl_calculation_run_id' => 'R1'];""",
"""'fli_set_id' => 1, 'fli_route' => 'Regression', 'ecl_calculated_at' => '2026-10-01 10:00:00', 'ecl_calculation_run_id' => 'R1'];"""),
])
print('ok')
