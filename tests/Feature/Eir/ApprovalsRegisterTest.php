<?php

namespace Tests\Feature\Eir;

use App\Models\GovernanceSetting;
use App\Models\SupportingDocument;
use App\Models\User;
use App\Services\ApprovalDocumentService;
use App\Services\ApprovalsRegisterService;
use App\Services\Eir\AccountingRuleService;
use App\Services\Eir\GovernanceDocumentService;
use App\Services\Eir\GovernanceService;
use App\Services\Eir\StagingPolicy;
use App\Support\EvidenceRule;
use Database\Seeders\GovernanceSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The register of approvals (Staging Module Specification v1, section 8.7;
 * acceptance 15): every pending and decided item across the Governance Centre
 * and the approval workflows, filtered, exported, reconciled to the audit log
 * both ways; one document store, a document linked to each decision it
 * covers; and the approvals made before the supporting-document rule,
 * labelled as such (section 8.10).
 */
class ApprovalsRegisterTest extends TestCase
{
    use RefreshDatabase;

    private User $analyst;

    private User $cfo;

    private GovernanceService $gov;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(GovernanceDocumentService::DISK);
        (new GovernanceSettingsSeeder())->run();
        $this->analyst = User::create(['name' => 'Finance analyst', 'email' => uniqid() . '@example.com', 'password' => bcrypt('x')]);
        $this->cfo = User::create(['name' => 'Chief Finance Officer', 'email' => uniqid() . '@example.com', 'password' => bcrypt('x')]);
        $this->gov = new GovernanceService();
    }

    private function register(array $filters = []): array
    {
        return (new ApprovalsRegisterService())->rows($filters);
    }

    private function upload(int $settingId, string $name = 'Board minute 14 Oct 2026.pdf'): SupportingDocument
    {
        return app(GovernanceDocumentService::class)->attach(GovernanceSetting::findOrFail($settingId), UploadedFile::fake()->create($name, 20, 'application/pdf'), 'minute', 'Credit Committee', $this->analyst->id);
    }

    public function test_the_register_lists_pending_and_decided_items_across_workflows_and_leaves_out_the_installation(): void
    {
        $approved = $this->gov->propose('day_count', '30/360', '2026-01-01', 'Finance moves the day count to 30/360', $this->analyst->id);
        $this->gov->approve($approved->id, $this->cfo->id);
        $pending = $this->gov->propose('stage_cure_months', '3 months', '2026-02-01', 'Credit Committee asks for a probation', $this->analyst->id);
        $rules = new AccountingRuleService();
        $rule = $rules->create(['name' => 'Arrangement fees', 'fee_type' => 'arrangement', 'proposed_integral' => true, 'rationale' => 'Origination fee', 'priority' => 20, 'active' => true], $this->analyst->id);

        $rows = collect($this->register());
        $this->assertNotNull($rows->first(fn ($r) => $r['workflow'] === 'governance' && $r['id'] === $approved->id && ! $r['pending'] && $r['decided_by'] === 'Chief Finance Officer'));
        $this->assertNotNull($rows->first(fn ($r) => $r['workflow'] === 'governance' && $r['id'] === $pending->id && $r['pending'] && $r['proposed_by'] === 'Finance analyst'));
        $this->assertNotNull($rows->first(fn ($r) => $r['workflow'] === 'fee-rule' && $r['id'] === $rule->id && $r['pending']));
        $this->assertNull($rows->first(fn ($r) => $r['workflow'] === 'governance' && $r['title'] !== null && str_starts_with($r['title'], 'day_count = ACT/365')), 'a seeded default is the installation, not a decision');

        $this->assertSame([$pending->id], array_column($this->register(['workflow' => 'governance', 'status' => 'pending']), 'id'));
        $this->assertSame([$approved->id], array_column($this->register(['workflow' => 'governance', 'status' => 'decided']), 'id'));
        $this->assertCount(0, $this->register(['person' => 999999]));
        $this->assertCount(0, $this->register(['to' => '2000-01-01']));
        $this->assertGreaterThanOrEqual(3, count($this->register(['person' => $this->analyst->id])));
    }

    public function test_the_register_reconciles_to_the_audit_log_both_ways(): void
    {
        $p = $this->gov->propose('day_count', '30/360', '2026-01-01', 'Finance moves the day count to 30/360', $this->analyst->id);
        $this->gov->approve($p->id, $this->cfo->id);
        $this->assertTrue((new ApprovalsRegisterService())->reconcile()['reconciled']);

        // a decision written straight to the table, with no audit entry
        $id = DB::table('governance_settings')->insertGetId(['key' => 'stage_cure_months', 'value' => '3 months', 'label' => 'Cure', 'effective_from' => '2026-03-01', 'options' => json_encode(GovernanceService::catalogue()['stage_cure_months']['options']), 'description' => 'test', 'status' => 'APPROVED',
            'set_by' => $this->analyst->id, 'approved_by' => $this->cfo->id, 'approved_at' => now(), 'reason' => 'Written outside the system', 'created_at' => now(), 'updated_at' => now()]);
        // and an audit decision entry for an item that is not on record
        \App\Services\AuditLoggerService::log('EIR Governance Setting Approved', GovernanceSetting::class, 999999, ['new_values' => ['key' => 'ghost']]);

        $rec = (new ApprovalsRegisterService())->reconcile();
        $this->assertFalse($rec['reconciled']);
        $this->assertSame([$id], array_column($rec['decided_without_entry'], 'id'));
        $this->assertSame([999999], array_column($rec['entries_without_item'], 'id'));
    }

    public function test_the_register_screen_and_its_exports(): void
    {
        $viewer = User::create(['name' => 'Auditor', 'email' => uniqid() . '@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($viewer)->get(route('approvals.register'))->assertForbidden();
        $viewer->givePermissionTo(Permission::findOrCreate('eir.view', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $viewer = $viewer->fresh();
        $p = $this->gov->propose('day_count', '30/360', '2026-01-01', 'Finance moves the day count to 30/360', $this->analyst->id);

        $this->actingAs($viewer)->get(route('approvals.register', ['status' => 'pending']))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Approvals/Register')->where('total', 1)->where('rows.0.id', $p->id)->where('canLink', false));
        $this->actingAs($viewer)->get(route('approvals.register.export', ['format' => 'xlsx']))->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="Approvals register ' . now()->format('Y-m-d') . '.xlsx"');
        $pdf = $this->actingAs($viewer)->get(route('approvals.register.export', ['format' => 'pdf']));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->actingAs($viewer)->post(route('approvals.register.link'), ['target' => 'governance', 'id' => $p->id, 'document_id' => 1, 'reason' => 'The minute covers this too'])->assertForbidden();
    }

    public function test_a_document_uploaded_once_is_linked_to_each_decision_it_covers(): void
    {
        $preset = $this->gov->propose(StagingPolicy::PRESET, StagingPolicy::PRESET_DIRECTIVE, '2026-02-01', 'Board adopts directive-aligned staging', $this->analyst->id);
        $profile = $this->gov->propose('stage2_threshold_medium_long', $this->gov->options('stage2_threshold_medium_long')[1] ?? '91 days', '2026-01-01', 'Board sets the medium and long term threshold', $this->analyst->id);
        $doc = $this->upload($preset->id);

        try {
            $this->gov->approve($profile->id, $this->cfo->id);
            $this->fail('approved without evidence');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('needs a supporting document', $e->getMessage());
        }
        $documents = app(ApprovalDocumentService::class);
        $documents->link($doc, 'governance', $profile->id, $this->analyst->id, 'Minute item 4 sets this threshold');
        $this->gov->approve($profile->id, $this->cfo->id);
        $this->assertSame(1, DB::table('supporting_documents')->count(), 'uploaded once');
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'Governance Document Linked')->count());
        $row = collect($this->register())->first(fn ($r) => $r['workflow'] === 'governance' && $r['id'] === $profile->id);
        $this->assertSame(1, $row['documents']);
        $this->assertStringContainsString('(linked)', $row['document_names']);

        // a decided item keeps the documents it was decided on; a removed document supports nothing
        $this->expectException(\LogicException::class);
        $documents->link($doc, 'governance', $profile->id, $this->analyst->id, 'Linked again after the decision');
    }

    public function test_an_approval_before_the_evidence_rule_is_labelled_on_the_register_and_the_history(): void
    {
        // production's position: an evidence-required setting approved without a document, before the rule came in
        $id = DB::table('governance_settings')->insertGetId(['key' => 'stage2_threshold_medium_long', 'value' => '31 days', 'label' => 'Stage 2 threshold', 'effective_from' => '2026-01-01', 'options' => json_encode(GovernanceService::catalogue()['stage2_threshold_medium_long']['options']), 'description' => 'test', 'status' => 'APPROVED',
            'set_by' => $this->analyst->id, 'approved_by' => $this->cfo->id, 'approved_at' => '2026-10-15 09:00:00', 'reason' => 'Credit Committee keeps 31 days', 'created_at' => '2026-10-15 08:00:00', 'updated_at' => now()]);
        \App\Services\AuditLoggerService::log('EIR Governance Setting Approved', GovernanceSetting::class, $id, ['new_values' => ['key' => 'stage2_threshold_medium_long']]);
        DB::table('system_milestones')->where('key', EvidenceRule::MILESTONE)->update(['at' => '2026-10-16 08:00:00']);

        $row = collect($this->register())->first(fn ($r) => $r['workflow'] === 'governance' && $r['id'] === $id);
        $this->assertTrue($row['grandfathered']);
        $this->assertSame([$id], array_column($this->register(['status' => 'grandfathered']), 'id'));
        $this->assertStringContainsString(EvidenceRule::LABEL, implode(' ', ApprovalsRegisterService::exportRows([$row])[0]));

        $govern = User::create(['name' => 'Governor', 'email' => uniqid() . '@example.com', 'password' => bcrypt('x')]);
        $govern->givePermissionTo(Permission::findOrCreate('eir.govern', 'web'));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($govern->fresh())->get(route('eir-governance.history', 'stage2_threshold_medium_long'))->assertOk()
            ->assertInertia(fn ($page) => $page->where('rows.0.grandfathered', true));

        // one approved after the rule with no document is not labelled: it could not have been made
        DB::table('system_milestones')->where('key', EvidenceRule::MILESTONE)->update(['at' => '2026-10-14 08:00:00']);
        $this->assertFalse(collect($this->register())->first(fn ($r) => $r['workflow'] === 'governance' && $r['id'] === $id)['grandfathered']);
    }
}
