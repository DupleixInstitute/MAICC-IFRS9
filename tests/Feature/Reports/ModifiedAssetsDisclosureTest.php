<?php

namespace Tests\Feature\Reports;

use App\Services\Eir\RestructureRegisterService;
use App\Services\Reports\Ifrs9NoteDocuments;
use App\Services\Reports\Ifrs9NoteService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The IFRS 9 note discloses the modified financial assets (IFRS 7.35J(a),
 * 35J(b) and 20A, and the IFRS 9 B5.4.6 catch-up) from the confirmed
 * restructure register as the revenue run applied it, for the note's period,
 * on the screen, the PDF, Word, Excel and CSV; says so honestly when there are
 * none; and counts the candidates that await a person, disclosing none of them.
 * A confirmed modification the revenue run has not applied stops the note.
 * Built 14 October 2026.
 *
 * The book, May to June 2026, portfolio 1: A Stage 1 both months (gross 900 in
 * June), W Stage 3 both months, N new in June at Stage 2. The note's period is
 * 1 to 30 June 2026.
 */
class ModifiedAssetsDisclosureTest extends TestCase
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
            $t->string('contract_status')->nullable(); $t->double('interest_rate')->nullable();
        });
        Schema::create('expected_credit_loss', function (Blueprint $t) {
            $t->increments('id'); $t->string('reporting_period'); $t->string('ifrs9_stage'); $t->string('ecl_calculation_level'); $t->integer('ecl_calculation_id')->nullable();
            $t->decimal('total_ecl', 20, 2)->nullable(); $t->decimal('total_ecl_discounted', 20, 2)->nullable(); $t->decimal('total_ead', 20, 2)->nullable(); $t->integer('total_loans')->default(0);
            $t->double('pd_value_used')->nullable(); $t->double('lgd_value_used')->nullable(); $t->timestamp('deleted_at')->nullable(); $t->timestamps();
        });
        Schema::create('reporting_periods', function (Blueprint $t) { $t->increments('id'); $t->date('period'); $t->integer('pd_id')->nullable(); $t->integer('lgd_id')->nullable(); $t->string('pd_calculation_source')->nullable(); $t->string('lgd_calculation_source')->nullable(); });
        Schema::create('contract_eir', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id')->unique(); $t->string('rate_type')->default('FIXED'); $t->timestamps(); });
        Schema::create('contract_cashflow_schedule', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->smallInteger('schedule_version')->default(1); $t->date('effective_from')->nullable(); $t->date('due_date'); $t->double('principal_due')->default(0); $t->double('interest_due')->default(0); $t->double('fee_due')->default(0); $t->string('schedule_source')->default('IMPORTED'); $t->timestamps(); });
        DB::table('loan_portfolios')->insert(['id' => 1, 'name' => 'MAIIC Agricultural']);

        $disc = 'CALCULATED_TIME_PHASED';
        foreach ([['A', '2026-05', 1, 1000, 10, 9], ['A', '2026-06', 1, 900, 12, 11], ['W', '2026-05', 3, 500, 300, 310], ['W', '2026-06', 3, 480, 290, 300], ['N', '2026-06', 2, 400, 40, 38]] as [$c, $p, $s, $gross, $ecl, $d]) {
            DB::table('loan_books')->insert(['loan_portfolio_id' => 1, 'contract_id' => $c, 'customer_name' => 'Customer ' . $c, 'product_code' => '1050101', 'reporting_period' => $p,
                'carrying_amount' => $gross, 'ecl_value' => $ecl, 'ecl_value_discounted' => $d, 'ecl_discounting_effect' => 1, 'ecl_discount_status' => $disc,
                'ifrs9stage_post_qualitative' => (string) $s, 'calculated_ifrs9_stage' => (string) $s, 'pd_post_fli' => 0.1, 'lgd_value' => 0.5]);
        }
        foreach (['2026-05' => [1 => [10, 9, 1000, 1], 3 => [300, 310, 500, 1]], '2026-06' => [1 => [12, 11, 900, 1], 2 => [40, 38, 400, 1], 3 => [290, 300, 480, 1]]] as $p => $stages) {
            foreach ($stages as $s => [$ecl, $d, $ead, $n]) {
                DB::table('expected_credit_loss')->insert(['reporting_period' => $p, 'ifrs9_stage' => (string) $s, 'ecl_calculation_level' => 'portfolio', 'ecl_calculation_id' => 1,
                    'total_ecl' => $ecl, 'total_ecl_discounted' => $d, 'total_ead' => $ead, 'total_loans' => $n, 'pd_value_used' => 0.1, 'lgd_value_used' => 0.5, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        DB::table('ebanker_loads')->insert(['id' => 1, 'pack_hash' => str_repeat('m', 64), 'pack_name' => 'fixture', 'manifest' => '{}', 'status' => 'LANDED', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function install(): void
    {
        (require base_path('database/migrations/2026_10_14_230000_restructure_register_and_modifications.php'))->up();
    }

    private function modification(string $contract, string $date, string $treatment, string $status, array $extra = []): void
    {
        static $version = 1;
        $version++;
        DB::table('contract_modifications')->insert($extra + ['restructure_event_id' => $version, 'contract_id' => $contract, 'modification_date' => $date, 'reporting_period' => substr($date, 0, 7),
            'classification' => $treatment === 'CATCH_UP' ? 'REVISED_ESTIMATE' : 'MODIFICATION', 'old_schedule_version' => 1, 'new_schedule_version' => $version,
            'treatment' => $status === 'APPLIED' ? $treatment : null, 'status' => $status, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function event(string $key, ?string $date, string $status): void
    {
        DB::table('restructure_events')->insert(['detection_key' => $key, 'contract_id' => 'A', 'event_date' => $date, 'source' => 'CHART_REPLACED', 'suggested_class' => 'UNCERTAIN',
            'status' => $status, 'classification' => $status === 'CANDIDATE' ? null : 'MODIFICATION', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_the_note_discloses_35j_and_20a_from_the_applied_modifications_and_counts_the_candidates(): void
    {
        $this->install();
        // A modified on 10 June while on lifetime ECL (the register recorded Stage 2 at the event), Stage 1 at 30 June
        $this->modification('A', '2026-06-10', 'MODIFICATION', 'APPLIED', ['lifetime_ecl_before' => true, 'carrying_amount_before' => 950, 'gain_loss' => -20]);
        $this->modification('N', '2026-06-15', 'CATCH_UP', 'APPLIED', ['carrying_amount_before' => 395, 'gain_loss' => 5]);
        $this->modification('W', '2026-06-20', 'DERECOGNITION', 'APPLIED', ['carrying_amount_before' => 500, 'gain_loss' => -30, 'lifetime_ecl_before' => true]);
        $this->modification('A', '2026-06-25', 'MODIFICATION', 'REVERSED', ['lifetime_ecl_before' => true, 'carrying_amount_before' => 1, 'gain_loss' => -1]); // reversed: never disclosed
        $this->modification('W', '2026-05-15', 'MODIFICATION', 'APPLIED', ['lifetime_ecl_before' => true, 'carrying_amount_before' => 7, 'gain_loss' => -7]); // before the period
        $this->event('C1', null, 'CANDIDATE');            // no date: counted
        $this->event('C2', '2026-07-05', 'CANDIDATE');    // after the period: not counted
        $this->event('P1', '2026-06-01', 'PROPOSED');     // awaiting a second person
        $this->event('X1', '2026-06-10', 'CONFIRMED');

        $r = Ifrs9NoteService::build('2026-05', '2026-06');
        $m = $r['modifications'];
        $this->assertTrue($m['installed']);
        $this->assertFalse($m['none']);
        $this->assertSame(['2026-06-01', '2026-06-30'], [$m['from'], $m['to']]);
        $this->assertSame(1, $m['lifetime_ecl_modified']['count']);
        $this->assertEquals(950, $m['lifetime_ecl_modified']['amortised_cost_before']);
        $this->assertEquals(-20, $m['lifetime_ecl_modified']['net_gain_loss']);
        $this->assertSame(1, $m['all_modified']['count']);
        $this->assertSame(1, $m['derecognised']['count']);
        $this->assertEquals(500, $m['derecognised']['carrying_amount']);
        $this->assertEquals(-30, $m['derecognised']['gain_loss']);
        $this->assertSame(1, $m['catch_up']['count']);
        $this->assertEquals(5, $m['catch_up']['adjustment']);
        // 35J(b): A, modified in the period while lifetime ECL, is on 12-month ECL at its end: its gross at 30 June
        $this->assertSame(1, $m['lifetime_modified_now_12_month']['count']);
        $this->assertEquals(900, $m['lifetime_modified_now_12_month']['gross_carrying_amount']);
        $this->assertSame([1, 1, 1], [$m['candidates'], $m['proposed'], $m['confirmed_in_period']]);
        $this->assertStringContainsString('2 candidate restructures', $m['message']);
        $this->assertStringContainsString('none is disclosed here', $m['message']);
        $check = collect($r['checks'])->firstWhere('key', 'modifications');
        $this->assertSame('info', $check['level']);
        $this->assertSame('ok', $r['status'], json_encode(collect($r['checks'])->where('level', 'fail')->values()));

        // the documents: one view model for the screen, the PDF, Word, Excel and CSV
        $note = Ifrs9NoteDocuments::build($r, 'full');
        $rows = collect($note['modifications']['rows'])->keyBy('key');
        $this->assertSame(95000, $rows['lifetime_cost']['amount']);
        $this->assertSame(-2000, $rows['lifetime_gain']['amount']);
        $this->assertSame(90000, $rows['now_12_month']['amount']);
        $this->assertSame(-3000, $rows['derecognised_gain']['amount']);
        $this->assertSame(500, $rows['catch_up']['amount']);
        $this->assertSame('IFRS 7.35J(b)', $rows['now_12_month']['ref']);
        $this->assertSame('IFRS 9 B5.4.6', $rows['catch_up']['ref']);
        $section = collect(Ifrs9NoteDocuments::payload($note)['sections'])->firstWhere('heading', '(h) Modified financial assets');
        $this->assertNotNull($section);
        $this->assertCount(7, $section['rows']);
        $this->assertSame('(30.00)', $section['rows'][5][3]);
        $html = view('reports.ifrs9_note_pdf', ['note' => $note, 'company' => 'MAIIC', 'logo' => null, 'preparedOn' => '14 Oct 2026', 'preparedBy' => null])->render();
        $this->assertStringContainsString('(h) Modified financial assets', $html);
        $this->assertStringContainsString('IFRS 7.20A', $html);
        $this->assertStringContainsString('2 candidate restructures', $html);

        // the portfolio scope: a portfolio holding none of the loans discloses none of them
        DB::table('loan_portfolios')->insert(['id' => 2, 'name' => 'Other']);
        DB::table('loan_books')->where('contract_id', 'N')->update(['loan_portfolio_id' => 2]);
        $scoped = Ifrs9NoteService::build('2026-05', '2026-06', 1)['modifications'];
        $this->assertSame(0, $scoped['catch_up']['count']);
        $this->assertSame(1, $scoped['derecognised']['count']);
    }

    public function test_a_confirmed_modification_not_applied_by_the_revenue_run_stops_the_note(): void
    {
        $this->install();
        $this->modification('A', '2026-06-10', 'MODIFICATION', 'PENDING', ['lifetime_ecl_before' => true]);
        $this->modification('W', '2026-06-12', 'MODIFICATION', 'BLOCKED', ['blocked_reason' => 'No fair value recorded.']);
        $r = Ifrs9NoteService::build('2026-05', '2026-06');
        $check = collect($r['checks'])->firstWhere('key', 'modifications');
        $this->assertSame('fail', $check['level']);
        $this->assertSame('failed', $r['status']);
        $this->assertStringContainsString('1 confirmed modification has not yet been applied', $check['detail']);
        $this->assertStringContainsString('1 confirmed modification was stopped by the revenue run', $check['detail']);
        $this->assertSame(0, $r['modifications']['all_modified']['count'], 'nothing unapplied is disclosed as a figure');
    }

    public function test_no_confirmed_modification_says_so_and_names_the_candidates(): void
    {
        $this->install();
        $this->event('C1', '2026-06-03', 'CANDIDATE');
        $r = Ifrs9NoteService::build('2026-05', '2026-06');
        $m = $r['modifications'];
        $this->assertTrue($m['none']);
        $this->assertStringContainsString('No modification of a financial asset was confirmed and applied', $m['message']);
        $this->assertStringContainsString('nothing to disclose under IFRS 7.35J(a), 35J(b) or 20A', $m['message']);
        $this->assertStringContainsString('1 candidate restructure found', $m['message']);
        $note = Ifrs9NoteDocuments::build($r, 'k');
        $this->assertSame([], $note['modifications']['rows']);
        $this->assertSame(1, $note['modifications']['candidates']);
        $section = collect(Ifrs9NoteDocuments::payload($note)['sections'])->firstWhere('heading', '(h) Modified financial assets');
        $this->assertStringContainsString('No modification', $section['rows'][0][1]);

        // and with nothing on the register at all
        DB::table('restructure_events')->delete();
        $this->assertStringContainsString('No candidate restructure awaits confirmation.', Ifrs9NoteService::build('2026-05', '2026-06')['modifications']['message']);
    }

    public function test_the_note_says_when_the_register_is_not_installed(): void
    {
        $r = Ifrs9NoteService::build('2026-05', '2026-06');
        $this->assertFalse($r['modifications']['installed']);
        $this->assertSame('warn', collect($r['checks'])->firstWhere('key', 'modifications')['level']);
        $this->assertStringContainsString('not installed', $r['modifications']['message']);
    }

    /** 35J(b) counts a loan modified before the period only if its allowance moved to 12-month ECL during the period. */
    public function test_35jb_needs_the_allowance_to_change_to_12_month_ecl_during_the_period(): void
    {
        $this->install();
        $this->modification('A', '2026-03-01', 'MODIFICATION', 'APPLIED', ['lifetime_ecl_before' => true, 'carrying_amount_before' => 1100, 'gain_loss' => -10]);
        $service = new RestructureRegisterService();
        // A was on 12-month ECL at the end of May already: its allowance did not change in June
        $this->assertSame(0, $service->ifrs7Disclosures('2026-06-01', '2026-06-30')['lifetime_modified_now_12_month']['count']);
        $this->assertSame(0, $service->ifrs7Disclosures('2026-06-01', '2026-06-30')['lifetime_ecl_modified']['count'], 'modified before the period');
        // on lifetime ECL at the end of May, 12-month at the end of June: disclosed at its June gross
        DB::table('loan_books')->where('contract_id', 'A')->where('reporting_period', '2026-05')->update(['ifrs9stage_post_qualitative' => '2']);
        $d = $service->ifrs7Disclosures('2026-06-01', '2026-06-30');
        $this->assertSame(1, $d['lifetime_modified_now_12_month']['count']);
        $this->assertEquals(900, $d['lifetime_modified_now_12_month']['gross_carrying_amount']);
        // scoped to other loans: none
        $this->assertSame(0, $service->ifrs7Disclosures('2026-06-01', '2026-06-30', ['W'])['lifetime_modified_now_12_month']['count']);
    }
}
