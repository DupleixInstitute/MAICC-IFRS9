<?php

namespace Tests\Feature\Eir;

use App\Services\Eir\LoanGrossAmountOverlayService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Finance's own position on a loan's gross amount, recorded beside the system's own figure,
 * never replacing it (MAIIC Finance Overlay Importers Specification v1, section 4.2): the
 * exported CSV always states the original system amount in its own comment text, and import
 * never recomputes anything.
 */
class LoanGrossAmountOverlayTest extends TestCase
{
    protected $seed = false;
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('loan_gross_amount_overlays', function (Blueprint $t) {
            $t->id();
            $t->string('contract_id', 30); $t->string('reporting_period', 7);
            $t->decimal('system_amount', 20, 2)->nullable(); $t->decimal('reference_amount', 20, 2)->nullable();
            $t->decimal('finance_amount', 20, 2)->nullable(); $t->text('comment')->nullable();
            $t->string('source_file', 255)->nullable(); $t->unsignedBigInteger('imported_by')->nullable();
            $t->timestamps();
            $t->unique(['contract_id', 'reporting_period']);
        });
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gross_overlay_' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) { @unlink($f); }
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function test_the_export_always_names_the_original_system_amount_in_the_comment_itself(): void
    {
        $path = $this->dir . '/overlay.csv';
        $service = new LoanGrossAmountOverlayService();
        $n = $service->exportTemplate($path, [
            ['contract_id' => '104430000002', 'customer_name' => 'Edge View Academy Limited', 'reporting_period' => '2025-12', 'system_amount' => 341104423.40, 'reference_amount' => 341104423.40, 'comment' => null],
        ]);
        $this->assertSame(1, $n);

        $rows = array_map('str_getcsv', file($path));
        $this->assertSame(LoanGrossAmountOverlayService::HEADER, $rows[0]);
        $this->assertStringContainsString('System amount: 341,104,423.40.', $rows[1][6]);
        $this->assertSame('341104423.4', $rows[1][3]);
        $this->assertSame('341104423.4', $rows[1][4]);
    }

    public function test_import_records_finance_answers_without_recomputing_anything(): void
    {
        $path = $this->dir . '/overlay_filled.csv';
        $f = fopen($path, 'w');
        fputcsv($f, LoanGrossAmountOverlayService::HEADER);
        fputcsv($f, ['104430000002', 'Edge View Academy Limited', '2025-12', '341104423.40', '341104423.40', '338000000.00', 'Finance: a write-back was missed in the ledger extract.']);
        fclose($f);

        $result = (new LoanGrossAmountOverlayService())->import($path, 3);

        $this->assertSame(1, $result['imported']);
        $row = DB::table('loan_gross_amount_overlays')->where('contract_id', '104430000002')->first();
        $this->assertEquals(341104423.40, $row->system_amount);
        $this->assertEquals(338000000.00, $row->finance_amount);
        $this->assertStringContainsString('write-back was missed', $row->comment);
        $this->assertSame(3, $row->imported_by);
    }

    public function test_a_later_import_replaces_the_earlier_one_for_the_same_loan_and_period(): void
    {
        $path = $this->dir . '/overlay2.csv';
        $write = function (string $comment) use ($path) {
            $f = fopen($path, 'w');
            fputcsv($f, LoanGrossAmountOverlayService::HEADER);
            fputcsv($f, ['104430000002', 'Edge View Academy Limited', '2025-12', '341104423.40', '341104423.40', '', $comment]);
            fclose($f);
        };
        $write('First pass.');
        (new LoanGrossAmountOverlayService())->import($path, 1);
        $write('Confirmed with Finance.');
        (new LoanGrossAmountOverlayService())->import($path, 2);

        $this->assertSame(1, DB::table('loan_gross_amount_overlays')->count());
        $row = DB::table('loan_gross_amount_overlays')->first();
        $this->assertStringContainsString('Confirmed with Finance', $row->comment);
        $this->assertSame(2, $row->imported_by);
    }
}
