<?php

namespace Tests\Feature\Ebanker;

use App\Models\GlTrialBalanceLine;
use App\Services\Ebanker\LandingZoneReader;
use App\Services\Ebanker\TrialBalanceLandingService;
use Database\Seeders\EbankerQuerySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use Tests\TestCase;

/**
 * The trial balances through the one door (spec v4 section 6.3; system
 * audit of 9 October 2026, finding M1): each file from Finance is a load of
 * its own with the file's hash and its gate results; every GL line is a raw
 * row under TB_01; gl_trial_balance_lines is derived from the landed rows in
 * the shape the direct importer wrote; a file that does not balance is
 * quarantined with its rows kept and read by nothing; a corrected
 * re-delivery versions the lines that changed and retires the ones that
 * went; the same file twice is a no-op; and the GL name the trial balance
 * prints is what the reconciliation shows beside a bare code.
 *
 * The files are written here in the layout of the delivered
 * rpt_Trial_Balance files: the period as an Excel serial in B1, the header
 * in row 2, "code...title" in column B, amounts as formatted text, and the
 * file's own Grand Total row.
 */
class TrialBalanceLandingServiceTest extends TestCase
{
    protected $seed = false;
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        Schema::create('audit_logs', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id')->nullable(); $t->string('action'); $t->string('entity_type'); $t->integer('entity_id')->nullable(); $t->string('scope')->nullable(); $t->string('reporting_period')->nullable(); $t->integer('rows_affected')->nullable(); $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->text('meta')->nullable(); $t->string('ip_address')->nullable(); $t->string('user_agent')->nullable(); $t->timestamps(); });
        foreach (['2026_08_20_000000_create_gl_trial_balance_tables', '2026_10_08_000000_create_ebanker_landing_zone', '2026_10_09_000000_keep_quarantined_rows_in_landing_zone'] as $m) {
            (require base_path("database/migrations/{$m}.php"))->up();
        }
        (new EbankerQuerySeeder())->run();
        DB::table('users')->insert(['id' => 1, 'name' => 'Finance', 'created_at' => now(), 'updated_at' => now()]);
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tb_' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) { @unlink($f); }
        @rmdir($this->dir);
        parent::tearDown();
    }

