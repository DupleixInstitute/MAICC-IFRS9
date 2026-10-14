<?php

namespace Tests\Feature\Ebanker;

use App\Models\GlAccountScope;
use App\Models\GlTrialBalanceLine;
use App\Services\Ebanker\ContractInputsBuildService;
use App\Services\Ebanker\GlFeeBuildService;
use App\Services\Eir\EirReadinessService;
use App\Services\Eir\FeeGlReconciliationService;
use App\Services\Eir\FeeSourcePrecedence;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Eir\Concerns\CreatesFeeSchema;
use Tests\Feature\Eir\Concerns\CreatesGovernanceSchema;
use Tests\TestCase;

/**
 * The GL fee route (spec v4 sections 7.8.3 to 7.8.5; results review of 13
 * October 2026, E3). Mphunzitsi SACCO's 2025 fees (30,000,000 arrangement and
 * 17,653,000 legal) were credited in the drawdown batches with a fee debit on
 * the loan, and none is in E-Banker's charges table, so the loan was locked at
 * an EIR with no fee. Malawi Police SACCO's fees of 7 May 2025 were credited
 * by transfer with no leg on any loan. The route ties the first to its loan
 * through the batch, holds the second with the reason and the loan its
 * narration and drawdown point to, counts a fee held in two sources once, and
 * makes the fee debits part of their drawdown, not tranches of their own.
 */
class GlFeeRouteTest extends TestCase
{
    use CreatesGovernanceSchema, CreatesFeeSchema;

    protected $seed = false;

    private const MPHUNZITSI = '000104430000085';
    private const POLICE = '000104430000072';
    private const KANINGA = '000104450000048';
    private const EDGE = '000104430000002';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        $this->createGovernanceSchema();
        $this->seedGovernanceDefaults();
        $this->createFeeSchema();
        foreach (['2026_10_08_000000_create_ebanker_landing_zone', '2026_10_09_000000_keep_quarantined_rows_in_landing_zone', '2026_09_24_000003_create_contract_disbursements', '2026_10_14_200000_eir_tranches_takeon_and_gl_fees'] as $m) {
            (require base_path("database/migrations/{$m}.php"))->up();
        }
        Schema::create('contract_eir', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id')->unique(); $t->string('instrument_type')->default('AMORTISED_LOAN'); $t->string('rate_type')->default('FIXED'); $t->string('origination_date')->nullable(); $t->double('drawn_amount')->nullable(); $t->integer('payments_per_year')->default(12); $t->string('frequency_source')->default('STATED'); $t->string('schedule_source')->nullable(); $t->string('schedule_approval_status')->default('APPROVED'); $t->double('schedule_amortising_balance')->nullable(); $t->string('locked_at')->nullable(); $t->timestamps(); });
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period')->nullable(); });
        Schema::create('contract_cashflow_schedule', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->integer('schedule_version')->default(1); $t->string('due_date'); $t->double('principal_due')->default(0); $t->double('interest_due')->default(0); $t->double('fee_due')->default(0); });
        Schema::create('gl_account_scope', function (Blueprint $t) { $t->increments('id'); $t->string('gl_code', 20); $t->string('gl_title'); $t->string('chart', 20)->default('EBANKER'); $t->string('quickbooks_code', 20)->nullable(); $t->string('statement', 2); $t->string('normal_balance', 2); $t->string('category', 30); $t->tinyInteger('eir_door')->nullable(); $t->boolean('in_eir_scope')->default(false); $t->string('portfolio', 30)->nullable(); $t->boolean('retired')->default(false); $t->string('notes', 500)->nullable(); $t->timestamps(); });
        Schema::create('gl_trial_balance_lines', function (Blueprint $t) { $t->increments('id'); $t->date('period'); $t->date('source_period_stamp')->nullable(); $t->string('gl_code', 20); $t->string('gl_title'); $t->decimal('debit', 20, 2)->default(0); $t->decimal('credit', 20, 2)->default(0); $t->string('basis', 12)->default('POSTCLOSING'); $t->string('source_file'); $t->string('source_sheet', 100)->nullable(); $t->timestamps(); });
        foreach (['4871' => 'Legal Fees', '4873' => 'Arrangement Fees'] as $code => $title) {
            GlAccountScope::create(['gl_code' => $code, 'gl_title' => $title, 'statement' => 'PL', 'normal_balance' => 'CR', 'category' => 'FEE_INCOME', 'in_eir_scope' => true, 'chart' => 'EBANKER']);
        }
        // the trial balance is year to date: August's movement on 4873 is 30,000,000 and on 4871 17,653,000
        foreach ([['4873', '2025-07-01', 10000000], ['4873', '2025-08-01', 40000000], ['4871', '2025-07-01', 5000000], ['4871', '2025-08-01', 22653000]] as [$gl, $p, $cr]) {
            GlTrialBalanceLine::create(['period' => $p, 'gl_code' => $gl, 'gl_title' => $gl, 'credit' => $cr, 'debit' => 0, 'basis' => 'POSTCLOSING', 'source_file' => 'test']);
        }
        DB::table('ebanker_loads')->insert(['id' => 1, 'pack_hash' => str_repeat('d', 64), 'pack_name' => 'fixture', 'manifest' => '{}', 'status' => 'LANDED', 'created_at' => now(), 'updated_at' => now()]);

