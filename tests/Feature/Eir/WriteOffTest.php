<?php

namespace Tests\Feature\Eir;

use App\Services\Ebanker\LandingZoneReader;
use App\Services\Ebanker\WriteOffClassifier;
use App\Services\Eir\WriteOffRegisterService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/** Spec v4 section 3.8: which credits are write-offs, and the governed register. */
class WriteOffTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // these tests are of other behaviour; the evidence a workflow needs before approval is tested in ApprovalWorkflowsTest
        config(['approvals.require_evidence' => false]);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('audit_logs', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id')->nullable(); $t->string('action'); $t->string('entity_type'); $t->integer('entity_id')->nullable(); $t->string('scope')->nullable(); $t->string('reporting_period')->nullable(); $t->integer('rows_affected')->nullable(); $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->text('meta')->nullable(); $t->string('ip_address')->nullable(); $t->string('user_agent')->nullable(); $t->timestamps(); });
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->string('customer_name')->nullable(); $t->string('external_identity_id')->nullable(); });
        Schema::create('eir_actual_transactions', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->date('transaction_date'); $t->string('transaction_type'); $t->double('principal_component')->default(0); $t->double('interest_component')->default(0); $t->double('fee_component')->default(0); $t->double('total_amount'); $t->string('source_system'); $t->string('source_reference')->nullable(); $t->string('external_transaction_id'); $t->text('row_note')->nullable(); $t->timestamps(); });
        (require base_path('database/migrations/2026_10_13_170000_create_loan_write_offs.php'))->up();
        DB::table('loan_books')->insert(['contract_id' => '104420000005', 'reporting_period' => '2025-12', 'customer_name' => 'MUSECO', 'external_identity_id' => '000104420000005']);
    }

    private function classifier(array $glLegs, array $master = []): WriteOffClassifier
    {
        $zone = $this->createMock(LandingZoneReader::class);
        $zone->method('family')->willReturn($glLegs);
        $zone->method('accountMaster')->willReturn($master);

        return new WriteOffClassifier($zone);
    }

    private function leg(string $date, string $type, string $number, string $gl, float $amount): array
    {
        return ['row_date' => $date, 'payload' => ['BATCH_TYPE' => $type, 'BATCH_NUMBER' => $number, 'AC_GLCODE' => $gl, 'TRANSAMT' => (string) $amount, 'DELETE_FLAG' => 'N']];
    }

    public function test_a_closure_credit_in_a_batch_that_debits_the_write_off_gl_is_a_write_off(): void
    {
        $c = $this->classifier([$this->leg('2026-06-29', 'TRF', '3', '1064', -38492032.54)]);
        $credit = ['TRANTYPE' => '305', 'TRANSAMT' => '5667578.18', 'BATCH_TYPE' => 'TRF', 'BATCH_NUMBER' => '3', 'PARTICULARS' => 'By Trf ACCOUNT CLOSURE - LENZIE MILL'];

        $this->assertNotNull($c->rule($credit, '2026-06-29', '000104420000007'));
        // the same batch number on another day is another batch
        $this->assertNull($c->rule($credit, '2026-06-30', '000104420000007'));
    }

    /** A write-off batch whose GL leg arrives in the monthly ledger feed (M09_01) marks the loan credit (added 14 October 2026). */
    public function test_a_write_off_batch_in_the_monthly_ledger_feed_is_read(): void
    {
        $this->assertContains('M09_01', WriteOffClassifier::GL_LEG_QUERIES);
        $zone = $this->createMock(LandingZoneReader::class);
        $zone->method('family')->willReturnCallback(fn (array $ids) => in_array('M09_01', $ids, true) ? [$this->leg('2026-10-30', 'TRF', '9', '1064', -1000.0), $this->leg('2026-10-30', 'TRF', '9', '1050101', 1000.0)] : []);
        $zone->method('accountMaster')->willReturn([]);
        $c = new WriteOffClassifier($zone);

        $credit = ['TRANTYPE' => '305', 'TRANSAMT' => '1000', 'BATCH_TYPE' => 'TRF', 'BATCH_NUMBER' => '9'];
        $this->assertSame('batch 2026-10-30 TRF 9 debits GL 1064', $c->rule($credit, '2026-10-30', 'A'));
        // the loan leg itself (a credit on a loan GL) does not mark a batch
        $this->assertNull($c->rule(['TRANTYPE' => '305', 'TRANSAMT' => '1000', 'BATCH_TYPE' => 'TRF', 'BATCH_NUMBER' => '10'], '2026-10-30', 'A'));
    }

    public function test_type_300_is_a_write_off_and_an_ordinary_repayment_is_not(): void
    {
        $c = $this->classifier([]);
        $this->assertSame('type 300', $c->rule(['TRANTYPE' => '300', 'TRANSAMT' => '100'], '2024-08-31', 'A'));
        $this->assertNull($c->rule(['TRANTYPE' => '305', 'TRANSAMT' => '100', 'BATCH_TYPE' => 'CSH', 'BATCH_NUMBER' => '7'], '2026-06-29', 'A'));
        // a debit is never a write-off
        $this->assertNull($c->rule(['TRANTYPE' => '300', 'TRANSAMT' => '-100'], '2024-08-31', 'A'));
    }

    public function test_an_account_closure_on_a_status_h_account_is_a_write_off_without_the_gl_leg(): void
    {
        $c = $this->classifier([], ['000104450000038' => ['STATUS_CODE' => 'H']]);
        $credit = ['TRANTYPE' => '305', 'TRANSAMT' => '100', 'BATCH_TYPE' => 'TRF', 'BATCH_NUMBER' => '9', 'PARTICULARS' => 'ACCOUNT CLOSURE'];

        $this->assertNotNull($c->rule($credit, '2026-06-29', '000104450000038'));
        $this->assertNull($c->rule($credit, '2026-06-29', '000104450000099'));
    }

    public function test_a_register_write_off_reaches_the_engines_only_when_a_different_person_approves_it(): void
    {
        $svc = new WriteOffRegisterService();
        $id = $svc->propose(['contract_id' => '000104420000005', 'write_off_date' => '2025-12-31', 'amount' => 138135680.80,
            'reason' => 'Written off in the audit adjustments', 'evidence' => 'TB-to-AFS mapping; GL 1064/1876 batch TRF 26 of 31 Jan 2026'], 1);
        $this->assertSame(0, DB::table('eir_actual_transactions')->count());

        try {
            $svc->approve($id, 1);
            $this->fail('The proposer approved their own write-off.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('cannot approve', $e->getMessage());
        }

        $svc->approve($id, 2);
        $row = DB::table('eir_actual_transactions')->first();
        $this->assertSame('Write-off', $row->transaction_type);
        $this->assertSame(WriteOffRegisterService::SOURCE, $row->source_system);
        $this->assertEqualsWithDelta(138135680.80, $row->total_amount, 0.001);
        $this->assertSame('APPROVED', DB::table('loan_write_offs')->value('status'));

        // a second open write-off for the same loan is refused
        $this->expectException(RuntimeException::class);
        $svc->propose(['contract_id' => '104420000005', 'write_off_date' => '2026-01-31', 'amount' => 1, 'reason' => 'x', 'evidence' => 'y'], 1);
    }

    public function test_reversing_an_approved_write_off_removes_it_from_the_engines(): void
    {
        $svc = new WriteOffRegisterService();
        $id = $svc->propose(['contract_id' => '104420000005', 'write_off_date' => '2025-12-31', 'amount' => 10, 'reason' => 'r', 'evidence' => 'e'], 1);
        $svc->approve($id, 2);
        $svc->close($id, 2, 'reverse', 'Finance confirmed it was not written off');

        $this->assertSame(0, DB::table('eir_actual_transactions')->count());
        $this->assertSame('REVERSED', DB::table('loan_write_offs')->value('status'));
    }

    /** The proposer withdraws; a rejection is a second person's act (documentation audit, 14 October 2026). */
    public function test_the_proposer_cannot_reject_their_own_write_off_but_can_withdraw_it(): void
    {
        $svc = new WriteOffRegisterService();
        $id = $svc->propose(['contract_id' => '104420000005', 'write_off_date' => '2025-12-31', 'amount' => 10, 'reason' => 'r', 'evidence' => 'e'], 1);
        try {
            $svc->close($id, 1, 'reject', 'Changed my mind');
            $this->fail('The proposer rejected their own write-off.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('withdraw it instead', $e->getMessage());
        }
        $this->assertSame('PROPOSED', DB::table('loan_write_offs')->value('status'));

        $svc->close($id, 1, 'withdraw', 'Proposed on the wrong loan');
        $this->assertSame('WITHDRAWN', DB::table('loan_write_offs')->value('status'));

        $other = $svc->propose(['contract_id' => '104420000005', 'write_off_date' => '2025-12-31', 'amount' => 10, 'reason' => 'r', 'evidence' => 'e'], 1);
        $svc->close($other, 2, 'reject', 'No evidence of the board approval');
        $this->assertSame('REJECTED', DB::table('loan_write_offs')->where('id', $other)->value('status'));
    }
}
