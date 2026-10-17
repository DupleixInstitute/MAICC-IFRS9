<?php

namespace Tests\Feature\Eir;

use App\Services\Ebanker\ClientBookReconciliationService;
use App\Services\Eir\LoanGrossAmountOverrideService;
use App\Services\Eir\LoanStagingBasisOverrideService;
use Database\Seeders\GovernanceSettingsSeeder;
use Database\Seeders\StagingThresholdSeeder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Client Book Reconciliation Specification v1: the client's own loan book of a month-end against
 * the book the system rebuilt, every difference with its cause, and the governed overrides that
 * take the client's figure with the client's book as their evidence.
 *
 * The system's book of 2026-08 (GL 1050101):
 *   104420000001 Alpha    1,000,000.00   current
 *   104420000002 Bravo      500,000.00   271+ bucket (over 180 days)
 *   104420000003 Charlie  2,500,000.00   current
 *   104420000004 Delta      300,000.00   current      (not in the client's book)
 * The client's E-Banker report: Alpha the same; Bravo in the 1-30 bucket; Charlie 2,000,000.00;
 * Echo 50,000.00 (not in the system's book); Foxtrot closed at nil (in neither book: the same);
 * Golf 100,000.00 all overdue and in no bucket (over 270 days, as the system has it: the same).
 */
class ClientBookReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        (new GovernanceSettingsSeeder())->run();
        (new StagingThresholdSeeder())->run();
        DB::table('users')->insert([
            ['id' => 501, 'name' => 'Maker', 'email' => 'maker@x.test', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 502, 'name' => 'Checker', 'email' => 'checker@x.test', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $base = ['reporting_period' => '2026-08', 'reporting_year' => 2026, 'reporting_month' => 8, 'customer_id' => '1', 'external_identity_id' => 'TBA', 'product_code' => '1050101', 'tenor' => 36];
        foreach ([
            ['contract_id' => '104420000001', 'customer_name' => 'Alpha', 'carrying_amount' => 1000000],
            ['contract_id' => '104420000002', 'customer_name' => 'Bravo', 'carrying_amount' => 500000, 'arrears_271_to_360' => 400000],
            ['contract_id' => '104420000003', 'customer_name' => 'Charlie', 'carrying_amount' => 2500000],
            ['contract_id' => '104420000004', 'customer_name' => 'Delta', 'carrying_amount' => 300000],
            ['contract_id' => '104420000005', 'customer_name' => 'Golf', 'carrying_amount' => 100000, 'arrears_271_to_360' => 100000],
        ] as $row) {
            DB::table('loan_books')->insert($row + $base);
        }
        $this->file = storage_path('app/test-client-book-2026-08.csv');
        file_put_contents($this->file, $this->report(3650000.00));
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    /** The E-Banker loan book report's layout: a header row, a GL heading, the loans, the GL's printed total. */
    private function report(float $glTotal): string
    {
        $head = ['Sr. No.', 'Customer ID', 'National ID', 'A/C Number', 'Name', 'Value Date', 'Maturity Date', 'Tenure (Years)', 'Moratorium (Months)', 'Interest Rate', 'Approved', 'Disbursed', 'Not Yet Disbursed', 'Principal', 'Collateral Type', 'Charge Amount', 'Collateral Value', 'Interest To Date', 'Repayments', 'Carrying Amount', 'Principal Overdue', 'Interest Overdue', 'Total Overdue', 'Arrears Category', '1 - 30 Days', '31 - 90 Days', '91 - 180 Days', '181 - 270 Days', 'Outstanding Balance'];
        $row = fn ($n, $acc, $name, $ca, $b1 = 0, $overdue = 0) => [$n, '1', '', '000' . $acc, $name, '', '', '', '', '', '', '', '', '', '', '', '', '', '', number_format($ca, 2), '0.00', '0.00', number_format($overdue, 2), '', number_format($b1, 2), '0.00', '0.00', '0.00', number_format($ca, 2)];
        $lines = [$head, ['Loan Type : 1050101-MAIIC Agricultural Loans'],
            $row(1, '104420000001', 'Alpha', 1000000), $row(2, '104420000002', 'Bravo', 500000, 1500), $row(3, '104420000003', 'Charlie', 2000000),
            $row(4, '104420000009', 'Echo', 50000), $row(5, '104420000010', 'Foxtrot', 0),
            // long past maturity: the whole balance overdue and in none of the 1-270 day buckets, so over 270 days, never current
            $row(6, '104420000005', 'Golf', 100000, 0, 100000),
            array_merge(['GL Wise Total : '], array_fill(1, 18, ''), [number_format($glTotal, 2)]), array_merge(['Printed Grand Total : '], array_fill(1, 18, ''), ['50,000.00'])];

        return implode("\n", array_map(fn ($l) => implode(',', array_map(fn ($c) => '"' . $c . '"', $l)), $lines)) . "\n";
    }

    private function service(): ClientBookReconciliationService
    {
        return app(ClientBookReconciliationService::class);
    }

    public function test_every_difference_is_found_with_its_cause_and_written_to_a_csv(): void
    {
        $landed = $this->service()->land($this->file, '2026-08', 'ebanker_report', 'E-Banker loan book report, 31 August 2026', 501);
        $this->assertSame(['LANDED', 6], [$landed['status'], $landed['rows']]);
        $golf = DB::table('client_loan_book_rows')->where('contract_id', '104420000005')->first();
        $this->assertSame(271, (int) $golf->days_past_due);
        $this->assertStringContainsString('older than the 270-day buckets', $golf->days_basis);
        $c = $this->service()->compare($landed['book_id']);

        $d = DB::table('client_book_differences')->where('client_book_id', $landed['book_id'])->get()->keyBy('contract_id');
        $this->assertSame(['104420000002', '104420000003', '104420000004', '104420000009'], $d->keys()->map(fn ($k) => (string) $k)->sort()->values()->all(), 'Alpha and Golf agree and Foxtrot is closed in both');
        $this->assertSame(['different arrears bracket', '1-30 Days', '>181 Days', 1], [$d['104420000002']->cause, $d['104420000002']->client_bracket, $d['104420000002']->system_bracket, (int) $d['104420000002']->client_days]);
        $this->assertSame(['different balance', 2000000.0, 2500000.0], [$d['104420000003']->cause, (float) $d['104420000003']->client_amount, (float) $d['104420000003']->system_amount]);
        $this->assertSame("in the system's book only", $d['104420000004']->cause);
        $this->assertSame("in the client's book only", $d['104420000009']->cause);
        $this->assertSame([4, 2, 4], [$c['compared'], $c['same'], $c['differences']]);
        $csv = array_map('str_getcsv', file(storage_path('app/' . $c['csv']), FILE_IGNORE_NEW_LINES));
        $this->assertCount(5, $csv, 'the header and the four differences');
        $this->assertSame(-500000.0, (float) collect($csv)->first(fn ($r) => $r[1] === '104420000003')[6]);
        // the file is kept as received, as the evidence
        $this->assertSame(1, DB::table('supporting_documents')->where('documentable_type', 'client_loan_books')->where('documentable_id', $landed['book_id'])->count());
        // nothing booked is changed
        $this->assertSame(2500000.0, (float) DB::table('loan_books')->where('contract_id', '104420000003')->value('carrying_amount'));
        // the same file twice is one book
        $this->assertSame('ALREADY_LANDED', $this->service()->land($this->file, '2026-08', 'ebanker_report', 'Again', 501)['status']);
        @unlink(storage_path('app/' . $c['csv']));
    }

    public function test_a_report_whose_rows_do_not_add_up_to_its_gl_total_is_refused(): void
    {
        file_put_contents($this->file, $this->report(3649000.00));
        $this->expectExceptionMessage('add up to 3,650,000.00 but the report prints a GL total of 3,649,000.00');
        $this->service()->land($this->file, '2026-08', 'ebanker_report', 'E-Banker loan book report, 31 August 2026', 501);
    }

    public function test_the_clients_figures_become_overrides_with_its_book_as_evidence_and_a_second_person_approves(): void
    {
        $book = $this->service()->land($this->file, '2026-08', 'ebanker_report', 'E-Banker loan book report, 31 August 2026', 501)['book_id'];
        $this->service()->compare($book);
        $diff = fn ($c) => DB::table('client_book_differences')->where('client_book_id', $book)->where('contract_id', $c)->first();

        $bravo = $this->service()->propose((int) $diff('104420000002')->id, 501, 'The client confirms the arrears were cleared by 31 August 2026.');
        $charlie = $this->service()->propose((int) $diff('104420000003')->id, 501, 'The client confirms the balance after the August repayment.');
        $this->assertNotNull($bravo['staging_override_id']);
        $this->assertNull($bravo['gross_override_id']);
        $this->assertNotNull($charlie['gross_override_id']);
        $staging = DB::table('loan_staging_basis_overrides')->find($bravo['staging_override_id']);
        $this->assertSame(['client_book', 1, '2026-08', 'PROPOSED'], [$staging->source, (int) $staging->override_days, $staging->reporting_period, $staging->status]);
        $this->assertSame(2000000.0, (float) DB::table('loan_gross_amount_overrides')->where('id', $charlie['gross_override_id'])->value('override_amount'));
        $this->assertSame('OVERRIDE_PROPOSED', $diff('104420000002')->status);
        // the client's book is linked to each override as its evidence
        $this->assertSame(1, DB::table('supporting_document_links')->where('documentable_type', 'loan_staging_basis_overrides')->where('documentable_id', $bravo['staging_override_id'])->count());

        // the proposer does not approve it; a second person does, on the linked evidence
        try {
            app(LoanStagingBasisOverrideService::class)->approve($bravo['staging_override_id'], 501);
            $this->fail('the proposer approved their own override');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Maker-checker', $e->getMessage());
        }
        app(LoanStagingBasisOverrideService::class)->approve($bravo['staging_override_id'], 502);
        app(LoanGrossAmountOverrideService::class)->approve($charlie['gross_override_id'], 502);
        $this->assertArrayHasKey('104420000003', app(LoanGrossAmountOverrideService::class)->active('2026-08'));

        // compared again before the rebuild: still a difference, the decision kept
        $this->service()->compare($book);
        $this->assertSame('OVERRIDE_PROPOSED', $diff('104420000003')->status);
        // after the rebuild (the build applies the approved figure), Charlie agrees and is reconciled
        DB::table('loan_books')->where('contract_id', '104420000003')->update(['carrying_amount' => 2000000]);
        $this->service()->compare($book);
        $this->assertSame('RECONCILED', $diff('104420000003')->status);
        $this->assertStringContainsString('agrees after the approved gross amount override #' . $charlie['gross_override_id'], $diff('104420000003')->cause);
    }

    public function test_an_override_proposed_without_a_client_book_needs_a_document_before_approval(): void
    {
        $id = app(LoanGrossAmountOverrideService::class)->propose('104420000003', '2026-08', 'Audited figure from the AFS working papers.', 501, 2000000);
        $this->expectExceptionMessage('needs a supporting document before it is approved');
        app(LoanGrossAmountOverrideService::class)->approve($id, 502);
    }

    public function test_the_system_figure_is_kept_with_a_reason_and_can_be_opened_again(): void
    {
        $book = $this->service()->land($this->file, '2026-08', 'ebanker_report', 'E-Banker loan book report, 31 August 2026', 501)['book_id'];
        $this->service()->compare($book);
        $delta = DB::table('client_book_differences')->where('client_book_id', $book)->where('contract_id', '104420000004')->value('id');

        $this->service()->keep((int) $delta, 502, 'Delta was drawn on 31 August 2026, after the report was printed.');
        $this->assertSame('KEPT', DB::table('client_book_differences')->where('id', $delta)->value('status'));
        // a comparison run again keeps the decision
        $this->service()->compare($book);
        $this->assertSame('KEPT', DB::table('client_book_differences')->where('client_book_id', $book)->where('contract_id', '104420000004')->value('status'));
        $this->service()->reopen((int) $delta, 501, 'The client has sent a corrected report for the month.');
        $this->assertSame('OPEN', DB::table('client_book_differences')->where('id', $delta)->value('status'));
        // a difference with no loan of the system's book is answered by the client, not overridden
        $echo = DB::table('client_book_differences')->where('client_book_id', $book)->where('contract_id', '104420000009')->value('id');
        $this->expectExceptionMessage('matches no loan');
        DB::table('client_book_differences')->where('id', $echo)->update(['contract_id' => null]);
        $this->service()->propose((int) $echo, 501, 'Not a loan of the system book at all.');
    }

    public function test_a_locked_period_is_never_overridden(): void
    {
        $book = $this->service()->land($this->file, '2026-08', 'ebanker_report', 'E-Banker loan book report, 31 August 2026', 501)['book_id'];
        $this->service()->compare($book);
        DB::table('reporting_period_locks')->insert(['reporting_period' => '2026-08', 'locked_by' => 502, 'locked_at' => now(), 'reason' => 'Closed', 'created_at' => now(), 'updated_at' => now()]);
        $bravo = DB::table('client_book_differences')->where('client_book_id', $book)->where('contract_id', '104420000002')->value('id');
        $this->expectExceptionMessage('is locked');
        $this->service()->propose((int) $bravo, 501, 'The client confirms the arrears were cleared.');
    }

    private function people(): array
    {
        foreach (['eir.view', 'eir.govern', 'reports.ifrs9'] as $p) {
            \Spatie\Permission\Models\Permission::findOrCreate($p, 'web');
        }
        $maker = User::find(501);
        $checker = User::find(502);
        $maker->givePermissionTo(['eir.view', 'eir.govern']);
        $checker->givePermissionTo(['eir.view', 'eir.govern']);

        return [$maker, $checker];
    }

    /** The screen end to end: land by upload, see the differences, propose, approve, keep, and the files it serves. */
    public function test_the_screen_lands_compares_and_decides_through_the_service(): void
    {
        [$maker, $checker] = $this->people();
        $upload = new UploadedFile($this->file, 'Loan Book 31 Aug 2026.csv', 'text/csv', null, true);
        $this->actingAs($maker)->post(route('client-books.land'), ['period' => '2026-08', 'kind' => 'ebanker_report', 'label' => 'E-Banker loan book report, 31 August 2026', 'file' => $upload])
            ->assertRedirect()->assertSessionHas('success');
        $book = (int) DB::table('client_loan_books')->value('id');
        $this->actingAs($checker)->get(route('client-books.index', ['book' => $book, 'tab' => 'differences']))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Eir/ClientBooks/Index')->has('books', 1)->where('books.0.summary.same', 2)->has('differences', 4));
        $charlie = (int) DB::table('client_book_differences')->where('contract_id', '104420000003')->value('id');
        $delta = (int) DB::table('client_book_differences')->where('contract_id', '104420000004')->value('id');

        $this->actingAs($maker)->post(route('client-books.differences.propose', $charlie), ['reason' => 'The client confirms the balance after the August repayment.'])->assertSessionHas('success');
        $gross = (int) DB::table('loan_gross_amount_overrides')->value('id');
        $this->actingAs($maker)->post(route('client-books.overrides.decide', ['gross', $gross]), ['action' => 'approve'])->assertSessionHas('error');
        $this->actingAs($checker)->post(route('client-books.overrides.decide', ['gross', $gross]), ['action' => 'approve'])->assertSessionHas('success');
        $this->assertSame('APPROVED', DB::table('loan_gross_amount_overrides')->where('id', $gross)->value('status'));
        // the month waits for its rebuild
        $this->actingAs($checker)->get(route('client-books.index', ['tab' => 'overrides']))
            ->assertInertia(fn ($page) => $page->has('rebuilds', 1)->where('rebuilds.0.period', '2026-08')->where('overrides.0.documents', 1));

        $this->actingAs($checker)->post(route('client-books.differences.keep', $delta), ['action' => 'keep', 'reason' => 'Drawn on the last day, after the report was printed.'])->assertSessionHas('success');
        $this->assertSame('KEPT', DB::table('client_book_differences')->where('id', $delta)->value('status'));

        $this->actingAs($checker)->get(route('client-books.differences-csv', $book))->assertOk();
        $this->actingAs($checker)->get(route('client-books.file', $book))->assertOk();
        $template = $this->actingAs($checker)->get(route('client-books.template', ['period' => '2026-08']));
        $template->assertOk();
        $this->assertStringContainsString('104420000003', $template->streamedContent());

        // a reader without eir.govern sees the screen and decides nothing
        DB::table('users')->insert(['id' => 503, 'name' => 'Reader', 'email' => 'reader@x.test', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        $reader = User::find(503);
        $reader->givePermissionTo('eir.view');
        $this->flushSession(); // a new person signs in: the session held the checker's password hash
        $this->actingAs($reader)->get(route('client-books.index'))->assertOk()->assertInertia(fn ($page) => $page->where('canGovern', false));
        $this->actingAs($reader)->post(route('client-books.compare', $book))->assertForbidden();
        @unlink(storage_path('app/' . DB::table('client_loan_books')->value('csv_path')));
    }
}