        foreach ([[self::MPHUNZITSI, 'Mphunzitsi SACCO'], [self::POLICE, 'MALAWI POLICE SACCO'], [self::KANINGA, "Kaning'a General Dealer"], [self::EDGE, 'Edge View Academy Limited']] as [$acc, $name]) {
            $this->raw('P1_02', 'M' . $acc, $acc, '2024-07-01', ['NEW_AC_NUMBER' => $acc, 'GLCODE' => '1050102', 'ACCOUNT_NAME' => $name, 'STATUS_CODE' => 'A']);
            DB::table('contract_eir')->insert(['contract_id' => ltrim($acc, '0'), 'origination_date' => '2025-07-15', 'drawn_amount' => 1, 'schedule_source' => 'GENERATED', 'created_at' => now(), 'updated_at' => now()]);
        }
        // the ledger: Mphunzitsi's drawdown with its two fee debits, and a later tranche
        $this->ledger('100', self::MPHUNZITSI, '2025-08-08', -350000000, '301', 'To Trf Loan drawdown', 'DISB', '8');
        $this->ledger('101', self::MPHUNZITSI, '2025-08-08', -30000000, '301', 'To Trf Arrangement fees- Mphunzitsi SACCO', 'DISB', '9');
        $this->ledger('103', self::MPHUNZITSI, '2025-08-08', -17653000, '301', 'To Trf Drawdown-Mphunzitsi SACCO', 'DISB', '10');
        $this->ledger('200', self::MPHUNZITSI, '2025-11-28', -350000000, '301', 'To Trf Second Partial draw down', 'DISB', '11');
        // Malawi Police: one gross drawdown, the fees credited by transfer the same day
        $this->ledger('300', self::POLICE, '2025-05-07', -1046210000, '301', 'To Trf Loan drawdown', 'DISB', '2');
        // a take-on opening balance is the migration, not a drawdown
        $this->ledger('500', self::KANINGA, '2022-10-27', -24000000, '301', 'To Trf Opening Account Balance', 'TRF', '1', '7/31/2024');

