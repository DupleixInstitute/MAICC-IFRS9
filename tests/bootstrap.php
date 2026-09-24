<?php

/**
 * PHPUnit bootstrap.
 *
 * A git worktree of this repository shares vendor/ with the main clone
 * through a junction. PHP resolves the junction to its real path, so the
 * composer autoloader roots App\, Database\ and Tests\ at the main clone,
 * and a test run inside the worktree would exercise the main clone's code
 * while reading the worktree's tests, and could not see a class that only
 * exists in the worktree. This bootstrap re-roots those namespaces at the
 * directory the tests live in. In the main clone the two paths are the
 * same and nothing changes.
 */

$loader = require __DIR__ . '/../vendor/autoload.php';

$root = str_replace(DIRECTORY_SEPARATOR, '/', dirname(__DIR__));
$vendorRoot = str_replace(
    DIRECTORY_SEPARATOR,
    '/',
    dirname((new ReflectionClass(Composer\Autoload\ClassLoader::class))->getFileName(), 3)
);

if (strcasecmp($vendorRoot, $root) !== 0) {
    $namespaces = [
        'App\\' => '/app',
        'Database\\Factories\\' => '/database/factories',
        'Database\\Seeders\\' => '/database/seeders',
        'Tests\\' => '/tests',
    ];

    foreach ($namespaces as $prefix => $dir) {
        $loader->addPsr4($prefix, $root . $dir, true);
    }

    // The optimised class map is consulted before any PSR-4 prefix, so every
    // application class already listed in it must be pointed at the worktree
    // as well, or the main clone's copy would still win.
    // Paths in the map are written as vendor/composer/../../app/..., so the
    // dot-dot segments are collapsed textually before the prefix is compared.
    $canonical = static function (string $path): string {
        $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
        while (($collapsed = preg_replace('#/(?!\.\./)[^/]+/\.\./#', '/', $path, 1)) !== $path) {
            $path = $collapsed;
        }

        return $path;
    };

    $override = [];
    foreach ($loader->getClassMap() as $class => $path) {
        $normalised = $canonical($path);
        foreach ($namespaces as $dir) {
            if (stripos($normalised, $vendorRoot . $dir . '/') === 0) {
                $override[$class] = $root . substr($normalised, strlen($vendorRoot));
                break;
            }
        }
    }
    $loader->addClassMap($override);
}

return $loader;
