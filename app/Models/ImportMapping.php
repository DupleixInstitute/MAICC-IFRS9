<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportMapping extends Model
{
    protected $table = 'import_mappings';

    protected $fillable = [
        'import_type',
        'source_header',
        'target_field',
        'transform',
    ];

    public function scopeForType($query, string $importType)
    {
        return $query->where('import_type', $importType);
    }

    /**
     * Mapping template for one import type: [source_header => target_field].
     */
    public static function templateFor(string $importType): array
    {
        return static::forType($importType)
            ->pluck('target_field', 'source_header')
            ->toArray();
    }
}