        // GL_04b: every leg of the fee batches; GL_04a: the fee legs
        $this->leg('101', self::MPHUNZITSI, '1050102', '2025-08-08', -30000000, 'DR', '301', 'DISB', '9', 'To Trf Arrangement fees- Mphunzitsi SACCO');
        $this->leg('102', '000101890000001', '4873', '2025-08-08', 30000000, 'CR', '401', 'DISB', '9', 'By Trf Arrangement fees- Mphunzitsi SACCO', true);
        $this->leg('103', self::MPHUNZITSI, '1050102', '2025-08-08', -17653000, 'DR', '301', 'DISB', '10', 'To Trf Drawdown-Mphunzitsi SACCO');
        $this->leg('104', '000101870000001', '4871', '2025-08-08', 17653000, 'CR', '401', 'DISB', '10', 'By Trf Drawdown-Mphunzitsi SACCO', true);
        $this->leg('301', '000103270000001', '1001', '2025-05-07', -46210000, 'DR', '400', 'TRF', '3', 'To Trf Malawi Police Sacco arrangement &');
        $this->leg('302', '000101890000001', '4873', '2025-05-07', 30000000, 'CR', '401', 'TRF', '3', 'By Trf Malawi Police Sacco arrangement', true);
        $this->leg('303', '000101870000001', '4871', '2025-05-07', 16210000, 'CR', '401', 'TRF', '3', 'By Trf Malawi Police Sacco arrangement', true);
        // several loans in one batch: an equal debit ties one fee, the narration the other
        $this->leg('401', '000101870000001', '4871', '2024-11-05', 6528000, 'CR', '401', 'TRF', '11', "By Trf Loan repayment reversal for Kaning'a G", true);
        $this->leg('402', self::KANINGA, '1050201', '2024-11-05', -6528000, 'DR', '306', 'TRF', '11', "To Trf Loan repayment reversal for Kaning'a G");
        $this->leg('403', self::EDGE, '1050102', '2024-11-05', 10000000, 'CR', '305', 'TRF', '11', 'By Trf Loan repayment for Edge view');
        $this->leg('404', '000101890000001', '4873', '2024-11-05', 6111200, 'CR', '401', 'TRF', '11', "By Trf Kaning'a General Dealer's arrangement", true);
        // the year-end closing voucher is not fee income
        $this->leg('900', '000101890000001', '4873', '2025-12-31', -311342792, 'DR', '800', 'YRCLO', '46', 'To Closing Voucher of Financial Year', true);

