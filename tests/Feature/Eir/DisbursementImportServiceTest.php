<?php

namespace Tests\Feature\Eir;

use App\Models\AuditLog;
use App\Services\Eir\DisbursementImportService;
use App\Services\Eir\DisbursementService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * The per-drawdown extract on a private in-memory schema: ISO dates only,
 * positive amounts only, deduplication on the source ids, and the undrawn
 * commitment that comes out of it.
 *
 * The eight facilities in the last test are MAIIC's own at 31 August 2026: MWK
 * 7,188,806,535 approved, MWK 3,716,371,138 drawn and MWK 3,472,435,397
 * undrawn (spec v3 section 7.6).
 */
class DisbursementImportServiceTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('users', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name');
            $t->string('email');
            $t->timestamps();
        });
        Schema::create('contract_disbursements', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id');
            $t->string('sub_account_no', 20)->nullable();
            $t->integer('tranche_no')->nullable();
            $t->string('disbursement_date');
            $t->double('amount');
            $t->string('reference')->nullable();
            $t->string('source_system', 40)->nullable();
            $t->string('source_reference')->nullable();
            $t->string('external_transaction_id', 120)->nullable();
            $t->integer('import_id')->nullable();
            $t->integer('created_by')->nullable();
            $t->timestamps();
            $t->unique(['source_system', 'external_transaction_id']);
            $t->index('contract_id');
        });
        Schema::create('contract_eir', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id')->unique();
            $t->double('approved_amount')->nullable();
            $t->double('drawn_amount')->nullable();
            $t->timestamps();
        });
        Schema::create('loan_books', function (Blueprint $t) {
            $t->increments('id');
            $t->string('contract_id')->nullable();
            $t->string('customer_name')->nullable();
            $t->string('reporting_period')->nullable();
            $t->double('approved_amount')->default(0);
            $t->double('disbursed')->default(0);
            $t->double('commitments')->default(0);
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('user_id')->nullable();
            $t->string('action');
            $t->string('entity_type');
            $t->integer('entity_id')->nullable();
            $t->string('scope')->nullable();
            $t->string('reporting_period')->nullable();
            $t->integer('rows_affected')->nullable();
            $t->text('old_values')->nullable();
            $t->text('new_values')->nullable();
            $t->text('meta')->nullable();
            $t->string('ip_address')->nullable();
            $t->text('user_agent')->nullable();
            $t->timestamps();
        });

        DB::table('users')->insert(['id' => 7, 'name' => 'Finance user', 'email' => 'finance@example.test']);
    }

    private function service(): DisbursementImportService
    {
        return new DisbursementImportService();
    }

    public function test_iso_dated_tranches_load_and_are_audited(): void
    {
        $result = $this->service()->import([
            ['contract_id' => '104450000053', 'disbursement_date' => '2025-06-30', 'amount' => '150,000,000.00', 'tranche_no' => '1', 'reference' => 'PV-1'],
            ['contract_id' => '104450000053', 'disbursement_date' => '2025-09-15', 'amount' => '147161905', 'tranche_no' => '2', 'reference' => 'PV-2'],
        ], null, 7);

        $this->assertSame(2, $result['loaded_rows']);
        $this->assertSame(1, $result['contracts']);
        $this->assertEqualsWithDelta(297_161_905.0, $result['total_amount'], 0.01);
        $this->assertSame('2025-06-30', $result['first_date']);
        $this->assertSame('2025-09-15', $result['last_date']);

        $stored = DB::table('contract_disbursements')->orderBy('disbursement_date')->get();
        $this->assertSame(2, $stored->count());
        $this->assertSame('MAIIC_DISBURSEMENTS', $stored[0]->source_system);
        $this->assertSame(7, (int) $stored[0]->created_by);
        // The audit row records who loaded the file. It reaches the logger as
        // the id the caller passed, because a queue worker has no signed-in user.
        $audit = AuditLog::where('action', 'EIR Disbursement Import')->first();
        $this->assertNotNull($audit);
        $this->assertSame(7, (int) $audit->meta['user_id']);
        $this->assertSame(2, (int) $audit->meta['result']['loaded_rows']);
    }

    public function test_a_day_first_date_refuses_the_whole_file_naming_the_column_and_row(): void
    {
        try {
            $this->service()->import([
                ['contract_id' => '104450000053', 'disbursement_date' => '2025-06-30', 'amount' => 100],
                ['contract_id' => '104450000053', 'disbursement_date' => '15/09/2025', 'amount' => 200],
            ], null, 7);
            $this->fail('A day-first date should refuse the file.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('disbursement_date on row 3', $e->getMessage());
            $this->assertStringContainsString("'15/09/2025'", $e->getMessage());
            $this->assertStringContainsString('never guesses day against month', $e->getMessage());
        }

        $this->assertSame(0, DB::table('contract_disbursements')->count());
    }

    public function test_a_negative_or_blank_amount_and_a_missing_account_refuse_the_file(): void
    {
        foreach ([
            [['contract_id' => '104450000053', 'disbursement_date' => '2025-06-30', 'amount' => '-100'], 'amount on row 2 is -100'],
            [['contract_id' => '104450000053', 'disbursement_date' => '2025-06-30', 'amount' => ''], 'amount on row 2'],
            [['contract_id' => '', 'disbursement_date' => '2025-06-30', 'amount' => '100'], 'loan account number on row 2 is blank'],
            [['contract_id' => '104450000053', 'disbursement_date' => '', 'amount' => '100'], 'disbursement_date on row 2 is blank'],
        ] as [$row, $expected]) {
            try {
                $this->service()->import([$row], null, 7);
                $this->fail('Expected a refusal for ' . json_encode($row));
            } catch (RuntimeException $e) {
                $this->assertStringContainsString($expected, $e->getMessage());
            }
        }

        $this->assertSame(0, DB::table('contract_disbursements')->count());
    }

    public function test_the_same_file_loaded_twice_adds_nothing(): void
    {
        $rows = [
            ['contract_id' => '104450000053', 'disbursement_date' => '2025-06-30', 'amount' => 150_000_000, 'tranche_no' => 1, 'reference' => 'PV-1'],
            ['contract_id' => '104450000053', 'disbursement_date' => '2025-09-15', 'amount' => 147_161_905, 'tranche_no' => 2, 'reference' => 'PV-2'],
        ];

        $this->service()->import($rows, null, 7);
        $again = $this->service()->import($rows, null, 7);

        $this->assertSame(0, $again['loaded_rows']);
        $this->assertSame(2, $again['already_stored']);
        $this->assertSame(2, DB::table('contract_disbursements')->count());
    }

    public function test_a_row_repeated_inside_one_file_is_counted_once_and_named(): void
    {
        $result = $this->service()->import([
            ['contract_id' => '104450000053', 'disbursement_date' => '2025-06-30', 'amount' => 150_000_000, 'tranche_no' => 1, 'reference' => 'PV-1'],
            ['contract_id' => '104450000053', 'disbursement_date' => '2025-06-30', 'amount' => 150_000_000, 'tranche_no' => 1, 'reference' => 'PV-1'],
        ], null, 7);

        $this->assertSame(1, $result['loaded_rows']);
        $this->assertSame(1, $result['duplicate_source_rows']);
        $this->assertStringContainsString('row 3 repeats row 2', $result['notes'][0]);
        $this->assertStringContainsString('own references', $result['notes'][0]);
        $this->assertSame(1, DB::table('contract_disbursements')->count());
    }

    /** Two drawdowns of the same amount on the same day stay apart on their references. */
    public function test_two_tranches_with_their_own_references_both_load(): void
    {
        $result = $this->service()->import([
            ['contract_id' => '104450000053', 'disbursement_date' => '2025-06-30', 'amount' => 50_000_000, 'tranche_no' => 1, 'reference' => 'PV-1'],
            ['contract_id' => '104450000053', 'disbursement_date' => '2025-06-30', 'amount' => 50_000_000, 'tranche_no' => 2, 'reference' => 'PV-2'],
        ], null, 7);

        $this->assertSame(2, $result['loaded_rows']);
        $this->assertSame(0, $result['duplicate_source_rows']);
    }

    public function test_tranches_drawn_and_undrawn_read_back_per_facility_and_by_date(): void
    {
        $this->service()->import([
            ['contract_id' => '104450000053', 'disbursement_date' => '2025-06-30', 'amount' => 150_000_000, 'tranche_no' => 1, 'reference' => 'PV-1'],
            ['contract_id' => '104450000053', 'disbursement_date' => '2025-09-15', 'amount' => 147_161_905, 'tranche_no' => 2, 'reference' => 'PV-2'],
        ], null, 7);
        DB::table('contract_eir')->insert([
            'contract_id' => '104450000053', 'approved_amount' => 1_055_473_655, 'drawn_amount' => 297_161_905,
        ]);

        $service = new DisbursementService();

        $this->assertCount(2, $service->tranchesFor('104450000053'));
        $this->assertEqualsWithDelta(150_000_000, $service->drawnAt('104450000053', '2025-08-31'), 0.01);
        $this->assertEqualsWithDelta(297_161_905, $service->drawnAt('104450000053', '2025-09-30'), 0.01);
        $this->assertEqualsWithDelta(905_473_655, $service->undrawn('104450000053', '2025-08-31'), 0.01);
        $this->assertEqualsWithDelta(758_311_750, $service->undrawn('104450000053', '2025-09-30'), 0.01);

        $facility = $service->facility('104450000053', '2025-09-30');
        $this->assertSame(DisbursementService::SOURCE_DRAWDOWNS, $facility['drawn_source']);
        $this->assertSame(2, $facility['tranche_count']);

        // The later tranche reaches the solver as an outflow, dated.
        $flows = $service->laterDrawdownFlows('104450000053', '2025-06-30');
        $this->assertCount(1, $flows);
        $this->assertSame('2025-09-15', $flows[0]['due_date']);
        $this->assertEqualsWithDelta(-147_161_905, $flows[0]['amount'], 0.01);
        $this->assertSame('DISBURSEMENT', $flows[0]['flow_type']);
    }

    /** With no drawdown rows the figures fall back to the loan book, and say so. */
    public function test_a_facility_with_no_drawdown_rows_falls_back_to_the_loan_book(): void
    {
        DB::table('loan_books')->insert([
            ['contract_id' => 'NO-ROWS', 'customer_name' => 'Fallback Ltd', 'reporting_period' => '2026-07', 'approved_amount' => 400_000_000, 'disbursed' => 300_000_000],
            ['contract_id' => 'NO-ROWS', 'customer_name' => 'Fallback Ltd', 'reporting_period' => '2026-08', 'approved_amount' => 400_000_000, 'disbursed' => 378_809_432],
        ]);

        $service = new DisbursementService();
        $facility = $service->facility('NO-ROWS', '2026-08-31');

        $this->assertSame(DisbursementService::SOURCE_LOAN_BOOK, $facility['drawn_source']);
        $this->assertSame('2026-08', $facility['loan_book_period']);
        $this->assertEqualsWithDelta(378_809_432, $facility['drawn'], 0.01);
        $this->assertEqualsWithDelta(21_190_568, $facility['undrawn'], 0.01);
        // A month earlier the loan book had a different figure.
        $this->assertEqualsWithDelta(300_000_000, $service->drawnAt('NO-ROWS', '2026-07-31'), 0.01);

        // Nothing at all is not zero risk: the undrawn commitment is not known.
        $unknown = $service->facility('NOTHING-KNOWN', '2026-08-31');
        $this->assertNull($unknown['approved']);
        $this->assertNull($unknown['drawn']);
        $this->assertNull($unknown['undrawn']);
        $this->assertSame(DisbursementService::SOURCE_NONE, $unknown['drawn_source']);
    }

    /**
     * MAIIC's eight facilities at 31 August 2026. Five carry loaded drawdowns
     * and three fall back to the loan book; between them they must total MWK
     * 3,472,435,397 undrawn.
     */
    public function test_the_eight_real_facilities_total_the_reported_undrawn_commitment(): void
    {
        $withTranches = [
            ['Mphunzitsi SACCO', '104450000101', 1_560_150_000, [['2025-11-28', 283_000_000, 1]]],
            ['Milele Agroprocessing', '104450000102', 1_347_651_030, [['2025-07-31', 249_625_911, 1], ['2026-02-27', 250_000_000, 2]]],
            ['Lake Malawi Aquaculture', '104450000103', 1_055_473_655, [['2025-06-30', 150_000_000, 1], ['2025-09-15', 147_161_905, 2]]],
            ['Pinnacle Financial Services', '104450000104', 1_046_111_250, [['2025-05-30', 413_777_940, 1], ['2026-01-30', 300_000_000, 2]]],
            ['The Food Empire', '104450000105', 1_045_790_600, [['2025-04-30', 843_995_950, 1]]],
        ];
        $rows = [];
        foreach ($withTranches as [$name, $contractId, $approved, $tranches]) {
            DB::table('contract_eir')->insert(['contract_id' => $contractId, 'approved_amount' => $approved]);
            DB::table('loan_books')->insert(['contract_id' => $contractId, 'customer_name' => $name, 'reporting_period' => '2026-08', 'approved_amount' => $approved]);
            foreach ($tranches as [$date, $amount, $tranche]) {
                $rows[] = ['contract_id' => $contractId, 'disbursement_date' => $date, 'amount' => $amount, 'tranche_no' => $tranche, 'reference' => "PV-{$contractId}-{$tranche}"];
            }
        }
        $this->service()->import($rows, null, 7);

        // Three smaller facilities with no per-drawdown extract yet: approved
        // 1,133,630,000 and drawn 1,078,809,432 between them.
        foreach ([
            ['Smaller facility one', '104450000106', 500_000_000, 500_000_000],
            ['Smaller facility two', '104450000107', 400_000_000, 378_809_432],
            ['Smaller facility three', '104450000108', 233_630_000, 200_000_000],
        ] as [$name, $contractId, $approved, $disbursed]) {
            DB::table('contract_eir')->insert(['contract_id' => $contractId, 'approved_amount' => $approved]);
            DB::table('loan_books')->insert([
                'contract_id' => $contractId, 'customer_name' => $name, 'reporting_period' => '2026-08',
                'approved_amount' => $approved, 'disbursed' => $disbursed,
            ]);
        }

        $service = new DisbursementService();
        $facilities = $service->facilities('2026-08-31');
        $totals = $service->totals($facilities);

        $this->assertSame(8, $totals['facilities']);
        $this->assertEqualsWithDelta(7_188_806_535, $totals['approved'], 0.01);
        $this->assertEqualsWithDelta(3_716_371_138, $totals['drawn'], 0.01);
        $this->assertEqualsWithDelta(3_472_435_397, $totals['undrawn'], 0.01);
        $this->assertSame(8, $totals['tranches']);
        $this->assertSame(5, $totals['from_drawdowns']);
        $this->assertSame(3, $totals['from_loan_book']);

        // Each facility's own figures, named as the specification states them.
        $byId = collect($facilities)->keyBy('contract_id');
        $this->assertEqualsWithDelta(1_277_150_000, $byId['104450000101']['undrawn'], 0.01);
        $this->assertEqualsWithDelta(848_025_119, $byId['104450000102']['undrawn'], 0.01);
        $this->assertEqualsWithDelta(758_311_750, $byId['104450000103']['undrawn'], 0.01);
        $this->assertEqualsWithDelta(332_333_310, $byId['104450000104']['undrawn'], 0.01);
        $this->assertEqualsWithDelta(201_794_650, $byId['104450000105']['undrawn'], 0.01);
        $this->assertSame('Lake Malawi Aquaculture', $byId['104450000103']['customer']);
    }
}
