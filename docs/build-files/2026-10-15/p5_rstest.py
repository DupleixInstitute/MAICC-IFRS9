p = r'C:\xampp\htdocs\MAICC-IFRS9\tests\Feature\Eir\ResetScheduleServiceTest.php'
s = open(p, encoding='utf-8').read()
old = """        $this->assertSame('BLOCKED', $result['details'][self::JAT]['status']);
        $this->assertStringStartsWith('NO_VERSION_1', $result['details'][self::JAT]['reason']);
    }
}"""
new = """        $this->assertSame('BLOCKED', $result['details'][self::JAT]['status']);
        $this->assertStringStartsWith('NO_VERSION_1', $result['details'][self::JAT]['reason']);
    }

    /**
     * Only a floating loan's prime-rate resets are re-amortised: a spread change, a move the
     * register holds from the pipeline and a reset approved as negotiated go through the
     * registers; a reset after a confirmed restructure is held (reconciliation of 14 Oct 2026).
     */
    public function test_only_the_pipelines_prime_rate_resets_are_built_and_none_past_a_restructure(): void
    {
        $base = ['contract_id' => self::JAT, 'old_reference_rate' => 25.3, 'new_reference_rate' => 25.3, 'classification_status' => 'DETECTED'];
        DB::table('rate_reset_events')->insert([
            $base + ['reset_date' => '2025-12-01', 'event_type' => 'SPREAD_CHANGE', 'status' => 'DETECTED', 'detection_key' => 'SPREAD|' . self::JAT . '|2025-12'],
            $base + ['reset_date' => '2025-12-15', 'event_type' => 'PLR_RESET', 'status' => 'BLOCKED', 'detection_key' => 'MOVE|' . self::JAT . '|2025-12'],
        ]);
        RateResetEvent::whereDate('reset_date', '2026-06-03')->update(['classification' => 'NEGOTIATED', 'classification_status' => 'APPROVED']);
        Schema::create('contract_modifications', function (Blueprint $t) {
            $t->increments('id'); $t->string('contract_id'); $t->string('modification_date'); $t->string('status');
        });
        DB::table('contract_modifications')->insert(['contract_id' => self::JAT, 'modification_date' => '2026-08-01', 'status' => 'APPLIED']);

        $result = $this->build();

        $states = collect($result['details'][self::JAT]['versions'])->keyBy('reset_date');
        $this->assertArrayNotHasKey('2025-12-01', $states->all(), 'a spread change is classified on the register');
        $this->assertArrayNotHasKey('2025-12-15', $states->all(), 'a held move carries no pipeline rate');
        $this->assertArrayNotHasKey('2026-06-03', $states->all(), 'approved as negotiated: the restructure register carries it');
        $this->assertSame(8, $result['built']);
        $this->assertSame(['BLOCKED', 'BLOCKED'], [$states['2026-08-05']['state'], $states['2026-09-03']['state']]);
        $this->assertStringStartsWith('RESET_AFTER_RESTRUCTURE', $states['2026-08-05']['reason']);
        $this->assertSame('BLOCKED', DB::table('rate_reset_events')->where('detection_key', 'MOVE|' . self::JAT . '|2025-12')->value('status'));

        // a loan the contract master does not state as floating gets no Reset version
        DB::table('contract_eir')->where('contract_id', self::JAT)->update(['rate_type' => 'FIXED']);
        $this->assertStringStartsWith('NOT_FLOATING', $this->build()['details'][self::JAT]['reason']);
    }
}"""
assert old in s
s = s.replace(old, new, 1)
for imp in ['use Illuminate\\Database\\Schema\\Blueprint;\n', 'use Illuminate\\Support\\Facades\\Schema;\n']:
    if imp not in s:
        s = s.replace('use Illuminate\\Support\\Facades\\DB;\n', 'use Illuminate\\Support\\Facades\\DB;\n' + imp, 1) if 'Schema;' in imp else s.replace('use Illuminate\\Support\\Facades\\DB;\n', imp + 'use Illuminate\\Support\\Facades\\DB;\n', 1)
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
