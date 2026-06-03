<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Safety-net model. The legacy clinic ReportsController referenced
 * App\Models\ReportCategory, which never existed in this IFRS 9 build and
 * produced a fatal 500 whenever stale OPcache served the old controller.
 * This lightweight model + table guarantees no code path can fatal again.
 */
class ReportCategory extends Model
{
    protected $table = 'report_categories';

    protected $fillable = ['name', 'symbol', 'active'];

    protected $casts = ['active' => 'boolean'];

    // Self-referential so legacy `ReportCategory::with(['reports'])` resolves
    // to an (empty) collection instead of fataling on a missing Report model.
    public function reports(): HasMany
    {
        return $this->hasMany(self::class, 'report_category_id');
    }
}
