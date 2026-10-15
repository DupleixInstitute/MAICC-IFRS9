import re
base = r'C:\xampp\htdocs\MAICC-IFRS9\tests\Feature\Eir\\'

old_ce = """        Schema::create('contract_eir', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id')->unique(); $t->string('rate_type')->default('FIXED'); $t->timestamps(); });"""
new_ce = """        Schema::create('contract_eir', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id')->unique(); $t->string('rate_type')->default('FIXED'); $t->string('instrument_type')->nullable(); $t->date('origination_date')->nullable(); $t->date('maturity_date')->nullable(); $t->double('contractual_rate')->nullable(); $t->double('markup')->nullable(); $t->string('sub_account_no')->nullable(); $t->timestamps(); });"""
old_mig = """        (require base_path('database/migrations/2026_10_14_230000_restructure_register_and_modifications.php'))->up();"""
new_mig = """        // the two migrations that share rate_reset_events, in the order a fresh install runs them
        (require base_path('database/migrations/2026_10_01_000000_create_eir_rate_periods.php'))->up();
        (require base_path('database/migrations/2026_10_14_230000_restructure_register_and_modifications.php'))->up();"""
old_f1 = """        DB::table('contract_eir')->insert(['contract_id' => 'F1', 'rate_type' => 'FLOATING', 'created_at' => now(), 'updated_at' => now()]);"""
new_f1 = """        // drawn 1 July 2025 at 31.3 percent with the PLR at 25.3: the spread locked at drawdown is 6.0
        DB::table('contract_eir')->insert(['contract_id' => 'F1', 'rate_type' => 'FLOATING', 'origination_date' => '2025-07-01', 'maturity_date' => '2030-06-30', 'contractual_rate' => 0.313, 'created_at' => now(), 'updated_at' => now()]);"""

for name in ['RateResetTest.php', 'RestructureDetectionWiringTest.php']:
    p = base + name
    s = open(p, encoding='utf-8').read()
    for o, n in [(old_ce, new_ce), (old_mig, new_mig), (old_f1, new_f1)]:
        if o not in s:
            raise SystemExit(name + ' missing: ' + o[:70])
        s = s.replace(o, n, 1)
    open(p, 'w', encoding='utf-8', newline='\n').write(s)

# RateResetTest expectations: the pipeline's reset is dated under plr_mid_period; maker-checker state is classification_status
p = base + 'RateResetTest.php'
s = open(p, encoding='utf-8').read()
reps = [
    (""" * The writer of rate_reset_events and its maker-checker intake (spec v4 P5):
 * a floating loan's move with the PLR is a reset, a move of its spread is for
 * a person to classify, a fixed-rate loan's move is a restructure candidate;
 * one person proposes, another approves; a locked month is refused; a
 * negotiated move becomes a restructure candidate.""",
     """ * The rate-reset register and its maker-checker intake (spec v4 P5), on the
 * one detector: the reset pipeline dates each prime-rate reset once, the
 * register sets it against the loan book; a move of the spread is its own
 * event for a person to classify, a fixed-rate loan's move is a restructure
 * candidate; one person proposes, another approves (classification_status;
 * status is the pipeline's); a locked month is refused; a negotiated move
 * becomes a restructure candidate."""),
    ("""        $this->assertSame('PLR_RESET', $f[0]->event_type);
        $this->assertSame('2026-02-04', $f[0]->reset_date, 'dated by the PLR\\'s effective date');""",
     """        $this->assertSame('PLR_RESET', $f[0]->event_type);
        $this->assertSame('2026-02-01', $f[0]->reset_date, 'the whole month at the new rate (plr_mid_period default, section 3.1)');
        $this->assertSame('2026-02-04', $f[0]->plr_effective_date, 'the PLR\\'s effective date is kept');
        $this->assertSame('PLR|F1|2026-02-01', $f[0]->detection_key, 'the pipeline\\'s row, carrying the loan book\\'s evidence');
        $this->assertEqualsWithDelta(30.0, $f[0]->new_rate, 1e-9);
        $this->assertEqualsWithDelta(0.30, $f[0]->new_contractual_rate, 1e-9);
        $this->assertSame('0000F1', $f[0]->account);"""),
    ("""        $this->assertNull($f[1]->suggested_classification);
        $this->assertSame('DETECTED', $f[1]->status);""",
     """        $this->assertNull($f[1]->suggested_classification);
        $this->assertSame('DETECTED', $f[1]->classification_status);"""),
    ("""        $this->assertSame(2, DB::table('rate_reset_events')->count());
        $this->assertSame(0, DB::table('rate_reset_events')->where('status', '!=', 'DETECTED')->count());""",
     """        $this->assertSame(2, DB::table('rate_reset_events')->count());
        $this->assertSame(0, DB::table('rate_reset_events')->where('classification_status', '!=', 'DETECTED')->count());
        $this->assertSame(2, DB::table('eir_rate_periods')->where('contract_id', 'F1')->count(), 'the pipeline\\'s rate periods: drawdown, then February');"""),
    ("""        $this->assertSame('APPROVED', $r->status);""", """        $this->assertSame('APPROVED', $r->classification_status);"""),
    ("""        $this->assertSame('DETECTED', DB::table('rate_reset_events')->where('id', $spread)->value('status'));""",
     """        $this->assertSame('DETECTED', DB::table('rate_reset_events')->where('id', $spread)->value('classification_status'));"""),
]
for o, n in reps:
    if o not in s:
        raise SystemExit('RateResetTest missing: ' + o[:70])
    s = s.replace(o, n, 1)

