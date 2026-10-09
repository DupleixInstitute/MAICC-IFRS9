<?php

namespace Tests\Feature\Regression;

use App\Models\CreditLossDefinition;
use App\Models\LoanPortfolio;
use App\Models\RegressionModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The legacy regression chain after the system audit of 9 October 2026
 * (finding M6): its routes sit behind the reports permission, its one-click
 * approval is maker-checker (the trainer cannot approve their own model)
 * and is audit-logged, and the menu's "Regression Analysis" leads to FLI
 * Adjustments. The legacy code stays for the models already trained.
 */
class LegacyRegressionApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function model(User $trainer): RegressionModel
    {
        $portfolio = LoanPortfolio::create(['name' => 'Test book', 'active' => true, 'created_by_id' => $trainer->id]);
        $definition = CreditLossDefinition::first() ?? CreditLossDefinition::create(['code' => 'NPL', 'name' => 'NPL ratio']);

        return RegressionModel::create([
            'name' => 'Legacy fit', 'type' => 'pd', 'portfolio_id' => $portfolio->id, 'dep_var_id' => $definition->id,
            'indep_vars' => [1], 'coeffs' => ['intercept' => 0.1, '1' => 0.5], 'r_squared' => 0.6, 'adj_r_squared' => 0.55,
            'stats' => [], 'train_start' => '2024-01-01', 'train_end' => '2025-12-31', 'train_periods' => 24, 'created_by' => $trainer->id,
        ]);
    }

    public function test_the_trainer_cannot_approve_their_own_model_and_a_second_person_can_with_an_audit_row(): void
    {
        $trainer = $this->admin();
        $checker = $this->admin();
        $model = $this->model($trainer);

        $this->actingAs($trainer)->from('/regression/' . $model->id)->patch('/regression/' . $model->id . '/approve')->assertRedirect('/regression/' . $model->id)->assertSessionHas('error');
        $this->assertFalse($model->fresh()->is_approved);
        $this->assertSame(0, DB::table('audit_logs')->where('action', 'Regression Model Approved')->count());

        $this->actingAs($checker)->from('/regression/' . $model->id)->patch('/regression/' . $model->id . '/approve')->assertRedirect('/regression/' . $model->id)->assertSessionHas('success');
        $this->assertTrue($model->fresh()->is_approved);
        $log = DB::table('audit_logs')->where('action', 'Regression Model Approved')->first();
        $this->assertNotNull($log);
        $this->assertSame($checker->id, (int) $log->user_id);
        $this->assertSame($model->id, (int) $log->entity_id);
        $this->assertSame($trainer->id, (int) json_decode($log->new_values, true)['trained_by']);
    }

    public function test_the_legacy_routes_need_the_reports_permission(): void
    {
        $user = User::factory()->create();
        $user->syncRoles([]);
        $user->syncPermissions([]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user)->get('/regression')->assertForbidden();
        $this->actingAs($user)->get('/fli-adj/external')->assertForbidden();
        foreach (['regression.index', 'regression.approve', 'fli.external.index', 'fli.external.update-loanbook', 'fli.regression.index'] as $name) {
            $this->assertContains('permission:reports.ifrs9', Route::getRoutes()->getByName($name)->gatherMiddleware(), $name);
        }
    }

    public function test_the_menu_sends_regression_analysis_to_fli_adjustments(): void
    {
        $leaf = null;
        $walk = function (array $items) use (&$walk, &$leaf) {
            foreach ($items as $item) {
                if (($item['name'] ?? '') === 'Regression Analysis') {
                    $leaf = $item;
                }
                if (! empty($item['children'])) {
                    $walk($item['children']);
                }
            }
        };
        $walk(config('menu.admin'));
        $this->assertNotNull($leaf);
        $this->assertSame('fli-adjustments.index', $leaf['route']);
    }
}
