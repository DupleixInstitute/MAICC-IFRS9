<?php

namespace Tests\Feature\Ebanker;

use App\Services\Ebanker\LandingZoneReader;
use App\Services\Ebanker\PackLandingService;
use Database\Seeders\EbankerQuerySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The gates at the door that the system audit of 9 October 2026 found
 * missing (finding M2), each on a small pack: a ledger account outside the
 * master refuses the pack with the row named; the balance history is tied
 * to the ledger's running balance and the count recorded, a break being a
 * warning against the load and not a refusal; an in-scope account without
 * its month-end row in the stored loan book is a warning; an amount that is
 * not a number refuses the pack; a query version the register does not hold
 * refuses the pack; a quarantined pack keeps its rows where no reader sees
 * them and they are never a version to build on; and whether the dates were
 * ISO is recorded while the declared format is still accepted.
 */
class PackGatesTest extends TestCase
{
    protected $seed = false;
    private string $dir;
    private const A = '000104420000005';
    private const B = '000104420000009';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        Schema::create('audit_logs', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id')->nullable(); $t->string('action'); $t->string('entity_type'); $t->integer('entity_id')->nullable(); $t->string('scope')->nullable(); $t->string('reporting_period')->nullable(); $t->integer('rows_affected')->nullable(); $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->text('meta')->nullable(); $t->string('ip_address')->nullable(); $t->string('user_agent')->nullable(); $t->timestamps(); });
        foreach (['2026_10_08_000000_create_ebanker_landing_zone', '2026_10_09_000000_keep_quarantined_rows_in_landing_zone'] as $m) {
            (require base_path("database/migrations/{$m}.php"))->up();
        }
        (new EbankerQuerySeeder())->run();
        DB::table('users')->insert(['id' => 1, 'name' => 'Loader', 'created_at' => now(), 'updated_at' => now()]);
        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gates_' . uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . DIRECTORY_SEPARATOR . '*') ?: [] as $f) { @unlink($f); }
        @rmdir($this->dir);
        parent::tearDown();
    }

    // ----- the pack -----------------------------------------------------------

    /** @param array<string, array{0:string,1:string,2?:array}> $files name => [query id, csv, manifest overrides] */
    private function writePack(array $files, array $extra = []): void
    {
        $manifest = ['pack' => 'gates pack', 'source' => 'dates exported as m/d/yyyy', 'files' => []] + $extra;
        foreach ($files as $name => $spec) {
            [$queryId, $csv] = $spec;
            file_put_contents($this->dir . DIRECTORY_SEPARATOR . $name, $csv);
            $manifest['files'][] = array_merge(['file' => $name, 'query_id' => $queryId, 'rows' => max(substr_count(trim($csv), "\n"), 0), 'sha256' => hash('sha256', $csv)], $spec[2] ?? []);
        }
        file_put_contents($this->dir . DIRECTORY_SEPARATOR . 'manifest.json', json_encode($manifest));
    }

    private function csv(array $headers, array $rows): string
    {
        $out = '"   ","' . implode('","', $headers) . "\"\n";
        foreach ($rows as $i => $r) {
            $out .= '"' . ($i + 1) . '","' . implode('","', $r) . "\"\n";
        }
        return $out;
    }

    /** [id, account, date, amount, type, gl, deleted] */
    private function ledger(array $rows): string
    {
        return $this->csv(['CUMVOUCH_DET_ID', 'NEW_AC_NUMBER', 'TRANSACTION_DATE', 'TRANSAMT', 'TRANTYPE', 'AC_GLCODE', 'DELETE_FLAG'],
            array_map(fn ($r) => [$r[0], $r[1], $r[2], $r[3], $r[4], $r[5] ?? '1050101', $r[6] ?? 'N'], $rows));
    }

    private function master(array $accounts, string $gl = '1050101'): string
    {
        return $this->csv(['NEW_AC_NUMBER', 'GLCODE', 'ACCOUNT_NAME', 'ACCOUNT_OPEN_DATE'], array_map(fn ($a) => [$a, $gl, "Customer {$a}", '7/1/2024'], $accounts));
    }

