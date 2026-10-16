<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The documents behind one decision, wherever they were uploaded (Staging
 * Module Specification v1, section 8.7): those attached to it and those
 * linked to it from another decision. Every evidence check counts both, so a
 * minute uploaded once serves each decision it covers. A removed document
 * counts for none of them.
 */
final class SupportingDocuments
{
    public static function count(string $type, int $id): int
    {
        return self::forItem($type, $id)->where('removed', false)->count();
    }

    /** @return Collection<int, array{id:int, name:string, type:?string, description:?string, sha256:?string, removed:bool, linked:bool, from:?string}> */
    public static function forItem(string $type, int $id): Collection
    {
        if (! Schema::hasTable('supporting_documents')) {
            return collect();
        }
        $own = DB::table('supporting_documents')->where('documentable_type', $type)->where('documentable_id', $id)->orderBy('id')->get()
            ->map(fn ($d) => self::row($d, false));
        if (! Schema::hasTable('supporting_document_links')) {
            return $own->values();
        }
        $linked = DB::table('supporting_document_links')->join('supporting_documents', 'supporting_documents.id', '=', 'supporting_document_links.supporting_document_id')
            ->where('supporting_document_links.documentable_type', $type)->where('supporting_document_links.documentable_id', $id)
            ->orderBy('supporting_document_links.id')->get(['supporting_documents.*'])
            ->map(fn ($d) => self::row($d, true));

        return $own->concat($linked)->values();
    }

    private static function row(object $d, bool $linked): array
    {
        return ['id' => (int) $d->id, 'name' => (string) $d->original_name, 'type' => $d->document_type, 'description' => $d->description, 'sha256' => $d->hash,
            'removed' => $d->deleted_at !== null, 'linked' => $linked, 'from' => $linked ? $d->documentable_type . ' ' . $d->documentable_id : null];
    }
}
