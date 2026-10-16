<?php

namespace Tests\Feature\Eir;

use App\Http\Controllers\EirRestructureController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Eir\Concerns\CreatesGovernanceSchema;
use Tests\TestCase;

/**
 * The Restructures & Resets screen (EIR Data): its routes, its permissions
 * (anyone with eir.view reads it; every action needs eir.govern), its tab in
 * the menu, and the classification and approval from the screen.
 */
class RestructureUiTest extends TestCase
{
    use CreatesGovernanceSchema;

    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();
        // these tests are of other behaviour; the evidence a workflow needs before approval is tested in ApprovalWorkflowsTest
        config(['approvals.require_evidence' => false]);
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'activitylog.enabled' => false]);
        DB::purge('sqlite'); DB::reconnect('sqlite');
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->string('email'); $t->string('password')->nullable(); $t->timestamps(); });
        DB::table('users')->insert([['id' => 10, 'name' => 'Maker', 'email' => 'm@example.test', 'created_at' => now(), 'updated_at' => now()], ['id' => 11, 'name' => 'Checker', 'email' => 'c@example.test', 'created_at' => now(), 'updated_at' => now()]]);
        $this->createGovernanceSchema();
        $this->seedGovernanceDefaults();
        Schema::create('loan_books', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->string('reporting_period'); $t->string('customer_name')->nullable(); $t->string('ifrs9stage_post_qualitative')->nullable(); $t->double('carrying_amount')->default(0); });
        Schema::create('contract_eir', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id')->unique(); $t->string('rate_type')->default('FIXED'); $t->timestamps(); });
        Schema::create('contract_cashflow_schedule', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->smallInteger('schedule_version')->default(1); $t->date('effective_from')->nullable(); $t->date('due_date'); $t->double('principal_due')->default(0); $t->double('interest_due')->default(0); $t->double('fee_due')->default(0); $t->string('schedule_source')->nullable(); $t->timestamps(); });
        Schema::create('rate_reset_events', function (Blueprint $t) { $t->increments('id'); $t->string('contract_id'); $t->date('reset_date'); $t->decimal('old_reference_rate', 8, 5); $t->decimal('new_reference_rate', 8, 5); $t->unsignedSmallInteger('new_schedule_version'); $t->integer('recorded_by')->nullable(); $t->timestamps(); });
        (require base_path('database/migrations/2026_10_14_230000_restructure_register_and_modifications.php'))->up();
        require_once base_path('database/migrations/2021_02_21_070651_create_permission_tables.php');
        (new \CreatePermissionTables())->up();
        DB::table('restructure_events')->insert(['id' => 5, 'detection_key' => 'CHART|A|2026-06-30', 'contract_id' => 'A', 'customer_name' => 'Pinnacle', 'event_date' => '2026-06-30',
            'source' => 'CHART_REPLACED', 'suggested_class' => 'MODIFICATION', 'suggestion_reason' => 'capitalised', 'status' => 'CANDIDATE',
            'new_flows' => json_encode([['due_date' => '2026-07-19', 'amount' => 60, 'principal' => 50, 'interest' => 10]]), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function hit(int $user, string $method, array $body, ...$args)
    {
        $this->actingAs(User::findOrFail($user));
        $request = Request::create('/eir-restructures', 'POST', $body);
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => User::findOrFail($user));
        app()->instance('request', $request);

        return app()->call([app(EirRestructureController::class), $method], ["request" => $request] + ($args === [] ? [] : ["id" => $args[0]]));
    }

    public function test_the_routes_menu_tab_and_permissions(): void
    {
        foreach (['index', 'detect', 'propose', 'approve', 'close', 'resets.propose', 'resets.approve', 'resets.close'] as $r) {
            $this->assertTrue(Route::has("eir-restructures.{$r}"), $r);
        }
        $govern = collect((new EirRestructureController())->getMiddleware())->firstWhere('middleware', 'permission:eir.govern');
        $this->assertSame(['index'], $govern['options']['except']);
        $this->assertStringContainsString("'eir-restructures.index'", file_get_contents(config_path('menu.php')));
        $this->assertFileExists(resource_path('js/Pages/Eir/Restructures/Index.vue'));
    }

    public function test_the_screen_lists_the_candidates_and_a_classification_passes_two_people(): void
    {
        $page = $this->hit(10, 'index', []);
        $props = (fn () => $this->props)->call($page);
        $this->assertSame('Eir/Restructures/Index', (fn () => $this->component)->call($page));
        $this->assertSame(1, $props['summary']['candidates']);
        $this->assertArrayNotHasKey('new_flows', $props['events'][0], 'the flows stay on the server');
        $this->assertSame(1, $props['events'][0]['new_flows_count']);

        $this->hit(10, 'propose', ['classification' => 'MODIFICATION', 'reason' => 'agreed with the borrower'], 5);
        $this->assertSame('PROPOSED', DB::table('restructure_events')->where('id', 5)->value('status'));
        $this->hit(10, 'approve', [], 5);
        $this->assertStringContainsString('cannot approve', (string) session('error'));
        $this->hit(11, 'approve', [], 5);
        $this->assertSame('CONFIRMED', DB::table('restructure_events')->where('id', 5)->value('status'));
        $this->assertStringContainsString('re-run the EIR revenue from 2026-06', (string) session('success'));
    }
}
