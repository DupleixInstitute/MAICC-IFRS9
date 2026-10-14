<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * The breadcrumb trail of the page in view, read from the menu tree
 * (config/menu.php, as filtered for the user): block heading > entry >
 * sub-entry > tab. The page is matched the way the sidebar lights it up
 * (resources/js/Jetstream/menuMatch.js): a route named "x.index" also claims
 * "x.*", the longer pattern wins, and a tab with params (tab=cashflows) wins
 * when the address carries them.
 *
 * Each crumb links to its node: an entry's own route, a section's first tab,
 * a group's or a heading's first entry. 'exact' says whether the last crumb
 * is the page itself; when it is not (a detail page under its list, a report
 * under the hub), the layout adds the page title as the last crumb.
 */
final class Breadcrumbs
{
    /**
     * @param  array  $menu  the menu as the user sees it
     * @param  array  $query  the request's query string
     * @return array{crumbs: list<array{label: string, href: ?string}>, exact: bool}
     */
    public static function trail(array $menu, ?string $routeName, array $query = []): array
    {
        $none = ['crumbs' => [], 'exact' => false];
        if (! $routeName) {
            return $none;
        }

        $best = null;
        foreach ($menu as $top) {
            self::search($top, [$top], $routeName, $query, $best);
        }
        // a page no entry claims, but whose route is a sibling of one
        // (internal-grading.grades beside internal-grading.profiles): its
        // place is under that entry, and the page closes the trail
        if (! $best) {
            foreach ($menu as $top) {
                self::search($top, [$top], $routeName, $query, $best, true);
            }
        }
        if (! $best) {
            return $none;
        }

        $crumbs = [];
        $top = $best['path'][0];
        if (! empty($top['heading'])) {
            $first = collect($menu)->first(fn ($item) => ($item['heading'] ?? null) === $top['heading']);
            $crumbs[] = ['label' => $top['heading'], 'href' => self::href($first)];
        }
        foreach ($best['path'] as $node) {
            $crumbs[] = ['label' => $node['name'], 'href' => self::href($node)];
        }
        $tab = $best['tab'];
        $section = end($best['path']);
        if ($tab && count($section['tabs'] ?? []) > 1 && $tab['name'] !== $section['name']) {
            $crumbs[] = ['label' => $tab['name'], 'href' => self::link($tab['route'], $tab['params'] ?? [])];
        }

        return ['crumbs' => $crumbs, 'exact' => $best['exact']];
    }

    /** Walk one node: keep the most specific match of the whole menu (the first on a tie). */
    private static function search(array $node, array $path, string $routeName, array $query, ?array &$best, bool $siblings = false): void
    {
        $score = fn (string $menuRoute) => $siblings ? self::siblingScore($menuRoute, $routeName) : self::score($menuRoute, $routeName);
        $offer = function (int $score, ?array $tab, bool $exact) use (&$best, $path) {
            if ($score > 0 && (! $best || $score > $best['score'])) {
                $best = ['score' => $score, 'path' => $path, 'tab' => $tab, 'exact' => $exact];
            }
        };

        if (! empty($node['tabs'])) {
            foreach ($node['tabs'] as $tab) {
                $points = $score($tab['route']);
                if (! $points) {
                    continue;
                }
                $state = self::paramsState($tab, $node['tabs'], $query);
                if ($state === 'miss') {
                    continue;
                }
                $points += ['match' => 1000, 'default' => 500, 'none' => 0][$state];
                $offer($points, $tab, $tab['route'] === $routeName);
            }
        } elseif (empty($node['dropdown']) && ! empty($node['route'])) {
            $points = $score($node['route']);
            if (! $siblings) {
                if (! $points && ($node['route_check'] ?? null) && Str::is($node['route_check'], $routeName)) {
                    $points = strlen($node['route_check']);
                }
                foreach ($node['match'] ?? [] as $pattern) {
                    if (Str::is($pattern, $routeName)) {
                        $points = max($points, strlen($pattern));
                    }
                }
            }
            $offer($points, null, $node['route'] === $routeName);
        }

        foreach ($node['children'] ?? [] as $child) {
            self::search($child, [...$path, $child], $routeName, $query, $best, $siblings);
        }
    }

    /** The length of the most specific pattern of a menu route that names the page, or 0. */
    private static function score(string $menuRoute, string $routeName): int
    {
        $patterns = [$menuRoute];
        if (str_ends_with($menuRoute, '.index')) {
            $patterns[] = substr($menuRoute, 0, -strlen('.index')) . '.*';
        }
        $best = 0;
        foreach ($patterns as $p) {
            if (Str::is($p, $routeName)) {
                $best = max($best, strlen($p));
            }
        }

        return $best;
    }

    /** The length of the shared prefix when the page's route is a sibling of a menu route (a.b beside a.c), or 0. */
    private static function siblingScore(string $menuRoute, string $routeName): int
    {
        $cut = strrpos($menuRoute, '.');
        if ($cut === false) {
            return 0;
        }
        $prefix = substr($menuRoute, 0, $cut);

        return str_starts_with($routeName, $prefix . '.') ? strlen($prefix) : 0;
    }

    /** A tab with params is one view of a screen: 'match' with them in the address, 'default' for the first such tab when none are, else 'miss'. */
    private static function paramsState(array $tab, array $tabs, array $query): string
    {
        $params = $tab['params'] ?? [];
        if (! $params) {
            return 'none';
        }
        $all = true;
        $unset = true;
        foreach ($params as $k => $v) {
            $got = $query[$k] ?? null;
            if ((string) $got !== (string) $v) {
                $all = false;
            }
            if ($got !== null && $got !== '') {
                $unset = false;
            }
        }
        if ($all) {
            return 'match';
        }
        $first = collect($tabs)->first(fn ($t) => $t['route'] === $tab['route'] && ! empty($t['params']));

        return $unset && $first === $tab ? 'default' : 'miss';
    }

    /** Where a crumb goes: the node's own route, a section's first tab, else its first entry. */
    private static function href(?array $node): ?string
    {
        if (! $node) {
            return null;
        }
        if (! empty($node['tabs'])) {
            return self::link($node['tabs'][0]['route'], $node['tabs'][0]['params'] ?? []);
        }
        if (empty($node['dropdown']) && ! empty($node['route']) && empty($node['download'])) {
            return self::link($node['route']);
        }
        foreach ($node['children'] ?? [] as $child) {
            if ($href = self::href($child)) {
                return $href;
            }
        }

        return null;
    }

    private static function link(string $name, array $params = []): ?string
    {
        return Route::has($name) ? route($name, $params, false) : null;
    }
}
