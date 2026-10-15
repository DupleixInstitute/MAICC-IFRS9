<?php

namespace Tests\Feature\Eir;

use App\Models\EirAccountingRule;
use App\Models\User;
use App\Services\Eir\AccountingRuleService;
use App\Services\Eir\FeeRuleMatcher;
use App\Support\MakerChecker;
use Database\Seeders\EirAccountingRuleSeeder;
use Database\Seeders\GovernanceSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Staging Module Specification v1, section 8.6 (acceptance 14; decision S9):
 * the person who wrote or changed a fee rule cannot approve it; approving and
 * switching a rule on or off need a reason and a second person; the matcher
 * uses only rules approved that way, and an approval given before the control
 * stays in use until a second person confirms it (option (a)). Under UAT mode
 * an administrator may decide their own proposal, recorded on the
 * self-approvals report.
 */
class FeeRulebookControlTest extends TestCase
{
    use RefreshDatabase;

    private User $maker;

    private User $checker;

    private AccountingRuleService $rules;

    protected function setUp(): void
    {
        parent::setUp();
        // these tests are of other behaviour; the evidence a workflow needs before approval is tested in ApprovalWorkflowsTest
        config(['approvals.require_evidence' => false]);
        $this->maker = User::create(['name' => 'Finance analyst', 'email' => uniqid() . '@example.com', 'password' => bcrypt('x')]);
        $this->checker = User::create(['name' => 'Chief Finance Officer', 'email' => uniqid() . '@example.com', 'password' => bcrypt('x')]);
        (new GovernanceSettingsSeeder())->run();
        $this->rules = new AccountingRuleService();
    }

    private function propose(): EirAccountingRule
    {
        return $this->rules->create(['name' => 'Arrangement fees', 'fee_type' => 'arrangement', 'proposed_integral' => true, 'rationale' => 'Origination fee, IFRS 9 B5.4.2', 'priority' => 20, 'active' => true], $this->maker->id);
    }

    private function matched(): ?string
    {
        return (new FeeRuleMatcher())->match(['fee_type' => 'arrangement', 'description' => 'Arrangement fee'])?->name;
    }

    public function test_a_second_person_approves_with_a_reason_and_only_then_the_matcher_uses_the_rule(): void
    {
        $rule = $this->propose();
        $this->assertNull($this->matched(), 'a proposed rule is not used');
        try {
            $this->rules->approve($rule, $this->maker->id, 'I wrote it and it is right');
            $this->fail('the writer approved their own rule');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('cannot approve it', $e->getMessage());
        }
        try {
            $this->rules->approve($rule, $this->checker->id, 'ok');
            $this->fail('approved without a reason');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('reason', $e->getMessage());
        }
        $approved = $this->rules->approve($rule, $this->checker->id, 'Matches the accounting policy, note 22.4');
        $this->assertSame([EirAccountingRule::STATUS_APPROVED, 'Matches the accounting policy, note 22.4'], [$approved->status, $approved->approval_reason]);
        $this->assertSame('Arrangement fees', $this->matched());

        // an edit clears the approval: the matcher stops using the rule, and the editor cannot approve it
        $edited = $this->rules->update($approved, ['name' => 'Arrangement fees', 'fee_type' => 'arrangement', 'proposed_integral' => true, 'rationale' => 'Origination fee, reworded', 'priority' => 20, 'active' => true], $this->checker->id);
        $this->assertSame(EirAccountingRule::STATUS_PROPOSED, $edited->status);
        $this->assertNull($this->matched());
        $this->expectException(\LogicException::class);
        $this->rules->approve($edited, $this->checker->id, 'My own wording is right');
    }

