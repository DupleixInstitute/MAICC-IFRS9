<?php

namespace Tests\Feature\Reports;

use App\Services\Reports\BookedEcl;
use App\Services\Reports\EclReconciliationService;
use App\Services\Reports\Ifrs9NoteDocuments;
use App\Services\Reports\Ifrs9NoteService;
use App\Services\Reports\LoanBookReconciliationService;
use App\Services\Reports\WriteOffSource;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Every report carries the booked loss allowance (discounted at the original
 * EIR; spec v4 6.10.1, IFRS 9 5.5.17) and the amounts written off from one
 * source: the ledger by the WriteOffClassifier and the approved write-off
 * register (spec v4 3.8; IFRS 7.35H, 35I, 35L). Built 14 October 2026.
 *
 * The book, May to June 2026, portfolio 1:
 *  A  Stage 1 both months; gross 1,000 to 900; ECL 10 to 12, discounted 9 to 11
 *  W  Stage 3 both months; gross 500 to nil after a ledger write-off of 480 on 29 June
 *     (TRF 3, whose other leg debits GL 1064); ECL 300 (discounted 310) to nil
 *  G  Stage 3 in May only; gross 200; ECL 150 (discounted 150); written off 200 in the
 *     register (approved, 15 June)
 *  N  new in June, Stage 2; gross 400; ECL 40 with no discounted figure (no locked EIR)
 */
class BookedAllowanceAndWriteOffsTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        (require base_path('database/migrations/2026_10_08_000000_create_ebanker_landing_zone.php'))->up();
        (require base_path('database/migrations/2026_10_13_170000_create_loan_write_offs.php'))->up();
        Schema::create('loan_portfolios', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->boolean('active')->default(true); });
        Schema::create('loan_books', function (Blueprint $t) {
            $t->increments('id'); $t->integer('loan_portfolio_id')->nullable(); $t->string('contract_id'); $t->string('customer_name')->nullable(); $t->string('external_identity_id')->nullable();
            $t->string('product_code')->nullable(); $t->string('reporting_period'); $t->integer('tenor')->default(24); $t->integer('overdue_days')->default(0);
            $t->decimal('carrying_amount', 20, 2)->default(0); $t->decimal('commitments', 20, 2)->nullable(); $t->decimal('facility_utilisation_rate', 10, 4)->nullable();
            $t->decimal('ecl_value', 20, 2)->nullable(); $t->decimal('ecl_value_discounted', 20, 2)->nullable(); $t->decimal('ecl_discounting_effect', 20, 2)->nullable(); $t->string('ecl_discount_status')->nullable();
            $t->string('ifrs9stage_post_qualitative')->nullable(); $t->string('calculated_ifrs9_stage')->nullable(); $t->double('pd_post_fli')->nullable(); $t->double('pd_prefli')->nullable(); $t->double('lgd_value')->nullable();
            $t->string('contract_status')->nullable();
        });
        Schema::create('expected_credit_loss', function (Blueprint $t) {
            $t->increments('id'); $t->string('reporting_period'); $t->string('ifrs9_stage'); $t->string('ecl_calculation_level'); $t->integer('ecl_calculation_id')->nullable();
            $t->decimal('total_ecl', 20, 2)->nullable(); $t->decimal('total_ecl_discounted', 20, 2)->nullable(); $t->decimal('total_ead', 20, 2)->nullable(); $t->integer('total_loans')->default(0);
            $t->double('pd_value_used')->nullable(); $t->double('lgd_value_used')->nullable(); $t->timestamp('deleted_at')->nullable(); $t->timestamps();
        });
        Schema::create('reporting_periods', function (Blueprint $t) { $t->increments('id'); $t->date('period'); $t->integer('pd_id')->nullable(); $t->integer('lgd_id')->nullable(); $t->string('pd_calculation_source')->nullable(); $t->string('lgd_calculation_source')->nullable(); });
        DB::table('loan_portfolios')->insert(['id' => 1, 'name' => 'MAIIC Agricultural']);

        $disc = 'CALCULATED_TIME_PHASED';
        $book = [
            // contract, period, stage, gross, ecl, discounted, effect, status
            ['A', '2026-05', 1, 1000, 10, 9, 2, $disc], ['A', '2026-06', 1, 900, 12, 11, 2, $disc],
            ['W', '2026-05', 3, 500, 300, 310, 0, $disc], ['W', '2026-06', 3, 0, 0, 0, 0, $disc],
            ['G', '2026-05', 3, 200, 150, 150, 0, $disc],
            ['N', '2026-06', 2, 400, 40, null, null, 'TIME_PHASED_UNRESOLVED'],
        ];
        foreach ($book as [$c, $p, $s, $gross, $ecl, $d, $e, $st]) {
            DB::table('loan_books')->insert(['loan_portfolio_id' => 1, 'contract_id' => $c, 'customer_name' => 'Customer ' . $c, 'product_code' => '1050101', 'reporting_period' => $p,
                'carrying_amount' => $gross, 'ecl_value' => $ecl, 'ecl_value_discounted' => $d, 'ecl_discounting_effect' => $e, 'ecl_discount_status' => $st,
                'ifrs9stage_post_qualitative' => (string) $s, 'calculated_ifrs9_stage' => (string) $s, 'pd_post_fli' => 0.1, 'lgd_value' => 0.5]);
        }
        foreach (['2026-05' => [1 => [10, 9, 1000, 1], 3 => [450, 460, 700, 2]], '2026-06' => [1 => [12, 11, 900, 1], 2 => [40, null, 400, 1], 3 => [0, 0, 0, 1]]] as $p => $stages) {
            foreach ($stages as $s => [$ecl, $d, $ead, $n]) {
                DB::table('expected_credit_loss')->insert(['reporting_period' => $p, 'ifrs9_stage' => (string) $s, 'ecl_calculation_level' => 'portfolio', 'ecl_calculation_id' => 1,
                    'total_ecl' => $ecl, 'total_ecl_discounted' => $d, 'total_ead' => $ead, 'total_loans' => $n, 'pd_value_used' => 0.1, 'lgd_value_used' => 0.5, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        // the ledger: W's closure credit in TRF 3, whose GL leg debits 1064 (GL_05); and an ordinary repayment on A
        DB::table('ebanker_loads')->insert(['id' => 1, 'pack_hash' => str_repeat('a', 64), 'pack_name' => 'fixture', 'manifest' => '{}', 'status' => 'LANDED', 'created_at' => now(), 'updated_at' => now()]);
        $rows = [
            ['P1_01', '1', '000000000000W', '2026-06-29', ['TRANSAMT' => '480.00', 'TRANTYPE' => '305', 'BATCH_TYPE' => 'TRF', 'BATCH_NUMBER' => '3', 'AC_GLCODE' => '1050101', 'PARTICULARS' => 'By Trf ACCOUNT CLOSURE', 'DELETE_FLAG' => 'N']],
            ['GL_05', '2', null, '2026-06-29', ['TRANSAMT' => '-480.00', 'BATCH_TYPE' => 'TRF', 'BATCH_NUMBER' => '3', 'AC_GLCODE' => '1064', 'DELETE_FLAG' => 'N']],
            ['P1_01', '3', '000000000000A', '2026-06-15', ['TRANSAMT' => '100.00', 'TRANTYPE' => '305', 'BATCH_TYPE' => 'CSH', 'BATCH_NUMBER' => '8', 'AC_GLCODE' => '1050101', 'DELETE_FLAG' => 'N']],
        ];
        foreach ($rows as [$q, $k, $acc, $d, $payload]) {
            DB::table('ebanker_raw_rows')->insert(['load_id' => 1, 'query_id' => $q, 'source_key' => $k, 'account' => $acc, 'row_date' => $d, 'payload' => json_encode($payload), 'row_hash' => 'h' . $k, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        // the register: G, approved by a second person
        DB::table('loan_write_offs')->insert(['contract_id' => 'G', 'customer_name' => 'Customer G', 'write_off_date' => '2026-06-15', 'amount' => 200, 'reason' => 'No recovery expected', 'status' => 'APPROVED',
            'proposed_by' => 1, 'approved_by' => 2, 'created_at' => now(), 'updated_at' => now()]);
        // a proposal not yet approved never reaches a report
        DB::table('loan_write_offs')->insert(['contract_id' => 'A', 'write_off_date' => '2026-06-20', 'amount' => 50, 'reason' => 'Proposed only', 'status' => 'PROPOSED', 'proposed_by' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_the_write_offs_come_from_the_ledger_rule_and_the_approved_register_only(): void
    {
        $wo = WriteOffSource::between('2026-05-31', '2026-06-30');
        $this->assertCount(2, $wo);
        $by = array_column($wo, null, 'contract_id');
        $this->assertSame('ledger', $by['W']['source']);
        $this->assertEquals(480, $by['W']['amount']);
        $this->assertStringContainsString('debits GL 1064', $by['W']['reference']);
        $this->assertSame('register', $by['G']['source']);
        $this->assertArrayNotHasKey('A', $by);   // a repayment, and a proposal not yet approved
        $this->assertSame([], WriteOffSource::between('2026-06-30', '2026-07-31'));
        $this->assertStringContainsString('Not recorded', WriteOffSource::enforcementDisclosure());
    }

    public function test_the_basis_of_the_allowance_names_the_loans_without_a_discounted_figure(): void
    {
        $june = BookedEcl::summary('2026-06');
        $this->assertSame('mixed', $june['status']);
        $this->assertSame(2, $june['bases']['discounted']['loans']);
        $this->assertSame(1, $june['bases']['undiscounted']['loans']);
        $this->assertEquals(40, $june['reconciliation']['not_booked']);
        // undiscounted 52, projection (11 + 2 - 12) + (0 + 0 - 0) = 1, discounting -2: the 51 shown
        $this->assertEquals(52, $june['reconciliation']['undiscounted']);
        $this->assertEquals(1, $june['reconciliation']['projection']);
        $this->assertEquals(-2, $june['reconciliation']['discounting_effect']);
        $this->assertEquals(51, $june['reconciliation']['shown']);
        $this->assertStringContainsString('1 loans have no discounted allowance', $june['message']);
        $this->assertSame('booked', BookedEcl::summary('2026-05')['status']);
    }

    public function test_the_ifrs9_note_carries_the_booked_allowance_and_the_amounts_written_off(): void
    {
        $r = Ifrs9NoteService::build('2026-05', '2026-06');
        $ecl = $r['ecl']['lines'];
        $gross = $r['gross']['lines'];
        $this->assertTrue($r['ecl']['check']['ok'], implode(' ', $r['ecl']['check']['messages']));
        $this->assertTrue($r['gross']['check']['ok'], implode(' ', $r['gross']['check']['messages']));
        // the allowance on the booked basis: 9 + 310 + 150 opening, not the undiscounted 460
        $this->assertSame(46900, $ecl['opening']['total']);
        $this->assertSame(5100, $ecl['closing']['total']);
        // the allowance the write-offs used: W 310 (its whole opening allowance, under the 480 written off) and G 150
        $this->assertSame(-46000, $ecl['written_off'][3]);
        $this->assertSame(0, $ecl['derecognised']['total']);
        $this->assertSame(4000, $ecl['originated'][2]);
        $this->assertSame(200, $ecl['remeasurement'][1]);
        // the gross written off: W 480 (still in the book at nil) and G 200 (gone from the book)
        $this->assertSame(-68000, $gross['written_off'][3]);
        $this->assertSame(-2000, $gross['remeasurement'][3]);   // W's other change: 500 to nil less the 480 written off
        $this->assertSame(0, $gross['derecognised'][3]);
        // N has no discounted figure: the note fails closed and says why
        $this->assertSame('failed', $r['status']);
        $basis = collect($r['checks'])->firstWhere('key', 'basis_closing');
        $this->assertSame('fail', $basis['level']);
        $this->assertStringContainsString('no discounted allowance', $basis['detail']);
        $this->assertSame('pass', collect($r['checks'])->firstWhere('key', 'basis_opening')['level']);
        $this->assertStringContainsString('2 contracts written off', collect($r['checks'])->firstWhere('key', 'write_offs')['detail']);
        $this->assertStringContainsString('IFRS 7.35L', $r['methodology']['paragraphs']['write_offs']);
        // the documents: the basis table casts and the write-offs are listed
        $note = Ifrs9NoteDocuments::build($r, 'full');
        $rows = array_column($note['basis']['closing']['rows'], null, 'key');
        $this->assertSame($rows['shown']['values']['total'], $rows['undiscounted']['values']['total'] + $rows['projection']['values']['total'] + $rows['discounting_effect']['values']['total']);
        $this->assertSame(5100, $rows['shown']['values']['total']);
        $this->assertSame(4000, $rows['not_booked']['values']['total']);
        $this->assertCount(2, $note['write_offs']['rows']);
        $this->assertSame(68000, $note['write_offs']['total']);

        // the basis paragraph names only approved staging thresholds in force at the closing month (maker-checker)
        Schema::create('staging_thresholds', function (Blueprint $t) { $t->increments('id'); $t->string('facility_class'); $t->integer('min_tenor_months')->default(0); $t->integer('stage2_dpd'); $t->integer('stage3_dpd'); $t->date('effective_from'); $t->string('status')->default('APPROVED'); });
        DB::table('staging_thresholds')->insert(['facility_class' => 'DEFAULT', 'min_tenor_months' => 0, 'stage2_dpd' => 30, 'stage3_dpd' => 90, 'effective_from' => '2026-01-01', 'status' => 'APPROVED']);
        DB::table('staging_thresholds')->insert(['facility_class' => 'DEFAULT', 'min_tenor_months' => 0, 'stage2_dpd' => 45, 'stage3_dpd' => 120, 'effective_from' => '2026-06-01', 'status' => 'PROPOSED']);
        $staging = Ifrs9NoteService::build('2026-05', '2026-06')['methodology']['paragraphs']['staging'];
        $this->assertStringContainsString('Stage 2 from 30 days past due and Stage 3 from 90 days', $staging);
        $this->assertStringNotContainsString('45 days', $staging);

        // once N carries its discounted allowance, the basis check passes
        DB::table('loan_books')->where('contract_id', 'N')->update(['ecl_value_discounted' => 38, 'ecl_discounting_effect' => 3, 'ecl_discount_status' => 'CALCULATED_TIME_PHASED']);
        $r = Ifrs9NoteService::build('2026-05', '2026-06');
        $this->assertSame('pass', collect($r['checks'])->firstWhere('key', 'basis_closing')['level']);
        $this->assertSame(4900, $r['ecl']['lines']['closing']['total']);
    }

    public function test_the_ecl_reconciliation_and_the_loan_book_reconciliation_carry_the_write_offs(): void
    {
        $ecl = EclReconciliationService::generate(['portfolio_id' => 1, 'start_period' => '2026-05', 'end_period' => '2026-06', 'movement_type' => 'ecl_value']);
        $rows = array_column($ecl['rows'], null, 'particulars');
        $this->assertEquals(469, $rows['Opening ECL Allowance']['total']);
        $this->assertEquals(-460, $rows['Amounts written off']['total']);
        $this->assertEquals(0, $rows['Financial assets derecognised']['total']);
        $this->assertEquals(51, $rows['Closing ECL Allowance']['total']);
        $this->assertSame('mixed', $ecl['basis']['end']['status']);

        $gross = EclReconciliationService::generate(['portfolio_id' => 1, 'start_period' => '2026-05', 'end_period' => '2026-06', 'movement_type' => 'principal_balance']);
        $g = array_column($gross['rows'], null, 'particulars');
        $this->assertEquals(-680, $g['Amounts written off']['total']);
        $this->assertEquals(1300, $g['Closing Carrying Amount']['total']);

        $lb = LoanBookReconciliationService::generate(['portfolio_id' => 1, 'start_period' => '2026-05', 'end_period' => '2026-06']);
        $this->assertEquals(680, $lb['write_offs']);
        $this->assertEquals(120, $lb['repayments']);     // A's 100 and W's 20; W's 480 is the write-off
        $this->assertEquals(0, $lb['reconciliation']['variance']);
    }
}
