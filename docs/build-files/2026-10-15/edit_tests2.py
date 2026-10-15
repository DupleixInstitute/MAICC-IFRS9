def edit(p, pairs):
    s = open(p, encoding='utf-8', newline='').read()
    nl = '\r\n' if '\r\n' in s else '\n'
    s = s.replace('\r\n', '\n')
    for old, new in pairs:
        assert old in s, (p, old[:80])
        s = s.replace(old, new, 1)
    open(p, 'w', encoding='utf-8', newline='').write(s.replace('\n', nl))


edit(r'C:\xampp\htdocs\MAICC-IFRS9\tests\Feature\FLI\FliCloseDiscountedRecordTest.php', [
("""use App\\Services\\Fli\\FliCloseService;""", """use App\\Services\\Eir\\GovernanceService;
use App\\Services\\Fli\\FliCloseService;
use App\\Services\\Fli\\FliWithoutModel;"""),
("""        $close->signOff((int) $s['id'], 3, 'commentary');
    }
}""", """        $close->signOff((int) $s['id'], 3, 'commentary');
    }

    /**
     * No model version in force, the setting fli_without_model at Proceed (14 October 2026, D-F1 note):
     * the record says so, the IFRS 9 note and the forward-looking reports disclose it, and the sign-off
     * needs a commentary saying so.
     */
    public function test_a_month_with_no_model_is_recorded_as_such_and_signed_off_only_with_a_commentary(): void
    {
        DB::table('loan_books')->where('contract_id', 'C')->delete(); // no loan left undiscounted: only the model asks for a commentary
        $close = app(FliCloseService::class);
        $s = $close->record('2026-09', 2);

        $this->assertSame(FliWithoutModel::NOTE, $s['inputs']['without_model']['note']);
        $this->assertSame(FliWithoutModel::PROCEED, $s['inputs']['without_model']['setting']);
        $this->assertNull($s['model_version_id']);
        $this->assertTrue($close->latest('2026-09')['without_model']);
        $this->assertStringContainsString(FliWithoutModel::NOTE, (string) FliWithoutModel::disclosure('2026-09'));
        try {
            $close->signOff((int) $s['id'], 3);
            $this->fail('signed off with no model in force and no commentary');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(FliWithoutModel::NOTE, $e->getMessage());
        }
        $close->signOff((int) $s['id'], 3, 'No forward-looking model version is in force: the fit awaits approval; the PD holds.');
        $this->assertSame('SIGNED_OFF', DB::table('fli_run_snapshots')->where('id', $s['id'])->value('status'));
    }

    public function test_under_refuse_a_month_with_no_model_is_not_recorded(): void
    {
        DB::table('governance_settings')->where('key', FliWithoutModel::KEY)->update(['value' => FliWithoutModel::REFUSE]);
        app()->forgetInstance(GovernanceService::class);
        try {
            app(FliCloseService::class)->record('2026-09', 2);
            $this->fail('recorded under Refuse');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('refuses the month-end until one is', $e->getMessage());
        }
        $this->assertSame(0, DB::table('fli_run_snapshots')->count());
    }

    /**
     * A month-end that stopped part-way (14 October 2026): the PD step re-ran after the record, so the
     * record on screen is not the month any more; it is shown as not current and cannot be signed off.
     */
    public function test_a_record_made_before_a_step_ran_again_is_not_current_and_cannot_be_signed_off(): void
    {
        $close = app(FliCloseService::class);
        $s = $close->record('2026-09', 2);
        $this->assertNull($close->latest('2026-09')['stale']);
        DB::table('audit_logs')->insert(['action' => 'PD Segment Run', 'entity_type' => 'segment_parameter_runs', 'reporting_period' => '2026-09',
            'created_at' => now()->addMinute(), 'updated_at' => now()->addMinute()]);

        $latest = $close->latest('2026-09');
        $this->assertStringContainsString('not current', (string) $latest['stale']);
        $this->assertStringContainsString('the PD run', (string) $latest['stale']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not current');
        $close->signOff((int) $s['id'], 3, 'C waits for its EIR to be locked.');
    }
}"""),
])
print('ok')
