<?php

namespace Tests\Feature\Eir;

use App\Models\User;
use App\Services\Decisions\DecisionsPack;
use App\Services\Decisions\DecisionsPackExporter;
use App\Services\Decisions\DecisionsPackReplayer;
use App\Services\Ebanker\LoanBookBuildService;
use App\Services\Eir\EirCalculationService;
use App\Services\Eir\FeeSourcePrecedence;
use App\Services\Eir\GovernanceService;
use Database\Seeders\GovernanceSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The decisions pack (Staging Module Specification v1, section 8.10;
 * acceptance 24 to 27): what people decided in one database, exported by
 * business key without changing it, and replayed into a fresh one with the
 * original people and dates, never under the bootstrap label; whatever does
 * not match there is listed, never forced.
 */
class DecisionsPackTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    private User $admin;

    private User $analyst;

    private User $cfo;

    private int $auditFrom = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/decisions-' . uniqid());
        (new GovernanceSettingsSeeder())->run();
        $this->admin = User::firstOrCreate(['email' => 'admin@localhost.com'], ['name' => 'Admin', 'password' => bcrypt('x')]);
        $this->analyst = User::create(['name' => 'Finance analyst', 'email' => 'Analyst@MAIIC.example', 'password' => bcrypt('secret-one')]);
        $this->cfo = User::create(['name' => 'Chief Finance Officer', 'email' => 'cfo@maiic.example', 'password' => bcrypt('secret-two')]);
        Role::create(['name' => 'Finance reviewer', 'guard_name' => 'web']);
        $this->analyst->assignRole('Finance reviewer');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function contract(string $id): void
    {
        DB::table('contract_eir')->insert(['contract_id' => $id, 'instrument_type' => 'AMORTISED_LOAN', 'rate_type' => 'FIXED', 'origination_date' => '2025-01-01',
            'drawn_amount' => 1000, 'payments_per_year' => 12, 'frequency_source' => 'STATED', 'schedule_source' => 'GENERATED', 'schedule_approval_status' => 'APPROVED',
            'calculation_status' => 'PENDING', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('contract_cashflow_schedule')->insert([
            ['contract_id' => $id, 'schedule_version' => 1, 'due_date' => '2025-02-01', 'principal_due' => 500, 'interest_due' => 50, 'created_at' => now(), 'updated_at' => now()],
            ['contract_id' => $id, 'schedule_version' => 1, 'due_date' => '2025-03-01', 'principal_due' => 500, 'interest_due' => 25, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /** The lines the bootstrap builds from files: one LOS charge, keyed by its source transaction. */
    private function systemLine(): int
    {
        return DB::table('contract_fees')->insertGetId(['contract_id' => '900000000301', 'fee_type' => 'arrangement', 'amount' => 30, 'transaction_date' => '2025-01-01',
            'cashflow_direction' => 'RECEIVED', 'classification_status' => 'PENDING', 'source_system' => FeeSourcePrecedence::SOURCE_LOS,
            'external_transaction_id' => 'P2_07:9', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function classify(int $feeId, bool $integral, User $maker, ?User $checker): void
    {
        DB::table('contract_fees')->where('id', $feeId)->update(['integral' => $integral, 'classification_status' => $checker ? 'REVIEWED' : 'CLASSIFIED', 'classification_reason' => 'Origination fee, IFRS 9 B5.4.2',
            'classified_by' => $maker->id, 'classified_at' => '2026-10-15 10:00:00', 'reviewed_by' => $checker?->id, 'reviewed_at' => $checker ? '2026-10-15 11:00:00' : null]);
        DB::table('eir_fee_classification_events')->insert(['contract_fee_id' => $feeId, 'action' => 'CLASSIFIED', 'integral' => $integral, 'reason' => 'Origination fee', 'performed_by' => $maker->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function move(int $feeId, string $to, User $user): void
    {
        $this->actingAs($user);
        $request = \Illuminate\Http\Request::create('/eir-fee-classification/fee/' . $feeId . '/move', 'POST', ['contract_id' => $to, 'reason' => 'The rows carried each other fees (077/110).']);
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => $user);
        app()->instance('request', $request);
        app(\App\Http\Controllers\EirFeeClassificationController::class)->moveFee($request, \App\Models\ContractFee::findOrFail($feeId));
    }

    /** The source database: a governance change, a Finance line, a moved LOS line, both reviewed, and one EIR locked by a person. */
    private function sourceDecisions(): void
    {
        foreach (['900000000301', '900000000302', '900000000303'] as $id) {
            $this->contract($id);
        }
        $gov = app(GovernanceService::class);
        $p = $gov->propose('fee_source_precedence', FeeSourcePrecedence::FINANCE_FIRST, '2024-07-01', 'Finance reconciled its 2025 fee workbook to the GL', $this->analyst->id);
        $gov->approve($p->id, $this->cfo->id);

        $finance = DB::table('contract_fees')->insertGetId(['contract_id' => '900000000302', 'fee_type' => 'legal', 'amount' => 20, 'transaction_date' => '2025-01-01', 'description' => 'Legal fee',
            'cashflow_direction' => 'RECEIVED', 'classification_status' => 'PENDING', 'source_system' => 'MAIIC Finance EIR assessment 2025', 'created_at' => now(), 'updated_at' => now()]);
        $this->classify($finance, true, $this->analyst, $this->cfo);
        $los = $this->systemLine();
        $this->move($los, '900000000302', $this->analyst);
        $this->classify($los, true, $this->analyst, $this->cfo);

        $calc = app(EirCalculationService::class);
        $this->assertSame('CALCULATED', $calc->calculate('900000000303', $this->analyst->id)['status']);
        $calc->lock('900000000303', $this->cfo->id);
        DB::table('contract_eir')->where('contract_id', '900000000303')->update(['locked_at' => '2026-10-15 12:00:00']);
    }

    /** What a fresh bootstrap leaves: the system lines unclassified where the files put them, nothing decided, no people but the seeded admin. */
    private function freshDatabase(): void
    {
        app('auth')->forgetGuards(); // the bootstrap runs from the console, signed in as nobody
        DB::table('eir_fee_classification_events')->delete();
        DB::table('fee_source_decisions')->delete();
        DB::table('contract_fees')->delete();
        DB::table('governance_settings')->whereNotNull('set_by')->delete();
        DB::table('governance_setting_history')->delete();
        DB::table('contract_eir')->update(['calculation_status' => 'PENDING', 'eir_period' => null, 'eir_effective_annual' => null, 'eir_nominal_annual' => null, 'input_snapshot' => null,
            'calculated_at' => null, 'calculated_by' => null, 'locked_at' => null, 'locked_by' => null, 'locked_label' => null]);
        // the audit log is append-only (section 8.5): the source's entries stay, and the replay's are counted from here
        $this->auditFrom = (int) DB::table('audit_logs')->max('id');
        DB::table('model_has_roles')->delete();
        // not in the fresh database: their rows stay (the append-only audit log points at them) under e-mails the pack does not name
        foreach ([$this->analyst, $this->cfo] as $u) {
            DB::table('users')->where('id', $u->id)->update(['email' => 'retired-' . $u->id . '@example.invalid']);
        }
        app()->forgetInstance(GovernanceService::class);
        $this->systemLine();
    }

    private function snapshotOfTables(): array
    {
        $out = [];
        foreach (['users', 'contract_fees', 'contract_eir', 'governance_settings', 'audit_logs', 'eir_fee_classification_events', 'fee_source_decisions', 'eir_reopen_requests'] as $t) {
            $out[$t] = md5(json_encode(DB::table($t)->orderBy('id')->get()));
        }

        return $out;
    }

    public function test_the_export_writes_nothing_and_names_no_database_id_no_password(): void
    {
        $this->sourceDecisions();
        $before = $this->snapshotOfTables();
        $r = (new DecisionsPackExporter())->export($this->dir, '2026-10-15');
        $this->assertSame($before, $this->snapshotOfTables(), 'the database the pack is read from is unchanged');

        $this->assertSame(1, $r['manifest']['tie_out']['person_locked_eirs']);
        $this->assertSame(2, $r['manifest']['tie_out']['reviewed_lines']);
        $all = implode("\n", array_map(fn ($f) => file_get_contents($this->dir . '/' . $f), DecisionsPack::FILES));
        $this->assertDoesNotMatchRegularExpression('/"(id|password|remember_token|two_factor_secret|contract_fee_id|setting_id)":/', $all);
        $this->assertStringContainsString('analyst@maiic.example', $all, 'people are named by e-mail');
        $this->assertStringNotContainsString(bcrypt('secret-one'), $all);

        $this->expectException(\RuntimeException::class);
        (new DecisionsPackExporter())->export($this->dir, '2026-10-15'); // a pack is written once
    }

    public function test_a_fresh_database_gets_the_same_decisions_with_their_people_and_dates(): void
    {
        $this->sourceDecisions();
        $sourceRate = (float) DB::table('contract_eir')->where('contract_id', '900000000303')->value('eir_effective_annual');
        (new DecisionsPackExporter())->export($this->dir, '2026-10-15');
        $this->freshDatabase();

        $replay = new DecisionsPackReplayer($this->dir);
        $replay->replay($this->admin->id);
        $this->assertSame(DecisionsPackReplayer::APPLIED, $replay->carryLock($replay->locks()['900000000303'], $this->admin->id));

        $analyst = User::where('email', 'analyst@maiic.example')->first();
        $cfo = User::where('email', 'cfo@maiic.example')->first();
        $this->assertNotNull($analyst);
        $this->assertNotSame($this->analyst->id, $analyst->id, 'a carried user is a new row, matched by e-mail');
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('secret-one', $analyst->password), 'no password is carried (decision S13)');
        $this->assertTrue($analyst->hasRole('Finance reviewer'));

        $setting = app(GovernanceService::class)->inForce('fee_source_precedence');
        $this->assertSame([FeeSourcePrecedence::FINANCE_FIRST, $analyst->id, $cfo->id], [$setting->value, (int) $setting->set_by, (int) $setting->approved_by]);

        $lines = DB::table('contract_fees')->orderBy('id')->get();
        $this->assertCount(2, $lines);
        foreach ($lines as $l) {
            $this->assertSame(['900000000302', 'REVIEWED', $analyst->id, $cfo->id], [$l->contract_id, $l->classification_status, (int) $l->classified_by, (int) $l->reviewed_by]);
        }
        $this->assertSame('2026-10-15 11:00:00', (string) $lines[0]->reviewed_at, 'the original date is kept');

        $eir = DB::table('contract_eir')->where('contract_id', '900000000303')->first();
        $this->assertSame(['LOCKED', $cfo->id, null, '2026-10-15 12:00:00'], [$eir->calculation_status, (int) $eir->locked_by, $eir->locked_label, (string) $eir->locked_at]);
        $this->assertEqualsWithDelta($sourceRate, (float) $eir->eir_effective_annual, 1e-12);

        $carried = DB::table('audit_logs')->where('id', '>', $this->auditFrom)->where('action', DecisionsPack::AUDIT_ACTION)->get();
        $this->assertGreaterThanOrEqual(6, $carried->count());
        $this->assertStringContainsString('Carried from', (string) $carried->first()->meta);
        $this->assertSame(0, DB::table('audit_logs')->where('id', '>', $this->auditFrom)->where('meta', 'like', '%' . LoanBookBuildService::BOOTSTRAP_LABEL . '%')->count(), 'never under the bootstrap label');
        $this->assertSame([], $replay->notCarried());
        $tie = $replay->tieOut();
        $this->assertSame($tie['reviewed fee lines in force']['source'], $tie['reviewed fee lines in force']['here']);
        $this->assertSame(1, $tie['EIRs locked by a person']['here']);
    }

    public function test_a_decision_whose_line_differs_is_listed_and_left_for_a_person(): void
    {
        $this->sourceDecisions();
        (new DecisionsPackExporter())->export($this->dir, '2026-10-15');
        $this->freshDatabase();
        DB::table('contract_fees')->update(['amount' => 31]); // the files now give the LOS charge a different amount

        $replay = new DecisionsPackReplayer($this->dir);
        $replay->replay($this->admin->id);
        $los = DB::table('contract_fees')->where('external_transaction_id', 'P2_07:9')->first();
        $this->assertSame('PENDING', $los->classification_status, 'not forced');
        $listed = collect($replay->notCarried())->firstWhere('kind', 'classifications');
        $this->assertSame(DecisionsPackReplayer::DIFFERENT, $listed['outcome']);
        $this->assertStringContainsString('amount 31.00 here, 30.00 in the source', $listed['detail']);

        // the EIR now solves on other cash flows: calculated, not locked
        DB::table('contract_cashflow_schedule')->where('contract_id', '900000000303')->where('due_date', '2025-03-01')->update(['interest_due' => 30]);
        $this->assertSame(DecisionsPackReplayer::DIFFERENT, $replay->carryLock($replay->locks()['900000000303'], $this->admin->id));
        $this->assertSame('CALCULATED', DB::table('contract_eir')->where('contract_id', '900000000303')->value('calculation_status'));

        $csv = $replay->writeReport($this->dir . '/replay.csv');
        $this->assertStringContainsString('DIFFERENT', file_get_contents($csv));
    }

    public function test_a_pack_changed_after_export_is_refused(): void
    {
        $this->sourceDecisions();
        (new DecisionsPackExporter())->export($this->dir, '2026-10-15');
        file_put_contents($this->dir . '/classifications.json', str_replace('"REVIEWED"', '"CLASSIFIED"', file_get_contents($this->dir . '/classifications.json')));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not match its manifest');
        new DecisionsPackReplayer($this->dir);
    }

    /** A raw approved change, as an approval made before the supporting-document rule leaves it. */
    private function approvedWithoutDocument(string $key, string $value, string $from, string $approvedAt): int
    {
        $c = GovernanceService::catalogue()[$key];

        return DB::table('governance_settings')->insertGetId(['key' => $key, 'value' => $value, 'options' => json_encode($c['options']), 'label' => $c['label'], 'description' => 'test',
            'effective_from' => $from, 'status' => 'APPROVED', 'set_by' => $this->analyst->id, 'approved_by' => $this->cfo->id, 'approved_at' => $approvedAt,
            'reason' => 'Credit Committee decision of ' . substr($approvedAt, 0, 10), 'created_at' => $approvedAt, 'updated_at' => $approvedAt]);
    }

    public function test_documents_are_carried_and_an_approval_before_the_evidence_rule_is_carried_as_one(): void
    {
        \Illuminate\Support\Facades\Storage::fake(\App\Services\Eir\GovernanceDocumentService::DISK);
        $gov = app(GovernanceService::class);
        // with its minute: carried with the minute
        $preset = $gov->propose(\App\Services\Eir\StagingPolicy::PRESET, \App\Services\Eir\StagingPolicy::PRESET_DIRECTIVE, '2026-01-01', 'Board adopts directive-aligned staging', $this->analyst->id);
        $doc = app(\App\Services\Eir\GovernanceDocumentService::class)->attach($preset, \Illuminate\Http\UploadedFile::fake()->createWithContent('Board minute.pdf', '%PDF-1.4 board minute 12 Oct 2026'),
            'minute', 'Board, item 4', $this->analyst->id);
        $gov->approve($preset->id, $this->cfo->id);
        DB::table('governance_settings')->where('id', $preset->id)->update(['approved_at' => '2026-10-14 09:00:00']); // approved first, as on a real source
        // the rule came in on the source at 08:00 on 16 October: one approval before it, one after, neither with a document
        DB::table('system_milestones')->where('key', \App\Support\EvidenceRule::MILESTONE)->update(['at' => '2026-10-16 08:00:00']);
        $this->approvedWithoutDocument('stage2_threshold_medium_long', '91 days, with documented rebuttal evidence', '2026-03-01', '2026-10-15 09:00:00');
        $this->approvedWithoutDocument('staging_threshold_profile', GovernanceService::catalogue()['staging_threshold_profile']['options'][1], '2026-04-01', '2026-10-16 10:00:00');

        $r = (new DecisionsPackExporter())->export($this->dir, '2026-10-16');
        $this->assertSame('2026-10-16 08:00:00', $r['manifest']['evidence_rule_from']);
        $this->assertSame(1, $r['manifest']['tie_out']['documents']);
        $this->assertFileExists($this->dir . '/documents/' . $doc->hash . '.pdf');

        $this->freshDatabase();
        DB::table('supporting_documents')->delete();
        DB::table('system_milestones')->where('key', \App\Support\EvidenceRule::MILESTONE)->update(['at' => now()]); // the fresh install's own rule start
        $replay = new DecisionsPackReplayer($this->dir);
        $replay->replay($this->admin->id);

        $outcomes = collect($replay->report())->where('kind', 'governance')->pluck('outcome', 'key')->all();
        $this->assertSame([DecisionsPackReplayer::APPLIED, DecisionsPackReplayer::GRANDFATHERED, DecisionsPackReplayer::REFUSED], array_values($outcomes));
        $carriedPreset = DB::table('governance_settings')->where('key', \App\Services\Eir\StagingPolicy::PRESET)->whereDate('effective_from', '2026-01-01')->first();
        $this->assertSame([$doc->hash], DB::table('supporting_documents')->where('documentable_id', $carriedPreset->id)->pluck('hash')->all(), 'the minute, the same file');
        $this->assertSame(0, DB::table('governance_settings')->where('key', 'staging_threshold_profile')->where('status', 'PROPOSED')->count(), 'a refused approval leaves no proposal behind');

        $grand = collect((new \App\Services\ApprovalsRegisterService())->rows(['status' => 'grandfathered']));
        $this->assertSame(['stage2_threshold_medium_long'], $grand->map(fn ($g) => explode(' ', $g['title'])[0])->all());
        $this->assertSame(1, $replay->tieOut()['approved before supporting documents were required']['here']);
    }
}
