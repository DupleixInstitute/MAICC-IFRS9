p = 'C:/xampp/htdocs/MAICC-IFRS9/tests/Feature/DashboardChartsTest.php'
s = open(p, encoding='utf-8').read()

def rep(old, new):
    global s
    assert s.count(old) == 1, old[:80]
    s = s.replace(old, new)

rep("""                ->where('scenarioEcl.source', 'month_end')
                ->where('scenarioEcl.set.bootstrap', true)
                // the record covers the 640 of the three loans; portfolios 903 to 909 carry 28 of ECL runs it never saw
                ->where('scenarioEcl.ties', false)
                ->where('scenarioEcl.series.2.name', 'Probability-weighted')
                ->where('defaultRates.rows.1.period', '2026-02'));""", """                // stress results only for February: January is listed as having none, never filled
                ->where('scenarioEcl.periods', ['2026-02'])
                ->where('scenarioEcl.missing', ['2026-01'])
                ->where('scenarioEcl.by_period.2026-02.source', 'month_end')
                ->where('scenarioEcl.by_period.2026-02.set.bootstrap', true)
                // the record covers the 640 of the three loans; portfolios 903 to 909 carry 28 of ECL runs it never saw
                ->where('scenarioEcl.by_period.2026-02.ties', false)
                ->where('scenarioEcl.by_period.2026-02.series.2.name', 'Probability-weighted')
                ->where('defaultRates.rows.1.period', '2026-02')
                ->where('eclMixTrend.range', ['from' => null, 'to' => null]));""")

rep("""        $s = collect($props['scenarioEcl']['series'])->keyBy('name');""", """        $feb = $props['scenarioEcl']['by_period']['2026-02'];
        $s = collect($feb['series'])->keyBy('name');""")
rep("""        $this->assertSame([2, 0, 1, 3], $props['scenarioEcl']['loans']);

        $feb = $props['defaultRates']['rows'][1];""", """        $this->assertSame([2, 0, 1, 3], $feb['loans']);

        $feb = $props['defaultRates']['rows'][1];""")
rep("""        $this->assertEqualsWithDelta(24.0, collect($props['scenarioEcl']['series'])->firstWhere('name', 'Base')['values'][3], 0.001);
        $this->assertTrue($props['scenarioEcl']['ties']);   // weighted (24 + 36) / 2 = 30, Beta's total ECL""",
    """        $beta = $props['scenarioEcl']['by_period']['2026-02'];
        $this->assertEqualsWithDelta(24.0, collect($beta['series'])->firstWhere('name', 'Base')['values'][3], 0.001);
        $this->assertTrue($beta['ties']);   // weighted (24 + 36) / 2 = 30, Beta's total ECL for February""")
rep("""        // January has no record and no saved stress run: an honest empty state
        $props = $this->actingAs($u)->get(route('dashboard', ['period' => '2026-01']))->viewData('page')['props'];
        $this->assertNull($props['scenarioEcl']['source']);
        $this->assertSame('No stress run or forward-looking month-end record for Jan 2026.', $props['scenarioEcl']['note']);
    }""", """        // January has no record and no saved stress run: an honest empty state
        $props = $this->actingAs($u)->get(route('dashboard', ['period' => '2026-01']))->viewData('page')['props'];
        $this->assertSame([], $props['scenarioEcl']['periods']);
        $this->assertSame(['2026-01'], $props['scenarioEcl']['missing']);
        $this->assertSame([], $props['scenarioEcl']['by_period']);
    }

    public function test_each_chart_keeps_its_own_range(): void
    {
        $this->seedBook();
        $this->seedMonthEndRecord();
        $u = $this->user();

        $props = $this->actingAs($u)->get(route('dashboard', ['period' => '2026-02', 'pf_from' => '2026-02', 'dr_to' => '2026-01', 'st_from' => 'all']))->viewData('page')['props'];
        $this->assertCount(2, $props['eclTrends']);                            // the trend keeps its own default range
        $this->assertSame(['2026-02'], $props['eclMixTrend']['periods']);
        $this->assertSame(['from' => '2026-02', 'to' => null], $props['eclMixTrend']['range']);
        $this->assertTrue($props['eclMixTrend']['ties']);
        $this->assertSame(['2026-01'], array_column($props['defaultRates']['rows'], 'period'));
        $this->assertSame(['from' => null, 'to' => '2026-01'], $props['defaultRates']['range']);
        $this->assertSame(['2026-01', '2026-02'], $props['scenarioEcl']['range_periods']);
        $this->assertSame(['from' => 'all', 'to' => null], $props['scenarioEcl']['range']);

        // a partial reload of one chart carries that chart alone
        $version = $this->actingAs($u)->get(route('dashboard'))->viewData('page')['version'];
        $this->actingAs($u)->get(route('dashboard', ['period' => '2026-02', 'pf_from' => '2026-02']), [
            'X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Inertia-Partial-Component' => 'Dashboard', 'X-Inertia-Partial-Data' => 'eclMixTrend',
        ])->assertOk()->assertJsonPath('props.eclMixTrend.periods', ['2026-02'])
            ->assertJsonMissingPath('props.scenarioEcl')->assertJsonMissingPath('props.defaultRates')->assertJsonMissingPath('props.summary');
    }""")
open(p, 'w', encoding='utf-8', newline='\n').write(s)
print('ok')
