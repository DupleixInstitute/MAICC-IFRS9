from ed import edit
p='app/Imports/LoanBooksImport.php'
edit(p, r"""    protected function normalizeKey($key): string
    {""", r"""    /** The end of the reporting period being imported: the date the staging rules are read at (H11). */
    protected function stagingAsOf(): string
    {
        $period = (string) ($this->mapping['reporting_period'] ?? $this->import->settings['reporting_period'] ?? '');
        if (preg_match('/^(\d{4})-(\d{2})/', $period, $m) !== 1) {
            throw new \RuntimeException('The import names no reporting period (YYYY-MM), so the staging rules in force cannot be read; the period is not assumed to be this month.');
        }

        return \Carbon\Carbon::create((int) $m[1], (int) $m[2], 1)->endOfMonth()->toDateString();
    }

    protected function normalizeKey($key): string
    {""")
p='tests/Feature/Eir/StagingClassifierTest.php'
import re
s=open(p,encoding='utf-8',newline='').read()
s=s.replace("new StagingClassifier()","new StagingClassifier('2026-08-31')")
open(p,'w',encoding='utf-8',newline='').write(s)
edit(p, r"""    public function test_matches_legacy_ladder_with_empty_config_table(): void
    {
        $this->createTable();

        $classifier = new StagingClassifier('2026-08-31');
        foreach ($this->legacyExpectations() as [$row, $expected]) {
            $this->assertSame($expected, $classifier->classify($row), json_encode($row));
        }
    }

    public function test_matches_legacy_ladder_with_no_table_at_all(): void
    {
        $classifier = new StagingClassifier('2026-08-31');
        foreach ($this->legacyExpectations() as [$row, $expected]) {
            $this->assertSame($expected, $classifier->classify($row), json_encode($row));
        }
    }""", r"""    /** With no rule in force the import stops; it no longer falls back to a 31/181 ladder in code (H11). */
    public function test_with_no_rule_in_force_the_classifier_stops_and_names_it(): void
    {
        $this->createTable();

        $classifier = new StagingClassifier('2026-08-31');
        $this->assertSame('1', $classifier->classify([]), 'a loan with no arrears needs no rule');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No approved staging threshold is in force');
        $classifier->classify(['31_90_days' => '100']);
    }

    /** The rule is read at the period end, not today: a rule effective later does not govern an earlier month. */
    public function test_the_rule_is_read_at_the_period_end_not_today(): void
    {
        $this->createTable();
        $this->seedDefault();
        StagingThreshold::create(['facility_class' => 'DEFAULT', 'min_tenor_months' => 0, 'stage2_dpd' => 61, 'stage3_dpd' => 181, 'effective_from' => '2026-06-01']);

        $this->assertSame('2', (new StagingClassifier('2026-05-31'))->classify(['31_90_days' => '100']));
        $this->assertSame('2', (new StagingClassifier('2026-08-31'))->classify(['31_90_days' => '100']), 'the 31-90 bucket reads as 31 days, still under 61');
        $this->assertSame('2', (new StagingClassifier('2026-08-31'))->classify(['91_180_days' => '100']));
    }

    /** A proposed row (maker-checker, H11) does not govern until a second person approves it. */
    public function test_a_proposed_rule_does_not_govern(): void
    {
        $this->createTable();
        Schema::table('staging_thresholds', function (Blueprint $t) {
            $t->string('status', 20)->default('APPROVED'); $t->unsignedBigInteger('proposed_by')->nullable(); $t->unsignedBigInteger('approved_by')->nullable(); $t->timestamp('approved_at')->nullable(); $t->string('reason', 500)->nullable();
        });
        $this->seedDefault();
        StagingThreshold::create(['facility_class' => 'DEFAULT', 'min_tenor_months' => 0, 'stage2_dpd' => 31, 'stage3_dpd' => 91, 'effective_from' => '2026-01-01', 'status' => 'PROPOSED', 'proposed_by' => 1]);

        $this->assertSame('2', (new StagingClassifier('2026-08-31'))->classify(['91_180_days' => '100']), 'the proposal (Stage 3 at 91) is not in force');
    }""")
