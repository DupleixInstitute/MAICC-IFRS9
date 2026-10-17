<?php

namespace Tests\Feature\Ifrs9;

use App\Console\Commands\Stage3History;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ifrs9:stage3-history --replace: after a restage, a month's Stage 3 history allowance is cleared on MAIIC's book and
 * booked again on the loans now in Stage 3; without --replace the month is left alone; a month holding a full
 * month-end ECL is refused and nothing in it is touched; the Mega Farm programme is its own and never cleared.
 */
class Stage3HistoryReplaceTest extends TestCase
{
    use RefreshDatabase;

    private function loan(string $period, string $contract, string $stage, ?float $ecl, ?string $runId, string $gl = '1050101'): void
    {
        DB::table('loan_books')->insert(['contract_id' => $contract, 'reporting_period' => $period, 'reporting_year' => (int) substr($period, 0, 4), 'reporting_month' => (int) substr($period, 5, 2),
            'customer_id' => '1', 'external_identity_id' => 'TBA', 'product_code' => $gl, 'carrying_amount' => 1000, 'ifrs9stage_post_qualitative' => $stage,
            'ecl_value' => $ecl, 'ecl_value_discounted' => $ecl, 'ecl_calculation_run_id' => $runId, 'pd_prefli' => $ecl !== null ? 1 : null]);
    }

    private function projectionRun(string $id, string $method): void
    {
        DB::table('ecl_projection_runs')->insert(['run_id' => $id, 'reporting_period' => '2024-12', 'methodology_version' => $method, 'status' => 'COMPLETED', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_replace_clears_a_stale_history_allowance_and_refuses_a_full_month(): void
    {
        $this->projectionRun('hist-1', Stage3History::METHOD);
        $this->projectionRun('full-1', 'TIME_PHASED_V1');
        // 2024-12: a history allowance on a loan restaged since to Stage 1, and a Mega Farm loan of the programme's own
        $this->loan('2024-12', 'A', '1', 600, 'hist-1');
        $this->loan('2024-12', 'MF', '3', 900, 'mf-run', '1050301');
        // 2025-01: a full month-end ECL
        $this->loan('2025-01', 'B', '1', 50, 'full-1');

        Artisan::call('ifrs9:stage3-history', ['from' => '2024-12', 'to' => '2025-01', '--user' => 1]);
        $this->assertSame(600.0, (float) DB::table('loan_books')->where('contract_id', 'A')->value('ecl_value'), 'without --replace the month is left alone');

        Artisan::call('ifrs9:stage3-history', ['from' => '2024-12', 'to' => '2025-01', '--user' => 1, '--replace' => true]);
        $a = DB::table('loan_books')->where('contract_id', 'A')->first();
        $this->assertNull($a->ecl_value, 'the stale allowance on a loan now in Stage 1 is cleared');
        $this->assertNull($a->pd_prefli);
        $this->assertSame(900.0, (float) DB::table('loan_books')->where('contract_id', 'MF')->value('ecl_value'), 'the Mega Farm programme is never cleared');
        $this->assertSame(50.0, (float) DB::table('loan_books')->where('contract_id', 'B')->value('ecl_value'), 'a full month-end ECL is never cleared');
        $this->assertSame(0, DB::table('audit_logs')->where('action', 'Stage 3 History Allowance Cleared')->where('reporting_period', '2025-01')->count(), 'the full month is refused, nothing cleared');
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'Stage 3 History Allowance Cleared')->where('reporting_period', '2024-12')->count());
    }
}
