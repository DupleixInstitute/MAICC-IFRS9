<?php

namespace Tests\Feature\Eir;

use App\Models\AuditLog;
use App\Models\GovernanceSetting;
use App\Models\SupportingDocument;
use App\Models\User;
use App\Services\Eir\GovernanceDocumentService;
use App\Services\Eir\GovernanceService;
use App\Services\Eir\StagingPolicy;
use App\Support\AuditChain;
use Database\Seeders\GovernanceSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Staging Module Specification v1, sections 8.1 to 8.5 (acceptance 9 to 12):
 * every governed change logged, with its documents; required evidence;
 * documents stored privately and checked on opening; one history per setting;
 * an append-only audit log chained by fingerprint.
 */
class GovernanceChangeLogTest extends TestCase
{
    use RefreshDatabase;

    private User $maker;

    private User $checker;

    private GovernanceService $governance;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->maker = User::create(['name' => 'Finance analyst', 'email' => uniqid() . '@example.com', 'password' => bcrypt('x')]);
        $this->checker = User::create(['name' => 'Chief Finance Officer', 'email' => uniqid() . '@example.com', 'password' => bcrypt('x')]);
        foreach ([$this->maker, $this->checker] as $u) {
            $u->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('eir.govern', 'web'));
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        [$this->maker, $this->checker] = [$this->maker->fresh(), $this->checker->fresh()];
        (new GovernanceSettingsSeeder())->run();
        $this->governance = new GovernanceService();
    }

    private function minute(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('Board minute 12 October 2026.pdf', '%PDF-1.4 Board resolution adopting directive-aligned staging');
    }

    private function actions(int $settingId): array
    {
        return DB::table('audit_logs')->where('entity_type', GovernanceSetting::class)->where('entity_id', $settingId)->orderBy('id')->pluck('action')->all();
    }

    /** Acceptance 9: seeding, proposing, documents, approving, rejecting, withdrawing and a preset each write an entry; the history shows them. */
    public function test_every_change_is_logged_and_the_history_shows_it(): void
    {
        $this->assertSame(count(GovernanceService::catalogue()), DB::table('audit_logs')->where('action', 'EIR Governance Setting Seeded')->count(), 'one entry per seeded default');

        $documents = new GovernanceDocumentService();
        $adopt = $this->governance->propose(StagingPolicy::PRESET, StagingPolicy::PRESET_DIRECTIVE, '2026-11-01', 'Board decision of 12 October 2026 adopting directive-aligned staging', $this->maker->id);
        $draft = $documents->attach($adopt, UploadedFile::fake()->createWithContent('draft.pdf', '%PDF draft'), 'analysis', 'First draft', $this->maker->id);
        $documents->remove($draft, $this->maker->id, 'Superseded by the signed minute');
        $documents->attach($adopt, $this->minute(), 'minute', 'Signed Board minute', $this->maker->id);
        $this->governance->approve($adopt->id, $this->checker->id);

        $this->assertSame(['EIR Governance Setting Proposed', 'Governance Document Added', 'Governance Document Removed', 'Governance Document Added', 'EIR Governance Setting Approved', 'Staging Preset Applied'],
            array_values(array_filter($this->actions($adopt->id), fn ($a) => $a !== 'Staging Rule Set By Preset')));
        $this->assertGreaterThan(5, collect($this->actions($adopt->id))->filter(fn ($a) => $a === 'Staging Rule Set By Preset')->count(), 'one entry per rule the preset changed');
        $this->assertNotNull(SupportingDocument::withTrashed()->find($draft->id), 'a removed document is kept, marked removed');

        $rejected = $this->governance->propose('stage_cure_months', '6 months', '2026-12-01', 'Probation lengthened to six months', $this->maker->id);
        $this->governance->reject($rejected->id, $this->checker->id, 'Not supported by the cure analysis');
        $withdrawn = $this->governance->propose('stage_cure_months', '12 months', '2027-01-01', 'Probation lengthened to twelve months', $this->maker->id);
        $this->governance->withdraw($withdrawn->id, $this->maker->id, 'Proposed in error, wrong value');
        $this->assertSame([GovernanceSetting::STATUS_REJECTED, GovernanceSetting::STATUS_WITHDRAWN], [$rejected->fresh()->status, $withdrawn->fresh()->status]);

        $this->actingAs($this->checker)->get(route('eir-governance.history', 'stage_cure_months'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Eir/GovernanceHistory')->has('rows', 3)
                ->where('rows.0.status', 'WITHDRAWN')->where('rows.0.review_reason', 'Proposed in error, wrong value')
                ->where('rows.1.status', 'REJECTED')->where('rows.2.proposed_by', 'installation'));
        $csv = $this->actingAs($this->checker)->get(route('eir-governance.history', ['key' => 'stage_cure_months', 'download' => 'csv']))->streamedContent();
        $this->assertStringContainsString('Not supported by the cure analysis', $csv);
    }

    /** Acceptance 10: a marked setting cannot be approved without a document, and the refusal says what is missing. */
    public function test_required_evidence(): void
    {
        $p = $this->governance->propose('stage2_threshold_medium_long', StagingPolicy::STAGE2_91, '2026-11-01', 'Rebuttal of the 30-day presumption for long-term loans', $this->maker->id);
        try {
            $this->governance->approve($p->id, $this->checker->id);
            $this->fail('approved without its evidence');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('needs a supporting document', $e->getMessage());
            $this->assertStringContainsString('Stage 2 threshold for medium and long-term facilities', $e->getMessage());
        }
        (new GovernanceDocumentService())->attach($p, UploadedFile::fake()->createWithContent('Rebuttal analysis.xlsx', 'arrears and default history'), 'analysis', null, $this->maker->id);
        $this->assertSame(GovernanceSetting::STATUS_APPROVED, $this->governance->approve($p->id, $this->checker->id)->status);
    }

    /** Acceptance 11: private storage, opened only with the governance permission, an altered file reported. */
    public function test_documents_are_stored_privately_and_an_altered_file_is_reported(): void
    {
        $p = $this->governance->propose(StagingPolicy::PRESET, StagingPolicy::PRESET_DIRECTIVE, '2026-11-01', 'Board decision adopting directive-aligned staging', $this->maker->id);
        $doc = (new GovernanceDocumentService())->attach($p, $this->minute(), 'minute', null, $this->maker->id);
        $this->assertSame('local', $doc->disk);
        $this->assertStringStartsWith(GovernanceDocumentService::FOLDER . '/', $doc->path);
        $this->assertSame(hash('sha256', '%PDF-1.4 Board resolution adopting directive-aligned staging'), $doc->hash);

        $this->actingAs($this->checker)->get(route('eir-governance.documents.open', $doc->id))->assertOk();

        Storage::disk('local')->put($doc->path, '%PDF-1.4 a different resolution');
        $this->actingAs($this->checker)->from('/eir-governance/history/staging_preset')->get(route('eir-governance.documents.open', $doc->id))
            ->assertRedirect('/eir-governance/history/staging_preset')->assertSessionHas('error');
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'Governance Document Altered')->count());
    }

    /** Acceptance 11: a person without the governance permission cannot open a document. */
    public function test_a_document_needs_the_governance_permission(): void
    {
        $p = $this->governance->propose(StagingPolicy::PRESET, StagingPolicy::PRESET_DIRECTIVE, '2026-11-01', 'Board decision adopting directive-aligned staging', $this->maker->id);
        $doc = (new GovernanceDocumentService())->attach($p, $this->minute(), 'minute', null, $this->maker->id);
        $stranger = User::create(['name' => 'No governance', 'email' => uniqid() . '@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($stranger)->get(route('eir-governance.documents.open', $doc->id))->assertForbidden();
    }

    /** Acceptance 12: no change or deletion through the system or the database; a change made with the guard bypassed is found by the chain check. */
    public function test_the_audit_log_is_append_only_and_chained(): void
    {
        $p = $this->governance->propose('stage_cure_months', '6 months', '2026-11-01', 'Probation lengthened to six months', $this->maker->id);
        $this->governance->approve($p->id, $this->checker->id);
        $entries = DB::table('audit_logs')->orderBy('id')->get();
        foreach ($entries as $i => $e) {
            $this->assertSame($i === 0 ? null : $entries[$i - 1]->entry_hash, $e->prev_hash, "entry {$e->id} follows the one before it");
        }
        $this->assertTrue(AuditChain::verify()['intact']);
        $this->assertTrue(AuditChain::verify()['guard']);

        $last = AuditLog::query()->orderByDesc('id')->first();
        try { $last->update(['action' => 'Tampered']); $this->fail('an entry was changed through the system'); } catch (\LogicException $e) { $this->assertStringContainsString('append-only', $e->getMessage()); }
        try { DB::table('audit_logs')->where('id', $last->id)->update(['action' => 'Tampered']); $this->fail('an entry was changed in the database'); } catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('append-only', $e->getMessage()); }
        try { DB::table('audit_logs')->where('id', $last->id)->delete(); $this->fail('an entry was deleted in the database'); } catch (\Illuminate\Database\QueryException $e) { $this->assertStringContainsString('append-only', $e->getMessage()); }

        // with the guard bypassed, the chain check names the first entry that no longer follows
        DB::unprepared('DROP TRIGGER audit_logs_append_only_update');
        DB::unprepared('DROP TRIGGER audit_logs_append_only_delete');
        $victim = $entries[(int) floor($entries->count() / 2)];
        DB::table('audit_logs')->where('id', $victim->id)->update(['action' => 'Tampered']);
        $r = AuditChain::verify();
        $this->assertSame([false, (int) $victim->id], [$r['intact'], $r['first_broken']]);
        $this->assertStringContainsString('changed', $r['reason']);
        $this->assertSame(1, \Illuminate\Support\Facades\Artisan::call('audit:verify-chain'));

        DB::table('audit_logs')->where('id', $victim->id)->delete();
        $r = AuditChain::verify();
        $this->assertFalse($r['intact']);
        $this->assertStringContainsString('removed or inserted', $r['reason']);
    }
}
