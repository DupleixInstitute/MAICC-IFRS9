<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The contract-aligned navigation, restored on 9 October 2026 and
 * consolidated into tabbed sections: every leaf and every tab names a
 * registered route, the entries keep their contract order, the new screens
 * sit in those entries, and the report-hub tiles are not menu items.
 */
class NavigationTest extends TestCase
{
    protected $seed = false;

    public function test_every_leaf_names_a_registered_route(): void
    {
        $missing = [];
        $this->walk(config('menu.admin'), function (array $item) use (&$missing) {
            if (empty($item['dropdown']) && ! empty($item['route']) && ! Route::has($item['route'])) {
                $missing[] = $item['name'] . ' -> ' . $item['route'];
            }
            foreach ($item['tabs'] ?? [] as $tab) {
                if (! Route::has($tab['route'])) {
                    $missing[] = $item['name'] . ' / ' . $tab['name'] . ' -> ' . $tab['route'];
                }
            }
        });
        $this->assertSame([], $missing, 'Menu leaves without a registered route: ' . implode(', ', $missing));
    }

    public function test_the_contract_entries_are_in_order(): void
    {
        $entries = array_map(fn ($g) => $g['name'], config('menu.admin'));
        $this->assertSame(['Dashboard', 'Workspace', 'Reports', 'Portfolio Setup', 'Customer & Loan Data', 'Collateral Management', 'EIR & Revenue Recognition', 'IFRS 9 Model Setup', 'ECL Processing', 'System Documentation', 'Administration'], $entries);
    }

    public function test_reports_is_one_entry_to_the_hub(): void
    {
        $reports = collect(config('menu.admin'))->firstWhere('name', 'Reports');
        $this->assertSame('ifrs9-reports.index', $reports['route']);
        $this->assertTrue(empty($reports['dropdown']));
    }

    public function test_the_new_screens_sit_in_the_contract_groups(): void
    {
        $where = [];
        foreach (config('menu.admin') as $group) {
            $this->walk([$group], function (array $item) use (&$where, $group) {
                if (! empty($item['route'])) {
                    $where[$item['route']] = $group['name'];
                }
                foreach ($item['tabs'] ?? [] as $tab) {
                    $where[$tab['route']] = $group['name'];
                }
            });
        }
        $this->assertSame('Customer & Loan Data', $where['eir-feed.index']);
        $this->assertSame('Customer & Loan Data', $where['eir-takeon.index']);
        $this->assertSame('EIR & Revenue Recognition', $where['eir-as-at.index']);
        $this->assertSame('EIR & Revenue Recognition', $where['eir-governance.index']);
        $this->assertSame('Administration', $where['accounting.financial_periods.index']);
        $this->assertSame('ECL Processing', $where['expected-credit-loss.index']);
    }

    public function test_report_hub_tiles_are_not_menu_items(): void
    {
        $tiles = [];
        $this->walk(config('menu.admin'), function (array $item) use (&$tiles) {
            if (! empty($item['route']) && str_starts_with($item['route'], 'ifrs9-reports.') && $item['route'] !== 'ifrs9-reports.index') {
                $tiles[] = $item['name'];
            }
        });
        $this->assertSame([], $tiles, 'Hub tiles listed in the menu: ' . implode(', ', $tiles));
    }

    private function walk(array $items, callable $fn): void
    {
        foreach ($items as $item) {
            $fn($item);
            if (! empty($item['children'])) {
                $this->walk($item['children'], $fn);
            }
        }
    }
}
