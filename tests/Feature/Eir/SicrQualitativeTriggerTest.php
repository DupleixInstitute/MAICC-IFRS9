<?php

namespace Tests\Feature\Eir;

use App\Services\Ebanker\LandingZoneReader;
use App\Services\Eir\GovernanceService;
use App\Services\Eir\QualitativeSicrService;
use App\Services\Eir\StagingService;
use Database\Seeders\SicrQualitativeFactorsSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Feature\Eir\Concerns\CreatesGovernanceSchema;
use Tests\Feature\Eir\Concerns\CreatesSicrSchema;
use Tests\TestCase;

/**
 * Qualitative SICR end to end (IFRS 9 B5.5.17, Appendix A): a trigger raised
 * on the register and approved by a second person moves a current loan to
 * Stage 2 (or Stage 3 for a credit-impaired item) after the days-past-due
 * stage; a trigger not approved, rejected, not yet started or ended does
 * not; a closed trigger still covers the months it was in force; the cure
 * period holds a loan after its trigger ends; who raised and approved it is
 * in the audit log.
 */
class SicrQualitativeTriggerTest extends TestCase
{
    use CreatesGovernanceSchema;
    use CreatesSicrSchema;

    protected $seed = false;

    private int $sicrItem;

    private int $impairedItem;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        $this->createGovernanceSchema(); $this->seedGovernanceDefaults();
        if (! Schema::hasTable('users')) { Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('name'); $t->timestamps(); }); }
        DB::table('users')->insert([['id' => 1, 'name' => 'Credit officer'], ['id' => 2, 'name' => 'Head of credit']]);
        Schema::create('staging_thresholds', function (Blueprint $t) { $t->increments('id'); $t->string('facility_class'); $t->integer('min_tenor_months'); $t->integer('stage2_dpd'); $t->integer('stage3_dpd'); $t->text('rebuttal_basis')->nullable(); $t->string('approved_by')->nullable(); $t->date('approved_at')->nullable(); $t->date('effective_from'); $t->timestamps(); });
        DB::table('staging_thresholds')->insert(['facility_class' => 'DEFAULT', 'min_tenor_months' => 0, 'stage2_dpd' => 31, 'stage3_dpd' => 181, 'effective_from' => '2018-07-13']);
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('external_identity_id')->nullable(); $t->string('customer_id')->nullable(); $t->string('customer_name')->nullable(); $t->string('reporting_period'); $t->string('product_group')->nullable(); $t->string('product_code')->nullable(); $t->integer('tenor')->default(36); $t->integer('overdue_days')->default(0); $t->integer('sicr')->default(0); $t->integer('ifrs9_stage')->default(0); $t->string('ifrs9stage_pre_qualitative')->nullable(); $t->string('ifrs9stage_post_qualitative')->nullable(); $t->string('calculated_ifrs9_stage')->nullable(); foreach (['arrears_1_to_30', 'arrears_30_to_90', 'arrears_91_to_180', 'arrears_180_to_270', 'arrears_271_to_360'] as $c) { $t->string($c)->nullable(); } });
        $this->createSicrSchema();
        (new SicrQualitativeFactorsSeeder())->run();
        // the missed-instalment trigger is off here so the landing zone is not needed
        $this->stageDirectiveAligned(['stage3_missed_instalments' => 'Not applied']);
        $this->sicrItem = (int) DB::table('finance_sicr_items')->where('name', 'Breach of a financial covenant')->value('id');
        $this->impairedItem = (int) DB::table('finance_sicr_items')->where('name', 'Borrower in liquidation, receivership or administration')->value('id');
    }

    private function loan(string $contract, string $period, int $dpd = 0, string $customer = 'C1'): void
    {
        DB::table('loan_books')->insert(['contract_id' => $contract, 'customer_id' => $customer, 'customer_name' => "Borrower {$customer}", 'reporting_period' => $period, 'overdue_days' => $dpd]);
    }

    private function stage(string $period): array
    {
        return (new StagingService(new GovernanceService(), new LandingZoneReader(), new QualitativeSicrService()))->stage($period, 1);
    }

    /** @return array{pre:string,post:string,sicr:int} */
    private function stages(string $contract, string $period): array
    {
        $r = DB::table('loan_books')->where('contract_id', $contract)->where('reporting_period', $period)->first();

        return ['pre' => $r->ifrs9stage_pre_qualitative, 'post' => $r->ifrs9stage_post_qualitative, 'sicr' => (int) $r->sicr];
    }

    private function raise(int $item, string $contract, string $from, ?string $to = null, int $by = 1): int
    {
        return (new QualitativeSicrService())->propose(['item_id' => $item, 'scope' => 'contract', 'contract_id' => $contract, 'reason' => 'Covenant test failed at the half year', 'evidence' => 'Management accounts June 2025, credit file ref CF-12', 'effective_from' => $from, 'effective_to' => $to], $by);
    }

    public function test_seeded_items_state_the_stage_they_imply(): void
    {
        $this->assertSame(6, DB::table('finance_sicr_groups')->count());
        $this->assertSame(25, DB::table('finance_sicr_items')->count());
        $this->assertSame(2, (int) DB::table('finance_sicr_items')->where('id', $this->sicrItem)->value('stage_implied'));
        $this->assertSame(3, (int) DB::table('finance_sicr_items')->where('id', $this->impairedItem)->value('stage_implied'));
        (new SicrQualitativeFactorsSeeder())->run();
        $this->assertSame(25, DB::table('finance_sicr_items')->count(), 'the seeder is idempotent');
    }

    public function test_an_approved_trigger_moves_a_current_loan_to_stage_2_after_the_dpd_stage(): void
    {
        $this->loan('L1', '2025-12');
        $this->loan('L2', '2025-12', 0, 'C2');
        $id = $this->raise($this->sicrItem, 'L1', '2025-12-01');

        // raised but not approved: nothing changes
        $this->stage('2025-12');
        $this->assertSame(['pre' => '1', 'post' => '1', 'sicr' => 0], $this->stages('L1', '2025-12'));

        (new QualitativeSicrService())->approve($id, 2);
        $c = $this->stage('2025-12');
        $this->assertSame(['pre' => '1', 'post' => '2', 'sicr' => 1], $this->stages('L1', '2025-12'));
        $this->assertSame(['pre' => '1', 'post' => '1', 'sicr' => 0], $this->stages('L2', '2025-12'));
        $this->assertSame(1, $c['by_sicr']);
        $this->assertSame(1, $c['qualitative_triggers']);

        // the staging run records which trigger set the stage, by contract
        $log = json_decode(DB::table('audit_logs')->where('action', 'Loan Book Staged')->orderByDesc('id')->value('new_values'), true);
        $this->assertSame(['stage' => 2, 'triggers' => [$id]], $log['qualitative']['L1']);
    }

    public function test_a_credit_impaired_trigger_gives_stage_3_and_a_trigger_never_moves_a_loan_down(): void
    {
        $this->loan('L1', '2025-12');
        $this->loan('L3', '2025-12', 200);
        $register = new QualitativeSicrService();
        $register->approve($this->raise($this->impairedItem, 'L1', '2025-11-15'), 2);
        $register->approve($this->raise($this->sicrItem, 'L3', '2025-11-15'), 2);
        $c = $this->stage('2025-12');
        $this->assertSame(['pre' => '1', 'post' => '3', 'sicr' => 1], $this->stages('L1', '2025-12'));
        $this->assertSame(['pre' => '3', 'post' => '3', 'sicr' => 1], $this->stages('L3', '2025-12'), 'a Stage 2 trigger on a Stage 3 loan leaves it at 3');
        $this->assertSame(1, $c['by_credit_impaired']);
    }

    public function test_a_trigger_applies_only_at_the_month_ends_it_covers_and_rejected_ones_never(): void
    {
        foreach (['2025-10', '2025-11', '2025-12'] as $p) {
            $this->loan('L1', $p);
            $this->loan('L2', $p, 0, 'C2');
            $this->loan('L4', $p, 0, 'C4');
        }
        $this->stageDirectiveAligned(['stage3_missed_instalments' => 'Not applied', 'stage_cure_months' => '0 months (no probation)']);
        $register = new QualitativeSicrService();
        // in force from 1 November to 30 November
        $register->approve($this->raise($this->sicrItem, 'L1', '2025-11-01', '2025-11-30'), 2);
        // starts after the December month-end
        $register->approve($this->raise($this->sicrItem, 'L2', '2026-01-05'), 2);
        // rejected
        $rej = $this->raise($this->sicrItem, 'L4', '2025-10-01');
        $register->reject($rej, 2, 'Covenant was waived before the test date');

        foreach (['2025-10', '2025-11', '2025-12'] as $p) {
            $this->stage($p);
        }
        $this->assertSame('1', $this->stages('L1', '2025-10')['post']);
        $this->assertSame('2', $this->stages('L1', '2025-11')['post']);
        $this->assertSame('1', $this->stages('L1', '2025-12')['post'], 'ended on 30 November: the December month-end does not see it');
        $this->assertSame('1', $this->stages('L2', '2025-12')['post'], 'not started by the December month-end');
        $this->assertSame('1', $this->stages('L4', '2025-10')['post'], 'rejected');
    }

    public function test_a_closed_trigger_still_covers_its_months_and_the_cure_period_holds_the_loan_after_it_ends(): void
    {
        foreach (['2025-09', '2025-10', '2025-11', '2025-12'] as $p) {
            $this->loan('L1', $p);
        }
        $register = new QualitativeSicrService();
        $id = $this->raise($this->sicrItem, 'L1', '2025-09-01');
        $register->approve($id, 2);
        $this->stage('2025-09');
        $this->assertSame('2', $this->stages('L1', '2025-09')['post']);

        $register->close($id, 2, 'Covenant back within limits on the September accounts', '2025-09-30');
        $this->assertSame('CLOSED', DB::table('finance_sicr_triggers')->where('id', $id)->value('status'));
        // staged again after closing, September still sees the trigger
        $this->stage('2025-09');
        $this->assertSame('2', $this->stages('L1', '2025-09')['post']);

        // three months' cure (seeded): October and November held at 2, December released
        $c = $this->stage('2025-10');
        $this->assertSame(['pre' => '1', 'post' => '2', 'sicr' => 0], $this->stages('L1', '2025-10'));
        $this->assertSame(1, $c['held_by_cure']);
        $this->stage('2025-11');
        $this->assertSame('2', $this->stages('L1', '2025-11')['post']);
        $this->stage('2025-12');
        $this->assertSame('1', $this->stages('L1', '2025-12')['post']);
    }

    public function test_a_customer_wide_trigger_reaches_every_loan_of_the_customer(): void
    {
        $this->loan('L1', '2025-12', 0, 'C9');
        $this->loan('L5', '2025-12', 0, 'C9');
        $this->loan('L6', '2025-12', 0, 'C7');
        $register = new QualitativeSicrService();
        $id = $register->propose(['item_id' => $this->sicrItem, 'scope' => 'customer', 'customer_id' => 'C9', 'reason' => 'Group covenant breach', 'evidence' => 'Audit letter', 'effective_from' => '2025-12-01'], 1);
        $register->approve($id, 2);
        $this->stage('2025-12');
        $this->assertSame('2', $this->stages('L1', '2025-12')['post']);
        $this->assertSame('2', $this->stages('L5', '2025-12')['post']);
        $this->assertSame('1', $this->stages('L6', '2025-12')['post']);
    }

    public function test_maker_checker_evidence_and_the_audit_record(): void
    {
        $this->loan('L1', '2025-12');
        $register = new QualitativeSicrService();
        // evidence is required for this item
        try {
            $register->propose(['item_id' => $this->sicrItem, 'scope' => 'contract', 'contract_id' => 'L1', 'reason' => 'x', 'evidence' => '', 'effective_from' => '2025-12-01'], 1);
            $this->fail('evidence was not required');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('needs evidence', $e->getMessage());
        }
        // a loan that is in no loan book is refused
        try {
            $register->propose(['item_id' => $this->sicrItem, 'scope' => 'contract', 'contract_id' => 'NOPE', 'reason' => 'x', 'evidence' => 'y', 'effective_from' => '2025-12-01'], 1);
            $this->fail('unknown contract accepted');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('No loan', $e->getMessage());
        }
        $id = $this->raise($this->sicrItem, 'L1', '2025-12-01');
        try {
            $register->approve($id, 1);
            $this->fail('the raiser approved their own trigger');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Maker-checker', $e->getMessage());
        }
        $register->approve($id, 2);
        $t = DB::table('finance_sicr_triggers')->find($id);
        $this->assertSame(['APPROVED', 1, 2, 2, 'Borrower C1'], [$t->status, (int) $t->triggered_by, (int) $t->approved_by, (int) $t->stage_implied, $t->customer_name]);
        $this->assertSame(['SICR Trigger Raised', 'SICR Trigger Approved'], DB::table('audit_logs')->where('entity_type', 'finance_sicr_triggers')->orderBy('id')->pluck('action')->all());
        $this->assertStringContainsString('"contract_id":"L1"', (string) DB::table('audit_logs')->where('action', 'SICR Trigger Raised')->value('new_values'));
    }
}
