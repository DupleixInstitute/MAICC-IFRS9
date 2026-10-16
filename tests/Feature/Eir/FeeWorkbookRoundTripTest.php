<?php

namespace Tests\Feature\Eir;

use App\Models\GovernanceSetting;
use App\Models\User;
use App\Services\Eir\EirReadinessService;
use App\Services\Eir\FeeReconciliationWorkbook;
use App\Services\Eir\FeeWorkbookAnswerService;
use App\Services\Eir\FeeWorkbookReader;
use App\Services\Eir\GovernanceDocumentService;
use App\Services\Eir\GovernanceService;
use App\Services\Eir\UnpostedFeeBasis;
use Database\Seeders\GovernanceSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use LogicException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Finance's fee reconciliation workbook, round trip (16 October 2026; spec v4 section 7.8.9): the download
 * carries a key per answer row and a fingerprint recorded in the audit log; the upload maps every answer to
 * exactly one record (by key, or by the visible columns for the offline workbook with no keys) and changes
 * nothing; a second person approves each answer through the service that owns it; the setting
 * los_unposted_fee_basis leaves out an unconfirmed LOS charge never posted and flags the locked EIRs whose
 * inputs change, never reopening one; at its default nothing moves.
 */
class FeeWorkbookRoundTripTest extends TestCase
{
    use RefreshDatabase;

    private const OPEN = '900000000201';
    private const LOCKED = '900000000202';
    private const OTHER = '900000000203';