    /** [id, account, date, balance] */
    private function balances(array $rows): string
    {
        return $this->csv(['ACCOUNT_BAL_MST_ID', 'GLCODE', 'NEW_AC_NUMBER', 'TRANSACTION_DATE', 'PRINCIPAL_BALANCE'], array_map(fn ($r) => [$r[0], '1050101', $r[1], $r[2], $r[3]], $rows));
    }

    /** [id, account, as-on date, carrying] */
    private function runs(array $rows): string
    {
        return $this->csv(['LOAN_BOOK_DET_ID_A', 'ASONDATE', 'NEW_AC_NUMBER', 'GLCODE', 'CARRYING_AMOUNT'], array_map(fn ($r) => [$r[0], $r[2], $r[1], '1050101', $r[3]], $rows));
    }

    /** The usual good pack: two accounts, July and August postings, balances and runs that agree. */
    private function goodPack(array $override = [], array $extra = []): void
    {
        $this->writePack($override + [
            'P1_02_master.csv' => ['P1_02', $this->master([self::A, self::B])],
            'P1_01_ledger.csv' => ['P1_01', $this->ledger([
                [100, self::A, '7/31/2024', '-10000000.00', '301'], [101, self::B, '7/15/2024', '-500000.00', '301'],
                [102, self::A, '8/31/2024', '-1000.50', '303'], [103, self::A, '8/31/2024', '3,000.00', '305'], [104, self::B, '8/20/2024', '500000.00', '305'],
            ])],
            'P2_09_balances.csv' => ['P2_09', $this->balances([
                [1, self::A, '7/31/2024', '-10000000.00'], [2, self::B, '7/31/2024', '-500000.00'],
                [3, self::A, '8/31/2024', '-9998000.50'], [4, self::B, '8/31/2024', '0.00'],
            ])],
            'P2_08_runs.csv' => ['P2_08', $this->runs([[5000, self::A, '7/31/2024', '10000000.00'], [5001, self::B, '7/31/2024', '500000.00'], [5002, self::A, '8/31/2024', '9998000.50']])],
        ], $extra);
    }

    private function land(): array
    {
        return (new PackLandingService())->land($this->dir, 1);
    }

    // ----- (a) every ledger account is in the master ------------------------

    public function test_a_good_pack_passes_every_gate_and_records_each_result(): void
    {
        $this->goodPack();
        $r = $this->land();
        $this->assertSame('LANDED', $r['status'], json_encode($r['gates']));
        $g = $r['gates']['pack'];
        $this->assertSame(['ERROR', 'PASS', 5], [$g['accounts_in_master']['level'], $g['accounts_in_master']['result'], $g['accounts_in_master']['checked']]);
        $this->assertSame(['WARNING', 'PASS', 4, 4], [$g['balance_history_ties']['level'], $g['balance_history_ties']['result'], $g['balance_history_ties']['checked'], $g['balance_history_ties']['tie']]);
        $this->assertSame('4 of 4 account month-ends tie to the ledger within 1.00', $g['balance_history_ties']['detail']);
        // B is closed at 31 August (zero balance), so three account month-ends are in scope and all have a run row
        $this->assertSame(['WARNING', 'PASS', 3, 0], [$g['month_end_rows']['level'], $g['month_end_rows']['result'], $g['month_end_rows']['checked'], $g['month_end_rows']['missing']]);
        $this->assertSame(['2024-07-31', '2024-08-31'], $g['month_end_rows']['month_ends']);
        $f = $r['gates']['files']['P1_01_ledger.csv']['gates'];
        $this->assertSame('PASS', $f['numeric']['result']);
        $this->assertSame(['TRANSAMT'], $f['numeric']['columns']);
        $this->assertSame(['PASS', null, '1'], [$f['query_version']['result'], $f['query_version']['manifest'], $f['query_version']['register']]);
        $this->assertStringContainsString('states no version', $f['query_version']['note']);
        $this->assertSame('1', DB::table('ebanker_pack_files')->where('file', 'P1_01_ledger.csv')->value('query_version'));
        // the comma in 3,000.00 is stripped and the amount lands as written
        $this->assertSame('3,000.00', json_decode(DB::table('ebanker_raw_rows')->where('source_key', '103')->value('payload'), true)['TRANSAMT']);
    }

