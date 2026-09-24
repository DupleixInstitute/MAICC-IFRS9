<?php

namespace Tests\Feature\Eir;

use App\Http\Controllers\EirCoverageController;
use App\Http\Controllers\EirDataController;
use App\Http\Controllers\EirGovernanceController;
use App\Http\Controllers\EirReconciliationController;
use App\Models\GovernanceSetting;
use App\Models\User;
use App\Services\Eir\GovernanceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Eir\Concerns\CreatesGovernanceSchema;
use Tests\TestCase;

/**
 * The Governance Centre controller, called directly (the same pattern as
 * EirReconciliationRevenueRunTest) so the refusal and flash messages are
 * tested without standing up the full permission tables. The permission each
 * controller demands is asserted from its registered middleware.
 */
class EirGovernanceControllerTest extends TestCase
{
    use CreatesGovernanceSchema;

    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'activitylog.enabled' => false]);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->string('email'); $t->string('password')->nullable(); $t->timestamps(); });
        Schema::create('roles', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->string('guard_name'); $t->timestamps(); });
        Schema::create('model_has_roles', function (Blueprint $t) { $t->integer('role_id'); $t->string('model_type'); $t->integer('model_id'); });
        Schema::create('audit_logs', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id')->nullable(); $t->string('action'); $t->string('entity_type'); $t->integer('entity_id')->nullable(); $t->string('scope')->nullable(); $t->string('reporting_period')->nullable(); $t->integer('rows_affected')->nullable(); $t->text('old_values')->nullable(); $t->text('new_values')->nullable(); $t->text('meta')->nullable(); $t->string('ip_address')->nullable(); $t->string('user_agent')->nullable(); $t->timestamps(); });
        DB::table('users')->insert([
            ['id' => 10, 'name' => 'Maker', 'email' => 'maker@example.test', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 20, 'name' => 'Checker', 'email' => 'checker@example.test', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 30, 'name' => 'Admin', 'email' => 'admin@example.test', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('roles')->insert(['id' => 1, 'name' => 'admin', 'guard_name' => 'web', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('model_has_roles')->insert(['role_id' => 1, 'model_type' => User::class, 'model_id' => 30]);
        $this->createGovernanceSchema();
        $this->seedGovernanceDefaults();
    }

    private function propose(int $userId, array $overrides = []): RedirectResponse
    {
        $this->actingAs(User::findOrFail($userId));
        $request = Request::create('/eir-governance/propose', 'POST', $overrides + [
            'key' => 'day_count',
            'value' => '30/360',
            'effective_from' => '2026-03-01',
            'reason' => 'Agreed with MAIIC at the September walkthrough.',
        ]);

        return app(EirGovernanceController::class)->propose($request, app(GovernanceService::class));
    }

    private function approve(int $userId, GovernanceSetting $setting): RedirectResponse
    {
        $this->actingAs(User::findOrFail($userId));

        return app(EirGovernanceController::class)->approve($setting, app(GovernanceService::class));
    }

    public function test_the_governance_routes_demand_the_govern_permission_and_the_read_only_screens_the_view_permission(): void
    {
        $middleware = fn ($controller) => array_column((new $controller())->getMiddleware(), 'middleware');

        $this->assertContains('permission:eir.govern', $middleware(EirGovernanceController::class));
        $this->assertContains('permission:eir.view', $middleware(EirDataController::class));
        $this->assertContains('permission:eir.view', $middleware(EirCoverageController::class));
        $this->assertContains('permission:eir.view', $middleware(EirReconciliationController::class));

        // Running the revenue from the reconciliation page keeps the permission it had.
        $reconciliation = collect((new EirReconciliationController())->getMiddleware());
        $this->assertSame(['index'], $reconciliation->firstWhere('middleware', 'permission:eir.view')['options']['only']);
        $this->assertSame(['index'], $reconciliation->firstWhere('middleware', 'permission:settings')['options']['except']);
    }

    public function test_a_proposal_is_recorded_against_the_signed_in_user_and_flashes_the_effective_date(): void
    {
        $response = $this->propose(10);

        $this->assertStringContainsString('2026-03-01', session('success'));
        $this->assertStringContainsString('second person approves', session('success'));
        $row = GovernanceSetting::where('key', 'day_count')->where('status', 'PROPOSED')->first();
        $this->assertSame('30/360', $row->value);
        $this->assertSame(10, (int) $row->set_by);
        $this->assertSame(10, (int) DB::table('audit_logs')->where('action', 'EIR Governance Setting Proposed')->value('user_id'));
    }

    public function test_a_refused_proposal_comes_back_as_a_named_error_not_an_exception(): void
    {
        $this->propose(10, ['effective_from' => '2025-01-01']);

        $this->assertStringContainsString('later than 2025-01-01', session('errors')->first('governance'));
        $this->assertSame(0, GovernanceSetting::where('status', 'PROPOSED')->count());
    }

    public function test_the_form_is_validated_before_the_service_is_reached(): void
    {
        $this->expectException(ValidationException::class);
        $this->propose(10, ['reason' => 'short']);
    }

    public function test_approval_follows_maker_checker_with_an_administrator_override(): void
    {
        $this->propose(10);
        $proposal = GovernanceSetting::where('status', 'PROPOSED')->firstOrFail();

        $this->approve(10, $proposal);
        $this->assertStringContainsString('cannot approve', session('errors')->first('governance'));
        $this->assertSame('PROPOSED', $proposal->fresh()->status);

        $this->approve(20, $proposal);
        $this->assertStringContainsString('"30/360" from 2026-03-01', session('success'));
        $this->assertSame('APPROVED', $proposal->fresh()->status);
        $this->assertSame(20, (int) $proposal->fresh()->approved_by);

        // An administrator may approve their own proposal; the override is recorded.
        $this->propose(30, ['value' => 'ACT/365', 'effective_from' => '2026-06-01']);
        $own = GovernanceSetting::where('status', 'PROPOSED')->firstOrFail();
        $this->approve(30, $own);
        $this->assertSame('APPROVED', $own->fresh()->status);
        $meta = json_decode(DB::table('audit_logs')->where('action', 'EIR Governance Setting Approved')->orderByDesc('id')->value('meta'), true);
        $this->assertTrue($meta['admin_override']);
        $this->assertSame(30, $meta['approved_by']);
    }
}