    private User $maker;
    private User $checker;
    private array $fee = [];
    private array $held = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->maker = User::create(['name' => 'Finance analyst', 'email' => uniqid() . '@example.com', 'password' => bcrypt('x')]);
        $this->checker = User::create(['name' => 'Chief Finance Officer', 'email' => uniqid() . '@example.com', 'password' => bcrypt('x')]);
        foreach (['eir.govern', 'eir.fees.import', 'settings'] as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->maker->givePermissionTo(['eir.fees.import']);
        $this->checker->givePermissionTo(['eir.govern', 'eir.fees.import']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        [$this->maker, $this->checker] = [$this->maker->fresh(), $this->checker->fresh()];
        (new GovernanceSettingsSeeder())->run();
        $this->book();
    }

    /**
     * One loan open (LOS charges only), one locked (an LOS charge never posted in its EIR), one more with a GL
     * fee, the LOS line of the same fee at another amount (a source difference) and an LOS line no GL line pairs
     * with (an overlap); two held postings. The fee source precedence marks them as the engine does.
     */
    private function book(): void
    {
        foreach ([self::OPEN => null, self::LOCKED => now()->subMonth(), self::OTHER => null] as $id => $locked) {
            DB::table('contract_eir')->insert(['contract_id' => $id, 'customer_name' => 'Customer ' . substr($id, -3), 'instrument_type' => 'AMORTISED_LOAN', 'rate_type' => 'FIXED',
                'origination_date' => '2025-01-01', 'drawn_amount' => 1000000, 'payments_per_year' => 12, 'frequency_source' => 'STATED', 'schedule_source' => 'GENERATED',
                'schedule_approval_status' => 'APPROVED', 'calculation_status' => $locked ? 'LOCKED' : 'PENDING', 'locked_at' => $locked, 'locked_by' => $locked ? 1 : null,
                'eir_effective_annual' => $locked ? 0.25 : null, 'created_at' => now()->subMonths(2), 'updated_at' => now()]);
            DB::table('contract_cashflow_schedule')->insert([
                ['contract_id' => $id, 'schedule_version' => 1, 'due_date' => '2025-02-01', 'principal_due' => 500000, 'interest_due' => 20000, 'created_at' => now(), 'updated_at' => now()],
                ['contract_id' => $id, 'schedule_version' => 1, 'due_date' => '2025-03-01', 'principal_due' => 500000, 'interest_due' => 10000, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
        $line = fn (string $c, string $type, float $amount, string $status, ?bool $integral, string $source = 'E-Banker LOS_DISB_CHARGES_POST', array $more = []) => DB::table('contract_fees')->insertGetId($more + [
            'contract_id' => $c, 'fee_type' => $type, 'amount' => $amount, 'description' => ucfirst($type) . ' Fee (%)', 'cashflow_direction' => 'RECEIVED', 'currency' => 'MWK',
            'source_system' => $source, 'classification_status' => $status, 'integral' => $integral, 'classified_by' => $status === 'PENDING' ? null : 1, 'reviewed_by' => $status === 'REVIEWED' ? 2 : null,
            'created_at' => now()->subMonths(2), 'updated_at' => now()->subMonths(2)]);
        $this->fee['open_los'] = $line(self::OPEN, 'arrangement', 20000, 'REVIEWED', true);
        $this->fee['open_other'] = $line(self::OPEN, 'other', 5000, 'PENDING', null);
        $this->fee['locked_los'] = $line(self::LOCKED, 'arrangement', 30000, 'REVIEWED', true);
        $this->fee['other_gl'] = $line(self::OTHER, 'legal', 10000, 'REVIEWED', true, 'E-Banker GL fee posting', ['gl_account_ref' => '4871', 'transaction_date' => '2025-01-01']);
        $this->fee['other_los'] = $line(self::OTHER, 'legal', 12000, 'PENDING', null);
        $this->fee['overlap'] = $line(self::OTHER, 'other', 7000, 'PENDING', null);
        $held = fn (string $ext, ?string $candidate, float $amount, string $narration) => DB::table('fee_gl_unassigned')->insertGetId(['external_transaction_id' => $ext, 'transaction_date' => '2025-01-02',
            'batch' => '2025-01-02 TRF ' . substr($ext, -1), 'gl_code' => '4873', 'amount' => $amount, 'narration' => $narration, 'reason' => 'NO_LOAN_IN_BATCH: no loan account is a leg of the batch',
            'loans_in_batch' => '[]', 'candidate_contract_id' => $candidate, 'candidate_evidence' => $candidate ? 'the narration carries the account name' : null, 'created_at' => now(), 'updated_at' => now()]);
        $this->held['tie'] = $held('GL_04a:1', self::OPEN, 15000, 'By Trf Customer 201 arrangement fees');
        $this->held['dismiss'] = $held('GL_04a:2', self::OTHER, 4000, 'By Trf Customer 203 guarantee fees');
        app(\App\Services\Eir\FeeSourcePrecedence::class)->apply();
        $this->assertNotNull(DB::table('contract_fees')->where('id', $this->fee['overlap'])->value('source_overlap'), 'the LOS line no GL line pairs with is an overlap');
        $this->assertSame(1, DB::table('fee_source_differences')->where('record_fee_id', $this->fee['other_gl'])->where('other_fee_id', $this->fee['other_los'])->count());
    }

    private function downloaded(): string
    {
        $file = app(FeeReconciliationWorkbook::class)->download($this->maker->id);

        return $file['path'];
    }

    /** Finance's pen: write answers by sheet, row predicate and header. */
    private function answer(string $path, array $answers, ?string $out = null): string
    {
        $book = IOFactory::load($path);
        foreach ($answers as [$sheet, $match, $values]) {
            $ws = $book->getSheetByName($sheet);
            $headers = [];
            foreach ($ws->getRowIterator(1, 1)->current()->getCellIterator() as $cell) {
                if ($cell->getValue() !== null) {
                    $headers[(string) $cell->getValue()] = $cell->getColumn();
                }
            }
            for ($r = 2; $r <= $ws->getHighestDataRow(); $r++) {
                $row = [];
                foreach ($headers as $h => $col) {
                    $row[$h] = $ws->getCell($col . $r)->getValue();
                }
                if ($match($row)) {
                    foreach ($values as $h => $v) {
                        $ws->getCell($headers[$h] . $r)->setValue($v);
                    }
                    break;
                }
            }
        }
        $out ??= tempnam(sys_get_temp_dir(), 'fw') . '.xlsx';
        (new Xlsx($book))->save($out);

        return $out;
    }

    private function upload(string $path, ?int $user = null): array
    {
        return app(FeeWorkbookAnswerService::class)->upload(new UploadedFile($path, 'answered.xlsx', null, null, true), $user ?? $this->maker->id);
    }

    private function answerId(string $sheet): int
    {
        return (int) DB::table('fee_workbook_answers')->where('sheet', $sheet)->orderBy('id')->value('id');
    }

    public function test_the_download_carries_a_key_per_row_and_a_fingerprint_recorded_in_the_audit_log(): void
    {
        $book = IOFactory::load($this->downloaded());
        $this->assertSame(['Read me', '1. Loan status', '2. Held GL postings', '3. Overlaps', '4. Source differences', '4b. Fees never posted', '5. Lines to classify', '6. Take-on loans', '7. Corrections made', '8. Fee lines'], $book->getSheetNames());
        $readme = $book->getSheetByName('Read me');
        $this->assertSame(FeeReconciliationWorkbook::FINGERPRINT_LABEL, $readme->getCell('Z1')->getValue());
        $this->assertFalse($readme->getColumnDimension('Z')->getVisible(), 'the fingerprint column is hidden');
        $fingerprint = json_decode($readme->getCell('Z2')->getValue(), true);
        $this->assertSame(DB::connection()->getDatabaseName(), $fingerprint['database']);

        $fees = $book->getSheetByName('8. Fee lines');
        $this->assertSame(FeeReconciliationWorkbook::KEY_HEADER, $fees->getCell('R1')->getValue());
        $this->assertFalse($fees->getColumnDimension('R')->getVisible(), 'the key column is hidden');
        $keys = [];
        foreach ($book->getWorksheetIterator() as $ws) {
            foreach ($ws->getRowIterator() as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $v = (string) $cell->getValue();
                    if (preg_match('/^(loan|account|held|overlap|diff|unposted|classify|takeon|move|fee|confirm):/', $v)) {
                        $keys[] = $v;
                    }
                }
            }
        }
        $this->assertContains('fee:' . $this->fee['locked_los'], $keys);
        $this->assertContains('held:GL_04a:1', $keys);
        $this->assertContains('unposted:' . $this->fee['locked_los'], $keys);
        $this->assertContains('diff:' . $this->fee['other_gl'] . ':' . $this->fee['other_los'], $keys);
        $this->assertContains('overlap:' . $this->fee['overlap'], $keys);
        $this->assertSame(FeeReconciliationWorkbook::keysHash($keys), $fingerprint['keys_hash']);
        // the dropdown answers and the answer colour of version 2
        $dv = collect($fees->getDataValidationCollection())->filter(fn ($v, $range) => str_starts_with($range, 'P2'))->first();
        $this->assertNotNull($dv, 'the answer column has its dropdown');
        $this->assertStringContainsString('Wrong amount - see comment', $dv->getFormula1());
        $this->assertSame(FeeReconciliationWorkbook::ANSWER, $fees->getStyle('P2')->getFill()->getStartColor()->getRGB());

        $entry = DB::table('audit_logs')->where('action', FeeReconciliationWorkbook::AUDIT_ACTION)->first();
        $this->assertSame((int) $this->maker->id, (int) json_decode($entry->meta, true)['user']);
        $this->assertSame($fingerprint['keys_hash'], json_decode($entry->new_values, true)['keys_hash']);
    }

    public function test_the_download_route_needs_the_fee_intake_permission(): void
    {
        $this->actingAs($this->maker)->get(route('eir-fee-classification.workbook.download'))->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        // deciding Finance's answers is a governed act: eir.fees.import alone does not decide
        $this->actingAs($this->maker)->post(route('eir-fee-classification.answers.decide', 1), ['action' => 'approve', 'reason' => 'Trying without eir.govern'])->assertForbidden();
    }

    public function test_the_download_is_refused_without_the_fee_intake_permission(): void
    {
        $nobody = User::create(['name' => 'Viewer', 'email' => uniqid() . '@example.com', 'password' => bcrypt('x')]);
        $this->actingAs($nobody)->get(route('eir-fee-classification.workbook.download'))->assertForbidden();
        $this->actingAs($nobody)->post(route('eir-fee-classification.workbook.upload'))->assertForbidden();
    }

    public function test_an_upload_maps_each_answer_by_its_key_stores_the_file_and_changes_nothing(): void
    {
        $before = DB::table('contract_fees')->orderBy('id')->get()->toJson();
        $path = $this->answer($this->downloaded(), [
            ['2. Held GL postings', fn ($r) => $r['Batch'] === '2025-01-02 TRF 1', ['Your answer' => 'Fee of the loan the narration points to', 'Comment' => 'Arrangement fee']],
            ['4b. Fees never posted', fn ($r) => $r['Loan account'] === self::LOCKED, ['Was it charged?' => 'No - never charged']],
            ['8. Fee lines', fn ($r) => (int) $r['Line'] === $this->fee['open_los'], ['Your answer' => 'Correct']],
        ]);
        $r = $this->upload($path);
        $this->assertSame(FeeWorkbookReader::KEYED, $r['basis']);
        $this->assertSame(3, $r['answers']);
        $this->assertSame([], $r['refused']);
        $this->assertSame(['held:GL_04a:1', 'unposted:' . $this->fee['locked_los'], 'fee:' . $this->fee['open_los']], DB::table('fee_workbook_answers')->orderBy('id')->pluck('record_key')->all());
        $this->assertSame(['PROPOSED'], DB::table('fee_workbook_answers')->distinct()->pluck('status')->all());
        $this->assertSame($before, DB::table('contract_fees')->orderBy('id')->get()->toJson(), 'nothing changes on upload');
        $this->assertSame(2, DB::table('fee_gl_unassigned')->count());
        $this->assertSame(1, \App\Support\SupportingDocuments::count('fee_workbook_uploads', $r['upload_id']), 'the workbook is the supporting document');
        $this->assertSame(1, collect(app(\App\Services\ApprovalsRegisterService::class)->rows(['workflow' => 'fee-workbook']))->where('pending', true)->count(), 'on the register of approvals');
    }

    public function test_a_workbook_whose_keys_do_not_match_its_fingerprint_is_refused(): void
    {
        $path = $this->downloaded();
        $book = IOFactory::load($path);
        $ws = $book->getSheetByName('8. Fee lines');
        $ws->removeRow(2); // a keyed row deleted
        $ws->getCell('P2')->setValue('Correct');
        $tampered = tempnam(sys_get_temp_dir(), 'fw') . '.xlsx';
        (new Xlsx($book))->save($tampered);
        try {
            $this->upload($tampered);
            $this->fail('a workbook with a row deleted was read');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('do not match its fingerprint', $e->getMessage());
        }
        $this->assertSame(0, DB::table('fee_workbook_answers')->count());
    }

    /** The offline workbook of 16 October 2026 has no keys: mapped by its visible columns; an ambiguous row is refused and named. */
    public function test_a_keyless_workbook_is_mapped_by_its_columns_and_an_ambiguous_row_is_refused(): void
    {
        // a second difference with the same loan, fee type and sources as the first: the keyless row cannot tell them apart
        DB::table('fee_source_differences')->insert(['contract_id' => self::OTHER, 'fee_type' => 'legal', 'record_fee_id' => $this->fee['other_gl'], 'source_of_record' => 'GL posting',
            'amount_of_record' => 10000, 'other_fee_id' => $this->fee['overlap'], 'other_source' => 'LOS capture', 'other_amount' => 9000, 'difference' => -1000, 'created_at' => now(), 'updated_at' => now()]);
        $build = app(FeeReconciliationWorkbook::class)->build()['spreadsheet'];
        // strip what the offline workbook never had: the fingerprint and the keys
        $readme = $build->getSheetByName('Read me');
        foreach (range(1, $readme->getHighestRow()) as $r) {
            $readme->getCell('Z' . $r)->setValue(null);
        }
        foreach ($build->getWorksheetIterator() as $ws) {
            foreach ($ws->getRowIterator(1, 1)->current()->getCellIterator() as $cell) {
                if ($cell->getValue() === FeeReconciliationWorkbook::KEY_HEADER) {
                    $ws->removeColumn($cell->getColumn());
                    break;
                }
            }
        }
        $plain = tempnam(sys_get_temp_dir(), 'fw') . '.xlsx';
        (new Xlsx($build))->save($plain);
        $path = $this->answer($plain, [
            ['2. Held GL postings', fn ($r) => $r['Batch'] === '2025-01-02 TRF 2', ['Your answer' => 'Not a loan fee', 'Comment' => 'A guarantee fee']],
            ['3. Overlaps', fn ($r) => $r['Loan account'] === self::OTHER, ['Your answer' => 'A separate fee of its own']],
            ['4. Source differences', fn ($r) => $r['Loan account'] === self::OTHER, ['Your answer' => 'Two separate fees']],
            ['4b. Fees never posted', fn ($r) => $r['Loan account'] === self::OPEN, ['Was it charged?' => 'Yes - deducted from the drawdown']],
            ['5. Lines to classify', fn ($r) => $r['Loan account'] === self::OPEN, ["Part of the loan's yield?" => 'No - a charge for a separate service']],
            ['8. Fee lines', fn ($r) => (int) $r['Line'] === $this->fee['other_gl'], ['Your answer' => 'Not integral']],
            ['1. Loan status', fn ($r) => $r['Loan account'] === self::LOCKED, ['Your answer' => 'Agree']],
        ]);
        $r = $this->upload($path);
        $this->assertSame(FeeWorkbookReader::NATURAL, $r['basis']);
        $this->assertCount(1, $r['refused']);
        $this->assertSame('4. Source differences', $r['refused'][0]['sheet']);
        $this->assertStringContainsString('matches 2 records', $r['refused'][0]['reason']);
        $this->assertStringContainsString('Loan account ' . self::OTHER, $r['refused'][0]['reason']);
        $this->assertEqualsCanonicalizing(['loan:' . self::LOCKED, 'held:GL_04a:2', 'overlap:' . $this->fee['overlap'], 'unposted:' . $this->fee['open_los'], 'classify:' . $this->fee['open_other'], 'fee:' . $this->fee['other_gl']],
            DB::table('fee_workbook_answers')->pluck('record_key')->all());
    }

    public function test_the_person_who_uploads_never_decides_and_a_second_person_does(): void
    {
        $r = $this->upload($this->answer($this->downloaded(), [['8. Fee lines', fn ($row) => (int) $row['Line'] === $this->fee['open_los'], ['Your answer' => 'Correct']]]));
        $id = $this->answerId('8. Fee lines');
        $service = app(FeeWorkbookAnswerService::class);
        foreach (['approve', 'reject', 'not_actionable'] as $action) {
            try {
                $service->decide($id, $action, 'Deciding my own entry here', $this->maker->id);
                $this->fail("the uploader could {$action} their own answer");
            } catch (LogicException $e) {
                $this->assertStringContainsString('cannot decide them', $e->getMessage());
            }
        }
        try {
            $service->approveSheet($r['upload_id'], '8. Fee lines', 'Approving my own sheet', $this->maker->id);
            $this->fail('the uploader approved their own sheet');
        } catch (LogicException $e) {
            $this->assertStringContainsString('cannot decide them', $e->getMessage());
        }
        $this->actingAs($this->checker)->post(route('eir-fee-classification.answers.decide', $id), ['action' => 'approve', 'reason' => 'Agrees with the take-on workbook'])->assertRedirect();
        $this->assertSame('APPROVED', DB::table('fee_workbook_answers')->where('id', $id)->value('status'));
        $this->assertSame((int) $this->checker->id, (int) DB::table('fee_workbook_answers')->where('id', $id)->value('decided_by'));
        $this->assertSame('DECIDED', DB::table('fee_workbook_uploads')->where('id', $r['upload_id'])->value('status'));
        $this->assertTrue(DB::table('audit_logs')->where('action', 'Fee Workbook Answer Approved')->where('entity_id', $id)->exists());
    }

    /** Each sheet's answer is applied through the service that owns the decision; a locked EIR is flagged, never changed. */
    public function test_each_sheet_is_applied_through_its_service(): void
    {
        $lockedBefore = DB::table('contract_eir')->where('contract_id', self::LOCKED)->first();
        $this->upload($this->answer($this->downloaded(), [
            ['2. Held GL postings', fn ($r) => $r['Batch'] === '2025-01-02 TRF 1', ['Your answer' => 'Fee of the loan the narration points to']],
            ['2. Held GL postings', fn ($r) => $r['Batch'] === '2025-01-02 TRF 2', ['Your answer' => 'Not a loan fee', 'Comment' => 'A guarantee fee']],
            ['3. Overlaps', fn ($r) => $r['Loan account'] === self::OTHER, ['Your answer' => 'A separate fee of its own']],
            ['5. Lines to classify', fn ($r) => $r['Loan account'] === self::OPEN, ["Part of the loan's yield?" => 'Yes - integral to the loan', 'What is this charge?' => 'Processing fee']],
            ['4b. Fees never posted', fn ($r) => $r['Loan account'] === self::LOCKED, ['Was it charged?' => 'No - never charged', 'Comment' => 'Waived']],
            ['8. Fee lines', fn ($r) => (int) $r['Line'] === $this->fee['locked_los'], ['Your answer' => 'Wrong amount - see comment', 'Comment' => 'Should be 32,000']],
        ]));
        $service = app(FeeWorkbookAnswerService::class);
        foreach (DB::table('fee_workbook_answers')->orderBy('id')->pluck('id') as $id) {
            $service->decide((int) $id, 'approve', 'Checked against the GL and the offer letter', $this->checker->id);
        }

        // 2: tied through assignHeld (the posting leaves the held list, a GL fee line joins the loan) and the candidate dismissed
        $this->assertNull(DB::table('fee_gl_unassigned')->where('id', $this->held['tie'])->first());
        $this->assertTrue(DB::table('contract_fees')->where('contract_id', self::OPEN)->where('external_transaction_id', 'GL_04a:1')->exists());
        $this->assertTrue(DB::table('audit_logs')->where('action', 'GL Fee Tied To Loan')->exists());
        $this->assertTrue(DB::table('fee_gl_candidate_dismissals')->where('external_transaction_id', 'GL_04a:2')->where('contract_id', self::OTHER)->exists());
        // 3: decided through FeeSourcePrecedence::decide
        $this->assertSame('SEPARATE', DB::table('fee_source_decisions')->where('contract_fee_id', $this->fee['overlap'])->value('decision'));
        // 5: classified by the person who entered the answer, reviewed by the approver
        $line = DB::table('contract_fees')->where('id', $this->fee['open_other'])->first();
        $this->assertSame(['REVIEWED', 1, (int) $this->maker->id, (int) $this->checker->id], [$line->classification_status, (int) $line->integral, (int) $line->classified_by, (int) $line->reviewed_by]);
        // 4b: recorded per fee line for the setting
        $this->assertSame(0, (int) DB::table('fee_unposted_confirmations')->where('contract_fee_id', $this->fee['locked_los'])->value('charged'));
        // 8 on a locked EIR, and 4b "never charged" on a fee it used: a follow-up and a lock-review flag, no figure changed
        $wrong = DB::table('fee_workbook_answers')->where('sheet', '8. Fee lines')->first();
        $this->assertStringContainsString('Should be 32,000', $wrong->follow_up);
        $this->assertNotNull($wrong->lock_review_id);
        $this->assertSame(2, DB::table('eir_lock_reviews')->where('contract_id', self::LOCKED)->where('status', 'OPEN')->count());
        $this->assertSame(30000.0, (float) DB::table('contract_fees')->where('id', $this->fee['locked_los'])->value('amount'));
        $after = DB::table('contract_eir')->where('contract_id', self::LOCKED)->first();
        $this->assertSame([(string) $lockedBefore->locked_at, (float) $lockedBefore->eir_effective_annual], [(string) $after->locked_at, (float) $after->eir_effective_annual], 'the locked EIR is untouched');
    }

    public function test_the_unposted_fee_basis_at_its_default_changes_nothing(): void
    {
        $this->assertSame(UnpostedFeeBasis::AS_BUILT, app(GovernanceService::class)->get(UnpostedFeeBasis::KEY));
        $this->assertSame([], UnpostedFeeBasis::leftOut());
        $this->assertSame(20000.0, (new EirReadinessService())->assess(self::OPEN)['metrics']['integral_fees_received'], 'the LOS charge never posted counts, as built');
        $this->assertContains($this->fee['open_los'], EirReadinessService::feeQuery()->pluck('id')->map(fn ($i) => (int) $i)->all());
    }

    public function test_the_second_option_leaves_out_unconfirmed_lines_and_flags_the_locked_eirs_it_changes(): void
    {
        // what it would do, shown before any approval: the locked EIR whose LOS charge would leave its inputs
        $preview = UnpostedFeeBasis::preview();
        $this->assertSame([self::LOCKED], array_map('strval', array_column($preview['locked'], 'contract_id')));
        $this->assertSame(30000.0, $preview['locked'][0]['amount']);
        $this->assertSame(0, DB::table('eir_lock_reviews')->count(), 'the preview raises nothing');

        $governance = app(GovernanceService::class);
        $change = $governance->propose(UnpostedFeeBasis::KEY, UnpostedFeeBasis::CONFIRMED_ONLY, now()->toDateString(), "Finance's answer to pack 13, question F2", $this->maker->id);
        try {
            $governance->approve($change->id, $this->checker->id);
            $this->fail('approved without a supporting document');
        } catch (LogicException $e) {
            $this->assertStringContainsString('needs a supporting document', $e->getMessage());
        }
        (new GovernanceDocumentService())->attach($change, UploadedFile::fake()->createWithContent('Finance F2 answer.pdf', '%PDF F2'), 'correspondence', null, $this->maker->id);
        try {
            $governance->approve($change->id, $this->maker->id);
            $this->fail('the proposer approved the change');
        } catch (LogicException $e) {
            $this->assertStringContainsString('cannot approve it', $e->getMessage());
        }
        $governance->approve($change->id, $this->checker->id);

        // the locked EIR is flagged, naming the line, and keeps its figures
        $flag = DB::table('eir_lock_reviews')->where('contract_id', self::LOCKED)->first();
        $this->assertNotNull($flag);
        $this->assertStringContainsString('line(s) ' . $this->fee['locked_los'], $flag->reason);
        $this->assertSame('LOCKED', DB::table('contract_eir')->where('contract_id', self::LOCKED)->value('calculation_status'));
        $this->assertSame(1, DB::table('eir_lock_reviews')->count(), 'only the locked EIR whose inputs change');
        // the open loan's unconfirmed charge leaves its inputs (and no longer blocks it)
        $this->assertSame(0.0, (new EirReadinessService())->assess(self::OPEN)['metrics']['integral_fees_received']);
        $this->assertNotContains($this->fee['open_other'], EirReadinessService::feeQuery()->pluck('id')->map(fn ($i) => (int) $i)->all());

        // Finance confirms the open loan's charge (an approved 4b answer): it counts again
        $this->upload($this->answer($this->downloaded(), [['4b. Fees never posted', fn ($r) => $r['Loan account'] === self::OPEN && (float) $r['Amount'] === 20000.0, ['Was it charged?' => 'Yes - deducted from the drawdown', 'Where it was posted or received (GL, date)' => 'Netted from the drawdown, 2025-01-01']]]));
        app(FeeWorkbookAnswerService::class)->decide($this->answerId('4b. Fees never posted'), 'approve', 'Agrees with the drawdown voucher', $this->checker->id);
        $this->assertSame(20000.0, (new EirReadinessService())->assess(self::OPEN)['metrics']['integral_fees_received']);
        $this->assertSame(GovernanceSetting::STATUS_APPROVED, GovernanceSetting::find($change->id)->status);
    }
}
