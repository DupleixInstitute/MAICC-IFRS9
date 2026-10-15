<?php

namespace Tests\Feature\Eir;

use App\Services\Eir\EirCalculationService;
use App\Services\Eir\EirLockReviewService;
use App\Services\Eir\EirReadinessService;
use App\Services\Eir\HeldFeeEvidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

/**
 * The EIR fee-readiness control (15 October 2026): an EIR is not solved or locked while a held GL fee
 * posting names the loan as its likely owner; a locked EIR whose fee evidence arrives later is flagged
 * for review, never reopened by the system; nothing is allocated by suggestion.
 */
class EirFeeReadinessControlTest extends TestCase
{
    use RefreshDatabase;

    private function contract(string $id, array $more = []): void
    {
        DB::table('contract_eir')->insert($more + ['contract_id' => $id, 'instrument_type' => 'AMORTISED_LOAN', 'rate_type' => 'FIXED', 'origination_date' => '2025-01-01',
            'drawn_amount' => 1000, 'payments_per_year' => 12, 'frequency_source' => 'STATED', 'schedule_source' => 'GENERATED', 'schedule_approval_status' => 'APPROVED',
            'calculation_status' => 'PENDING', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('contract_cashflow_schedule')->insert([
            ['contract_id' => $id, 'schedule_version' => 1, 'due_date' => '2025-02-01', 'principal_due' => 500, 'interest_due' => 50, 'created_at' => now(), 'updated_at' => now()],
            ['contract_id' => $id, 'schedule_version' => 1, 'due_date' => '2025-03-01', 'principal_due' => 500, 'interest_due' => 25, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function held(string $candidate, float $amount, string $ext): int
    {
        return DB::table('fee_gl_unassigned')->insertGetId(['external_transaction_id' => $ext, 'transaction_date' => '2025-01-02', 'batch' => 'TRF 1', 'gl_code' => '4873',
            'amount' => $amount, 'narration' => 'By Trf Acme arrangement fees', 'reason' => 'NO_LOAN_IN_BATCH: no loan account is a leg of the batch', 'loans_in_batch' => '[]',
            'candidate_contract_id' => $candidate, 'candidate_evidence' => 'name and drawdown date', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_a_held_posting_naming_the_loan_blocks_it_until_tied_or_dismissed(): void
    {
        $this->contract('900000000101');
        $this->assertNotContains('FEE_HELD_FOR_LOAN', array_column((new EirReadinessService())->assess('900000000101')['issues'], 'code'));

        $h = $this->held('900000000101', 25, 'GL_04a:1');
        $issues = (new EirReadinessService())->assess('900000000101')['issues'];
        $this->assertContains('FEE_HELD_FOR_LOAN', array_column($issues, 'code'));
        $this->assertStringContainsString('25.00', collect($issues)->firstWhere('code', 'FEE_HELD_FOR_LOAN')['message']);
        $this->assertSame(1, DB::table('fee_gl_unassigned')->count(), 'nothing is allocated by the suggestion');

        try {
            HeldFeeEvidence::dismiss($h, '900000000101', 'short', 1);
            $this->fail('a dismissal without a reason was accepted');
        } catch (InvalidArgumentException) {
        }
        HeldFeeEvidence::dismiss($h, '900000000101', 'The narration names Acme Holdings, a different borrower.', 1);
        $this->assertNotContains('FEE_HELD_FOR_LOAN', array_column((new EirReadinessService())->assess('900000000101')['issues'], 'code'));
        $this->assertSame(1, DB::table('fee_gl_unassigned')->count(), 'the posting stays held for the right loan');
    }

    public function test_a_posting_that_arrives_after_the_calculation_stops_the_lock(): void
    {
        $this->contract('900000000102', ['calculation_status' => 'CALCULATED', 'eir_period' => 0.01, 'calculated_by' => 2]);
        $this->held('900000000102', 40, 'GL_04a:2');
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('cannot be locked');
        app(EirCalculationService::class)->lock('900000000102', 3);
    }

    public function test_a_locked_eir_with_later_fee_evidence_is_flagged_never_reopened(): void
    {
        $this->contract('900000000103', ['calculation_status' => 'LOCKED', 'eir_period' => 0.01, 'locked_at' => now()->subDay(), 'locked_by' => 1]);
        $reviews = app(EirLockReviewService::class);
        $this->assertSame(0, $reviews->detect(1));

        $this->held('900000000103', 15, 'GL_04a:3');
        DB::table('contract_fees')->insert(['contract_id' => '900000000103', 'fee_type' => 'legal', 'amount' => 10, 'classification_status' => 'PENDING',
            'source_system' => 'MAIIC Finance EIR assessment 2025', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame(2, $reviews->detect(1));
        $this->assertSame(0, $reviews->detect(1), 'the same evidence is never flagged twice');
        $this->assertSame('LOCKED', DB::table('contract_eir')->where('contract_id', '900000000103')->value('calculation_status'), 'a flag reopens nothing');

        $flag = $reviews->open()[0];
        try {
            $reviews->clear((int) $flag['id'], 1, 'The fee was considered at the lock.');
            $this->fail('the person who raised the flag cleared it');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('second person', $e->getMessage());
        }
        $reviews->clear((int) $flag['id'], 2, 'The fee was considered at the lock: it is in the input snapshot.');
        $this->assertCount(1, $reviews->open());

        $this->assertSame(1, $reviews->closeOnReopen('900000000103', 2));
        $this->assertSame([], $reviews->open());
    }

    private function fee(string $loan, string $type, float $amount, string $source, ?string $date): int
    {
        return DB::table('contract_fees')->insertGetId(['contract_id' => $loan, 'fee_type' => $type, 'amount' => $amount, 'source_system' => $source, 'transaction_date' => $date,
            'classification_status' => 'PENDING', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_precedence_is_per_fee_event_a_partial_source_never_suppresses_another_fee(): void
    {
        $gl = $this->fee('900000000201', 'arrangement', 4_800_000, \App\Services\Eir\FeeSourcePrecedence::SOURCE_GL, '2025-06-19');
        $losArr = $this->fee('900000000201', 'arrangement', 4_500_000, \App\Services\Eir\FeeSourcePrecedence::SOURCE_LOS, null);
        $losLegal = $this->fee('900000000201', 'legal', 2_866_000, \App\Services\Eir\FeeSourcePrecedence::SOURCE_LOS, null);

        $r = app(\App\Services\Eir\FeeSourcePrecedence::class)->apply(1);

        $this->assertSame('GL posting', DB::table('contract_fees')->where('id', $losArr)->value('superseded_by_source'), 'the same event: the source of record wins');
        $this->assertNull(DB::table('contract_fees')->where('id', $losLegal)->value('superseded_by_source'), 'a fee type the GL does not hold stands (it was suppressed per contract before R03)');
        $this->assertNull(DB::table('contract_fees')->where('id', $gl)->value('superseded_by_source'));
        $this->assertSame(0, $r['overlaps']);
        $d = DB::table('fee_source_differences')->first();
        $this->assertSame([$gl, $losArr, -300000.0], [(int) $d->record_fee_id, (int) $d->other_fee_id, (float) $d->difference], 'the difference is stored per event');
    }

    public function test_a_fee_of_another_period_is_an_overlap_that_waits_for_a_person(): void
    {
        $this->contract('900000000202');
        $this->fee('900000000202', 'arrangement', 1_000_000, \App\Services\Eir\FeeSourcePrecedence::SOURCE_GL, '2025-01-10');
        $later = $this->fee('900000000202', 'arrangement', 600_000, 'MAIIC Finance EIR assessment 2025', '2025-11-30'); // a second tranche's fee, outside the event window
        $precedence = app(\App\Services\Eir\FeeSourcePrecedence::class);

        $this->assertSame(1, $precedence->apply(1)['overlaps']);
        $row = DB::table('contract_fees')->where('id', $later)->first();
        $this->assertNull($row->superseded_by_source, 'never silently dropped');
        $this->assertNotNull($row->source_overlap);
        $this->assertContains('FEE_SOURCE_OVERLAP', array_column((new EirReadinessService())->assess('900000000202')['issues'], 'code'));

        $precedence->decide($later, 'SEPARATE', 'The second drawdown carried its own arrangement fee.', 2);
        $row = DB::table('contract_fees')->where('id', $later)->first();
        $this->assertSame([null, null], [$row->superseded_by_source, $row->source_overlap]);
        $this->assertNotContains('FEE_SOURCE_OVERLAP', array_column((new EirReadinessService())->assess('900000000202')['issues'], 'code'));

        DB::table('fee_source_decisions')->delete();
        $precedence->apply(1);
        $precedence->decide($later, 'SAME', 'Finance dated the drawdown fee to the second tranche by mistake.', 2);
        $this->assertSame('GL posting', DB::table('contract_fees')->where('id', $later)->value('superseded_by_source'));
    }

    public function test_the_fee_gl_gate_also_finds_a_month_the_extract_does_not_carry(): void
    {
        $movements = \Mockery::mock(\App\Services\Eir\TrialBalanceMovementService::class);
        $movements->shouldReceive('movement')->andReturnUsing(fn ($gl, $date) => ['movement' => match ($gl . ' ' . substr($date, 0, 7)) {
            '4871 2025-01' => 100.0, '4871 2025-02' => 250.0, '4871 2025-03' => 300.0, default => 0.0,
        }]);
        app()->instance(\App\Services\Eir\TrialBalanceMovementService::class, $movements);
        $gate = app(\App\Services\Ebanker\GlFeeBuildService::class)->gate([
            ['gl' => '4871', 'date' => '2025-01-15', 'amount' => 100.0],
            ['gl' => '4871', 'date' => '2025-03-15', 'amount' => 300.0],
        ]);
        $missing = array_values(array_filter($gate['months'], fn ($m) => $m['status'] === 'MISSING_IN_GL04'));
        $this->assertSame([['4871', '2025-02', 250.0]], array_map(fn ($m) => [$m['gl'], $m['month'], $m['trial_balance']], $missing));
        $this->assertSame(1, $gate['differ']);
        $this->assertSame(2, $gate['agree']);
    }

    public function test_a_flag_can_be_raised_by_hand_only_on_a_locked_eir(): void
    {
        $this->contract('900000000104');
        $reviews = app(EirLockReviewService::class);
        $this->expectException(InvalidArgumentException::class);
        $reviews->raise('900000000104', 'Finance workbook shows fees for this loan.', ['evidence' => 'workbook'], 1);
    }
}
