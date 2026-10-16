<?php

namespace Tests\Feature\Ebanker;

use App\Services\Ebanker\LandingZoneReader;
use Database\Seeders\EbankerQuerySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * LandingZoneReader::latestRunsOn() (Loan Book Report Point-in-Time
 * Specification v1, sections 2 to 4 and 6): E-Banker re-runs the Loan Book
 * Report for a closed month and the arrears-ageing columns recompute live
 * against today, not the ASONDATE asked for, so a later pull of the same
 * month reads more overdue than an earlier one did. The earliest pull (by
 * its own TRANSACTION_DATE) is now preferred over the latest, but only once
 * the fields that should never move for a closed month — the carrying
 * amount, the total overdue amount, the overdue-principal date — agree
 * across every pull on record for the account (section 6.1); where they do
 * not, the pre-fix rule (the latest pull) stands, flagged for a person.
 */
class LoanBookReportPointInTimeTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        foreach (['2026_10_08_000000_create_ebanker_landing_zone', '2026_10_09_000000_keep_quarantined_rows_in_landing_zone'] as $m) {
            (require base_path("database/migrations/{$m}.php"))->up();
        }
        (new EbankerQuerySeeder())->run();
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        DB::table('ebanker_loads')->insert(['id' => 1, 'pack_hash' => str_repeat('a', 64), 'pack_name' => 'fixture', 'manifest' => '{}', 'status' => 'LANDED', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function pull(string $key, string $account, string $asOnDate, string $transactionDate, array $stable = [], array $more = []): void
    {
        $payload = array_merge(['LOAN_BOOK_DET_ID_A' => $key, 'NEW_AC_NUMBER' => $account, 'ASONDATE' => $asOnDate, 'TRANSACTION_DATE' => $transactionDate,
            'GLCODE' => '1050101', 'CARRYING_AMOUNT' => '8000000.00', 'ARREAS_TOTAL' => '500000.00', 'OVERDUE_PRINCI_DATE' => '7/1/2024'], $stable, $more);
        $iso = \Carbon\CarbonImmutable::createFromFormat('!n/j/Y', $asOnDate)->toDateString();
        DB::table('ebanker_raw_rows')->insert(['load_id' => 1, 'query_id' => 'P2_08', 'source_key' => $key, 'account' => $account, 'row_date' => $iso,
            'payload' => json_encode($payload), 'row_hash' => md5($key), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_the_earliest_pull_wins_once_the_stable_fields_agree(): void
    {
        $a = '000104420000005';
        // the later pull (higher key, later TRANSACTION_DATE) ages the arrears further; the stable fields do not move
        $this->pull('4000', $a, '12/31/2025', '1/5/2026', [], ['DAY_31_91' => '500000.00']);
        $this->pull('5000', $a, '12/31/2025', '9/15/2026', [], ['DAY_91_180' => '500000.00']);

        $run = (new LandingZoneReader())->latestRunsOn('2025-12-31')[$a];

        $this->assertSame('4000', $run['source_key'], 'the earliest pull is preferred, not the highest key');
        $this->assertArrayNotHasKey('pick_conflict', $run);
    }

    public function test_a_genuine_disagreement_on_the_stable_fields_is_flagged_not_resolved_silently(): void
    {
        $a = '000104420000005';
        $this->pull('4000', $a, '12/31/2025', '1/5/2026', ['CARRYING_AMOUNT' => '1.00', 'ARREAS_TOTAL' => '0.00']);
        $this->pull('5000', $a, '12/31/2025', '9/15/2026');

        $run = (new LandingZoneReader())->latestRunsOn('2025-12-31')[$a];

        $this->assertSame('5000', $run['source_key'], 'the pre-fix rule stands while the disagreement is reviewed');
        $this->assertStringContainsString('2 pulls on record', $run['pick_conflict']);
        $this->assertStringContainsString('section 6.1', $run['pick_conflict']);
    }

    public function test_a_disagreement_on_the_overdue_principal_date_alone_is_also_flagged(): void
    {
        $a = '000104420000005';
        $this->pull('4000', $a, '12/31/2025', '1/5/2026', ['OVERDUE_PRINCI_DATE' => '6/1/2024']);
        $this->pull('5000', $a, '12/31/2025', '9/15/2026', ['OVERDUE_PRINCI_DATE' => '7/1/2024']);

        $run = (new LandingZoneReader())->latestRunsOn('2025-12-31')[$a];

        $this->assertSame('5000', $run['source_key']);
        $this->assertArrayHasKey('pick_conflict', $run);
    }

    public function test_with_no_readable_transaction_date_the_pre_fix_rule_stands(): void
    {
        $a = '000104420000005';
        $this->pull('4000', $a, '12/31/2025', '');
        $this->pull('5000', $a, '12/31/2025', '');

        $run = (new LandingZoneReader())->latestRunsOn('2025-12-31')[$a];

        $this->assertSame('5000', $run['source_key'], 'no date to tell the pulls apart by: the highest key wins, as before the fix');
        $this->assertArrayNotHasKey('pick_conflict', $run);
    }

    public function test_a_single_pull_is_returned_unflagged(): void
    {
        $a = '000104420000005';
        $this->pull('5000', $a, '12/31/2025', '1/5/2026');

        $run = (new LandingZoneReader())->latestRunsOn('2025-12-31')[$a];

        $this->assertSame('5000', $run['source_key']);
        $this->assertArrayNotHasKey('pick_conflict', $run);
    }

    public function test_small_rounding_differences_in_the_stable_fields_are_not_a_conflict(): void
    {
        $a = '000104420000005';
        $this->pull('4000', $a, '12/31/2025', '1/5/2026', ['CARRYING_AMOUNT' => '8000000.00']);
        $this->pull('5000', $a, '12/31/2025', '9/15/2026', ['CARRYING_AMOUNT' => '8000000.004']);

        $run = (new LandingZoneReader())->latestRunsOn('2025-12-31')[$a];

        $this->assertSame('4000', $run['source_key']);
        $this->assertArrayNotHasKey('pick_conflict', $run);
    }
}
