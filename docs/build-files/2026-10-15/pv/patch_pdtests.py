p = 'C:/xampp/htdocs/MAICC-IFRS9/tests/Feature/Pd/PdAndLgdEngineTest.php'
s = open(p, encoding='utf-8').read()
anchor = "    public function test_an_empty_cohort_is_refused_rather_than_an_lgd_invented(): void"
new = r"""    /**
     * Verification of 14 October 2026: a written-off account is left in the
     * book at a nil balance, where it stages as current, and was read as a
     * cure (COMSIV, Mwachisosa, Walusiya and Kayola in June 2026). A loan the
     * window closes at nil has not cured: its outcome is the cash it returned.
     * A Stage 3 loan that owed nothing at the start is no member of the cohort.
     */
    public function test_a_loan_closed_at_nil_is_not_a_cure_and_a_nil_start_balance_is_no_member(): void
    {
        Schema::table('loan_books', function (Blueprint $t) { $t->decimal('repayments', 20, 2)->default(0); });
        DB::table('loan_books')->where('contract_id', 'D')->where('reporting_period', '2026-08')->update(['repayments' => 250]);
        // F: Stage 3, 500 owing; written off in the window, left in the book at nil and staged current
        $this->loan('F', '2025-08', '3', '3', 500);
        $this->loan('F', '2026-08', '1', '1', 0);
        // G: Stage 3 with nothing owed at the start
        $this->loan('G', '2025-08', '3', '3', 0);
        $this->loan('G', '2026-08', '1', '1', 0);
        // H: Stage 3, 400 owing, repaid in full in the window: a full recovery, not a cure
        $this->loan('H', '2025-08', '3', '3', 400);
        $this->loan('H', '2026-08', '1', '1', 0);
        DB::table('loan_books')->where('contract_id', 'H')->where('reporting_period', '2026-08')->update(['repayments' => 450]);

        $m = (new LgdEngineService())->measure('2026-08', [1], 12, '2025-08');
        $this->assertSame(5, $m['cohort'], 'C, D, E, F and H; G owed nothing');
        $this->assertEquals(2400.0, $m['start_balance']);
        $this->assertEqualsWithDelta(500 / 2400, $m['cure_rate'], 1e-9, 'only C cured: F and H closed at nil');
        // of the 1,900 that did not cure: D returned 250, H 400 (capped at its balance), E and F nothing
        $this->assertEqualsWithDelta(650 / 1900, $m['recovery_rate'], 1e-9);
        $this->assertEqualsWithDelta(1000.0, $m['written_off'], 1e-6, 'E absent and F closed at nil: 500 each');
    }

"""
assert s.count(anchor) == 1
s = s.replace(anchor, new + anchor)
open(p, 'w', encoding='utf-8', newline='\n').write(s)

p = 'C:/xampp/htdocs/MAICC-IFRS9/tests/Feature/Pd/PdSegmentationTest.php'
s = open(p, encoding='utf-8').read()
old = "            $t->string('lgd_applied_segment_key')->nullable(); $t->integer('lgd_source_id')->nullable();\n        });"
assert s.count(old) == 1
s = s.replace(old, "            $t->string('lgd_applied_segment_key')->nullable(); $t->integer('lgd_source_id')->nullable(); $t->text('build_basis')->nullable();\n        });")
anchor = "    public function test_the_governed_options_parse(): void"
new = r"""    /**
     * Verification of 14 October 2026. A loan with a nil or credit balance at
     * the window start is no observation (ten such loans counted towards the
     * governed minimum of MAIIC's Stage 1 cohort, and a credit balance shrank
     * the balance the defaults are weighed against). A loan written off in the
     * window defaulted, though the written-off account is left in the book at
     * nil and stages as current, or leaves the book with a last row at nil.
     */
    public function test_a_nil_start_balance_is_no_observation_and_a_write_off_in_the_window_is_a_default(): void
    {
        $basis = fn (float $w) => json_encode(['ledger' => ['written_off' => $w]]);
        $add = function (string $id, string $period, string $stage, float $ca, float $writtenOff) use ($basis) {
            $this->loan($id, $period, 3, $stage, $ca, '3-3. Manufacturing', '1079');
            DB::table('loan_books')->where('contract_id', $id)->where('reporting_period', $period)->update(['build_basis' => $basis($writtenOff)]);
        };
        DB::table('loan_portfolios')->insert(['id' => 3, 'name' => 'Gamma']);
        $add('C-ok', '2025-08', '1', 1000, 0); $add('C-ok', '2026-08', '1', 900, 0);
        $add('C-wo', '2025-08', '1', 1000, 0); $add('C-wo', '2026-08', '1', 0, 950);         // written off, left at nil, staged current
        $add('C-gone', '2025-08', '1', 1000, 0); $add('C-gone', '2026-02', '1', 0, 980);    // written off, then gone: its last row is at nil
        $add('C-nil', '2025-08', '1', 0, 0); $add('C-nil', '2026-08', '1', 0, 0);
        $add('C-credit', '2025-08', '1', -50, 0); $add('C-credit', '2026-08', '1', -50, 0);

        $m = app(PdSegmentationService::class)->measure('2026-08', 'portfolio', 12, [3]);
        $c = $m['segments']['portfolio:3']['stages']['1'];
        $this->assertSame(3, $c['loans'], 'the nil and the credit balance are no observation');
        $this->assertEquals(3000.0, $c['balance']);
        $this->assertSame(2, $c['default_loans'], 'both written-off loans defaulted');
        $this->assertEqualsWithDelta(2 / 3, $c['observed'], 1e-9);
        $this->assertSame(2, $m['not_observed_nil_balance']);
        $this->assertSame(2, $m['defaults_by_write_off']);
    }

"""
assert s.count(anchor) == 1
s = s.replace(anchor, new + anchor)
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
