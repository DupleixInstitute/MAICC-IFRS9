import io
p = 'tests/Feature/FLI/FliRouteServiceTest.php'
s = io.open(p, encoding='utf-8', newline='').read()
nl = '\r\n' if '\r\n' in s else '\n'


def rep(old, new, count=1):
    global s
    old = old.replace('\n', nl)
    new = new.replace('\n', nl)
    assert s.count(old) == count, (s.count(old), old[:90])
    s = s.replace(old, new)


rep("""use App\\Services\\Fli\\OverlayService;
""", """use App\\Services\\Fli\\OverlayService;
use App\\Services\\Fli\\RegressionEngine;
""")
rep("""use App\\Services\\Scenario\\ScenarioSetService;
use Illuminate""", """use App\\Services\\Scenario\\ScenarioSetService;
use App\\Support\\Fli\\GovernedValues;
use Illuminate""")
rep("""            ['contract_id' => 'B', 'reporting_period' => '2026-08', 'ifrs9stage_post_qualitative' => '3', 'pd_prefli' => 0.30],
        ]);
    }
""", """            ['contract_id' => 'B', 'reporting_period' => '2026-08', 'ifrs9stage_post_qualitative' => '3', 'pd_prefli' => 0.30],
        ]);
        // what a fit is checked against before it is proposed or approved (UI audit item 3): the governed
        // guardrail settings its inputs hash is taken under, the credit-loss measure it was estimated on, the
        // governed forecast horizon the projection reads, and each fit stamped with the hash of the data held
        GovernedValues::ensureDefaults('sqlite');
        foreach (['202512' => 0.28, '202601' => 0.29, '202607' => 0.30] as $p => $v) {
            DB::table('credit_loss_series')->insert(['proxy_code' => 'STAGE3_SHARE', 'proxy_name' => 'Stage 3 share', 'observation_period' => $p, 'value' => $v]);
        }
        DB::table('governance_settings')->insert(['key' => 'fli_forecast_horizon', 'value' => '3 years, then 2 years back to the long-run PD', 'options' => json_encode(['3 years, then 2 years back to the long-run PD']), 'label' => 'horizon', 'description' => '', 'effective_from' => '1900-01-01', 'status' => 'APPROVED', 'created_at' => now(), 'updated_at' => now()]);
        $this->stampFit(10);
        $this->stampFit(11);
    }

    /** The inputs hash a sweep would record for the fit on the data now held, as at its period. */
    private function stampFit(int $id): void
    {
        $f = DB::table('fli_fits as f')->join('fli_relationships as r', 'r.id', '=', 'f.fli_relationship_id')->where('f.id', $id)->first(['f.reporting_period', 'r.statistic_code', 'r.proxy_code', 'r.lag_months']);
        $hash = (new RegressionEngine('sqlite', new GovernedValues($f->reporting_period, 'sqlite')))->currentInputsHash($f->reporting_period, $f->statistic_code, $f->proxy_code, (int) $f->lag_months);
        $this->assertNotNull($hash, "fit {$id}: the fixture holds its driver and its measure");
        DB::table('fli_fits')->where('id', $id)->update(['inputs_hash' => $hash, 'computed_at' => now()]);
    }
""")
rep("""            $this->assertStringContainsString('declined by the guardrail', $e->getMessage());
        }
        $this->service()->proposeFit(10, 1);""", """            $this->assertStringContainsString('declined by the guardrail', $e->getMessage());
        }
        $this->approvedSet();
        $this->service()->proposeFit(10, 1);""")
