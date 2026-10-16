<?php

namespace Tests\Feature\Eir;

use App\Http\Controllers\EirFeeClassificationController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Eir\Concerns\CreatesFeeSchema;
use Tests\TestCase;

/**
 * UI audit of 14 October 2026, Fee Classification:
 *  - a fee line loaded without a cash-flow direction (the LOS charges) stopped
 *    the EIR with "a reviewed integral fee/cost has no cash-flow direction" and
 *    nothing on the screen could set it: the classifier now states it, and it
 *    only ever fills a blank;
 *  - the held GL fee lines and the differences between fee sources are read
 *    for the screen without writing anything; tying a held line is governed.
 */
class FeeClassificationUiTest extends TestCase
{
    use CreatesFeeSchema;

    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'activitylog.enabled' => false]);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->string('email'); $t->string('password')->nullable(); $t->timestamps(); });
        Schema::create('audit_logs', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id')->nullable(); $t->string('action'); $t->string('entity_type'); $t->integer('entity_id')->nullable(); $t->string('scope')->nullable(); $t->string('reporting_period')->nullable(); $t->integer('rows_affected')->nullable(); $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->text('meta')->nullable(); $t->string('ip_address')->nullable(); $t->string('user_agent')->nullable(); $t->timestamps(); });
        $this->createFeeSchema();
        Schema::create('eir_fee_classification_events', function (Blueprint $t) { $t->increments('id'); $t->integer('contract_fee_id'); $t->string('action'); $t->boolean('integral')->nullable(); $t->text('reason')->nullable(); $t->integer('accounting_rule_id')->nullable(); $t->integer('performed_by')->nullable(); $t->timestamps(); });
        DB::table('users')->insert(['id' => 10, 'name' => 'Maker', 'email' => 'maker@example.test', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('contract_fees')->insert([
            ['id' => 1, 'contract_id' => 'C1', 'fee_type' => 'arrangement', 'amount' => 500000, 'cashflow_direction' => null, 'source_system' => 'E-Banker LOS_DISB_CHARGES_POST', 'superseded_by_source' => 'GL posting', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'contract_id' => 'C1', 'fee_type' => 'arrangement', 'amount' => 450000, 'cashflow_direction' => 'PAID', 'source_system' => 'E-Banker GL fee posting', 'superseded_by_source' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function classify(array $body): void
    {
        $this->actingAs(User::findOrFail(10));
        $request = Request::create('/eir-fee-classification/classify', 'POST', $body);
        $request->setLaravelSession(app('session.store'));
        app()->instance('request', $request);
        app(EirFeeClassificationController::class)->classify($request);
    }

    public function test_the_classifier_fills_a_missing_direction_and_never_overwrites_one(): void
    {
        $this->classify(['fee_ids' => [1, 2], 'mode' => 'manual', 'integral' => true, 'reason' => 'Origination fees are integral.', 'cashflow_direction' => 'RECEIVED']);

        $this->assertSame('RECEIVED', DB::table('contract_fees')->where('id', 1)->value('cashflow_direction'));
        $this->assertSame('PAID', DB::table('contract_fees')->where('id', 2)->value('cashflow_direction'));
        $this->assertSame(2, DB::table('contract_fees')->where('classification_status', 'CLASSIFIED')->count());
    }

    public function test_without_a_direction_the_blank_stays_blank(): void
    {
        $this->classify(['fee_ids' => [1], 'mode' => 'manual', 'integral' => true, 'reason' => 'Origination fees are integral.']);

        $this->assertNull(DB::table('contract_fees')->where('id', 1)->value('cashflow_direction'));
    }

    public function test_the_source_differences_are_read_from_the_stored_per_event_table(): void
    {
        // FeeSourcePrecedence::apply stores each difference per fee event (R03, 15 October 2026); the screen only reads it
        Schema::create('fee_source_differences', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('fee_type'); $t->integer('record_fee_id'); $t->string('source_of_record'); $t->decimal('amount_of_record', 20, 2); $t->integer('other_fee_id'); $t->string('other_source'); $t->decimal('other_amount', 20, 2); $t->decimal('difference', 20, 2); $t->timestamps(); });
        DB::table('fee_source_differences')->insert(['contract_id' => 'C1', 'fee_type' => 'arrangement', 'record_fee_id' => 2, 'source_of_record' => 'GL posting', 'amount_of_record' => 450000,
            'other_fee_id' => 1, 'other_source' => 'LOS capture', 'other_amount' => 500000, 'difference' => 50000, 'created_at' => now(), 'updated_at' => now()]);
        $method = new \ReflectionMethod(EirFeeClassificationController::class, 'sourceDifferences');
        $method->setAccessible(true);
        $rows = $method->invoke(new EirFeeClassificationController());

        $this->assertCount(1, $rows);
        $this->assertSame('GL posting', $rows[0]['source_of_record']);
        $this->assertSame(450000.0, $rows[0]['amount_of_record']);
        $this->assertSame('LOS capture', $rows[0]['other_source']);
        $this->assertSame(50000.0, $rows[0]['difference']);
    }

    public function test_tying_a_held_line_is_routed_and_governed(): void
    {
        $this->assertTrue(Route::has('eir-fee-classification.tie-held'));
        $govern = collect((new EirFeeClassificationController())->getMiddleware())->firstWhere('middleware', 'permission:eir.govern');
        // saying a held posting is not the named loan's fee is governed the same way (15 October 2026)
        $this->assertTrue(Route::has('eir-fee-classification.dismiss-candidate'));
        // and so is deciding Finance's workbook answers, which tie, decide and classify through the same services (16 October 2026)
        // and so is the held-queue re-import, a bulk front end to assignHeld()/dismiss() (Held Fee Classification Export/Reimport Specification v1)
        $this->assertSame(['tieHeld', 'dismissCandidate', 'decideOverlap', 'moveFee', 'decideAnswer', 'approveSheet', 'uploadHeldImport'], $govern['options']['only']);
        $this->assertTrue(Route::has('eir-fee-classification.move'));
        $intake = collect((new EirFeeClassificationController())->getMiddleware())->firstWhere('middleware', 'permission:settings|eir.fees.import');
        $this->assertSame(['index', 'downloadWorkbook', 'uploadAnswers', 'downloadHeldExport'], $intake['options']['only']);
    }

    public function test_select_all_matching_returns_every_line_the_filters_select_on_every_page(): void
    {
        $now = now();
        foreach (range(3, 22) as $id) {
            DB::table('contract_fees')->insert(['id' => $id, 'contract_id' => 'C' . $id, 'fee_type' => $id % 2 ? 'legal' : 'arrangement', 'amount' => 1000,
                'gl_account_ref' => $id === 22 ? '4874' : null, 'classification_status' => $id === 21 ? 'REVIEWED' : 'PENDING', 'source_system' => 'MAIIC Finance EIR assessment 2025',
                'created_at' => $now, 'updated_at' => $now]);
        }
        $ids = fn (array $q) => app(EirFeeClassificationController::class)->matchingIds(Request::create('/eir-fee-classification/matching-ids', 'GET', $q))->getData(true);

        $all = $ids([]);
        $this->assertNotContains(1, $all['ids'], 'a superseded line is left out');
        $this->assertNotContains(21, $all['ids'], 'a reviewed line is left out unless asked for');
        $this->assertSame(20, $all['count'], 'more than one page (15) is selected');

        $this->assertSame([2, 4, 6, 8, 10, 12, 14, 16, 18, 20, 22], $ids(['fee_type' => 'arrangement'])['ids'], 'the fixture line 2 is a pending arrangement fee too');
        $this->assertSame([22], $ids(['gl' => '4874'])['ids']);
        $this->assertSame([21], $ids(['status' => 'REVIEWED'])['ids']);
    }
}
