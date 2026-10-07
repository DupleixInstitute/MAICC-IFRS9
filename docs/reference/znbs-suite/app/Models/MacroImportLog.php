<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per macro import / write process (CSV, World Bank, IMF, manual).
 * Persists the full validation outcome + errors so nothing is lost after the
 * request, and powers the Recent Imports history + error log.
 */
class MacroImportLog extends Model
{
    protected $fillable = [
        'batch_ref', 'source', 'import_mode', 'filename', 'reporting_period', 'checksum',
        'total_rows', 'valid_rows', 'created_rows', 'updated_rows', 'rejected_rows',
        'errors', 'error_summary', 'status', 'imported_by', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'errors'       => 'array',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
        'total_rows'   => 'integer',
        'valid_rows'   => 'integer',
        'created_rows' => 'integer',
        'updated_rows' => 'integer',
        'rejected_rows' => 'integer',
    ];

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