    public function test_a_ledger_account_outside_the_master_refuses_the_pack_and_names_the_row(): void
    {
        $this->goodPack(['P1_02_master.csv' => ['P1_02', $this->master([self::A])]]);
        $r = $this->land();
        $this->assertSame('QUARANTINED', $r['status']);
        $g = $r['gates']['pack']['accounts_in_master'];
        $this->assertSame(['ERROR', 'FAIL', 5, 2], [$g['level'], $g['result'], $g['checked'], $g['failed']]);
        $this->assertSame('P1_01_ledger.csv row 3: account ' . self::B . ' is not in the account master of this pack or an earlier landed one', $g['failures'][0]);
        $this->assertSame('3 of 5 loan postings carry an account in the master', $g['detail']);
        $this->assertSame('QUARANTINED', DB::table('ebanker_loads')->first()->status);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'E-Banker Pack Quarantined')->count());
    }

    public function test_the_master_of_an_earlier_landed_pack_counts_and_a_posting_off_the_loan_gls_is_not_checked(): void
    {
        $this->writePack(['P1_02_master.csv' => ['P1_02', $this->master([self::A, self::B])]], ['pack' => 'masters first']);
        $this->assertSame('LANDED', $this->land()['status']);
        // the monthly ledger alone: its accounts are in the master landed before; the contra leg on income GL 4215 has no master row and needs none
        $this->writePack(['M09_01_ledger.csv' => ['M09_01', $this->ledger([[200, self::A, '9/30/2024', '-100.00', '303'], [201, '', '9/30/2024', '100.00', '303', '4215'], [202, '000199990000001', '9/30/2024', '1.00', '303', '4216']])]], ['pack' => 'september']);
        $r = $this->land();
        $this->assertSame('LANDED', $r['status'], json_encode($r['gates']['pack']));
        $this->assertSame(['PASS', 1], [$r['gates']['pack']['accounts_in_master']['result'], $r['gates']['pack']['accounts_in_master']['checked']]);
    }

    public function test_a_deleted_posting_is_not_checked_against_the_master(): void
    {
        $this->goodPack(['P1_01_ledger.csv' => ['P1_01', $this->ledger([[100, self::A, '7/31/2024', '-1.00', '301'], [101, '000199990000001', '7/31/2024', '-1.00', '301', '1050101', 'Y']])],
            'P2_09_balances.csv' => ['P2_09', $this->balances([[1, self::A, '7/31/2024', '-1.00']])], 'P2_08_runs.csv' => ['P2_08', $this->runs([[1, self::A, '7/31/2024', '1.00']])]]);
        $this->assertSame('LANDED', $this->land()['status']);
    }

    // ----- (b) the balance history ties to the ledger -----------------------

    public function test_a_balance_history_row_off_the_ledger_is_recorded_as_a_warning_and_the_pack_still_lands(): void
    {
        $this->goodPack(['P2_09_balances.csv' => ['P2_09', $this->balances([
            [1, self::A, '7/31/2024', '-10000000.00'], [2, self::B, '7/31/2024', '-500000.00'],
            [3, self::A, '8/31/2024', '-9997995.50'],   // 5.00 off the ledger
            [4, self::B, '8/31/2024', '0.75'],          // within 1.00: ties
        ])]]);
        $r = $this->land();
        $this->assertSame('LANDED', $r['status']);
        $g = $r['gates']['pack']['balance_history_ties'];
        $this->assertSame(['WARNING', 'WARN', 4, 3], [$g['level'], $g['result'], $g['checked'], $g['tie']]);
        $this->assertSame('3 of 4 account month-ends tie to the ledger within 1.00', $g['detail']);
        $this->assertCount(1, $g['failures']);
        $this->assertSame('P2_09_balances.csv row 4: account ' . self::A . ' at 2024-08-31: ledger running balance -9,998,000.50, balance history -9,997,995.50, difference -5.00', $g['failures'][0]);
        $stored = json_decode(DB::table('ebanker_loads')->first()->gates, true);
        $this->assertSame('WARN', $stored['pack']['balance_history_ties']['result']);
    }

    public function test_the_running_balance_reaches_back_to_postings_landed_by_an_earlier_pack(): void
    {
        $this->goodPack();
        $this->assertSame('LANDED', $this->land()['status']);
        // September's pack: one posting since the watermark and the month-end balance that includes July and August
        $this->writePack([
            'M09_01_ledger.csv' => ['M09_01', $this->ledger([[200, self::A, '9/30/2024', '-1500.00', '303']])],
            'M09_02_balances.csv' => ['M09_02', $this->balances([[9, self::A, '9/30/2024', '-9999500.50']])],
        ], ['pack' => 'september']);
        $r = $this->land();
        $this->assertSame('LANDED', $r['status']);
        $this->assertSame(['PASS', 1, 1], [$r['gates']['pack']['balance_history_ties']['result'], $r['gates']['pack']['balance_history_ties']['checked'], $r['gates']['pack']['balance_history_ties']['tie']]);
        $this->assertSame('SKIPPED', $r['gates']['pack']['month_end_rows']['result']);
    }

    // ----- (c) every in-scope account has its month-end row -----------------

    public function test_an_in_scope_account_without_a_stored_loan_book_row_is_a_warning(): void
    {
        $this->goodPack(['P2_08_runs.csv' => ['P2_08', $this->runs([[5000, self::A, '7/31/2024', '10000000.00'], [5002, self::A, '8/31/2024', '9998000.50']])]]);
        $r = $this->land();
        $this->assertSame('LANDED', $r['status']);
        $g = $r['gates']['pack']['month_end_rows'];
        $this->assertSame(['WARNING', 'WARN', 3, 1], [$g['level'], $g['result'], $g['checked'], $g['missing']]);
        $this->assertSame('account ' . self::B . ' has a ledger balance at 2024-07-31 and no stored loan book row for that month-end', $g['failures'][0]);
        $this->assertSame('2 of 3 in-scope account month-ends have a stored loan book row', $g['detail']);
    }

    public function test_an_account_on_a_gl_outside_the_loan_gls_is_not_in_scope(): void
    {
        $this->goodPack([
            'P1_02_master.csv' => ['P1_02', $this->master([self::A]) . '"2","' . self::B . '","3065","Suspense","7/1/2024"' . "\n"],
            'P2_08_runs.csv' => ['P2_08', $this->runs([[5000, self::A, '7/31/2024', '10000000.00'], [5002, self::A, '8/31/2024', '9998000.50']])],
        ]);
        $r = $this->land();
        $this->assertSame('LANDED', $r['status']);
        $this->assertSame(['PASS', 2, 0], [$r['gates']['pack']['month_end_rows']['result'], $r['gates']['pack']['month_end_rows']['checked'], $r['gates']['pack']['month_end_rows']['missing']]);
    }

    // ----- (d) numerics parse -----------------------------------------------

    public function test_an_amount_that_is_not_a_number_refuses_the_pack_and_names_the_file_and_row(): void
    {
        $this->goodPack(['P1_01_ledger.csv' => ['P1_01', $this->ledger([[100, self::A, '7/31/2024', '-10000000.00', '301'], [101, self::B, '7/15/2024', '5OO.OO', '301']])]]);
        $r = $this->land();
        $this->assertSame('QUARANTINED', $r['status']);
        $f = $r['gates']['files']['P1_01_ledger.csv'];
        $this->assertSame(['ERROR', 'FAIL'], [$f['gates']['numeric']['level'], $f['gates']['numeric']['result']]);
        $this->assertSame("P1_01_ledger.csv row 3: '5OO.OO' in TRANSAMT is not a number", $f['gates']['numeric']['failures'][0]);
        $this->assertContains("P1_01_ledger.csv row 3: '5OO.OO' in TRANSAMT is not a number", $f['failures']);
    }

    public function test_a_balance_column_is_checked_as_a_number_and_a_blank_is_allowed(): void
    {
        $this->goodPack(['P2_09_balances.csv' => ['P2_09', $this->balances([[1, self::A, '7/31/2024', ''], [2, self::B, '7/31/2024', 'n/a']])]]);
        $r = $this->land();
        $this->assertSame('QUARANTINED', $r['status']);
        $this->assertSame("P2_09_balances.csv row 3: 'n/a' in PRINCIPAL_BALANCE is not a number", $r['gates']['files']['P2_09_balances.csv']['gates']['numeric']['failures'][0]);
    }

    // ----- (e) the query version matches the register -----------------------

    public function test_a_query_version_the_register_does_not_hold_refuses_the_pack(): void
    {
        $this->goodPack(['P1_01_ledger.csv' => ['P1_01', $this->ledger([[100, self::A, '7/31/2024', '-10000000.00', '301']]), ['query_version' => '2']],
            'P2_09_balances.csv' => ['P2_09', $this->balances([[1, self::A, '7/31/2024', '-10000000.00']])], 'P2_08_runs.csv' => ['P2_08', $this->runs([[1, self::A, '7/31/2024', '10000000.00']])]]);
        $r = $this->land();
        $this->assertSame('QUARANTINED', $r['status']);
        $g = $r['gates']['files']['P1_01_ledger.csv']['gates']['query_version'];
        $this->assertSame(['ERROR', 'FAIL', '2', '1'], [$g['level'], $g['result'], $g['manifest'], $g['register']]);
        $this->assertSame('P1_01_ledger.csv: query P1_01 version 2 in the manifest, 1 in the register', $g['failures'][0]);
    }

    public function test_a_query_version_the_register_holds_passes_and_is_recorded_on_the_file(): void
    {
        DB::table('ebanker_queries')->where('query_id', 'P1_01')->update(['version' => '3']);
        $this->goodPack(['P1_01_ledger.csv' => ['P1_01', $this->ledger([[100, self::A, '7/31/2024', '-10000000.00', '301']]), ['query_version' => '3']],
            'P2_09_balances.csv' => ['P2_09', $this->balances([[1, self::A, '7/31/2024', '-10000000.00']])], 'P2_08_runs.csv' => ['P2_08', $this->runs([[1, self::A, '7/31/2024', '10000000.00']])]]);
        $r = $this->land();
        $this->assertSame('LANDED', $r['status']);
        $this->assertSame('PASS', $r['gates']['files']['P1_01_ledger.csv']['gates']['query_version']['result']);
        $this->assertSame('3', DB::table('ebanker_pack_files')->where('file', 'P1_01_ledger.csv')->value('query_version'));
    }

    // ----- (f) a quarantined pack keeps its rows and nothing reads them -------

    public function test_quarantined_rows_are_kept_against_the_load_and_are_never_a_version_to_build_on(): void
    {
        // refused: B is not in the master
        $this->goodPack(['P1_02_master.csv' => ['P1_02', $this->master([self::A])]], ['pack' => 'refused']);
        $q = $this->land();
        $this->assertSame('QUARANTINED', $q['status']);
        $this->assertSame(5, DB::table('ebanker_raw_rows')->where('load_id', $q['load_id'])->where('query_id', 'P1_01')->count());
        $this->assertSame(['QUARANTINED'], DB::table('ebanker_pack_files')->where('load_id', $q['load_id'])->distinct()->pluck('status')->all());
        $this->assertSame([], (new LandingZoneReader())->ledgerByAccount());
        $this->assertSame([], (new LandingZoneReader())->runMonthEnds());
        $this->assertSame([], (new LandingZoneReader())->loadsOf(['P1_01']));
        // the corrected pack lands the same keys at version 1: the quarantined rows were not a lineage
        $this->goodPack([], ['pack' => 'corrected']);
        $r = $this->land();
        $this->assertSame('LANDED', $r['status']);
        $this->assertSame(5, $r['files']['P1_01_ledger.csv']['new']);
        $this->assertSame(0, $r['files']['P1_01_ledger.csv']['versioned']);
        $this->assertSame([1], DB::table('ebanker_raw_rows')->where('load_id', $r['load_id'])->where('source_key', '100')->pluck('version')->all());
        $this->assertSame([1, 1], DB::table('ebanker_raw_rows')->where('query_id', 'P1_01')->where('source_key', '100')->orderBy('load_id')->pluck('version')->all());
        $this->assertCount(2, (new LandingZoneReader())->ledgerByAccount());
        $this->assertSame([$r['load_id']], array_column((new LandingZoneReader())->loadsOf(['P1_01']), 'load_id'));
    }

    public function test_a_refused_pack_offered_again_replaces_its_quarantined_rows(): void
    {
        $this->goodPack(['P1_02_master.csv' => ['P1_02', $this->master([self::A])]]);
        $first = $this->land();
        $this->assertSame('QUARANTINED', $first['status']);
        // the master is landed separately, the same manifest is offered again and now passes
        $manifest = file_get_contents($this->dir . '/manifest.json');
        $this->writePack(['P1_02_master.csv' => ['P1_02', $this->master([self::A, self::B])]], ['pack' => 'masters']);
        $this->assertSame('LANDED', $this->land()['status']);
        $this->goodPack(['P1_02_master.csv' => ['P1_02', $this->master([self::A])]]);
        $this->assertSame($manifest, file_get_contents($this->dir . '/manifest.json'));
        $second = $this->land();
        $this->assertSame('LANDED', $second['status']);
        $this->assertSame($first['load_id'], $second['load_id']);
        $this->assertSame(['LANDED'], DB::table('ebanker_pack_files')->where('load_id', $second['load_id'])->distinct()->pluck('status')->all());
        $this->assertSame(5, DB::table('ebanker_raw_rows')->where('load_id', $second['load_id'])->where('query_id', 'P1_01')->count());
    }

    // ----- (g) whether the dates were ISO is recorded ------------------------

    public function test_dates_in_the_declared_format_are_accepted_and_recorded_as_not_iso(): void
    {
        $this->goodPack();
        $r = $this->land();
        $this->assertSame('LANDED', $r['status']);
        $f = $r['gates']['files']['P1_01_ledger.csv']['gates']['dates_iso'];
        $this->assertSame(['WARNING', 'WARN', false, ['TRANSACTION_DATE']], [$f['level'], $f['result'], $f['iso'], $f['columns']]);
        $this->assertSame("P1_01_ledger.csv row 2: '7/31/2024' in TRANSACTION_DATE is not ISO", $f['first_non_iso']);
        $g = $r['gates']['pack']['dates_iso'];
        $this->assertSame(['WARNING', 'WARN', 4, 0], [$g['level'], $g['result'], $g['checked'], $g['iso']]);
        $this->assertStringContainsString('the format the manifest declares is accepted (D23)', $g['detail']);
    }

    public function test_an_iso_pack_records_every_dated_file_as_iso(): void
    {
        $this->writePack([
            'P1_02_master.csv' => ['P1_02', $this->csv(['NEW_AC_NUMBER', 'GLCODE', 'ACCOUNT_NAME', 'ACCOUNT_OPEN_DATE'], [[self::A, '1050101', 'Customer', '2024-07-01']])],
            'P1_01_ledger.csv' => ['P1_01', $this->ledger([[100, self::A, '2024-07-31', '-10000000.00', '301']])],
            'P2_09_balances.csv' => ['P2_09', $this->balances([[1, self::A, '2024-07-31', '-10000000.00']])],
            'P2_08_runs.csv' => ['P2_08', $this->runs([[1, self::A, '2024-07-31', '10000000.00']])],
        ], ['source' => 'RUN 0 settings applied', 'date_format' => 'Y-m-d']);
        $r = $this->land();
        $this->assertSame('LANDED', $r['status'], json_encode($r['gates']));
        $this->assertSame(['PASS', 4, 4], [$r['gates']['pack']['dates_iso']['result'], $r['gates']['pack']['dates_iso']['checked'], $r['gates']['pack']['dates_iso']['iso']]);
        $this->assertSame('2024-07-31', DB::table('ebanker_raw_rows')->where('source_key', '100')->value('row_date'));
        $this->assertSame(['PASS', 1], [$r['gates']['pack']['balance_history_ties']['result'], $r['gates']['pack']['balance_history_ties']['tie']]);
    }
}
