<?php

namespace Tests\Feature\Pd;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PD bases compared (IFRS 9 Model Setup, PD Model): the booked run and the comparison runs of a
 * month side by side, the booked basis marked, and the pooled book's history; measuring again
 * needs eir.govern.
 */
class PdBasesScreenTest extends TestCase
{
    use RefreshDatabase;

    private function segmentRun(string $id, string $basis, string $status, array $cells): void
    {
        DB::table('segment_parameter_runs')->insert(['run_id' => $id, 'measure' => 'PD', 'reporting_period' => '2026-09', 'basis' => $basis, 'window_start' => '2025-09', 'window_end' => '2026-09',
            'window_months' => 12, 'status' => $status, 'failure_reason' => $status === 'comparison' ? 'Comparison only' : null, 'loans_updated' => $status === 'applied' ? 10 : 0, 'created_at' => now(), 'updated_at' => now()]);
        foreach ($cells as [$key, $parent, $stage, $rate, $cellStatus]) {
            DB::table('segment_parameter_results')->insert(['run_id' => $id, 'measure' => 'PD', 'reporting_period' => '2026-09', 'segment_key' => $key, 'segment_label' => $key, 'parent_key' => $parent,
                'stage' => $stage, 'cohort_loans' => 20, 'cohort_balance' => 1000, 'default_loans' => 4, 'default_balance' => 200, 'observed_rate' => $rate, 'annual_rate' => $rate,
                'status' => $cellStatus, 'applied_from_key' => $key, 'applied_rate' => $rate, 'period_loans' => 5, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function test_the_screen_shows_every_basis_with_the_booked_one_marked(): void
    {
        \Spatie\Permission\Models\Permission::findOrCreate('eir.view', 'web');
        \Spatie\Permission\Models\Permission::findOrCreate('eir.govern', 'web');
        DB::table('users')->insert(['id' => 601, 'name' => 'Reader', 'email' => 'r@x.test', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        $reader = User::find(601);
        $reader->givePermissionTo('eir.view');
        $this->segmentRun('booked', 'book', 'applied', [['book', null, 1, 0.2, 'ok'], ['book', null, 2, 0.4, 'ok']]);
        $this->segmentRun('c-book', 'book', 'comparison', [['book', null, 1, 0.2, 'ok'], ['book', null, 2, 0.4, 'ok']]);
        $this->segmentRun('c-portfolio', 'portfolio', 'comparison', [['book', null, 1, 0.2, 'ok'], ['portfolio:1', 'book', 1, 0.1, 'own']]);

        $this->actingAs($reader)->get(route('pd-bases.index', ['period' => '2026-09']))->assertOk()
            ->assertInertia(fn ($page) => $page->component('PD/Bases')->where('booked.basis', 'book')->has('bases', 2)
                ->where('bases.0.basis', 'book')->where('bases.0.booked', true)->where('bases.1.basis', 'portfolio')->where('bases.1.booked', false)
                ->where('bases.1.own_cells', 1)->has('history', 1)->where('history.0.s1', 0.2)->where('canGovern', false));
        // measuring again is for eir.govern
        $this->actingAs($reader)->post(route('pd-bases.measure'), ['period' => '2026-09'])->assertForbidden();
    }
}
