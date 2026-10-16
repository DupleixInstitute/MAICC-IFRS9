<?php

namespace Tests\Feature\Ebanker;

use App\Services\Ebanker\PackLandingService;
use Database\Seeders\EbankerQuerySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The drift notice of the Loan Book Report Point-in-Time Specification v1,
 * section 6.3: a pack that lands a Loan Book Report pull for an account and
 * ASONDATE an earlier pack already landed is not absorbed silently. One
 * entry per account/ASONDATE pair is audit-logged, naming whether the
 * stable fields still agree (ordinary ageing drift) or genuinely differ
 * (the section 6.1 conflict). A month's first-ever pull has nothing to
 * drift against and is left out.
 */
class LoanBookRunDriftNoticeTest extends TestCase
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
        foreach (['2026_10_08_000000_create_ebanker_landing_zone.php', '2026_10_09_000000_keep_quarantined_rows_in_landing_zone.php'] as $m) {
            (require base_path('database/migrations/' . $m))->up();
        }
        (new EbankerQuerySeeder())->run();
        DB::table('users')->insert(['id' => 1, 'name' => 'Loader', 'created_at' => now(), 'updated_at' => now()]);
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pack_' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) { @unlink($f); }
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function masterCsv(string $account): string
    {
        return "\"   \",\"NEW_AC_NUMBER\",\"GLCODE\",\"ACCOUNT_NAME\",\"ACCOUNT_OPEN_DATE\"\n\"1\",\"{$account}\",\"1050101\",\"Loan Book Report Co\",\"7/1/2024\"\n";
    }

    private function loanBookCsv(string $key, string $account, string $asOnDate, string $transactionDate, string $carrying, string $arrearsTotal, string $overdueDate): string
    {
        $header = "\"   \",\"LOAN_BOOK_DET_ID_A\",\"NEW_AC_NUMBER\",\"ASONDATE\",\"TRANSACTION_DATE\",\"GLCODE\",\"CARRYING_AMOUNT\",\"ARREAS_TOTAL\",\"OVERDUE_PRINCI_DATE\",\"DAY_1_30\",\"DAY_31_91\",\"DAY_91_180\",\"DAY_181_270\",\"DAY_271_360\"\n";

        return $header . "\"1\",\"{$key}\",\"{$account}\",\"{$asOnDate}\",\"{$transactionDate}\",\"1050101\",\"{$carrying}\",\"{$arrearsTotal}\",\"{$overdueDate}\",\"0.00\",\"{$arrearsTotal}\",\"0.00\",\"0.00\",\"0.00\"\n";
    }

    private function writePack(string $loanBookCsv, string $account): void
    {
        $files = ['P1_02_master.csv' => $this->masterCsv($account), 'P2_08_runs.csv' => $loanBookCsv];
        $manifest = ['pack' => 'test pack', 'source' => 'dates exported as m/d/yyyy', 'files' => []];
        $map = ['P1_02_master.csv' => 'P1_02', 'P2_08_runs.csv' => 'P2_08'];
        foreach ($files as $name => $csv) {
            file_put_contents($this->dir . DIRECTORY_SEPARATOR . $name, $csv);
            $manifest['files'][] = ['file' => $name, 'query_id' => $map[$name], 'rows' => 1, 'sha256' => hash('sha256', $csv)];
        }
        file_put_contents($this->dir . DIRECTORY_SEPARATOR . 'manifest.json', json_encode($manifest));
    }

    public function test_a_second_pull_of_an_already_landed_month_is_flagged_as_ordinary_ageing_drift(): void
    {
        $a = '000104420000005';
        $this->writePack($this->loanBookCsv('4000', $a, '12/31/2025', '1/5/2026', '8000000.00', '500000.00', '7/1/2024'), $a);
        $first = (new PackLandingService())->land($this->dir, 1);
        $this->assertSame('LANDED', $first['status']);
        $this->assertSame([], $first['loan_book_run_drift'], 'the first pull of this month has nothing to drift against');

        @unlink($this->dir . DIRECTORY_SEPARATOR . 'manifest.json');
        @unlink($this->dir . DIRECTORY_SEPARATOR . 'P2_08_runs.csv');
        $this->writePack($this->loanBookCsv('5000', $a, '12/31/2025', '9/15/2026', '8000000.00', '500000.00', '7/1/2024'), $a);
        $second = (new PackLandingService())->land($this->dir, 1);

        $this->assertSame('LANDED', $second['status']);
        $this->assertCount(1, $second['loan_book_run_drift']);
        $notice = $second['loan_book_run_drift'][0];
        $this->assertSame($a, $notice['account']);
        $this->assertSame('2025-12-31', $notice['as_on_date']);
        $this->assertSame('4000', $notice['earlier_source_key']);
        $this->assertSame('5000', $notice['new_source_key']);
        $this->assertTrue($notice['stable_fields_agree']);
        $log = DB::table('audit_logs')->where('action', 'Loan Book Report Re-pull Drift Notice')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('ordinary ageing drift', json_encode(json_decode($log->new_values, true)));
    }

    public function test_a_re_pull_that_disagrees_on_the_stable_fields_is_flagged_as_a_conflict(): void
    {
        $a = '000104420000005';
        $this->writePack($this->loanBookCsv('4000', $a, '12/31/2025', '1/5/2026', '8000000.00', '500000.00', '7/1/2024'), $a);
        (new PackLandingService())->land($this->dir, 1);

        @unlink($this->dir . DIRECTORY_SEPARATOR . 'manifest.json');
        @unlink($this->dir . DIRECTORY_SEPARATOR . 'P2_08_runs.csv');
        // the re-pull's carrying amount genuinely differs, not just its ageing buckets
        $this->writePack($this->loanBookCsv('5000', $a, '12/31/2025', '9/15/2026', '7500000.00', '500000.00', '7/1/2024'), $a);
        $second = (new PackLandingService())->land($this->dir, 1);

        $this->assertCount(1, $second['loan_book_run_drift']);
        $this->assertFalse($second['loan_book_run_drift'][0]['stable_fields_agree']);
        $this->assertStringContainsString('section 6.1', $second['loan_book_run_drift'][0]['detail']);
    }

    public function test_landing_the_same_pack_twice_is_a_no_op_with_no_drift_notice(): void
    {
        $a = '000104420000005';
        $this->writePack($this->loanBookCsv('4000', $a, '12/31/2025', '1/5/2026', '8000000.00', '500000.00', '7/1/2024'), $a);
        (new PackLandingService())->land($this->dir, 1);
        $again = (new PackLandingService())->land($this->dir, 1);

        $this->assertSame('ALREADY_LANDED', $again['status']);
        $this->assertSame(0, DB::table('audit_logs')->where('action', 'Loan Book Report Re-pull Drift Notice')->count());
    }
}
