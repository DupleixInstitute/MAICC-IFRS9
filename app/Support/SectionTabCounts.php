<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The record count shown on each tab of a menu section (config/menu.php):
 * the number of rows the tab's screen lists. Only the section the user is
 * in is counted, each table once a minute. A tab whose screen is an analysis
 * or a form rather than a list has no count.
 */
class SectionTabCounts
{
    /** Tab route => the table its screen lists. */
    private const TABLES = [
        'portfolios.index' => 'loan_portfolios',
        'groups.index' => 'loan_product_categories',
        'industry_types.index' => 'industry_types',
        'imports.index' => 'imports',
        'eir-feed.index' => 'ebanker_loads',
        'eir-takeon.index' => 'takeon_blocks',
        'eir-feed.index?tab=loads' => 'ebanker_loads',
        'eir-feed.index?tab=builds' => 'loan_book_builds',
        'eir-feed.index?tab=queries' => 'ebanker_queries',
        'eir-takeon.index?tab=blocks' => 'takeon_blocks',
        'eir-takeon.index?tab=population' => 'contract_takeon',
        'collateral.register.index' => 'collateral_registers',
        'collateral.allocations.index' => 'collateral_allocations',
        'collateral.types.index' => 'collateral_types',
        'eir-data.index' => 'contract_eir',
        'eir-data.index?tab=contracts' => 'contract_eir',
        'eir-data.index?tab=cashflows' => 'contract_cashflow_schedule',
        'eir-data.index?tab=schedules' => 'contract_eir',
        'eir-drawdowns.index' => 'contract_disbursements',
        'eir-reference-rates.index' => 'reference_rate_series',
        'eir-accounting-rules.index' => 'eir_accounting_rules',
        'eir-fee-classification.index' => 'contract_fees',
        'eir-calculations.index' => 'contract_eir',
        'eir-reconciliation.index' => 'gl_interest_postings',
        'sicr-groups.index' => 'finance_sicr_groups',
        'sicr-items.index' => 'finance_sicr_items',
        'sicr-triggers.index' => 'finance_sicr_triggers',
        'transition-profiles.index' => 'transition_profile_definitions',
        'transition-matrices.index' => 'transition_matrices',
        'transition-matrix-cummulative.index' => 'transition_matrix_cummulative',
        'internal-grading.profiles' => 'internal_grade_profiles',
        'loss-given-default.index' => 'loss_given_default',
        'lgd-cummulative.index' => 'loss_given_default_cummulative',
        'macro-statistics.index' => 'macro_statistics',
        'macro-forecast-weighted.index' => 'macro_forecast_weighted',
        'credit-loss-data.index' => 'macro_credit_loss_data',
        'fli-correlation.index' => 'analysis_runs',
        'fli-adjustments.index' => 'fli_fits',
        'scenario-sets.index' => 'governed_scenario_sets',
        'scenarios.profiles' => 'scenario_profiles',
        'fli-overlays.index' => 'fli_overlays',
        'fli.scenarios.index' => 'scenario_sets',
        'fli.external.list' => 'fli_adj',
        'users.index' => 'users',
        'users.roles.index' => 'roles',
        'audit-trail.index' => ['activity_log', 'audit_logs'],
        'compliance-audits.index' => 'compliance_audits',
    ];

    /** The tabs of the section the current route sits in, each with its count. */
    public static function annotate(array $menu, ?string $current): array
    {
        if (! $current) {
            return $menu;
        }
        foreach ($menu as $i => $item) {
            if (! empty($item['tabs']) && self::inSection($item['tabs'], $current)) {
                foreach ($item['tabs'] as $t => $tab) {
                    $key = $tab['route'] . (! empty($tab['params']['tab']) ? '?tab=' . $tab['params']['tab'] : '');
                    $menu[$i]['tabs'][$t]['count'] = self::count($key);
                }
            }
            if (! empty($item['children'])) {
                $menu[$i]['children'] = self::annotate($item['children'], $current);
            }
        }

        return $menu;
    }

    private static function inSection(array $tabs, string $current): bool
    {
        foreach ($tabs as $tab) {
            $route = $tab['route'];
            if ($route === $current) {
                return true;
            }
            if (str_ends_with($route, '.index') && str_starts_with($current, substr($route, 0, -strlen('index')))) {
                return true;
            }
        }

        return false;
    }

    private static function count(string $route): ?int
    {
        if (! isset(self::TABLES[$route])) {
            return null;
        }

        return Cache::remember('section_tab_count.' . $route, 60, function () use ($route) {
            $total = 0;
            foreach ((array) self::TABLES[$route] as $table) {
                if (! Schema::hasTable($table)) {
                    return null;
                }
                $q = DB::table($table);
                if (Schema::hasColumn($table, 'deleted_at')) {
                    $q->whereNull('deleted_at');
                }
                $total += $q->count();
            }

            return $total;
        });
    }
}