    /**
     * One trial balance file. $lines are [code, title, debit text, credit text];
     * the Grand Total printed is the debit total unless one is given.
     */
    private function file(string $name, ?string $period, array $lines, ?string $grandTotal = null, string $sheet = 'Worksheet'): string
    {
        $book = new Spreadsheet();
        $ws = $book->getActiveSheet();
        $ws->setTitle($sheet);
        if ($period !== null) {
            $ws->setCellValue('B1', ExcelDate::PHPToExcel(new \DateTime($period)));
        }
        $ws->setCellValue('B2', 'GL Title'); $ws->setCellValue('C2', 'Debit'); $ws->setCellValue('D2', 'Credit');
        $row = 3; $debit = 0.0;
        foreach ($lines as [$code, $title, $dr, $cr]) {
            $ws->setCellValue("B{$row}", "{$code}...{$title}"); $ws->setCellValue("C{$row}", $dr); $ws->setCellValue("D{$row}", $cr);
            $debit += (float) str_replace(',', '', $dr);
            $row++;
        }
        $ws->setCellValue("B{$row}", 'Grand Total :'); $ws->setCellValue("C{$row}", $grandTotal ?? number_format($debit, 2)); $ws->setCellValue("D{$row}", $grandTotal ?? number_format($debit, 2));
        $path = $this->dir . DIRECTORY_SEPARATOR . $name;
        (new Xls($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }

    private function january(): string
    {
        return $this->file('Trial Balance_31 January 2025.xls', '2025-01-01', [
            ['1050101', 'MAIIC Agricultural Loans', '1,000,000.00', '0.00'],
            ['1050102', 'MAIIC Industrial Loans', '500,000.00', '0.00'],
            ['4215', 'Interest on MAIIC Agricultural Loans', '0.00', '1,500,000.00'],
        ]);
    }

    private function service(): TrialBalanceLandingService
    {
        return new TrialBalanceLandingService();
    }

    public function test_each_file_is_a_load_with_its_hash_and_its_lines_are_raw_rows_from_which_the_gl_side_is_derived(): void
    {
        $this->january();
        $this->file('Trial Balance_28 February 2025.xls', '2025-02-01', [
            ['1050101', 'MAIIC Agricultural Loans', '1,100,000.00', '0.00'],
            ['4215', 'Interest on MAIIC Agricultural Loans', '0.00', '1,100,000.00'],
        ]);
        $r = $this->service()->landDirectory($this->dir, 1);
        $this->assertSame([], $r['failures']);
        $this->assertSame(['LANDED', 'LANDED'], array_column($r['files'], 'status'));
        $this->assertSame(['2025-02-01', '2025-01-01'], array_column($r['files'], 'period'));   // the folder's sort order, as the importer read it
        $jan = $r['files'][1];
        $this->assertSame([3, 1500000.0, 1500000.0, 1500000.0, '2025-01-01', 3, 0, 0], [$jan['lines'], $jan['debit'], $jan['credit'], $jan['grand_total'], $jan['source_period_stamp'], $jan['new'], $jan['versioned'], $jan['unchanged']]);
        $this->assertSame('PASS', $jan['gates']['debits_equal_credits_and_grand_total_ties']['result']);

        $load = DB::table('ebanker_loads')->where('id', $jan['load_id'])->first();
        $this->assertSame([hash_file('sha256', $this->dir . '/Trial Balance_31 January 2025.xls'), TrialBalanceLandingService::ROUTE, '2025-01', 'LANDED'], [$load->pack_hash, $load->route, $load->period, $load->status]);
        $this->assertSame('Trial Balance_31 January 2025.xls', json_decode($load->manifest, true)['file']);
        $this->assertSame(1, DB::table('ebanker_pack_files')->where('load_id', $jan['load_id'])->where('query_id', 'TB_01')->where('rows_loaded', 3)->count());
        $row = DB::table('ebanker_raw_rows')->where('load_id', $jan['load_id'])->where('source_key', '2025-01-01|POSTCLOSING|4215')->first();
        $this->assertNotNull($row);
        $this->assertSame('2025-01-01', $row->row_date);
        $p = json_decode($row->payload, true);
        $this->assertSame(['4215', 'Interest on MAIIC Agricultural Loans', 5, 'POSTCLOSING'], [$p['GL_CODE'], $p['GL_TITLE'], $p['ROW'], $p['BASIS']]);
        $this->assertEquals([0.0, 1500000.0], [$p['DEBIT'], $p['CREDIT']]);
        $this->assertSame(2, DB::table('audit_logs')->where('action', 'Trial Balance Landed')->count());

        // the GL side, in the importer's shape, derived from the landed rows
        $this->assertSame([5, 5, 0, 0], [$r['derived']['lines'], $r['derived']['imported'], $r['derived']['updated'], $r['derived']['removed']]);
        $this->assertSame(['2025-01', '2025-02'], $r['derived']['periods']);
        $line = GlTrialBalanceLine::where('gl_code', '1050101')->whereDate('period', '2025-01-01')->first();
        $this->assertSame(['MAIIC Agricultural Loans', 1000000.0, 0.0, 'POSTCLOSING', 'Trial Balance_31 January 2025.xls', 'Worksheet'],
            [$line->gl_title, $line->debit, $line->credit, $line->basis, $line->source_file, $line->source_sheet]);
        $this->assertSame('2025-01-01', $line->source_period_stamp->toDateString());
        $this->assertSame(5, GlTrialBalanceLine::count());
    }

    public function test_a_file_that_does_not_balance_is_quarantined_with_its_rows_kept_and_nothing_derived_from_it(): void
    {
        $this->january();
        $this->file('Trial Balance_28 February 2025.xls', '2025-02-01', [
            ['1050101', 'MAIIC Agricultural Loans', '1,100,000.00', '0.00'],
            ['4215', 'Interest on MAIIC Agricultural Loans', '0.00', '1,000,000.00'],
        ]);
        $r = $this->service()->landDirectory($this->dir, 1);
        $this->assertCount(1, $r['failures']);
        $this->assertStringContainsString('Trial Balance_28 February 2025.xls - Trial Balance_28 February 2025.xls does not balance: debits 1,100,000.00, credits 1,000,000.00', $r['failures'][0]);
        $feb = $r['files'][0];
        $this->assertSame(['QUARANTINED', 'FAIL'], [$feb['status'], $feb['gates']['debits_equal_credits_and_grand_total_ties']['result']]);
        $this->assertSame('QUARANTINED', DB::table('ebanker_loads')->where('id', $feb['load_id'])->value('status'));
        $this->assertSame(2, DB::table('ebanker_raw_rows')->where('load_id', $feb['load_id'])->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'Trial Balance Quarantined')->count());
        // the reader and the derivation see January only
        $this->assertCount(3, (new LandingZoneReader())->trialBalanceLines());
        $this->assertSame(3, GlTrialBalanceLine::count());
        $this->assertSame(0, GlTrialBalanceLine::whereDate('period', '2025-02-01')->count());
    }

    public function test_a_grand_total_that_does_not_tie_to_the_rows_is_refused(): void
    {
        $path = $this->file('Trial Balance_31 January 2025.xls', '2025-01-01', [['1050101', 'Loans', '100.00', '0.00'], ['4215', 'Interest', '0.00', '100.00']], '200.00');
        $r = $this->service()->landFile($path, GlTrialBalanceLine::BASIS_POSTCLOSING, null, null, 1);
        $this->assertSame('QUARANTINED', $r['status']);
        $this->assertStringContainsString('does not tie to its own Grand Total: rows sum to 100.00, the file states 200.00', $r['failures'][0]);
    }

    public function test_an_amount_that_is_not_a_number_refuses_the_file_and_names_the_row(): void
    {
        $path = $this->file('Trial Balance_31 January 2025.xls', '2025-01-01', [['1050101', 'Loans', '100.00', '0.00'], ['4215', 'Interest', '0.00', '1OO.OO']], '100.00');
        $r = $this->service()->landFile($path, GlTrialBalanceLine::BASIS_POSTCLOSING, null, null, 1);
        $this->assertSame('QUARANTINED', $r['status']);
        $this->assertSame('FAIL', $r['gates']['numeric']['result']);
        $this->assertContains("Trial Balance_31 January 2025.xls row 4: '1OO.OO' in Credit is not a number", $r['failures']);
    }

    public function test_a_sheet_without_a_period_stamp_is_refused_unless_the_period_is_given(): void
    {
        $path = $this->file('AFS bridge.xlsx.xls', null, [['1050101', 'Loans', '100.00', '0.00'], ['4215', 'Interest', '0.00', '100.00']], null, 'Final E-Banker TB Dec 2025');
        $refused = $this->service()->landFile($path, GlTrialBalanceLine::BASIS_PRECLOSING, null, 'Final E-Banker TB Dec 2025', 1);
        $this->assertSame(['QUARANTINED', 'FAIL'], [$refused['status'], $refused['gates']['period_known']['result']]);
        $this->assertStringContainsString('no period stamp in the file and none supplied', $refused['failures'][0]);
        // the same file, offered again with its period: the quarantined load is replaced
        $landed = $this->service()->landFile($path, GlTrialBalanceLine::BASIS_PRECLOSING, '2025-12-01', 'Final E-Banker TB Dec 2025', 1);
        $this->assertSame(['LANDED', '2025-12-01', 'PRECLOSING', $refused['load_id']], [$landed['status'], $landed['period'], $landed['basis'], $landed['load_id']]);
        $this->assertSame(2, DB::table('ebanker_raw_rows')->where('load_id', $landed['load_id'])->where('source_key', 'like', '2025-12-01|PRECLOSING|%')->count());
    }

    public function test_december_holds_both_bases_and_the_derivation_keeps_them_apart(): void
    {
        $this->file('Trial Balance_31 December 2025.xls', '2025-12-01', [['1050101', 'Loans', '100.00', '0.00'], ['3200', 'Retained earnings', '0.00', '100.00']]);
        $afs = $this->file('AFS Final TB Dec 2025.xls', null, [['1050101', 'Loans', '100.00', '0.00'], ['4215', 'Interest', '0.00', '100.00']], null, 'Final E-Banker TB Dec 2025');
        rename($afs, $this->dir . '/afs.xlsx.bridge');   // outside the *.xls glob, passed explicitly as the AFS workbook
        $r = $this->service()->landDirectory($this->dir, 1, $this->dir . '/afs.xlsx.bridge', 'Final E-Banker TB Dec 2025', '2025-12-01');
        $this->assertSame([], $r['failures']);
        $this->assertSame(['POSTCLOSING', 'PRECLOSING'], array_column($r['files'], 'basis'));
        $this->assertSame(4, GlTrialBalanceLine::count());
        $this->assertSame(['POSTCLOSING', 'PRECLOSING'], GlTrialBalanceLine::where('gl_code', '1050101')->orderBy('id')->pluck('basis')->all());
        $this->assertSame('PRECLOSING', GlTrialBalanceLine::where('gl_code', '4215')->value('basis'));
        // the baseline's read of December: the latest line for the GL, as before the landing
        $this->assertEquals(100.0, DB::table('gl_trial_balance_lines')->where('period', 'like', '2025-12%')->where('gl_code', '1050101')->orderByDesc('id')->value('debit'));
    }

    public function test_the_same_file_twice_is_a_no_op_and_a_corrected_file_versions_what_changed_and_retires_what_went(): void
    {
        $path = $this->january();
        $first = $this->service()->landFile($path, GlTrialBalanceLine::BASIS_POSTCLOSING, null, null, 1);
        $this->service()->derive();
        $again = $this->service()->landFile($path, GlTrialBalanceLine::BASIS_POSTCLOSING, null, null, 1);
        $this->assertSame(['ALREADY_LANDED', $first['load_id']], [$again['status'], $again['load_id']]);
        $this->assertSame(3, DB::table('ebanker_raw_rows')->count());

        // Finance re-issues January: the industrial line is corrected, the interest line is unchanged, the agricultural line is gone
        unlink($path);
        $corrected = $this->file('Trial Balance_31 January 2025.xls', '2025-01-01', [
            ['1050102', 'MAIIC Industrial Loans', '1,500,000.00', '0.00'],
            ['4215', 'Interest on MAIIC Agricultural Loans', '0.00', '1,500,000.00'],
        ]);
        $r = $this->service()->landFile($corrected, GlTrialBalanceLine::BASIS_POSTCLOSING, null, null, 1);
        // the industrial line changed; the interest line moved up a row, so it is a new version too; the agricultural line is retired
        $this->assertSame(['LANDED', 0, 2, 0, 1], [$r['status'], $r['new'], $r['versioned'], $r['unchanged'], $r['retired']]);
        $this->assertNotSame($first['load_id'], $r['load_id']);
        $versions = DB::table('ebanker_raw_rows')->where('source_key', '2025-01-01|POSTCLOSING|1050102')->orderBy('version')->get();
        $this->assertCount(2, $versions);
        $this->assertNotNull($versions[0]->superseded_at);
        $this->assertSame([$r['load_id'], 2], [(int) $versions[1]->load_id, (int) $versions[1]->version]);
        $this->assertEquals(1500000.0, json_decode($versions[1]->payload, true)['DEBIT']);
        $gone = DB::table('ebanker_raw_rows')->where('source_key', '2025-01-01|POSTCLOSING|1050101')->first();
        $this->assertNotNull($gone->superseded_at);   // kept, retired, never deleted
        $this->assertSame(5, DB::table('ebanker_raw_rows')->count());

        $d = $this->service()->derive();
        $this->assertSame([2, 0, 2, 1], [$d['lines'], $d['imported'], $d['updated'], $d['removed']]);
        $this->assertSame(['1050102', '4215'], GlTrialBalanceLine::orderBy('gl_code')->pluck('gl_code')->all());
        $this->assertEquals(1500000.0, GlTrialBalanceLine::where('gl_code', '1050102')->value('debit'));
    }

    public function test_the_gl_name_the_trial_balance_prints_is_read_for_a_bare_code(): void
    {
        $this->january();
        $this->file('Trial Balance_28 February 2025.xls', '2025-02-01', [['4215', 'Interest on MAIIC Agricultural Loans (renamed)', '0.00', '1.00'], ['1050101', 'MAIIC Agricultural Loans', '1.00', '0.00']]);
        $this->service()->landDirectory($this->dir, 1);
        $names = (new LandingZoneReader())->glNames();
        // the latest period's title wins; the master GL_02 rows, which carry no name column, add nothing
        $this->assertSame('Interest on MAIIC Agricultural Loans (renamed)', $names['4215']);
        $this->assertSame('MAIIC Industrial Loans', $names['1050102']);
        $this->assertArrayNotHasKey('9999', $names);
    }

    public function test_the_derivation_leaves_the_gl_side_alone_when_nothing_has_landed(): void
    {
        GlTrialBalanceLine::create(['period' => '2025-01-01', 'gl_code' => '1050101', 'gl_title' => 'Loans', 'debit' => 1, 'credit' => 0, 'basis' => 'POSTCLOSING', 'source_file' => 'legacy.xls']);
        $this->assertSame(0, $this->service()->derive()['lines']);
        $this->assertSame(1, GlTrialBalanceLine::count());
    }
}