    public function test_switching_a_rule_off_is_a_proposal_a_second_person_approves(): void
    {
        $rule = $this->rules->approve($this->propose(), $this->checker->id, 'Matches the accounting policy, note 22.4');
        $proposed = $this->rules->proposeSwitch($rule, false, $this->maker->id, 'Arrangement fees are now billed as a service');
        $this->assertTrue($proposed->active, 'nothing changes until it is approved');
        $this->assertSame('Arrangement fees', $this->matched());
        try {
            $this->rules->approveSwitch($proposed, $this->maker->id, 'Approving my own switch');
            $this->fail('the proposer approved their own switch');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('cannot approve it', $e->getMessage());
        }
        $off = $this->rules->approveSwitch($proposed, $this->checker->id, 'Confirmed with Finance on the new billing');
        $this->assertFalse($off->active);
        $this->assertNull($this->matched());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'EIR Accounting Rule Status Changed')->count());
    }

    public function test_an_approval_given_before_the_control_stays_in_use_until_confirmed(): void
    {
        // a rule approved before 15 October 2026, as the migration leaves it
        $rule = EirAccountingRule::create(['name' => 'Legal fees on origination', 'fee_type' => 'arrangement', 'proposed_integral' => true, 'rationale' => 'Legal costs of drawing up the facility', 'priority' => 25, 'active' => true,
            'created_by' => $this->maker->id, 'approved_by' => $this->maker->id, 'approved_at' => now(), 'status' => EirAccountingRule::STATUS_APPROVED, 'approval_to_confirm' => true]);
        $this->assertSame('Legal fees on origination', $this->matched(), 'option (a): still used while it waits');
        $confirmed = $this->rules->approve($rule, $this->checker->id, 'Re-approved under maker-checker, decision S9');
        $this->assertFalse($confirmed->approval_to_confirm);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'EIR Accounting Rule Approval Confirmed')->count());
    }

    public function test_a_seeded_rule_approved_before_the_control_is_not_confirmed_by_its_own_approver(): void
    {
        // the seeder records no maker, so only the earlier approver stands between one person and a confirmed rule (review S1)
        $rule = EirAccountingRule::create(['name' => 'Legal fees on origination', 'fee_type' => 'arrangement', 'proposed_integral' => true, 'rationale' => 'Legal costs of drawing up the facility', 'priority' => 25, 'active' => true,
            'created_by' => null, 'approved_by' => $this->maker->id, 'approved_at' => now(), 'status' => EirAccountingRule::STATUS_APPROVED, 'approval_to_confirm' => true]);
        try {
            $this->rules->approve($rule, $this->maker->id, 'Confirming my own earlier approval');
            $this->fail('the earlier approver confirmed their own approval');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('cannot confirm that approval', $e->getMessage());
        }
        $this->assertTrue($rule->fresh()->approval_to_confirm);
        $this->assertFalse($this->rules->approve($rule, $this->checker->id, 'Re-approved under maker-checker, decision S9')->approval_to_confirm);
    }

    public function test_the_seeder_never_touches_an_existing_rule(): void
    {
        (new EirAccountingRuleSeeder())->run();
        $first = EirAccountingRule::orderBy('id')->first();
        $this->assertSame(EirAccountingRule::STATUS_PROPOSED, $first->status);
        $this->rules->approve($first, $this->checker->id, 'Approved for the take-on review');
        (new EirAccountingRuleSeeder())->run();
        $this->assertSame(EirAccountingRule::STATUS_APPROVED, $first->fresh()->status, 'a re-seed (every bootstrap run) leaves the approval in place');
        $this->assertNotNull($first->fresh()->approved_at);
    }

    public function test_under_uat_mode_an_administrator_decides_their_own_proposal_and_it_is_reported(): void
    {
        $this->maker->assignRole('admin');
        $this->assertSame(0, Artisan::call('governance:uat-mode', ['action' => 'on', '--user' => $this->maker->id]));
        $rule = $this->rules->approve($this->propose(), $this->maker->id, 'UAT run of the rulebook workflow');
        $this->assertSame(EirAccountingRule::STATUS_APPROVED, $rule->status);
        $switch = $this->rules->proposeSwitch($rule, false, $this->maker->id, 'UAT run of the switch workflow');
        $this->rules->approveSwitch($switch, $this->maker->id, 'UAT run of the switch approval');
        $this->assertSame(['Fee rulebook', 'Fee rulebook'], MakerChecker::selfApprovals()->pluck('workflow')->all());

        // UAT mode off: the same self-approval is refused
        $this->assertSame(0, Artisan::call('governance:uat-mode', ['action' => 'off', '--user' => $this->checker->id]));
        $this->expectException(\LogicException::class);
        $this->rules->approve($this->rules->create(['name' => 'Appraisal fees', 'fee_type' => 'appraisal', 'proposed_integral' => true, 'rationale' => 'Appraisal on origination', 'priority' => 30, 'active' => true], $this->maker->id), $this->maker->id, 'After UAT, my own rule again');
    }

    public function test_the_screen_refuses_a_self_approval_with_a_message(): void
    {
        $this->maker->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('settings', 'web'));
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $rule = $this->propose();
        $this->actingAs($this->maker->fresh())->from('/eir-accounting-rules')
            ->post(route('eir-accounting-rules.decide', $rule->id), ['action' => 'approve', 'reason' => 'Approving my own rule'])
            ->assertRedirect('/eir-accounting-rules')->assertSessionHas('error');
        $this->assertSame(EirAccountingRule::STATUS_PROPOSED, $rule->fresh()->status);
    }
}
