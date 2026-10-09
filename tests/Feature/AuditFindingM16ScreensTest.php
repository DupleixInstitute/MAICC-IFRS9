<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The two screens the system audit of 9 October 2026 (finding M16) asked
 * for, the Correlation Finder and the Auditor Pack, render for an
 * administrator and refuse a user without the permission; the Macro
 * Statistics permissions exist and open the screen. The pages are fetched
 * as Inertia does (the X-Inertia header), so the test reads the component
 * and its props and needs no compiled front end.
 */
class AuditFindingM16ScreensTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function nobody(): User
    {
        $user = User::factory()->create();
        $user->syncRoles([]);
        $user->syncPermissions([]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function inertia(User $user, string $url): TestResponse
    {
        $version = (string) (new HandleInertiaRequests())->version(request());
        $response = $this->actingAs($user)->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Requested-With' => 'XMLHttpRequest'])->get($url);
        $this->flushHeaders(); // the Inertia headers must not ride on the plain requests that follow

        return $response;
    }

    public function test_the_correlation_finder_renders_for_an_administrator_with_the_latest_sweep(): void
    {
        DB::table('analysis_runs')->insert(['run_type' => 'correlation', 'reporting_period' => '202608', 'inputs_hash' => str_repeat('a', 64), 'status' => 'complete', 'run_at' => now()]);
        $runId = (int) DB::table('analysis_runs')->max('id');
        DB::table('fli_suggestions')->insert([
            ['run_id' => $runId, 'statistic_code' => 'CPI', 'proxy_code' => 'NPL_RATIO', 'lag_months' => 3, 'score' => 0.8, 'r_squared' => 0.61, 'sign_ok' => 1, 'verdict' => 'recommended', 'reason' => '[recommended] sign as expected', 'created_at' => now()],
            ['run_id' => $runId, 'statistic_code' => 'GDP', 'proxy_code' => 'NPL_RATIO', 'lag_months' => 0, 'score' => 0.1, 'r_squared' => 0.02, 'sign_ok' => 0, 'verdict' => 'rejected', 'reason' => '[rejected] wrong sign', 'created_at' => now()],
        ]);
        $rel = (int) DB::table('fli_relationships')->insertGetId(['statistic_code' => 'CPI', 'proxy_code' => 'NPL_RATIO', 'expected_sign' => 'positive', 'r2_cutoff' => 0.3, 'method' => 'pearson', 'mode' => 'single', 'lag_months' => 3, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('fli_fits')->insert(['fli_relationship_id' => $rel, 'reporting_period' => '202608', 'slope' => 0.012, 'intercept' => 0.03, 'correlation_r' => 0.78, 'r_squared' => 0.61, 'p_value' => 0.01, 'n_obs' => 24, 'sign_ok' => 1, 'verdict' => 'applied', 'approval_status' => 'NONE', 'computed_at' => now()]);

        $r = $this->inertia($this->admin(), '/fli-correlation?period=2026-08');
        $r->assertOk();
        $page = $r->json();
        $this->assertSame('FLI/Correlation', $page['component']);
        $this->assertSame('2026-08', $page['props']['period']);
        $this->assertSame($runId, $page['props']['run']['id']);
        $this->assertCount(2, $page['props']['suggestions']);
        $this->assertSame('CPI', $page['props']['suggestions'][0]['statistic_code'], 'ranked by score, strongest first');
        $this->assertSame('recommended', $page['props']['suggestions'][0]['verdict']);
        $this->assertCount(1, $page['props']['fits']);
        $this->assertSame('applied', $page['props']['fits'][0]['verdict']);
        $this->assertSame(24, (int) $page['props']['fits'][0]['n_obs']);
        $this->assertTrue($page['props']['canRun']);
    }

    public function test_the_correlation_finder_renders_with_nothing_swept_and_the_run_needs_the_run_permission(): void
    {
        $r = $this->inertia($this->admin(), '/fli-correlation');
        $r->assertOk();
        $this->assertSame('FLI/Correlation', $r->json('component'));
        $this->assertNull($r->json('props.run'));
        $this->assertSame([], $r->json('props.suggestions'));

        $this->actingAs($this->nobody())->post('/fli-correlation/run', ['period' => '2026-08'])->assertForbidden();
        $this->actingAs($this->admin())->post('/fli-correlation/run', ['period' => 'not-a-period'])->assertSessionHasErrors('period');
    }

    /** The button calls the console command in the request and the outcome, good or bad, lands on the audit log. */
    public function test_the_run_button_calls_the_finder_and_logs_the_outcome(): void
    {
        $this->actingAs($this->admin())->from('/fli-correlation')->post('/fli-correlation/run', ['period' => '2026-08']);
        $log = DB::table('audit_logs')->where('action', 'Correlation Finder Run')->where('reporting_period', '2026-08')->first();
        $this->assertNotNull($log, 'the run is audit-logged whether it swept or stopped');
        $this->assertArrayHasKey('exit_code', json_decode($log->new_values, true));
    }

    public function test_the_auditor_pack_screen_lists_the_packs_with_their_manifest_counts_and_hands_one_out(): void
    {
        $dir = storage_path('app/auditor-packs');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $file = $dir . '/MAIIC auditor pack 2099-01.zip';
        $zip = new \ZipArchive();
        $zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('Baselines 2099-01.json', '{}');
        $zip->addFromString('manifest.json', json_encode(['pack' => 'test', 'built_at' => '2099-02-01 09:00:00', 'files' => [['file' => 'Baselines 2099-01.json', 'bytes' => 2, 'sha256' => hash('sha256', '{}')]]]));
        $zip->close();
        try {
            $r = $this->inertia($this->admin(), '/auditor-pack');
            $r->assertOk();
            $this->assertSame('Reports/AuditorPack', $r->json('component'));
            $packs = collect($r->json('props.packs'))->keyBy('file');
            $this->assertTrue($packs->has('MAIIC auditor pack 2099-01.zip'));
            $pack = $packs['MAIIC auditor pack 2099-01.zip'];
            $this->assertSame('2099-01', $pack['period']);
            $this->assertSame(1, $pack['files']);
            $this->assertSame(1, $pack['with_sha256']);
            $this->assertSame('2099-02-01 09:00:00', $pack['built_at']);
            $this->assertTrue($r->json('props.canExport'));

            $d = $this->actingAs($this->admin())->get('/auditor-pack/download/' . rawurlencode('MAIIC auditor pack 2099-01.zip'));
            $d->assertOk();
            $this->assertStringContainsString('.zip', (string) $d->headers->get('content-disposition'));
            $this->assertSame(1, DB::table('audit_logs')->where('action', "Auditor's Pack Downloaded")->count());

            // only a pack the command named is served
            $this->actingAs($this->admin())->get('/auditor-pack/download/' . rawurlencode('../.env'))->assertNotFound();
            $this->actingAs($this->nobody())->get('/auditor-pack')->assertForbidden();
            $this->actingAs($this->nobody())->post('/auditor-pack/build', ['period' => '2099-01'])->assertForbidden();
        } finally {
            @unlink($file);
        }
    }

    public function test_the_macro_statistics_permissions_exist_and_open_the_screen(): void
    {
        $this->assertTrue(Permission::where('name', 'macro.view')->exists());
        $this->assertTrue(Permission::where('name', 'macro.manage')->exists());
        $admin = $this->admin();
        $this->assertTrue($admin->can('macro.view') && $admin->can('macro.manage'));

        $this->inertia($admin, '/macro-statistics')->assertOk();
        $this->actingAs($this->nobody())->get('/macro-statistics')->assertForbidden();

        // eir.govern still opens the commit, as the audit asked (finding M16)
        $middleware = array_column((new \App\Http\Controllers\MacroStatisticsController())->getMiddleware(), 'middleware');
        $this->assertContains('permission:macro.manage|eir.govern', $middleware);
        $this->assertContains('permission:macro.view|eir.view', $middleware);
    }

    public function test_the_help_chapters_carry_the_suite_group_names(): void
    {
        $titles = DB::table('help_categories')->where('manual', 'user')->pluck('title')->all();
        $this->assertContains('Financial Modelling', $titles);
        $this->assertContains('Financial Modelling: the ECL', $titles);
        $this->assertNotContains('IFRS 9 Model Setup', $titles);
        $this->assertNotContains('ECL Processing', $titles);

        // an installed manual still carrying the old titles is renamed by the seeder, its articles kept
        $id = (int) DB::table('help_categories')->where('manual', 'user')->where('title', 'Financial Modelling')->value('id');
        $articles = DB::table('help_articles')->where('help_category_id', $id)->count();
        DB::table('help_categories')->where('id', $id)->update(['title' => 'IFRS 9 Model Setup', 'slug' => 'ifrs-9-model-setup']);
        (new \Database\Seeders\HelpContentSeeder())->run();
        $this->assertSame('Financial Modelling', DB::table('help_categories')->where('id', $id)->value('title'));
        $this->assertSame($articles, DB::table('help_articles')->where('help_category_id', $id)->count());
    }
}
