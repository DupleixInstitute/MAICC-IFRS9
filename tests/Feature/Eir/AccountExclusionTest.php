<?php

namespace Tests\Feature\Eir;

use App\Models\User;
use App\Services\ApprovalDocumentService;
use App\Services\Decisions\DecisionsPackExporter;
use App\Services\Decisions\DecisionsPackReplayer;
use App\Services\Ebanker\AccountExclusionService;
use App\Services\Ebanker\LandingZoneReader;
use App\Services\Ebanker\LoanBookBuildService;
use App\Services\Eir\FeeImportService;
use App\Services\Eir\GovernanceService;
use App\Support\AccountExclusions;
use Database\Seeders\GovernanceSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * The account exclusion register (MAIIC's confirmation of 16 October 2026):
 * maker-checker on a proposal, every build leaving an approved account out,
 * an approval moving the account's rows to the archive and a withdrawal
 * putting them back, the refusal while a row sits in a locked month, the
 * committed client input landed under the bootstrap label, and the register
 * carried by the decisions pack.
 */
class AccountExclusionTest extends TestCase
{
    use RefreshDatabase;

    private User $maker;

    private User $checker;

    private const UAT = '104430000063';

    private const LOAN = '104420000005';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->maker = User::create(['name' => 'Finance analyst', 'email' => 'maker@example.com', 'password' => bcrypt('x')]);
        $this->checker = User::create(['name' => 'Chief Finance Officer', 'email' => 'checker@example.com', 'password' => bcrypt('x')]);
        (new GovernanceSettingsSeeder())->run();
        DB::table('ebanker_loads')->insert(['id' => 1, 'pack_hash' => str_repeat('a', 64), 'pack_name' => 'fixture', 'manifest' => '{}', 'status' => 'LANDED', 'created_at' => now(), 'updated_at' => now()]);
        if (! DB::table('loan_portfolios')->exists()) {
            DB::table('loan_portfolios')->insert(['name' => 'Loans', 'created_by_id' => $this->maker->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->landZone();
    }

    private function raw(string $q, string $key, ?string $account, ?string $date, array $payload): void
    {
        DB::table('ebanker_raw_rows')->insert(['load_id' => 1, 'query_id' => $q, 'source_key' => $key, 'account' => $account, 'row_date' => $date,
            'payload' => json_encode($payload), 'row_hash' => md5($q . $key), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** A loan with a drawdown and a UAT test account in the masters, the stored run and the charges, as E-Banker holds both. */
    private function landZone(): void
    {
        foreach ([[self::LOAN, 'Micholess Creamery', '1050101'], [self::UAT, 'infotech private limited', '1050102']] as [$id, $name, $gl]) {
            $a = AccountExclusions::padded($id);
            $this->raw('P1_02', $a, $a, '2025-09-01', ['NEW_AC_NUMBER' => $a, 'GLCODE' => $gl, 'CUSTOMER_ID' => '31' . substr($id, -1), 'ACCOUNT_NAME' => $name, 'STATUS_CODE' => 'A', 'INTEREST_RATE' => '20.00', 'ACCOUNT_OPEN_DATE' => '9/1/2025']);
            $this->raw('P2_08', 'run' . $id, $a, '2025-09-30', ['LOAN_BOOK_DET_ID_A' => '1' . substr($id, -3), 'ASONDATE' => '9/30/2025', 'NEW_AC_NUMBER' => $a, 'GLCODE' => $gl, 'CARRYING_AMOUNT' => $id === self::LOAN ? '1000000.00' : '0.00', 'PRINCIPAL' => '0', 'DISBURSD' => '0']);
            $this->raw('P2_07', 'chg' . $id, $a, null, ['LO_DISB_CHARG_DET_ID' => 'chg' . $id, 'NEW_AC_NUMBER' => $a, 'ACTUAL_CHARGES_AMT' => '8000.00', 'DISB_CHARGES_NAME' => 'Arrangement fee', 'INCOME_GL' => '4871']);
        }
        $a = AccountExclusions::padded(self::LOAN);
        $this->raw('P1_01', '100', $a, '2025-09-30', ['CUMVOUCH_DET_ID' => '100', 'NEW_AC_NUMBER' => $a, 'AC_GLCODE' => '1050101', 'TRANSACTION_DATE' => '9/30/2025', 'TRANSAMT' => '-1000000.00', 'TRANTYPE' => '301', 'DELETE_FLAG' => 'N']);
    }

    private function propose(string $contract = self::UAT, ?int $by = null): int
    {
        return app(AccountExclusionService::class)->propose([
            'contract_id' => $contract, 'category' => 'UAT_TEST_ACCOUNT', 'reason' => 'A UAT test account of the E-Banker vendor',
            'evidence' => 'ACCOUNT_STATUS_HISTORY row 13361: status C, reason UAT accounts',
        ], $by ?? $this->maker->id);
    }

    private function document(int $id): void
    {
        (new ApprovalDocumentService())->attach('account-exclusion', $id, UploadedFile::fake()->createWithContent('MAIIC confirmation.pdf', '%PDF UAT accounts'), 'correspondence', 'MAIIC e-mail of 16 October 2026', $this->maker->id);
    }

    /** Book rows of the UAT account, as an earlier build left them: two months, a contract, a fee line and its classification event. */
    private function holdRows(): void
    {
        foreach (['2025-09', '2025-10'] as $p) {
            DB::table('loan_books')->insert(['contract_id' => self::UAT, 'customer_id' => '313', 'customer_name' => 'infotech private limited', 'external_identity_id' => AccountExclusions::padded(self::UAT),
                'reporting_period' => $p, 'reporting_year' => 2025, 'reporting_month' => (int) substr($p, 5), 'loan_portfolio_id' => 1, 'carrying_amount' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('contract_eir')->insert(['contract_id' => self::UAT, 'customer_name' => 'infotech private limited', 'approved_amount' => 8000000, 'drawn_amount' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $fee = DB::table('contract_fees')->insertGetId(['contract_id' => self::UAT, 'fee_type' => 'arrangement', 'amount' => 8000, 'currency' => 'MWK', 'classification_status' => 'PENDING', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('eir_fee_classification_events')->insert(['contract_fee_id' => $fee, 'action' => 'CLASSIFIED', 'reason' => 'arrangement fee', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_the_person_who_proposed_cannot_approve_and_an_approval_needs_its_document(): void
    {
        $id = $this->propose();
        $service = app(AccountExclusionService::class);
        try {
            $service->approve($id, $this->maker->id);
            $this->fail('the proposer approved their own exclusion');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('cannot approve it', $e->getMessage());
        }
        try {
            $service->approve($id, $this->checker->id);
            $this->fail('approved without its supporting document');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('needs a supporting document', $e->getMessage());
        }
        $this->document($id);
        $service->approve($id, $this->checker->id);
        $row = DB::table('account_exclusions')->find($id);
        $this->assertSame('APPROVED', $row->status);
        $this->assertSame($this->checker->id, (int) $row->approved_by);
        $this->assertTrue(AccountExclusions::isExcluded(AccountExclusions::padded(self::UAT)));
        $this->assertSame(['Account Exclusion Proposed', 'Approval Document Added', 'Account Exclusion Approved'], DB::table('audit_logs')->where('entity_type', 'account_exclusions')->orderBy('id')->pluck('action')->all());
        // the proposer withdraws a proposal; a second person rejects it, never the proposer
        $other = $this->propose(self::LOAN);
        try {
            $service->close($other, $this->maker->id, 'reject', 'my own proposal, rejected by me');
            $this->fail('the proposer rejected their own proposal');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('withdraw it instead', $e->getMessage());
        }
        $service->close($other, $this->checker->id, 'reject', 'Micholess Creamery is a real loan of the book');
        $this->assertSame('REJECTED', DB::table('account_exclusions')->find($other)->status);
    }

    public function test_every_build_leaves_an_approved_account_out(): void
    {
        config(['approvals.require_evidence' => false]);
        $build = fn () => (new LoanBookBuildService(new LandingZoneReader(), new GovernanceService()))->build('2025-09', '2025-09', 'B', $this->maker->id, $this->checker->id);
        $build();
        $this->assertEqualsCanonicalizing([self::LOAN, self::UAT], DB::table('loan_books')->pluck('contract_id')->all());
        DB::table('loan_books')->delete();

        app(AccountExclusionService::class)->approve($this->propose(), $this->checker->id);
        $build();
        $this->assertSame([self::LOAN], DB::table('loan_books')->pluck('contract_id')->all());
        // the zone itself is untouched, and reads as received for the gates
        $this->assertCount(2, (new LandingZoneReader())->includingExcluded()->accountMaster());
        $this->assertCount(1, (new LandingZoneReader())->accountMaster());
        // the fee lines from the charges: none for the excluded account, from the zone or from a file
        app(\App\Services\Ebanker\ContractInputsBuildService::class)->fees();
        $this->assertSame([self::LOAN], DB::table('contract_fees')->distinct()->pluck('contract_id')->all());
        $r = app(FeeImportService::class)->import([['contract_id' => AccountExclusions::padded(self::UAT), 'fee_type' => 'arrangement', 'amount' => 100, 'currency' => 'MWK']]);
        $this->assertSame(0, $r['loaded_rows']);
        $this->assertSame(1, $r['excluded_rows']);
    }

    public function test_an_approval_moves_the_rows_to_the_archive_and_a_withdrawal_puts_them_back(): void
    {
        config(['approvals.require_evidence' => false]);
        $this->holdRows();
        $before = DB::table('loan_books')->where('contract_id', self::UAT)->orderBy('id')->pluck('id')->all();
        $service = app(AccountExclusionService::class);
        $id = $this->propose();
        $this->assertSame(['eir_fee_classification_events' => 1, 'contract_fees' => 1, 'contract_eir' => 1, 'loan_books' => 2], $service->footprint(self::UAT));

        $r = $service->approve($id, $this->checker->id);
        $this->assertSame(5, $r['archived']);
        $this->assertSame(0, DB::table('loan_books')->where('contract_id', self::UAT)->count());
        $this->assertSame(0, DB::table('contract_eir')->where('contract_id', self::UAT)->count());
        $this->assertSame(0, DB::table('contract_fees')->where('contract_id', self::UAT)->count());
        $this->assertSame(0, DB::table('eir_fee_classification_events')->count());
        $this->assertSame(5, DB::table('account_exclusion_archive')->where('exclusion_id', $id)->whereNull('restored_at')->count());

        // a withdrawal is asked by one person and approved by another
        $service->close($id, $this->maker->id, 'withdraw', 'the account turned out to be a live loan');
        try {
            $service->approveWithdrawal($id, $this->maker->id);
            $this->fail('the person who asked approved the withdrawal');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('cannot approve it', $e->getMessage());
        }
        $back = $service->approveWithdrawal($id, $this->checker->id);
        $this->assertSame(5, $back['restored']);
        $this->assertSame($before, DB::table('loan_books')->where('contract_id', self::UAT)->orderBy('id')->pluck('id')->all());
        $this->assertSame(1, DB::table('contract_eir')->where('contract_id', self::UAT)->count());
        $fee = DB::table('contract_fees')->where('contract_id', self::UAT)->first();
        $this->assertSame((int) $fee->id, (int) DB::table('eir_fee_classification_events')->value('contract_fee_id'));
        $this->assertSame('WITHDRAWN', DB::table('account_exclusions')->find($id)->status);
        $this->assertFalse(AccountExclusions::isExcluded(self::UAT));
        $this->assertSame(0, DB::table('account_exclusion_archive')->whereNull('restored_at')->count());
    }

    public function test_an_approval_is_refused_while_a_row_sits_in_a_locked_month(): void
    {
        config(['approvals.require_evidence' => false]);
        $this->holdRows();
        DB::table('reporting_period_locks')->insert(['reporting_period' => '2025-10', 'locked_by' => $this->checker->id, 'locked_at' => now(), 'reason' => 'October closed', 'created_at' => now(), 'updated_at' => now()]);
        $id = $this->propose();
        try {
            app(AccountExclusionService::class)->approve($id, $this->checker->id);
            $this->fail('approved over a locked month');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('locked reporting period(s) 2025-10', $e->getMessage());
            $this->assertStringContainsString('unlock it first', $e->getMessage());
        }
        $this->assertSame('PROPOSED', DB::table('account_exclusions')->find($id)->status);
        $this->assertSame(2, DB::table('loan_books')->where('contract_id', self::UAT)->count());
        $this->assertSame(0, DB::table('account_exclusion_archive')->count());
    }

    public function test_the_committed_client_input_lands_as_approved_under_the_bootstrap_label(): void
    {
        $file = base_path('docs/bootstrap/' . AccountExclusionService::CLIENT_INPUT);
        // an entry a person rejected on this database is not overridden by the file
        $rejected = $this->propose();
        app(AccountExclusionService::class)->close($rejected, $this->checker->id, 'reject', 'kept in the book by a person here');

        $r = app(AccountExclusionService::class)->landClientInput($file);
        $this->assertSame(12, $r['accounts']);
        $this->assertSame(11, $r['landed']);
        $this->assertSame([self::UAT => 'rejected (#' . $rejected . ')'], $r['already']);
        $landed = DB::table('account_exclusions')->where('source', 'CLIENT_INPUT')->get();
        $this->assertCount(11, $landed);
        foreach ($landed as $x) {
            $this->assertSame('APPROVED', $x->status);
            $this->assertSame(LoanBookBuildService::BOOTSTRAP_LABEL, $x->approver_label);
            $this->assertNull($x->approved_by);   // never as a MAIIC person
            $this->assertNull($x->proposed_by);
            $this->assertStringContainsString('confirmed by MAIIC, 16 October 2026', $x->evidence);
        }
        $this->assertSame(3, $landed->where('category', 'DUPLICATE_APPROVAL')->count());
        $this->assertSame('104430000082', $landed->firstWhere('contract_id', '104430000081')->sister_contract_id);
        // landed again: nothing changes
        $again = app(AccountExclusionService::class)->landClientInput($file);
        $this->assertSame(0, $again['landed']);
        $this->assertCount(12, $again['already']);
    }

    /**
     * Release review of 16 October 2026: a control reads the zone as received. The old ledger
     * (CV_04a) holds 58,413,511.84 of the two 2022 loans the register excludes; the tie of the
     * copy to the GL opening (CV_14) must not move by it, while the book still leaves them out.
     */
    public function test_a_control_reads_the_zone_as_received_and_the_book_leaves_the_excluded_account_out(): void
    {
        config(['approvals.require_evidence' => false]);
        $seeds = AccountExclusions::padded('104420000044');
        $this->raw('P1_02', $seeds, $seeds, '2022-08-30', ['NEW_AC_NUMBER' => $seeds, 'GLCODE' => '1050202', 'ACCOUNT_NAME' => 'Global Seeds Limited FInES', 'STATUS_CODE' => 'C']);
        $this->raw('CV_04a', 'old1', $seeds, '2022-08-30', ['CUMVOUCH_DET_ID' => 'old1', 'NEW_AC_NUMBER' => $seeds, 'AC_GLCODE' => '1050202', 'TRANSAMT' => '-103225546.00', 'DELETE_FLAG' => 'N']);
        $this->raw('CV_04a', 'old2', $seeds, '2023-06-30', ['CUMVOUCH_DET_ID' => 'old2', 'NEW_AC_NUMBER' => $seeds, 'AC_GLCODE' => '1050202', 'TRANSAMT' => '52319941.42', 'DELETE_FLAG' => 'N']);
        // the GL opening of 1 Jan 2024, 0.50 off the copy as on the real data
        $this->raw('CV_14', 'gl1050202', null, null, ['GLCODE' => '1050202', 'OPENING_BAL' => '-50905605.08']);
        $tie = fn () => app(\App\Services\Eir\BaselineService::class)->takeonWorksheetTie()['premigration_ledger_vs_gl_opening']['value'];
        $this->assertEqualsWithDelta(0.50, $tie(), 0.001);

        $id = app(AccountExclusionService::class)->landClientInput(base_path('docs/bootstrap/' . AccountExclusionService::CLIENT_INPUT));
        $this->assertTrue(AccountExclusions::isExcluded($seeds));
        // the control still reads every row, whatever reader it is handed
        $this->assertEqualsWithDelta(0.50, $tie(), 0.001);
        $this->assertEqualsWithDelta(0.50, (new \App\Services\Eir\BaselineService(new LandingZoneReader()))->takeonWorksheetTie()['premigration_ledger_vs_gl_opening']['value'], 0.001);
        // the book's view leaves the account out; the zone as received holds it
        $this->assertCount(0, (new LandingZoneReader())->family(['CV_04a']));
        $this->assertCount(2, LandingZoneReader::asReceived()->family(['CV_04a']));
        $this->assertFalse((new LandingZoneReader())->readsAsReceived());
        $this->assertTrue(LandingZoneReader::asReceived()->readsAsReceived());
    }

    /** Pack 13, question C5: the two 2022 loans are excluded truthfully, and the excluded set is the same twelve. */
    public function test_the_two_closed_2022_loans_are_labelled_truthfully_and_the_excluded_set_is_unchanged(): void
    {
        $doc = json_decode((string) file_get_contents(base_path('docs/bootstrap/' . AccountExclusionService::CLIENT_INPUT)), true);
        $ids = array_column($doc['accounts'], 'contract_id');
        sort($ids);
        $this->assertSame(['104420000044', '104430000063', '104430000065', '104430000066', '104430000067', '104430000081', '104430000083', '104430000088',
            '104450000096', '104450000097', '104460000029', '104460000103'], $ids);
        $by = array_column($doc['accounts'], 'category', 'contract_id');
        $this->assertSame('CLOSED_BEFORE_WINDOW', $by['104420000044']);
        $this->assertSame('CLOSED_BEFORE_WINDOW', $by['104460000029']);
        $this->assertSame(7, count(array_filter($by, fn ($c) => $c === 'UAT_TEST_ACCOUNT')));
        $this->assertArrayHasKey('CLOSED_BEFORE_WINDOW', AccountExclusionService::CATEGORIES);
        $text = json_encode($doc['accounts']);
        $this->assertStringContainsString('103,225,546.00 drawn on 30 Aug 2022', $text);
        $this->assertStringContainsString('21,295,000.00 drawn on 14 Jun 2022', $text);

        // a database that landed the earlier wording follows the file: the label changes, the set does not
        $service = app(AccountExclusionService::class);
        $service->landClientInput(base_path('docs/bootstrap/' . AccountExclusionService::CLIENT_INPUT));
        DB::table('account_exclusions')->whereIn('contract_id', ['104420000044', '104460000029'])->update(['category' => 'UAT_TEST_ACCOUNT', 'reason' => 'A UAT test account']);
        $before = array_keys(AccountExclusions::approved());
        $r = $service->landClientInput(base_path('docs/bootstrap/' . AccountExclusionService::CLIENT_INPUT));
        AccountExclusions::forget();
        $this->assertEqualsCanonicalizing(['104420000044', '104460000029'], $r['relabelled']);
        $this->assertSame(0, $r['landed']);
        $this->assertEqualsCanonicalizing($before, array_keys(AccountExclusions::approved()));
        $this->assertSame('CLOSED_BEFORE_WINDOW', DB::table('account_exclusions')->where('contract_id', '104420000044')->value('category'));
        $this->assertSame(2, DB::table('audit_logs')->where('action', 'Account Exclusion Relabelled')->count());
    }

    public function test_the_screen_lists_the_register_and_proposes_through_the_service(): void
    {
        foreach (['eir.view', 'eir.govern'] as $p) {
            \Spatie\Permission\Models\Permission::findOrCreate($p, 'web');
        }
        $this->maker->givePermissionTo(['eir.view', 'eir.govern']);
        $this->checker->givePermissionTo(['eir.view', 'eir.govern']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        [$this->maker, $this->checker] = [$this->maker->fresh(), $this->checker->fresh()];

        $this->actingAs($this->maker)->post(route('account-exclusions.store'), ['contract_id' => self::UAT, 'category' => 'UAT_TEST_ACCOUNT',
            'reason' => 'A UAT test account of the E-Banker vendor', 'evidence' => 'ACCOUNT_STATUS_HISTORY row 13361'])->assertSessionHas('success');
        $id = (int) DB::table('account_exclusions')->value('id');
        // the proposer is refused, the message shown as the error modal
        $this->actingAs($this->maker)->post(route('account-exclusions.approve', $id))->assertSessionHas('error');
        $this->flushSession(); // a second person signs in: the session of the first is not theirs
        $this->actingAs($this->checker)->get(route('account-exclusions.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Eir/AccountExclusions/Index')->has('register', 1)->where('register.0.contract_id', self::UAT)
                ->where('register.0.status', 'PROPOSED')->where('summary.proposed', 1));
    }

    public function test_the_register_is_carried_by_the_decisions_pack(): void
    {
        config(['approvals.require_evidence' => false]);
        $id = $this->propose();
        app(AccountExclusionService::class)->approve($id, $this->checker->id);
        $folder = storage_path('framework/testing/decisions-' . uniqid());
        $export = (new DecisionsPackExporter())->export($folder, 'exclusions-test');
        $this->assertSame(1, $export['counts']['account_exclusions']);

        // a fresh database: the register is empty and the account's rows are back in the book
        DB::table('account_exclusions')->delete();
        AccountExclusions::forget();
        $replayer = new DecisionsPackReplayer($folder);
        $summary = $replayer->replayExclusions();
        $this->assertSame(['APPLIED' => 1], $summary['account_exclusions']);
        $x = DB::table('account_exclusions')->where('contract_id', self::UAT)->first();
        $this->assertSame('APPROVED', $x->status);
        $this->assertSame('DECISIONS_PACK', $x->source);
        $this->assertSame($this->maker->id, (int) $x->proposed_by);
        $this->assertSame($this->checker->id, (int) $x->approved_by);
        $this->assertTrue(AccountExclusions::isExcluded(self::UAT));
        // replayed again: already here
        $this->assertSame(['ALREADY' => 1], (new DecisionsPackReplayer($folder))->replayExclusions()['account_exclusions']);
        \Illuminate\Support\Facades\File::deleteDirectory($folder);
    }
}
