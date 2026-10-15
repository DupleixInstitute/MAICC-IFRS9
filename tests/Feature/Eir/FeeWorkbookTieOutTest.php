<?php

namespace Tests\Feature\Eir;

use App\Jobs\ProcessEirImportJob;
use App\Models\Import;
use App\Services\Eir\FeeWorkbookTieOut;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Finance's fee workbook is loaded only when it ties to the GL fee postings
 * (15 October 2026): every line accounted for by a posting, every posting of the
 * months it covers accounted for by a line, and each held posting a line
 * accounts for tied to that line's loan.
 */
class FeeWorkbookTieOutTest extends TestCase
{
    use RefreshDatabase;

    private const GL = 'E-Banker GL fee posting';

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('contract_fees')->delete();
        DB::table('fee_gl_unassigned')->delete();
        foreach ([['104430000201', 'ACME MILLING LIMITED'], ['104430000202', 'BETA FARMS LIMITED'], ['104430000203', 'GAMMA TRADERS']] as [$id, $name]) {
            DB::table('loan_books')->insert(['contract_id' => $id, 'customer_id' => $id, 'customer_name' => $name, 'reporting_period' => '2025-06', 'reporting_year' => 2025, 'reporting_month' => 6,
                'loan_portfolio_id' => 1, 'commitments' => 0, 'facility_utilisation_rate' => 1,
                'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function held(string $gl, float $amount, string $date, string $narration, ?string $candidate = null): int
    {
        return DB::table('fee_gl_unassigned')->insertGetId(['external_transaction_id' => 'GL_04a:' . random_int(1, 9_999_999), 'transaction_date' => $date, 'batch' => 'TRF 1',
            'gl_code' => $gl, 'amount' => $amount, 'narration' => $narration, 'reason' => 'NO_LOAN_IN_BATCH: no loan account is a leg of the batch', 'loans_in_batch' => '[]',
            'candidate_contract_id' => $candidate, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function tied(string $loan, string $gl, float $amount, string $date): void
    {
        DB::table('contract_fees')->insert(['contract_id' => $loan, 'fee_type' => $gl === '4871' ? 'legal' : 'arrangement', 'amount' => $amount, 'transaction_date' => $date,
            'cashflow_direction' => 'RECEIVED', 'source_system' => self::GL, 'external_transaction_id' => 'GL_04a:' . random_int(1, 9_999_999), 'gl_account_ref' => $gl,
            'description' => 'By Trf fees (GL ' . $gl . ', one loan in the batch)', 'classification_status' => 'PENDING', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function row(string $loan, string $type, float $amount, string $date): array
    {
        return ['contract_id' => $loan, 'fee_type' => $type, 'amount' => $amount, 'transaction_date' => $date, 'cashflow_direction' => 'RECEIVED'];
    }

    public function test_a_workbook_that_ties_passes_and_names_the_held_postings_to_tie(): void
    {
        $h = $this->held('4871', 1_000_000, '2025-06-10', 'By Trf Acme Milling legal fees');
        $this->tied('104430000202', '4873', 2_000_000, '2025-06-12');

        $r = app(FeeWorkbookTieOut::class)->check([
            $this->row('104430000201', 'legal', 1_000_000, '2025-06-01'),
            $this->row('104430000202', 'arrangement', 2_000_000, '2025-06-01'),
        ]);

        $this->assertSame('PASS', $r['status'], json_encode($r['refusals']));
        $this->assertSame([['held_id' => $h, 'contract_id' => '104430000201', 'row' => 2, 'posting' => 'H' . $h, 'amount' => 1_000_000.0]], $r['ties']);
    }

    public function test_a_line_no_posting_accounts_for_and_a_posting_left_out_each_refuse_the_file(): void
    {
        $this->held('4871', 1_000_000, '2025-06-10', 'By Trf Acme Milling legal fees');
        $this->held('4871', 750_000, '2025-06-20', 'By Trf Delta legal fees'); // in the month, in no line

        $r = app(FeeWorkbookTieOut::class)->check([
            $this->row('104430000201', 'legal', 1_000_000, '2025-06-01'),
            $this->row('104430000203', 'legal', 400_000, '2025-06-01'), // no posting
        ]);

        $this->assertSame('REFUSED', $r['status']);
        $reasons = implode(' | ', array_column($r['refusals'], 'reason'));
        $this->assertStringContainsString('No GL fee posting on 4871 accounts for this line', $reasons);
        $this->assertStringContainsString('GL 4871, 2025-06: 1 posting(s) netting 750,000.00 are not in the file', $reasons);
    }

    public function test_take_on_loans_lines_before_the_first_posting_and_undated_lines_are_refused(): void
    {
        $this->held('4871', 1_000_000, '2025-06-10', 'By Trf Acme Milling legal fees');
        DB::table('contract_fees')->insert(['contract_id' => '104430000203', 'fee_type' => 'legal', 'amount' => 5_000, 'source_system' => 'TAKEON_WORKBOOK',
            'classification_status' => 'PENDING', 'created_at' => now(), 'updated_at' => now()]);

        $r = app(FeeWorkbookTieOut::class)->check([
            $this->row('104430000203', 'legal', 5_000, '2025-06-01'),
            $this->row('104430000201', 'legal', 1_000_000, '2024-01-15'),
            ['contract_id' => '104430000202', 'fee_type' => 'legal', 'amount' => 9_000],
        ]);

        $reasons = array_column($r['refusals'], 'reason');
        $this->assertStringContainsString('take-on loan', $reasons[0]);
        $this->assertStringContainsString('before the first GL fee posting', $reasons[1]);
        $this->assertStringContainsString('No date', $reasons[2]);
    }

    public function test_one_posting_for_two_loans_a_fee_in_parts_and_a_reversal_across_months_are_recognised(): void
    {
        $shared = $this->held('4873', 3_000_000, '2025-06-02', 'By Trf arrangement fees');
        $this->tied('104430000203', '4871', 5_900_000, '2025-07-08');
        $this->tied('104430000203', '4871', 900, '2025-07-08');
        $this->held('4871', 250_000, '2025-07-09', 'By Trf legal fees');      // posted in July ...
        $this->held('4871', -250_000, '2025-09-30', 'To Trf reversal');       // ... and reversed in September

        $r = app(FeeWorkbookTieOut::class)->check([
            $this->row('104430000201', 'arrangement', 1_000_000, '2025-06-10'),
            $this->row('104430000202', 'arrangement', 2_000_000, '2025-06-10'),
            $this->row('104430000203', 'legal', 5_900_900, '2025-07-05'),
        ]);

        $this->assertSame('PASS', $r['status'], json_encode($r['refusals']));
        $this->assertSame([], $r['ties'], 'a posting that pays two loans is not tied to either');
        $this->assertStringContainsString('pays this fee and loan 104430000202', $r['lines'][0]['how'][0]);
        $this->assertCount(2, $r['lines'][2]['postings'], 'the fee posted in two parts');
        $this->assertSame(0.0, $r['lines'][2]['difference']);
    }

    public function test_the_import_job_refuses_a_file_that_does_not_tie_and_loads_and_ties_one_that_does(): void
    {
        Storage::fake();
        Storage::fake('public');
        $h = $this->held('4871', 1_000_000, '2025-06-10', 'By Trf Acme Milling legal fees');
        $map = ['contract_id' => 'contract_id', 'fee_type' => 'fee_type', 'amount' => 'amount', 'transaction_date' => 'transaction_date', 'cashflow_direction' => 'cashflow_direction'];
        $run = function (array $lines) use ($map): Import {
            $import = Import::create(['kind' => 'eir_intake', 'name' => 'EIR fees: test', 'status' => 'pending']);
            $csv = "contract_id,fee_type,amount,transaction_date,cashflow_direction\n" . implode("\n", $lines) . "\n";
            Storage::put("temp-imports/fees_{$import->id}.csv", $csv);
            (new ProcessEirImportJob($import->id, "temp-imports/fees_{$import->id}.csv", 'fees.csv', 'fees', $map, [], null))->handle(...array_map(fn ($c) => app($c), [
                \App\Services\Imports\MappedFileReader::class, \App\Services\Eir\ScheduleImportService::class, \App\Services\Eir\FeeImportService::class,
                \App\Services\Eir\ContractTransactionImportService::class, \App\Services\Eir\ContractMasterImportService::class, \App\Services\Eir\GlInterestImportService::class,
                \App\Services\Eir\ReferenceRateImportService::class, \App\Services\Eir\DisbursementImportService::class, \App\Services\Eir\FeeGlReconciliationService::class,
                FeeWorkbookTieOut::class, \App\Services\Ebanker\GlFeeBuildService::class, \App\Services\Eir\FeeSourcePrecedence::class,
            ]));

            return $import->fresh();
        };

        $refused = $run(['104430000201,legal,1200000,2025-06-01,RECEIVED']);
        $this->assertSame('failed', $refused->status);
        $this->assertSame(0, DB::table('contract_fees')->count(), 'nothing loads from a file that does not tie');
        $this->assertSame(1, DB::table('fee_gl_unassigned')->count());

        $loaded = $run(['104430000201,legal,1000000,2025-06-01,RECEIVED']);
        $this->assertSame('completed', $loaded->status);
        $this->assertSame(0, DB::table('fee_gl_unassigned')->where('id', $h)->count(), 'the held posting is tied');
        $lines = DB::table('contract_fees')->where('contract_id', '104430000201')->orderBy('id')->get();
        $this->assertCount(2, $lines, 'the workbook line and the GL posting now on the loan');
        $this->assertContains(self::GL, $lines->pluck('source_system')->all());
        $this->assertSame(1, $lines->whereNotNull('superseded_by_source')->count(), 'one source of record per loan (fee_source_precedence)');
    }
}
