<?php

namespace Tests\Feature\FLI;

use App\Services\Eir\GovernanceService;
use App\Services\Fli\OverlayService;
use App\Services\Scenario\ScenarioSetService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * The manual-overlay register (spec v4 sections 14.6 and 15.7; system audit
 * of 9 October 2026, finding M3): an overlay is a register entry with scope,
 * reason, evidence, owner and expiry; one person proposes it and a different
 * person approves it; it is in force from its period to its expiry; and its
 * effect on the ECL is its own line, computed on the same stage-PD rule the
 * sensitivity uses.
 */
class OverlayServiceTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamps(); });
        Schema::create('audit_logs', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id')->nullable(); $t->string('action'); $t->string('entity_type'); $t->integer('entity_id')->nullable(); $t->string('scope')->nullable(); $t->string('reporting_period')->nullable(); $t->integer('rows_affected')->nullable(); $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->text('meta')->nullable(); $t->string('ip_address')->nullable(); $t->string('user_agent')->nullable(); $t->timestamps(); });
        Schema::create('macro_series', function (Blueprint $t) { $t->increments('id'); $t->string('statistic_code'); $t->string('observation_period'); $t->decimal('value', 18, 6); $t->string('value_type')->default('actual'); });
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->string('product_group')->nullable(); $t->string('ifrs9stage_post_qualitative')->nullable(); $t->string('calculated_ifrs9_stage')->nullable(); $t->string('ifrs9stage_pre_qualitative')->nullable(); $t->decimal('pd_prefli', 16, 8)->nullable(); $t->decimal('pd_value', 16, 8)->nullable(); $t->decimal('12m_pd', 8, 2)->nullable(); $t->decimal('pd_post_fli', 16, 8)->nullable(); $t->decimal('lgd_value', 16, 8)->nullable(); $t->decimal('ecl_value', 18, 2)->nullable(); $t->decimal('remaining_tenor', 8, 2)->nullable(); $t->decimal('carrying_amount', 20, 2)->default(0); $t->decimal('commitments', 18, 2)->nullable(); $t->decimal('facility_utilisation_rate', 5, 2)->nullable(); $t->text('fli_by_scenario')->nullable(); $t->integer('fli_set_id')->nullable(); });
        foreach (['2026_10_09_100000_create_governed_scenario_sets', '2026_10_09_300000_create_fli_overlays'] as $m) {
            (require base_path("database/migrations/{$m}.php"))->up();
        }
        DB::table('users')->insert([['id' => 1, 'name' => 'Maker', 'created_at' => now(), 'updated_at' => now()], ['id' => 2, 'name' => 'Checker', 'created_at' => now(), 'updated_at' => now()]]);
        DB::table('macro_series')->insert(['statistic_code' => 'CPI', 'observation_period' => '202612', 'value' => 20.0]);
        // Three loans with a booked ECL: a Stage 1 loan with 24 months to run (a
        // full twelve-month PD), a Stage 2 loan over 24 months (lifetime PD),
        // and a Stage 3 loan that is already at 100 percent.
        DB::table('loan_books')->insert([
            ['contract_id' => 'A', 'reporting_period' => '2026-08', 'product_group' => 'SME', 'ifrs9stage_post_qualitative' => '1', 'pd_prefli' => 0.10, 'lgd_value' => 0.5, 'carrying_amount' => 1000, 'ecl_value' => 50, 'remaining_tenor' => 24],
            ['contract_id' => 'B', 'reporting_period' => '2026-08', 'product_group' => 'AGRI', 'ifrs9stage_post_qualitative' => '2', 'pd_prefli' => 0.10, 'lgd_value' => 0.5, 'carrying_amount' => 1000, 'ecl_value' => 95, 'remaining_tenor' => 24],
            ['contract_id' => 'C', 'reporting_period' => '2026-08', 'product_group' => 'AGRI', 'ifrs9stage_post_qualitative' => '3', 'pd_prefli' => 0.10, 'lgd_value' => 0.5, 'carrying_amount' => 1000, 'ecl_value' => 500, 'remaining_tenor' => 24],
        ]);
    }

    private function sets(): ScenarioSetService
    {
        return new ScenarioSetService(new GovernanceService());
    }

    private function service(): OverlayService
    {
        return new OverlayService($this->sets());
    }

    /** An approved set for the period, since the rule of 15.6 says an overlay needs one. */
    private function approvedSet(string $period = '2026-08'): int
    {
        $id = $this->sets()->create($period, 'Set', [
            ['name' => 'Base', 'weight' => 50, 'is_base' => true, 'pd_multiplier' => 1, 'calibration_note' => 'base'],
            ['name' => 'Up', 'weight' => 25, 'pd_multiplier' => 0.9, 'calibration_note' => 'up', 'shocks' => [['statistic_code' => 'CPI', 'kind' => 'abs', 'value' => -5]]],
            ['name' => 'Down', 'weight' => 25, 'pd_multiplier' => 1.2, 'calibration_note' => 'down', 'shocks' => [['statistic_code' => 'CPI', 'kind' => 'abs', 'value' => 5]]],
        ], 1);
        $this->sets()->propose($id, 1);
        $this->sets()->approve($id, 2);

        return $id;
    }

    private function overlay(array $over = []): array
    {
        return $over + ['reporting_period' => '2026-08', 'scope' => 'book', 'adjustment' => 0.15, 'reason' => 'A drought the regression cannot see yet', 'evidence' => 'Ministry of Agriculture crop estimate, July 2026', 'owner_id' => 1, 'expiry_period' => '2026-10'];
    }

    public function test_an_overlay_needs_an_approved_set_and_a_second_person_to_approve_it(): void
    {
        try {
            $this->service()->propose($this->overlay(), 1);
            $this->fail('an overlay was proposed with no approved set for the period');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('approved scenario set', $e->getMessage());
        }
        $setId = $this->approvedSet();
        $id = $this->service()->propose($this->overlay(), 1);
        $o = $this->service()->find($id);
        $this->assertSame('PROPOSED', $o->status);
        $this->assertSame($setId, (int) $o->set_id);
        $this->assertSame(1, (int) $o->proposed_by);
        try {
            $this->service()->approve($id, 1);
            $this->fail('the proposer approved their own overlay');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('different person', $e->getMessage());
        }
        $this->service()->approve($id, 2);
        $o = $this->service()->find($id);
        $this->assertSame('APPROVED', $o->status);
        $this->assertSame(2, (int) $o->approved_by);
        $this->assertSame(['FLI Overlay Proposed', 'FLI Overlay Approved'], DB::table('audit_logs')->where('entity_type', 'fli_overlays')->orderBy('id')->pluck('action')->all());
    }

    public function test_the_register_refuses_an_entry_without_its_scope_reason_or_a_sane_adjustment(): void
    {
        $this->approvedSet();
        foreach ([
            [['scope' => 'product_group', 'scope_value' => ''], 'must name which one'],
            [['reason' => '  '], 'needs a reason'],
            [['adjustment' => -1.0], 'minus one'],
            [['adjustment' => 'lots'], 'must be a number'],
            [['expiry_period' => '2026-07'], 'no earlier than the reporting period'],
            [['scope' => 'stage'], 'the book, a product group or a contract'],
        ] as [$over, $message]) {
            try {
                $this->service()->propose($this->overlay($over), 1);
                $this->fail('the register accepted ' . json_encode($over));
            } catch (RuntimeException $e) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        }
        $this->assertSame(0, DB::table('fli_overlays')->count());
    }

    public function test_in_force_is_by_period_and_expiry_and_a_rejected_or_expired_overlay_is_not(): void
    {
        $this->approvedSet();
        $s = $this->service();
        $live = $s->propose($this->overlay(['expiry_period' => '2026-10']), 1);
        $s->approve($live, 2);
        $short = $s->propose($this->overlay(['expiry_period' => '2026-08', 'scope' => 'contract', 'scope_value' => 'A']), 1);
        $s->approve($short, 2);
        $rejected = $s->propose($this->overlay(['scope' => 'product_group', 'scope_value' => 'AGRI']), 1);
        $s->reject($rejected, 2, 'the evidence does not support it');
        $pending = $s->propose($this->overlay(['adjustment' => 0.05]), 1);

        $this->assertSame([$live, $short], $s->inForce('2026-08')->pluck('id')->map(fn ($i) => (int) $i)->all());
        $this->assertSame([$live], $s->inForce('2026-09')->pluck('id')->map(fn ($i) => (int) $i)->all());
        $this->assertSame([$live], $s->inForce('2026-10')->pluck('id')->map(fn ($i) => (int) $i)->all());
        $this->assertSame([], $s->inForce('2026-11')->pluck('id')->all());
        $this->assertSame([], $s->inForce('2026-07')->pluck('id')->all());
        $this->assertSame('REJECTED', $s->find($rejected)->status);
        $this->assertSame('PROPOSED', $s->find($pending)->status);

        // withdrawn early: no longer in force for its own period
        $s->expire($live, 2, 'the rains came');
        $this->assertSame([$short], $s->inForce('2026-08')->pluck('id')->map(fn ($i) => (int) $i)->all());
        // the sweep marks what has passed its expiry period
        $this->assertSame(1, $s->sweepExpired('2026-09'));
        $this->assertSame('EXPIRED', $s->find($short)->status);
        // only a proposed overlay can be approved or rejected; only an approved one expires
        $this->expectException(RuntimeException::class);
        $s->approve($rejected, 2);
    }

    public function test_the_ecl_line_is_the_booked_ecl_scaled_by_the_ratio_of_stage_pds(): void
    {
        $this->approvedSet();
        $s = $this->service();
        $book = $s->propose($this->overlay(['adjustment' => 0.20]), 1);
        $s->approve($book, 2);
        $agri = $s->propose($this->overlay(['adjustment' => 0.50, 'scope' => 'product_group', 'scope_value' => 'AGRI']), 1);
        $s->approve($agri, 2);
        $one = $s->propose($this->overlay(['adjustment' => -0.10, 'scope' => 'contract', 'scope_value' => 'A']), 1);
        $s->approve($one, 2);

        $loans = $s->loansOf('2026-08');
        $this->assertCount(3, $loans);
        // Loan A, Stage 1 over 24 months: stage PD is the twelve-month PD itself,
        // so the book overlay of +20 percent adds 50 x (0.12 / 0.10 - 1) = 10.
        // Loan B, Stage 2 over 24 months: lifetime PD 1 - (1 - PD)^2, 0.19 at
        // 0.10 and 0.2256 at 0.12, so it adds 95 x (0.2256 / 0.19 - 1) = 17.80.
        // Loan C, Stage 3: the stage PD is 1 whatever the overlay, it adds nothing.
        $line = $s->eclLine($s->find($book), $loans);
        $this->assertSame(3, $line['loans']);
        $this->assertEqualsWithDelta(10 + 95 * ((1 - pow(0.88, 2)) / (1 - pow(0.9, 2)) - 1), $line['amount'], 0.01);
        // The AGRI overlay covers B and C only: B at 0.15 lifetime 1 - 0.85^2 = 0.2775 adds 95 x (0.2775 / 0.19 - 1) = 43.75; C adds nothing.
        $line = $s->eclLine($s->find($agri), $loans);
        $this->assertSame(2, $line['loans']);
        $this->assertEqualsWithDelta(95 * ((1 - pow(0.85, 2)) / (1 - pow(0.9, 2)) - 1), $line['amount'], 0.01);
        // The contract overlay covers A only: -10 percent takes 50 to 45, a line of -5.
        $line = $s->eclLine($s->find($one), $loans);
        $this->assertSame(1, $line['loans']);
        $this->assertEqualsWithDelta(-5.0, $line['amount'], 0.01);
        // Every line in force for the period, with the total
        $lines = $s->eclLines('2026-08');
        $this->assertCount(3, $lines['lines']);
        $this->assertEqualsWithDelta(10 + 17.80 + 43.75 - 5, $lines['total'], 0.05);
        // When the route has already written a post-FLI PD, the line is re-based
        // to the pre-FLI PD so it is the overlay's own contribution: loan A booked
        // at 0.12 with an ECL of 60 gives the same +10 for the book overlay.
        DB::table('loan_books')->where('contract_id', 'A')->update(['pd_post_fli' => 0.12, 'ecl_value' => 60]);
        $line = $s->eclLine($s->find($one), $s->loansOf('2026-08'));
        $this->assertEqualsWithDelta(-5.0, $line['amount'], 0.01);
    }
}
