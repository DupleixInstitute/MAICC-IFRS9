<?php

namespace Tests\Feature\Ebanker;

use App\Services\Ebanker\GlFeeBuildService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * eir:build-gl-fees and the fee GL gate (spec v4 6.4, "a failure refuses";
 * documentation audit of 14 October 2026): a month whose fee GL movement
 * differs from the trial balance fails the command, with the months listed,
 * so the month-end stops at that step. A month without a trial balance is
 * listed, not failed.
 */
class GlFeeGateCommandTest extends TestCase
{
    protected $seed = false;

    private function build(array $months): void
    {
        $agree = count(array_filter($months, fn ($m) => $m['status'] === 'AGREE'));
        $differ = count(array_filter($months, fn ($m) => $m['status'] === 'DIFFERENCE'));
        $result = [
            'legs' => 3,
            'assigned' => ['lines' => 2, 'amount' => 200.0, 'by_rule' => ['ONLY_LOAN_IN_BATCH' => 2]],
            'held' => ['lines' => 1, 'amount' => 50.0, 'by_reason' => ['NO_LOAN_LEG' => 1]],
            'loaded' => ['loaded_rows' => 2],
            'precedence' => ['superseded_lines' => 0, 'order' => ['E-Banker GL fee posting'], 'exceptions' => []],
            'gate' => ['agree' => $agree, 'differ' => $differ, 'no_tb' => count($months) - $agree - $differ, 'months' => $months],
        ];
        $this->app->instance(GlFeeBuildService::class, new class($result) extends GlFeeBuildService {
            public function __construct(private array $result) {}
            public function build(?int $userId = null): array { return $this->result; }
        });
    }

    public function test_a_month_that_differs_fails_the_build_with_the_months_named(): void
    {
        $this->build([
            ['gl' => '4873', 'month' => '2026-08', 'status' => 'AGREE', 'gl_04a' => 100.0, 'trial_balance' => 100.0],
            ['gl' => '4873', 'month' => '2026-09', 'status' => 'DIFFERENCE', 'gl_04a' => 150.0, 'trial_balance' => 100.0],
            ['gl' => '4871', 'month' => '2026-07', 'status' => 'DIFFERENCE', 'gl_04a' => 10.0, 'trial_balance' => 0.0],
        ]);

        $code = Artisan::call('eir:build-gl-fees', ['--user' => 1]);
        $out = Artisan::output();

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Fee GL gate failed', $out);
        $this->assertStringContainsString('2026-07, 2026-09', $out);
    }

    public function test_months_that_agree_or_wait_for_a_trial_balance_pass(): void
    {
        $this->build([
            ['gl' => '4873', 'month' => '2026-08', 'status' => 'AGREE', 'gl_04a' => 100.0, 'trial_balance' => 100.0],
            ['gl' => '4873', 'month' => '2026-10', 'status' => 'NO_TB', 'gl_04a' => 100.0, 'trial_balance' => null],
        ]);

        $this->assertSame(0, Artisan::call('eir:build-gl-fees', ['--user' => 1]));
    }
}
