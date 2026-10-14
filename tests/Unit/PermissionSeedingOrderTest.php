<?php

namespace Tests\Unit;

use App\Console\Commands\ResetPermissions;
use PHPUnit\Framework\TestCase;

/**
 * Documentation audit of 14 October 2026, item 13: does re-creating the base
 * permissions (PermissionsTableSeeder) bring back the credit-scoring-era
 * permissions LegacyPermissionsCleanupSeeder removes? Not in eir:bootstrap:
 * the base list carries none of them and the bootstrap runs the cleanup after
 * every permission seeder. permissions:reset had re-seeded the base list alone
 * (dropping every eir.* permission); it now runs the bootstrap's sequence.
 */
class PermissionSeedingOrderTest extends TestCase
{
    private function root(string $path): string
    {
        return dirname(__DIR__, 2) . '/' . $path;
    }

    public function test_the_base_permission_list_carries_no_legacy_permission(): void
    {
        preg_match_all("/'name'\s*=>\s*'([^']+)'/", file_get_contents($this->root('database/seeders/PermissionsTableSeeder.php')), $names);
        $prefixes = (new \ReflectionClassConstant(\Database\Seeders\LegacyPermissionsCleanupSeeder::class, 'LEGACY_PREFIXES'))->getValue();
        $this->assertNotEmpty($names[1]);
        foreach ($names[1] as $name) {
            foreach ($prefixes as $prefix) {
                $this->assertFalse($name === $prefix || str_starts_with($name, $prefix . '.'), "{$name} is a legacy permission the cleanup removes");
            }
        }
    }

    public function test_the_bootstrap_runs_the_cleanup_after_every_permission_seeder(): void
    {
        preg_match('/\$classes = \[(.*?)\];/s', file_get_contents($this->root('app/Console/Commands/Bootstrap.php')), $m);
        preg_match_all("/'([A-Za-z]+Seeder)'/", $m[1], $classes);
        $order = array_flip($classes[1]);
        $this->assertArrayHasKey('LegacyPermissionsCleanupSeeder', $order);
        foreach (['PermissionsTableSeeder', 'RolesTableSeeder', 'MaiicAdminPermissionsSeeder', 'TicketsPermissionsSeeder'] as $seeder) {
            $this->assertLessThan($order['LegacyPermissionsCleanupSeeder'], $order[$seeder], "{$seeder} runs after the cleanup");
        }
    }

    public function test_permissions_reset_runs_the_same_sequence_ending_with_the_cleanup(): void
    {
        $this->assertSame('PermissionsTableSeeder', ResetPermissions::SEEDERS[0]);
        $this->assertContains('MaiicAdminPermissionsSeeder', ResetPermissions::SEEDERS);
        $this->assertSame('LegacyPermissionsCleanupSeeder', ResetPermissions::SEEDERS[count(ResetPermissions::SEEDERS) - 1]);
    }
}
