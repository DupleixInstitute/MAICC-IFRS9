<?php

namespace Tests\Feature\Eir;

use App\Exceptions\GovernanceSettingMissingException;
use App\Models\GovernanceSetting;
use App\Services\Eir\GovernanceService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use LogicException;
use Tests\Feature\Eir\Concerns\CreatesGovernanceSchema;
use Tests\TestCase;

/**
 * The Governance Centre service on a private in-memory schema: resolution
 * by effective date, fail-closed on a missing value, option validation,
 * maker-checker, the history copy on supersession and the audit trail.
 */
class GovernanceServiceTest extends TestCase
{
    use CreatesGovernanceSchema;

    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->string('email'); $t->timestamps(); });
        Schema::create('audit_logs', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id')->nullable(); $t->string('action'); $t->string('entity_type'); $t->integer('entity_id')->nullable(); $t->string('scope')->nullable(); $t->string('reporting_period')->nullable(); $t->integer('rows_affected')->nullable(); $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->text('meta')->nullable(); $t->string('ip_address')->nullable(); $t->string('user_agent')->nullable(); $t->timestamps(); });
        DB::table('users')->insert([
            ['id' => 10, 'name' => 'Maker', 'email' => 'maker@example.test', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 20, 'name' => 'Checker', 'email' => 'checker@example.test', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->createGovernanceSchema();
        $this->seedGovernanceDefaults();
    }

    private function service(): GovernanceService
    {
        return new GovernanceService();
    }

    private function approvedChange(string $key, string $value, string $effectiveFrom, int $maker = 10, int $checker = 20): GovernanceSetting
    {
        $service = $this->service();
        $proposal = $service->propose($key, $value, $effectiveFrom, 'Agreed with MAIIC at the September walkthrough.', $maker);

        return $service->approve($proposal->id, $checker);
    }

    public function test_the_seeder_writes_every_catalogue_setting_once_as_an_approved_default(): void
    {
        $this->assertSame(count(GovernanceService::catalogue()), GovernanceSetting::count());
        $this->assertSame(12, GovernanceSetting::count());
        $this->assertSame(12, GovernanceSetting::where('status', 'APPROVED')->count());

        foreach (GovernanceService::catalogue() as $key => $definition) {
            $this->assertSame($definition['default'], $this->service()->get($key), $key);
            $this->assertContains($definition['default'], $definition['options'], $key);
            foreach ($definition['options'] as $option) {
                $this->assertLessThanOrEqual(60, strlen($option), "{$key}: '{$option}' does not fit the value column");
            }
        }

        // Running it again changes nothing: an approved MAIIC change is never overwritten.
        $this->seedGovernanceDefaults();
        $this->assertSame(12, GovernanceSetting::count());
    }

    public function test_the_value_in_force_is_resolved_by_effective_date(): void
    {
        $this->approvedChange('day_count', '30/360', '2026-03-01');
        $service = $this->service();

        $this->assertSame('ACT/365', $service->get('day_count', CarbonImmutable::parse('2025-01-01')));
        $this->assertSame('ACT/365', $service->get('day_count', CarbonImmutable::parse('2026-02-28')));
        $this->assertSame('30/360', $service->get('day_count', CarbonImmutable::parse('2026-03-01')));
        $this->assertSame('30/360', $service->get('day_count', CarbonImmutable::parse('2026-09-30')));
        $this->assertSame('30/360', $service->get('day_count'));
    }

    public function test_a_missing_setting_throws_a_named_exception_instead_of_defaulting(): void
    {
        $service = $this->service();

        try {
            $service->get('day_count', CarbonImmutable::parse('2024-12-31'));
            $this->fail('A date before the first approved value must not resolve.');
        } catch (GovernanceSettingMissingException $e) {
            $this->assertStringContainsString('day_count', $e->getMessage());
            $this->assertStringContainsString('2024-12-31', $e->getMessage());
        }

        $this->expectException(GovernanceSettingMissingException::class);
        $service->get('not_a_setting');
    }

    public function test_a_proposal_must_use_one_of_the_options(): void
    {
        $service = $this->service();
        $this->assertSame(['ACT/365', '30/360'], $service->options('day_count'));

        try {
            $service->propose('day_count', 'ACT/360', '2026-03-01', 'Trying a value nobody agreed.', 10);
            $this->fail('A value outside the options must be refused.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('ACT/360', $e->getMessage());
            $this->assertStringContainsString('ACT/365 / 30/360', $e->getMessage());
        }
        $this->assertSame(0, GovernanceSetting::where('status', 'PROPOSED')->count());

        $this->expectException(InvalidArgumentException::class);
        $service->propose('not_a_setting', 'anything', '2026-03-01', 'Unknown key must be refused.', 10);
    }

    public function test_a_proposal_needs_a_reason_and_a_forward_effective_date(): void
    {
        $service = $this->service();

        try {
            $service->propose('day_count', '30/360', '2026-03-01', 'short', 10);
            $this->fail('A reason under 10 characters must be refused.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('reason', $e->getMessage());
        }

        try {
            $service->propose('day_count', '30/360', '2025-01-01', 'Same date as the seeded default.', 10);
            $this->fail('An effective date on or before the last approved change must be refused.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('later than 2025-01-01', $e->getMessage());
        }

        try {
            $service->propose('day_count', '30/360', '2024-06-01', 'Would restate a governed period.', 10);
            $this->fail('An effective date before the last approved change must be refused.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('never restates', $e->getMessage());
        }

        try {
            $service->propose('day_count', 'ACT/365', '2026-03-01', 'Proposing the value already in force.', 10);
            $this->fail('Proposing the value already in force is nothing to change.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('nothing to change', $e->getMessage());
        }

        $service->propose('day_count', '30/360', '2026-03-01', 'A real change for the new financial year.', 10);
        $this->expectException(LogicException::class);
        $service->propose('day_count', '30/360', '2026-03-01', 'The same key and date a second time.', 20);
    }

    public function test_the_proposer_cannot_approve_their_own_change_unless_an_administrator_overrides(): void
    {
        $service = $this->service();
        $proposal = $service->propose('day_count', '30/360', '2026-03-01', 'Agreed with MAIIC at the September walkthrough.', 10);
        $this->assertSame('PROPOSED', $proposal->status);
        $this->assertSame('ACT/365', $service->get('day_count', CarbonImmutable::parse('2026-03-01')), 'A proposal must not take effect before approval.');

        try {
            $service->approve($proposal->id, 10);
            $this->fail('The proposer must not approve their own change.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('cannot approve', $e->getMessage());
        }
        $this->assertSame('PROPOSED', $proposal->fresh()->status);

        $approved = $service->approve($proposal->id, 20);
        $this->assertSame('APPROVED', $approved->status);
        $this->assertSame(20, (int) $approved->approved_by);
        $this->assertNotNull($approved->approved_at);
        $this->assertSame('30/360', $service->get('day_count', CarbonImmutable::parse('2026-03-01')));

        $this->expectException(LogicException::class);
        $service->approve($proposal->id, 20);
    }

    public function test_an_administrator_override_may_approve_the_proposers_own_change(): void
    {
        $service = $this->service();
        $proposal = $service->propose('cash_source', 'Extract B (transaction ledger)', '2026-03-01', 'Extract B is now delivered monthly.', 10);

        $approved = $service->approve($proposal->id, 10, true);

        $this->assertSame('APPROVED', $approved->status);
        $this->assertSame(10, (int) $approved->approved_by);
        $audit = DB::table('audit_logs')->where('action', 'EIR Governance Setting Approved')->first();
        $this->assertTrue((bool) json_decode($audit->meta, true)['admin_override']);
    }

    public function test_approval_copies_the_superseded_value_to_history_and_keeps_it_live_for_earlier_dates(): void
    {
        $seeded = GovernanceSetting::where('key', 'day_count')->first();
        $approved = $this->approvedChange('day_count', '30/360', '2026-03-01');

        $history = DB::table('governance_setting_history')->get();
        $this->assertCount(1, $history);
        $this->assertSame($seeded->id, (int) $history[0]->setting_id);
        $this->assertSame('day_count', $history[0]->key);
        $this->assertSame('ACT/365', $history[0]->value);
        $this->assertSame(20, (int) $history[0]->superseded_by);
        $this->assertSame($approved->id, (int) $history[0]->superseded_by_setting_id);
        $this->assertNotNull($history[0]->superseded_at);

        // The old row is not deleted: 2025 still resolves to it.
        $this->assertSame(2, GovernanceSetting::where('key', 'day_count')->count());
        $this->assertSame('ACT/365', $this->service()->get('day_count', CarbonImmutable::parse('2025-12-31')));

        // A second change supersedes the first change, not the seeded row.
        $second = $this->approvedChange('day_count', 'ACT/365', '2026-06-01');
        $this->assertSame(2, DB::table('governance_setting_history')->count());
        $latest = DB::table('governance_setting_history')->orderByDesc('id')->first();
        $this->assertSame($approved->id, (int) $latest->setting_id);
        $this->assertSame('30/360', $latest->value);
        $this->assertSame($second->id, (int) $latest->superseded_by_setting_id);
    }

    public function test_every_proposal_and_approval_is_audit_logged_with_old_and_new_values(): void
    {
        $approved = $this->approvedChange('stage3_interest_basis', 'Gross with allowance unwind', '2026-03-01');

        $proposed = DB::table('audit_logs')->where('action', 'EIR Governance Setting Proposed')->first();
        $this->assertNotNull($proposed);
        $this->assertSame(GovernanceSetting::class, $proposed->entity_type);
        $this->assertSame($approved->id, (int) $proposed->entity_id);
        $this->assertSame('Net carrying amount', json_decode($proposed->old_values, true)['value']);
        $this->assertSame('Gross with allowance unwind', json_decode($proposed->new_values, true)['value']);
        $this->assertSame(10, json_decode($proposed->meta, true)['proposed_by']);
        $this->assertSame('2026-03', json_decode($proposed->meta, true)['first_period_applied']);

        $approval = DB::table('audit_logs')->where('action', 'EIR Governance Setting Approved')->first();
        $this->assertNotNull($approval);
        $this->assertSame('Net carrying amount', json_decode($approval->old_values, true)['value']);
        $this->assertSame('Gross with allowance unwind', json_decode($approval->new_values, true)['value']);
        $this->assertSame(20, json_decode($approval->meta, true)['approved_by']);
        $this->assertFalse(json_decode($approval->meta, true)['admin_override']);
    }

    public function test_the_overview_names_the_value_in_force_and_the_state_of_every_row(): void
    {
        $service = $this->service();
        $this->approvedChange('day_count', '30/360', '2026-03-01');
        $service->propose('day_count', 'ACT/365', '2026-09-01', 'Reverting after the auditor comment.', 10);

        $overview = collect($service->overview(CarbonImmutable::parse('2026-06-30')))->keyBy('key');
        $this->assertCount(12, $overview);

        $dayCount = $overview['day_count'];
        $this->assertSame('Day count', $dayCount['label']);
        $this->assertSame(['ACT/365', '30/360'], $dayCount['options']);
        $this->assertSame('30/360', $dayCount['in_force']['value']);
        $this->assertSame('2026-03-01', $dayCount['in_force']['effective_from']);
        $this->assertSame('Checker', $dayCount['in_force']['approver']);
        $this->assertSame('Maker', $dayCount['in_force']['proposer']);
        $this->assertSame(['PROPOSED', 'IN_FORCE', 'PAST'], array_column($dayCount['rows'], 'state'));
        $this->assertCount(1, $dayCount['history']);
        $this->assertSame('ACT/365', $dayCount['history'][0]['value']);
        $this->assertSame('Checker', $dayCount['history'][0]['superseded_by']);

        $this->assertNull($overview['plr_mid_period']['in_force']['approver'], 'A seeded default has no approver.');
        $this->assertSame(['IN_FORCE'], array_column($overview['plr_mid_period']['rows'], 'state'));
    }

    public function test_tolerance_options_parse_to_a_share_and_a_floor(): void
    {
        $this->assertSame(['percent' => 1.0, 'floor' => 1.0], GovernanceService::parseTolerance('1 percent of the posted amount, floor MWK 1'));
        $this->assertSame(['percent' => 0.5, 'floor' => 1.0], GovernanceService::parseTolerance('0.5 percent of the posted amount, floor MWK 1'));
        $this->assertSame(['percent' => 1.0, 'floor' => 1.0], GovernanceService::parseTolerance('100 basis points on the EIR and MWK 1 per account-month'));
        $this->assertSame(['percent' => 0.0, 'floor' => 100.0], GovernanceService::parseTolerance('MWK 100 per account-month, whatever the amount posted'));
        foreach (GovernanceService::catalogue()['recon_tolerance']['options'] as $option) {
            GovernanceService::parseTolerance($option);
        }

        $this->assertSame(['percent' => 1.0, 'floor' => 1.0], $this->service()->reconciliationTolerance());
        $this->assertSame('NET', $this->service()->stage3InterestBasis());
        $this->assertSame('GROSS', GovernanceService::parseStage3Basis('Gross with allowance unwind'));

        $this->expectException(InvalidArgumentException::class);
        GovernanceService::parseTolerance('whatever the reviewer thinks');
    }
}
