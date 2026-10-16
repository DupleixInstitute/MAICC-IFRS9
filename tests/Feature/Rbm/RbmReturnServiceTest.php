<?php

namespace Tests\Feature\Rbm;

use App\Services\Ebanker\LandingZoneReader;
use App\Services\Rbm\RbmReturnService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The provisional RBM return (directive s.17): the directive's day bands by
 * term of facility; the minimum provision per class against the IFRS 9
 * allowance with the higher of the two; interest in suspense as the
 * contractual interest posted on non-performing facilities over the EIR
 * interest on the net basis.
 */
class RbmReturnServiceTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        (require base_path('database/migrations/2026_10_08_000000_create_ebanker_landing_zone.php'))->up();
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('customer_name')->nullable(); $t->string('product_group')->nullable(); $t->string('product_code')->nullable(); $t->string('reporting_period'); $t->integer('tenor')->default(0); $t->integer('overdue_days')->default(0); $t->date('overdue_principal_date')->nullable(); $t->decimal('carrying_amount', 20, 2)->default(0); $t->decimal('principal_balance', 20, 2)->default(0); $t->decimal('interest_to_date', 20, 2)->nullable(); $t->decimal('ecl_value', 20, 2)->nullable(); $t->decimal('ecl_value_discounted', 20, 2)->nullable(); $t->decimal('ecl_discounting_effect', 20, 2)->nullable(); $t->string('ecl_discount_status')->nullable(); $t->string('ifrs9stage_post_qualitative')->nullable(); $t->string('contract_status')->nullable(); });
        Schema::create('gl_trial_balance_lines', function (Blueprint $t) { $t->increments('id'); $t->date('period'); $t->string('gl_code'); $t->string('gl_title')->nullable(); $t->decimal('debit', 20, 2)->default(0); $t->decimal('credit', 20, 2)->default(0); $t->string('basis')->nullable(); $t->string('source_file')->nullable(); });
        Schema::create('eir_amortisation', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->decimal('interest_accrued', 20, 2); });
        DB::table('ebanker_loads')->insert(['id' => 1, 'pack_hash' => str_repeat('e', 64), 'pack_name' => 'fixture', 'manifest' => '{}', 'status' => 'LANDED', 'created_at' => now(), 'updated_at' => now()]);
        $rows = [
            ['A', 12, 0, 1000, 0],      // short-term, current: pass
            ['B', 12, 100, 1000, 100],  // short-term, 100 days: substandard (20 percent)
            ['C', 36, 100, 1000, 0],    // medium-term, 100 days: special mention (5 percent)
            ['D', 36, 400, 1000, 600],  // medium-term, 400 days: doubtful (50 percent)
            ['E', 12, 400, 1000, 1000], // short-term, 400 days: loss (100 percent)
        ];
        foreach ($rows as [$id, $tenor, $dpd, $ca, $ecl]) {
            DB::table('loan_books')->insert(['contract_id' => $id, 'reporting_period' => '2026-08', 'product_code' => '1050101', 'tenor' => $tenor, 'overdue_days' => $dpd, 'carrying_amount' => $ca, 'principal_balance' => $ca, 'ecl_value' => $ecl, 'ifrs9stage_post_qualitative' => $dpd > 90 ? '3' : '1']);
        }
        // contractual interest posted on B in August (120 charged) against 40 recognised on the net basis
        DB::table('ebanker_raw_rows')->insert(['load_id' => 1, 'query_id' => 'P1_01', 'source_key' => '1', 'account' => '000000000000B', 'row_date' => '2026-08-31', 'payload' => json_encode(['TRANSAMT' => '-120.00', 'TRANTYPE' => '303', 'AC_GLCODE' => '1050101', 'DELETE_FLAG' => 'N']), 'row_hash' => 'x', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('eir_amortisation')->insert(['contract_id' => 'B', 'reporting_period' => '2026-08', 'interest_accrued' => 40]);
    }

    public function test_the_directive_bands_by_term(): void
    {
        // Gazette section 10 (pages 683 and 685): short-term 30/90/180/365; medium and long 90/180/365/746
        foreach ([[30, 'Pass'], [31, 'Special mention'], [90, 'Special mention'], [91, 'Substandard'], [180, 'Substandard'], [181, 'Doubtful'], [365, 'Doubtful'], [366, 'Loss']] as [$dpd, $class]) {
            $this->assertSame($class, RbmReturnService::classify($dpd, 12), "short-term {$dpd} days");
        }
        foreach ([[30, 'Pass'], [90, 'Pass'], [91, 'Special mention'], [180, 'Special mention'], [181, 'Substandard'], [365, 'Substandard'], [366, 'Doubtful'], [746, 'Doubtful'], [747, 'Loss']] as [$dpd, $class]) {
            $this->assertSame($class, RbmReturnService::classify($dpd, 36), "medium/long-term {$dpd} days");
        }
    }

    public function test_the_return_fills_the_sections_from_the_book(): void
    {
        $r = (new RbmReturnService(new LandingZoneReader()))->build('2026-08');
        $this->assertSame('PROVISIONAL', $r['status']);
        $classes = array_column($r['sections']['A_classification'], 'total', 'class');
        $this->assertSame(1, $classes['Pass']['accounts']);
        $this->assertSame(1, $classes['Special mention']['accounts']);
        $this->assertSame(1, $classes['Substandard']['accounts']);
        $this->assertSame(1, $classes['Doubtful']['accounts']);
        $this->assertSame(1, $classes['Loss']['accounts']);
        $this->assertEqualsWithDelta(0.6, $r['npl_ratio'], 1e-6);
        $b = array_column($r['sections']['B_provisions'], null, 'class');
        $this->assertEquals(50, $b['Special mention']['minimum_provision']);
        $this->assertEquals(200, $b['Substandard']['minimum_provision']);
        $this->assertEquals(100, $b['Substandard']['shortfall']);      // held 100 against 200
        $this->assertEquals(0, $b['Doubtful']['shortfall']);           // held 600 against 500
        $this->assertEquals(1750, $r['sections']['B_totals']['minimum_provision']);
        $this->assertEquals(1700, $r['sections']['B_totals']['ifrs9_allowance']);
        $this->assertEquals(1750, $r['sections']['B_totals']['higher_of_the_two']);
        $c = $r['sections']['C_interest_in_suspense'];
        $this->assertEquals(120, $c['interest_posted_in_period']);
        $this->assertEquals(40, $c['interest_recognised_eir_net_basis']);
        $this->assertEquals(80, $c['interest_in_suspense']);
        $this->assertSame('Substandard', $r['per_loan']['B']['class']);
        $this->assertStringContainsString('PROVISIONAL', $r['notes'][0]);
    }

    /** The IFRS 9 allowance is the booked one: discounted where the engine discounted the loan; a loan without a discounted figure is counted and named (built 14 October 2026). */
    public function test_the_allowance_is_the_booked_discounted_allowance_and_a_gap_is_named(): void
    {
        // D (doubtful, 1,000) is discounted to 450 from 600; E (loss) keeps no discounted figure
        DB::table('loan_books')->where('contract_id', 'D')->update(['ecl_value_discounted' => 450, 'ecl_discounting_effect' => 160, 'ecl_discount_status' => 'CALCULATED_TIME_PHASED']);
        DB::table('loan_books')->where('contract_id', 'E')->update(['ecl_discount_status' => 'TIME_PHASED_UNRESOLVED']);
        $r = (new RbmReturnService(new LandingZoneReader()))->build('2026-08');
        $b = array_column($r['sections']['B_provisions'], null, 'class');
        $this->assertEquals(450, $b['Doubtful']['ifrs9_allowance']);   // not the undiscounted 600
        $this->assertEquals(50, $b['Doubtful']['shortfall']);          // 500 minimum against 450 held
        $this->assertEquals(1550, $r['sections']['B_totals']['ifrs9_allowance']);
        $basis = $r['sections']['B_allowance_basis'];
        $this->assertSame('mixed', $basis['status']);
        $this->assertSame(1, $basis['bases']['discounted']['loans']);
        $this->assertSame(2, $basis['bases']['undiscounted']['loans']);   // B and E; A and C carry a nil ECL
        $this->assertEquals(1100, $basis['reconciliation']['not_booked']);
        // undiscounted 1,700, plus the projection 450 + 160 - 600 = 10, less the discounting 160 = the 1,550 shown
        $this->assertEquals(1700, $basis['reconciliation']['undiscounted']);
        $this->assertEquals(10, $basis['reconciliation']['projection']);
        $this->assertEquals(-160, $basis['reconciliation']['discounting_effect']);
        $this->assertEquals(1550, $basis['reconciliation']['shown']);
        $this->assertStringContainsString('no discounted allowance', $basis['message']);
    }

    /** Paragraph 12: a loss facility is written off in the quarter after the one it became loss in; the list names each with its age (built 14 October 2026). */
    public function test_loss_facilities_are_listed_with_the_quarter_they_must_be_written_off_by(): void
    {
        // E (short-term) was loss from February 2026 (366 days by then), and is overdue for write-off at August (Q1 + one quarter = 30 June)
        foreach (['2026-01' => 300, '2026-02' => 370, '2026-03' => 400, '2026-04' => 430, '2026-05' => 460, '2026-06' => 490, '2026-07' => 520] as $p => $dpd) {
            DB::table('loan_books')->insert(['contract_id' => 'E', 'reporting_period' => $p, 'product_code' => '1050101', 'tenor' => 12, 'overdue_days' => $dpd, 'carrying_amount' => 1000, 'principal_balance' => 1000]);
        }
        // F (medium-term) became loss in July 2026 (Q3): due by 31 December, not yet overdue
        foreach (['2026-06' => 700, '2026-07' => 750, '2026-08' => 780] as $p => $dpd) {
            DB::table('loan_books')->insert(['contract_id' => 'F', 'reporting_period' => $p, 'product_code' => '1050101', 'tenor' => 36, 'overdue_days' => $dpd, 'carrying_amount' => 500, 'principal_balance' => 500, 'ecl_value' => 400]);
        }
        $r = (new RbmReturnService(new LandingZoneReader()))->build('2026-08');
        $loss = $r['sections']['B_loss_write_offs'];
        $this->assertSame(2, $loss['facilities']);
        $this->assertEquals(1500, $loss['balance']);
        $rows = array_column($loss['rows'], null, 'contract_id');
        $this->assertSame('2026-02', $rows['E']['loss_since']);
        $this->assertFalse($rows['E']['loss_since_open']);
        $this->assertSame('2026-06-30', $rows['E']['write_off_due_by']);
        $this->assertSame('overdue', $rows['E']['status']);
        $this->assertSame(7, $rows['E']['months_classified_loss']);
        $this->assertSame('2026-07', $rows['F']['loss_since']);
        $this->assertSame('2026-12-31', $rows['F']['write_off_due_by']);
        $this->assertSame('due next quarter', $rows['F']['status']);
        $this->assertSame(1, $loss['overdue']);
    }

    /** Paragraph 13(1): the interest charged since each facility became non-performing, tied to GL 1320 (built 14 October 2026). */
    public function test_cumulative_interest_in_suspense_is_measured_and_tied_to_gl_1320(): void
    {
        // B became non-performing in July (substandard, 95 days); 50 charged in July and 120 in August, 30 received in August
        DB::table('loan_books')->insert(['contract_id' => 'B', 'reporting_period' => '2026-07', 'product_code' => '1050101', 'tenor' => 12, 'overdue_days' => 95, 'carrying_amount' => 900, 'principal_balance' => 900]);
        DB::table('loan_books')->insert(['contract_id' => 'B', 'reporting_period' => '2026-06', 'product_code' => '1050101', 'tenor' => 12, 'overdue_days' => 60, 'carrying_amount' => 850, 'principal_balance' => 850]);
        foreach ([['2', '2026-07-31', '-50.00', '303'], ['3', '2026-08-15', '30.00', '305'], ['4', '2026-06-30', '-45.00', '303']] as [$k, $d, $amt, $type]) {
            DB::table('ebanker_raw_rows')->insert(['load_id' => 1, 'query_id' => 'P1_01', 'source_key' => $k, 'account' => '000000000000B', 'row_date' => $d, 'payload' => json_encode(['TRANSAMT' => $amt, 'TRANTYPE' => $type, 'AC_GLCODE' => '1050101', 'DELETE_FLAG' => 'N']), 'row_hash' => 'x' . $k, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('gl_trial_balance_lines')->insert(['period' => '2026-08-01', 'gl_code' => '1320', 'gl_title' => 'Interest Suspense', 'debit' => 0, 'credit' => 100, 'basis' => 'POSTCLOSING', 'source_file' => 'TB Aug']);
        $c = (new RbmReturnService(new LandingZoneReader()))->build('2026-08')['sections']['C_interest_in_suspense'];
        $this->assertEquals(170, $c['cumulative_interest_charged_since_non_performing']);   // July and August, not June (performing then)
        $this->assertEquals(30, $c['cash_received_since_non_performing']);
        $this->assertEquals(100, $c['gl_1320_interest_suspense']);
        $this->assertEquals(70, $c['difference_cumulative_less_gl_1320']);
        $this->assertStringContainsString('does not split a repayment', $c['note']);   // the cash-basis interest is a named gap, not a figure
    }

    /** Finding F3 of workbook 4: the RBM Classification report classifies by the same bands by term as the return. */
    public function test_the_rbm_classification_report_agrees_with_the_return_on_every_loan(): void
    {
        // the edge of every band, on both terms
        foreach ([[12, 31], [12, 90], [12, 91], [12, 180], [12, 181], [12, 365], [12, 366], [36, 31], [36, 90], [36, 91], [36, 180], [36, 181], [36, 365], [36, 366], [36, 746], [36, 747]] as $i => [$tenor, $dpd]) {
            DB::table('loan_books')->insert(['contract_id' => 'X' . $i, 'reporting_period' => '2026-08', 'product_code' => '1050101', 'tenor' => $tenor, 'overdue_days' => $dpd, 'carrying_amount' => 1, 'principal_balance' => 1]);
        }
        $case = new \ReflectionMethod(\App\Http\Controllers\Reports\Ifrs9ReportsController::class, 'rbmClassCase');
        $sql = $case->invoke(app(\App\Http\Controllers\Reports\Ifrs9ReportsController::class));
        $rows = DB::table('loan_books')->where('reporting_period', '2026-08')->selectRaw("contract_id, tenor, overdue_days, {$sql} rbm")->get();
        $this->assertCount(21, $rows);
        foreach ($rows as $row) {
            $this->assertSame(strtolower(RbmReturnService::classify((int) $row->overdue_days, (int) $row->tenor)), strtolower($row->rbm), "loan {$row->contract_id}: tenor {$row->tenor}, {$row->overdue_days} days");
        }
    }

    /**
     * Section D (paragraph 16(1)): the facilities of the return with a restructure confirmed on the
     * register by the month-end, each with its Board approval, and the counts; a candidate is counted
     * as awaiting confirmation and never reported as restructured. The screen and the downloads carry
     * the list (built 14 October 2026).
     */
    public function test_section_d_lists_the_confirmed_restructures_with_their_board_approval(): void
    {
        (require base_path('database/migrations/2026_10_14_230000_restructure_register_and_modifications.php'))->up();
        $event = fn (string $key, string $contract, ?string $date, string $status, ?string $board = null) => DB::table('restructure_events')->insertGetId([
            'detection_key' => $key, 'contract_id' => $contract, 'customer_name' => "Customer {$contract}", 'event_date' => $date, 'source' => 'CHART_REPLACED',
            'suggested_class' => 'MODIFICATION', 'status' => $status, 'classification' => $status === 'CANDIDATE' ? null : 'MODIFICATION',
            'board_approval_ref' => $board, 'board_approval_date' => $board ? '2026-01-20' : null, 'created_at' => now(), 'updated_at' => now()]);
        $c = $event('R1', 'C', '2026-02-01', 'CONFIRMED', 'BRD/2026/04');
        $event('R2', 'D', '2026-08-12', 'CONFIRMED');                  // in the month, and no Board approval recorded
        $event('R3', 'A', null, 'CANDIDATE');                          // awaiting a person
        $event('R4', 'B', '2026-08-05', 'PROPOSED');                   // awaiting a second person
        $event('R5', 'E', '2026-09-02', 'CONFIRMED', 'BRD/2026/09');   // after the month-end
        DB::table('contract_modifications')->insert(['restructure_event_id' => $c, 'contract_id' => 'C', 'modification_date' => '2026-02-01', 'reporting_period' => '2026-02',
            'classification' => 'MODIFICATION', 'old_schedule_version' => 1, 'new_schedule_version' => 2, 'treatment' => 'MODIFICATION', 'status' => 'APPLIED', 'created_at' => now(), 'updated_at' => now()]);

        $r = (new RbmReturnService(new LandingZoneReader()))->build('2026-08');
        $d = $r['sections']['D_restructured'];
        $this->assertSame(2, $d['accounts']);
        $this->assertEquals(2000, $d['balance']);
        $this->assertSame(1, $d['restructured_in_period']);
        $this->assertSame(1, $d['without_board_approval']);
        $this->assertSame(2, $d['candidates_awaiting_confirmation']);
        $by = array_column($d['facilities'], null, 'contract_id');
        $this->assertSame(['C', 'D'], array_keys($by));
        $this->assertSame('BRD/2026/04', $by['C']['board_approval_ref']);
        $this->assertSame('MODIFICATION', $by['C']['treatment']);
        $this->assertNull($by['D']['treatment']);
        $this->assertSame('Doubtful', $by['D']['class']);
        $this->assertStringContainsString('paragraph 16(1)', $d['note']);
        $this->assertStringContainsString('2 candidates', $d['note']);

        // the downloads carry the counts and the list, with the paragraph cited as 16(1)
        $payload = (new \ReflectionMethod(\App\Http\Controllers\RbmReturnController::class, 'payload'))->invoke(new \App\Http\Controllers\RbmReturnController(), $r);
        $sections = collect($payload['sections'])->keyBy('heading');
        $counts = array_column($sections['D. Restructured facilities (paragraph 16(1))']['rows'], 1, 0);
        $this->assertSame('2', $counts['Facilities restructured (confirmed on the register by the month-end)']);
        $this->assertSame('2,000.00', $counts['Their balance']);
        $this->assertSame('1', $counts['Without a Board approval recorded (paragraph 16(1) requires one)']);
        $this->assertSame('2', $counts['Candidates awaiting confirmation (not reported as restructured)']);
        $list = $sections['D. Restructured facilities: by facility']['rows'];
        $this->assertCount(3, $list);
        $this->assertSame(['C', 'BRD/2026/04 (2026-01-20)'], [$list[0][0], $list[0][5]]);
        $this->assertSame(['D', 'NONE RECORDED', '2026-08-12 (this month)'], [$list[1][0], $list[1][5], $list[1][2]]);
        $this->assertSame('Total', $list[2][0]);

        // with nothing confirmed, the list says so and the candidates are still counted
        DB::table('restructure_events')->where('status', 'CONFIRMED')->update(['status' => 'CANDIDATE', 'classification' => null]);
        $r = (new RbmReturnService(new LandingZoneReader()))->build('2026-08');
        $this->assertSame(0, $r['sections']['D_restructured']['accounts']);
        $this->assertStringContainsString('No restructure of a facility in this return is confirmed', $r['sections']['D_restructured']['note']);
        $payload = (new \ReflectionMethod(\App\Http\Controllers\RbmReturnController::class, 'payload'))->invoke(new \App\Http\Controllers\RbmReturnController(), $r);
        $list = collect($payload['sections'])->firstWhere('heading', 'D. Restructured facilities: by facility')['rows'];
        $this->assertStringContainsString('No facility in this return', $list[0][0]);
    }

    /**
     * Paragraph 13(3): a facility non-performing last month-end is held at Substandard this
     * month unless its days past due have reached nil, whatever band the day count alone
     * would put it in — a catch-up on part of the arrears, a schedule correction or a
     * modification must never by itself restore accrual while any arrears remain (compliance
     * audit finding F4; built 16 October 2026).
     */
    public function test_paragraph_13_3_holds_a_facility_at_substandard_until_its_arrears_are_nil(): void
    {
        // G: Substandard in July (95 days, short-term), would be Pass in August (20 days, dpd > 0): held
        DB::table('loan_books')->insert(['contract_id' => 'G', 'reporting_period' => '2026-07', 'product_code' => '1050101', 'tenor' => 12, 'overdue_days' => 95, 'carrying_amount' => 700, 'principal_balance' => 700]);
        DB::table('loan_books')->insert(['contract_id' => 'G', 'reporting_period' => '2026-08', 'product_code' => '1050101', 'tenor' => 12, 'overdue_days' => 20, 'carrying_amount' => 700, 'principal_balance' => 700]);
        // H: Substandard in July, genuinely nil arrears in August (0 days): released to Pass
        DB::table('loan_books')->insert(['contract_id' => 'H', 'reporting_period' => '2026-07', 'product_code' => '1050101', 'tenor' => 12, 'overdue_days' => 95, 'carrying_amount' => 300, 'principal_balance' => 300]);
        DB::table('loan_books')->insert(['contract_id' => 'H', 'reporting_period' => '2026-08', 'product_code' => '1050101', 'tenor' => 12, 'overdue_days' => 0, 'carrying_amount' => 300, 'principal_balance' => 300]);
        // I: no July row (new to the book): classified on August's day count alone, no hold possible
        DB::table('loan_books')->insert(['contract_id' => 'I', 'reporting_period' => '2026-08', 'product_code' => '1050101', 'tenor' => 12, 'overdue_days' => 20, 'carrying_amount' => 200, 'principal_balance' => 200]);
        // J: Doubtful in July (medium-term, 400 days), would be Special mention in August (100 days, dpd > 0): held at Substandard, not Special mention
        DB::table('loan_books')->insert(['contract_id' => 'J', 'reporting_period' => '2026-07', 'product_code' => '1050101', 'tenor' => 36, 'overdue_days' => 400, 'carrying_amount' => 900, 'principal_balance' => 900]);
        DB::table('loan_books')->insert(['contract_id' => 'J', 'reporting_period' => '2026-08', 'product_code' => '1050101', 'tenor' => 36, 'overdue_days' => 100, 'carrying_amount' => 900, 'principal_balance' => 900]);

        $r = (new RbmReturnService(new LandingZoneReader()))->build('2026-08');
        $p = $r['per_loan'];
        $this->assertSame('Substandard', $p['G']['class']);
        $this->assertTrue($p['G']['held_13_3']);
        $this->assertSame('Pass', $p['H']['class']);
        $this->assertFalse($p['H']['held_13_3']);
        $this->assertSame('Pass', $p['I']['class']);
        $this->assertFalse($p['I']['held_13_3']);
        $this->assertSame('Substandard', $p['J']['class']);
        $this->assertTrue($p['J']['held_13_3']);
        $this->assertStringContainsString('2 facilities were non-performing at the prior month-end', implode(' ', $r['notes']));
        $this->assertStringContainsString('arrears are not yet nil', implode(' ', $r['notes']));

        // held facilities count as non-performing for section A, the minimum provision and interest in suspense
        $classes = array_column($r['sections']['A_classification'], 'total', 'class');
        $this->assertSame(3, $classes['Substandard']['accounts']); // B (unchanged), G and J (held)
        $this->assertSame(1, $classes['Special mention']['accounts']); // C alone: G and J did not land here despite their raw day count

        // the download marks the held facilities
        $payload = (new \ReflectionMethod(\App\Http\Controllers\RbmReturnController::class, 'payload'))->invoke(new \App\Http\Controllers\RbmReturnController(), $r);
        $perFacility = collect($payload['sections'])->firstWhere('heading', 'Per facility')['rows'];
        $byContract = collect($perFacility)->keyBy(0);
        $this->assertSame('Yes: arrears not yet nil', $byContract['G'][6]);
        $this->assertSame('', $byContract['H'][6]);

        // known, bounded gap: the day-count-only SQL behind the RBM Classification, IFRS 9 vs RBM,
        // Provision Comparison and Concentration reports does not (yet) apply 13(3), so a facility the
        // Return holds at Substandard can show a lower class there. Documented, not a silent regression.
        $case = new \ReflectionMethod(\App\Http\Controllers\Reports\Ifrs9ReportsController::class, 'rbmClassCase');
        $sql = $case->invoke(app(\App\Http\Controllers\Reports\Ifrs9ReportsController::class));
        $dayCountOnly = DB::table('loan_books')->where('reporting_period', '2026-08')->where('contract_id', 'G')->selectRaw("{$sql} rbm")->value('rbm');
        $this->assertSame('Pass', $dayCountOnly, 'the day-count-only view is unaware of the hold');
        $this->assertNotSame($dayCountOnly, $p['G']['class'], 'the Return (held at Substandard) is the filing of record; this divergence is why');
    }
}
