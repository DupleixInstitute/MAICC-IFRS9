<?php

namespace Tests\Feature\Fli;

use App\Services\Eir\GovernanceService;
use App\Services\Fli\TransmissionMethodCatalogue;
use App\Support\Fli\AdjustmentMethods;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The transmission methods (spec v4 section 14.7): each moves a PD as its
 * card says, declines rather than invents when an input is undefined, is
 * floored and capped, and the cards check their preconditions against the
 * data with the figure that decides each.
 */
class TransmissionMethodCatalogueTest extends TestCase
{
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('customer_name')->nullable(); $t->string('product_group')->nullable(); $t->string('reporting_period'); $t->string('ifrs9stage_post_qualitative')->nullable(); $t->decimal('pd_prefli', 16, 8)->nullable(); $t->decimal('fli_adj', 16, 8)->nullable(); $t->decimal('pd_post_fli', 16, 8)->nullable(); });
        Schema::create('scenario_sets', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->boolean('is_active')->default(0); });
        foreach (['2025-10', '2025-11', '2025-12'] as $p) {
            DB::table('loan_books')->insert(['contract_id' => '104420000005', 'customer_name' => 'Micholess Creamery', 'product_group' => 'MAIIC Agricultural Loans', 'reporting_period' => $p, 'ifrs9stage_post_qualitative' => '1', 'pd_prefli' => 0.05, 'fli_adj' => 0.10, 'pd_post_fli' => 0.055]);
        }
    }

    private function catalogue(): TransmissionMethodCatalogue
    {
        return new TransmissionMethodCatalogue(new GovernanceService());
    }

    public function test_each_method_moves_the_pd_as_its_card_says(): void
    {
        $c = $this->catalogue();
        $this->assertEqualsWithDelta(0.055, $c->apply(TransmissionMethodCatalogue::SCALAR, 0.05, ['adjustment' => 0.10]), 1e-9);
        $this->assertEqualsWithDelta(0.045, $c->apply(TransmissionMethodCatalogue::SEGMENT, 0.05, ['segment_adjustment' => -0.10]), 1e-9);
        // logit: with beta 0 the PD is unchanged; a positive beta x macro raises the odds
        $alpha = log(0.05 / 0.95);
        $this->assertEqualsWithDelta(0.05, $c->apply(TransmissionMethodCatalogue::LOGIT, 0.05, ['alpha' => $alpha, 'beta' => 0, 'macro' => 2]), 1e-9);
        $this->assertGreaterThan(0.05, $c->apply(TransmissionMethodCatalogue::LOGIT, 0.05, ['alpha' => $alpha, 'beta' => 0.5, 'macro' => 1]));
        // Vasicek: the conditional PD at Z = 0 sits below a TTC PD under 50 percent (the TTC is the
        // average over Z); a downturn (Z < 0) raises it; the shift is bounded
        $neutral = $c->apply(TransmissionMethodCatalogue::VASICEK, 0.05, ['rho' => 0.12, 'z' => 0.0]);
        $this->assertEqualsWithDelta(TransmissionMethodCatalogue::phi(TransmissionMethodCatalogue::phiInv(0.05) / sqrt(0.88)), $neutral, 1e-6);
        $down = $c->apply(TransmissionMethodCatalogue::VASICEK, 0.05, ['rho' => 0.12, 'z' => -2.0]);
        $this->assertGreaterThan($neutral, $down);
        $this->assertGreaterThan(0.05, $down);
        $this->assertLessThan(1.0, $down);
        // the reference methods: the forecast-over-base ratio is MAIIC's scalar
        $post = $c->apply(TransmissionMethodCatalogue::REFERENCE, 0.05, ['reference_method' => 'fli_adj_FDH_ByChange', 'reference_ctx' => ['macro_forecast' => 1.1, 'macro_base' => 1.0, 'correlation_r' => 0.5]]);
        $this->assertEqualsWithDelta(0.05 * (1 + 0.5 * 0.1), $post, 1e-9);
    }

    public function test_an_undefined_input_declines_and_the_result_is_floored_and_capped(): void
    {
        $c = $this->catalogue();
        $this->assertNull($c->apply(TransmissionMethodCatalogue::SCALAR, 0.05, []));
        $this->assertNull($c->apply(TransmissionMethodCatalogue::REFERENCE, 0.05, ['reference_method' => 'fli_adj_FDH_ByChange', 'reference_ctx' => ['macro_forecast' => 1.1, 'macro_base' => 0.0, 'correlation_r' => 0.5]]));
        $this->assertNull($c->apply(TransmissionMethodCatalogue::VASICEK, 0.0, ['rho' => 0.12, 'z' => -1]));
        $this->assertNull($c->apply('No such method', 0.05, []));
        $this->assertSame(1.0, $c->apply(TransmissionMethodCatalogue::SCALAR, 0.6, ['adjustment' => 1.0]));
        $this->assertSame(0.0, $c->apply(TransmissionMethodCatalogue::SCALAR, 0.6, ['adjustment' => -2.0]));
        $this->assertNull(AdjustmentMethods::compute('annual_change_inStatistic', ['annual_current' => 1, 'annual_prior' => 0])['value']);
    }

    public function test_the_cards_check_their_preconditions_live_with_the_deciding_figure(): void
    {
        $cards = collect($this->catalogue()->cards('2025-12'))->keyBy('key');
        $this->assertCount(5, $cards);
        $scalar = $cards[TransmissionMethodCatalogue::SCALAR];
        $this->assertTrue($scalar['seeded']);
        $this->assertTrue($scalar['in_force']);
        $this->assertFalse($scalar['available']); // no fit and no overlay in the fixture
        $this->assertStringContainsString('no approved fit and no overlay', $scalar['preconditions'][0]['figure']);
        $logit = $cards[TransmissionMethodCatalogue::LOGIT];
        $this->assertFalse($logit['available']);
        $this->assertStringContainsString('3 months held, 36 needed', $logit['preconditions'][0]['figure']);
        $vasicek = $cards[TransmissionMethodCatalogue::VASICEK];
        $this->assertSame('not yet governed', $vasicek['preconditions'][0]['figure']);
        foreach ($cards as $card) {
            $this->assertNotEmpty($card['what']);
            $this->assertNotEmpty($card['formula']);
            $this->assertNotEmpty($card['implies']);
        }
    }

    public function test_the_normal_distribution_round_trips(): void
    {
        foreach ([0.001, 0.05, 0.5, 0.95, 0.999] as $p) {
            $this->assertEqualsWithDelta($p, TransmissionMethodCatalogue::phi(TransmissionMethodCatalogue::phiInv($p)), 1e-6);
        }
    }
}