        // E-Banker's charges table holds Kaning'a's arrangement fee at another figure, and Malawi Police's fees
        DB::table('contract_fees')->insert([
            ['contract_id' => '104450000048', 'fee_type' => 'arrangement', 'amount' => 6000000, 'source_system' => FeeSourcePrecedence::SOURCE_LOS, 'external_transaction_id' => 'P2_07:1', 'cashflow_direction' => 'RECEIVED', 'transaction_date' => null],
            ['contract_id' => '104430000072', 'fee_type' => 'arrangement', 'amount' => 30000000, 'source_system' => FeeSourcePrecedence::SOURCE_LOS, 'external_transaction_id' => 'P2_07:2', 'cashflow_direction' => 'RECEIVED', 'transaction_date' => null],
            ['contract_id' => '104430000072', 'fee_type' => 'legal', 'amount' => 16210000, 'source_system' => FeeSourcePrecedence::SOURCE_LOS, 'external_transaction_id' => 'P2_07:3', 'cashflow_direction' => 'RECEIVED', 'transaction_date' => null],
        ]);
    }

    private function raw(string $q, string $key, ?string $account, string $date, array $payload): void
    {
        DB::table('ebanker_raw_rows')->insert(['load_id' => 1, 'query_id' => $q, 'source_key' => $key, 'account' => $account, 'row_date' => $date, 'payload' => json_encode($payload), 'row_hash' => md5($q . $key), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function ledger(string $voucher, string $account, string $date, float $amount, string $type, string $narration, string $batchType, string $batch, string $operation = ''): void
    {
        $this->raw('P1_01', $voucher, $account, $date, ['CUMVOUCH_DET_ID' => $voucher, 'NEW_AC_NUMBER' => $account, 'AC_GLCODE' => '1050102', 'TRANSAMT' => (string) $amount, 'TRANTYPE' => $type,
            'PARTICULARS' => $narration, 'BATCH_TYPE' => $batchType, 'BATCH_NUMBER' => $batch, 'OPERATION_DATE' => $operation, 'DELETE_FLAG' => 'N']);
    }

    private function leg(string $voucher, string $account, string $gl, string $date, float $amount, string $dbcr, string $type, string $batchType, string $batch, string $narration, bool $feeLeg = false): void
    {
        $payload = ['CUMVOUCH_DET_ID' => $voucher, 'AC_GLCODE' => $gl, 'CB_GLCODE' => $gl, 'NEW_AC_NUMBER' => $account, 'TRANSAMT' => (string) $amount, 'DBCR' => $dbcr, 'TRANTYPE' => $type,
            'BATCH_TYPE' => $batchType, 'BATCH_NUMBER' => $batch, 'PARTICULARS' => $narration, 'PASS_FLAG' => 'Y', 'DELETE_FLAG' => 'N'];
        $this->raw('GL_04b', $voucher, $account, $date, $payload);
        if ($feeLeg) {
            $this->raw('GL_04a', $voucher, $account, $date, $payload);
        }
    }

    private function build(): array
    {
        return app(GlFeeBuildService::class)->build(1);
    }

    public function test_fees_are_tied_to_their_loans_through_the_batch_and_the_rest_held_with_the_reason(): void
    {
        $r = $this->build();

        $this->assertSame(6, $r['legs'], 'the closing voucher is not fee income');
        $this->assertSame(4, $r['assigned']['lines']);
        $this->assertEquals(['one loan in the batch' => 2, 'the loan\'s own debit of the same amount' => 1, 'the account name in the narration' => 1], $r['assigned']['by_rule']);
        $mph = DB::table('contract_fees')->where('contract_id', '104430000085')->orderBy('fee_type')->get();
        $this->assertSame(['arrangement', 'legal'], $mph->pluck('fee_type')->all());
        $this->assertEquals([30000000, 17653000], $mph->pluck('amount')->map(fn ($a) => (float) $a)->all());
        $this->assertTrue($mph->every(fn ($f) => $f->source_system === GlFeeBuildService::SOURCE && $f->classification_status === 'PENDING' && $f->integral === null
            && $f->transaction_date === '2025-08-08' && in_array($f->gl_account_ref, ['4871', '4873'], true)));
        $this->assertSame(['GL_04a:102', 'GL_04a:104'], $mph->pluck('external_transaction_id')->sort()->values()->all());
        $kaninga = DB::table('contract_fees')->where('contract_id', '104450000048')->where('source_system', GlFeeBuildService::SOURCE)->orderBy('external_transaction_id')->pluck('external_transaction_id')->all();
        $this->assertSame(['GL_04a:401', 'GL_04a:404'], $kaninga);

        // the transfer with no loan leg is held, with the loan its narration and that day's drawdown point to
        $this->assertSame(2, $r['held']['lines']);
        $this->assertSame(2, $r['held']['with_a_candidate']);
        $held = DB::table('fee_gl_unassigned')->orderBy('external_transaction_id')->get();
        $this->assertStringStartsWith('NO_LOAN_IN_BATCH', $held[0]->reason);
        $this->assertSame('104430000072', $held[0]->candidate_contract_id);
        $this->assertStringContainsString('MALAWI POLICE SACCO', $held[0]->candidate_evidence);
        $this->assertSame(0, DB::table('contract_fees')->where('source_system', GlFeeBuildService::SOURCE)->where('contract_id', '104430000072')->count());

        // the fee GL gate: August's GL_04a movement agrees with the trial balance; May has none to hold it to
        $august = collect($r['gate']['months'])->where('month', '2025-08')->values();
        $this->assertCount(2, $august);
        $this->assertTrue($august->every(fn ($m) => $m['status'] === 'AGREES'));
        $this->assertSame('NO_TB', collect($r['gate']['months'])->firstWhere('month', '2025-05')['status']);

        // building again loads nothing twice
        $again = $this->build();
        $this->assertSame(0, $again['loaded']['loaded_rows']);
        $this->assertSame(4, DB::table('contract_fees')->where('source_system', GlFeeBuildService::SOURCE)->count());
    }

    public function test_one_fee_one_source_the_lower_source_is_superseded_and_the_difference_named(): void
    {
        $r = $this->build();

        $los = DB::table('contract_fees')->where('external_transaction_id', 'P2_07:1')->first();
        $this->assertSame(FeeSourcePrecedence::TIER_GL, $los->superseded_by_source);
        $this->assertNotNull($los->superseded_at);
        $exception = collect($r['precedence']['exceptions'])->firstWhere('contract_id', '104450000048');
        $this->assertSame('arrangement', $exception['fee_type']);
        $this->assertEquals(6111200, $exception['amount_of_record']);
        $this->assertEquals(6000000, $exception['other_amount']);
        // Malawi Police has no GL line on its loan, so the charges table stays the source
        $this->assertSame(0, DB::table('contract_fees')->where('contract_id', '104430000072')->whereNotNull('superseded_by_source')->count());

        // a superseded line neither blocks the EIR nor enters it, and the fee GL check counts the fee once
        $issues = array_column(app(EirReadinessService::class)->assess('104450000048')['issues'], 'message', 'code');
        $this->assertStringStartsWith('2 fee/cost line(s)', $issues['FEE_CLASSIFICATION_PENDING']);
        $year = app(FeeGlReconciliationService::class)->reconcile([2024])['years'][2024];
        $this->assertEquals(6111200, $year['gls']['4873']['imported']);

        // a later precedence that puts the charges table first restores it and supersedes the GL lines
        DB::table('governance_settings')->where('key', 'fee_source_precedence')->update(['value' => FeeSourcePrecedence::LOS_FIRST]);
        $flip = app(FeeSourcePrecedence::class)->apply(1);
        $this->assertNull(DB::table('contract_fees')->where('external_transaction_id', 'P2_07:1')->value('superseded_by_source'));
        $this->assertSame(FeeSourcePrecedence::TIER_LOS, DB::table('contract_fees')->where('external_transaction_id', 'GL_04a:404')->value('superseded_by_source'));
        $this->assertGreaterThan(0, $flip['restored_lines']);
    }

    public function test_a_held_fee_is_tied_by_a_person_and_stays_tied(): void
    {
        $this->build();
        $held = DB::table('fee_gl_unassigned')->where('external_transaction_id', 'GL_04a:302')->first();

        $r = app(GlFeeBuildService::class)->assignHeld($held->id, '104430000072', 1, 'Narration and drawdown of 7 May 2025, confirmed by Finance');
        $this->assertSame(1, $r['loaded_rows']);
        $tied = DB::table('contract_fees')->where('external_transaction_id', 'GL_04a:302')->first();
        $this->assertSame('104430000072', $tied->contract_id);
        $this->assertSame('PENDING', $tied->classification_status);
        $this->assertStringContainsString('tied by hand', $tied->description);
        // the GL line now outranks the charges table on this loan
        $this->assertSame(2, DB::table('contract_fees')->where('contract_id', '104430000072')->where('superseded_by_source', FeeSourcePrecedence::TIER_GL)->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'GL Fee Tied To Loan')->count());

        // a rebuild keeps the tie and does not hold the line again
        $this->build();
        $this->assertSame(0, DB::table('fee_gl_unassigned')->where('external_transaction_id', 'GL_04a:302')->count());
        $this->assertSame(1, DB::table('contract_fees')->where('external_transaction_id', 'GL_04a:302')->count());

        $this->expectExceptionMessage('at least 10 characters');
        app(GlFeeBuildService::class)->assignHeld(DB::table('fee_gl_unassigned')->value('id'), '104430000072', 1, 'yes');
    }

    /** Spec 7.8.4: a fee debit is the fee capitalised into its drawdown, not a tranche; the take-on opening is not a drawdown. */
    public function test_fee_debits_join_their_drawdown_and_the_takeon_opening_is_not_a_tranche(): void
    {
        DB::table('contract_disbursements')->insert(['contract_id' => '104430000085', 'tranche_no' => 9, 'disbursement_date' => '2025-08-08', 'amount' => 30000000, 'reference' => 'CUMVOUCH:101',
            'source_system' => 'MAIIC_DISBURSEMENTS', 'external_transaction_id' => 'stale', 'created_at' => now(), 'updated_at' => now()]);

        $r = app(ContractInputsBuildService::class)->disbursements();

        $mph = DB::table('contract_disbursements')->where('contract_id', '104430000085')->orderBy('disbursement_date')->get();
        $this->assertCount(2, $mph, 'two drawdowns: the fee debits are not tranches, and the stale row of an earlier build is gone');
        $this->assertEquals(397653000, $mph[0]->amount);
        $this->assertEquals(47653000, $mph[0]->capitalised_fees);
        $this->assertStringContainsString('fees 101+103', $mph[0]->reference);
        $this->assertEquals(350000000, $mph[1]->amount);
        $this->assertNull($mph[1]->capitalised_fees);
        $this->assertSame(0, DB::table('contract_disbursements')->where('contract_id', '104450000048')->count());
        $this->assertSame(1, DB::table('contract_disbursements')->where('contract_id', '104430000072')->count());
        $this->assertSame(1, $r['result']['fee_debits_capitalised']);

        // a rerun rebuilds the same register
        app(ContractInputsBuildService::class)->disbursements();
        $this->assertSame(3, DB::table('contract_disbursements')->count());
        $this->assertEquals(47653000, DB::table('contract_disbursements')->where('contract_id', '104430000085')->sum('capitalised_fees'));
    }

    /** Spec 6.4: the fee GL gate at the door. A pack whose GL_04a month differs from the trial balance is refused; a month with no trial balance yet is listed, not refused. */
    public function test_the_fee_gl_gate_refuses_a_pack_whose_fee_movement_differs_from_the_trial_balance(): void
    {
        $gates = new \App\Services\Ebanker\PackGates(new \App\Services\Ebanker\LandingZoneReader(), app(\App\Services\Ebanker\PackLandingService::class));
        $query = (object) ['query_id' => 'GL_04a'];
        $row = fn ($voucher, $gl, $date, $amount) => ['CUMVOUCH_DET_ID' => $voucher, 'AC_GLCODE' => $gl, 'CB_GLCODE' => $gl, 'NEW_AC_NUMBER' => '000101890000001', 'TRANSACTION_DATE' => $date,
            'TRANSAMT' => (string) $amount, 'TRANTYPE' => '401', 'BATCH_TYPE' => 'DISB', 'BATCH_NUMBER' => '9', 'PASS_FLAG' => 'Y', 'DELETE_FLAG' => 'N'];

        // a re-pull of August that agrees (the pack's rows over the landed ones)
        $same = $gates->acrossPack([['file' => 'GL_04a.csv', 'query' => $query, 'headers' => [], 'rows' => [$row('102', '4873', '2025-08-08', 30000000)]]], 'Y-m-d')['fee_gl'];
        $this->assertSame('PASS', $same['result']);
        $this->assertSame('ERROR', $same['level']);

        // a pack that carries 1,000,000 more of arrangement fees in August than the trial balance moved
        $more = $gates->acrossPack([['file' => 'GL_04a.csv', 'query' => $query, 'headers' => [], 'rows' => [$row('102', '4873', '2025-08-08', 30000000), $row('105', '4873', '2025-08-20', 1000000)]]], 'Y-m-d')['fee_gl'];
        $this->assertSame('FAIL', $more['result']);
        $this->assertStringContainsString('fee GL 4873, 2025-08', $more['failures'][0]);

        // a month whose trial balance has not landed is listed and checked again by the build
        $noTb = $gates->acrossPack([['file' => 'GL_04a.csv', 'query' => $query, 'headers' => [], 'rows' => [$row('700', '4873', '2026-10-05', 500000)]]], 'Y-m-d')['fee_gl'];
        $this->assertSame('SKIPPED', $noTb['result']);
        $this->assertSame(['2026-10'], $noTb['months_without_tb']);
    }

    /** One GL_07 leg (pack 5 RUN 3b: every leg of the batches touching GL 2010). */
    private function gl2010(string $voucher, string $account, string $gl, string $date, float $amount, string $batch, string $narration): void
    {
        $this->raw('GL_07', $voucher, $account, $date, ['CUMVOUCH_DET_ID' => $voucher, 'AC_GLCODE' => $gl, 'CB_GLCODE' => $gl, 'NEW_AC_NUMBER' => $account, 'TRANSAMT' => (string) $amount,
            'TRANTYPE' => $amount < 0 ? '400' : '401', 'BATCH_TYPE' => 'TRF', 'BATCH_NUMBER' => $batch, 'PARTICULARS' => $narration, 'PASS_FLAG' => 'Y', 'DELETE_FLAG' => 'N']);
    }

    /**
     * Pack 5 results review, RUN 3: in October 2024 a drawdown was posted loan debit / GL 2010 credit,
     * and a second batch paid 2010 out to the bank net of the fee and credited the fee GL. The fee
     * batch has no loan leg; GL_07 ties it to the loan drawn into 2010 by the amount the batch clears.
     * The line lands PENDING (never reviewed by the build), a second build loads nothing twice, a line
     * a person tied by hand stays as tied, and a batch whose 2010 amount two loans drew stays held.
     */
    public function test_a_fee_paid_out_through_gl_2010_is_tied_to_the_loan_drawn_into_2010(): void
    {
        $mafeni = '000104460000104'; $agwenda = '000104430000064'; $twinA = '000104430000090'; $twinB = '000104430000091';
        foreach ([[$mafeni, 'Mafeni'], [$agwenda, 'Agwenda'], [$twinA, 'Twin A'], [$twinB, 'Twin B']] as [$acc, $name]) {
            $this->raw('P1_02', 'M' . $acc, $acc, '2024-07-01', ['NEW_AC_NUMBER' => $acc, 'GLCODE' => '1050202', 'ACCOUNT_NAME' => $name, 'STATUS_CODE' => 'A']);
            DB::table('contract_eir')->insert(['contract_id' => ltrim($acc, '0'), 'origination_date' => '2024-10-03', 'drawn_amount' => 1, 'schedule_source' => 'GENERATED', 'created_at' => now(), 'updated_at' => now()]);
        }
        // Mafeni: drawn into 2010 (TRF 17), then 2010 paid out net of the 4,250,000 fee (TRF 25)
        $this->gl2010('25800', $mafeni, '1050202', '2024-10-03', -200000000, '17', '');
        $this->gl2010('25805', '000105310000001', '2010', '2024-10-03', 200000000, '17', 'Working Capital Loan');
        $this->gl2010('25792', '000103270000001', '1001', '2024-10-03', 195750000, '25', '');
        $this->gl2010('25795', '000101890000001', '4873', '2024-10-03', 4250000, '25', 'Mafeni');
        $this->gl2010('25804', '000105310000001', '2010', '2024-10-03', -200000000, '25', 'loan draw down Mafeni');
        $this->leg('25792', '000103270000001', '1001', '2024-10-03', 195750000, 'DR', '401', 'TRF', '25', '');
        $this->leg('25795', '000101890000001', '4873', '2024-10-03', 4250000, 'CR', '401', 'TRF', '25', 'Mafeni', true);
        // Agwenda: two fees in one clearing batch (TRF 204) of the drawdown in TRF 189
        $this->gl2010('26260', $agwenda, '1050102', '2024-10-23', -21295000, '189', '');
        $this->gl2010('26298', '000105310000001', '2010', '2024-10-23', 21295000, '189', 'Working Capital Loan');
        $this->gl2010('26299', '000105310000001', '2010', '2024-10-23', -21295000, '204', 'loan draw down');
        $this->gl2010('26310', '000101870000001', '4871', '2024-10-23', 1205000, '204', 'Agwenda');
        $this->gl2010('26311', '000101890000001', '4873', '2024-10-23', 1500000, '204', 'Agwenda');
        $this->leg('26310', '000101870000001', '4871', '2024-10-23', 1205000, 'CR', '401', 'TRF', '204', 'Agwenda', true);
        $this->leg('26311', '000101890000001', '4873', '2024-10-23', 1500000, 'CR', '401', 'TRF', '204', 'Agwenda', true);
        // two loans drew 9,000,000 into 2010 the same day: the clearing batch cannot say whose fee it is
        $this->gl2010('27001', $twinA, '1050102', '2024-10-28', -9000000, '300', '');
        $this->gl2010('27002', '000105310000001', '2010', '2024-10-28', 9000000, '300', 'Working Capital Loan');
        $this->gl2010('27003', $twinB, '1050102', '2024-10-28', -9000000, '301', '');
        $this->gl2010('27004', '000105310000001', '2010', '2024-10-28', 9000000, '301', 'Working Capital Loan');
        $this->gl2010('27005', '000105310000001', '2010', '2024-10-28', -9000000, '302', 'loan draw down');
        $this->gl2010('27006', '000101890000001', '4873', '2024-10-28', 450000, '302', 'arrangement');
        $this->leg('27006', '000101890000001', '4873', '2024-10-28', 450000, 'CR', '401', 'TRF', '302', 'arrangement', true);

        $r = $this->build();

        $this->assertSame(3, $r['assigned']['by_rule'][GlFeeBuildService::RULE_CLEARING]);
        $m = DB::table('contract_fees')->where('external_transaction_id', 'GL_04a:25795')->first();
        $this->assertSame('104460000104', $m->contract_id);
        $this->assertEquals(4250000, (float) $m->amount);
        $this->assertSame('PENDING', $m->classification_status, 'the build ties the line; a person classifies and reviews it');
        $this->assertStringContainsString('GL 2010', $m->description);
        $this->assertEquals([1205000, 1500000], DB::table('contract_fees')->where('contract_id', '104430000064')->orderBy('amount')->pluck('amount')->map(fn ($a) => (float) $a)->all());
        $this->assertSame(0, DB::table('fee_gl_unassigned')->whereIn('external_transaction_id', ['GL_04a:25795', 'GL_04a:26310', 'GL_04a:26311'])->count());
        $twin = DB::table('fee_gl_unassigned')->where('external_transaction_id', 'GL_04a:27006')->first();
        $this->assertNotNull($twin, 'two loans drew the amount the batch clears: held, not guessed');
        $this->assertStringContainsString('2 loans were drawn into GL 2010', $twin->reason);

        // a second build loads nothing twice
        $this->assertSame(0, $this->build()['loaded']['loaded_rows']);
        $this->assertSame(1, DB::table('contract_fees')->where('external_transaction_id', 'GL_04a:25795')->count());

        // a line a person tied by hand before the route existed stays as tied, once
        DB::table('contract_fees')->where('external_transaction_id', 'GL_04a:26311')->update(['contract_id' => '104430000090', 'description' => 'tied by hand']);
        $this->build();
        $this->assertSame(1, DB::table('contract_fees')->where('external_transaction_id', 'GL_04a:26311')->count());
        $this->assertSame('104430000090', DB::table('contract_fees')->where('external_transaction_id', 'GL_04a:26311')->value('contract_id'));
    }
}
