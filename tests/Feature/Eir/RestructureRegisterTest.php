<?php

namespace Tests\Feature\Eir;

use App\Services\Ebanker\LandingZoneReader;
use App\Services\Eir\RestructureCandidateService;
use App\Services\Eir\RestructureRegisterService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * The restructure register (spec v4 section 3.9, review item R10, phase P7):
 * candidates found in E-Banker's reschedule history are never confirmed
 * events; a person classifies, a different person approves; only a confirmed
 * event that changes the accounting gets schedule version N+1 and a
 * contract_modifications row.
 */
class RestructureRegisterTest extends TestCase
{
    protected $seed = false;

    private string $a = '000104430000082';

    protected function setUp(): void
    {
        parent::setUp();
        // these tests are of other behaviour; the evidence a workflow needs before approval is tested in ApprovalWorkflowsTest
        config(['approvals.require_evidence' => false]);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        foreach (['2026_10_08_000000_create_ebanker_landing_zone', '2026_10_09_000000_keep_quarantined_rows_in_landing_zone'] as $m) {
            (require base_path("database/migrations/{$m}.php"))->up();
        }
        Schema::create('audit_logs', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id')->nullable(); $t->string('action'); $t->string('entity_type'); $t->integer('entity_id')->nullable(); $t->string('scope')->nullable(); $t->string('reporting_period')->nullable(); $t->integer('rows_affected')->nullable(); $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->text('meta')->nullable(); $t->string('ip_address')->nullable(); $t->string('user_agent')->nullable(); $t->timestamps(); });
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->string('customer_name')->nullable(); $t->string('external_identity_id')->nullable(); $t->string('ifrs9stage_post_qualitative')->nullable(); $t->double('interest_rate')->nullable(); $t->double('carrying_amount')->default(0); });
        Schema::create('contract_eir', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id')->unique(); $t->string('rate_type')->default('FIXED'); $t->timestamps(); });
        Schema::create('contract_cashflow_schedule', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->smallInteger('schedule_version')->default(1); $t->date('effective_from')->nullable(); $t->date('due_date'); $t->double('principal_due')->default(0); $t->double('interest_due')->default(0); $t->double('fee_due')->default(0); $t->string('schedule_source')->default('IMPORTED'); $t->timestamps(); });
        (require base_path('database/migrations/2026_10_14_230000_restructure_register_and_modifications.php'))->up();
        DB::table('ebanker_loads')->insert(['id' => 1, 'pack_hash' => str_repeat('r', 64), 'pack_name' => 'fixture', 'manifest' => '{}', 'status' => 'LANDED', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('loan_books')->insert(['contract_id' => '104430000082', 'reporting_period' => '2026-06', 'customer_name' => 'Pinnacle', 'external_identity_id' => $this->a, 'ifrs9stage_post_qualitative' => '1']);
        DB::table('loan_books')->insert(['contract_id' => '104460000059', 'reporting_period' => '2026-06', 'customer_name' => 'BenCal', 'external_identity_id' => '000104460000059']);
        DB::table('contract_eir')->insert(['contract_id' => '104430000082', 'rate_type' => 'FIXED', 'created_at' => now(), 'updated_at' => now()]);
        // the loan was drawn on 5 January 2025
        $this->raw('P1_01', 'L1', $this->a, '2025-01-05', ['TRANTYPE' => '301', 'TRANSAMT' => '-300.00', 'AC_GLCODE' => '1050102', 'DELETE_FLAG' => 'N']);
    }

    private function raw(string $q, string $key, ?string $account, ?string $date, array $payload): void
    {
        DB::table('ebanker_raw_rows')->insert(['load_id' => 1, 'query_id' => $q, 'source_key' => $key, 'account' => $account, 'row_date' => $date,
            'payload' => json_encode($payload), 'row_hash' => md5($q . $key), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** A chart row (dates m/d/Y as E-Banker writes them). */
    private function chartRow(string $id, string $due, float $amount, float $principal, float $balanceAfter, string $created, ?string $replaced, string $det, int $serial, ?string $account = null): void
    {
        $account ??= $this->a;
        $p = ['INST_CHART_DET_ID' => $id, 'NEW_AC_NUMBER' => $account, 'INST_DET_ID' => $det, 'INST_SR_NO' => (string) $serial, 'INSTALLMENT_DATE' => date('n/j/Y', strtotime($due)),
            'INSTALLMENT_AMT' => number_format($amount, 2, '.', ''), 'PRIN_INSTALLMENT_AMT' => number_format($principal, 2, '.', ''), 'INTR_INSTALLMENT_AMT' => number_format($amount - $principal, 2, '.', ''),
            'EXP_BALANCE' => number_format($balanceAfter, 2, '.', ''), 'ACTIVE_FLAG' => 'Y', 'DELETE_FLAG' => $replaced === null ? 'N' : 'Y', 'TRANSACTION_DATE' => $created, 'OPERATION_DATE' => $replaced ?? '',
            'ENTER_USER_ID' => '55', 'PASS_USER_ID' => '18', 'VERIFY_FLAG' => 'Y'];
        $this->raw($replaced === null ? 'P2_10' : 'RS_02a', $id, $account, $due, $p);
    }

    /** Version 1 from 10 January 2026: three instalments of 100 retiring 270; replaced on 30 June 2026 by version 2, which re-spreads 300 over six instalments. */
    private function twoVersions(string $v2Created = '6/30/2026'): void
    {
        foreach ([['2026-07-19', 180], ['2026-08-19', 90], ['2026-09-19', 0]] as $i => [$due, $bal]) {
            $this->chartRow("v1-{$i}", $due, 100, 90, $bal, '1/10/2026', $v2Created, '10', $i + 1);
        }
        foreach (['2026-07-19', '2026-08-19', '2026-09-19', '2026-10-19', '2026-11-19', '2026-12-19'] as $i => $due) {
            $this->chartRow("v2-{$i}", $due, 60, 50, 250 - 50 * $i, $v2Created, null, '11', $i + 1);
        }
    }

    private function detector(): RestructureCandidateService
    {
        return new RestructureCandidateService(new LandingZoneReader());
    }

    /**
     * The detector reads the ledger once (bootstrap step 5i took about 30 minutes
     * at a query per account and date) and must see exactly what the per-account
     * read returned: a source key landed in two families, one dated after the
     * date, keeps the earlier one at that date; a later load wins; deleted, non-
     * loan GL, undated, superseded and quarantined rows are left out as before.
     */
    public function test_the_ledger_read_once_gives_what_the_per_account_read_gives_at_every_date(): void
    {
        DB::table('ebanker_loads')->insert([['id' => 2, 'pack_hash' => str_repeat('s', 64), 'pack_name' => 'later', 'manifest' => '{}', 'status' => 'LANDED', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'pack_hash' => str_repeat('q', 64), 'pack_name' => 'refused', 'manifest' => '{}', 'status' => 'QUARANTINED', 'created_at' => now(), 'updated_at' => now()]]);
        $post = fn (string $type, string $amount, string $gl = '1050102', string $deleted = 'N') => ['TRANTYPE' => $type, 'TRANSAMT' => $amount, 'AC_GLCODE' => $gl, 'DELETE_FLAG' => $deleted];
        $this->raw('P1_01', 'K1', $this->a, '2026-02-01', $post('305', '50.00'));
        $this->raw('M09_01', 'K1', $this->a, '2026-05-01', $post('305', '70.00'));           // the same key in another family, later
        $this->raw('P1_01', 'K2', $this->a, '2026-03-01', $post('305', '10.00', '1050102', 'Y')); // deleted
        $this->raw('P1_01', 'K3', $this->a, '2026-03-02', $post('305', '5.00', '4215'));    // a contra leg on an income GL
        $this->raw('P1_01', 'K4', $this->a, null, $post('305', '1.00'));                   // undated
        $this->raw('P1_01', 'K5', '000104460000059', '2026-01-15', $post('301', '-90.00'));
        DB::table('ebanker_raw_rows')->insert(['load_id' => 2, 'query_id' => 'P1_01', 'source_key' => 'K5', 'account' => '000104460000059', 'row_date' => '2026-01-16',
            'payload' => json_encode($post('301', '-95.00')), 'row_hash' => 'h2', 'version' => 2, 'created_at' => now(), 'updated_at' => now()]);   // a later load restates it
        DB::table('ebanker_raw_rows')->insert(['load_id' => 3, 'query_id' => 'P1_01', 'source_key' => 'K6', 'account' => $this->a, 'row_date' => '2026-01-20',
            'payload' => json_encode($post('305', '999.00')), 'row_hash' => 'h3', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);  // quarantined
        DB::table('ebanker_raw_rows')->insert(['load_id' => 1, 'query_id' => 'P1_01', 'source_key' => 'K7', 'account' => $this->a, 'row_date' => '2026-01-21', 'superseded_at' => now(),
            'payload' => json_encode($post('305', '888.00')), 'row_hash' => 'h4', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);  // superseded

        $zone = new LandingZoneReader();
        $reader = $zone->ledgerReader();
        $checked = 0;
        foreach ([$this->a, '000104460000059', '000999999999999'] as $account) {
            foreach ([null, '2024-12-31', '2025-01-05', '2026-01-15', '2026-02-01', '2026-03-01', '2026-04-30', '2026-05-01', '2026-12-31'] as $date) {
                $this->assertSame($zone->ledgerByAccount($date, $account)[$account] ?? [], $reader($account, $date), "{$account} at " . ($date ?? 'no date'));
                $checked++;
            }
        }
        $this->assertSame(27, $checked);
        $this->assertSame(['L1', 'K1'], array_column($reader($this->a, '2026-04-30'), 'source_key'));
        $this->assertSame('50.00', $reader($this->a, '2026-04-30')[1]['payload']['TRANSAMT'], 'at April the key is the February posting');
        $all = $reader($this->a, null);
        $this->assertSame(['K4', 'L1', 'K1'], array_column($all, 'source_key'), 'with no date the undated row is read, first');
        $this->assertSame('70.00', $all[2]['payload']['TRANSAMT'], 'with no date the key is the May posting');
        $this->assertSame('2026-01-16', $reader('000104460000059', null)[0]['row_date'], 'the later load wins');
    }

    public function test_a_chart_that_capitalises_arrears_off_a_rate_date_is_a_candidate_and_is_never_confirmed_by_the_detector(): void
    {
        $this->twoVersions();
        $summary = $this->detector()->detect();

        $e = DB::table('restructure_events')->where('source', 'CHART_REPLACED')->first();
        $this->assertNotNull($e);
        $this->assertSame('2026-06-30', $e->event_date);
        $this->assertSame('MODIFICATION', $e->suggested_class);
        $this->assertEqualsWithDelta(30, $e->capitalised_amount, 0.01, 'the new chart amortises 300 where the old expected 270, with nothing advanced in between');
        $this->assertSame('2026-09-19', $e->old_maturity);
        $this->assertSame('2026-12-19', $e->new_maturity);
        $this->assertSame('CANDIDATE', $e->status, 'a candidate is never a confirmed event (R10)');
        $this->assertNull($e->classification);
        $this->assertCount(6, json_decode($e->new_flows, true));
        $this->assertSame(1, $summary['found']);
    }

    public function test_a_new_chart_that_amortises_more_than_the_ledger_owes_cannot_be_booked(): void
    {
        $this->raw('P1_01', 'L3', $this->a, '2026-03-01', ['TRANTYPE' => '305', 'TRANSAMT' => '100.00', 'AC_GLCODE' => '1050102', 'DELETE_FLAG' => 'N']); // 100 repaid: 200 owed
        $this->twoVersions();
        $this->detector()->detect();

        $e = DB::table('restructure_events')->where('source', 'CHART_REPLACED')->first();
        $this->assertStringContainsString('the ledger owes MWK 200.00', $e->incomplete_reason);
        $this->expectException(RuntimeException::class);
        (new RestructureRegisterService())->propose($e->id, ['classification' => 'MODIFICATION', 'reason' => 'x'], 1);
    }

    public function test_a_regeneration_for_a_plr_change_that_keeps_the_balance_and_term_is_a_reset_not_a_candidate(): void
    {
        $this->raw('P2_06', 'PLR1', null, '2026-06-30', ['APPLI_FROM_DATE' => '6/30/2026', 'PLR_RATE' => '25.3', 'DELETE_FLAG' => 'N']);
        foreach ([['2026-07-19', 180], ['2026-08-19', 90], ['2026-09-19', 0]] as $i => [$due, $bal]) {
            $this->chartRow("v1-{$i}", $due, 100, 90, $bal, '1/10/2026', '6/30/2026', '10', $i + 1);
            $this->chartRow("v2-{$i}", $due, 102, 90, $bal, '6/30/2026', null, '11', $i + 1); // re-amortised at the new rate
        }
        $this->detector()->detect();

        $this->assertSame(0, DB::table('restructure_events')->count(), 'a B5.4.5 regeneration belongs on the rate-reset register');
    }

    public function test_a_regeneration_that_takes_in_a_tranche_is_a_drawdown_not_a_candidate(): void
    {
        $this->raw('P1_01', 'L2', $this->a, '2026-06-20', ['TRANTYPE' => '301', 'TRANSAMT' => '-30.00', 'AC_GLCODE' => '1050102', 'DELETE_FLAG' => 'N']);
        $this->twoVersions();
        $this->detector()->detect();

        $this->assertSame(0, DB::table('restructure_events')->where('source', 'CHART_REPLACED')->count());
    }

    public function test_two_dated_copies_that_differ_give_a_candidate_that_fails_closed_without_a_date(): void
    {
        $b = '000104460000059';
        foreach ([['RS_02b', 100.0], ['RS_02c', 120.0]] as [$q, $amount]) {
            $this->raw($q, "{$q}-1", $b, '2025-02-01', ['NEW_AC_NUMBER' => $b, 'INSTALLMENT_DATE' => '2/1/2025', 'INSTALLMENT_AMT' => (string) $amount, 'INST_SR_NO' => '1', 'INST_DET_ID' => '1', 'TRANSACTION_DATE' => '3/28/2024', 'ACTIVE_FLAG' => 'Y', 'DELETE_FLAG' => 'N']);
        }
        $this->detector()->detect();

        $e = DB::table('restructure_events')->where('source', 'CHART_COPY')->first();
        $this->assertNotNull($e);
        $this->assertNull($e->event_date);
        $this->assertStringContainsString('No effective date', $e->incomplete_reason);

        // it cannot be booked as a modification, but a person may still record it as a correction
        $svc = new RestructureRegisterService();
        try {
            $svc->propose($e->id, ['classification' => 'MODIFICATION', 'reason' => 'agreed restructure'], 1);
            $this->fail('A candidate with no date was proposed as a modification.');
        } catch (RuntimeException $ex) {
            $this->assertStringContainsString('no effective date', $ex->getMessage());
        }
        $svc->propose($e->id, ['classification' => 'CORRECTION', 'reason' => 'the copies differ only by a re-keyed amount'], 1);
        $svc->approve($e->id, 2);
        $this->assertSame('CONFIRMED', DB::table('restructure_events')->where('id', $e->id)->value('status'));
        $this->assertSame(0, DB::table('contract_modifications')->count(), 'a correction books nothing');
    }

    public function test_modification_log_edits_are_resolved_to_the_loan_and_a_rejected_one_is_ignored(): void
    {
        $this->twoVersions();
        $this->raw('P1_03', 'LM1', $this->a, null, ['ACLOAN_MST_ID' => '77', 'NEW_AC_NUMBER' => $this->a]);
        $log = fn (string $id, string $date, string $field, string $old, string $new, string $flag) => $this->raw('RS_02f', $id, null, null, ['MOD_LOG_DET_ID' => $id, 'TRANSACTION_DATE' => $date,
            'TABLE_NAME' => 'ACLOANMASTER', 'FIELD_NAME' => $field, 'OLD_VALUE' => $old, 'NEW_VALUE' => $new, 'PRIMARY_KEY_FIELD' => 'ACLOAN_MST_ID', 'PRIMARY_KEY_ID' => '77',
            'ACCEPTED_REJECTED_FLAG' => $flag, 'ENTER_USER_ID' => '19', 'PASS_USER_ID' => '18']);
        $log('900', '3/1/2026', 'EXPIRY_DATE', '19-SEP-26', '19-DEC-27', 'R');       // rejected: never took effect
        $log('901', '12/1/2024', 'EXPIRY_DATE', '19-SEP-26', '19-DEC-26', 'A');      // before the first drawdown
        $log('902', '3/2/2026', 'PERIOD_MONTHS', '48', '0', 'A');                    // the tenor written in years instead
        $log('903', '3/2/2026', 'PERIOD_YEARS', '0', '4', 'A');
        $this->detector()->detect();

        $log = DB::table('restructure_events')->where('source', 'MODIFICATION_LOG')->orderBy('event_date')->get();
        $this->assertCount(2, $log, 'the rejected edit is not a candidate');
        $this->assertSame('2024-12-01', $log[0]->event_date);
        $this->assertSame('CORRECTION', $log[0]->suggested_class);
        $this->assertStringContainsString('before the loan\'s first drawdown', $log[0]->suggestion_reason);
        $this->assertSame('CORRECTION', $log[1]->suggested_class, 'the same tenor in other units, and the chart did not change');
    }

    public function test_a_confirmed_modification_needs_two_people_and_loads_schedule_version_two(): void
    {
        $this->twoVersions();
        $this->detector()->detect();
        $id = (int) DB::table('restructure_events')->value('id');
        $svc = new RestructureRegisterService();

        $svc->propose($id, ['classification' => 'MODIFICATION', 'reason' => 'Arrears capitalised and term extended by agreement', 'board_approval_ref' => 'Board minute 14/2026',
            'board_approval_date' => '2026-06-25', 'qualitative_factors' => []], 1);
        try {
            $svc->approve($id, 1);
            $this->fail('The proposer approved their own classification.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('cannot approve', $e->getMessage());
        }
        $this->assertSame(0, DB::table('contract_cashflow_schedule')->count(), 'nothing reaches the accounts before approval');

        $svc->approve($id, 2);
        $m = DB::table('contract_modifications')->first();
        $this->assertSame('PENDING', $m->status, 'booked by the revenue run of its month');
        $this->assertSame('2026-06', $m->reporting_period);
        $this->assertSame(2, (int) $m->new_schedule_version);
        $this->assertSame(6, DB::table('contract_cashflow_schedule')->where('schedule_version', 2)->count());
        $this->assertSame('2026-06-30', DB::table('contract_cashflow_schedule')->where('schedule_version', 2)->value('effective_from'));

        // the RBM return reports it with its Board approval (directive 16(1))
        $rbm = $svc->restructuredBy('2026-06-30');
        $this->assertCount(1, $rbm);
        $this->assertSame('Board minute 14/2026', $rbm[0]['board_approval_ref']);
        $this->assertSame([], $svc->restructuredBy('2026-05-31'));

        // a reversal while the month is open removes the version and returns the candidate
        $svc->close($id, 2, 'reverse', 'Board minute was for another loan');
        $this->assertSame('CANDIDATE', DB::table('restructure_events')->where('id', $id)->value('status'));
        $this->assertSame('REVERSED', DB::table('contract_modifications')->value('status'));
        $this->assertSame(0, DB::table('contract_cashflow_schedule')->where('schedule_version', 2)->count());
    }

    public function test_a_restructure_in_the_migration_worksheet_is_a_candidate_and_once_confirmed_is_history_not_a_new_gain_or_loss(): void
    {
        $this->raw('CV_10', '1', $this->a, null, ['OP_BAL_DET_ID' => '1', 'ACCNO' => $this->a, 'SANCTION_DATE' => '04/14/2020', 'SANCTION_AMOUNT' => '300000000.00', 'DISBURS_AMT' => '-300000000.00',
            'RESTRUCTURE_DATE' => '04/01/2022', 'RESTRUCURE_AMT' => '331395845.48', 'INT_RATE' => '0.33', 'MATURITY_DATE' => '03/31/2034', 'TENOR_YRS' => '12.0000000000',
            'BALANCE_AMT' => '371380275.90', 'NEWDISBURS_AMT' => '-331395845.48']);
        $this->raw('CV_10', '2', '000104430000099', null, ['OP_BAL_DET_ID' => '2', 'ACCNO' => '000104430000099', 'RESTRUCTURE_DATE' => '', 'RESTRUCURE_AMT' => '']); // not restructured
        $this->detector()->detect();

        $e = DB::table('restructure_events')->where('source', 'OPENING_BAL')->first();
        $this->assertSame('2022-04-01', $e->event_date);
        $this->assertSame('MODIFICATION', $e->suggested_class);
        $this->assertSame('CANDIDATE', $e->status, 'MAIIC\'s worksheet is evidence; a person still confirms it');
        $this->assertSame('2034-03-31', $e->new_maturity);
        $this->assertEqualsWithDelta(331395845.48, $e->new_balance, 0.01);
        $this->assertEqualsWithDelta(33, $e->new_rate, 1e-9);
        $this->assertSame(1, DB::table('restructure_events')->where('source', 'OPENING_BAL')->count());

        $svc = new RestructureRegisterService();
        $svc->propose($e->id, ['classification' => 'MODIFICATION', 'reason' => 'Restructured 1 April 2022 per the migration worksheet', 'board_approval_ref' => 'Board minute 3/2022'], 1);
        $svc->approve($e->id, 2);
        $this->assertSame(0, DB::table('contract_modifications')->count(), 'before the take-on: the balance taken on already carries it');
        $this->assertSame(0, DB::table('contract_cashflow_schedule')->count());
        $this->assertCount(1, $svc->restructuredBy('2026-08-31'), 'and the RBM return reports it (16(1))');
    }

    public function test_a_candidate_a_person_acted_on_survives_a_new_detection(): void
    {
        $this->twoVersions();
        $this->detector()->detect();
        $id = (int) DB::table('restructure_events')->value('id');
        (new RestructureRegisterService())->propose($id, ['classification' => 'REVISED_ESTIMATE', 'reason' => 'prepayment re-spread'], 1);
        DB::table('ebanker_raw_rows')->where('query_id', 'P2_10')->delete(); // the evidence is no longer in the zone

        $summary = $this->detector()->detect();
        $this->assertSame('PROPOSED', DB::table('restructure_events')->where('id', $id)->value('status'));
        $this->assertSame(0, $summary['removed']);
    }

    public function test_the_rbm_section_d_reports_only_confirmed_restructures_and_names_a_missing_board_approval(): void
    {
        $this->twoVersions();
        $this->detector()->detect();
        $id = (int) DB::table('restructure_events')->value('id');
        $rbm = new \App\Services\Rbm\RbmReturnService(new LandingZoneReader());
        $section = (new \ReflectionMethod($rbm, 'restructured'))->invoke($rbm, '2026-06', ['104430000082' => ['balance' => 300.0, 'class' => 'Pass']]);
        $this->assertSame(0, $section['accounts'], 'a candidate is not reported as restructured');
        $this->assertSame(1, $section['candidates_awaiting_confirmation']);

        $svc = new RestructureRegisterService();
        $svc->propose($id, ['classification' => 'MODIFICATION', 'reason' => 'agreed'], 1);
        $svc->approve($id, 2);
        $section = (new \ReflectionMethod($rbm, 'restructured'))->invoke($rbm, '2026-06', ['104430000082' => ['balance' => 300.0, 'class' => 'Pass']]);
        $this->assertSame(1, $section['accounts']);
        $this->assertEquals(300, $section['balance']);
        $this->assertSame(1, $section['without_board_approval']);
        $this->assertStringContainsString('16(1)', $section['note']);
    }

    /** The proposer withdraws; a rejection is a second person's act (documentation audit, 14 October 2026). */
    public function test_the_proposer_cannot_reject_their_own_classification(): void
    {
        $this->twoVersions();
        $this->detector()->detect();
        $id = (int) DB::table('restructure_events')->value('id');
        $svc = new RestructureRegisterService();
        $svc->propose($id, ['classification' => 'MODIFICATION', 'reason' => 'Arrears capitalised and term extended by agreement', 'board_approval_ref' => 'Board minute 14/2026',
            'board_approval_date' => '2026-06-25', 'qualitative_factors' => []], 1);
        try {
            $svc->close($id, 1, 'reject', 'Not sure after all');
            $this->fail('The proposer rejected their own classification.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('withdraw it instead', $e->getMessage());
        }
        $this->assertSame('PROPOSED', DB::table('restructure_events')->where('id', $id)->value('status'));

        $svc->close($id, 2, 'reject', 'The board minute is for another loan');
        $this->assertSame('CANDIDATE', DB::table('restructure_events')->where('id', $id)->value('status'));
    }
}
