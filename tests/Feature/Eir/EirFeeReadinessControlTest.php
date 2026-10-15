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

    public function test_a_fee_type_the_gl_does_not_hold_waits_for_a_person_when_the_totals_differ(): void
    {
        $gl = $this->fee('900000000201', 'arrangement', 4_800_000, \App\Services\Eir\FeeSourcePrecedence::SOURCE_GL, '2025-06-19');
        $losArr = $this->fee('900000000201', 'arrangement', 4_500_000, \App\Services\Eir\FeeSourcePrecedence::SOURCE_LOS, null);
        $losLegal = $this->fee('900000000201', 'legal', 2_866_000, \App\Services\Eir\FeeSourcePrecedence::SOURCE_LOS, null);

        $r = app(\App\Services\Eir\FeeSourcePrecedence::class)->apply(1);

        $this->assertSame('GL posting', DB::table('contract_fees')->where('id', $losArr)->value('superseded_by_source'), 'the same event: the source of record wins');
        $legal = DB::table('contract_fees')->where('id', $losLegal)->first();
        $this->assertNull($legal->superseded_by_source, 'never silently dropped');
        $this->assertNotNull($legal->source_overlap, 'nor silently counted: the totals differ, so a person decides');
        $this->assertNull(DB::table('contract_fees')->where('id', $gl)->value('superseded_by_source'));
        $this->assertSame(1, $r['overlaps']);
        $d = DB::table('fee_source_differences')->first();
        $this->assertSame([$gl, $losArr, -300000.0], [(int) $d->record_fee_id, (int) $d->other_fee_id, (float) $d->difference], 'the difference is stored per event');
    }

    /** The Food Empire case of the 15 October impact report: the LOS splits the GL legal posting into legal and other. */
    public function test_lines_that_total_the_source_of_record_are_the_same_money_whatever_their_types(): void
    {
        $gl = \App\Services\Eir\FeeSourcePrecedence::SOURCE_GL;
        $los = \App\Services\Eir\FeeSourcePrecedence::SOURCE_LOS;
        $this->fee('900000000203', 'arrangement', 30_000_000, $gl, '2025-11-24');
        $this->fee('900000000203', 'legal', 15_790_600, $gl, '2025-11-24');
        $ids = [$this->fee('900000000203', 'arrangement', 30_000_000, $los, null), $this->fee('900000000203', 'legal', 10_000_000, $los, null),
            $this->fee('900000000203', 'other', 5_790_600, $los, null)];

        $r = app(\App\Services\Eir\FeeSourcePrecedence::class)->apply(1);

        $this->assertSame(['GL posting', 'GL posting', 'GL posting'], DB::table('contract_fees')->whereIn('id', $ids)->orderBy('id')->pluck('superseded_by_source')->all(), 'no part of it is counted twice');
        $this->assertSame([0, 3], [$r['overlaps'], $r['tied_by_total']]);
        $this->assertSame(0, DB::table('fee_source_differences')->count(), 'a different split by type is not a difference');
    }

    public function test_a_line_in_a_month_the_source_of_record_does_not_cover_stands(): void
    {
        $precedence = app(\App\Services\Eir\FeeSourcePrecedence::class);
        $precedence->recordCoverage(\App\Services\Eir\FeeSourcePrecedence::TIER_GL, '2024-10', '2025-06', 'GL fee build', 1, true);
        $this->fee('900000000204', 'arrangement', 1_000_000, \App\Services\Eir\FeeSourcePrecedence::SOURCE_GL, '2025-01-10');
        $before = $this->fee('900000000204', 'legal', 400_000, 'MAIIC Finance EIR assessment 2024', '2024-05-20');
        $after = $this->fee('900000000204', 'arrangement', 600_000, 'MAIIC Finance EIR assessment 2025', '2025-11-30');

        $r = $precedence->apply(1);

        foreach ([$before, $after] as $id) {
            $row = DB::table('contract_fees')->where('id', $id)->first();
            $this->assertSame([null, null], [$row->superseded_by_source, $row->source_overlap], 'the GL holds no evidence for that month: the workbook line is the only one');
        }
        $this->assertSame([2, 0], [$r['outside_coverage'], $r['overlaps']]);

        $precedence->recordCoverage(\App\Services\Eir\FeeSourcePrecedence::TIER_GL, '2024-10', '2025-12', 'GL fee build', 1, true);
        $this->assertSame(1, $precedence->apply(1)['overlaps'], 'once the GL covers November 2025, the line waits for a person');
        $this->assertSame(1, DB::table('fee_source_coverage')->count(), 'the GL build replaces its span');
    }

    /** The per-period rule whichever source wins: with Finance's workbook first, the GL fills the months its file does not cover. */
    public function test_with_finance_first_a_gl_fee_outside_the_workbook_months_still_counts(): void
    {
        DB::table('governance_settings')->where('key', 'fee_source_precedence')->update(['value' => \App\Services\Eir\FeeSourcePrecedence::FINANCE_FIRST]);
        $precedence = app(\App\Services\Eir\FeeSourcePrecedence::class);
        $this->assertSame(\App\Services\Eir\FeeSourcePrecedence::TIER_FINANCE, $precedence->order()[0]);
        $precedence->recordCoverage(\App\Services\Eir\FeeSourcePrecedence::TIER_FINANCE, '2025-01', '2025-12', 'Finance fee workbook 2025', 1);

        $workbook = $this->fee('900000000205', 'arrangement', 1_000_000, 'MAIIC Finance EIR assessment 2025', '2025-03-14');
        $glSame = $this->fee('900000000205', 'arrangement', 1_000_000, \App\Services\Eir\FeeSourcePrecedence::SOURCE_GL, '2025-03-14');
        $gl2026 = $this->fee('900000000205', 'legal', 500_000, \App\Services\Eir\FeeSourcePrecedence::SOURCE_GL, '2026-02-10');

        $r = $precedence->apply(1);

        $this->assertNull(DB::table('contract_fees')->where('id', $workbook)->value('superseded_by_source'), 'the workbook is the source of record');
        $this->assertSame('Finance workbook', DB::table('contract_fees')->where('id', $glSame)->value('superseded_by_source'), 'the same fee in a covered month: the workbook wins');
        $row = DB::table('contract_fees')->where('id', $gl2026)->first();
        $this->assertSame([null, null], [$row->superseded_by_source, $row->source_overlap], 'February 2026 is outside the workbook: the GL fee counts');
        $this->assertSame([1, 0], [$r['outside_coverage'], $r['overlaps']]);
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

    public function test_a_fee_line_on_the_wrong_loan_is_moved_with_the_reason_and_classified_again(): void
    {
        $this->contract('900000000301');
        $this->contract('900000000302');
        $id = $this->fee('900000000301', 'arrangement', 4_800_000, 'MAIIC Finance EIR assessment 2025', '2025-04-01');
        DB::table('contract_fees')->where('id', $id)->update(['classification_status' => 'REVIEWED', 'integral' => true, 'cashflow_direction' => 'RECEIVED']);
        $user = \App\Models\User::factory()->create();
        $move = function (array $body) use ($id, $user) {
            $this->actingAs($user);
            $request = \Illuminate\Http\Request::create('/eir-fee-classification/fee/' . $id . '/move', 'POST', $body);
            $request->setLaravelSession(app('session.store'));
            $request->setUserResolver(fn () => $user);
            app()->instance('request', $request);

            return app(\App\Http\Controllers\EirFeeClassificationController::class)->moveFee($request, \App\Models\ContractFee::findOrFail($id));
        };

        DB::table('contract_eir')->where('contract_id', '900000000302')->update(['locked_at' => now(), 'calculation_status' => 'LOCKED']);
        $move(['contract_id' => '900000000302', 'reason' => 'The rows carried each other fees.']);
        $this->assertSame('900000000301', DB::table('contract_fees')->where('id', $id)->value('contract_id'), 'a locked EIR is reopened first');

        DB::table('contract_eir')->where('contract_id', '900000000302')->update(['locked_at' => null, 'calculation_status' => 'PENDING']);
        $move(['contract_id' => '900000000302', 'transaction_date' => '2025-06-19', 'reason' => 'The rows carried each other fees.']);
        $fee = DB::table('contract_fees')->where('id', $id)->first();
        $this->assertSame(['900000000302', '2025-06-19', 'PENDING', null], [$fee->contract_id, substr((string) $fee->transaction_date, 0, 10), $fee->classification_status, $fee->reviewed_by]);
        $event = DB::table('eir_fee_classification_events')->where('contract_fee_id', $id)->where('action', 'REOPENED')->first();
        $this->assertStringContainsString('Moved from loan 900000000301 to loan 900000000302, dated 2025-06-19 (was 2025-04-01)', $event->reason);
    }
}
