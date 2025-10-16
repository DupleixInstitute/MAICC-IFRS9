<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\GeneralImportConfiguration;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneralImportTemplate extends Model
{
    protected $fillable = [
        'template_name',
        'template_description',
        'source_table_name',
        'import_count',
        'column_count',
        'active_status',
        'updated_by'
    ];

    public function configurations(): HasMany
    {
        return $this->hasMany(GeneralImportConfiguration::class, 'template_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
