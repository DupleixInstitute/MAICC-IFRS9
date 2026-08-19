<?php

namespace Tests\Feature\Eir;

use App\Services\Eir\FeeRuleMatcher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FeeRuleSweepTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('eir_accounting_rules', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->string('fee_type')->nullable(); $t->string('description_contains')->nullable(); $t->string('gl_account_ref')->nullable(); $t->string('cashflow_direction')->nullable(); $t->boolean('proposed_integral')->default(false); $t->text('rationale')->nullable(); $t->integer('priority')->default(100); $t->boolean('active')->default(true); $t->integer('created_by')->nullable(); $t->integer('approved_by')->nullable(); $t->timestamp('approved_at')->nullable(); $t->timestamps(); });
        Schema::create('contract_fees', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('fee_type')->nullable(); $t->text('description')->nullable(); $t->double('amount')->default(0); $t->string('cashflow_direction')->nullable(); $t->string('gl_account_ref')->nullable(); $t->boolean('integral')->nullable(); $t->string('classification_status')->default('PENDING'); $t->text('classification_reason')->nullable(); $t->integer('suggested_rule_id')->nullable(); $t->boolean('suggested_integral')->nullable(); $t->integer('classified_by')->nullable(); $t->timestamp('classified_at')->nullable(); $t->integer('reviewed_by')->nullable(); $t->timestamp('reviewed_at')->nullable(); $t->timestamps(); });
    }

    private function rule(string $name, ?string $feeType, ?string $keyword, bool $integral, int $priority, bool $approved = true): int
    {
        return DB::table('eir_accounting_rules')->insertGetId([
            'name' => $name, 'fee_type' => $feeType, 'description_contains' => $keyword,
            'proposed_integral' => $integral, 'priority' => $priority, 'active' => true,
            'approved_at' => $approved ? now() : null, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function fee(string $type, string $description, string $status = 'PENDING'): int
    {
        return DB::table('contract_fees')->insertGetId([
            'contract_id' => 'C-1', 'fee_type' => $type, 'description' => $description,
            'amount' => 1000, 'classification_status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_the_sweep_tags_pending_lines_that_had_no_suggestion(): void
    {
        $ruleId = $this->rule('Arrangement fee', 'arrangement', null, true, 20);
        $feeId = $this->fee('arrangement', 'Arrangement fee 1.5%');

        $result = (new FeeRuleMatcher())->sweepPending();
        $fee = DB::table('contract_fees')->find($feeId);

        $this->assertSame(1, $result['examined']);
        $this->assertSame(1, $result['matched']);
        $this->assertSame(1, $result['changed']);
        $this->assertSame($ruleId, (int) $fee->suggested_rule_id);
        $this->assertSame(1, (int) $fee->suggested_integral);
    }

    public function test_unapproved_rules_never_tag_anything(): void
    {
        $this->rule('Arrangement fee', 'arrangement', null, true, 20, approved: false);
        $feeId = $this->fee('arrangement', 'Arrangement fee 1.5%');

        $result = (new FeeRuleMatcher())->sweepPending();

        $this->assertSame(0, $result['matched']);
        $this->assertSame(1, $result['unmatched']);
        $this->assertNull(DB::table('contract_fees')->find($feeId)->suggested_rule_id);
    }

    /**
     * A decision already recorded must not have its suggestion moved
     * underneath it, or the audit trail reads as though the classifier
     * overruled a rule they never saw.
     */
    public function test_classified_and_reviewed_lines_are_left_untouched(): void
    {
        $this->rule('Arrangement fee', 'arrangement', null, true, 20);
        $classified = $this->fee('arrangement', 'Arrangement fee', 'CLASSIFIED');
        $reviewed = $this->fee('arrangement', 'Arrangement fee', 'REVIEWED');
        $pending = $this->fee('arrangement', 'Arrangement fee');

        $result = (new FeeRuleMatcher())->sweepPending();

        $this->assertSame(1, $result['examined']);
        $this->assertSame(2, $result['left_alone']);
        $this->assertNull(DB::table('contract_fees')->find($classified)->suggested_rule_id);
        $this->assertNull(DB::table('contract_fees')->find($reviewed)->suggested_rule_id);
        $this->assertNotNull(DB::table('contract_fees')->find($pending)->suggested_rule_id);
    }

    public function test_the_sweep_reports_lines_it_could_not_match(): void
    {
        $this->rule('Arrangement fee', 'arrangement', null, true, 20);
        $this->fee('arrangement', 'Arrangement fee');
        $this->fee('other', 'Advisory fee');   // nothing covers a bare advisory line
        $this->fee('legal', 'Legal fees');     // nor a bare legal line

        $result = (new FeeRuleMatcher())->sweepPending();

        $this->assertSame(3, $result['examined']);
        $this->assertSame(1, $result['matched']);
        $this->assertSame(2, $result['unmatched']);
    }

    public function test_priority_decides_when_two_rules_could_both_apply(): void
    {
        // The exclusion must beat the general inclusion below it.
        $recovery = $this->rule('Recovery legal cost', 'legal', 'recovery', false, 12);
        $this->rule('Origination legal cost', 'legal', null, true, 24);
        $feeId = $this->fee('legal', 'Legal fees - recovery action');

        (new FeeRuleMatcher())->sweepPending();
        $fee = DB::table('contract_fees')->find($feeId);

        $this->assertSame($recovery, (int) $fee->suggested_rule_id);
        $this->assertSame(0, (int) $fee->suggested_integral);
    }

    public function test_rerunning_the_sweep_changes_nothing_the_second_time(): void
    {
        $this->rule('Arrangement fee', 'arrangement', null, true, 20);
        $this->fee('arrangement', 'Arrangement fee');
        $matcher = new FeeRuleMatcher();

        $first = $matcher->sweepPending();
        $second = $matcher->sweepPending();

        $this->assertSame(1, $first['changed']);
        $this->assertSame(0, $second['changed']);
        $this->assertSame(1, $second['matched']);
    }

    public function test_an_amended_rule_retags_pending_lines_on_the_next_sweep(): void
    {
        $ruleId = $this->rule('Arrangement fee', 'arrangement', null, true, 20);
        $feeId = $this->fee('arrangement', 'Arrangement fee');
        $matcher = new FeeRuleMatcher();
        $matcher->sweepPending();

        // Accounting reverses the treatment; the sweep must carry it through.
        DB::table('eir_accounting_rules')->where('id', $ruleId)->update(['proposed_integral' => false]);
        $result = $matcher->sweepPending();

        $this->assertSame(1, $result['changed']);
        $this->assertSame(0, (int) DB::table('contract_fees')->find($feeId)->suggested_integral);
    }
}
