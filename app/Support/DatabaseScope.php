<?php

namespace App\Support;

/**
 * Files under storage/app that belong to one database (14 October 2026).
 *
 * One code tree serves several databases (the live one, the clones the
 * bootstrap and the checks build), switched by DB_DATABASE. The Maintenance
 * console's run history (storage/app/command-runs) and the API inbox of the
 * E-Banker feed (storage/app/ebanker-inbox) were shared by all of them, so a
 * run on a scratch clone showed in the live console's history and a pack
 * posted for one database could be landed into another by the poller. Each
 * now lives in a folder named after the database in use.
 */
class DatabaseScope
{
    /** The database in use, as a folder name. */
    public static function name(): string
    {
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database", '');
        if ($database === '' || $database === ':memory:') {
            $database = $connection . ($database === ':memory:' ? '-memory' : '');
        } elseif (str_contains($database, '/') || str_contains($database, '\\')) {
            $database = pathinfo($database, PATHINFO_FILENAME); // an SQLite file
        }

        return preg_replace('/[^A-Za-z0-9_.-]/', '_', $database) ?: 'default';
    }

    /** storage/app/{area}/{database}, created when missing. */
    public static function storagePath(string $area): string
    {
        $dir = storage_path('app/' . trim($area, '/\\') . '/' . self::name());
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }
}
