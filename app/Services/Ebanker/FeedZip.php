<?php

namespace App\Services\Ebanker;

use Illuminate\Support\Facades\Log;
use ZipArchive;

/**
 * The feed zip, opened with an explicit check on every entry name before
 * anything is written (system audit of 9 October 2026, finding M14: the
 * extraction relied on PHP's own sanitising and nothing in the system
 * refused an entry that climbs out of the pack folder).
 *
 * An entry is refused when its name contains "..", starts with "/" or
 * "\", or contains ":" (a drive letter or a stream). The API door refuses
 * the whole pack and names the entries; the poller and the upload screen
 * extract the rest and log each entry they left out, so a pack is never
 * silently partial.
 */
class FeedZip
{
    /** @return list<string> the entry names that are not safe to extract */
    public static function unsafeEntries(ZipArchive $zip): array
    {
        $out = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (! self::isSafe($name)) {
                $out[] = $name;
            }
        }

        return $out;
    }

    public static function isSafe(string $name): bool
    {
        return $name !== ''
            && ! str_contains($name, '..')
            && ! str_starts_with($name, '/')
            && ! str_starts_with($name, '\\')
            && ! str_contains($name, ':');
    }

    /**
     * Extract every safe entry into the folder, skipping and logging the
     * rest. Returns how many entries were written and which were skipped.
     *
     * @return array{extracted:int,skipped:list<string>}
     */
    public static function extract(ZipArchive $zip, string $dir, string $context = 'feed'): array
    {
        $safe = [];
        $skipped = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (self::isSafe($name)) {
                $safe[] = $name;
            } else {
                $skipped[] = $name;
                Log::warning(sprintf('E-Banker %s zip: entry "%s" skipped, its name climbs out of the pack folder (audit finding M14)', $context, $name));
            }
        }
        if ($safe !== []) {
            $zip->extractTo($dir, $safe);
        }

        return ['extracted' => count($safe), 'skipped' => $skipped];
    }
}
