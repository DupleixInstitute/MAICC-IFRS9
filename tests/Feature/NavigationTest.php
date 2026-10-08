<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The navigation tree is the single source of truth (spec v4 section 11.3):
 * every leaf names a registered route, every group carries an accent from
 * the map the build includes, and the six working groups are present in
 * the order the work is done.
 */
class NavigationTest extends TestCase
{
    protected $seed = false;

    private const ACCENTS = ['teal', 'amber', 'sky', 'indigo', 'emerald', 'violet', 'slate', 'rose'];

    public function test_every_leaf_names_a_registered_route(): void
    {
        $missing = [];
        $this->walk(config('menu.admin'), function (array $item) use (&$missing) {
            if (empty($item['dropdown']) && ! empty($item['route']) && ! Route::has($item['route'])) {
                $missing[] = $item['name'] . ' -> ' . $item['route'];
            }
        });
        $this->assertSame([], $missing, 'Menu leaves without a registered route: ' . implode(', ', $missing));
    }

    public function test_every_group_has_an_accent_from_the_map(): void
    {
        $bad = [];
        $this->walk(config('menu.admin'), function (array $item) use (&$bad) {
            if (! empty($item['dropdown']) && ! in_array($item['accent'] ?? '', self::ACCENTS, true)) {
                $bad[] = $item['name'];
            }
        });
        $this->assertSame([], $bad, 'Groups without a known accent: ' . implode(', ', $bad));
    }

    public function test_the_six_working_groups_are_in_order(): void
    {
        $groups = array_values(array_map(fn ($g) => $g['name'], array_filter(config('menu.admin'), fn ($g) => ! empty($g['dropdown']))));
        $this->assertSame(['Data Foundation', 'Governance Centre', 'Financial Modelling', 'Risk & Regulatory', 'Monitoring', 'Report Hub', 'System Documentation', 'Administration'], $groups);
        $this->assertSame(['teal', 'amber', 'sky', 'indigo', 'emerald', 'violet', 'slate', 'rose'], array_values(array_map(fn ($g) => $g['accent'], array_filter(config('menu.admin'), fn ($g) => ! empty($g['dropdown'])))));
    }

    public function test_the_new_screens_sit_where_the_spec_puts_them(): void
    {
        $where = [];
        foreach (config('menu.admin') as $group) {
            $this->walk([$group], function (array $item) use (&$where, $group) {
                if (! empty($item['route'])) {
                    $where[$item['route']] = $group['name'];
                }
            });
        }
        $this->assertSame('Data Foundation', $where['eir-feed.index']);
        $this->assertSame('Data Foundation', $where['eir-takeon.index']);
        $this->assertSame('Report Hub', $where['eir-as-at.index']);
        $this->assertSame('Governance Centre', $where['eir-governance.index']);
        $this->assertSame('Governance Centre', $where['accounting.financial_periods.index']);
        $this->assertSame('Financial Modelling', $where['expected-credit-loss.index']);
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
