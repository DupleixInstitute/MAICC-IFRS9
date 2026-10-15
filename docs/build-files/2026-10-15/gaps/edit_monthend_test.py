import os
os.chdir('C:/xampp/htdocs/MAICC-IFRS9')
p = 'tests/Feature/Eir/MonthEndUiTest.php'
s = open(p, encoding='utf-8').read()
reps = [
(""" *  - A reporting period is locked (and unlocked, with a reason) from the
 *    E-Banker Feed's Periods Built tab, not only from `eir:lock-period`.""",
 """ *  - A reporting period is locked (and its unlock requested, with a reason)
 *    from the E-Banker Feed's Periods Built tab, not only from
 *    `eir:lock-period`; the unlock waits for a second person (documentation
 *    audit of 14 October 2026, ReopenAndUnlockMakerCheckerTest)."""),
("""        DB::table('users')->insert(['id' => 10, 'name' => 'Maker', 'email' => 'maker@example.test', 'created_at' => now(), 'updated_at' => now()]);""",
 """        DB::table('users')->insert(['id' => 10, 'name' => 'Maker', 'email' => 'maker@example.test', 'created_at' => now(), 'updated_at' => now()]);
        (require base_path('database/migrations/2026_10_14_240000_create_reopen_and_unlock_requests.php'))->up();"""),
("""    public function test_a_period_is_locked_and_unlocked_from_the_screen_with_a_reason(): void""",
 """    public function test_a_period_is_locked_and_its_unlock_requested_from_the_screen_with_a_reason(): void"""),
("""        $this->lock('2026-09', ['reason' => 'Restatement approved, board minute 12.', 'unlock' => 1]);
        $this->assertStringContainsString('Period 2026-09 unlocked', session('success'));
        $this->assertFalse(\\App\\Support\\ReportingPeriodLock::isLocked('2026-09'));""",
 """        // one person asks; the period stays locked until a second approves (PeriodUnlockService)
        $this->lock('2026-09', ['reason' => 'Restatement approved, board minute 12.', 'unlock' => 1]);
        $this->assertStringContainsString('Unlock of period 2026-09 requested', session('success'));
        $this->assertTrue(\\App\\Support\\ReportingPeriodLock::isLocked('2026-09'));
        $this->assertSame('PROPOSED', DB::table('period_unlock_requests')->value('status'));"""),
("""        Schema::create('fli_run_snapshots', function (Blueprint $t) { $t->increments('id'); $t->string('status'); });
""",
 """        Schema::create('fli_run_snapshots', function (Blueprint $t) { $t->increments('id'); $t->string('status'); });
        foreach (['restructure_events', 'rate_reset_events', 'takeon_build_requests', 'staging_thresholds'] as $table) {
            Schema::create($table, function (Blueprint $t) { $t->increments('id'); $t->string('status'); });
        }
        Schema::create('eir_reopen_requests_unused', function (Blueprint $t) { $t->increments('id'); });
"""),
]
for a, b in reps:
    assert a in s, a[:70]
    s = s.replace(a, b, 1)
s = s.replace("""        Schema::create('eir_reopen_requests_unused', function (Blueprint $t) { $t->increments('id'); });
""", "")
open(p, 'w', encoding='utf-8', newline='').write(s)
print('done')
