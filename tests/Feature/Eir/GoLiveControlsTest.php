<?php

namespace Tests\Feature\Eir;

use App\Models\ContractEir;
use App\Models\StagingThreshold;
use App\Services\Ebanker\ContractInputsBuildService;
use App\Services\Ebanker\LoanBookBuildService;
use App\Services\Eir\EirLedgerCashService;
use App\Services\Eir\GovernanceService;
use App\Services\Eir\ScheduleWorkflowService;
use App\Services\Eir\StagingThresholdService;
use App\Services\Scenario\ScenarioSetService;
use App\Support\Tenor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use LogicException;
use Tests\Concerns\SeedsGovernedDefaults;
use Tests\TestCase;

/**
 * The go-live controls of 14 October 2026: the schedule's second person
 * (O18, audit H9), the staging thresholds under maker-checker (H11), the
 * contractual rate's unit (L5), the reversal pairing and the over-sanction
 * flag (M15, spec 7.1 and 7.3), the scheme settings from the scheme master
 * (spec v3 6.3), the scenario rules read as their options say, the tenor in
 * months (C2) and the settings recorded but not yet applied (M12).
 */
class GoLiveControlsTest extends TestCase
{
    use SeedsGovernedDefaults;

    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        $this->seedGovernedDefaults();
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        Schema::create('governance_setting_history', function (Blueprint $t) {
            $t->increments('id'); $t->integer('setting_id')->nullable(); $t->string('key'); $t->string('value'); $t->text('options')->nullable(); $t->string('label')->nullable(); $t->text('description')->nullable();
            $t->string('effective_from'); $t->integer('set_by')->nullable(); $t->integer('approved_by')->nullable(); $t->string('approved_at')->nullable(); $t->string('reason')->nullable(); $t->string('status')->nullable();
            $t->string('superseded_at')->nullable(); $t->integer('superseded_by')->nullable(); $t->integer('superseded_by_setting_id')->nullable(); $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id')->nullable(); $t->string('action'); $t->string('entity_type'); $t->integer('entity_id')->nullable(); $t->string('scope')->nullable(); $t->string('reporting_period')->nullable(); $t->integer('rows_affected')->nullable(); $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->text('meta')->nullable(); $t->string('ip_address')->nullable(); $t->string('user_agent')->nullable(); $t->timestamps(); });
    }

    // ----- the schedule's second person (O18) -----------------------------

    private function scheduleFixture(): ContractEir
    {
        Schema::create('contract_eir', function (Blueprint $t) {
            $t->increments('id'); $t->string('contract_id'); $t->string('schedule_approval_status')->default('NOT_GENERATED'); $t->string('schedule_comparison_status')->nullable();
            $t->text('schedule_review_notes')->nullable(); $t->timestamp('schedule_approved_at')->nullable(); $t->integer('schedule_approved_by')->nullable();
            $t->integer('schedule_prepared_by')->nullable(); $t->string('schedule_approver_label')->nullable(); $t->string('emi_calc_type')->nullable(); $t->string('scheme_code')->nullable();
            $t->string('rate_type')->nullable(); $t->string('interest_policy')->nullable(); $t->date('origination_date')->nullable(); $t->timestamps();
        });
        Schema::create('contract_cashflow_schedule', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->integer('schedule_version'); $t->date('due_date'); $t->double('principal_due'); $t->double('interest_due'); $t->double('fee_due')->default(0); $t->string('schedule_source')->nullable(); $t->timestamps(); });
        Schema::create('contract_remaining_cashflow_schedule', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->date('due_date'); $t->double('principal_due'); $t->double('interest_due'); $t->double('fee_due')->default(0); });
        DB::table('contract_eir')->insert(['contract_id' => 'C1', 'schedule_approval_status' => 'DRAFT', 'schedule_prepared_by' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('contract_cashflow_schedule')->insert(['contract_id' => 'C1', 'schedule_version' => 1, 'due_date' => '2026-01-31', 'principal_due' => 100, 'interest_due' => 10, 'schedule_source' => 'GENERATED']);

        return ContractEir::where('contract_id', 'C1')->firstOrFail();
    }

    public function test_the_person_who_prepared_a_schedule_cannot_approve_it(): void
    {
        $contract = $this->scheduleFixture();
        $workflow = app(ScheduleWorkflowService::class);
        foreach ([1, null] as $user) {
            try {
                $workflow->approve($contract, $user, 'reviewed against the offer letter');
                $this->fail('approved by ' . ($user === null ? 'no one' : 'the preparer'));
            } catch (InvalidArgumentException $e) {
                $this->assertMatchesRegularExpression('/cannot approve it|named person/', $e->getMessage());
            }
        }
        try {
            $workflow->approve($contract, 1, 'note', 'Administrator');
            $this->fail('a label other than the bootstrap label stood in for a named approver');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Only the bootstrap label', $e->getMessage());
        }
        $workflow->approve($contract->fresh(), 2, 'reviewed against the offer letter');
        $c = $contract->fresh();
        $this->assertSame('APPROVED', $c->schedule_approval_status);
        $this->assertSame(2, (int) $c->schedule_approved_by);
        $this->assertNull($c->schedule_approver_label);
    }

    public function test_the_governed_control_is_read_and_the_bootstrap_label_is_recorded(): void
    {
        $contract = $this->scheduleFixture();
        DB::table('governance_settings')->insert(['key' => 'schedule_approval_control', 'value' => 'One person may draft and approve', 'options' => '[]', 'label' => 'x', 'description' => '',
            'effective_from' => '2020-01-01', 'status' => 'APPROVED', 'created_at' => now(), 'updated_at' => now()]);
        app(ScheduleWorkflowService::class)->approve($contract, 1, 'one person may, under the governed control');
        $this->assertSame('APPROVED', $contract->fresh()->schedule_approval_status);

        DB::table('contract_eir')->where('contract_id', 'C1')->update(['schedule_approval_status' => 'DRAFT']);
        DB::table('governance_settings')->where('key', 'schedule_approval_control')->delete();
        try {
            app(ScheduleWorkflowService::class)->approve($contract->fresh(), 2, 'note');
            $this->fail('approved with no governed control in force');
        } catch (\App\Exceptions\GovernanceSettingMissingException $e) {
            $this->assertStringContainsString('schedule_approval_control', $e->getMessage());
        }
        // the bootstrap approves under its label, recorded on the contract, never as a MAIIC approval
        app(ScheduleWorkflowService::class)->approve($contract->fresh(), 1, LoanBookBuildService::BOOTSTRAP_LABEL, LoanBookBuildService::BOOTSTRAP_LABEL);
        $this->assertSame(LoanBookBuildService::BOOTSTRAP_LABEL, $contract->fresh()->schedule_approver_label);
    }

    public function test_the_contractual_rate_is_a_fraction_and_a_percent_is_refused_not_rescaled(): void
    {
        $this->assertSame(0.321, ScheduleWorkflowService::rateAsFraction(0.321));
        $this->assertSame(1.0, ScheduleWorkflowService::rateAsFraction('1'));
        foreach ([32.1, -0.01, null, ''] as $bad) {
            try {
                ScheduleWorkflowService::rateAsFraction($bad);
                $this->fail('accepted ' . var_export($bad, true));
            } catch (InvalidArgumentException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
    }

    // ----- the staging thresholds under maker-checker (H11) ---------------

    private function thresholdFixture(): void
    {
        Schema::create('staging_thresholds', function (Blueprint $t) {
            $t->increments('id'); $t->string('facility_class')->default('DEFAULT'); $t->unsignedSmallInteger('min_tenor_months')->default(0); $t->unsignedSmallInteger('stage2_dpd'); $t->unsignedSmallInteger('stage3_dpd');
            $t->text('rebuttal_basis')->nullable(); $t->date('effective_from'); $t->timestamps();
        });
        (require base_path('database/migrations/2026_10_14_220000_go_live_controls.php'))->up();
        StagingThreshold::create(['facility_class' => 'DEFAULT', 'min_tenor_months' => 0, 'stage2_dpd' => 31, 'stage3_dpd' => 181, 'effective_from' => '2020-01-01']);
    }

    public function test_a_threshold_is_proposed_by_one_person_and_approved_by_another_before_staging_reads_it(): void
    {
        $this->thresholdFixture();
        $this->assertSame('APPROVED', StagingThreshold::first()->status, 'the rows already loaded are the approved defaults');
        $service = new StagingThresholdService();
        $row = $service->propose(['facility_class' => 'DEFAULT', 'min_tenor_months' => 0, 'stage2_dpd' => 31, 'stage3_dpd' => 91, 'effective_from' => '2026-11-01', 'reason' => 'the RBM directive for short facilities'], 1);
        $this->assertSame(181, (int) StagingThreshold::forFacility(null, 12, '2026-11-30')->stage3_dpd, 'a proposal does not govern');
        try {
            $service->approve($row->id, 1);
            $this->fail('the proposer approved their own threshold');
        } catch (LogicException $e) {
            $this->assertStringContainsString('different person', $e->getMessage());
        }
        $service->approve($row->id, 2);
        $this->assertSame(91, (int) StagingThreshold::forFacility(null, 12, '2026-11-30')->stage3_dpd);
        $this->assertSame(181, (int) StagingThreshold::forFacility(null, 12, '2026-10-31')->stage3_dpd, 'an earlier month keeps its rule');
    }

    public function test_a_threshold_applies_forward_only_and_a_rebuttal_states_its_evidence(): void
    {
        $this->thresholdFixture();
        $service = new StagingThresholdService();
        try {
            $service->propose(['facility_class' => 'DEFAULT', 'min_tenor_months' => 0, 'stage2_dpd' => 31, 'stage3_dpd' => 91, 'effective_from' => '2019-01-01', 'reason' => 'backdated change attempt'], 1);
            $this->fail('a change restated a governed month');
        } catch (LogicException $e) {
            $this->assertStringContainsString('later than 2020-01-01', $e->getMessage());
        }
        try {
            $service->propose(['facility_class' => 'LONG_TERM', 'min_tenor_months' => 36, 'stage2_dpd' => 91, 'stage3_dpd' => 181, 'effective_from' => '2026-11-01', 'reason' => 'the 90-day backstop for long loans'], 1);
            $this->fail('a rebuttal of the 30-day presumption was proposed without its evidence');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('B5.5.37', $e->getMessage());
        }
    }

    // ----- the reversal pairing (spec 7.1) ---------------------------------

    public function test_a_reversal_is_paired_with_the_posting_it_reverses_and_an_orphan_is_named(): void
    {
        Schema::create('eir_actual_transactions', function (Blueprint $t) {
            $t->increments('id'); $t->string('contract_id'); $t->date('transaction_date'); $t->string('transaction_type'); $t->double('total_amount'); $t->string('source_system'); $t->string('source_reference')->nullable();
            $t->string('external_transaction_id'); $t->text('row_note')->nullable(); $t->string('reverses_external_id')->nullable(); $t->string('reversal_status', 20)->nullable(); $t->timestamps();
        });
        $row = fn ($id, $date, $type, $amount, $contract = 'A') => DB::table('eir_actual_transactions')->insert(['contract_id' => $contract, 'transaction_date' => $date, 'transaction_type' => 'Principal+Interest',
            'total_amount' => $amount, 'source_system' => EirLedgerCashService::SOURCE, 'source_reference' => "TRANTYPE {$type} narration", 'external_transaction_id' => (string) $id]);
        $row(10, '2025-04-01', '305', 500000.0);
        $row(11, '2025-04-20', '305', 500000.0);   // the nearer one is reversed
        $row(12, '2025-04-23', '306', -500000.0);
        $row(13, '2025-05-02', '306', -700000.0);  // nothing to reverse: named
        $row(14, '2025-04-01', '305', 500000.0, 'B'); // another account is never paired

        $r = app(EirLedgerCashService::class)->pairReversals();
        $this->assertSame(['reversals' => 2, 'paired' => 1, 'unpaired' => 1, 'unpaired_ids' => ['13']], $r);
        $rev = DB::table('eir_actual_transactions')->where('external_transaction_id', '12')->first();
        $this->assertSame('11', $rev->reverses_external_id);
        $this->assertStringContainsString('3 days earlier', $rev->row_note);
        $this->assertSame('REVERSED', DB::table('eir_actual_transactions')->where('external_transaction_id', '11')->value('reversal_status'));
        $this->assertNull(DB::table('eir_actual_transactions')->where('external_transaction_id', '10')->value('reversal_status'));
        $this->assertSame(1, app(EirLedgerCashService::class)->pairReversals()['paired'], 'a second run keeps the pair');
    }

    // ----- the scheme settings and the over-sanction flag ------------------

    public function test_the_scheme_settings_load_from_sm_01_with_dd_10_where_it_lacks_them_and_over_sanction_is_flagged(): void
    {
        foreach (['2026_10_08_000000_create_ebanker_landing_zone', '2026_10_09_000000_keep_quarantined_rows_in_landing_zone'] as $m) {
            (require base_path("database/migrations/{$m}.php"))->up();
        }
        Schema::create('schemes', function (Blueprint $t) { $t->id(); $t->string('scheme_code', 30); $t->string('product_code', 30)->nullable(); $t->char('interest_policy', 1)->nullable(); $t->char('floating_flag', 1)->nullable(); $t->char('interest_calc_base', 1)->nullable(); $t->string('installment_based_on', 20)->nullable(); $t->char('emi_calc_type', 1)->nullable(); $t->string('default_moratorium_type', 20)->nullable(); $t->date('effective_from'); $t->timestamps(); });
        Schema::create('contract_eir', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->double('approved_amount')->nullable(); $t->double('drawn_amount')->nullable(); $t->timestamps(); });
        (require base_path('database/migrations/2026_10_14_220000_go_live_controls.php'))->up();
        DB::table('ebanker_loads')->insert(['id' => 1, 'pack_hash' => str_repeat('a', 64), 'pack_name' => 'fixture', 'manifest' => '{}', 'status' => 'LANDED', 'created_at' => now(), 'updated_at' => now()]);
        $raw = fn ($q, $key, $payload) => DB::table('ebanker_raw_rows')->insert(['load_id' => 1, 'query_id' => $q, 'source_key' => $key, 'account' => null, 'row_date' => null,
            'payload' => json_encode($payload), 'row_hash' => md5($q . $key), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $raw('SM_01', '1', ['SCHEME_MST_ID' => '84', 'SCHEME_NAME' => 'MAIIC Agricultural Loans ', 'INTEREST_POLICY' => 'P', 'FLOATING_FLAG' => 'F', 'LOANINT_SANCWISE_BALWISE' => 'B', 'INST_BASED_ON' => '', 'APPLICABLE_FROM_DATE' => '1/1/2000']);
        $raw('DD_10', '1', ['SCHEME_MST_ID' => '84', 'SCHEME_NAME' => 'MAIIC Agricultural Loans', 'INST_BASED_ON' => 'D', 'APPLICABLE_FROM_DATE' => '1/1/2000']);
        $raw('DD_10', '2', ['SCHEME_MST_ID' => '90', 'SCHEME_NAME' => 'FInES AGRICULTURAL LOANS', 'INTEREST_POLICY' => 'S', 'LOANINT_SANCWISE_BALWISE' => 'B', 'INST_BASED_ON' => 'D', 'APPLICABLE_FROM_DATE' => '1/1/2000']);
        $raw('SM_01', '3', ['SCHEME_MST_ID' => '99', 'SCHEME_NAME' => 'No date', 'APPLICABLE_FROM_DATE' => '']);

        $r = app(ContractInputsBuildService::class)->schemes();
        $this->assertSame(['rows' => 3, 'upserted' => 2, 'from_dd10' => 2, 'refused' => ['99' => 'no readable APPLICABLE_FROM_DATE in SM_01 or DD_10']], $r);
        $s84 = DB::table('schemes')->where('scheme_code', '84')->first();
        $this->assertSame(['B', 'D', 'P', 'SM_01+DD_10'], [$s84->interest_calc_base, $s84->installment_based_on, $s84->interest_policy, $s84->source]);
        $this->assertNull($s84->emi_calc_type, 'the EMI code table is not confirmed: the scheme says nothing rather than a guess');
        $this->assertSame('B', \App\Models\Scheme::inForce('90', \Carbon\Carbon::parse('2025-06-30'))->interest_calc_base);
        app(ContractInputsBuildService::class)->schemes();
        $this->assertSame(2, DB::table('schemes')->count(), 'a second load updates, never duplicates');

        DB::table('contract_eir')->insert([['contract_id' => 'OVER', 'approved_amount' => 50000000, 'drawn_amount' => 100000000], ['contract_id' => 'IN', 'approved_amount' => 100, 'drawn_amount' => 100]]);
        $o = app(ContractInputsBuildService::class)->flagOverSanction();
        $this->assertSame(['flagged' => 1, 'contracts' => ['OVER']], $o);
        $this->assertSame(50000000.0, (float) DB::table('contract_eir')->where('contract_id', 'OVER')->value('over_sanction_amount'));
        $this->assertNull(DB::table('contract_eir')->where('contract_id', 'IN')->value('over_sanction_amount'));
    }

    // ----- the scenario rules, the tenor, the settings not yet applied -----

    public function test_the_scenario_rules_read_each_option_as_it_says(): void
    {
        $rules = (new ScenarioSetService(new GovernanceService()))->rules();
        $this->assertSame([3, 40.0, 60.0, true, true], [$rules['min_count'], $rules['base_floor'], $rules['single_ceiling'], $rules['note_required'], $rules['overlay_needs_set']]);
        foreach (['scenario_weight_bounds' => 'No bounds beyond summing to 100', 'overlay_requires_approved_set' => 'Not required', 'scenario_calibration_note' => 'Recommended, not enforced'] as $key => $value) {
            DB::table('governance_settings')->insert(['key' => $key, 'value' => $value, 'options' => '[]', 'label' => $key, 'description' => '', 'effective_from' => '2020-01-01', 'status' => 'APPROVED', 'created_at' => now(), 'updated_at' => now()]);
        }
        $rules = (new ScenarioSetService(new GovernanceService()))->rules();
        $this->assertSame([0.0, 100.0, false, false], [$rules['base_floor'], $rules['single_ceiling'], $rules['note_required'], $rules['overlay_needs_set']],
            '"No bounds" used to read as 40 and 60, and "Not required" as required');
        DB::table('governance_settings')->where('key', 'scenario_minimum_count')->delete();
        $this->expectException(\App\Exceptions\GovernanceSettingMissingException::class);
        (new ScenarioSetService(new GovernanceService()))->rules();
    }

    public function test_the_remaining_tenor_in_months_is_read_as_the_year_that_covers_it(): void
    {
        $this->assertSame([1, 1, 2, 2, 3], [Tenor::yearsCovering(1), Tenor::yearsCovering(12), Tenor::yearsCovering(13), Tenor::yearsCovering('24'), Tenor::yearsCovering(24.5)]);
        foreach ([null, '', 0, -3, 'n/a'] as $bad) {
            $this->assertNull(Tenor::yearsCovering($bad));
        }
    }

    /** No decorative setting: one marked "recorded, not yet applied" is read by no engine, and the screen says so. */
    public function test_a_setting_marked_not_yet_applied_is_read_by_no_engine(): void
    {
        $notApplied = GovernanceService::notYetApplied();
        $this->assertSame([], array_diff(array_keys($notApplied), GovernanceService::keys()), 'every marked key is in the catalogue');
        $code = '';
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path('Services'))) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && $file->getFilename() !== 'GovernanceService.php') {
                $code .= file_get_contents($file->getPathname());
            }
        }
        foreach (array_keys($notApplied) as $key) {
            $this->assertDoesNotMatchRegularExpression('/->get\(\s*\'' . preg_quote($key, '/') . '\'/', $code, "{$key} is read by an engine: take it off the not-yet-applied list");
        }
        foreach (['schedule_approval_control', 'dpd_basis', 'mega_farms_scope', 'fli_adjustment_route'] as $read) {
            $this->assertArrayNotHasKey($read, $notApplied);
        }
        $overview = collect((new GovernanceService())->overview())->keyBy('key');
        $this->assertFalse($overview['cash_source']['applied']);
        $this->assertStringContainsString('ledger', $overview['cash_source']['not_applied_note']);
        $this->assertTrue($overview['schedule_approval_control']['applied']);
    }
}
