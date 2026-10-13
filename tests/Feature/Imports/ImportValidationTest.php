<?php

namespace Tests\Feature\Imports;

use App\Support\Imports\ImportSpecValidator;
use App\Support\Imports\ImportTemplateBuilder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Imports\Concerns\CreatesImportSpecSchema;
use Tests\TestCase;

/**
 * A file that breaks its import spec is refused before anything is written,
 * with the problems listed row by row (13 October 2026): a missing required
 * column, a value of the wrong type, a value outside the allowed list, a
 * repeated key. The sample file of each spec passes its own check.
 */
class ImportValidationTest extends TestCase
{
    use CreatesImportSpecSchema;

    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createImportSpecSchema();
        $this->withoutMiddleware();
    }

    protected function tearDown(): void
    {
        $this->dropImportSpecStorage();
        parent::tearDown();
    }

    private const COLLATERAL_HEAD = ['customer_id', 'customer_name', 'collateral_type', 'registration_date', 'market_value', 'execution_value'];

    public function test_a_collateral_file_missing_a_required_column_is_refused_and_nothing_is_written(): void
    {
        $file = $this->csvUpload([['customer_id', 'customer_name', 'market_value'], ['80000001', 'Sample', '1000']], 'no-type.csv');

        $response = $this->from('/collateral/register')->post(route('collateral.register.import.store'), ['file' => $file, 'period' => '2025-12', 'import_type' => 'legacy']);

        $response->assertRedirect('/collateral/register');
        $check = session('import_check');
        $this->assertFalse($check['ok']);
        $this->assertSame(['collateral_type'], $check['missing_columns']);
        $this->assertSame(1, $check['issues'][0]['row']);
        $this->assertSame(0, DB::table('imports')->count(), 'no import was started');
        $this->assertSame(0, DB::table('collateral_registers')->count(), 'nothing was written');
    }

    public function test_a_collateral_file_with_bad_values_lists_each_problem_by_row(): void
    {
        $file = $this->csvUpload([
            self::COLLATERAL_HEAD,
            ['80000001', 'Sample', 'PROPERTY', '2024-07-31', '600,000,000', '420000000'],
            ['80000002', 'Sample', 'BOAT', '2024-07-31', '1000', '800'],            // not a collateral type
            ['80000003', 'Sample', 'VEHICLE', '31-31-2024', 'about 5m', '(800)'],   // bad date, not a number, bracket negative
            ['80000001', 'Sample', 'PROPERTY', '2024-07-31', '1', '1'],             // repeats row 2
        ], 'bad.csv');

        $this->post(route('collateral.register.import.store'), ['file' => $file, 'period' => '2025-12', 'import_type' => 'legacy'])->assertRedirect();

        $issues = collect(session('import_check')['issues']);
        $this->assertTrue($issues->contains(fn ($i) => $i['row'] === 3 && $i['column'] === 'collateral_type'));
        $this->assertTrue($issues->contains(fn ($i) => $i['row'] === 4 && $i['column'] === 'registration_date'));
        $this->assertTrue($issues->contains(fn ($i) => $i['row'] === 4 && $i['column'] === 'market_value'));
        $this->assertTrue($issues->contains(fn ($i) => $i['row'] === 4 && $i['column'] === 'execution_value'));
        $this->assertTrue($issues->contains(fn ($i) => $i['row'] === 5 && str_contains($i['message'], 'Repeats row 2')));
        $this->assertTrue($issues->every(fn ($i) => $i['fix'] !== null && $i['fix'] !== ''), 'every problem says how to fix it');
        $this->assertSame(0, DB::table('imports')->count());
        $this->assertSame(0, DB::table('collateral_registers')->count());
    }

    public function test_a_loan_book_without_its_required_dates_is_refused(): void
    {
        $file = $this->csvUpload([['customer_id', 'contract_id', 'name', 'principal'], ['80000001', '800000000001', 'Sample', '1000']], 'tape.csv');

        $this->post(route('loan_applications.loan-book.import.store'), ['file' => $file, 'loan_portfolio_id' => 1, 'import_type' => 'legacy', 'reporting_period' => '2025-12'])->assertRedirect();

        $check = session('import_check');
        $this->assertSame('loan-book', $check['key']);
        $this->assertEqualsCanonicalizing(['value_date', 'maturity_date'], $check['missing_columns']);
        $this->assertSame(0, DB::table('imports')->count());
        $this->assertSame(0, DB::table('loan_books')->count());
    }

    public function test_a_mapped_loan_book_is_checked_through_its_mapping(): void
    {
        $file = $this->csvUpload([
            ['Client No', 'Account', 'Opened', 'Matures', 'Balance'],
            ['80000001', '800000000001', '2024-07-31', 'never', '1000'],
        ], 'mapped.csv');
        $mapping = ['Client No' => 'customer_id', 'Account' => 'contract_id', 'Opened' => 'create_date', 'Matures' => 'due_date', 'Balance' => 'principal_balance'];

        $json = $this->post(route('import-templates.check', 'loan-book'), ['file' => $file, 'mapping' => json_encode($mapping)])->assertOk()->json();

        $this->assertFalse($json['ok']);
        $this->assertSame([], $json['missing_columns'], 'create_date and due_date are read as value_date and maturity_date');
        $this->assertSame('maturity_date', $json['issues'][0]['column']);
        $this->assertSame(2, $json['issues'][0]['row']);
    }

    public function test_credit_loss_data_out_of_range_is_refused(): void
    {
        $file = $this->csvUpload([['period', 'pd_value', 'lgd_value'], ['2025-12-31', '1.5', '0.4'], ['2025-11-30', '0.04', 'n/a']], 'cld.csv');

        $this->post(route('credit-loss-data.import'), ['file' => $file])->assertRedirect();

        $issues = collect(session('import_check')['issues']);
        $this->assertTrue($issues->contains(fn ($i) => $i['row'] === 2 && $i['column'] === 'pd_value' && str_contains($i['message'], 'out of range')));
        $this->assertTrue($issues->contains(fn ($i) => $i['row'] === 3 && $i['column'] === 'lgd_value'));
        $this->assertSame(0, DB::table('imports')->count());
        $this->assertSame(0, DB::table('macro_credit_loss_data')->count());
    }

    public function test_a_client_file_with_a_repeated_customer_is_refused(): void
    {
        $file = $this->csvUpload([['customer_id', 'name'], ['80000001', 'A'], ['1.0445E+11', 'B'], ['80000001', 'C']], 'clients.csv');

        $this->post(route('clients.import.store'), ['names_file' => $file, 'import_type' => 'legacy'])->assertRedirect();

        $issues = collect(session('import_check')['issues']);
        $this->assertTrue($issues->contains(fn ($i) => $i['row'] === 3 && str_contains($i['message'], 'scientific notation')));
        $this->assertTrue($issues->contains(fn ($i) => $i['row'] === 4 && str_contains($i['message'], 'Repeats row 2')));
        $this->assertSame(0, DB::table('imports')->count());
        $this->assertSame(0, DB::table('clients')->count());
    }

    public function test_an_eir_fee_file_with_a_bad_amount_is_refused_before_it_is_queued(): void
    {
        $file = $this->csvUpload([
            ['contract_id', 'fee_type', 'amount', 'cashflow_direction'],
            ['800000000001', 'arrangement', '12500000', 'RECEIVED'],
            ['800000000001', 'legal', 'twelve', 'RECEIVED'],
            ['800000000002', 'legal', '100', 'SIDEWAYS'],
        ], 'fees.csv');

        $response = $this->postJson(route('eir-intake.import'), [
            'file' => $file, 'import_type' => 'fees',
            'mapping' => json_encode(['contract_id' => 'contract_id', 'fee_type' => 'fee_type', 'amount' => 'amount', 'cashflow_direction' => 'cashflow_direction']),
        ]);

        $response->assertStatus(422);
        $issues = collect($response->json('check.issues'));
        $this->assertTrue($issues->contains(fn ($i) => $i['row'] === 3 && $i['column'] === 'amount'));
        $this->assertTrue($issues->contains(fn ($i) => $i['row'] === 4 && $i['column'] === 'cashflow_direction'));
        $this->assertStringContainsString('was not imported', $response->json('error'));
        $this->assertSame(0, DB::table('imports')->count(), 'nothing was queued');
    }

    public function test_the_check_endpoint_writes_nothing_and_a_clean_file_passes(): void
    {
        $file = $this->csvUpload([self::COLLATERAL_HEAD, ['80000001', 'Sample', 'PROPERTY', '31/07/2024', '600,000,000.00', '420000000']], 'good.csv');

        $json = $this->post(route('import-templates.check', 'collateral'), ['file' => $file])->assertOk()->json();

        $this->assertTrue($json['ok']);
        $this->assertSame(1, $json['rows']);
        $this->assertSame(0, DB::table('collateral_registers')->count());
    }

    public function test_a_trial_balance_that_does_not_balance_is_refused_by_the_check(): void
    {
        [$body] = (new ImportTemplateBuilder())->template('trial-balance');
        $good = $this->storage . '/tb-good.xlsx';
        file_put_contents($good, $body);
        $this->assertTrue((new ImportSpecValidator())->checkFile('trial-balance', $good)['ok'], 'the layout sample ties');

        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($good);
        $book->getSheet(0)->setCellValue('C3', '999.00');
        $book->getSheet(0)->setCellValue('D4', 'abc');
        $bad = $this->storage . '/tb-bad.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($bad);

        $json = $this->post(route('import-templates.check', 'trial-balance'), ['file' => new UploadedFile($bad, 'tb-bad.xlsx', null, null, true)])->assertOk()->json();

        $this->assertFalse($json['ok']);
        $issues = collect($json['issues']);
        $this->assertTrue($issues->contains(fn ($i) => $i['row'] === 4 && $i['column'] === 'Credit'));
        $this->assertTrue($issues->contains(fn ($i) => $i['column'] === 'Grand Total :'));
    }

    public function test_an_ebanker_report_row_is_read_even_when_a_name_holds_id(): void
    {
        [$body] = (new ImportTemplateBuilder())->template('loan-book-ebanker');
        $path = $this->storage . '/report.csv';
        file_put_contents($path, str_replace('SAMPLE ONLY - not a loan', 'David Phiri', $body));

        $check = (new ImportSpecValidator())->checkFile('loan-book-ebanker', $path);

        $this->assertTrue($check['ok']);
        $this->assertSame(1, $check['rows'], 'the loan of a borrower named David is a loan, not a header row');
    }

    public function test_a_sicr_item_file_with_a_bad_stage_or_flag_is_refused(): void
    {
        $file = $this->csvUpload([
            ['group', 'name', 'active', 'stage'],
            ['Financial difficulty', 'Covenant breach', 'yes', '2'],
            ['Financial difficulty', 'Restructure', 'maybe', '4'],
            ['', 'No group', 'yes', ''],
        ], 'items.csv');

        $this->post(route('sicr-items.import'), ['file' => $file])->assertRedirect();

        $issues = collect(session('import_check')['issues']);
        $this->assertTrue($issues->contains(fn ($i) => $i['row'] === 3 && $i['column'] === 'active'));
        $this->assertTrue($issues->contains(fn ($i) => $i['row'] === 3 && $i['column'] === 'stage'));
        $this->assertTrue($issues->contains(fn ($i) => $i['row'] === 4 && $i['column'] === 'group'));
        $this->assertSame(0, DB::table('sicr_groups')->count(), 'no group was created');
    }

    public function test_a_macro_csv_with_an_unknown_variable_is_refused_before_the_preview(): void
    {
        $file = $this->csvUpload([['series_code', 'period', 'value'], ['GDP_GROWTH', '2025-12', '4.2'], ['NOT_A_VARIABLE', '2025-12', '1'], ['GDP_GROWTH', 'Dec 25', 'x']], 'macro.csv');

        $response = $this->postJson(route('macro-statistics.csv-preview'), ['file' => $file]);

        $response->assertStatus(422);
        $issues = collect($response->json('check.issues'));
        $this->assertTrue($issues->contains(fn ($i) => $i['row'] === 3 && $i['column'] === 'series_code'));
        $this->assertTrue($issues->contains(fn ($i) => $i['row'] === 4 && $i['column'] === 'period'));
        $this->assertTrue($issues->contains(fn ($i) => $i['row'] === 4 && $i['column'] === 'value'));
    }

    public function test_a_policy_rate_file_with_a_bad_date_is_refused(): void
    {
        $file = $this->csvUpload([['date', 'rate'], ['2025-11-30', '26'], ['30-11-2025', '26%'], ['2025-12-31', 'n/a']], 'rbm.csv');

        $response = $this->postJson(route('macro-statistics.preview'), ['source' => 'rbm_file', 'file' => $file]);

        $response->assertStatus(422);
        $issues = collect($response->json('check.issues'));
        $this->assertTrue($issues->contains(fn ($i) => $i['row'] === 3 && $i['column'] === 'date'));
        $this->assertTrue($issues->contains(fn ($i) => $i['row'] === 4 && $i['column'] === 'rate'));
    }

    public function test_every_row_sample_passes_its_own_check(): void
    {
        $builder = new ImportTemplateBuilder();
        foreach (['clients', 'loan-book', 'loan-book-ebanker', 'collateral', 'credit-loss-data', 'sicr-groups', 'sicr-items', 'macro-series', 'rbm-policy-rate', 'imf-weo'] as $key) {
            [$body, $name] = $builder->template($key);
            $path = $this->storage . '/' . $name;
            file_put_contents($path, $body);
            $check = (new ImportSpecValidator())->checkFile($key, $path, $name);
            $this->assertTrue($check['ok'], "{$key}: " . json_encode(array_slice($check['issues'], 0, 3)));
        }
    }
}
