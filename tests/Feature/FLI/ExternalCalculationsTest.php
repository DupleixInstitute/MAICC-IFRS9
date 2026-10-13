<?php

namespace Tests\Feature\FLI;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * External Calculations and Calculation History (FLI module specification
 * v1). The older one-slope endpoints that wrote fli_adj straight onto the
 * loans (audit finding M6) are gone; an externally calibrated model is
 * proposed here and reaches the loans only as an approved model version.
 */
class ExternalCalculationsTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $permissions = []): User
    {
        $u = User::create(['name' => 'Test User ' . uniqid(), 'email' => uniqid() . '@example.com', 'password' => bcrypt('password'), 'email_verified_at' => now()]);
        $u->forceFill(['active' => 1])->save();
        foreach ($permissions as $p) {
            Permission::findOrCreate($p, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        if ($permissions !== []) {
            $u->givePermissionTo($permissions);
        }

        return $u;
    }

    public function test_the_page_and_the_history_open(): void
    {
        $u = $this->user(['eir.view', 'reports.ifrs9']);
        $this->actingAs($u)->get(route('fli.external.index'))->assertOk()->assertInertia(fn ($page) => $page->component('FLI/ExternalCalculations/Index')->has('preview'));
        $this->actingAs($u)->get(route('fli.external.list'))->assertOk()->assertInertia(fn ($page) => $page->component('FLI/ExternalCalculations/List')->has('snapshots')->has('events'));
    }

    public function test_the_old_endpoints_that_wrote_onto_the_loans_are_gone(): void
    {
        foreach (['fli.external.save-parameters', 'fli.external.generate', 'fli.external.save', 'fli.external.update-loanbook', 'fli.regression.index'] as $name) {
            $this->assertFalse(app('router')->has($name), "{$name} should no longer exist");
        }
    }

    public function test_proposing_an_external_model_needs_the_governance_permission(): void
    {
        $payload = ['statistic_code' => 'PLR', 'proxy_code' => 'STAGE3_SHARE', 'lag_months' => 9, 'slope' => 0.017, 'intercept' => -0.01,
            'calibrated_period' => '2026-08', 'effective_from' => '2026-09', 'evidence' => 'validation workbook'];
        $this->actingAs($this->user(['eir.view']))->post(route('fli.external.propose'), $payload)->assertForbidden();
        $this->assertSame(0, DB::table('fli_model_versions')->count());
    }
}
