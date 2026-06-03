<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SicrItem extends Model
{
    protected $table = 'finance_sicr_items';

    protected $fillable = [
        'group_id',
        'name',
        'active',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(SicrGroup::class, 'group_id');
    }
}
