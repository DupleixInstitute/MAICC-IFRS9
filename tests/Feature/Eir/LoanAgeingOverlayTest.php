<?php

namespace Tests\Feature\Eir;

use App\Services\Eir\LoanAgeingOverlayService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Finance's own position on a loan's ageing, recorded beside the system's own figure, never
 * replacing it (December 2025 Loan Book Investigation Findings v1, section 10): the exported
 * CSV always states the original bracket, in its own comment text as well as its own column,
 * and the import never recomputes a day count or a bracket — whatever the file says stands.
 */
class LoanAgeingOverlayTest extends TestCase
{
    protected $seed = false;
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('loan_ageing_overlays', function (Blueprint $t) {
            $t->id();
            $t->string('contract_id', 30); $t->string('reporting_period', 7);
            $t->integer('system_days')->nullable(); $t->string('system_bracket', 20)->nullable();
            $t->string('workbook_bracket', 20)->nullable();
            $t->integer('finance_days')->nullable(); $t->string('finance_bracket', 20)->nullable();
            $t->text('comment')->nullable(); $t->string('source_file', 255)->nullable();
            $t->unsignedBigInteger('imported_by')->nullable(); $t->timestamps();
            $t->unique(['contract_id', 'reporting_period']);
        });
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'overlay_' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) { @unlink($f); }
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function test_the_export_always_names_the_original_bracket_in_the_comment_itself(): void
    {
        $path = $this->dir . '/overlay.csv';
        $service = new LoanAgeingOverlayService();
        $n = $service->exportTemplate($path, [
            ['contract_id' => '104430000087', 'customer_name' => 'JAT Group Ltd', 'reporting_period' => '2025-12', 'system_days' => 1, 'workbook_bracket' => '31-90 Days', 'comment' => 'Ledger/chart reconstruction suggests 69 days.'],
            ['contract_id' => '104450000023', 'customer_name' => "Nature's Gift Limited", 'reporting_period' => '2025-12', 'system_days' => 271, 'workbook_bracket' => '1-30 Days', 'comment' => null],
        ]);
        $this->assertSame(2, $n);

        $rows = array_map('str_getcsv', file($path));
        $this->assertSame(LoanAgeingOverlayService::HEADER, $rows[0]);
        $jat = $rows[1];
        $this->assertSame('104430000087', $jat[0]);
        $this->assertSame('1', $jat[3]); // system_days column
        $this->assertSame('1-30 Days', $jat[4]); // system_bracket column: 1 day is current
        $this->assertSame('31-90 Days', $jat[5]); // workbook_bracket
        $this->assertStringContainsString('System bracket: 1-30 Days (1 days)', $jat[8]);
        $this->assertStringContainsString('Ledger/chart reconstruction suggests 69 days.', $jat[8]);

        $nature = $rows[2];
        $this->assertStringContainsString('System bracket: >181 Days (271 days).', $nature[8]);
    }

    public function test_import_records_finance_answers_without_recomputing_anything(): void
    {
        $path = $this->dir . '/overlay_filled.csv';
        $f = fopen($path, 'w');
        fputcsv($f, LoanAgeingOverlayService::HEADER);
        fputcsv($f, ['104430000087', 'JAT Group Ltd', '2025-12', '1', '1-30 Days', '31-90 Days', '69', '31-90 Days', 'Finance agrees: schedule-corrected count is right.']);
        fclose($f);

        $result = (new LoanAgeingOverlayService())->import($path, 7);

        $this->assertSame(1, $result['imported']);
        $row = DB::table('loan_ageing_overlays')->where('contract_id', '104430000087')->first();
        $this->assertSame('2025-12', $row->reporting_period);
        $this->assertSame(1, $row->system_days);
        $this->assertSame('1-30 Days', $row->system_bracket);
        $this->assertSame(69, $row->finance_days);
        $this->assertSame('31-90 Days', $row->finance_bracket);
        $this->assertStringContainsString('Finance agrees', $row->comment);
        $this->assertSame(7, $row->imported_by);
        $this->assertSame('overlay_filled.csv', $row->source_file);
    }

    public function test_a_later_import_for_the_same_loan_and_period_replaces_the_earlier_one(): void
    {
        $path = $this->dir . '/overlay2.csv';
        $write = function (string $comment) use ($path) {
            $f = fopen($path, 'w');
            fputcsv($f, LoanAgeingOverlayService::HEADER);
            fputcsv($f, ['104430000087', 'JAT Group Ltd', '2025-12', '1', '1-30 Days', '31-90 Days', '', '', $comment]);
            fclose($f);
        };
        $write('First pass, still checking with Credit.');
        (new LoanAgeingOverlayService())->import($path, 1);
        $write('Confirmed with Credit: 69 days is right.');
        (new LoanAgeingOverlayService())->import($path, 2);

        $this->assertSame(1, DB::table('loan_ageing_overlays')->count());
        $row = DB::table('loan_ageing_overlays')->first();
        $this->assertStringContainsString('Confirmed with Credit', $row->comment);
        $this->assertSame(2, $row->imported_by);
    }

    public function test_a_blank_comment_still_states_the_original_bracket(): void
    {
        $path = $this->dir . '/overlay3.csv';
        $service = new LoanAgeingOverlayService();
        $service->exportTemplate($path, [
            ['contract_id' => '104420000005', 'customer_name' => 'MUSECO', 'reporting_period' => '2025-12', 'system_days' => 0, 'workbook_bracket' => '>181 Days', 'comment' => null],
        ]);
        $rows = array_map('str_getcsv', file($path));
        $this->assertSame('System bracket: 1-30 Days (0 days).', $rows[1][8]);
    }
}