rep("""    /** A fit for January 2026 on PLR, lag in months, approved by two people; one Stage 1 loan in January. */
    private function januaryFit(int $lag): void
    {
        DB::table('fli_relationships')->insert(['id' => 2, 'statistic_code' => 'PLR', 'proxy_code' => 'STAGE3_SHARE', 'r2_cutoff' => 0.3, 'lag_months' => $lag, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('fli_fits')->insert(['id' => 20, 'fli_relationship_id' => 2, 'reporting_period' => '202601', 'slope' => 0.01, 'intercept' => 0.1, 'correlation_r' => 0.9, 'r_squared' => 0.81, 'n_obs' => 24, 'verdict' => 'applied']);
        $this->service()->proposeFit(20, 1);
        $this->service()->approveFit(20, 2);
        DB::table('loan_books')->insert(['contract_id' => 'E', 'reporting_period' => '2026-01', 'ifrs9stage_post_qualitative' => '1', 'pd_prefli' => 0.30, 'remaining_tenor' => 24]);
    }""", """    /**
     * A fit for January 2026 on PLR, lag in months, estimated on the data held; with the approved set of
     * January it is proposed and approved by two people, or (propose false) published as approved, as a
     * version published before its data went missing would be. One Stage 1 loan in January. Returns the set.
     */
    private function januaryFit(int $lag, bool $propose = true): int
    {
        DB::table('fli_relationships')->insert(['id' => 2, 'statistic_code' => 'PLR', 'proxy_code' => 'STAGE3_SHARE', 'r2_cutoff' => 0.3, 'lag_months' => $lag, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('fli_fits')->insert(['id' => 20, 'fli_relationship_id' => 2, 'reporting_period' => '202601', 'slope' => 0.01, 'intercept' => 0.1, 'correlation_r' => 0.9, 'r_squared' => 0.81, 'n_obs' => 24, 'verdict' => 'applied']);
        $this->stampFit(20);
        $setId = $this->approvedSet('2026-01');
        if ($propose) {
            $this->service()->proposeFit(20, 1);
            $this->service()->approveFit(20, 2);
        } else {
            DB::table('fli_fits')->where('id', 20)->update(['approval_status' => 'APPROVED', 'proposed_by' => 1, 'approved_by' => 2, 'approved_at' => now()]);
            (new \\App\\Services\\Fli\\FliModelRegistry())->publishFromFit(20, 2);
        }
        DB::table('loan_books')->insert(['contract_id' => 'E', 'reporting_period' => '2026-01', 'ifrs9stage_post_qualitative' => '1', 'pd_prefli' => 0.30, 'remaining_tenor' => 24]);

        return $setId;
    }""")
rep("""        $this->januaryFit(9);
        $setId = $this->approvedSet('2026-01');
        $r = $this->service()->apply('2026-01', 1);""", """        $setId = $this->januaryFit(9);
        $r = $this->service()->apply('2026-01', 1);""")
rep("""        $this->januaryFit(12);
        $this->approvedSet('2026-01');
        $r = $this->service()->apply('2026-01', 1);""", """        $this->januaryFit(12);
        $r = $this->service()->apply('2026-01', 1);""")
rep("""    /** No actual at P - lag known at the period: the route refuses, and no loan is touched. */
    public function test_the_route_fails_closed_when_the_lagged_base_is_not_known_at_the_period(): void
    {
        DB::table('macro_series')->insert(['statistic_code' => 'PLR', 'observation_period' => '202601', 'value' => 25.3, 'value_type' => 'actual']);
        $this->januaryFit(9);
        $this->approvedSet('2026-01');
        try {""", """    /**
     * No actual at P - lag known at the period: the route refuses, and no loan is touched. Such a fit can
     * no longer be proposed or approved (see the fit-applicability tests below); the route still fails
     * closed for a version published before, or one whose data was withdrawn after it was published.
     */
    public function test_the_route_fails_closed_when_the_lagged_base_is_not_known_at_the_period(): void
    {
        DB::table('macro_series')->insert(['statistic_code' => 'PLR', 'observation_period' => '202601', 'value' => 25.3, 'value_type' => 'actual']);
        $this->januaryFit(9, false);
        try {""")
rep("""    public function test_an_approved_fit_is_a_version_in_force_from_its_month_until_a_later_one(): void
    {
        $this->service()->proposeFit(10, 1);""", """    public function test_an_approved_fit_is_a_version_in_force_from_its_month_until_a_later_one(): void
    {
        $this->approvedSet();
        $this->service()->proposeFit(10, 1);""")
rep("""        DB::table('credit_loss_series')->insert(['proxy_code' => 'STAGE3_SHARE', 'proxy_name' => 'Stage 3 share', 'observation_period' => '202608', 'value' => 0.3]);
        $this->service()->proposeFit(10, 1);""", """        DB::table('credit_loss_series')->insert(['proxy_code' => 'STAGE3_SHARE', 'proxy_name' => 'Stage 3 share', 'observation_period' => '202608', 'value' => 0.3]);
        $this->stampFit(10); // the fit as a sweep records it on the August measure just added
        $this->approvedSet();
        $this->service()->proposeFit(10, 1);""")
io.open(p, 'w', encoding='utf-8', newline='').write(s)
print('ok')