extra = """
    /**
     * One detector (reconciliation of 14 October 2026): the pipeline's detector and the register
     * run in either order, any number of times, and each reset is one row; a PLR reset and a
     * spread change in the same month are two events on one date; a fixed-rate loan whose rate
     * moved with the PLR is recorded, held from the pipeline with the reason.
     */
    public function test_each_reset_is_detected_once_and_both_legs_of_one_month_are_two_events(): void
    {
        // F2: the PLR fell 1.3 points in February and MAIIC raised the spread by 0.5 in the same month
        DB::table('contract_eir')->insert(['contract_id' => 'F2', 'rate_type' => 'FLOATING', 'origination_date' => '2025-07-01', 'maturity_date' => '2030-06-30', 'contractual_rate' => 0.313, 'created_at' => now(), 'updated_at' => now()]);
        // M1: Interest Policy M, the engine's rate type FIXED, re-priced with the PLR in February
        DB::table('contract_eir')->insert(['contract_id' => 'M1', 'rate_type' => 'FIXED', 'origination_date' => '2025-07-01', 'maturity_date' => '2030-06-30', 'contractual_rate' => 0.313, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['F2' => ['2026-01' => 31.3, '2026-02' => 30.5], 'M1' => ['2026-01' => 31.3, '2026-02' => 30.0]] as $c => $months) {
            foreach ($months as $p => $r) {
                DB::table('loan_books')->insert(['contract_id' => $c, 'reporting_period' => $p, 'customer_name' => "Loan {$c}", 'external_identity_id' => "0000{$c}", 'interest_rate' => $r]);
            }
        }

        app(\\App\\Services\\Eir\\ResetDetectionService::class)->detect();
        $this->service()->detect();
        $this->service()->detect();
        app(\\App\\Services\\Eir\\ResetDetectionService::class)->detect();

        $this->assertSame(1, DB::table('rate_reset_events')->where('contract_id', 'F1')->where('event_type', 'PLR_RESET')->count(), 'detected once');
        $f2 = DB::table('rate_reset_events')->where('contract_id', 'F2')->orderBy('event_type')->get();
        $this->assertSame(['PLR_RESET', 'SPREAD_CHANGE'], $f2->pluck('event_type')->all());
        $this->assertSame(['2026-02-01', '2026-02-01'], $f2->pluck('reset_date')->all(), 'two events on one date: one classification each');
        $this->assertSame(['IN_CONTRACT', null], $f2->pluck('suggested_classification')->all());
        $this->assertEqualsWithDelta(6.5, $f2[1]->new_spread, 1e-9);
        $this->assertStringContainsString('prime plus the spread locked at drawdown gives 30%', $f2[0]->evidence);

        $m1 = DB::table('rate_reset_events')->where('contract_id', 'M1')->sole();
        $this->assertSame(['PLR_RESET', 'BLOCKED', 'DETECTED', 'MOVE|M1|2026-02'], [$m1->event_type, $m1->status, $m1->classification_status, $m1->detection_key]);
        $this->assertStringContainsString('fixed-rate', $m1->note);
        $this->assertSame(0, DB::table('restructure_events')->where('contract_id', 'M1')->count(), 'moved with the PLR: a reset to record, not a restructure');
    }

    /** Both migrations add each column only where it is missing, in either order and over the register's first version. */
    public function test_the_two_migrations_run_in_either_order_and_over_the_registers_first_version(): void
    {
        $columns = fn () => collect(Schema::getColumnListing('rate_reset_events'))->sort()->values()->all();
        $indexes = fn () => array_keys(Schema::getConnection()->getDoctrineSchemaManager()->listTableIndexes('rate_reset_events'));
        $expected = $columns();
        $this->assertContains('status', $expected);
        $this->assertContains('classification_status', $expected);
        $this->assertContains('uq_reset_contract_date_type', $indexes());
        $this->assertNotContains('uq_reset_contract_date', $indexes());

        $base = function () {
            Schema::dropIfExists('eir_rate_periods');
            Schema::dropIfExists('rate_reset_events');
            Schema::create('rate_reset_events', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->date('reset_date'); $t->decimal('old_reference_rate', 8, 5); $t->decimal('new_reference_rate', 8, 5); $t->unsignedSmallInteger('new_schedule_version'); $t->integer('recorded_by')->nullable(); $t->timestamps(); });
        };
        $a = fn () => (require base_path('database/migrations/2026_10_01_000000_create_eir_rate_periods.php'))->up();
        $b = fn () => (require base_path('database/migrations/2026_10_14_230000_restructure_register_and_modifications.php'))->up();

        // the register first, then the pipeline
        $base(); $b(); $a();
        $this->assertSame($expected, $columns());
        $this->assertContains('uq_reset_contract_date_type', $indexes());

        // the register's first version (its maker-checker state in `status`), then the pipeline
        $base();
        Schema::table('rate_reset_events', function (Blueprint $t) { $t->string('detection_key', 120)->nullable()->unique(); $t->string('event_type', 20)->nullable(); $t->string('status', 12)->default('DETECTED'); });
        DB::table('rate_reset_events')->insert(['contract_id' => 'F1', 'reset_date' => '2026-02-04', 'old_reference_rate' => 25.3, 'new_reference_rate' => 24.0, 'new_schedule_version' => 1,
            'detection_key' => 'RESET|F1|2026-02', 'event_type' => 'PLR_RESET', 'status' => 'PROPOSED']);
        $a(); $b();
        $this->assertSame($expected, $columns());
        $row = DB::table('rate_reset_events')->sole();
        $this->assertSame(['PROPOSED', 'DETECTED'], [$row->classification_status, $row->status], 'the person\\'s proposal is kept as the classification; the pipeline starts at DETECTED');

        // running either again changes nothing
        $a(); $b();
        $this->assertSame($expected, $columns());
    }
}"""
assert s.rstrip().endswith('}')
s = s.rstrip()[:-1].rstrip() + '\n' + extra + '\n'
open(p, 'w', encoding='utf-8', newline='\n').write(s)

# the wiring test reads the register's state
p = base + 'RestructureDetectionWiringTest.php'
s = open(p, encoding='utf-8').read()
o = """        $this->assertSame(['DETECTED'], DB::table('rate_reset_events')->distinct()->pluck('status')->all(), 'every move waits for a person');"""
assert o in s
s = s.replace(o, """        $this->assertSame(['DETECTED'], DB::table('rate_reset_events')->distinct()->pluck('classification_status')->all(), 'every move waits for a person');""", 1)
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
